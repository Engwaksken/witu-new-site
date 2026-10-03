<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/includes/admin-enhancements.php';
adminTrackActivity($conn);

$has_permission = false;

if (function_exists('hasPermission')) {
    if (hasPermission($conn, 'manage_impact')) {
        $has_permission = true;
    } elseif (hasPermission($conn, 'manage_content')) {
        $has_permission = true;
    }
} else {
    $has_permission = true;
}

if (!$has_permission) {
    die('<h2>Access Denied</h2><p>You do not have permission to manage impact.</p>');
}

$page_title = 'Manage Our Impact';

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function impactPageRow(mysqli $conn): array {
    $row = [
        'id' => 0,
        'hero_title' => 'Our Impact',
        'hero_subtitle' => '',
        'intro_title' => 'Overall Impact',
        'intro_text' => '',
        'hero_image' => '',
        'annual_cta_title' => 'Want to see the official impact data?',
        'annual_cta_text' => 'Our annual reports speak to the success of the WITU model and the lives our Programmes have changed.',
        'annual_cta_button' => 'Read the Annual Reports',
        'annual_cta_link' => 'annual-reports.php',
        'is_active' => 1,
    ];

    $res = $conn->query("SELECT * FROM impact_page WHERE is_active = 1 ORDER BY id DESC LIMIT 1");

    if ($res && ($db = $res->fetch_assoc())) {
        $row = array_merge($row, $db);
    }

    return $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_page') {
        $hero_title = clean($conn, $_POST['hero_title'] ?? 'Our Impact');
        $hero_subtitle = trim($_POST['hero_subtitle'] ?? '');
        $intro_title = clean($conn, $_POST['intro_title'] ?? 'Overall Impact');
        $intro_text = trim($_POST['intro_text'] ?? '');
        $annual_cta_title = clean($conn, $_POST['annual_cta_title'] ?? '');
        $annual_cta_text = trim($_POST['annual_cta_text'] ?? '');
        $annual_cta_button = clean($conn, $_POST['annual_cta_button'] ?? '');
        $annual_cta_link = clean($conn, $_POST['annual_cta_link'] ?? '');

        $pageRow = impactPageRow($conn);
        $new_image = '';

        if (isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] === 0) {
            $upload = uploadFile($_FILES['hero_image'], 'impact');

            if (!empty($upload['success'])) {
                $new_image = $upload['filename'];
            } else {
                setFlashMessage('error', $upload['message'] ?? 'Failed to upload image.');
                header("Location: our-impact.php");
                exit;
            }
        }

        if (!empty($pageRow['id'])) {
            if ($new_image && !empty($pageRow['hero_image'])) {
                deleteFile($pageRow['hero_image'], 'impact');
            }

            $sql = "UPDATE impact_page 
                    SET hero_title=?, hero_subtitle=?, intro_title=?, intro_text=?, 
                        annual_cta_title=?, annual_cta_text=?, annual_cta_button=?, annual_cta_link=?";

            $types = "ssssssss";
            $params = [
                $hero_title,
                $hero_subtitle,
                $intro_title,
                $intro_text,
                $annual_cta_title,
                $annual_cta_text,
                $annual_cta_button,
                $annual_cta_link
            ];

            if ($new_image) {
                $sql .= ", hero_image=?";
                $types .= "s";
                $params[] = $new_image;
            }

            $sql .= " WHERE id=?";
            $types .= "i";
            $params[] = (int)$pageRow['id'];

            $st = $conn->prepare($sql);
            $st->bind_param($types, ...$params);
            $st->execute();

            setFlashMessage('success', 'Impact page updated successfully.');
        } else {
            $st = $conn->prepare("
                INSERT INTO impact_page 
                (hero_title, hero_subtitle, intro_title, intro_text, hero_image, annual_cta_title, annual_cta_text, annual_cta_button, annual_cta_link, is_active)
                VALUES (?,?,?,?,?,?,?,?,?,1)
            ");

            $hero_image = $new_image;

            $st->bind_param(
                "sssssssss",
                $hero_title,
                $hero_subtitle,
                $intro_title,
                $intro_text,
                $hero_image,
                $annual_cta_title,
                $annual_cta_text,
                $annual_cta_button,
                $annual_cta_link
            );

            $st->execute();

            setFlashMessage('success', 'Impact page created successfully.');
        }

        header("Location: our-impact.php");
        exit;
    }

    if ($action === 'save_stat') {
        $id = (int)($_POST['id'] ?? 0);
        $group_key = ($_POST['group_key'] ?? 'current') === 'alumni' ? 'alumni' : 'current';
        $stat_value = clean($conn, $_POST['stat_value'] ?? '');
        $stat_label = clean($conn, $_POST['stat_label'] ?? '');
        $icon_class = clean($conn, $_POST['icon_class'] ?? 'fas fa-chart-line');
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $bg_color = clean($conn, $_POST['bg_color'] ?? '');
        $title_color = clean($conn, $_POST['title_color'] ?? '');
        $label_color = clean($conn, $_POST['label_color'] ?? '');

        if ($stat_value === '' || $stat_label === '') {
            setFlashMessage('error', 'Stat value and label are required.');
            header("Location: our-impact.php");
            exit;
        }

        if ($icon_class === '') {
            $icon_class = 'fas fa-chart-line';
        }

        if ($id > 0) {
            $st = $conn->prepare("
                UPDATE impact_stats
                SET group_key=?, stat_value=?, stat_label=?, icon_class=?, bg_color=?, title_color=?, label_color=?, sort_order=?, is_active=?
                WHERE id=?
            ");

            $st->bind_param(
                "sssssssiii",
                $group_key,
                $stat_value,
                $stat_label,
                $icon_class,
                $bg_color,
                $title_color,
                $label_color,
                $sort_order,
                $is_active,
                $id
            );

            $st->execute();

            setFlashMessage('success', 'Stat updated successfully.');
        } else {
            $st = $conn->prepare("
                INSERT INTO impact_stats
                (group_key, stat_value, stat_label, icon_class, bg_color, title_color, label_color, sort_order, is_active)
                VALUES (?,?,?,?,?,?,?,?,?)
            ");

            $st->bind_param(
                "sssssssii",
                $group_key,
                $stat_value,
                $stat_label,
                $icon_class,
                $bg_color,
                $title_color,
                $label_color,
                $sort_order,
                $is_active
            );

            $st->execute();

            setFlashMessage('success', 'Stat added successfully.');
        }

        header("Location: our-impact.php");
        exit;
    }

    if ($action === 'delete_stat') {
        $id = (int)($_POST['id'] ?? 0);

        $st = $conn->prepare("DELETE FROM impact_stats WHERE id=?");
        $st->bind_param("i", $id);
        $st->execute();

        setFlashMessage('success', 'Stat deleted successfully.');
        header("Location: our-impact.php");
        exit;
    }
}

$pageRow = impactPageRow($conn);

$stats = [];
$res = $conn->query("SELECT * FROM impact_stats ORDER BY group_key ASC, sort_order ASC, id ASC");

if ($res) {
    while ($r = $res->fetch_assoc()) {
        $stats[] = $r;
    }
}

include 'layout/header.php';
include 'layout/sidebar.php';
?>

<style>
.impact-grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
}

