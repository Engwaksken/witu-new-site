<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/includes/admin-enhancements.php';
adminTrackActivity($conn);

$has_permission = false;

if (function_exists('hasPermission')) {
    $has_permission = hasPermission($conn, 'manage_partner') || hasPermission($conn, 'manage_content');
} else {
    $has_permission = true;
}

if (!$has_permission) {
    die('<h2>Access Denied</h2><p>You don\'t have permission to manage the partner page. Please contact your administrator.</p>');
}

$page_title = 'Manage Partner Page';

if (!function_exists('h')) {
    function h($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function tableColumnExists(mysqli $conn, string $table, string $column): bool {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
    ");
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int)($row['total'] ?? 0) > 0;
}

function ensurePartnerBackgroundColumns(mysqli $conn): void {
    $columns = [
        'bg_type' => "ALTER TABLE partner ADD COLUMN bg_type ENUM('color','image','video') NOT NULL DEFAULT 'color' AFTER featured_image",
        'bg_color' => "ALTER TABLE partner ADD COLUMN bg_color VARCHAR(30) NULL DEFAULT '#ffffff' AFTER bg_type",
        'bg_image' => "ALTER TABLE partner ADD COLUMN bg_image VARCHAR(255) NULL AFTER bg_color",
        'bg_video' => "ALTER TABLE partner ADD COLUMN bg_video VARCHAR(255) NULL AFTER bg_image",
        'overlay_color' => "ALTER TABLE partner ADD COLUMN overlay_color VARCHAR(50) NULL DEFAULT 'rgba(11,44,58,.55)' AFTER bg_video",
    ];

    foreach ($columns as $column => $sql) {
        if (!tableColumnExists($conn, 'partner', $column)) {
            $conn->query($sql);
        }
    }
}

function ensureSortOrdersPartnerBlocks(mysqli $conn): void {
    $res = $conn->query("SELECT id FROM partner_blocks ORDER BY sort_order ASC, id ASC");
    if (!$res) return;

    $i = 1;
    while ($r = $res->fetch_assoc()) {
        $id = (int)$r['id'];
        $st = $conn->prepare("UPDATE partner_blocks SET sort_order=? WHERE id=?");
        if ($st) {
            $st->bind_param("ii", $i, $id);
            $st->execute();
            $st->close();
        }
        $i++;
    }
}

function normalizeColor($v): string {
    $v = trim((string)$v);
    if ($v === '') return '';
    if (preg_match('/^#([0-9a-fA-F]{3}){1,2}$/', $v)) return $v;
    if (preg_match('/^(rgba?|hsla?)\(/i', $v)) return $v;
    return $v;
}

function uploadPartnerBackgroundFile(string $field, string $type): string {
    if (empty($_FILES[$field]['name']) || empty($_FILES[$field]['tmp_name'])) {
        return '';
    }

    if (!is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return '';
    }

    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));

    $allowedImages = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $allowedVideos = ['mp4', 'webm', 'ogg'];

    if ($type === 'image' && !in_array($ext, $allowedImages, true)) {
        return '';
    }

    if ($type === 'video' && !in_array($ext, $allowedVideos, true)) {
        return '';
    }

    $dir = __DIR__ . '/../uploads/partner/backgrounds/';

    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $filename = 'partner_bg_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    $target = $dir . $filename;

    if (move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        return 'uploads/partner/backgrounds/' . $filename;
    }

    return '';
}

