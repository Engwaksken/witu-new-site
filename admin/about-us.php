<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/includes/admin-enhancements.php';
adminTrackActivity($conn);

$has_permission = false;

if (function_exists('hasPermission')) {
    $has_permission = hasPermission($conn, 'manage_content') || hasPermission($conn, 'manage_about_us');
} else {
    $has_permission = true;
}

if (!$has_permission) {
    die('<h2>Access Denied</h2><p>You do not have permission to manage About content.</p>');
}

$page_title = 'Manage About Us';

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function post_int($k, $d = 0): int {
    return isset($_POST[$k]) ? (int)$_POST[$k] : $d;
}

function post_str(mysqli $conn, $k, $d = ''): string {
    return clean($conn, $_POST[$k] ?? $d);
}

function post_text($k, $d = ''): string {
    return trim($_POST[$k] ?? $d);
}

function ensureUploadDir(string $dir): void {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

function deleteLocalFile(string $path): void {
    if ($path === '') return;

    $full = __DIR__ . '/../' . ltrim($path, '/');

    if (is_file($full)) {
        @unlink($full);
    }
}

function uploadFileSafe(
    string $fileKey,
    string $prefix,
    string $currentFile = '',
    string $subdir = 'uploads/about/',
    string $type = 'image'
): string {
    if (empty($_FILES[$fileKey]['name'])) {
        return $currentFile;
    }

    $uploadDir = __DIR__ . '/../' . trim($subdir, '/') . '/';
    ensureUploadDir($uploadDir);

    $file = $_FILES[$fileKey];

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return $currentFile;
    }

    $imageMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    $videoMimes = [
        'video/mp4'  => 'mp4',
        'video/webm' => 'webm',
        'video/ogg'  => 'ogv',
    ];

    $allowed = $type === 'video' ? $videoMimes : $imageMimes;
    $maxSize = $type === 'video' ? 80 * 1024 * 1024 : 5 * 1024 * 1024;

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed[$mime])) {
        setFlashMessage('error', 'Invalid ' . $type . ' type. Please upload a valid file.');
        header('Location: about-us.php');
        exit;
    }

    if (($file['size'] ?? 0) > $maxSize) {
        setFlashMessage('error', ucfirst($type) . ' file is too large.');
        header('Location: about-us.php');
        exit;
    }

    $ext = $allowed[$mime];
    $newName = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destination = $uploadDir . $newName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        setFlashMessage('error', 'Failed to upload file.');
        header('Location: about-us.php');
        exit;
    }

    if (!empty($currentFile)) {
        deleteLocalFile($currentFile);
    }

    return trim($subdir, '/') . '/' . $newName;
}

function validBgType(string $type): string {
    return in_array($type, ['color', 'image', 'video'], true) ? $type : 'color';
}

function validMediaType(string $type): string {
    return in_array($type, ['icon', 'image'], true) ? $type : 'image';
}

function runStmt(mysqli $conn, string $sql, string $types = '', array $params = []): void {
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        setFlashMessage('error', 'Database error: ' . $conn->error);
        header('Location: about-us.php');
        exit;
    }

    if ($types !== '') {
        if (strlen($types) !== count($params)) {
            die(
                'Bind error: types count ' . strlen($types) .
                ' does not match params count ' . count($params)
            );
        }

        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
}