@media(max-width:1100px) {
    .impact-grid {
        grid-template-columns:1fr;
    }
}

.preview-img {
    max-width:420px;
    width:100%;
    border-radius:14px;
    border:2px solid #e5e7eb;
    background:#fff;
}

.group-card {
    background:#fff;
    border:1px solid #eef2f7;
    border-radius:14px;
    box-shadow:0 10px 28px rgba(0,0,0,.06);
}

.group-card .head {
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:14px 16px;
    border-bottom:1px solid #eef2f7;
}

.group-card .head h3 {
    margin:0;
    font-size:16px;
    font-weight:900;
}

.group-card .body {
    padding:10px 12px;
}

.drag-row {
    display:flex;
    align-items:center;
    gap:10px;
    padding:10px;
    border:1px solid #eef2f7;
    border-radius:12px;
    margin-bottom:8px;
    background:#fff;
}

.drag-handle {
    cursor:grab;
    color:#9ca3af;
}

.stat-icon-preview {
    width:42px;
    height:42px;
    border-radius:50%;
    background:rgba(248,101,5,.1);
    color:#f86505;
    display:flex;
    align-items:center;
    justify-content:center;
    flex:0 0 42px;
}

.stat-icon-preview i {
    font-size:18px;
}

.drag-row strong {
    display:block;
}

.drag-row small {
    color:#6b7280;
}

.inline-actions {
    margin-left:auto;
    display:flex;
    gap:6px;
    align-items:center;
}

.toggle {
    width:42px;
    height:24px;
    border-radius:999px;
    background:#e5e7eb;
    position:relative;
    cursor:pointer;
    border:1px solid #d1d5db;
}

.toggle::after {
    content:"";
    position:absolute;
    top:50%;
    transform:translateY(-50%);
    left:3px;
    width:18px;
    height:18px;
    border-radius:999px;
    background:#fff;
    box-shadow:0 2px 6px rgba(0,0,0,.15);
    transition:all .2s ease;
}

