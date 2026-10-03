<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/includes/admin-enhancements.php';
adminTrackActivity($conn);

$has_permission = false;
if (function_exists('hasPermission')) {
    if (hasPermission($conn, 'manage_hero_images'))  $has_permission = true;
    elseif (hasPermission($conn, 'manage_content'))  $has_permission = true;
} else {
    $has_permission = true;
}

if (!$has_permission) {
    die('<h2>Access Denied</h2><p>You don\'t have permission to manage Hero Images. Please contact your administrator.</p>');
}

$page_title = 'Hero Images';

/* POST actions  */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* Save image + meta for one page */
    if ($action === 'save') {
        $id              = (int)($_POST['id']               ?? 0);
        $page_key        = trim($_POST['page_key']          ?? '');
        $page_label      = trim($_POST['page_label']        ?? '');
        $alt_text        = trim($_POST['alt_text']          ?? '');
        $overlay_title   = trim($_POST['overlay_title']     ?? '');
        $overlay_subtitle= trim($_POST['overlay_subtitle']  ?? '');
        $is_active       = isset($_POST['is_active']) ? 1 : 0;

        if (!$page_key || !$page_label) {
            setFlashMessage('error', 'Page key and label are required.');
            header("Location: hero-images.php"); exit;
        }

    
        $new_image = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $up = uploadFile($_FILES['image'], 'heroes');
            if (!empty($up['success'])) {
                $new_image = $up['filename'];
            } else {
                setFlashMessage('error', $up['message'] ?? 'Image upload failed.');
                header("Location: hero-images.php"); exit;
            }
        }

        if ($id > 0) {
         
            if ($new_image) {
                $st = $conn->prepare("SELECT image FROM hero_images WHERE id = ?");
                $st->bind_param("i", $id);
                $st->execute();
                $old = $st->get_result()->fetch_assoc();
                $st->close();
                if ($old && !empty($old['image'])) deleteFile($old['image'], 'heroes');
            }

            $sql    = "UPDATE hero_images SET page_key=?, page_label=?, alt_text=?, overlay_title=?, overlay_subtitle=?, is_active=?";
            $types  = "sssssi";
            $params = [$page_key, $page_label, $alt_text, $overlay_title, $overlay_subtitle, $is_active];

            if ($new_image) { $sql .= ", image=?"; $types .= "s"; $params[] = $new_image; }

            $sql .= " WHERE id=?"; $types .= "i"; $params[] = $id;

            $st = $conn->prepare($sql);
            $st->bind_param($types, ...$params);
            $st->execute();
            $st->close();

            setFlashMessage('success', "Hero image for <strong>" . htmlspecialchars($page_label) . "</strong> updated.");
        } else {
       
            $st = $conn->prepare("INSERT INTO hero_images (page_key, page_label, image, alt_text, overlay_title, overlay_subtitle, is_active) VALUES (?,?,?,?,?,?,?)");
            $st->bind_param("ssssssi", $page_key, $page_label, $new_image, $alt_text, $overlay_title, $overlay_subtitle, $is_active);
            $st->execute();
            $st->close();

            setFlashMessage('success', "Page <strong>" . htmlspecialchars($page_label) . "</strong> added.");
        }

        header("Location: hero-images.php"); exit;
    }

    /* Remove image from a page (keep the row) */
    if ($action === 'remove_image') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $st = $conn->prepare("SELECT image FROM hero_images WHERE id = ?");
            $st->bind_param("i", $id);
            $st->execute();
            $old = $st->get_result()->fetch_assoc();
            $st->close();

            if ($old && !empty($old['image'])) deleteFile($old['image'], 'heroes');

            $st = $conn->prepare("UPDATE hero_images SET image='' WHERE id=?");
            $st->bind_param("i", $id);
            $st->execute();
            $st->close();

            setFlashMessage('success', 'Hero image removed.');
        }
        header("Location: hero-images.php"); exit;
    }

    /* Toggle active */
    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $conn->query("UPDATE hero_images SET is_active = 1 - is_active WHERE id = $id");
        }
        header("Location: hero-images.php"); exit;
    }

    /* Delete entire row  */
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $st = $conn->prepare("SELECT image FROM hero_images WHERE id = ?");
            $st->bind_param("i", $id);
            $st->execute();
            $old = $st->get_result()->fetch_assoc();
            $st->close();
            if ($old && !empty($old['image'])) deleteFile($old['image'], 'heroes');

            $st = $conn->prepare("DELETE FROM hero_images WHERE id = ?");
            $st->bind_param("i", $id);
            $st->execute();
            $st->close();

            setFlashMessage('success', 'Page entry deleted.');
        }
        header("Location: hero-images.php"); exit;
    }
}