$sectionMap = [
    'value' => [
        'table' => 'about_values',
        'tab' => 'values',
        'title_col' => 'title',
        'fields' => ['title', 'description'],
        'required' => 'title',
        'prefix' => 'value'
    ],
    'goal' => [
        'table' => 'about_goals',
        'tab' => 'goals',
        'title_col' => 'heading',
        'fields' => ['heading', 'subtext'],
        'required' => 'heading',
        'prefix' => 'goal'
    ],
    'approach' => [
        'table' => 'about_approach',
        'tab' => 'approach',
        'title_col' => 'heading',
        'fields' => ['heading', 'subtext1', 'subtext2'],
        'required' => 'heading',
        'prefix' => 'approach'
    ],
    'model' => [
        'table' => 'about_model',
        'tab' => 'model',
        'title_col' => 'heading',
        'fields' => ['heading', 'description'],
        'required' => 'heading',
        'prefix' => 'model'
    ],
    'why' => [
        'table' => 'about_why_choose',
        'tab' => 'why',
        'title_col' => 'heading',
        'fields' => ['heading', 'description'],
        'required' => 'heading',
        'prefix' => 'why'
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_main') {
        $id = post_int('id');

        $hero_image = $_POST['existing_hero_image'] ?? '';
        $cta_background_image = $_POST['existing_cta_background_image'] ?? '';

        $hero_image = uploadFileSafe('hero_image', 'hero', $hero_image, 'uploads/about/', 'image');
        $cta_background_image = uploadFileSafe('cta_background_image', 'cta_bg', $cta_background_image, 'uploads/about/', 'image');

        $sections = ['values', 'goals', 'approach', 'model', 'why'];
        $bg = [];

        foreach ($sections as $sec) {
            $bg[$sec . '_bg_color'] = post_str($conn, $sec . '_bg_color', '#ffffff');
            $bg[$sec . '_bg_type']  = validBgType($_POST[$sec . '_bg_type'] ?? 'color');

            $existingImg = $_POST['existing_' . $sec . '_bg_image'] ?? '';
            $existingVid = $_POST['existing_' . $sec . '_bg_video'] ?? '';

            $bg[$sec . '_bg_image'] = uploadFileSafe(
                $sec . '_bg_image',
                $sec . '_bg_img',
                $existingImg,
                'uploads/about/backgrounds/',
                'image'
            );

            $bg[$sec . '_bg_video'] = uploadFileSafe(
                $sec . '_bg_video',
                $sec . '_bg_vid',
                $existingVid,
                'uploads/about/backgrounds/',
                'video'
            );
        }

        $data = [
            post_text('mission'),
            post_text('vision'),
            post_text('impact_statement'),
            post_text('history_text'),
            post_str($conn, 'founder_name'),
            post_str($conn, 'founder_title'),
            post_str($conn, 'founder_video_url'),

            $bg['values_bg_color'],
            $bg['values_bg_type'],
            $bg['values_bg_image'],
            $bg['values_bg_video'],

            $bg['goals_bg_color'],
            $bg['goals_bg_type'],
            $bg['goals_bg_image'],
            $bg['goals_bg_video'],

            $bg['approach_bg_color'],
            $bg['approach_bg_type'],
            $bg['approach_bg_image'],
            $bg['approach_bg_video'],

            $bg['model_bg_color'],
            $bg['model_bg_type'],
            $bg['model_bg_image'],
            $bg['model_bg_video'],

            $bg['why_bg_color'],
            $bg['why_bg_type'],
            $bg['why_bg_image'],
            $bg['why_bg_video'],

            post_str($conn, 'cta_title'),
            post_text('cta_text'),
            post_str($conn, 'cta_button_text'),
            post_str($conn, 'cta_button_link'),
            $cta_background_image,
            isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($id > 0) {
            $sql = "
                UPDATE about_page SET
                    mission=?,
                    vision=?,
                    impact_statement=?,
                    history_text=?,
                    founder_name=?,
                    founder_title=?,
                    founder_video_url=?,

                    values_bg_color=?,
                    values_bg_type=?,
                    values_bg_image=?,
                    values_bg_video=?,

                    goals_bg_color=?,
                    goals_bg_type=?,
                    goals_bg_image=?,
                    goals_bg_video=?,

                    approach_bg_color=?,
                    approach_bg_type=?,
                    approach_bg_image=?,
                    approach_bg_video=?,

                    model_bg_color=?,
                    model_bg_type=?,
                    model_bg_image=?,
                    model_bg_video=?,

                    why_bg_color=?,
                    why_bg_type=?,
                    why_bg_image=?,
                    why_bg_video=?,

                    cta_title=?,
                    cta_text=?,
                    cta_button_text=?,
                    cta_button_link=?,
                    cta_background_image=?,
                    is_active=?
            ";

            $types = str_repeat('s', 32) . 'i';

            if ($hero_image !== ($_POST['existing_hero_image'] ?? '')) {
                $sql .= ", hero_image=?";
                $types .= "s";
                $data[] = $hero_image;
            }

            $sql .= " WHERE id=?";
            $types .= "i";
            $data[] = $id;

            runStmt($conn, $sql, $types, $data);
            setFlashMessage('success', 'About page updated successfully.');
        } else {
            $sql = "
                INSERT INTO about_page (
                    hero_image,
                    mission,
                    vision,
                    impact_statement,
                    history_text,
                    founder_name,
                    founder_title,
                    founder_video_url,

                    values_bg_color,
                    values_bg_type,
                    values_bg_image,
                    values_bg_video,

                    goals_bg_color,
                    goals_bg_type,
                    goals_bg_image,
                    goals_bg_video,

                    approach_bg_color,
                    approach_bg_type,
                    approach_bg_image,
                    approach_bg_video,

                    model_bg_color,
                    model_bg_type,
                    model_bg_image,
                    model_bg_video,

                    why_bg_color,
                    why_bg_type,
                    why_bg_image,
                    why_bg_video,

                    cta_title,
                    cta_text,
                    cta_button_text,
                    cta_button_link,
                    cta_background_image,
                    is_active
                ) VALUES (" . implode(',', array_fill(0, 34, '?')) . ")
            ";

            array_unshift($data, $hero_image);

            $types = str_repeat('s', 33) . 'i';

            runStmt($conn, $sql, $types, $data);
            setFlashMessage('success', 'About page created successfully.');
        }

        header("Location: about-us.php#tab-main");
        exit;
    }

    foreach ($sectionMap as $key => $cfg) {
        if ($action === 'save_' . $key) {
            $id = post_int('id');
            $sort_order = post_int('sort_order');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $media_type = validMediaType($_POST['media_type'] ?? 'image');
            $icon_class = post_str($conn, 'icon_class', 'fas fa-star');
            $image = $_POST['existing_image'] ?? '';

            if ($media_type === 'image') {
                $image = uploadFileSafe('image', $cfg['prefix'], $image, 'uploads/about/', 'image');
            }

            $required = post_str($conn, $cfg['required']);

            if ($required === '') {
                setFlashMessage('error', ucfirst($key) . ' title/heading is required.');
                header("Location: about-us.php#tab-" . $cfg['tab']);
                exit;
            }

            $values = [];

            foreach ($cfg['fields'] as $field) {
                $values[$field] = in_array($field, ['description'], true)
                    ? post_text($field)
                    : post_str($conn, $field);
            }

            if ($id > 0) {
                $set = [];
                $types = '';
                $params = [];

                foreach ($cfg['fields'] as $field) {
                    $set[] = "$field=?";
                    $types .= "s";
                    $params[] = $values[$field];
                }

                $set[] = "media_type=?";
                $set[] = "icon_class=?";
                $set[] = "image=?";
                $set[] = "sort_order=?";
                $set[] = "is_active=?";

                $types .= "sssii";
                $params[] = $media_type;
                $params[] = $icon_class;
                $params[] = $image;
                $params[] = $sort_order;
                $params[] = $is_active;

                $types .= "i";
                $params[] = $id;

                runStmt(
                    $conn,
                    "UPDATE {$cfg['table']} SET " . implode(',', $set) . " WHERE id=?",
                    $types,
                    $params
                );

                setFlashMessage('success', ucfirst($key) . ' updated successfully.');
            } else {
                $cols = array_merge($cfg['fields'], [
                    'media_type',
                    'icon_class',
                    'image',
                    'sort_order',
                    'is_active'
                ]);

                $placeholders = implode(',', array_fill(0, count($cols), '?'));

                $params = [];
                $types = '';

                foreach ($cfg['fields'] as $field) {
                    $types .= "s";
                    $params[] = $values[$field];
                }

                $types .= "sssii";
                $params[] = $media_type;
                $params[] = $icon_class;
                $params[] = $image;
                $params[] = $sort_order;
                $params[] = $is_active;

                runStmt(
                    $conn,
                    "INSERT INTO {$cfg['table']} (" . implode(',', $cols) . ") VALUES ($placeholders)",
                    $types,
                    $params
                );

                setFlashMessage('success', ucfirst($key) . ' created successfully.');
            }

            header("Location: about-us.php#tab-" . $cfg['tab']);
            exit;
        }

        if ($action === 'delete_' . $key) {
            $id = post_int('id');

            $res = $conn->query("SELECT image FROM {$cfg['table']} WHERE id=" . (int)$id);
            if ($res && $row = $res->fetch_assoc()) {
                deleteLocalFile($row['image'] ?? '');
            }

            runStmt($conn, "DELETE FROM {$cfg['table']} WHERE id=?", "i", [$id]);

            setFlashMessage('success', ucfirst($key) . ' deleted successfully.');
            header("Location: about-us.php#tab-" . $cfg['tab']);
            exit;
        }
    }
}

$about = [
    'id' => 0,
    'hero_image' => '',
    'mission' => '',
    'vision' => '',
    'impact_statement' => '',
    'history_text' => '',
    'founder_name' => 'BARBARA B. MUTABAZI',
    'founder_title' => 'CEO & Founder.',
    'founder_video_url' => '',

    'values_bg_color' => '#ffffff',
    'values_bg_type' => 'color',
    'values_bg_image' => '',
    'values_bg_video' => '',

    'goals_bg_color' => '#f9fafb',
    'goals_bg_type' => 'color',
    'goals_bg_image' => '',
    'goals_bg_video' => '',

    'approach_bg_color' => '#ffffff',
    'approach_bg_type' => 'color',
    'approach_bg_image' => '',
    'approach_bg_video' => '',

    'model_bg_color' => '#ffffff',
    'model_bg_type' => 'color',
    'model_bg_image' => '',
    'model_bg_video' => '',

    'why_bg_color' => '#f9fafb',
    'why_bg_type' => 'color',
    'why_bg_image' => '',
    'why_bg_video' => '',

    'cta_title' => '',
    'cta_text' => '',
    'cta_button_text' => '',
    'cta_button_link' => '',
    'cta_background_image' => '',
    'is_active' => 1,
];

$res = $conn->query("SELECT * FROM about_page ORDER BY id DESC LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    $about = array_merge($about, $row);
}

$dataSets = [];

foreach ($sectionMap as $key => $cfg) {
    $dataSets[$key] = [];

    $res = $conn->query("SELECT * FROM {$cfg['table']} ORDER BY sort_order ASC, id DESC");

    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $dataSets[$key][] = $r;
        }
    }
}

