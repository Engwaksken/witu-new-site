<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/popup.php';
require_once __DIR__ . '/includes/admin-enhancements.php';
adminTrackActivity($conn);

requireLogin();
if (!hasPermission($conn, 'manage_popups') && !hasPermission($conn, 'manage_settings')) {
    die('<h2>Access Denied</h2><p>You don\'t have permission to manage Site Popups. Please contact your administrator.</p>');
}

ensurePopupTable($conn);

// Register the permission so it can be assigned to roles from the Roles screen.
$st = $conn->prepare("SELECT id FROM permissions WHERE name = 'manage_popups'");
$st->execute();
if ($st->get_result()->num_rows === 0) {
    $conn->query("INSERT INTO permissions (name, description) VALUES ('manage_popups', 'Manage frontend site popups')");
}
$st->close();

$page_title = 'Site Popups';

function popupDateOrNull(string $value): ?string
{
    $value = trim($value);
    if ($value === '') return null;
    $ts = strtotime($value);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

/* POST actions */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id             = (int)($_POST['id'] ?? 0);
        $title          = trim($_POST['title'] ?? '');
        $content        = popupSanitizeContent(trim($_POST['content'] ?? ''));
        $button_text    = trim($_POST['button_text'] ?? '');
        $button_url     = trim($_POST['button_url'] ?? '');
        $button_new_tab = isset($_POST['button_new_tab']) ? 1 : 0;
        $delay          = max(0, min(120, (int)($_POST['delay_seconds'] ?? 3)));
        $frequency      = $_POST['frequency'] ?? 'once_per_session';
        $priority       = (int)($_POST['priority'] ?? 0);
        $is_active      = isset($_POST['is_active']) ? 1 : 0;
        $start_at       = popupDateOrNull($_POST['start_at'] ?? '');
        $end_at         = popupDateOrNull($_POST['end_at'] ?? '');

        $pages = array_values(array_intersect((array)($_POST['pages'] ?? []), array_keys(POPUP_PAGES)));
        $pages = ($_POST['page_scope'] ?? 'all') === 'selected' ? implode(',', $pages) : '';

        if (!array_key_exists($frequency, POPUP_FREQUENCIES)) $frequency = 'once_per_session';
        if (preg_match('/^\s*javascript:/i', $button_url)) $button_url = '';

        if ($title === '') {
            setFlashMessage('error', 'A popup title is required.');
            header("Location: popups.php"); exit;
        }
        if (($_POST['page_scope'] ?? 'all') === 'selected' && $pages === '') {
            setFlashMessage('error', 'Select at least one page, or choose "All pages".');
            header("Location: popups.php"); exit;
        }
        if ($start_at && $end_at && $end_at < $start_at) {
            setFlashMessage('error', 'The end date must be after the start date.');
            header("Location: popups.php"); exit;
        }

        $new_image = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $up = uploadFile($_FILES['image'], 'popups', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            if (empty($up['success'])) {
                setFlashMessage('error', $up['message'] ?? 'Image upload failed.');
                header("Location: popups.php"); exit;
            }
            $new_image = $up['filename'];
        }

        $old_image = '';
        if ($id > 0) {
            $st = $conn->prepare("SELECT image FROM site_popups WHERE id = ?");
            $st->bind_param("i", $id);
            $st->execute();
            $old_image = (string)($st->get_result()->fetch_assoc()['image'] ?? '');
            $st->close();
        }
        $image = $new_image ?: (isset($_POST['remove_image']) ? '' : $old_image);
        if ($old_image !== '' && $image !== $old_image) deleteFile($old_image, 'popups');

        if ($id > 0) {
            $st = $conn->prepare("UPDATE site_popups SET title=?, content=?, image=?, button_text=?, button_url=?, button_new_tab=?,
                pages=?, delay_seconds=?, frequency=?, start_at=?, end_at=?, priority=?, is_active=? WHERE id=?");
            $st->bind_param("sssssisisssiii", $title, $content, $image, $button_text, $button_url, $button_new_tab,
                $pages, $delay, $frequency, $start_at, $end_at, $priority, $is_active, $id);
            $st->execute();
            $st->close();
            setFlashMessage('success', 'Popup <strong>' . htmlspecialchars($title) . '</strong> updated.');
        } else {
            $st = $conn->prepare("INSERT INTO site_popups (title, content, image, button_text, button_url, button_new_tab,
                pages, delay_seconds, frequency, start_at, end_at, priority, is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $st->bind_param("sssssisisssii", $title, $content, $image, $button_text, $button_url, $button_new_tab,
                $pages, $delay, $frequency, $start_at, $end_at, $priority, $is_active);
            $st->execute();
            $st->close();
            setFlashMessage('success', 'Popup <strong>' . htmlspecialchars($title) . '</strong> added.');
        }
        header("Location: popups.php"); exit;
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) $conn->query("UPDATE site_popups SET is_active = 1 - is_active WHERE id = $id");
        header("Location: popups.php"); exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $st = $conn->prepare("SELECT image FROM site_popups WHERE id = ?");
            $st->bind_param("i", $id);
            $st->execute();
            $old = $st->get_result()->fetch_assoc();
            $st->close();
            if ($old && !empty($old['image'])) deleteFile($old['image'], 'popups');

            $st = $conn->prepare("DELETE FROM site_popups WHERE id = ?");
            $st->bind_param("i", $id);
            $st->execute();
            $st->close();
            setFlashMessage('success', 'Popup deleted.');
        }
        header("Location: popups.php"); exit;
    }
}

