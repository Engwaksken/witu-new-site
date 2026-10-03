<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/includes/admin-enhancements.php';
adminTrackActivity($conn);

// Permission: manage_partners OR fallback manage_content
$has_permission = false;
if (function_exists('hasPermission')) {
    if (hasPermission($conn, 'manage_partners')) $has_permission = true;
    elseif (hasPermission($conn, 'manage_content')) $has_permission = true;
} else { $has_permission = true; }

if (!$has_permission) {
    die('<h2>Access Denied</h2><p>You don\'t have permission to manage partners. Please contact your administrator to assign the "manage_partners" or "manage_content" permission.</p>');
}

$page_title = 'Manage Partners';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = intval($_POST['id'] ?? 0);

        $name = clean($conn, $_POST['name'] ?? '');
        $website = clean($conn, $_POST['website'] ?? '');
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $is_active  = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '') {
            setFlashMessage('error', 'Partner name is required.');
            header("Location: partners.php");
            exit;
        }

        $new_logo = '';
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
            $upload = uploadFile($_FILES['logo'], 'partners');
            if (!empty($upload['success'])) {
                $new_logo = $upload['filename'];
            } else {
                setFlashMessage('error', $upload['message'] ?? 'Failed to upload logo.');
                header("Location: partners.php");
                exit;
            }
        }

        if ($id > 0) {
            if ($new_logo) {
                $st = $conn->prepare("SELECT logo FROM partners WHERE id=?");
                $st->bind_param("i", $id);
                $st->execute();
                $old = $st->get_result()->fetch_assoc();
                $st->close();

                if ($old && !empty($old['logo'])) {
                    deleteFile($old['logo'], 'partners');
                }
            }

            $sql = "UPDATE partners SET name=?, website=?, sort_order=?, is_active=?";
            $types = "ssii";
            $params = [$name, $website, $sort_order, $is_active];

            if ($new_logo) {
                $sql .= ", logo=?";
                $types .= "s";
                $params[] = $new_logo;
            }

            $sql .= " WHERE id=?";
            $types .= "i";
            $params[] = $id;

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();

            setFlashMessage('success', 'Partner updated successfully.');
        } else {
            $stmt = $conn->prepare("INSERT INTO partners (name, logo, website, sort_order, is_active) VALUES (?,?,?,?,?)");
            $stmt->bind_param("sssii", $name, $new_logo, $website, $sort_order, $is_active);
            $stmt->execute();
            setFlashMessage('success', 'Partner created successfully.');
        }

        header("Location: partners.php");
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);

        $st = $conn->prepare("SELECT logo FROM partners WHERE id=?");
        $st->bind_param("i", $id);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();

        if ($row && !empty($row['logo'])) {
            deleteFile($row['logo'], 'partners');
        }

        $stmt = $conn->prepare("DELETE FROM partners WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        setFlashMessage('success', 'Partner deleted successfully.');
        header("Location: partners.php");
        exit;
    }
}

// list
$items = [];
$res = $conn->query("SELECT * FROM partners ORDER BY sort_order ASC, id DESC");
if ($res) while ($r = $res->fetch_assoc()) $items[] = $r;

include 'layout/header.php';
include 'layout/sidebar.php';
?>

