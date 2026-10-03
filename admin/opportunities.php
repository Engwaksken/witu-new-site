<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/includes/admin-enhancements.php';
adminTrackActivity($conn);

$has_permission = false;
if (function_exists('hasPermission')) {
    if (hasPermission($conn, 'manage_opportunities')) $has_permission = true;
    elseif (hasPermission($conn, 'manage_content')) $has_permission = true;
} else {
    $has_permission = true;
}

if (!$has_permission) {
    die('<h2>Access Denied</h2><p>You don\'t have permission to manage Opportunities. Please contact your administrator to assign the "manage_opportunities" or "manage_content" permission.</p>');
}

$page_title = 'Manage Opportunities';

/** Helpers */
function ensureOpportunitiesRow(mysqli $conn): array {
    $row = null;
    $res = $conn->query("SELECT * FROM opportunities ORDER BY id DESC LIMIT 1");
    if ($res) $row = $res->fetch_assoc();

    if (!$row) {
        $headline        = "If you're ready to live a life set on your own terms.\nWITU is ready to give you the tools.\nLet's make it happen.";
        $intro           = "";
        $framework_intro = "Your opportunity through WITU is supported by the following focus areas:";

        $stmt = $conn->prepare("INSERT INTO opportunities (headline, intro, framework_intro, is_active) VALUES (?,?,?,1)");
        $stmt->bind_param("sss", $headline, $intro, $framework_intro);
        $stmt->execute();
        $stmt->close();

        $res2 = $conn->query("SELECT * FROM opportunities ORDER BY id DESC LIMIT 1");
        if ($res2) $row = $res2->fetch_assoc();
    }

    return $row ?: [];
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/* --- AJAX: reorder ------------------------------------------- */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'reorder') {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        jsonResponse(['success' => false, 'message' => 'Invalid payload'], 400);
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE opportunity_sections SET sort_order=? WHERE id=?");
        if (!$stmt) throw new RuntimeException('Prepare failed');

        foreach ($data as $item) {
            $id   = (int)($item['id']         ?? 0);
            $sort = (int)($item['sort_order'] ?? 0);
            if ($id <= 0) continue;
            $stmt->bind_param("ii", $sort, $id);
            $stmt->execute();
        }
        $stmt->close();
        $conn->commit();
        jsonResponse(['success' => true]);
    } catch (Throwable $e) {
        $conn->rollback();
        jsonResponse(['success' => false, 'message' => 'Failed to update order'], 500);
    }
}

/* --- AJAX: toggle active ------------------------------------- */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jsonResponse(['success' => false, 'message' => 'Invalid id'], 400);

    $st = $conn->prepare("SELECT is_active FROM opportunity_sections WHERE id=? LIMIT 1");
    $st->bind_param("i", $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$row) jsonResponse(['success' => false, 'message' => 'Not found'], 404);

    $new = ((int)$row['is_active'] === 1) ? 0 : 1;

    $up = $conn->prepare("UPDATE opportunity_sections SET is_active=? WHERE id=?");
    $up->bind_param("ii", $new, $id);
    $up->execute();
    $up->close();

    jsonResponse(['success' => true, 'is_active' => $new]);
}