/*  Fetch all rows  */
$pages = [];
$res   = $conn->query("SELECT * FROM hero_images ORDER BY page_label ASC");
if ($res) while ($r = $res->fetch_assoc()) $pages[] = $r;

include 'layout/header.php';
include 'layout/sidebar.php';
?>

<style>
/* Grid of page cards  */
.hi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
    margin-top: 4px;
}

.hi-card {
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    background: #fff;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(0,0,0,.04);
    display: flex;
    flex-direction: column;
    transition: box-shadow .22s;
}
.hi-card:hover { box-shadow: 0 8px 28px rgba(0,0,0,.09); }

/* Image preview */
.hi-card-img {
    position: relative;
    aspect-ratio: 16/7;
    background: #1a1a1a;
    overflow: hidden;
    flex-shrink: 0;
}
.hi-card-img img {
    width: 100%; height: 100%;
    object-fit: cover; display: block;
    transition: transform .4s;
}
.hi-card:hover .hi-card-img img { transform: scale(1.03); }

/* Placeholder when no image */
.hi-card-placeholder {
    width: 100%; height: 100%;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center; gap: 8px;
    color: rgba(255,255,255,.25);
}
.hi-card-placeholder i { font-size: 36px; }
.hi-card-placeholder span { font-size: 12px; letter-spacing: .08em; text-transform: uppercase; }