.toggle.on {
    background:#dcfce7;
    border-color:#86efac;
}

.toggle.on::after {
    left:21px;
    background:#16a34a;
}

.badge {
    font-size:11px;
    font-weight:800;
    padding:3px 10px;
    border-radius:999px;
}

.badge-current {
    background:#dbeafe;
    color:#1e40af;
}

.badge-alumni {
    background:#fef3c7;
    color:#92400e;
}

.icon-picker {
    display:grid;
    grid-template-columns:repeat(5, 1fr);
    gap:8px;
    margin-top:8px;
}

.icon-option {
    border:1px solid #e5e7eb;
    border-radius:10px;
    padding:10px;
    cursor:pointer;
    text-align:center;
    background:#fff;
    transition:.2s ease;
}

.icon-option:hover,
.icon-option.active {
    border-color:#f86505;
    background:#fff7ed;
    color:#f86505;
}

.icon-option i {
    font-size:20px;
    display:block;
    margin-bottom:5px;
}

.icon-option span {
    font-size:10px;
    font-weight:700;
}

@media(max-width:700px) {
    .icon-picker {
        grid-template-columns:repeat(2, 1fr);
    }
}
</style>

<div class="content-header">
    <h2><i class="fas fa-chart-line" style="color:#f86505;"></i> Manage Our Impact</h2>
    <button class="btn btn-primary" onclick="openStatModal()">
        <i class="fas fa-plus"></i> Add Stat
    </button>
</div>

<?php $flash = getFlashMessage(); if ($flash): ?>
    <div class="alert alert-<?php echo h($flash['type']); ?>">
        <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo h($flash['message']); ?>
    </div>
<?php endif; ?>