/* --- POST actions -------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['ajax'])) {
    $action = $_POST['action'] ?? '';

    /* -- Save main opportunity block -- */
    if ($action === 'save_main') {
        $id              = (int)($_POST['op_id']          ?? 0);
        $headline        = trim($_POST['headline']        ?? '');
        $intro           = trim($_POST['intro']           ?? '');
        $framework_intro = trim($_POST['framework_intro'] ?? '');
        $is_active       = isset($_POST['is_active']) ? 1 : 0;

        if ($headline === '') {
            setFlashMessage('error', 'Headline is required.');
            header("Location: opportunities.php");
            exit;
        }

        $new_featured = '';
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === 0) {
            $upload = uploadFile($_FILES['featured_image'], 'opportunities');
            if (!empty($upload['success'])) {
                $new_featured = $upload['filename'];
            } else {
                setFlashMessage('error', $upload['message'] ?? 'Failed to upload featured image.');
                header("Location: opportunities.php");
                exit;
            }
        }

        if ($id > 0) {
            if ($new_featured) {
                $st = $conn->prepare("SELECT featured_image FROM opportunities WHERE id=?");
                $st->bind_param("i", $id);
                $st->execute();
                $old = $st->get_result()->fetch_assoc();
                $st->close();
                if ($old && !empty($old['featured_image'])) {
                    deleteFile($old['featured_image'], 'opportunities');
                }
            }

            $sql    = "UPDATE opportunities SET headline=?, intro=?, framework_intro=?, is_active=?";
            $types  = "sssi";
            $params = [$headline, $intro, $framework_intro, $is_active];

            if ($new_featured) {
                $sql    .= ", featured_image=?";
                $types  .= "s";
                $params[] = $new_featured;
            }

            $sql    .= " WHERE id=?";
            $types  .= "i";
            $params[] = $id;

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO opportunities (headline, intro, framework_intro, featured_image, is_active) VALUES (?,?,?,?,?)");
            $stmt->bind_param("ssssi", $headline, $intro, $framework_intro, $new_featured, $is_active);
            $stmt->execute();
            $stmt->close();
        }

        setFlashMessage('success', 'Opportunities content saved.');
        header("Location: opportunities.php");
        exit;
    }

    /* -- Save / create section -- */
    if ($action === 'save_section') {
        $id         = (int)($_POST['id']         ?? 0);
        $title      = trim($_POST['title']       ?? '');
        $content    = trim($_POST['content']     ?? '');
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $is_active  = isset($_POST['is_active']) ? 1 : 0;

        if ($title === '' || $content === '') {
            setFlashMessage('error', 'Section title and content are required.');
            header("Location: opportunities.php");
            exit;
        }

        $new_image = '';
        if (isset($_FILES['section_image']) && $_FILES['section_image']['error'] === 0) {
            $upload = uploadFile($_FILES['section_image'], 'opportunities');
            if (!empty($upload['success'])) {
                $new_image = $upload['filename'];
            } else {
                setFlashMessage('error', $upload['message'] ?? 'Image upload failed.');
                header("Location: opportunities.php");
                exit;
            }
        }

        if ($id > 0) {
            // Delete old image if being replaced
            if ($new_image) {
                $st = $conn->prepare("SELECT section_image FROM opportunity_sections WHERE id=?");
                $st->bind_param("i", $id);
                $st->execute();
                $old = $st->get_result()->fetch_assoc();
                $st->close();
                if ($old && !empty($old['section_image'])) {
                    deleteFile($old['section_image'], 'opportunities');
                }
            }

            $sql    = "UPDATE opportunity_sections SET title=?, content=?, sort_order=?, is_active=?";
            $types  = "ssii";
            $params = [$title, $content, $sort_order, $is_active];

            if ($new_image) {
                $sql    .= ", section_image=?";
                $types  .= "s";
                $params[] = $new_image;
            }

            $sql    .= " WHERE id=?";
            $types  .= "i";
            $params[] = $id;

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $stmt->close();

            setFlashMessage('success', 'Section updated.');
        } else {
            if ($sort_order === 0) {
                $mx         = $conn->query("SELECT COALESCE(MAX(sort_order), -1) AS m FROM opportunity_sections")->fetch_assoc();
                $sort_order = ((int)($mx['m'] ?? -1)) + 1;
            }

            $stmt = $conn->prepare("INSERT INTO opportunity_sections (title, content, section_image, sort_order, is_active) VALUES (?,?,?,?,?)");
            $stmt->bind_param("sssii", $title, $content, $new_image, $sort_order, $is_active);
            $stmt->execute();
            $stmt->close();

            setFlashMessage('success', 'Section created.');
        }

        header("Location: opportunities.php");
        exit;
    }

    /* -- FIX: Delete section (was completely missing) -- */
    if ($action === 'delete_section') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            setFlashMessage('error', 'Invalid section ID.');
            header("Location: opportunities.php");
            exit;
        }

        // Fetch and delete associated image file first
        $st = $conn->prepare("SELECT section_image FROM opportunity_sections WHERE id=? LIMIT 1");
        $st->bind_param("i", $id);
        $st->execute();
        $old = $st->get_result()->fetch_assoc();
        $st->close();

        if ($old && !empty($old['section_image'])) {
            deleteFile($old['section_image'], 'opportunities');
        }

        $del = $conn->prepare("DELETE FROM opportunity_sections WHERE id=?");
        $del->bind_param("i", $id);
        $del->execute();
        $del->close();

        setFlashMessage('success', 'Section deleted.');
        header("Location: opportunities.php");
        exit;
    }
}