ensurePartnerBackgroundColumns($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_page') {
        $id = (int)($_POST['id'] ?? 0);

        $page_title_txt   = clean($conn, $_POST['page_title'] ?? '');
        $page_subtitle    = clean($conn, $_POST['page_subtitle'] ?? '');
        $intro_title      = clean($conn, $_POST['intro_title'] ?? '');
        $intro_text       = trim($_POST['intro_text'] ?? '');
        $right_title      = clean($conn, $_POST['right_title'] ?? '');
        $other_ways_title = clean($conn, $_POST['other_ways_title'] ?? '');
        $other_email      = clean($conn, $_POST['other_email'] ?? '');
        $other_phone      = clean($conn, $_POST['other_phone'] ?? '');
        $is_active        = isset($_POST['is_active']) ? 1 : 0;

        $bg_type       = strtolower(trim((string)($_POST['bg_type'] ?? 'color')));
        $bg_color      = normalizeColor($_POST['bg_color'] ?? '#ffffff');
        $overlay_color = normalizeColor($_POST['overlay_color'] ?? 'rgba(11,44,58,.55)');

        if (!in_array($bg_type, ['color', 'image', 'video'], true)) {
            $bg_type = 'color';
        }

        if ($bg_color === '') {
            $bg_color = '#ffffff';
        }

        if ($overlay_color === '') {
            $overlay_color = 'rgba(11,44,58,.55)';
        }

        if ($page_title_txt === '') {
            setFlashMessage('error', 'Page title is required.');
            header("Location: partner.php");
            exit;
        }

        $existing = null;

        if ($id > 0) {
            $st = $conn->prepare("SELECT * FROM partner WHERE id=? LIMIT 1");
            $st->bind_param("i", $id);
            $st->execute();
            $existing = $st->get_result()->fetch_assoc();
            $st->close();
        }

        $bg_image = $existing['bg_image'] ?? '';
        $bg_video = $existing['bg_video'] ?? '';

        $newBgImage = uploadPartnerBackgroundFile('bg_image_file', 'image');
        $newBgVideo = uploadPartnerBackgroundFile('bg_video_file', 'video');

        if ($newBgImage !== '') {
            $bg_image = $newBgImage;
        }

        if ($newBgVideo !== '') {
            $bg_video = $newBgVideo;
        }

        if (!empty($_POST['remove_bg_image'])) {
            $bg_image = '';
        }

        if (!empty($_POST['remove_bg_video'])) {
            $bg_video = '';
        }

        $new_featured = '';

        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === 0) {
            $upload = uploadFile($_FILES['featured_image'], 'partner');

            if (!empty($upload['success'])) {
                $new_featured = $upload['filename'];
            } else {
                setFlashMessage('error', $upload['message'] ?? 'Failed to upload featured image.');
                header("Location: partner.php");
                exit;
            }
        }

        if ($is_active === 1) {
            $conn->query("UPDATE partner SET is_active=0");
        }

        if ($id > 0) {
            if ($new_featured) {
                $st = $conn->prepare("SELECT featured_image FROM partner WHERE id=?");
                if ($st) {
                    $st->bind_param("i", $id);
                    $st->execute();
                    $old = $st->get_result()->fetch_assoc();
                    $st->close();

                    if ($old && !empty($old['featured_image'])) {
                        deleteFile($old['featured_image'], 'partner');
                    }
                }
            }

            $sql = "
                UPDATE partner
                SET page_title=?,
                    page_subtitle=?,
                    intro_title=?,
                    intro_text=?,
                    right_title=?,
                    other_ways_title=?,
                    other_email=?,
                    other_phone=?,
                    bg_type=?,
                    bg_color=?,
                    bg_image=?,
                    bg_video=?,
                    overlay_color=?,
                    is_active=?
            ";

            $types = "sssssssssssssii";
            $params = [
                $page_title_txt,
                $page_subtitle,
                $intro_title,
                $intro_text,
                $right_title,
                $other_ways_title,
                $other_email,
                $other_phone,
                $bg_type,
                $bg_color,
                $bg_image,
                $bg_video,
                $overlay_color,
                $is_active
            ];

            if ($new_featured) {
                $sql .= ", featured_image=?";
                $types .= "s";
                $params[] = $new_featured;
            }

            $sql .= " WHERE id=?";
            $params[] = $id;

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $stmt->close();

            setFlashMessage('success', 'Partner page updated successfully.');
        } else {
            $stmt = $conn->prepare("
                INSERT INTO partner
                (
                    page_title,
                    page_subtitle,
                    intro_title,
                    intro_text,
                    right_title,
                    other_ways_title,
                    other_email,
                    other_phone,
                    featured_image,
                    bg_type,
                    bg_color,
                    bg_image,
                    bg_video,
                    overlay_color,
                    is_active
                )
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");

            $stmt->bind_param(
                "ssssssssssssssi",
                $page_title_txt,
                $page_subtitle,
                $intro_title,
                $intro_text,
                $right_title,
                $other_ways_title,
                $other_email,
                $other_phone,
                $new_featured,
                $bg_type,
                $bg_color,
                $bg_image,
                $bg_video,
                $overlay_color,
                $is_active
            );

            $stmt->execute();
            $stmt->close();

            setFlashMessage('success', 'Partner page created successfully.');
        }

        header("Location: partner.php");
        exit;
    }

    if ($action === 'save_block') {
        $id = (int)($_POST['block_id'] ?? 0);

        $title        = clean($conn, $_POST['title'] ?? '');
        $body         = trim($_POST['body'] ?? '');
        $icon_class   = clean($conn, $_POST['icon_class'] ?? '');
        $bg_color     = normalizeColor($_POST['bg_color'] ?? '');
        $border_color = normalizeColor($_POST['border_color'] ?? '');
        $title_color  = normalizeColor($_POST['title_color'] ?? '');
        $sort_order   = (int)($_POST['sort_order'] ?? 0);
        $is_active    = isset($_POST['block_is_active']) ? 1 : 0;

        if ($title === '' || $body === '') {
            setFlashMessage('error', 'Block title and body are required.');
            header("Location: partner.php");
            exit;
        }

        if ($id > 0) {
            $stmt = $conn->prepare("
                UPDATE partner_blocks
                SET title=?,
                    body=?,
                    icon_class=?,
                    bg_color=?,
                    border_color=?,
                    title_color=?,
                    sort_order=?,
                    is_active=?
                WHERE id=?
            ");
            $stmt->bind_param(
                "ssssssiii",
                $title,
                $body,
                $icon_class,
                $bg_color,
                $border_color,
                $title_color,
                $sort_order,
                $is_active,
                $id
            );

            $stmt->execute();
            $stmt->close();

            setFlashMessage('success', 'Block updated successfully.');
        } else {
            if ($sort_order <= 0) {
                $r = $conn->query("SELECT IFNULL(MAX(sort_order),0) AS mx FROM partner_blocks");
                $mx = $r ? $r->fetch_assoc() : ['mx' => 0];
                $sort_order = ((int)($mx['mx'] ?? 0)) + 1;
            }

            $stmt = $conn->prepare("
                INSERT INTO partner_blocks
                (title, body, icon_class, bg_color, border_color, title_color, sort_order, is_active)
                VALUES (?,?,?,?,?,?,?,?)
            ");
            $stmt->bind_param(
                "ssssssii",
                $title,
                $body,
                $icon_class,
                $bg_color,
                $border_color,
                $title_color,
                $sort_order,
                $is_active
            );

            $stmt->execute();
            $stmt->close();

            setFlashMessage('success', 'Block created successfully.');
        }

        ensureSortOrdersPartnerBlocks($conn);
        header("Location: partner.php");
        exit;
    }

    if ($action === 'delete_block') {
        $id = (int)($_POST['block_id'] ?? 0);

        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM partner_blocks WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            ensureSortOrdersPartnerBlocks($conn);
            setFlashMessage('success', 'Block deleted successfully.');
        }

        header("Location: partner.php");
        exit;
    }

    if ($action === 'toggle_block') {
        $id = (int)($_POST['block_id'] ?? 0);
        $val = (int)($_POST['value'] ?? 0);

        $stmt = $conn->prepare("UPDATE partner_blocks SET is_active=? WHERE id=?");
        $stmt->bind_param("ii", $val, $id);
        $ok = $stmt->execute();
        $stmt->close();

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => (bool)$ok]);
        exit;
    }

    if ($action === 'reorder_blocks') {
        $order = $_POST['order'] ?? [];

        if (!is_array($order)) {
            $order = [];
        }

        $i = 1;
        $stmt = $conn->prepare("UPDATE partner_blocks SET sort_order=? WHERE id=?");

        foreach ($order as $id) {
            $id = (int)$id;
            if ($id <= 0) continue;

            $stmt->bind_param("ii", $i, $id);
            $stmt->execute();
            $i++;
        }

        $stmt->close();

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true]);
        exit;
    }
}

