<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/includes/admin-enhancements.php';
adminTrackActivity($conn);

$has_permission = false;
if (function_exists('hasPermission')) {
    if (hasPermission($conn, 'manage_testimonials')) $has_permission = true;
    elseif (hasPermission($conn, 'manage_content')) $has_permission = true;
} else { $has_permission = true; }

if (!$has_permission) {
    die('<h2>Access Denied</h2><p>You don\'t have permission to manage testimonials. Please contact your administrator to assign the "manage_testimonials" or "manage_content" permission.</p>');
}

$page_title = 'Manage Testimonials';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = intval($_POST['id'] ?? 0);

        $full_name = clean($conn, $_POST['full_name'] ?? '');
        $headline  = clean($conn, $_POST['headline'] ?? '');
        $quote     = trim($_POST['quote'] ?? '');
        $program   = clean($conn, $_POST['program'] ?? '');
        $location  = clean($conn, $_POST['location'] ?? '');
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $is_active  = isset($_POST['is_active']) ? 1 : 0;

        if ($full_name === '' || $quote === '') {
            setFlashMessage('error', 'Full name and quote are required.');
            header("Location: testimonials.php");
            exit;
        }

        $new_photo = '';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
            $upload = uploadFile($_FILES['photo'], 'testimonials');
            if (!empty($upload['success'])) {
                $new_photo = $upload['filename'];
            } else {
                setFlashMessage('error', $upload['message'] ?? 'Failed to upload photo.');
                header("Location: testimonials.php");
                exit;
            }
        }

        if ($id > 0) {
            // delete old photo if new one is uploaded
            if ($new_photo) {
                $st = $conn->prepare("SELECT photo FROM testimonials WHERE id=?");
                $st->bind_param("i", $id);
                $st->execute();
                $old = $st->get_result()->fetch_assoc();
                $st->close();

                if ($old && !empty($old['photo'])) {
                    deleteFile($old['photo'], 'testimonials');
                }
            }

            $sql = "UPDATE testimonials SET full_name=?, headline=?, quote=?, program=?, location=?, sort_order=?, is_active=?";
            $types = "sssssii";
            $params = [$full_name, $headline, $quote, $program, $location, $sort_order, $is_active];

            if ($new_photo) {
                $sql .= ", photo=?";
                $types .= "s";
                $params[] = $new_photo;
            }

            $sql .= " WHERE id=?";
            $types .= "i";
            $params[] = $id;

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();

            setFlashMessage('success', 'Testimonial updated successfully.');
        } else {
            $stmt = $conn->prepare("INSERT INTO testimonials (photo, full_name, headline, quote, program, location, sort_order, is_active) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param("ssssssii", $new_photo, $full_name, $headline, $quote, $program, $location, $sort_order, $is_active);
            $stmt->execute();
            setFlashMessage('success', 'Testimonial created successfully.');
        }

        header("Location: testimonials.php");
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);

        $st = $conn->prepare("SELECT photo FROM testimonials WHERE id=?");
        $st->bind_param("i", $id);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();

        if ($row && !empty($row['photo'])) {
            deleteFile($row['photo'], 'testimonials');
        }

        $stmt = $conn->prepare("DELETE FROM testimonials WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        setFlashMessage('success', 'Testimonial deleted successfully.');
        header("Location: testimonials.php");
        exit;
    }
}

// list
$items = [];
$res = $conn->query("SELECT * FROM testimonials ORDER BY sort_order ASC, id DESC");
if ($res) while ($r = $res->fetch_assoc()) $items[] = $r;

include 'layout/header.php';
include 'layout/sidebar.php';
?>