// Load main row + sections
$opportunity = ensureOpportunitiesRow($conn);

$sections = [];
$res = $conn->query("SELECT * FROM opportunity_sections ORDER BY sort_order ASC, id ASC");
if ($res) {
    while ($r = $res->fetch_assoc()) $sections[] = $r;
}

include 'layout/header.php';
include 'layout/sidebar.php';
?>

<style>
/* Page */
.op-wrap{max-width:1100px}
.op-split{display:grid;grid-template-columns:1fr;gap:18px}

/* cards */
.cardx{
  background:#fff;border:1px solid #e5e7eb;border-radius:14px;
  box-shadow:0 10px 28px rgba(0,0,0,.05);overflow:hidden;
}
.cardx .cardx-h{
  display:flex;align-items:center;justify-content:space-between;gap:12px;
  padding:16px 18px;border-bottom:1px solid #eef2f7;background:#fafafa;
}
.cardx .cardx-h h3{margin:0;font-size:16px;font-weight:900;color:#111827}
.cardx .cardx-b{padding:18px}

/* form */
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:992px){.form-row{grid-template-columns:1fr}}
.form-group{margin-bottom:14px}
.form-group label{display:block;margin-bottom:6px;font-weight:700;color:#111827}
.form-group input[type="text"],
.form-group input[type="number"],
.form-group textarea{
  width:100%;padding:12px;border:1px solid #e5e7eb;border-radius:10px;font-size:14px;
}
.form-group textarea{min-height:120px;resize:vertical}
.hint{color:#6b7280;font-size:12px;margin-top:6px}

/* sections list */
.sortable-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:10px}
.sort-item{border:1px solid #e5e7eb;border-radius:14px;background:#fff;overflow:hidden}
.sort-top{
  display:flex;align-items:center;justify-content:space-between;gap:12px;
  padding:14px 16px;background:#fff;
}
.sort-left{display:flex;align-items:center;gap:12px;min-width:0}
.drag-handle{
  width:34px;height:34px;border-radius:10px;background:#f3f4f6;
  display:flex;align-items:center;justify-content:center;
  cursor:grab;color:#6b7280;border:1px solid #e5e7eb;
}
.drag-handle:active{cursor:grabbing}
.si-title{min-width:0}
.si-title strong{display:block;font-weight:900;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.si-title small{color:#6b7280}
.sort-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end}

/* toggle */
.switch{position:relative;display:inline-block;width:48px;height:28px;flex:0 0 auto}
.switch input{opacity:0;width:0;height:0}
.slider{
  position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;
  background:#e5e7eb;transition:.2s;border-radius:999px;border:1px solid #d1d5db;
}
.slider:before{
  position:absolute;content:"";height:22px;width:22px;left:3px;top:2px;
  background:#fff;transition:.2s;border-radius:999px;box-shadow:0 3px 8px rgba(0,0,0,.15)
}
.switch input:checked + .slider{background:#10b981;border-color:#10b981}
.switch input:checked + .slider:before{transform:translateX(20px)}

.badge-active{background:#dcfce7;color:#166534;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:900}
.badge-inactive{background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:900}

.inline-edit{
  display:none;
  padding:16px;
  border-top:1px solid #eef2f7;
  background:#fafafa;
}
.inline-edit.open{display:block}

.btn-xs{padding:7px 10px;font-size:12px;border-radius:10px}
.btn-ghost{background:#f3f4f6;border:1px solid #e5e7eb}
.btn-ghost:hover{background:#e5e7eb}
.small-muted{color:#6b7280;font-size:12px}
</style>

<div class="content-header">
  <h2><i class="fas fa-briefcase" style="color:#f86505;"></i> Manage Opportunities</h2>
</div>

<?php $flash = getFlashMessage(); if ($flash): ?>
<div class="alert alert-<?php echo $flash['type']; ?>">
  <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
  <?php echo $flash['message']; ?>
</div>
<?php endif; ?>

<div class="op-wrap">

  <!-- MAIN CONTENT -->
  <div class="cardx" style="margin-bottom:16px;">
    <div class="cardx-h">
      <h3>Hero / Main Content</h3>
    </div>
    <div class="cardx-b">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action"  value="save_main">
        <input type="hidden" name="op_id"   value="<?php echo (int)($opportunity['id'] ?? 0); ?>">

        <div class="form-group">
          <label>Hero Featured Image</label>
          <input type="file" name="featured_image" accept="image/*">
          <div class="hint">Recommended: 1600x900 or larger.</div>
        </div>

        <?php if (!empty($opportunity['featured_image'])): ?>
        <div class="form-group" style="margin-top:-6px;">
          <label>Current Featured Image</label>
          <div style="margin-top:8px;">
            <img src="../uploads/opportunities/<?php echo htmlspecialchars($opportunity['featured_image']); ?>"
                 alt="Featured"
                 style="max-width:360px;border-radius:14px;border:2px solid #e5e7eb;box-shadow:0 10px 28px rgba(0,0,0,.06);">
          </div>
          <div class="hint">Uploading a new image will replace this one.</div>
        </div>
        <?php endif; ?>

        <div class="form-group">
          <label>Headline *</label>
          <textarea name="headline" rows="3" required><?php echo htmlspecialchars($opportunity['headline'] ?? ''); ?></textarea>
          <div class="hint">Line breaks will display as separate lines on the frontend (nl2br).</div>
        </div>

        <div class="form-group">
          <label>Intro (optional)</label>
          <textarea name="intro" rows="4"><?php echo htmlspecialchars($opportunity['intro'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
          <label>Framework Intro</label>
          <textarea name="framework_intro" rows="3"><?php echo htmlspecialchars($opportunity['framework_intro'] ?? ''); ?></textarea>
        </div>

        <div class="form-group" style="margin-top:10px;">
          <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
            <input type="checkbox" name="is_active" value="1" <?php echo !empty($opportunity['is_active']) ? 'checked' : ''; ?>>
            <span>Page Active</span>
          </label>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px;">
          <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Save</button>
          <a class="btn btn-ghost" href="../opportunities.php" target="_blank"><i class="fas fa-eye"></i> View Page</a>
        </div>
      </form>
    </div>
  </div>

  <!-- ADD SECTION -->
  <div class="cardx" style="margin-bottom:16px;">
    <div class="cardx-h">
      <h3>Add New Framework Section</h3>
      <div class="small-muted">Example: Demographics, Digital Transformation</div>
    </div>
    <div class="cardx-b">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_section">
        <input type="hidden" name="id"     value="0">

        <div class="form-row">
          <div class="form-group">
            <label>Title *</label>
            <input type="text" name="title" required placeholder="e.g., Demographics">
          </div>
          <div class="form-group">
            <label>Sort Order</label>
            <input type="number" name="sort_order" value="0" min="0">
            <div class="hint">Leave 0 to auto-append at the end.</div>
          </div>
        </div>

        <div class="form-group">
          <label>Content *</label>
          <textarea name="content" rows="6" required placeholder="Write the section content here..."></textarea>
          <div class="hint">Use line breaks to separate paragraphs.</div>
        </div>

        <div class="form-group">
          <label>Section Image (optional)</label>
          <input type="file" name="section_image" accept="image/*">
          <div class="hint">Recommended: 600x600 or square format.</div>
        </div>

        <div class="form-group">
          <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
            <input type="checkbox" name="is_active" value="1" checked>
            <span>Active</span>
          </label>
        </div>

        <button class="btn btn-primary" type="submit"><i class="fas fa-plus"></i> Add Section</button>
      </form>
    </div>
  </div>

  <!-- SECTIONS LIST -->
  <div class="cardx">
    <div class="cardx-h">
      <h3>Framework Sections</h3>
      <div class="small-muted">Drag &amp; drop to reorder - Toggle active inline - Edit expands below</div>
    </div>
    <div class="cardx-b">

      <?php if (empty($sections)): ?>
        <div style="text-align:center;padding:30px;color:#6b7280;">
          <i class="fas fa-inbox" style="font-size:44px;color:#d1d5db;margin-bottom:12px;display:block;"></i>
          <strong>No sections yet</strong><br>
          <small>Add your first section above.</small>
        </div>
      <?php else: ?>
        <ul class="sortable-list" id="oppSortable">
          <?php foreach ($sections as $s): ?>
            <?php
              $sid      = (int)$s['id'];
              $isActive = !empty($s['is_active']);
              $preview  = trim(strip_tags($s['content'] ?? ''));
              if (mb_strlen($preview) > 90) $preview = mb_substr($preview, 0, 90) . '...';
            ?>
            <li class="sort-item" data-id="<?php echo $sid; ?>" id="sec-<?php echo $sid; ?>">

              <div class="sort-top">
                <div class="sort-left">
                  <div class="drag-handle" title="Drag to reorder"><i class="fas fa-grip-vertical"></i></div>
                  <div class="si-title">
                    <strong><?php echo htmlspecialchars($s['title']); ?></strong>
                    <small><?php echo htmlspecialchars($preview); ?></small>
                  </div>
                </div>

                <div class="sort-actions">
                  <span class="<?php echo $isActive ? 'badge-active' : 'badge-inactive'; ?>"
                        id="badge-<?php echo $sid; ?>">
                    <?php echo $isActive ? 'ACTIVE' : 'INACTIVE'; ?>
                  </span>

                  <label class="switch" title="Activate / Deactivate">
                    <input type="checkbox"
                           <?php echo $isActive ? 'checked' : ''; ?>
                           onchange="toggleSection(<?php echo $sid; ?>, this)">
                    <span class="slider"></span>
                  </label>

                  <button type="button" class="btn btn-xs btn-ghost"
                          onclick="toggleEdit(<?php echo $sid; ?>)">
                    <i class="fas fa-edit"></i> Edit
                  </button>

                  <!-- FIX: delete form now posts to the delete_section handler -->
                  <form method="POST"
                        onsubmit="return confirm('Delete section: <?php echo addslashes(htmlspecialchars($s['title'])); ?>?');"
                        style="display:inline;">
                    <input type="hidden" name="action" value="delete_section">
                    <input type="hidden" name="id"     value="<?php echo $sid; ?>">
                    <button type="submit" class="btn btn-xs btn-danger">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  </form>
                </div>
              </div><!-- /.sort-top -->

              <!-- FIX: enctype added so section_image upload works inside inline edit -->
              <div class="inline-edit" id="edit-<?php echo $sid; ?>">
                <form method="POST" enctype="multipart/form-data">
                  <input type="hidden" name="action" value="save_section">
                  <input type="hidden" name="id"     value="<?php echo $sid; ?>">

                  <div class="form-row">
                    <div class="form-group">
                      <label>Title *</label>
                      <input type="text" name="title" required
                             value="<?php echo htmlspecialchars($s['title']); ?>">
                    </div>
                    <div class="form-group">
                      <label>Sort Order</label>
                      <input type="number" name="sort_order" min="0"
                             value="<?php echo (int)$s['sort_order']; ?>">
                      <div class="hint">Drag &amp; drop updates sort order automatically.</div>
                    </div>
                  </div>

                  <div class="form-group">
                    <label>Content *</label>
                    <textarea name="content" rows="7" required><?php echo htmlspecialchars($s['content']); ?></textarea>
                  </div>

                  <div class="form-group">
                    <label>Section Image (optional)</label>
                    <input type="file" name="section_image" accept="image/*">
                    <div class="hint">Recommended: 600x600 or square format.</div>
                    <?php if (!empty($s['section_image'])): ?>
                      <div style="margin-top:8px;">
                        <img src="../uploads/opportunities/<?php echo htmlspecialchars($s['section_image']); ?>"
                             alt="Section image"
                             style="max-width:180px;border-radius:10px;border:2px solid #e5e7eb;">
                        <div class="hint">Uploading a new image will replace this one.</div>
                      </div>
                    <?php endif; ?>
                  </div>

                  <div class="form-group">
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                      <input type="checkbox" name="is_active" value="1"
                             <?php echo $isActive ? 'checked' : ''; ?>>
                      <span>Active</span>
                    </label>
                    <div class="hint">Note: The toggle above updates status instantly via AJAX; this checkbox saves on form submit.</div>
                  </div>

                  <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <button class="btn btn-primary" type="submit">
                      <i class="fas fa-save"></i> Save Changes
                    </button>
                    <button class="btn btn-ghost" type="button"
                            onclick="toggleEdit(<?php echo $sid; ?>)">
                      <i class="fas fa-times"></i> Close
                    </button>
                  </div>
                </form>
              </div><!-- /.inline-edit -->

            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

    </div>
  </div><!-- /.cardx sections -->

</div><!-- /.op-wrap -->

<!-- SortableJS -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
(function () {
  const list = document.getElementById('oppSortable');
  if (!list) return;

  new Sortable(list, {
    animation: 160,
    handle: '.drag-handle',
    onEnd: function () {
      const payload = [];
      list.querySelectorAll('.sort-item').forEach((el, idx) => {
        payload.push({ id: parseInt(el.dataset.id || '0', 10), sort_order: idx });
      });

      fetch('opportunities.php?ajax=reorder', {
        method : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body   : JSON.stringify(payload)
      })
        .then(r => r.json())
        .then(j => {
          if (!j || !j.success) alert('Failed to save order. Please refresh and try again.');
        })
        .catch(() => alert('Failed to save order. Please refresh and try again.'));
    }
  });
})();

function toggleEdit(id) {
  const box = document.getElementById('edit-' + id);
  if (!box) return;
  box.classList.toggle('open');
  if (box.classList.contains('open')) {
    setTimeout(() => box.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 50);
  }
}

function toggleSection(id, checkbox) {
  const fd = new FormData();
  fd.append('id', id);

  fetch('opportunities.php?ajax=toggle', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(j => {
      if (!j || !j.success) {
        alert(j && j.message ? j.message : 'Failed to update status.');
        checkbox.checked = !checkbox.checked;
        return;
      }
      const badge = document.getElementById('badge-' + id);
      if (badge) {
        const active = parseInt(j.is_active, 10) === 1;
        badge.textContent = active ? 'ACTIVE' : 'INACTIVE';
        badge.className   = active ? 'badge-active' : 'badge-inactive';
      }
    })
    .catch(() => {
      alert('Failed to update status.');
      checkbox.checked = !checkbox.checked;
    });
}
</script>

<?php include 'layout/footer.php'; ?>