$about = null;

$res = $conn->query("SELECT * FROM partner ORDER BY is_active DESC, id DESC LIMIT 1");
if ($res) {
    $about = $res->fetch_assoc();
}

if (!$about) {
    $about = [
        'id' => 0,
        'page_title' => 'Partner With Us',
        'page_subtitle' => 'Collaborate with us to expand opportunities for women and girls in technology.',
        'intro_title' => 'Become a Partner',
        'intro_text' => 'Collaborate with us through funding, training, mentorship, technology, and strategic support.',
        'right_title' => 'Why Partner?',
        'other_ways_title' => 'Partnership Contacts',
        'other_email' => 'info@witu.org',
        'other_phone' => '+256 392 177 980',
        'featured_image' => '',
        'bg_type' => 'color',
        'bg_color' => '#ffffff',
        'bg_image' => '',
        'bg_video' => '',
        'overlay_color' => 'rgba(11,44,58,.55)',
        'is_active' => 1
    ];
}

$blocks = [];

$res = $conn->query("SELECT * FROM partner_blocks ORDER BY sort_order ASC, id ASC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $blocks[] = $r;
    }
}

include 'layout/header.php';
include 'layout/sidebar.php';
?>

<style>
.two-col {
    display: grid;
    grid-template-columns: 1.1fr .9fr;
    gap: 18px;
}