<style>
.p-preview{display:flex;align-items:center;gap:12px;}
.p-thumb{width:90px;height:48px;border-radius:10px;object-fit:contain;border:2px solid #e5e7eb;background:#fff;padding:6px;}
.badge-active{background:#dcfce7;color:#166534;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700;}
.badge-inactive{background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700;}
</style>

<div class="content-header">
    <h2><i class="fas fa-handshake" style="color:#f86505;"></i> Manage Partners</h2>
    <button class="btn btn-primary" onclick="openCreateModal()">
        <i class="fas fa-plus"></i> Add Partner
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
          <th>Partner</th>
          <th width="110">Status</th>
          <th width="200">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($items)): ?>
        <tr>
          <td colspan="4" style="text-align:center;padding:40px;color:#6b7280;">
            <i class="fas fa-inbox" style="font-size:48px;color:#d1d5db;margin-bottom:15px;display:block;"></i>
            <strong>No partners found</strong><br><small>Add partner logos for the homepage.</small>
          </td>
        </tr>
      <?php else: foreach ($items as $p): ?>
        <tr>
          <td style="text-align:center;font-weight:700;color:#6b7280;"><?php echo (int)$p['sort_order']; ?></td>
          <td>
            <div class="p-preview">
              <?php if (!empty($p['logo'])): ?>
                <img class="p-thumb" src="../uploads/partners/<?php echo htmlspecialchars($p['logo']); ?>" alt="">
              <?php else: ?>
                <div class="p-thumb" style="display:flex;align-items:center;justify-content:center;color:#9ca3af;">No Logo</div>
              <?php endif; ?>
              <div>
                <strong><?php echo htmlspecialchars($p['name']); ?></strong><br>
                <small style="color:#6b7280;"><?php echo htmlspecialchars($p['website'] ?? ''); ?></small>
              </div>
            </div>
          </td>
          <td>
            <?php echo !empty($p['is_active'])
              ? '<span class="badge-active">ACTIVE</span>'
              : '<span class="badge-inactive">INACTIVE</span>'; ?>
          </td>
          <td>
            <div style="display:flex;gap:6px;">
              <button type="button" class="btn btn-sm btn-primary" onclick='editItem(<?php echo json_encode($p); ?>)'>
                <i class="fas fa-edit"></i>
              </button>
              <button type="button" class="btn btn-sm btn-danger" onclick="deleteItem(<?php echo (int)$p['id']; ?>, <?php echo json_encode($p['name']); ?>)">
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

<!-- Modal -->
<div id="partnerModal" class="modal" style="display:none;">
  <div class="modal-content" style="max-width:900px;max-height:90vh;overflow-y:auto;">
    <div class="modal-header">
      <h3><i class="fas fa-handshake"></i> <span id="modalTitle">Add Partner</span></h3>
      <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
    </div>

    <form method="POST" enctype="multipart/form-data" id="partnerForm">
      <div class="modal-body">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="partnerId" value="0">

        <div class="form-row">
          <div class="form-group col-8">
            <label>Partner Name *</label>
            <input type="text" name="name" id="partnerName" required>
          </div>
          <div class="form-group col-4">
            <label>Sort Order</label>
            <input type="number" name="sort_order" id="partnerSortOrder" value="0" min="0">
          </div>
        </div>

        <div class="form-group">
          <label>Website (optional)</label>
          <input type="text" name="website" id="partnerWebsite" placeholder="https://partner.org">
        </div>

        <div class="form-group">
          <label>Logo</label>
          <input type="file" name="logo" id="partnerLogo" accept="image/*">
          <small>Uploads to: <strong>uploads/partners/</strong></small>
        </div>

        <div id="currentLogoPreview" style="display:none;margin-bottom:15px;">
          <label>Current Logo:</label>
          <div style="margin-top:8px;">
            <img id="currentLogo"
                 src=""
                 alt="Current logo"
                 style="max-width:220px;border-radius:10px;border:2px solid #e5e7eb;background:#fff;padding:8px;">
          </div>
        </div>

        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="is_active" id="partnerActive" value="1" checked>
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
  document.getElementById('modalTitle').textContent = 'Add Partner';
  const form = document.getElementById('partnerForm');
  form.reset();
  document.getElementById('partnerId').value = '0';
  document.getElementById('partnerSortOrder').value = '0';
  document.getElementById('partnerActive').checked = true;
  document.getElementById('currentLogoPreview').style.display = 'none';
  document.getElementById('currentLogo').src = '';
  document.getElementById('partnerModal').style.display = 'flex';
}

function editItem(p){
  document.getElementById('modalTitle').textContent = 'Edit Partner';
  document.getElementById('partnerId').value = p.id;
  document.getElementById('partnerName').value = p.name || '';
  document.getElementById('partnerWebsite').value = p.website || '';
  document.getElementById('partnerSortOrder').value = p.sort_order || 0;
  document.getElementById('partnerActive').checked = (parseInt(p.is_active) === 1);

  if (p.logo) {
    document.getElementById('currentLogo').src = '../uploads/partners/' + p.logo;
    document.getElementById('currentLogoPreview').style.display = 'block';
  } else {
    document.getElementById('currentLogoPreview').style.display = 'none';
    document.getElementById('currentLogo').src = '';
  }

  // Clear file input
  document.getElementById('partnerLogo').value = '';
  document.getElementById('partnerModal').style.display = 'flex';
}

function closeModal(){
  document.getElementById('partnerModal').style.display = 'none';
  document.getElementById('partnerForm').reset();
}

function deleteItem(id, name){
  if (confirm(`Delete partner "${name}"?\n\nThis will also delete the logo file.`)) {
    const f = document.createElement('form');
    f.method = 'POST';
    f.innerHTML = `<input type="hidden" name="action" value="delete">
                   <input type="hidden" name="id" value="${id}">`;
    document.body.appendChild(f);
    f.submit();
  }
}

// Close modal when clicking outside
window.addEventListener('click', function(e){
  const modal = document.getElementById('partnerModal');
  if (e.target === modal) closeModal();
});

// Close on Escape
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape') closeModal();
});
</script>

<?php include 'layout/footer.php'; ?>