<div class="impact-grid">

    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <strong>Page Content</strong>
            <a href="../our-impact.php" target="_blank" class="btn btn-sm">
                <i class="fas fa-eye"></i> View Page
            </a>
        </div>

        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_page">

                <div class="form-group">
                    <label>Hero Title</label>
                    <input type="text" name="hero_title" value="<?php echo h($pageRow['hero_title'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Hero Subtitle</label>
                    <textarea name="hero_subtitle" rows="3"><?php echo h($pageRow['hero_subtitle'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Hero Featured Image</label>
                    <input type="file" name="hero_image" accept="image/*">
                    <small>Uploads to: <strong>uploads/impact/</strong></small>
                </div>

                <?php if (!empty($pageRow['hero_image'])): ?>
                    <div class="form-group">
                        <label>Current Image</label><br>
                        <img class="preview-img" src="../uploads/impact/<?php echo h($pageRow['hero_image']); ?>" alt="">
                    </div>
                <?php endif; ?>

                <hr>

                <div class="form-group">
                    <label>Intro Title</label>
                    <input type="text" name="intro_title" value="<?php echo h($pageRow['intro_title'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Intro Text</label>
                    <textarea name="intro_text" rows="7"><?php echo h($pageRow['intro_text'] ?? ''); ?></textarea>
                </div>

                <hr>

                <div class="form-group">
                    <label>Annual Reports CTA Title</label>
                    <input type="text" name="annual_cta_title" value="<?php echo h($pageRow['annual_cta_title'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Annual Reports CTA Text</label>
                    <textarea name="annual_cta_text" rows="3"><?php echo h($pageRow['annual_cta_text'] ?? ''); ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group col-6">
                        <label>CTA Button Text</label>
                        <input type="text" name="annual_cta_button" value="<?php echo h($pageRow['annual_cta_button'] ?? ''); ?>">
                    </div>

                    <div class="form-group col-6">
                        <label>CTA Link</label>
                        <input type="text" name="annual_cta_link" value="<?php echo h($pageRow['annual_cta_link'] ?? ''); ?>">
                    </div>
                </div>

                <button class="btn btn-primary" type="submit">
                    <i class="fas fa-save"></i> Save Page
                </button>
            </form>
        </div>
    </div>

    <div class="group-card">
        <div class="head">
            <h3>Stats</h3>
            <small style="color:#6b7280;">Drag, edit, toggle active</small>
        </div>

        <div class="body">
            <?php if (empty($stats)): ?>
                <div style="text-align:center;padding:26px;color:#6b7280;">
                    <i class="fas fa-inbox" style="font-size:40px;color:#d1d5db;display:block;margin-bottom:10px;"></i>
                    <strong>No stats found</strong><br>
                    <small>Add stats for Current and Alumni.</small>
                </div>
            <?php else: ?>
                <div id="statsList">
                    <?php foreach ($stats as $s):
                        $icon = !empty($s['icon_class']) ? $s['icon_class'] : 'fas fa-chart-line';
                    ?>
                        <div class="drag-row" draggable="true" data-id="<?php echo (int)$s['id']; ?>">
                            <span class="drag-handle" title="Drag">
                                <i class="fas fa-grip-vertical"></i>
                            </span>

                            <div class="stat-icon-preview">
                                <i class="<?php echo h($icon); ?>"></i>
                            </div>

                            <div>
                                <strong>
                                    <?php echo h($s['stat_value']); ?>
                                    <span class="badge <?php echo $s['group_key'] === 'alumni' ? 'badge-alumni' : 'badge-current'; ?>">
                                        <?php echo h(strtoupper($s['group_key'])); ?>
                                    </span>
                                </strong>
                                <small><?php echo h($s['stat_label']); ?></small>
                            </div>

                            <div class="inline-actions">
                                <div class="toggle <?php echo !empty($s['is_active']) ? 'on' : ''; ?>"
                                     onclick="toggleStat(<?php echo (int)$s['id']; ?>, this)"
                                     title="Activate/Deactivate"></div>

                                <button class="btn btn-sm btn-primary" onclick='editStat(<?php echo json_encode($s, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>

                                <button class="btn btn-sm btn-danger" onclick="deleteStat(<?php echo (int)$s['id']; ?>, '<?php echo h(addslashes($s['stat_label'])); ?>')">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <small style="color:#6b7280;display:block;margin-top:10px;">
                Tip: The frontend separates stats using the group value.
            </small>
        </div>
    </div>

</div>

<div id="statModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:850px;max-height:90vh;overflow-y:auto;">
        <div class="modal-header">
            <h3><i class="fas fa-chart-bar"></i> <span id="statModalTitle">Add Stat</span></h3>
            <button class="modal-close" onclick="closeStatModal()">&times;</button>
        </div>

        <form method="POST" id="statForm">
            <div class="modal-body">
                <input type="hidden" name="action" value="save_stat">
                <input type="hidden" name="id" id="statId" value="0">

                <div class="form-row">
                    <div class="form-group col-6">
                        <label>Group</label>
                        <select name="group_key" id="statGroup">
                            <option value="current">Current Stats</option>
                            <option value="alumni">Alumni Stats</option>
                        </select>
                    </div>

                    <div class="form-group col-6">
                        <label>Sort Order</label>
                        <input type="number" name="sort_order" id="statOrder" value="0" min="0">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-4">
                        <label>Value *</label>
                        <input type="text" name="stat_value" id="statValue" required placeholder="e.g. 4000+, 85%, 100%">
                    </div>

                    <div class="form-group col-8">
                        <label>Label *</label>
                        <input type="text" name="stat_label" id="statLabel" required placeholder="e.g. Women and girls reached">
                    </div>
                </div>

                <div class="form-group">
                    <label>Font Awesome Icon Class</label>
                    <input type="text" name="icon_class" id="statIcon" placeholder="fas fa-user-graduate">
                    <small>Example: <strong>fas fa-user-graduate</strong>, <strong>fas fa-users</strong>, <strong>fas fa-briefcase</strong></small>

                    <div class="icon-picker" id="iconPicker">
                        <div class="icon-option" data-icon="fas fa-user-graduate"><i class="fas fa-user-graduate"></i><span>Graduates</span></div>
                        <div class="icon-option" data-icon="fas fa-users"><i class="fas fa-users"></i><span>People</span></div>
                        <div class="icon-option" data-icon="fas fa-person-dress"><i class="fas fa-person-dress"></i><span>Women</span></div>
                        <div class="icon-option" data-icon="fas fa-briefcase"><i class="fas fa-briefcase"></i><span>Jobs</span></div>
                        <div class="icon-option" data-icon="fas fa-store"><i class="fas fa-store"></i><span>Business</span></div>
                        <div class="icon-option" data-icon="fas fa-laptop-code"><i class="fas fa-laptop-code"></i><span>STEM</span></div>
                        <div class="icon-option" data-icon="fas fa-chalkboard-user"><i class="fas fa-chalkboard-user"></i><span>Mentors</span></div>
                        <div class="icon-option" data-icon="fas fa-book-open"><i class="fas fa-book-open"></i><span>Courses</span></div>
                        <div class="icon-option" data-icon="fas fa-chart-line"><i class="fas fa-chart-line"></i><span>Growth</span></div>
                        <div class="icon-option" data-icon="fas fa-handshake"><i class="fas fa-handshake"></i><span>Partners</span></div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-4">
                        <label>Card Background</label>
                        <input type="text" name="bg_color" id="statBgColor" placeholder="#ffffff">
                    </div>

                    <div class="form-group col-4">
                        <label>Value Color</label>
                        <input type="text" name="title_color" id="statTitleColor" placeholder="#111827">
                    </div>

                    <div class="form-group col-4">
                        <label>Label Color</label>
                        <input type="text" name="label_color" id="statLabelColor" placeholder="#374151">
                    </div>
                </div>

                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="is_active" id="statActive" value="1" checked>
                        <span>Active</span>
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeStatModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Stat
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openStatModal() {
    document.getElementById('statModalTitle').textContent = 'Add Stat';
    document.getElementById('statForm').reset();
    document.getElementById('statId').value = '0';
    document.getElementById('statOrder').value = '0';
    document.getElementById('statIcon').value = 'fas fa-chart-line';
    document.getElementById('statActive').checked = true;
    refreshIconPicker();
    document.getElementById('statModal').style.display = 'flex';
}

function editStat(s) {
    document.getElementById('statModalTitle').textContent = 'Edit Stat';
    document.getElementById('statId').value = s.id || 0;
    document.getElementById('statGroup').value = s.group_key || 'current';
    document.getElementById('statValue').value = s.stat_value || '';
    document.getElementById('statLabel').value = s.stat_label || '';
    document.getElementById('statIcon').value = s.icon_class || 'fas fa-chart-line';
    document.getElementById('statBgColor').value = s.bg_color || '';
    document.getElementById('statTitleColor').value = s.title_color || '';
    document.getElementById('statLabelColor').value = s.label_color || '';
    document.getElementById('statOrder').value = s.sort_order || 0;
    document.getElementById('statActive').checked = parseInt(s.is_active) === 1;
    refreshIconPicker();
    document.getElementById('statModal').style.display = 'flex';
}

function closeStatModal() {
    document.getElementById('statModal').style.display = 'none';
    document.getElementById('statForm').reset();
}

function deleteStat(id, label) {
    if (!confirm(`Delete this stat?\n\n${label}`)) return;

    const f = document.createElement('form');
    f.method = 'POST';
    f.innerHTML = `<input type="hidden" name="action" value="delete_stat">
                   <input type="hidden" name="id" value="${id}">`;

    document.body.appendChild(f);
    f.submit();
}

function refreshIconPicker() {
    const selected = document.getElementById('statIcon').value.trim();

    document.querySelectorAll('.icon-option').forEach(option => {
        option.classList.toggle('active', option.dataset.icon === selected);
    });
}

document.querySelectorAll('.icon-option').forEach(option => {
    option.addEventListener('click', function () {
        document.getElementById('statIcon').value = this.dataset.icon;
        refreshIconPicker();
    });
});

document.getElementById('statIcon').addEventListener('input', refreshIconPicker);

async function toggleStat(id, el) {
    try {
        const isOn = el.classList.contains('on');

        const form = new FormData();
        form.append('id', id);
        form.append('is_active', isOn ? '0' : '1');

        const res = await fetch('our-impact-toggle.php', {
            method:'POST',
            body:form
        });

        const data = await res.json();

        if (!data.success) {
            throw new Error(data.message || 'Failed');
        }

        el.classList.toggle('on', !isOn);
    } catch (err) {
        alert(err.message || 'Failed to toggle.');
    }
}

(function () {
    const list = document.getElementById('statsList');
    if (!list) return;

    let dragged = null;

    list.querySelectorAll('.drag-row').forEach(row => {
        row.addEventListener('dragstart', () => {
            dragged = row;
            row.style.opacity = '0.6';
        });

        row.addEventListener('dragend', () => {
            row.style.opacity = '1';
            dragged = null;
            saveOrder();
        });

        row.addEventListener('dragover', e => {
            e.preventDefault();

            const target = row;

            if (!dragged || dragged === target) return;

            const rect = target.getBoundingClientRect();
            const next = (e.clientY - rect.top) > (rect.height / 2);

            list.insertBefore(dragged, next ? target.nextSibling : target);
        });
    });

    async function saveOrder() {
        const ids = Array.from(list.querySelectorAll('.drag-row')).map((r, idx) => ({
            id:parseInt(r.dataset.id, 10),
            sort_order:idx
        }));

        try {
            const res = await fetch('our-impact-reorder.php', {
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body:JSON.stringify({items:ids})
            });

            const data = await res.json();

            if (!data.success) {
                throw new Error(data.message || 'Failed to save order');
            }
        } catch (err) {
            console.log(err);
        }
    }
})();

window.addEventListener('click', e => {
    if (e.target.id === 'statModal') closeStatModal();
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeStatModal();
});
</script>

<?php include 'layout/footer.php'; ?>