@media(max-width:1100px) {
    .two-col {
        grid-template-columns: 1fr;
    }
}

.card {
    background: #fff;
    border: 1px solid #eef2f7;
    border-radius: 14px;
    box-shadow: 0 10px 28px rgba(0,0,0,.05);
    overflow: hidden;
}

.card .card-head {
    padding: 14px 16px;
    border-bottom: 1px solid #eef2f7;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.card .card-head h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 900;
    color: #111827;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card .card-body {
    padding: 16px;
}

.muted {
    color: #6b7280;
}

.hr {
    height: 1px;
    background: #eef2f7;
    margin: 14px 0;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.form-grid .full {
    grid-column: 1 / -1;
}

input[type="text"],
input[type="number"],
input[type="color"],
textarea,
select,
input[type="file"] {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    font-size: 14px;
    outline: none;
}

input[type="color"] {
    min-height: 42px;
    padding: 4px;
}

textarea {
    min-height: 110px;
    resize: vertical;
}

label {
    font-size: 12px;
    font-weight: 800;
    color: #374151;
    display: block;
    margin: 0 0 6px;
}

.small {
    font-size: 12px;
    color: #6b7280;
    margin-top: 6px;
}

.thumb {
    width: 100%;
    max-width: 520px;
    height: 180px;
    border-radius: 14px;
    border: 1px solid #e5e7eb;
    object-fit: cover;
    background: #f3f4f6;
}

.actions-row {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
}

.btn {
    border: 1px solid transparent;
    border-radius: 10px;
    padding: 10px 14px;
    font-weight: 800;
    cursor: pointer;
    display: inline-flex;
    gap: 8px;
    align-items: center;
}

.btn-primary {
    background: #f86505;
    color: #fff;
}

.btn-primary:hover {
    background: #fd6501;
}

.btn-secondary {
    background: #f3f4f6;
    color: #111827;
    border-color: #e5e7eb;
}

.btn-danger {
    background: #ef4444;
    color: #fff;
}

.btn-sm {
    padding: 8px 10px;
    border-radius: 10px;
}

.badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 900;
}

.badge-on {
    background: #dcfce7;
    color: #166534;
}

.badge-off {
    background: #fee2e2;
    color: #991b1b;
}

.switch {
    position: relative;
    display: inline-block;
    width: 48px;
    height: 26px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    inset: 0;
    background: #e5e7eb;
    transition: .2s;
    border-radius: 999px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    top: 3px;
    background: white;
    transition: .2s;
    border-radius: 999px;
    box-shadow: 0 2px 8px rgba(0,0,0,.15);
}

.switch input:checked + .slider {
    background: #10b981;
}

.switch input:checked + .slider:before {
    transform: translateX(22px);
}

.block-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.block-item {
    border: 1px solid #eef2f7;
    border-radius: 14px;
    padding: 12px;
    display: grid;
    grid-template-columns: 28px 1fr auto;
    gap: 12px;
    align-items: center;
    background: #fff;
}