/* Active / inactive ribbon */
.hi-card-ribbon {
    position: absolute; top: 10px; left: 10px;
    padding: 3px 10px; border-radius: 999px;
    font-size: 10px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase;
}
.hi-card-ribbon.active   { background: rgba(16,185,129,.9);  color:#fff; }
.hi-card-ribbon.inactive { background: rgba(239,68,68,.85);  color:#fff; }

/* Remove image button overlay */
.hi-remove-btn {
    position: absolute; top: 10px; right: 10px;
    width: 30px; height: 30px; border-radius: 50%;
    background: rgba(0,0,0,.55); border: none;
    color: #fff; cursor: pointer; font-size: 12px;
    display: flex; align-items: center; justify-content: center;
    opacity: 0; transition: opacity .2s;
}
.hi-card:hover .hi-remove-btn { opacity: 1; }
.hi-remove-btn:hover { background: rgba(239,68,68,.8); }

/* Body */
.hi-card-body {
    padding: 16px 18px 18px;
    flex: 1; display: flex; flex-direction: column; gap: 12px;
}
.hi-card-meta {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
}
.hi-page-label {
    font-size: 15px; font-weight: 900; color: #111827;
}
.hi-page-key {
    font-size: 11px; color: #9ca3af;
    background: #f3f4f6; padding: 2px 8px; border-radius: 4px;
    font-family: monospace;
}
.hi-card-actions {
    display: flex; gap: 8px; flex-wrap: wrap;
}

/* Modal form */
.hi-modal .modal-content { max-width: 640px; }

.form-section-label {
    font-size: 10px; font-weight: 700; letter-spacing: .18em;
    text-transform: uppercase; color: #6b7280;
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 6px; margin: 18px 0 12px;
}
</style>

<div class="content-header">
    <h2><i class="fas fa-image" style="color:#f86505;"></i> Hero Images</h2>
    <button class="btn btn-primary" onclick="openAddModal()">
        <i class="fas fa-plus"></i> Add Page
    </button>
</div>

<?php $flash = getFlashMessage(); if ($flash): ?>
<div class="alert alert-<?php echo $flash['type']; ?>">
    <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
    <?php echo $flash['message']; ?>
</div>
<?php endif; ?>

<p style="color:#6b7280;font-size:14px;margin-bottom:20px;">
    Each card represents one page on your site. Upload a hero image and it will automatically appear on that page.
  
</p>

<?php if (empty($pages)): ?>
    <div style="text-align:center;padding:60px;color:#6b7280;">
        <i class="fas fa-images" style="font-size:48px;color:#d1d5db;display:block;margin-bottom:16px;"></i>
        <strong>No pages yet.</strong><br>
        <small>Run the SQL migration first, or click "Add Page" to create entries manually.</small>
    </div>
<?php else: ?>
    <div class="hi-grid">
        <?php foreach ($pages as $p):
            $hasImage = !empty($p['image']);
            $imgSrc   = $hasImage ? '../uploads/heroes/' . htmlspecialchars($p['image']) : '';
            $isActive = (int)$p['is_active'] === 1;
        ?>
        <div class="hi-card">

            <!-- Image / placeholder -->
            <div class="hi-card-img">
                <?php if ($hasImage): ?>
                    <img src="<?php echo $imgSrc; ?>"
                         alt="<?php echo htmlspecialchars($p['alt_text'] ?: $p['page_label']); ?>">
                <?php else: ?>
                    <div class="hi-card-placeholder">
                        <i class="fas fa-image"></i>
                        <span>No image set</span>
                    </div>
                <?php endif; ?>

                <!-- Status ribbon -->
                <span class="hi-card-ribbon <?php echo $isActive ? 'active' : 'inactive'; ?>">
                    <?php echo $isActive ? 'Active' : 'Inactive'; ?>
                </span>

                <!-- Remove image button (only when image set) -->
                <?php if ($hasImage): ?>
                <form method="POST" style="display:contents;"
                      onsubmit="return confirm('Remove the hero image for <?php echo addslashes(htmlspecialchars($p['page_label'])); ?>?')">
                    <input type="hidden" name="action" value="remove_image">
                    <input type="hidden" name="id"     value="<?php echo (int)$p['id']; ?>">
                    <button type="submit" class="hi-remove-btn" title="Remove image">
                        <i class="fas fa-times"></i>
                    </button>
                </form>
                <?php endif; ?>
            </div>

            <!-- Body -->
            <div class="hi-card-body">
                <div class="hi-card-meta">
                    <span class="hi-page-label"><?php echo htmlspecialchars($p['page_label']); ?></span>
                    <code class="hi-page-key"><?php echo htmlspecialchars($p['page_key']); ?></code>
                </div>

                <?php if (!empty($p['overlay_title'])): ?>
                    <small style="color:#6b7280;">
                        <i class="fas fa-heading" style="color:#f86505;font-size:10px;"></i>
                        <?php echo htmlspecialchars($p['overlay_title']); ?>
                    </small>
                <?php endif; ?>

                <div class="hi-card-actions">
                    <button onclick='openEditModal(<?php echo json_encode($p); ?>)'
                            class="btn btn-sm btn-primary" style="flex:1;">
                        <i class="fas fa-upload"></i> Upload / Edit
                    </button>

                    <!-- Toggle active -->
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id"     value="<?php echo (int)$p['id']; ?>">
                        <button type="submit" class="btn btn-sm <?php echo $isActive ? 'btn-warning' : 'btn-success'; ?>"
                                title="<?php echo $isActive ? 'Deactivate' : 'Activate'; ?>">
                            <i class="fas fa-<?php echo $isActive ? 'eye-slash' : 'eye'; ?>"></i>
                        </button>
                    </form>

                    <!-- Delete row -->
                    <form method="POST" style="display:inline;"
                          onsubmit="return confirm('Delete this page entry entirely?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id"     value="<?php echo (int)$p['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger" title="Delete entry">
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
<div id="hiModal" class="modal hi-modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>
                <i class="fas fa-image"></i>
                <span id="hiModalTitle">Upload Hero Image</span>
            </h3>
            <button class="modal-close" onclick="closeHiModal()">&times;</button>
        </div>

        <form method="POST" enctype="multipart/form-data" id="hiForm">
            <div class="modal-body">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id"     id="hiId"  value="0">

                <!-- Page info -->
                <p class="form-section-label">Page</p>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label>Page Label *</label>
                        <input type="text" name="page_label" id="hiLabel" required
                               placeholder="e.g., About Us">
                        <small>Display name shown in admin</small>
                    </div>
                    <div class="form-group col-6">
                        <label>Page Key *</label>
                        <input type="text" name="page_key" id="hiKey" required
                               placeholder="e.g., about"
                               pattern="[a-z0-9\-]+"
                               title="Lowercase letters, numbers and hyphens only">
                        <small>Used in code: <code>getHeroImage($conn, '<span id="hiKeyPreview">key</span>')</code></small>
                    </div>
                </div>

                <!-- Image upload -->
                <p class="form-section-label">Hero Image</p>
                <div class="form-group">
                    <label>
                        <i class="fas fa-panorama" style="color:#f86505;"></i>
                        Upload Image
                    </label>
                    <input type="file" name="image" id="hiImage" accept="image/*"
                           onchange="previewHiImage(this)">
                    <small>Recommended: 1600x600 px or wider - JPG, PNG, WebP</small>
                </div>

                <!-- Live preview + current image -->
                <div id="hiPreviewWrap" style="display:none;margin-bottom:16px;">
                    <img id="hiPreviewImg" src=""
                         style="width:100%;height:160px;object-fit:cover;border-radius:10px;border:2px solid #e5e7eb;display:block;">
                    <small style="color:#9ca3af;margin-top:4px;display:block;">
                        Uploading a new file will replace the current image.
                    </small>
                </div>

                <!-- Alt text -->
                <div class="form-group">
                    <label>Alt Text</label>
                    <input type="text" name="alt_text" id="hiAlt"
                           placeholder="Describe the image for screen readers">
                    <small>Important for accessibility and SEO</small>
                </div>

                <!-- Optional overlay -->
                <p class="form-section-label">Optional Text Overlay (used if your hero template supports it)</p>
                <div class="form-group">
                    <label>Overlay Title</label>
                    <input type="text" name="overlay_title" id="hiOverlayTitle"
                           placeholder="e.g., Grow Your Skills">
                </div>
                <div class="form-group">
                    <label>Overlay Subtitle</label>
                    <textarea name="overlay_subtitle" id="hiOverlaySub" rows="2"
                              placeholder="e.g., Explore courses and programmes..."></textarea>
                </div>

                <!-- Active -->
                <div class="form-group" style="margin-top:6px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="is_active" id="hiActive" value="1" checked>
                        <span>Active (visible on frontend)</span>
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeHiModal()">
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
function openAddModal() {
    document.getElementById('hiModalTitle').textContent = 'Add Page';
    document.getElementById('hiForm').reset();
    document.getElementById('hiId').value = '0';
    document.getElementById('hiKey').readOnly = false;
    hideHiPreview();
    document.getElementById('hiModal').style.display = 'flex';
}

function openEditModal(page) {
    document.getElementById('hiModalTitle').textContent = 'Upload / Edit - ' + page.page_label;
    document.getElementById('hiId').value            = page.id;
    document.getElementById('hiLabel').value         = page.page_label   || '';
    document.getElementById('hiKey').value           = page.page_key     || '';
    document.getElementById('hiKey').readOnly        = true; // prevent accidental key change
    document.getElementById('hiAlt').value           = page.alt_text     || '';
    document.getElementById('hiOverlayTitle').value  = page.overlay_title || '';
    document.getElementById('hiOverlaySub').value    = page.overlay_subtitle || '';
    document.getElementById('hiActive').checked      = page.is_active == 1;

    if (page.image) {
        document.getElementById('hiPreviewImg').src  = '../uploads/heroes/' + page.image;
        document.getElementById('hiPreviewWrap').style.display = 'block';
    } else {
        hideHiPreview();
    }

    document.getElementById('hiKeyPreview').textContent = page.page_key || 'key';
    document.getElementById('hiModal').style.display = 'flex';
}

function closeHiModal() {
    document.getElementById('hiModal').style.display = 'none';
    document.getElementById('hiForm').reset();
    hideHiPreview();
}

function hideHiPreview() {
    document.getElementById('hiPreviewWrap').style.display = 'none';
}

function previewHiImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('hiPreviewImg').src = e.target.result;
            document.getElementById('hiPreviewWrap').style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Update code snippet as page key is typed
document.getElementById('hiKey').addEventListener('input', function(){
    document.getElementById('hiKeyPreview').textContent = this.value || 'key';
});

// Close on outside click / Escape
window.onclick = function(e) { if (e.target.id === 'hiModal') closeHiModal(); };
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeHiModal(); });
</script>

<?php include 'layout/footer.php'; ?>