/* Fetch all popups */
$popups = [];
$res = $conn->query("SELECT * FROM site_popups ORDER BY is_active DESC, priority DESC, updated_at DESC");
if ($res) while ($r = $res->fetch_assoc()) $popups[] = $r;

// Which popup visitors currently see on the home page (the highest-priority live one).
$live_home = getActivePopup($conn, 'index.php');

function popupStatus(array $p): array
{
    if (!(int)$p['is_active']) return ['inactive', 'Inactive'];
    $now = time();
    if ($p['start_at'] && strtotime($p['start_at']) > $now) return ['scheduled', 'Scheduled'];
    if ($p['end_at'] && strtotime($p['end_at']) < $now) return ['expired', 'Expired'];
    return ['active', 'Live'];
}

include 'layout/header.php';
include 'layout/sidebar.php';
?>

<style>
.pp-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(min(320px, 100%), 1fr)); gap:20px; margin-top:4px; }
.pp-card { border:1px solid #e5e7eb; border-radius:14px; background:#fff; overflow:hidden; box-shadow:0 4px 16px rgba(0,0,0,.04); display:flex; flex-direction:column; transition:box-shadow .22s; }
.pp-card:hover { box-shadow:0 8px 28px rgba(0,0,0,.09); }
.pp-card-img { position:relative; aspect-ratio:16/7; background:#1a1a1a; overflow:hidden; }
.pp-card-img img { width:100%; height:100%; object-fit:cover; display:block; }
.pp-card-placeholder { width:100%; height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; color:rgba(255,255,255,.3); }
.pp-card-placeholder i { font-size:36px; }
.pp-ribbon { position:absolute; top:10px; left:10px; padding:3px 10px; border-radius:999px; font-size:10px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#fff; }
.pp-ribbon.active { background:rgba(16,185,129,.9); }
.pp-ribbon.inactive, .pp-ribbon.expired { background:rgba(239,68,68,.85); }
.pp-ribbon.scheduled { background:rgba(59,130,246,.9); }
.pp-showing { position:absolute; top:10px; right:10px; padding:3px 10px; border-radius:999px; font-size:10px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; background:#f86505; color:#fff; }
.pp-card-body { padding:16px 18px 18px; flex:1; display:flex; flex-direction:column; gap:10px; }
.pp-title { font-size:15px; font-weight:900; color:#111827; }
.pp-excerpt { color:#6b7280; font-size:13px; line-height:1.5; }
.pp-meta { display:flex; flex-wrap:wrap; gap:6px; }
.pp-chip { font-size:11px; color:#4b5563; background:#f3f4f6; padding:3px 8px; border-radius:4px; }
.pp-chip i { color:#f86505; margin-right:3px; }
.pp-actions { display:flex; gap:8px; flex-wrap:wrap; margin-top:auto; }
.pp-modal .modal-content { max-width:720px; }
.form-section-label { font-size:10px; font-weight:700; letter-spacing:.18em; text-transform:uppercase; color:#6b7280; border-bottom:1px solid #e5e7eb; padding-bottom:6px; margin:18px 0 12px; }
.pp-pages { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:6px 12px; margin-top:10px; }
.pp-pages label, .pp-check { display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:400; }
.pp-scope { display:flex; gap:20px; flex-wrap:wrap; }
</style>

<div class="content-header">
    <h2><i class="fas fa-window-restore" style="color:#f86505;"></i> Site Popups</h2>
    <button class="btn btn-primary" onclick="openPopupAdd()">
        <i class="fas fa-plus"></i> Add Popup
    </button>
</div>

<?php $flash = getFlashMessage(); if ($flash): ?>
<div class="alert alert-<?php echo $flash['type']; ?>">
    <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
    <?php echo $flash['message']; ?>
</div>
<?php endif; ?>

<p style="color:#6b7280;font-size:14px;margin-bottom:20px;">
    Popups appear centred over the hero banner at the top of each page, without blocking scrolling. When several popups are live on the same page,
    visitors see the one with the highest priority.
</p>

<?php if (empty($popups)): ?>
    <div style="text-align:center;padding:60px;color:#6b7280;">
        <i class="fas fa-window-restore" style="font-size:48px;color:#d1d5db;display:block;margin-bottom:16px;"></i>
        <strong>No popups yet.</strong><br>
        <small>Click "Add Popup" to create an announcement for your visitors.</small>
    </div>
<?php else: ?>
    <div class="pp-grid">
        <?php foreach ($popups as $p):
            [$statusClass, $statusLabel] = popupStatus($p);
            $pageList = array_filter(explode(',', (string)$p['pages']));
            $pageLabels = array_map(fn($f) => POPUP_PAGES[$f] ?? $f, $pageList);
        ?>
        <div class="pp-card">
            <div class="pp-card-img">
                <?php if (!empty($p['image'])): ?>
                    <img src="../uploads/popups/<?php echo htmlspecialchars($p['image']); ?>" alt="">
                <?php else: ?>
                    <div class="pp-card-placeholder"><i class="fas fa-window-restore"></i></div>
                <?php endif; ?>
                <span class="pp-ribbon <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
                <?php if ($live_home && (int)$live_home['id'] === (int)$p['id']): ?>
                    <span class="pp-showing">Showing on Home</span>
                <?php endif; ?>
            </div>

            <div class="pp-card-body">
                <span class="pp-title"><?php echo htmlspecialchars($p['title']); ?></span>
                <?php if (trim(strip_tags((string)$p['content'])) !== ''): ?>
                    <span class="pp-excerpt"><?php echo htmlspecialchars(truncate(trim(strip_tags($p['content'])), 120)); ?></span>
                <?php endif; ?>

                <div class="pp-meta">
                    <span class="pp-chip"><i class="fas fa-file"></i><?php echo $pageLabels ? htmlspecialchars(implode(', ', $pageLabels)) : 'All pages'; ?></span>
                    <span class="pp-chip"><i class="fas fa-repeat"></i><?php echo htmlspecialchars(POPUP_FREQUENCIES[$p['frequency']] ?? $p['frequency']); ?></span>
                    <span class="pp-chip"><i class="fas fa-stopwatch"></i><?php echo (int)$p['delay_seconds']; ?>s delay</span>
                    <span class="pp-chip"><i class="fas fa-arrow-up-wide-short"></i>Priority <?php echo (int)$p['priority']; ?></span>
                    <?php if ($p['start_at'] || $p['end_at']): ?>
                        <span class="pp-chip"><i class="fas fa-calendar"></i>
                            <?php echo $p['start_at'] ? formatDate($p['start_at']) : 'Now'; ?> &ndash; <?php echo $p['end_at'] ? formatDate($p['end_at']) : 'No end'; ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="pp-actions">
                    <button onclick='openPopupEdit(<?php echo json_encode($p, JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)'
                            class="btn btn-sm btn-primary" style="flex:1;">
                        <i class="fas fa-pen"></i> Edit
                    </button>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                        <button type="submit" class="btn btn-sm <?php echo (int)$p['is_active'] ? 'btn-warning' : 'btn-success'; ?>"
                                title="<?php echo (int)$p['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                            <i class="fas fa-<?php echo (int)$p['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                        </button>
                    </form>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this popup?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger" title="Delete popup">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>


<!-- -- MODAL ------------------------------------------------ -->
<div id="ppModal" class="modal pp-modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-window-restore"></i> <span id="ppModalTitle">Add Popup</span></h3>
            <button class="modal-close" onclick="closePopupModal()">&times;</button>
        </div>

        <form method="POST" enctype="multipart/form-data" id="ppForm">
            <div class="modal-body">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="ppId" value="0">

                <p class="form-section-label">Content</p>
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" id="ppTitle" required maxlength="255"
                           placeholder="e.g., Applications for Elevate Accelerator are open">
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="content" id="ppContent" rows="5"
                              placeholder="Tell visitors what's happening..."></textarea>
                    <small>Plain text or simple HTML: &lt;p&gt;, &lt;strong&gt;, &lt;em&gt;, &lt;a&gt;, &lt;ul&gt;/&lt;li&gt;, &lt;br&gt;</small>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-image" style="color:#f86505;"></i> Image (optional)</label>
                    <input type="file" name="image" id="ppImage" accept="image/jpeg,image/png,image/gif,image/webp"
                           onchange="previewPopupImage(this)">
                    <small>Shown as a 16:9 banner across the top of the popup. Upload at least 960 x 540 px (JPG or WebP, under 300 KB).</small>
                </div>
                <div id="ppPreviewWrap" style="display:none;margin-bottom:16px;">
                    <img id="ppPreviewImg" src="" alt=""
                         style="width:100%;max-height:200px;object-fit:cover;border-radius:10px;border:2px solid #e5e7eb;display:block;">
                    <label class="pp-check" id="ppRemoveWrap" style="margin-top:8px;">
                        <input type="checkbox" name="remove_image" id="ppRemoveImage" value="1">
                        <span>Remove current image</span>
                    </label>
                </div>

                <p class="form-section-label">Call to Action (optional)</p>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label>Button Text</label>
                        <input type="text" name="button_text" id="ppBtnText" maxlength="100" placeholder="e.g., Apply Now">
                    </div>
                    <div class="form-group col-6">
                        <label>Button Link</label>
                        <input type="text" name="button_url" id="ppBtnUrl" maxlength="500" placeholder="e.g., programmes.php or https://...">
                    </div>
                </div>
                <label class="pp-check">
                    <input type="checkbox" name="button_new_tab" id="ppBtnNewTab" value="1">
                    <span>Open link in a new tab</span>
                </label>

                <p class="form-section-label">Where to Show</p>
                <div class="pp-scope">
                    <label class="pp-check"><input type="radio" name="page_scope" value="all" id="ppScopeAll" checked onchange="togglePopupPages()"> All pages</label>
                    <label class="pp-check"><input type="radio" name="page_scope" value="selected" id="ppScopeSelected" onchange="togglePopupPages()"> Selected pages only</label>
                </div>
                <div class="pp-pages" id="ppPages" style="display:none;">
                    <?php foreach (POPUP_PAGES as $file => $label): ?>
                        <label><input type="checkbox" name="pages[]" value="<?php echo htmlspecialchars($file); ?>"> <?php echo htmlspecialchars($label); ?></label>
                    <?php endforeach; ?>
                </div>

                <p class="form-section-label">When to Show</p>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label>Show How Often</label>
                        <select name="frequency" id="ppFrequency">
                            <?php foreach (POPUP_FREQUENCIES as $value => $label): ?>
                                <option value="<?php echo $value; ?>"><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small>Editing a popup shows it again to visitors who already closed it.</small>
                    </div>
                    <div class="form-group col-6">
                        <label>Delay (seconds)</label>
                        <input type="number" name="delay_seconds" id="ppDelay" min="0" max="120" value="3">
                        <small>Time after the page loads before the popup opens.</small>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label>Start Showing</label>
                        <input type="datetime-local" name="start_at" id="ppStart">
                        <small>Leave blank to start immediately.</small>
                    </div>
                    <div class="form-group col-6">
                        <label>Stop Showing</label>
                        <input type="datetime-local" name="end_at" id="ppEnd">
                        <small>Leave blank to keep showing.</small>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label>Priority</label>
                        <input type="number" name="priority" id="ppPriority" value="0">
                        <small>Higher wins when several popups are live.</small>
                    </div>
                    <div class="form-group col-6" style="display:flex;align-items:center;">
                        <label class="pp-check">
                            <input type="checkbox" name="is_active" id="ppActive" value="1" checked>
                            <span>Active (visible on frontend)</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn" onclick="closePopupModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Popup
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toLocalInput(value) {
    return value ? value.replace(' ', 'T').slice(0, 16) : '';
}

function togglePopupPages() {
    document.getElementById('ppPages').style.display =
        document.getElementById('ppScopeSelected').checked ? 'grid' : 'none';
}

function setPopupPages(pages) {
    var selected = (pages || '').split(',').filter(Boolean);
    document.querySelectorAll('#ppPages input').forEach(function (cb) {
        cb.checked = selected.indexOf(cb.value) !== -1;
    });
    document.getElementById(selected.length ? 'ppScopeSelected' : 'ppScopeAll').checked = true;
    togglePopupPages();
}

function openPopupAdd() {
    document.getElementById('ppModalTitle').textContent = 'Add Popup';
    document.getElementById('ppForm').reset();
    document.getElementById('ppId').value = '0';
    setPopupPages('');
    document.getElementById('ppPreviewWrap').style.display = 'none';
    document.getElementById('ppModal').style.display = 'flex';
}

function openPopupEdit(p) {
    document.getElementById('ppForm').reset();
    document.getElementById('ppModalTitle').textContent = 'Edit Popup - ' + p.title;
    document.getElementById('ppId').value          = p.id;
    document.getElementById('ppTitle').value       = p.title || '';
    document.getElementById('ppContent').value     = p.content || '';
    document.getElementById('ppBtnText').value     = p.button_text || '';
    document.getElementById('ppBtnUrl').value      = p.button_url || '';
    document.getElementById('ppBtnNewTab').checked = p.button_new_tab == 1;
    document.getElementById('ppFrequency').value   = p.frequency || 'once_per_session';
    document.getElementById('ppDelay').value       = p.delay_seconds;
    document.getElementById('ppStart').value       = toLocalInput(p.start_at);
    document.getElementById('ppEnd').value         = toLocalInput(p.end_at);
    document.getElementById('ppPriority').value    = p.priority;
    document.getElementById('ppActive').checked    = p.is_active == 1;
    setPopupPages(p.pages);

    if (p.image) {
        document.getElementById('ppPreviewImg').src = '../uploads/popups/' + p.image;
        document.getElementById('ppRemoveWrap').style.display = 'flex';
        document.getElementById('ppPreviewWrap').style.display = 'block';
    } else {
        document.getElementById('ppPreviewWrap').style.display = 'none';
    }
    document.getElementById('ppModal').style.display = 'flex';
}

function closePopupModal() {
    document.getElementById('ppModal').style.display = 'none';
    document.getElementById('ppForm').reset();
}

function previewPopupImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function (e) {
            document.getElementById('ppPreviewImg').src = e.target.result;
            document.getElementById('ppRemoveWrap').style.display = 'none';
            document.getElementById('ppPreviewWrap').style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

window.onclick = function (e) { if (e.target.id === 'ppModal') closePopupModal(); };
document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closePopupModal(); });
</script>

<?php include 'layout/footer.php'; ?>