.drag-handle {
    cursor: grab;
    color: #9ca3af;
    font-size: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.block-title {
    font-weight: 900;
    color: #111827;
    margin: 0;
}

.block-meta {
    font-size: 12px;
    color: #6b7280;
    margin-top: 3px;
}

.block-actions {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.block-preview {
    display: inline-flex;
    gap: 10px;
    align-items: flex-start;
    padding: 10px 12px;
    border-radius: 12px;
    border: 1px solid rgba(0,0,0,.06);
    margin-top: 8px;
}

.block-preview i {
    margin-top: 2px;
}

.block-preview .bp-title {
    font-weight: 900;
    margin: 0 0 4px;
}

.block-preview .bp-body {
    margin: 0;
    color: #374151;
    line-height: 1.6;
    font-size: 13px;
    max-width: 520px;
}

.edit-panel {
    border-top: 1px solid #eef2f7;
    margin-top: 14px;
    padding-top: 14px;
}

.inline-note {
    font-size: 12px;
    color: #6b7280;
    margin: 0;
}

.background-preview {
    min-height: 190px;
    border-radius: 16px;
    overflow: hidden;
    position: relative;
    display: flex;
    align-items: center;
    padding: 28px;
    color: #fff;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    border: 1px solid #e5e7eb;
}

.background-preview video {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    z-index: 0;
}

.background-preview::after {
    content: "";
    position: absolute;
    inset: 0;
    background: var(--overlay-color, rgba(11,44,58,.55));
    z-index: 1;
}

.background-preview-content {
    position: relative;
    z-index: 2;
}

.background-preview h3 {
    margin: 0 0 8px;
    color: #fff;
    font-size: 28px;
    font-weight: 900;
}

.background-preview p {
    margin: 0;
    max-width: 760px;
    color: rgba(255,255,255,.92);
    line-height: 1.7;
}

.current-file {
    font-size: 12px;
    color: #6b7280;
    margin-top: 6px;
    word-break: break-all;
}

.bg-help {
    padding: 10px 12px;
    border: 1px dashed #e5e7eb;
    border-radius: 10px;
    background: #fafafa;
    color: #6b7280;
    font-size: 13px;
}
</style>

<div class="content-header">
    <h2><i class="fas fa-handshake" style="color:#f86505;"></i> Manage Partner Page</h2>
</div>

<?php $flash = getFlashMessage(); if ($flash): ?>
    <div class="alert alert-<?php echo h($flash['type']); ?>">
        <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo h($flash['message']); ?>
    </div>
<?php endif; ?>

<div class="two-col">
    <div class="card">
        <div class="card-head">
            <h3><i class="fas fa-file-alt"></i> Partner Page Content</h3>
            <span class="badge <?php echo !empty($about['is_active']) ? 'badge-on' : 'badge-off'; ?>">
                <?php echo !empty($about['is_active']) ? 'ACTIVE' : 'INACTIVE'; ?>
            </span>
        </div>

        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_page">
                <input type="hidden" name="id" value="<?php echo (int)$about['id']; ?>">

                <div class="form-grid">
                    <div class="full">
                        <label>Hero Title *</label>
                        <input type="text" name="page_title" value="<?php echo h($about['page_title'] ?? ''); ?>" required>
                    </div>

                    <div class="full">
                        <label>Hero Subtitle</label>
                        <textarea name="page_subtitle" rows="2"><?php echo h($about['page_subtitle'] ?? ''); ?></textarea>
                    </div>

                    <div class="full">
                        <label>Featured Hero Image</label>
                        <input type="file" name="featured_image" accept="image/*">
                        <div class="small">Uploads to: <strong>uploads/partner/</strong></div>

                        <?php if (!empty($about['featured_image'])): ?>
                            <div style="margin-top:10px;">
                                <img class="thumb" src="../uploads/partner/<?php echo h($about['featured_image']); ?>" alt="">
                            </div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label>Left Column Title</label>
                        <input type="text" name="intro_title" value="<?php echo h($about['intro_title'] ?? ''); ?>">
                    </div>

                    <div>
                        <label>Right Column Title</label>
                        <input type="text" name="right_title" value="<?php echo h($about['right_title'] ?? ''); ?>">
                    </div>

                    <div class="full">
                        <label>Left Column Intro Text</label>
                        <textarea name="intro_text"><?php echo h($about['intro_text'] ?? ''); ?></textarea>
                    </div>

                    <div>
                        <label>Other Ways Title</label>
                        <input type="text" name="other_ways_title" value="<?php echo h($about['other_ways_title'] ?? ''); ?>">
                    </div>

                    <div>
                        <label>Other Email</label>
                        <input type="text" name="other_email" value="<?php echo h($about['other_email'] ?? ''); ?>">
                    </div>

                    <div>
                        <label>Other Phone</label>
                        <input type="text" name="other_phone" value="<?php echo h($about['other_phone'] ?? ''); ?>">
                    </div>

                    <div class="full">
                        <div class="hr"></div>
                        <h3 style="margin:0 0 12px;font-size:16px;font-weight:900;color:#111827;">
                            <i class="fas fa-layer-group"></i> Partner Section Background
                        </h3>
                    </div>

                    <div>
                        <label>Background Type</label>
                        <select name="bg_type" id="bgType" onchange="toggleBackgroundFields(); updateBackgroundPreview();">
                            <option value="color" <?php echo ($about['bg_type'] ?? 'color') === 'color' ? 'selected' : ''; ?>>Color</option>
                            <option value="image" <?php echo ($about['bg_type'] ?? '') === 'image' ? 'selected' : ''; ?>>Image</option>
                            <option value="video" <?php echo ($about['bg_type'] ?? '') === 'video' ? 'selected' : ''; ?>>Video</option>
                        </select>
                    </div>

                    <div>
                        <label>Background Color</label>
                        <input type="color" name="bg_color" id="bgColor" value="<?php echo h($about['bg_color'] ?: '#ffffff'); ?>" onchange="updateBackgroundPreview();">
                    </div>

                    <div class="full">
                        <label>Overlay Color</label>
                        <input type="text" name="overlay_color" id="overlayColor" value="<?php echo h($about['overlay_color'] ?: 'rgba(11,44,58,.55)'); ?>" onkeyup="updateBackgroundPreview();">
                        <div class="small">Use transparent RGBA, for example: <code>rgba(11,44,58,.55)</code></div>
                    </div>

                    <div class="full" id="imageField">
                        <label>Background Image</label>
                        <input type="file" name="bg_image_file" accept="image/*">

                        <?php if (!empty($about['bg_image'])): ?>
                            <div class="current-file">Current image: <?php echo h($about['bg_image']); ?></div>
                            <label style="display:flex;gap:8px;align-items:center;margin-top:8px;">
                                <input type="checkbox" name="remove_bg_image" value="1">
                                <span>Remove existing background image</span>
                            </label>
                        <?php endif; ?>
                    </div>

                    <div class="full" id="videoField">
                        <label>Background Video</label>
                        <input type="file" name="bg_video_file" accept="video/mp4,video/webm,video/ogg">

                        <?php if (!empty($about['bg_video'])): ?>
                            <div class="current-file">Current video: <?php echo h($about['bg_video']); ?></div>
                            <label style="display:flex;gap:8px;align-items:center;margin-top:8px;">
                                <input type="checkbox" name="remove_bg_video" value="1">
                                <span>Remove existing background video</span>
                            </label>
                        <?php endif; ?>
                    </div>

                    <div class="full">
                        <?php
                        $previewStyle = 'background:' . h($about['bg_color'] ?: '#ffffff') . ';';

                        if (($about['bg_type'] ?? '') === 'image' && !empty($about['bg_image'])) {
                            $previewStyle = "background-image:url('../" . h($about['bg_image']) . "');";
                        }
                        ?>

                        <div
                            class="background-preview"
                            id="backgroundPreview"
                            style="<?php echo $previewStyle; ?> --overlay-color:<?php echo h($about['overlay_color'] ?: 'rgba(11,44,58,.55)'); ?>;"
                        >
                            <?php if (($about['bg_type'] ?? '') === 'video' && !empty($about['bg_video'])): ?>
                                <video autoplay muted loop playsinline>
                                    <source src="../<?php echo h($about['bg_video']); ?>">
                                </video>
                            <?php endif; ?>

                            <div class="background-preview-content">
                                <h3><?php echo h($about['page_title'] ?: 'Partner With Us'); ?></h3>
                                <p><?php echo h($about['page_subtitle'] ?: 'Partner section subtitle preview.'); ?></p>
                            </div>
                        </div>

                        <div class="bg-help" style="margin-top:12px;">
                            For image or video backgrounds, use an overlay such as
                            <code>rgba(11,44,58,.55)</code> or <code>rgba(253,101,1,.45)</code>
                            so text remains readable.
                        </div>
                    </div>

                    <div class="full" style="display:flex;align-items:center;gap:10px;">
                        <label style="margin:0;">Active</label>
                        <label class="switch">
                            <input type="checkbox" name="is_active" value="1" <?php echo !empty($about['is_active']) ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                        <p class="inline-note">If active, this content becomes the version used by the frontend.</p>
                    </div>
                </div>

                <div class="hr"></div>

                <div class="actions-row">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Page
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h3><i class="fas fa-layer-group"></i> Why Partner Blocks</h3>
            <button class="btn btn-primary btn-sm" onclick="toggleAddBlock(true)">
                <i class="fas fa-plus"></i> Add Block
            </button>
        </div>

        <div class="card-body">
            <p class="muted" style="margin:0 0 10px;">
                Drag and drop to reorder. Use the toggle to activate or deactivate each block.
            </p>

            <?php if (empty($blocks)): ?>
                <div class="muted" style="padding:16px;border:1px dashed #e5e7eb;border-radius:14px;">
                    No blocks yet. Click <strong>Add Block</strong> to create your first card.
                </div>
            <?php else: ?>
                <div class="block-list" id="blockList">
                    <?php foreach ($blocks as $b): ?>
                        <?php
                        $bg = trim($b['bg_color'] ?? '') ?: '#ffffff';
                        $bd = trim($b['border_color'] ?? '') ?: '#fd6501';
                        $tc = trim($b['title_color'] ?? '') ?: '#111827';
                        ?>

                        <div class="block-item" data-id="<?php echo (int)$b['id']; ?>">
                            <div class="drag-handle">
                                <i class="fas fa-grip-vertical"></i>
                            </div>

                            <div>
                                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                    <div class="block-title"><?php echo h($b['title']); ?></div>

                                    <span class="badge <?php echo !empty($b['is_active']) ? 'badge-on' : 'badge-off'; ?>" id="badge-<?php echo (int)$b['id']; ?>">
                                        <?php echo !empty($b['is_active']) ? 'ACTIVE' : 'INACTIVE'; ?>
                                    </span>
                                </div>

                                <div class="block-meta">
                                    Order:
                                    <strong id="order-<?php echo (int)$b['id']; ?>">
                                        <?php echo (int)$b['sort_order']; ?>
                                    </strong>

                                    <?php if (!empty($b['icon_class'])): ?>
                                        - Icon: <code><?php echo h($b['icon_class']); ?></code>
                                    <?php endif; ?>
                                </div>

                                <div class="block-preview" style="background:<?php echo h($bg); ?>; border-left:4px solid <?php echo h($bd); ?>;">
                                    <div style="color:<?php echo h($tc); ?>;">
                                        <?php if (!empty($b['icon_class'])): ?>
                                            <i class="<?php echo h($b['icon_class']); ?>"></i>
                                        <?php endif; ?>
                                    </div>

                                    <div>
                                        <div class="bp-title" style="color:<?php echo h($tc); ?>;">
                                            <?php echo h($b['title']); ?>
                                        </div>

                                        <p class="bp-body">
                                            <?php echo nl2br(h(mb_strimwidth(strip_tags($b['body']), 0, 140, '...'))); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="block-actions">
                                <label class="switch">
                                    <input type="checkbox" <?php echo !empty($b['is_active']) ? 'checked' : ''; ?> onchange="toggleBlock(<?php echo (int)$b['id']; ?>, this.checked)">
                                    <span class="slider"></span>
                                </label>

                                <button class="btn btn-secondary btn-sm" onclick='openEditBlock(<?php echo json_encode($b, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>

                                <button class="btn btn-danger btn-sm" onclick="deleteBlock(<?php echo (int)$b['id']; ?>, <?php echo json_encode($b['title']); ?>)">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="edit-panel" id="blockEditor" style="display:none;">
                <h4 style="margin:0 0 10px;font-size:15px;font-weight:900;color:#111827;">
                    <i class="fas fa-pen"></i> <span id="beTitle">Add Block</span>
                </h4>

                <form method="POST" id="blockForm">
                    <input type="hidden" name="action" value="save_block">
                    <input type="hidden" name="block_id" id="blockId" value="0">

                    <div class="form-grid">
                        <div class="full">
                            <label>Title *</label>
                            <input type="text" name="title" id="bTitle" required>
                        </div>

                        <div class="full">
                            <label>Body *</label>
                            <textarea name="body" id="bBody" required></textarea>
                        </div>

                        <div>
                            <label>Icon Class</label>
                            <input type="text" name="icon_class" id="bIcon" placeholder="fas fa-handshake">
                        </div>

                        <div>
                            <label>Sort Order</label>
                            <input type="number" name="sort_order" id="bOrder" value="0" min="0">
                            <div class="small">Leave 0 to auto-append.</div>
                        </div>

                        <div>
                            <label>Background Color</label>
                            <input type="text" name="bg_color" id="bBg" placeholder="#eff6ff">
                        </div>

                        <div>
                            <label>Border Color</label>
                            <input type="text" name="border_color" id="bBorder" placeholder="#fd6501">
                        </div>

                        <div>
                            <label>Title Color</label>
                            <input type="text" name="title_color" id="bTitleColor" placeholder="#111827">
                        </div>

                        <div class="full" style="display:flex;align-items:center;gap:10px;">
                            <label style="margin:0;">Active</label>
                            <label class="switch">
                                <input type="checkbox" name="block_is_active" id="bActive" value="1" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="hr"></div>

                    <div class="actions-row">
                        <button type="button" class="btn btn-secondary" onclick="toggleAddBlock(false)">
                            <i class="fas fa-times"></i> Cancel
                        </button>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Block
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleBackgroundFields() {
    const type = document.getElementById('bgType').value;

    document.getElementById('imageField').style.display = type === 'image' ? 'block' : 'none';
    document.getElementById('videoField').style.display = type === 'video' ? 'block' : 'none';
}

function updateBackgroundPreview() {
    const type = document.getElementById('bgType').value;
    const color = document.getElementById('bgColor').value || '#ffffff';
    const overlay = document.getElementById('overlayColor').value || 'rgba(11,44,58,.55)';
    const preview = document.getElementById('backgroundPreview');

    if (!preview) return;

    preview.style.setProperty('--overlay-color', overlay);

    if (type === 'color') {
        preview.style.background = color;
        preview.style.backgroundImage = '';
    }
}

function toggleAddBlock(show) {
    const ed = document.getElementById('blockEditor');

    if (!ed) return;

    if (show) {
        document.getElementById('beTitle').textContent = 'Add Block';
        document.getElementById('blockForm').reset();
        document.getElementById('blockId').value = '0';
        document.getElementById('bOrder').value = '0';
        document.getElementById('bActive').checked = true;

        ed.style.display = 'block';
        ed.scrollIntoView({behavior:'smooth', block:'start'});
    } else {
        ed.style.display = 'none';
        document.getElementById('blockForm').reset();
    }
}

function openEditBlock(b) {
    toggleAddBlock(true);

    document.getElementById('beTitle').textContent = 'Edit Block';
    document.getElementById('blockId').value = b.id || 0;
    document.getElementById('bTitle').value = b.title || '';
    document.getElementById('bBody').value = b.body || '';
    document.getElementById('bIcon').value = b.icon_class || '';
    document.getElementById('bOrder').value = b.sort_order || 0;
    document.getElementById('bBg').value = b.bg_color || '';
    document.getElementById('bBorder').value = b.border_color || '';
    document.getElementById('bTitleColor').value = b.title_color || '';
    document.getElementById('bActive').checked = parseInt(b.is_active || 0, 10) === 1;
}

function deleteBlock(id, title) {
    if (!confirm(`Delete block "${title}"?\n\nThis cannot be undone.`)) return;

    const f = document.createElement('form');
    f.method = 'POST';
    f.innerHTML = `
        <input type="hidden" name="action" value="delete_block">
        <input type="hidden" name="block_id" value="${id}">
    `;
    document.body.appendChild(f);
    f.submit();
}

async function toggleBlock(id, isOn) {
    try {
        const fd = new FormData();
        fd.append('action', 'toggle_block');
        fd.append('block_id', id);
        fd.append('value', isOn ? 1 : 0);

        const res = await fetch('partner.php', {
            method: 'POST',
            body: fd
        });

        const data = await res.json();
        const badge = document.getElementById('badge-' + id);

        if (data && data.success) {
            if (badge) {
                badge.textContent = isOn ? 'ACTIVE' : 'INACTIVE';
                badge.classList.toggle('badge-on', isOn);
                badge.classList.toggle('badge-off', !isOn);
            }
        } else {
            alert('Failed to update status. Please refresh and try again.');
            location.reload();
        }
    } catch (e) {
        alert('Network error. Please refresh and try again.');
        location.reload();
    }
}

(function() {
    const list = document.getElementById('blockList');

    if (!list) return;

    let dragging = null;

    function isHandle(el) {
        return el && (el.classList.contains('drag-handle') || el.closest('.drag-handle'));
    }

    list.querySelectorAll('.block-item').forEach(item => {
        item.setAttribute('draggable', 'true');

        item.addEventListener('dragstart', e => {
            if (!isHandle(e.target)) {
                e.preventDefault();
                return;
            }

            dragging = item;
            item.style.opacity = '0.55';
            e.dataTransfer.effectAllowed = 'move';
        });

        item.addEventListener('dragend', async () => {
            if (!dragging) return;

            dragging.style.opacity = '1';
            dragging = null;

            await persistOrder();
        });

        item.addEventListener('dragover', e => {
            e.preventDefault();

            if (!dragging || item === dragging) return;

            const rect = item.getBoundingClientRect();
            const next = (e.clientY - rect.top) > (rect.height / 2);

            list.insertBefore(dragging, next ? item.nextSibling : item);
        });
    });

    async function persistOrder() {
        const ids = Array.from(list.querySelectorAll('.block-item')).map(el => el.getAttribute('data-id'));

        try {
            const fd = new FormData();
            fd.append('action', 'reorder_blocks');

            ids.forEach(id => fd.append('order[]', id));

            const res = await fetch('partner.php', {
                method: 'POST',
                body: fd
            });

            const data = await res.json();

            if (!data || !data.success) {
                alert('Failed to save order. Please refresh and try again.');
                return;
            }

            ids.forEach((id, idx) => {
                const el = document.getElementById('order-' + id);
                if (el) el.textContent = String(idx + 1);
            });
        } catch (e) {
            alert('Network error while saving order.');
        }
    }
})();

toggleBackgroundFields();
updateBackgroundPreview();
</script>

<?php include 'layout/footer.php'; ?>