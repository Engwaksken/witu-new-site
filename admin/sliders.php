<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/includes/admin-enhancements.php';
adminTrackActivity($conn);

// Permission: manage_sliders OR fallback manage_content
$has_permission = false;
if (function_exists('hasPermission')) {
    if (hasPermission($conn, 'manage_sliders')) $has_permission = true;
    elseif (hasPermission($conn, 'manage_content')) $has_permission = true;
} else { $has_permission = true; }

if (!$has_permission) {
    die('<h2>Access Denied</h2><p>You don\'t have permission to manage sliders. Please contact your administrator to assign the "manage_sliders" or "manage_content" permission.</p>');
}

$page_title = 'Manage Hero Sliders';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = intval($_POST['id'] ?? 0);
        $title = clean($conn, $_POST['title'] ?? '');
        $headline = clean($conn, $_POST['headline'] ?? '');
        $subtext = clean($conn, $_POST['subtext'] ?? '');
        $cta_text = clean($conn, $_POST['cta_text'] ?? '');
        $cta_link = clean($conn, $_POST['cta_link'] ?? '');
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($headline === '') {
            setFlashMessage('error', 'Headline is required.');
            header("Location: sliders.php");
            exit;
        }

        $new_image = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $upload = uploadFile($_FILES['image'], 'sliders');
            if ($upload['success']) {
                $new_image = $upload['filename']; // stored filename
            } else {
                setFlashMessage('error', $upload['message'] ?? 'Failed to upload image.');
                header("Location: sliders.php");
                exit;
            }
        }

        if ($id > 0) {
            // Update
            $sql = "UPDATE sliders SET title=?, headline=?, subtext=?, cta_text=?, cta_link=?, sort_order=?, is_active=?";
            $types = "sssssi i";
            // build safely without weird type string spaces
            $params = [$title, $headline, $subtext, $cta_text, $cta_link, $sort_order, $is_active];
            $bindTypes = "sssssii";

            if ($new_image) {
                // delete old
                $st = $conn->prepare("SELECT image_path FROM sliders WHERE id=?");
                $st->bind_param("i", $id);
                $st->execute();
                $old = $st->get_result()->fetch_assoc();
                $st->close();

                if ($old && $old['image_path']) {
                    // existing stored as 'uploads/sliders/filename' OR maybe just filename
                    $oldFile = basename($old['image_path']);
                    deleteFile($oldFile, 'sliders');
                }

                $sql .= ", image_path=?";
                $bindTypes .= "s";
                $params[] = 'uploads/sliders/' . $new_image;
            }

            $sql .= " WHERE id=?";
            $bindTypes .= "i";
            $params[] = $id;

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($bindTypes, ...$params);
            $stmt->execute();

            setFlashMessage('success', 'Slider updated successfully.');
        } else {
            $image_path = $new_image ? ('uploads/sliders/' . $new_image) : null;

            $stmt = $conn->prepare("INSERT INTO sliders (title, image_path, headline, subtext, cta_text, cta_link, sort_order, is_active) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param("ssssssii", $title, $image_path, $headline, $subtext, $cta_text, $cta_link, $sort_order, $is_active);
            $stmt->execute();

            setFlashMessage('success', 'Slider created successfully.');
        }

        header("Location: sliders.php");
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);

        // get image
        $stmt = $conn->prepare("SELECT image_path FROM sliders WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $s = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($s && !empty($s['image_path'])) {
            $oldFile = basename($s['image_path']);
            deleteFile($oldFile, 'sliders');
        }

        $stmt = $conn->prepare("DELETE FROM sliders WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        setFlashMessage('success', 'Slider deleted successfully.');
        header("Location: sliders.php");
        exit;
    }
}

// List
$sliders = [];
$res = $conn->query("SELECT * FROM sliders ORDER BY sort_order ASC, id DESC");
if ($res) while ($row = $res->fetch_assoc()) $sliders[] = $row;

include 'layout/header.php';
include 'layout/sidebar.php';
?>