<style>
.t-preview{display:flex;align-items:center;gap:12px;}
.t-thumb{width:46px;height:46px;border-radius:999px;object-fit:cover;border:2px solid #e5e7eb;background:#f3f4f6;}
.badge-active{background:#dcfce7;color:#166534;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700;}
.badge-inactive{background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700;}
</style>

<div class="content-header">
    <h2><i class="fas fa-comment-dots" style="color:#f86505;"></i> Manage Testimonials</h2>
    <button class="btn btn-primary" onclick="openCreateModal()">
        <i class="fas fa-plus"></i> Add Testimonial
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
          <th>Testimonial</th>
          <th width="110">Status</th>
          <th width="200">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($items)): ?>
        <tr>
          <td colspan="4" style="text-align:center;padding:40px;color:#6b7280;">
            <i class="fas fa-inbox" style="font-size:48px;color:#d1d5db;margin-bottom:15px;display:block;"></i>
            <strong>No testimonials found</strong><br><small>Add participant stories for the homepage.</small>
          </td>
        </tr>
      <?php else: foreach ($items as $t): ?>
        <tr>
          <td style="text-align:center;font-weight:700;color:#6b7280;"><?php echo (int)$t['sort_order']; ?></td>
          <td>
            <div class="t-preview">
              <?php if (!empty($t['photo'])): ?>
                <img class="t-thumb" src="../uploads/testimonials/<?php echo htmlspecialchars($t['photo']); ?>" alt="">
              <?php else: ?>
                <div class="t-thumb"></div>
              <?php endif; ?>
              <div>
                <strong><?php echo htmlspecialchars($t['full_name']); ?></strong>
                <?php if (!empty($t['headline'])): ?>
                  <div><small style="color:#6b7280;"><?php echo htmlspecialchars($t['headline']); ?></small></div>
                <?php endif; ?>
                <small style="color:#6b7280;">
                  <?php echo htmlspecialchars(($t['program'] ?? '').(!empty($t['program']) && !empty($t['location']) ? ' - ' : '').($t['location'] ?? '')); ?>
                </small>
              </div>
            </div>
            <div style="margin-top:6px;color:#374151;">
              <small><?php echo htmlspecialchars(mb_strimwidth(strip_tags($t['quote']), 0, 150, '...')); ?></small>
            </div>
          </td>
          <td><?php echo !empty($t['is_active']) ? '<span class="badge-active">ACTIVE</span>' : '<span class="badge-inactive">INACTIVE</span>'; ?></td>
          <td>
            <div style="display:flex;gap:6px;">
              <button type="button" class="btn btn-sm btn-primary" onclick='editItem(<?php echo json_encode($t); ?>)'><i class="fas fa-edit"></i></button>
              <button type="button" class="btn btn-sm btn-danger" onclick="deleteItem(<?php echo (int)$t['id']; ?>, <?php echo json_encode($t['full_name']); ?>)"><i class="fas fa-trash-alt"></i></button>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal -->
<div id="testimonialModal" class="modal" style="display:none;">
  <div class="modal-content" style="max-width:900px;max-height:90vh;overflow-y:auto;">
    <div class="modal-header">
      <h3><i class="fas fa-comment-dots"></i> <span id="modalTitle">Add Testimonial</span></h3>
      <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
    </div>

    <form method="POST" enctype="multipart/form-data" id="testimonialForm">
      <div class="modal-body">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="testimonialId" value="0">

        <div class="form-row">
          <div class="form-group col-8">
            <label>Full Name *</label>
            <input type="text" name="full_name" id="tFullName" required>
          </div>
          <div class="form-group col-4">
            <label>Sort Order</label>
            <input type="number" name="sort_order" id="tSortOrder" value="0" min="0">
          </div>
        </div>

        <div class="form-group">
          <label>Short Headline (optional)</label>
          <input type="text" name="headline" id="tHeadline" placeholder="e.g. I got my first tech job!">
        </div>

        <div class="form-group">
          <label>Quote *</label>
          <textarea name="quote" id="tQuote" rows="5" required></textarea>
        </div>

        <div class="form-row">
          <div class="form-group col-6">
            <label>Program Attended</label>
            <input type="text" name="program" id="tProgram">
          </div>
          <div class="form-group col-6">
            <label>Location</label>
            <input type="text" name="location" id="tLocation">
          </div>
        </div>

        <div class="form-group">
          <label>Photo</label>
          <input type="file" name="photo" id="tPhoto" accept="image/*">
          <small>Uploads to: <strong>uploads/testimonials/</strong></small>
        </div>

        <div id="currentPhotoPreview" style="display:none;margin-bottom:15px;">
          <label>Current Photo:</label>
          <div style="margin-top:8px;">
            <img id="currentPhoto"
                 src=""
                 alt="Current photo"
                 style="max-width:160px;border-radius:999px;border:2px solid #e5e7eb;background:#f3f4f6;">
          </div>
        </div>

        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="is_active" id="tActive" value="1" checked>
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
  document.getElementById('modalTitle').textContent = 'Add Testimonial';
  const form = document.getElementById('testimonialForm');
  form.reset();
  document.getElementById('testimonialId').value = '0';
  document.getElementById('tSortOrder').value = '0';
  document.getElementById('tActive').checked = true;

  document.getElementById('currentPhotoPreview').style.display = 'none';
  document.getElementById('currentPhoto').src = '';
  document.getElementById('tPhoto').value = '';

  document.getElementById('testimonialModal').style.display = 'flex';
}

function editItem(t){
  document.getElementById('modalTitle').textContent = 'Edit Testimonial';
  document.getElementById('testimonialId').value = t.id;

  document.getElementById('tFullName').value = t.full_name || '';
  document.getElementById('tHeadline').value = t.headline || '';
  document.getElementById('tQuote').value = t.quote || '';
  document.getElementById('tProgram').value = t.program || '';
  document.getElementById('tLocation').value = t.location || '';
  document.getElementById('tSortOrder').value = t.sort_order || 0;
  document.getElementById('tActive').checked = (parseInt(t.is_active) === 1);

  if (t.photo) {
    document.getElementById('currentPhoto').src = '../uploads/testimonials/' + t.photo;
    document.getElementById('currentPhotoPreview').style.display = 'block';
  } else {
    document.getElementById('currentPhotoPreview').style.display = 'none';
    document.getElementById('currentPhoto').src = '';
  }

  document.getElementById('tPhoto').value = '';
  document.getElementById('testimonialModal').style.display = 'flex';
}

function closeModal(){
  document.getElementById('testimonialModal').style.display = 'none';
  document.getElementById('testimonialForm').reset();
}

function deleteItem(id, name){
  if (confirm(`Delete testimonial for "${name}"?\n\nThis will also delete the photo.`)) {
    const f = document.createElement('form');
    f.method = 'POST';
    f.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="${id}">`;
    document.body.appendChild(f);
    f.submit();
  }
}

// Close modal when clicking outside
window.addEventListener('click', function(e){
  const modal = document.getElementById('testimonialModal');
  if (e.target === modal) closeModal();
});

// Close on Escape
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape') closeModal();
});
</script>

<?php include 'layout/footer.php'; ?>
