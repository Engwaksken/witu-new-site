<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/includes/admin-enhancements.php';
adminTrackActivity($conn);

$has_permission = false;

if (function_exists('hasPermission')) {
    $has_permission = hasPermission($conn, 'manage_participant_journey') || hasPermission($conn, 'manage_content');
} else {
    $has_permission = true;
}

if (!$has_permission) {
    die('<h2>Access Denied</h2><p>You don\'t have permission to manage participant journey.</p>');
}

$page_title = 'Manage Participant Journey';

if (!function_exists('h')) {
    function h($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function tableExists(mysqli $conn, string $table): bool {
    $stmt = $conn->prepare("
        SELECT COUNT(*) total
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
    ");
    $stmt->bind_param("s", $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int)($row['total'] ?? 0) > 0;
}

function ensureJourneySectionSettings(mysqli $conn): void {
    if (!tableExists($conn, 'participant_journey_section_settings')) {
        $conn->query("
            CREATE TABLE participant_journey_section_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                section_title VARCHAR(255) NULL,
                section_subtitle TEXT NULL,
                bg_type ENUM('color','image','video') NOT NULL DEFAULT 'color',
                bg_color VARCHAR(30) NULL DEFAULT '#ffffff',
                bg_image VARCHAR(255) NULL,
                bg_video VARCHAR(255) NULL,
                overlay_color VARCHAR(50) NULL DEFAULT 'rgba(11,44,58,.55)',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $stmt = $conn->prepare("
            INSERT INTO participant_journey_section_settings
            (section_title, section_subtitle, bg_type, bg_color, overlay_color, is_active)
            VALUES
            ('Participant Journey', 'A structured pathway from skills development to employment and enterprise growth.', 'color', '#ffffff', 'rgba(11,44,58,.55)', 1)
        ");
        $stmt->execute();
        $stmt->close();
    }
}

function uploadSectionFile(string $field, string $type): string {
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

    $dir = __DIR__ . '/../uploads/participant-journey-section/';

    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $filename = 'journey_section_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    $target = $dir . $filename;

    if (move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        return 'uploads/participant-journey-section/' . $filename;
    }

    return '';
}

function getJourneySectionSettings(mysqli $conn): array {
    ensureJourneySectionSettings($conn);

    $default = [
        'id' => 0,
        'section_title' => 'Participant Journey',
        'section_subtitle' => 'A structured pathway from skills development to employment and enterprise growth.',
        'bg_type' => 'color',
        'bg_color' => '#ffffff',
        'bg_image' => '',
        'bg_video' => '',
        'overlay_color' => 'rgba(11,44,58,.55)',
        'is_active' => 1,
    ];

    $res = $conn->query("SELECT * FROM participant_journey_section_settings ORDER BY id DESC LIMIT 1");

    if ($res && $row = $res->fetch_assoc()) {
        return array_merge($default, $row);
    }

    return $default;
}

function getJourneyCounts(mysqli $conn): array {
    $counts = [
        'continuous' => 0,
        'step1' => 0,
        'step2' => 0,
        'step3' => 0,
    ];

    $res = $conn->query("
        SELECT step_number, is_continuous, COUNT(*) c
        FROM participant_journey
        GROUP BY step_number, is_continuous
    ");

    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $isC = (int)$r['is_continuous'];
            $sn = (int)$r['step_number'];
            $c = (int)$r['c'];

            if ($isC === 1) $counts['continuous'] += $c;
            if ($isC === 0 && $sn === 1) $counts['step1'] += $c;
            if ($isC === 0 && $sn === 2) $counts['step2'] += $c;
            if ($isC === 0 && $sn === 3) $counts['step3'] += $c;
        }
    }

    return $counts;
}

function requiredMissing(array $counts): array {
    $missing = [];

    if ($counts['step1'] < 1) $missing[] = 'Step 1';
    if ($counts['step2'] < 1) $missing[] = 'Step 2';
    if ($counts['step3'] < 1) $missing[] = 'Step 3';
    if ($counts['continuous'] < 1) $missing[] = 'Continuous Support';

    return $missing;
}

ensureJourneySectionSettings($conn);

$sectionSettings = getJourneySectionSettings($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_section_background') {
        $id = (int)($sectionSettings['id'] ?? 0);

        $section_title = trim((string)($_POST['section_title'] ?? 'Participant Journey'));
        $section_subtitle = trim((string)($_POST['section_subtitle'] ?? ''));

        $bg_type = strtolower(trim((string)($_POST['section_bg_type'] ?? 'color')));
        $bg_color = trim((string)($_POST['section_bg_color'] ?? '#ffffff'));
        $overlay_color = trim((string)($_POST['section_overlay_color'] ?? 'rgba(11,44,58,.55)'));
        $is_active = isset($_POST['section_is_active']) ? 1 : 0;

        if (!in_array($bg_type, ['color', 'image', 'video'], true)) {
            $bg_type = 'color';
        }

        if ($bg_color === '') {
            $bg_color = '#ffffff';
        }

        if ($overlay_color === '') {
            $overlay_color = 'rgba(11,44,58,.55)';
        }

        $bg_image = $sectionSettings['bg_image'] ?? '';
        $bg_video = $sectionSettings['bg_video'] ?? '';

        $newImage = uploadSectionFile('section_bg_image_file', 'image');
        $newVideo = uploadSectionFile('section_bg_video_file', 'video');

        if ($newImage !== '') {
            $bg_image = $newImage;
        }

        if ($newVideo !== '') {
            $bg_video = $newVideo;
        }

        if (!empty($_POST['remove_section_bg_image'])) {
            $bg_image = '';
        }

        if (!empty($_POST['remove_section_bg_video'])) {
            $bg_video = '';
        }

        if ($id > 0) {
            $stmt = $conn->prepare("
                UPDATE participant_journey_section_settings
                SET section_title=?,
                    section_subtitle=?,
                    bg_type=?,
                    bg_color=?,
                    bg_image=?,
                    bg_video=?,
                    overlay_color=?,
                    is_active=?
                WHERE id=?
            ");
            $stmt->bind_param(
                "sssssssii",
                $section_title,
                $section_subtitle,
                $bg_type,
                $bg_color,
                $bg_image,
                $bg_video,
                $overlay_color,
                $is_active,
                $id
            );
        } else {
            $stmt = $conn->prepare("
                INSERT INTO participant_journey_section_settings
                (section_title, section_subtitle, bg_type, bg_color, bg_image, bg_video, overlay_color, is_active)
                VALUES (?,?,?,?,?,?,?,?)
            ");
            $stmt->bind_param(
                "sssssssi",
                $section_title,
                $section_subtitle,
                $bg_type,
                $bg_color,
                $bg_image,
                $bg_video,
                $overlay_color,
                $is_active
            );
        }

        $stmt->execute();
        $stmt->close();

        setFlashMessage('success', 'Participant Journey section background updated successfully.');
        header("Location: participant-journey.php");
        exit;
    }

    if ($action === 'save') {
        $id = intval($_POST['id'] ?? 0);
        $is_continuous = isset($_POST['is_continuous']) ? 1 : 0;
        $step_number = $is_continuous ? 0 : intval($_POST['step_number'] ?? 0);

        $title = clean($conn, $_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $stat_text = clean($conn, $_POST['stat_text'] ?? '');
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($title === '' || $description === '') {
            setFlashMessage('error', 'Title and description are required.');
            header("Location: participant-journey.php");
            exit;
        }

        if ($is_continuous === 0 && !in_array($step_number, [1, 2, 3], true)) {
            setFlashMessage('error', 'Step number must be 1, 2, or 3. Continuous uses step number 0.');
            header("Location: participant-journey.php");
            exit;
        }

        if ($is_continuous === 1) {
            if ($id > 0) {
                $stmt = $conn->prepare("SELECT id FROM participant_journey WHERE is_continuous=1 AND id<>? LIMIT 1");
                $stmt->bind_param("i", $id);
            } else {
                $stmt = $conn->prepare("SELECT id FROM participant_journey WHERE is_continuous=1 LIMIT 1");
            }

            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($exists) {
                setFlashMessage('error', 'Only one Continuous Support record is allowed. Edit the existing one instead.');
                header("Location: participant-journey.php");
                exit;
            }
        }

        if ($is_continuous === 0) {
            if ($id > 0) {
                $stmt = $conn->prepare("SELECT id FROM participant_journey WHERE is_continuous=0 AND step_number=? AND id<>? LIMIT 1");
                $stmt->bind_param("ii", $step_number, $id);
            } else {
                $stmt = $conn->prepare("SELECT id FROM participant_journey WHERE is_continuous=0 AND step_number=? LIMIT 1");
                $stmt->bind_param("i", $step_number);
            }

            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($exists) {
                setFlashMessage('error', 'Only one record is allowed for each step. Edit the existing one instead.');
                header("Location: participant-journey.php");
                exit;
            }
        }

        if ($id > 0) {
            $stmt = $conn->prepare("
                UPDATE participant_journey
                SET step_number=?,
                    title=?,
                    description=?,
                    is_continuous=?,
                    stat_text=?,
                    sort_order=?,
                    is_active=?
                WHERE id=?
            ");
            $stmt->bind_param(
                "issisiii",
                $step_number,
                $title,
                $description,
                $is_continuous,
                $stat_text,
                $sort_order,
                $is_active,
                $id
            );

            $stmt->execute();
            $stmt->close();

            setFlashMessage('success', 'Journey item updated successfully.');
        } else {
            $stmt = $conn->prepare("
                INSERT INTO participant_journey
                (step_number, title, description, is_continuous, stat_text, sort_order, is_active)
                VALUES (?,?,?,?,?,?,?)
            ");
            $stmt->bind_param(
                "issisii",
                $step_number,
                $title,
                $description,
                $is_continuous,
                $stat_text,
                $sort_order,
                $is_active
            );

            $stmt->execute();
            $stmt->close();

            setFlashMessage('success', 'Journey item created successfully.');
        }

        header("Location: participant-journey.php");
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);

        $stmt = $conn->prepare("SELECT id, step_number, is_continuous, title FROM participant_journey WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$item) {
            setFlashMessage('error', 'Record not found.');
            header("Location: participant-journey.php");
            exit;
        }

        $counts = getJourneyCounts($conn);

        if ((int)$item['is_continuous'] === 1 && $counts['continuous'] <= 1) {
            setFlashMessage('error', 'You cannot delete the only Continuous Support record. Edit it instead or set inactive.');
            header("Location: participant-journey.php");
            exit;
        }

        if ((int)$item['is_continuous'] === 0) {
            $sn = (int)$item['step_number'];

            if ($sn === 1 && $counts['step1'] <= 1) {
                setFlashMessage('error', 'You cannot delete Step 1 because it is required.');
                header("Location: participant-journey.php");
                exit;
            }

            if ($sn === 2 && $counts['step2'] <= 1) {
                setFlashMessage('error', 'You cannot delete Step 2 because it is required.');
                header("Location: participant-journey.php");
                exit;
            }

            if ($sn === 3 && $counts['step3'] <= 1) {
                setFlashMessage('error', 'You cannot delete Step 3 because it is required.');
                header("Location: participant-journey.php");
                exit;
            }
        }

        $stmt = $conn->prepare("DELETE FROM participant_journey WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        setFlashMessage('success', 'Journey item deleted successfully.');
        header("Location: participant-journey.php");
        exit;
    }
}

$sectionSettings = getJourneySectionSettings($conn);

$steps = [];
$res = $conn->query("SELECT * FROM participant_journey ORDER BY sort_order ASC, is_continuous DESC, step_number ASC, id DESC");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $steps[] = $row;
    }
}

$counts = getJourneyCounts($conn);
$missing = requiredMissing($counts);

include 'layout/header.php';
include 'layout/sidebar.php';
?>

<style>
.badge-active,
.badge-inactive,
.badge-cont,
.badge-step {
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    display: inline-flex;
}

.badge-active {
    background: #dcfce7;
    color: #166534;
}

.badge-inactive {
    background: #fee2e2;
    color: #991b1b;
}

.badge-cont {
    background: #dbeafe;
    color: #1e40af;
}

.badge-step {
    background: #fef3c7;
    color: #92400e;
}

.alert-warning {
    background: #fffbeb;
    border: 1px solid #fde68a;
    color: #92400e;
    padding: 12px 14px;
    border-radius: 10px;
    margin-bottom: 14px;
}

.settings-card {
    margin-bottom: 20px;
}

.form-section-title {
    font-size: 16px;
    font-weight: 800;
    margin: 0 0 14px;
    color: #111827;
}

.current-file {
    font-size: 12px;
    color: #6b7280;
    margin-top: 5px;
    word-break: break-all;
}

.section-preview {
    min-height: 170px;
    border-radius: 18px;
    padding: 30px;
    color: #fff;
    position: relative;
    overflow: hidden;
    background-size: cover;
    background-position: center;
    display: flex;
    align-items: center;
    margin-top: 15px;
}

.section-preview video {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    z-index: 0;
}

.section-preview::after {
    content: "";
    position: absolute;
    inset: 0;
    background: var(--overlay-color, rgba(11,44,58,.55));
    z-index: 1;
}

.section-preview-content {
    position: relative;
    z-index: 2;
}

.section-preview h3 {
    color: #fff;
    margin: 0 0 8px;
    font-size: 28px;
    font-weight: 900;
}

.section-preview p {
    color: rgba(255,255,255,.92);
    margin: 0;
    max-width: 720px;
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
    <h2><i class="fas fa-route" style="color:#f86505;"></i> Manage Participant Journey</h2>
    <button class="btn btn-primary" onclick="openCreateModal()">
        <i class="fas fa-plus"></i> Add Item
    </button>
</div>

<?php if (!empty($missing)): ?>
    <div class="alert-warning">
        <i class="fas fa-exclamation-triangle"></i>
        Homepage journey is missing required items:
        <strong><?php echo h(implode(', ', $missing)); ?></strong>.
    </div>
<?php endif; ?>

<?php $flash = getFlashMessage(); if ($flash): ?>
    <div class="alert alert-<?php echo h($flash['type']); ?>">
        <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo h($flash['message']); ?>
    </div>
<?php endif; ?>

<div class="card settings-card">
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_section_background">

        <h3 class="form-section-title">
            <i class="fas fa-layer-group"></i> Section Background Settings
        </h3>

        <div class="form-row">
            <div class="form-group col-6">
                <label>Section Title</label>
                <input type="text" name="section_title" value="<?php echo h($sectionSettings['section_title']); ?>">
            </div>

            <div class="form-group col-6">
                <label>Background Type</label>
                <select name="section_bg_type" id="sectionBgType" onchange="toggleSectionBackgroundFields(); updateSectionPreview();">
                    <option value="color" <?php echo $sectionSettings['bg_type'] === 'color' ? 'selected' : ''; ?>>Color</option>
                    <option value="image" <?php echo $sectionSettings['bg_type'] === 'image' ? 'selected' : ''; ?>>Image</option>
                    <option value="video" <?php echo $sectionSettings['bg_type'] === 'video' ? 'selected' : ''; ?>>Video</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Section Subtitle</label>
            <textarea name="section_subtitle" rows="3"><?php echo h($sectionSettings['section_subtitle']); ?></textarea>
        </div>

        <div class="form-row">
            <div class="form-group col-4">
                <label>Background Color</label>
                <input type="color" name="section_bg_color" id="sectionBgColor" value="<?php echo h($sectionSettings['bg_color'] ?: '#ffffff'); ?>" onchange="updateSectionPreview();">
            </div>

            <div class="form-group col-4">
                <label>Overlay Color</label>
                <input type="text" name="section_overlay_color" id="sectionOverlayColor" value="<?php echo h($sectionSettings['overlay_color'] ?: 'rgba(11,44,58,.55)'); ?>" onkeyup="updateSectionPreview();">
                <small>Example: rgba(11,44,58,.55)</small>
            </div>

            <div class="form-group col-4">
                <label style="display:flex;gap:8px;align-items:center;margin-top:28px;">
                    <input type="checkbox" name="section_is_active" value="1" <?php echo !empty($sectionSettings['is_active']) ? 'checked' : ''; ?>>
                    <span>Show Section</span>
                </label>
            </div>
        </div>

        <div class="form-row" id="sectionImageField">
            <div class="form-group col-12">
                <label>Section Background Image</label>
                <input type="file" name="section_bg_image_file" accept="image/*">
                <?php if (!empty($sectionSettings['bg_image'])): ?>
                    <div class="current-file">Current image: <?php echo h($sectionSettings['bg_image']); ?></div>
                    <label style="display:flex;gap:8px;align-items:center;margin-top:8px;">
                        <input type="checkbox" name="remove_section_bg_image" value="1">
                        <span>Remove existing image</span>
                    </label>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-row" id="sectionVideoField">
            <div class="form-group col-12">
                <label>Section Background Video</label>
                <input type="file" name="section_bg_video_file" accept="video/mp4,video/webm,video/ogg">
                <?php if (!empty($sectionSettings['bg_video'])): ?>
                    <div class="current-file">Current video: <?php echo h($sectionSettings['bg_video']); ?></div>
                    <label style="display:flex;gap:8px;align-items:center;margin-top:8px;">
                        <input type="checkbox" name="remove_section_bg_video" value="1">
                        <span>Remove existing video</span>
                    </label>
                <?php endif; ?>
            </div>
        </div>

        <?php
        $previewStyle = 'background:' . h($sectionSettings['bg_color'] ?: '#ffffff') . ';';

        if (($sectionSettings['bg_type'] ?? '') === 'image' && !empty($sectionSettings['bg_image'])) {
            $previewStyle = "background-image:url('../" . h($sectionSettings['bg_image']) . "');";
        }
        ?>

        <div
            class="section-preview"
            id="sectionPreview"
            style="<?php echo $previewStyle; ?> --overlay-color:<?php echo h($sectionSettings['overlay_color'] ?: 'rgba(11,44,58,.55)'); ?>;"
        >
            <?php if (($sectionSettings['bg_type'] ?? '') === 'video' && !empty($sectionSettings['bg_video'])): ?>
                <video autoplay muted loop playsinline>
                    <source src="../<?php echo h($sectionSettings['bg_video']); ?>">
                </video>
            <?php endif; ?>

            <div class="section-preview-content">
                <h3><?php echo h($sectionSettings['section_title'] ?: 'Participant Journey'); ?></h3>
                <p><?php echo h($sectionSettings['section_subtitle'] ?: 'Section subtitle preview.'); ?></p>
            </div>
        </div>

        <div class="bg-help" style="margin-top:14px;">
            Use a transparent overlay such as <code>rgba(11,44,58,.55)</code> or <code>rgba(253,101,1,.45)</code>
            when using image or video backgrounds.
        </div>

        <div style="margin-top:15px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Section Background
            </button>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
            <tr>
                <th width="70">Order</th>
                <th width="120">Type</th>
                <th width="90">Step</th>
                <th>Title</th>
                <th width="110">Status</th>
                <th width="200">Actions</th>
            </tr>
            </thead>

            <tbody>
            <?php if (empty($steps)): ?>
                <tr>
                    <td colspan="6" style="text-align:center;padding:40px;color:#6b7280;">
                        <i class="fas fa-inbox" style="font-size:48px;color:#d1d5db;margin-bottom:15px;display:block;"></i>
                        <strong>No journey items found</strong><br>
                        <small>Add Step 1, Step 2, Step 3 and Continuous Support.</small>
                    </td>
                </tr>
            <?php else: foreach ($steps as $st): ?>
                <tr>
                    <td style="text-align:center;font-weight:700;color:#6b7280;"><?php echo (int)$st['sort_order']; ?></td>

                    <td>
                        <?php if (!empty($st['is_continuous'])): ?>
                            <span class="badge-cont">Continuous</span>
                        <?php else: ?>
                            <span class="badge-step">Step</span>
                        <?php endif; ?>
                    </td>

                    <td style="text-align:center;">
                        <?php echo !empty($st['is_continuous']) ? '-' : (int)$st['step_number']; ?>
                    </td>

                    <td>
                        <strong><?php echo h($st['title']); ?></strong><br>
                        <small style="color:#6b7280;">
                            <?php echo h(mb_strimwidth(strip_tags($st['description']), 0, 110, '...')); ?>
                        </small>

                        <?php if (!empty($st['stat_text'])): ?>
                            <div style="margin-top:6px;">
                                <small><i class="fas fa-chart-line"></i> <?php echo h($st['stat_text']); ?></small>
                            </div>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php echo !empty($st['is_active'])
                            ? '<span class="badge-active">ACTIVE</span>'
                            : '<span class="badge-inactive">INACTIVE</span>'; ?>
                    </td>

                    <td>
                        <div style="display:flex;gap:6px;">
                            <button class="btn btn-sm btn-primary" onclick='editItem(<?php echo json_encode($st, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                <i class="fas fa-edit"></i>
                            </button>

                            <button class="btn btn-sm btn-danger"
                                    onclick="deleteItem(<?php echo (int)$st['id']; ?>,'<?php echo h(addslashes($st['title'])); ?>', <?php echo (int)$st['is_continuous']; ?>, <?php echo (int)$st['step_number']; ?>)">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="itemModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:900px;max-height:90vh;overflow-y:auto;">
        <div class="modal-header">
            <h3><i class="fas fa-route"></i> <span id="modalTitle">Add Item</span></h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>

        <form method="POST" id="itemForm">
            <div class="modal-body">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="itemId" value="0">

                <div class="form-row">
                    <div class="form-group col-4">
                        <label>Sort Order</label>
                        <input type="number" name="sort_order" id="itemOrder" value="0" min="0">
                    </div>

                    <div class="form-group col-4">
                        <label>Step Number</label>
                        <input type="number" name="step_number" id="itemStepNumber" value="1" min="0">
                        <small>Allowed: 1, 2, 3. Continuous uses 0.</small>
                    </div>

                    <div class="form-group col-4">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-top:28px;">
                            <input type="checkbox" name="is_continuous" id="itemContinuous" value="1">
                            <span>Continuous Support</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" id="itemTitle" required>
                </div>

                <div class="form-group">
                    <label>Description *</label>
                    <textarea name="description" id="itemDesc" rows="7" required></textarea>
                </div>

                <div class="form-group">
                    <label>Stat Text</label>
                    <input type="text" name="stat_text" id="itemStat">
                </div>

                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="is_active" id="itemActive" value="1" checked>
                        <span>Active</span>
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Add Item';
    document.getElementById('itemForm').reset();

    document.getElementById('itemId').value = '0';
    document.getElementById('itemOrder').value = '0';
    document.getElementById('itemStepNumber').value = '1';
    document.getElementById('itemContinuous').checked = false;
    document.getElementById('itemActive').checked = true;

    lockStepNumberIfContinuous();

    document.getElementById('itemModal').style.display = 'flex';
}

function editItem(s) {
    document.getElementById('modalTitle').textContent = 'Edit Item';

    document.getElementById('itemId').value = s.id || 0;
    document.getElementById('itemOrder').value = s.sort_order || 0;
    document.getElementById('itemContinuous').checked = parseInt(s.is_continuous || 0) === 1;
    document.getElementById('itemStepNumber').value = parseInt(s.is_continuous || 0) === 1 ? 0 : (s.step_number || 1);

    document.getElementById('itemTitle').value = s.title || '';
    document.getElementById('itemDesc').value = s.description || '';
    document.getElementById('itemStat').value = s.stat_text || '';
    document.getElementById('itemActive').checked = parseInt(s.is_active || 0) === 1;

    lockStepNumberIfContinuous();

    document.getElementById('itemModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('itemModal').style.display = 'none';
    document.getElementById('itemForm').reset();
}

function lockStepNumberIfContinuous() {
    const isC = document.getElementById('itemContinuous').checked;
    const stepInput = document.getElementById('itemStepNumber');

    if (isC) {
        stepInput.value = 0;
        stepInput.setAttribute('readonly', 'readonly');
        stepInput.style.background = '#f3f4f6';
    } else {
        stepInput.removeAttribute('readonly');
        stepInput.style.background = '';

        if (parseInt(stepInput.value || '0', 10) === 0) {
            stepInput.value = 1;
        }
    }
}

function deleteItem(id, title, isContinuous, stepNumber) {
    let msg = `Delete "${title}"?\n\nThis cannot be undone.`;

    if (isContinuous == 1) {
        msg += `\n\nIf this is the only continuous record, deletion will be blocked.`;
    }

    if (isContinuous == 0 && [1,2,3].includes(parseInt(stepNumber))) {
        msg += `\n\nRequired steps may be blocked from deletion.`;
    }

    if (confirm(msg)) {
        const f = document.createElement('form');
        f.method = 'POST';
        f.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(f);
        f.submit();
    }
}

function toggleSectionBackgroundFields() {
    const type = document.getElementById('sectionBgType').value;

    document.getElementById('sectionImageField').style.display = type === 'image' ? 'block' : 'none';
    document.getElementById('sectionVideoField').style.display = type === 'video' ? 'block' : 'none';
}

function updateSectionPreview() {
    const type = document.getElementById('sectionBgType').value;
    const color = document.getElementById('sectionBgColor').value || '#ffffff';
    const overlay = document.getElementById('sectionOverlayColor').value || 'rgba(11,44,58,.55)';
    const preview = document.getElementById('sectionPreview');

    preview.style.setProperty('--overlay-color', overlay);

    if (type === 'color') {
        preview.style.background = color;
        preview.style.backgroundImage = '';
    }
}

document.getElementById('itemContinuous')?.addEventListener('change', lockStepNumberIfContinuous);

window.onclick = function(e) {
    if (e.target.id === 'itemModal') closeModal();
};

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});

toggleSectionBackgroundFields();
updateSectionPreview();
</script>

<?php include 'layout/footer.php'; ?>