<style>
.slide-preview{display:flex;align-items:center;gap:12px;}
.slide-thumb{width:80px;height:48px;object-fit:cover;border-radius:8px;border:2px solid #e5e7eb;background:#f3f4f6;}
.slide-placeholder{width:80px;height:48px;border-radius:8px;border:2px dashed #d1d5db;display:flex;align-items:center;justify-content:center;color:#9ca3af;}
.badge-active{background:#dcfce7;color:#166534;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700;}
.badge-inactive{background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700;}
</style>

<div class="content-header">
    <h2><i class="fas fa-images" style="color:#f86505;"></i> Manage Hero Sliders</h2>
    <button class="btn btn-primary" onclick="openCreateModal()">
        <i class="fas fa-plus"></i> Add Slider
    </button>
</div>

<?php $flash = getFlashMessage(); if ($flash): ?>
<div class="alert alert-<?php echo $flash['type']; ?>">
    <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
    <?php echo $flash['message']; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th width="70">Order</th>
                    <th>Slide</th>
                    <th width="110">Status</th>
                    <th width="220">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($sliders)): ?>
                <tr>
                    <td colspan="4" style="text-align:center;padding:40px;color:#6b7280;">
                        <i class="fas fa-inbox" style="font-size:48px;color:#d1d5db;margin-bottom:15px;display:block;"></i>
                        <strong>No sliders found</strong><br><small>Add your first hero slide.</small>
                    </td>
                </tr>
            <?php else: foreach ($sliders as $s): ?>
                <tr>
                    <td style="text-align:center;font-weight:700;color:#6b7280;"><?php echo (int)$s['sort_order']; ?></td>
                    <td>
                        <div class="slide-preview">
                            <?php if (!empty($s['image_path'])): ?>
                                <img class="slide-thumb" src="../<?php echo htmlspecialchars($s['image_path']); ?>" alt="">
                            <?php else: ?>
                                <div class="slide-placeholder"><i class="fas fa-image"></i></div>
                            <?php endif; ?>

                            <div>
                                <strong><?php echo htmlspecialchars($s['headline']); ?></strong><br>
                                <small style="color:#6b7280;"><?php echo htmlspecialchars($s['cta_text'] ?? ''); ?> | <?php echo htmlspecialchars($s['cta_link'] ?? ''); ?></small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if (!empty($s['is_active'])): ?>
                            <span class="badge-active">ACTIVE</span>
                        <?php else: ?>
                            <span class="badge-inactive">INACTIVE</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <button class="btn btn-sm btn-primary" onclick='editSlider(<?php echo json_encode($s); ?>)'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteSlider(<?php echo (int)$s['id']; ?>,'<?php echo addslashes($s['headline']); ?>')">
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

<!-- Slider Modal -->
<div id="sliderModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:900px;max-height:90vh;overflow-y:auto;">
        <div class="modal-header">
            <h3><i class="fas fa-images"></i> <span id="modalTitle">Add Slider</span></h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>

        <form method="POST" enctype="multipart/form-data" id="sliderForm">
            <div class="modal-body">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="sliderId" value="0">

                <div class="form-row">
                    <div class="form-group col-8">
                        <label>Internal Title (optional)</label>
                        <input type="text" name="title" id="sliderTitle" placeholder="e.g. Donate slide">
                    </div>
                    <div class="form-group col-4">
                        <label>Sort Order</label>
                        <input type="number" name="sort_order" id="sliderOrder" value="0" min="0">
                    </div>
                </div>

                <div class="form-group">
                    <label>Headline *</label>
                    <input type="text" name="headline" id="sliderHeadline" required>
                </div>

                <div class="form-group">
                    <label>Subtext</label>
                    <input type="text" name="subtext" id="sliderSubtext">
                </div>

                <div class="form-row">
                    <div class="form-group col-6">
                        <label>CTA Text</label>
                        <input type="text" name="cta_text" id="sliderCtaText">
                    </div>
                    <div class="form-group col-6">
                        <label>CTA Link</label>
                        <input type="text" name="cta_link" id="sliderCtaLink" placeholder="e.g. donate.php">
                    </div>
                </div>

                <div class="form-group">
                    <label>Slider Image</label>
                    <input type="file" name="image" id="sliderImage" accept="image/*">
                    <small>Uploads to: <strong>uploads/sliders/</strong> (JPG/PNG/WebP)</small>
                </div>

                <div id="currentImagePreview" style="display:none;margin-bottom:15px;">
                    <label>Current Image:</label>
                    <div style="margin-top:8px;">
                        <img id="currentImage" src="" style="max-width:320px;border-radius:8px;border:2px solid #e5e7eb;">
                    </div>
                </div>

                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="is_active" id="sliderActive" value="1" checked>
                        <span>Active</span>
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal()"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal(){
    document.getElementById('modalTitle').textContent = 'Add Slider';
    document.getElementById('sliderForm').reset();
    document.getElementById('sliderId').value = '0';
    document.getElementById('sliderOrder').value = '0';
    document.getElementById('sliderActive').checked = true;
    document.getElementById('currentImagePreview').style.display = 'none';
    document.getElementById('sliderModal').style.display = 'flex';
}
function editSlider(s){
    document.getElementById('modalTitle').textContent = 'Edit Slider';
    document.getElementById('sliderId').value = s.id;
    document.getElementById('sliderTitle').value = s.title || '';
    document.getElementById('sliderHeadline').value = s.headline || '';
    document.getElementById('sliderSubtext').value = s.subtext || '';
    document.getElementById('sliderCtaText').value = s.cta_text || '';
    document.getElementById('sliderCtaLink').value = s.cta_link || '';
    document.getElementById('sliderOrder').value = s.sort_order || 0;
    document.getElementById('sliderActive').checked = (parseInt(s.is_active) === 1);

    if (s.image_path) {
        document.getElementById('currentImage').src = '../' + s.image_path;
        document.getElementById('currentImagePreview').style.display = 'block';
    } else {
        document.getElementById('currentImagePreview').style.display = 'none';
    }
    document.getElementById('sliderModal').style.display = 'flex';
}
function closeModal(){
    document.getElementById('sliderModal').style.display = 'none';
    document.getElementById('sliderForm').reset();
}
function deleteSlider(id, headline){
    if (confirm(`Delete slider: "${headline}"?\n\nThis will remove it permanently (and delete its image).`)) {
        const f = document.createElement('form');
        f.method = 'POST';
        f.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="${id}">`;
        document.body.appendChild(f);
        f.submit();
    }
}
window.onclick = function(e){ if (e.target.id === 'sliderModal') closeModal(); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>

<?php include 'layout/footer.php'; ?>