include 'layout/header.php';
include 'layout/sidebar.php';
?>

<style>
.badge-active{background:#dcfce7;color:#166534;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:800}
.badge-inactive{background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:800}
.tabbar{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.tabbtn{border:1px solid #e5e7eb;background:#fff;padding:10px 12px;border-radius:12px;font-weight:800;cursor:pointer}
.tabbtn.active{background:#dbeafe;border-color:#93c5fd;color:#1e40af}
.tabpane{display:none}
.tabpane.active{display:block}
.small-muted{color:#6b7280;font-size:12px}
.inline-actions{display:flex;gap:6px;flex-wrap:wrap}
.thumb{width:56px;height:56px;border-radius:10px;object-fit:cover;border:1px solid #e5e7eb;background:#f9fafb}
.icon-thumb{width:56px;height:56px;border-radius:10px;border:1px solid #e5e7eb;background:#fff7ed;color:#f86505;display:flex;align-items:center;justify-content:center;font-size:24px}
.preview-box{margin-top:10px}
.preview-box img,.preview-box video{max-width:180px;height:auto;border-radius:10px;border:1px solid #e5e7eb}
.bg-box{border:1px solid #e5e7eb;border-radius:14px;padding:14px;margin-bottom:14px;background:#fff}
.bg-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
.media-preview{display:flex;align-items:center;gap:10px}
@media(max-width:900px){.bg-grid{grid-template-columns:1fr 1fr}}
@media(max-width:600px){.bg-grid{grid-template-columns:1fr}}
</style>

<div class="content-header">
    <h2><i class="fas fa-info-circle" style="color:#f86505;"></i> Manage About Us</h2>
    <div class="inline-actions">
        <a class="btn btn-sm" href="../about-us.php" target="_blank"><i class="fas fa-eye"></i> View About Page</a>
    </div>
</div>

<?php $flash = getFlashMessage(); if ($flash): ?>
<div class="alert alert-<?php echo h($flash['type']); ?>">
    <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
    <?php echo h($flash['message']); ?>
</div>
<?php endif; ?>

<div class="card">
    <div style="padding:16px 16px 0;">
        <div class="tabbar" role="tablist">
            <button class="tabbtn" data-tab="main"><i class="fas fa-pen"></i> Main</button>
            <button class="tabbtn" data-tab="values"><i class="fas fa-list"></i> Core Values</button>
            <button class="tabbtn" data-tab="goals"><i class="fas fa-bullseye"></i> Goals</button>
            <button class="tabbtn" data-tab="approach"><i class="fas fa-route"></i> Approach</button>
            <button class="tabbtn" data-tab="model"><i class="fas fa-cubes"></i> Model</button>
            <button class="tabbtn" data-tab="why"><i class="fas fa-star"></i> Why Choose Us</button>
        </div>
        <div class="small-muted" style="margin:10px 0 0;">Each item can use either an uploaded image or a Font Awesome icon. Each section background can use color, image, or video.</div>
    </div>

    <div class="card-body">
        <div class="tabpane" id="tab-main">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_main">
                <input type="hidden" name="id" value="<?php echo (int)$about['id']; ?>">
                <input type="hidden" name="existing_hero_image" value="<?php echo h($about['hero_image']); ?>">
                <input type="hidden" name="existing_cta_background_image" value="<?php echo h($about['cta_background_image']); ?>">

                <?php foreach (['values','goals','approach','model','why'] as $sec): ?>
                    <input type="hidden" name="existing_<?php echo $sec; ?>_bg_image" value="<?php echo h($about[$sec . '_bg_image'] ?? ''); ?>">
                    <input type="hidden" name="existing_<?php echo $sec; ?>_bg_video" value="<?php echo h($about[$sec . '_bg_video'] ?? ''); ?>">
                <?php endforeach; ?>

                <div class="form-group">
                    <label>Hero Image</label>
                    <input type="file" name="hero_image" accept="image/*">
                    <?php if (!empty($about['hero_image'])): ?>
                        <div class="preview-box"><img src="../<?php echo h($about['hero_image']); ?>" alt=""></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Mission</label>
                    <textarea name="mission" rows="4"><?php echo h($about['mission']); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Vision</label>
                    <textarea name="vision" rows="4"><?php echo h($about['vision']); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Strategic Impact Statement</label>
                    <textarea name="impact_statement" rows="6"><?php echo h($about['impact_statement']); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Our History</label>
                    <textarea name="history_text" rows="5"><?php echo h($about['history_text']); ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group col-6">
                        <label>Founder Name</label>
                        <input type="text" name="founder_name" value="<?php echo h($about['founder_name']); ?>">
                    </div>
                    <div class="form-group col-6">
                        <label>Founder Title</label>
                        <input type="text" name="founder_title" value="<?php echo h($about['founder_title']); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Founder Video URL</label>
                    <input type="url" name="founder_video_url" value="<?php echo h($about['founder_video_url']); ?>" placeholder="Paste YouTube link">
                </div>

                <hr>

                <h4>Section Backgrounds</h4>

                <?php
                $bgLabels = [
                    'values' => 'Core Values',
                    'goals' => 'Goals',
                    'approach' => 'Approach',
                    'model' => 'Model',
                    'why' => 'Why Choose Us'
                ];
                foreach ($bgLabels as $sec => $label):
                ?>
                    <div class="bg-box">
                        <h4><?php echo h($label); ?> Background</h4>

                        <div class="bg-grid">
                            <div class="form-group">
                                <label>Background Type</label>
                                <select name="<?php echo $sec; ?>_bg_type">
                                    <option value="color" <?php echo ($about[$sec . '_bg_type'] ?? 'color') === 'color' ? 'selected' : ''; ?>>Color</option>
                                    <option value="image" <?php echo ($about[$sec . '_bg_type'] ?? '') === 'image' ? 'selected' : ''; ?>>Image</option>
                                    <option value="video" <?php echo ($about[$sec . '_bg_type'] ?? '') === 'video' ? 'selected' : ''; ?>>Video</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Color</label>
                                <input type="color" name="<?php echo $sec; ?>_bg_color" value="<?php echo h($about[$sec . '_bg_color'] ?? '#ffffff'); ?>">
                            </div>

                            <div class="form-group">
                                <label>Image</label>
                                <input type="file" name="<?php echo $sec; ?>_bg_image" accept="image/*">
                                <?php if (!empty($about[$sec . '_bg_image'])): ?>
                                    <div class="preview-box"><img src="../<?php echo h($about[$sec . '_bg_image']); ?>" alt=""></div>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label>Video</label>
                                <input type="file" name="<?php echo $sec; ?>_bg_video" accept="video/mp4,video/webm,video/ogg">
                                <?php if (!empty($about[$sec . '_bg_video'])): ?>
                                    <div class="preview-box"><video src="../<?php echo h($about[$sec . '_bg_video']); ?>" controls muted></video></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <hr>

                <h4>Bottom CTA</h4>

                <div class="form-group">
                    <label>CTA Title</label>
                    <input type="text" name="cta_title" value="<?php echo h($about['cta_title']); ?>">
                </div>

                <div class="form-group">
                    <label>CTA Text</label>
                    <textarea name="cta_text" rows="4"><?php echo h($about['cta_text']); ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group col-6">
                        <label>CTA Button Text</label>
                        <input type="text" name="cta_button_text" value="<?php echo h($about['cta_button_text']); ?>">
                    </div>
                    <div class="form-group col-6">
                        <label>CTA Button Link</label>
                        <input type="url" name="cta_button_link" value="<?php echo h($about['cta_button_link']); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>CTA Background Image</label>
                    <input type="file" name="cta_background_image" accept="image/*">
                    <?php if (!empty($about['cta_background_image'])): ?>
                        <div class="preview-box"><img src="../<?php echo h($about['cta_background_image']); ?>" alt=""></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="is_active" value="1" <?php echo !empty($about['is_active']) ? 'checked' : ''; ?>>
                        Active
                    </label>
                </div>

                <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Save Main</button>
            </form>
        </div>

        <?php foreach ($sectionMap as $key => $cfg): ?>
            <div class="tabpane" id="tab-<?php echo h($cfg['tab']); ?>">
                <div class="inline-actions" style="margin-bottom:12px;">
                    <button class="btn btn-primary btn-sm" onclick="openItemModal('<?php echo h($key); ?>')">
                        <i class="fas fa-plus"></i> Add <?php echo h(ucfirst($key)); ?>
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th width="70">Order</th>
                            <th width="90">Media</th>
                            <th>Content</th>
                            <th width="110">Status</th>
                            <th width="180">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($dataSets[$key])): ?>
                            <tr><td colspan="5" style="text-align:center;padding:35px;color:#6b7280;">No records found.</td></tr>
                        <?php else: foreach ($dataSets[$key] as $row): ?>
                            <tr>
                                <td style="font-weight:800;text-align:center;"><?php echo (int)$row['sort_order']; ?></td>
                                <td>
                                    <?php if (($row['media_type'] ?? 'image') === 'icon'): ?>
                                        <div class="icon-thumb"><i class="<?php echo h($row['icon_class'] ?: 'fas fa-star'); ?>"></i></div>
                                    <?php elseif (!empty($row['image'])): ?>
                                        <img src="../<?php echo h($row['image']); ?>" class="thumb" alt="">
                                    <?php else: ?>
                                        <div class="thumb" style="display:flex;align-items:center;justify-content:center;color:#9ca3af;">N/A</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo h($row[$cfg['title_col']] ?? ''); ?></strong>
                                    <div style="margin-top:6px;color:#4b5563;">
                                        <small>
                                            <?php
                                            $txt = $row['description'] ?? $row['subtext'] ?? $row['subtext1'] ?? '';
                                            echo h(mb_strimwidth(strip_tags($txt), 0, 160, '...'));
                                            ?>
                                        </small>
                                    </div>
                                </td>
                                <td><?php echo !empty($row['is_active']) ? '<span class="badge-active">ACTIVE</span>' : '<span class="badge-inactive">INACTIVE</span>'; ?></td>
                                <td>
                                    <div class="inline-actions">
                                        <button class="btn btn-sm btn-primary" onclick='editItem("<?php echo h($key); ?>", <?php echo json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteRow('delete_<?php echo h($key); ?>', <?php echo (int)$row['id']; ?>)">
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
        <?php endforeach; ?>
    </div>
</div>

<?php foreach ($sectionMap as $key => $cfg): ?>
<div id="<?php echo h($key); ?>Modal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:900px;max-height:90vh;overflow-y:auto;">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> <span id="<?php echo h($key); ?>ModalTitle">Add <?php echo h(ucfirst($key)); ?></span></h3>
            <button class="modal-close" onclick="closeModal('<?php echo h($key); ?>Modal')">&times;</button>
        </div>

        <form method="POST" id="<?php echo h($key); ?>Form" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="action" value="save_<?php echo h($key); ?>">
                <input type="hidden" name="id" id="<?php echo h($key); ?>Id" value="0">
                <input type="hidden" name="existing_image" id="<?php echo h($key); ?>ExistingImage" value="">

                <?php foreach ($cfg['fields'] as $field): ?>
                    <div class="form-group">
                        <label><?php echo h(ucwords(str_replace('_', ' ', $field))); ?> <?php echo $field === $cfg['required'] ? '*' : ''; ?></label>
                        <?php if (in_array($field, ['description'], true)): ?>
                            <textarea name="<?php echo h($field); ?>" id="<?php echo h($key . '_' . $field); ?>" rows="4"></textarea>
                        <?php else: ?>
                            <input type="text" name="<?php echo h($field); ?>" id="<?php echo h($key . '_' . $field); ?>" <?php echo $field === $cfg['required'] ? 'required' : ''; ?>>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <div class="form-row">
                    <div class="form-group col-6">
                        <label>Media Type</label>
                        <select name="media_type" id="<?php echo h($key); ?>MediaType" onchange="toggleMediaFields('<?php echo h($key); ?>')">
                            <option value="icon">Use Icon</option>
                            <option value="image">Use Image</option>
                        </select>
                    </div>

                    <div class="form-group col-6">
                        <label>Sort Order</label>
                        <input type="number" name="sort_order" id="<?php echo h($key); ?>Order" value="0" min="0">
                    </div>
                </div>

                <div class="form-group media-icon-field" id="<?php echo h($key); ?>IconBox">
                    <label>Font Awesome Icon Class</label>
                    <input type="text" name="icon_class" id="<?php echo h($key); ?>IconClass" placeholder="fas fa-star">
                    <small class="small-muted">Example: fas fa-users, fas fa-heart, fas fa-lightbulb, fas fa-award</small>
                </div>

                <div class="form-group media-image-field" id="<?php echo h($key); ?>ImageBox">
                    <label>Image</label>
                    <input type="file" name="image" accept="image/*">
                    <div id="<?php echo h($key); ?>Preview" class="preview-box"></div>
                </div>

                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="is_active" id="<?php echo h($key); ?>Active" value="1" checked>
                        Active
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('<?php echo h($key); ?>Modal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save
                </button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<script>
const sectionFields = <?php echo json_encode(array_map(fn($x) => $x['fields'], $sectionMap)); ?>;

(function(){
    const buttons = document.querySelectorAll('.tabbtn');
    const panes = document.querySelectorAll('.tabpane');

    function show(tab){
        buttons.forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
        panes.forEach(p => p.classList.toggle('active', p.id === 'tab-' + tab));
    }

    function tabFromHash(){
        const h = (location.hash || '').replace('#','');
        const tab = h.startsWith('tab-') ? h.replace('tab-','') : (h || 'main');
        return ['main','values','goals','approach','model','why'].includes(tab) ? tab : 'main';
    }

    buttons.forEach(b => b.addEventListener('click', () => {
        history.replaceState(null, '', '#tab-' + b.dataset.tab);
        show(b.dataset.tab);
    }));

    show(tabFromHash());
    window.addEventListener('hashchange', () => show(tabFromHash()));
})();

function openModal(id){
    document.getElementById(id).style.display = 'flex';
}

function closeModal(id){
    const modal = document.getElementById(id);
    modal.style.display = 'none';

    const form = modal.querySelector('form');
    if (form) form.reset();

    modal.querySelectorAll('.preview-box').forEach(p => p.innerHTML = '');
}

function deleteRow(action, id){
    if (!confirm('Delete this item?')) return;

    const f = document.createElement('form');
    f.method = 'POST';
    f.innerHTML = `<input type="hidden" name="action" value="${action}">
                   <input type="hidden" name="id" value="${id}">`;
    document.body.appendChild(f);
    f.submit();
}

function imagePreviewHtml(path){
    return path ? `<img src="../${path}" alt="">` : '';
}

function toggleMediaFields(key){
    const type = document.getElementById(key + 'MediaType').value;
    document.getElementById(key + 'IconBox').style.display = type === 'icon' ? 'block' : 'none';
    document.getElementById(key + 'ImageBox').style.display = type === 'image' ? 'block' : 'none';
}

function openItemModal(key){
    document.getElementById(key + 'Form').reset();
    document.getElementById(key + 'Id').value = '0';
    document.getElementById(key + 'ExistingImage').value = '';
    document.getElementById(key + 'Order').value = '0';
    document.getElementById(key + 'MediaType').value = 'icon';
    document.getElementById(key + 'IconClass').value = 'fas fa-star';
    document.getElementById(key + 'Active').checked = true;
    document.getElementById(key + 'Preview').innerHTML = '';

    sectionFields[key].forEach(field => {
        const el = document.getElementById(key + '_' + field);
        if (el) el.value = '';
    });

    toggleMediaFields(key);
    openModal(key + 'Modal');
}

function editItem(key, row){
    document.getElementById(key + 'Form').reset();
    document.getElementById(key + 'Id').value = row.id || 0;
    document.getElementById(key + 'ExistingImage').value = row.image || '';
    document.getElementById(key + 'Order').value = row.sort_order || 0;
    document.getElementById(key + 'MediaType').value = row.media_type || 'image';
    document.getElementById(key + 'IconClass').value = row.icon_class || 'fas fa-star';
    document.getElementById(key + 'Active').checked = parseInt(row.is_active || 0) === 1;
    document.getElementById(key + 'Preview').innerHTML = imagePreviewHtml(row.image || '');

    sectionFields[key].forEach(field => {
        const el = document.getElementById(key + '_' + field);
        if (el) el.value = row[field] || '';
    });

    toggleMediaFields(key);
    openModal(key + 'Modal');
}

window.addEventListener('click', e => {
    if (e.target && e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
    }
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(m => m.style.display = 'none');
    }
});
</script>

<?php include 'layout/footer.php'; ?>