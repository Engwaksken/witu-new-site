<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/includes/admin-enhancements.php';
adminTrackActivity($conn);

$has_permission = false;
if (function_exists('hasPermission')) {
    $has_permission = hasPermission($conn, 'manage_programmes') || hasPermission($conn, 'manage_content');
} else {
    $has_permission = true;
}

if (!$has_permission) {
    die('<h2>Access Denied</h2><p>You don\'t have permission to manage programmes.</p>');
}

$page_title = 'Manage Programmes';

function programme_column_exists(mysqli $conn, string $column): bool
{
    $column = $conn->real_escape_string($column);

    $result = $conn->query("
        SHOW COLUMNS
        FROM programmes
        LIKE '{$column}'
    ");

    return $result && $result->num_rows > 0;
}

if (!programme_column_exists($conn, 'parent_id')) {
    $conn->query("ALTER TABLE programmes ADD COLUMN parent_id INT NULL DEFAULT NULL AFTER id");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = intval($_POST['id'] ?? 0);

        $title            = clean($conn, $_POST['title'] ?? '');
        $slug             = clean($conn, $_POST['slug'] ?? '');
        $summary          = clean($conn, $_POST['summary'] ?? '');
        $description      = clean($conn, $_POST['description'] ?? '');
        $content          = $_POST['content'] ?? '';
        $icon             = clean($conn, $_POST['icon'] ?? '');
        $display_order    = intval($_POST['display_order'] ?? 0);
        $status           = clean($conn, $_POST['status'] ?? 'published');
        $show_in_menu     = isset($_POST['show_in_menu']) ? 1 : 0;
        $meta_title       = clean($conn, $_POST['meta_title'] ?? '');
        $meta_description = clean($conn, $_POST['meta_description'] ?? '');
        $parent_id        = intval($_POST['parent_id'] ?? 0);
        $parent_id        = $parent_id > 0 ? $parent_id : null;

        if ($id > 0 && $parent_id === $id) {
            $parent_id = null;
        }

        $slug = empty($slug) ? generateSlug($title) : generateSlug($slug);

        $featured_image = '';

        if (isset($_FILES['featured_image']) && (int)($_FILES['featured_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $allowedProgrammeImages = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $upload_result = uploadFile($_FILES['featured_image'], 'programmes', $allowedProgrammeImages);

            if (empty($upload_result['success'])) {
                setFlashMessage('danger', $upload_result['message'] ?? 'Featured image upload failed.');
                header('Location: programmes.php');
                exit();
            }

            $featured_image = $upload_result['filename'];
        }

        if ($id > 0) {
            $sql = "UPDATE programmes 
                    SET title = ?, slug = ?, summary = ?, description = ?, content = ?, icon = ?, 
                        display_order = ?, status = ?, show_in_menu = ?, meta_title = ?, 
                        meta_description = ?, parent_id = ?";

            $params = [
                $title,
                $slug,
                $summary,
                $description,
                $content,
                $icon,
                $display_order,
                $status,
                $show_in_menu,
                $meta_title,
                $meta_description,
                $parent_id
            ];

            $types = "ssssssisissi";

            if ($featured_image !== '') {
                $stmt = $conn->prepare("SELECT featured_image FROM programmes WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $old = $stmt->get_result()->fetch_assoc();

                if ($old && !empty($old['featured_image'])) {
                    deleteFile($old['featured_image'], 'programmes');
                }

                $sql .= ", featured_image = ?";
                $params[] = $featured_image;
                $types .= "s";
            }

            $sql .= " WHERE id = ?";
            $params[] = $id;
            $types .= "i";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                if ($featured_image !== '') {
                    deleteFile($featured_image, 'programmes');
                }
                setFlashMessage('danger', 'Unable to prepare programme update: ' . $conn->error);
                header('Location: programmes.php');
                exit();
            }

            $stmt->bind_param($types, ...$params);
            if (!$stmt->execute()) {
                if ($featured_image !== '') {
                    deleteFile($featured_image, 'programmes');
                }
                setFlashMessage('danger', 'Unable to update programme: ' . $stmt->error);
                header('Location: programmes.php');
                exit();
            }

            setFlashMessage('success', 'Programme updated successfully');
        } else {
            $created_by = intval($_SESSION['user_id'] ?? 0);

            $stmt = $conn->prepare("
                INSERT INTO programmes 
                (parent_id, title, slug, summary, description, content, featured_image, icon, display_order, status, show_in_menu, meta_title, meta_description, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "isssssssisissi",
                $parent_id,
                $title,
                $slug,
                $summary,
                $description,
                $content,
                $featured_image,
                $icon,
                $display_order,
                $status,
                $show_in_menu,
                $meta_title,
                $meta_description,
                $created_by
            );

            if (!$stmt->execute()) {
                if ($featured_image !== '') {
                    deleteFile($featured_image, 'programmes');
                }
                setFlashMessage('danger', 'Unable to create programme: ' . $stmt->error);
                header('Location: programmes.php');
                exit();
            }

            setFlashMessage('success', 'Programme created successfully');
        }

        header("Location: programmes.php");
        exit();
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);

        $stmt = $conn->prepare("SELECT featured_image FROM programmes WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $programme = $stmt->get_result()->fetch_assoc();

        if ($programme && !empty($programme['featured_image'])) {
            deleteFile($programme['featured_image'], 'programmes');
        }

        $stmt = $conn->prepare("UPDATE programmes SET parent_id = NULL WHERE parent_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $stmt = $conn->prepare("DELETE FROM programmes WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        setFlashMessage('success', 'Programme deleted successfully');
        header("Location: programmes.php");
        exit();
    }
}

$parent_programmes = [];
$result = $conn->query("SELECT id, title FROM programmes ORDER BY title ASC");
while ($row = $result->fetch_assoc()) {
    $parent_programmes[] = $row;
}

$programmes = [];
$result = $conn->query("
    SELECT p.*, parent.title AS parent_title, u.name AS author_name
    FROM programmes p
    LEFT JOIN programmes parent ON parent.id = p.parent_id
    LEFT JOIN users u ON p.created_by = u.id
    ORDER BY 
        CASE WHEN p.parent_id IS NULL THEN p.display_order ELSE 999999 END,
        p.parent_id ASC,
        p.display_order ASC,
        p.created_at DESC
");

while ($row = $result->fetch_assoc()) {
    $programmes[] = $row;
}

include 'layout/header.php';
include 'layout/sidebar.php';
?>

<style>
.programme-preview {
    display: flex;
    align-items: center;
    gap: 12px;
}

.programme-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #f86505 0%, #f86505a8 100%);
    border-radius: 8px;
    color: #fff;
    font-size: 18px;
    flex-shrink: 0;
}

.programme-thumb {
    width: 60px;
    height: 40px;
    object-fit: cover;
    border-radius: 6px;
    border: 2px solid #e5e7eb;
    flex-shrink: 0;
}

.parent-label {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 5px;
    padding: 3px 8px;
    border-radius: 999px;
    background: #fff7ed;
    color: #c2410c;
    font-size: 11px;
    font-weight: 600;
}

.child-indent {
    padding-left: 20px;
    border-left: 3px solid #f86505;
}
</style>

<div class="content-header">
    <h2><i class="fas fa-rocket" style="color:#f86505;"></i> Manage Programmes</h2>
    <button class="btn btn-primary" onclick="openCreateModal()">
        <i class="fas fa-plus"></i> Create Programme
    </button>
</div>

<?php $flash = getFlashMessage(); ?>
<?php if ($flash): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?>">
        <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo htmlspecialchars($flash['message']); ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th width="50">Order</th>
                    <th>Programme</th>
                    <th>Parent Program</th>
                    <th>Summary</th>
                    <th width="100">Status</th>
                    <th width="80">Menu</th>
                    <th width="200">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($programmes)): ?>
                <tr>
                    <td colspan="7" style="text-align:center;padding:40px;color:#6b7280;">
                        <i class="fas fa-inbox" style="font-size:48px;color:#d1d5db;margin-bottom:15px;display:block;"></i>
                        <strong>No programmes found</strong><br>
                        <small>Create your first programme to get started!</small>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($programmes as $prog): ?>
                    <tr>
                        <td style="text-align:center;font-weight:600;color:#6b7280;">
                            <?php echo intval($prog['display_order']); ?>
                        </td>

                        <td>
                            <div class="programme-preview <?php echo !empty($prog['parent_id']) ? 'child-indent' : ''; ?>">
                                <?php if (!empty($prog['featured_image'])): ?>
                                    <img src="../uploads/programmes/<?php echo htmlspecialchars($prog['featured_image']); ?>"
                                         alt="<?php echo htmlspecialchars($prog['title']); ?>"
                                         class="programme-thumb">
                                <?php elseif (!empty($prog['icon'])): ?>
                                    <div class="programme-icon">
                                        <i class="<?php echo htmlspecialchars($prog['icon']); ?>"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="programme-icon">
                                        <i class="fas fa-rocket"></i>
                                    </div>
                                <?php endif; ?>

                                <div>
                                    <strong style="display:block;margin-bottom:4px;">
                                        <?php echo htmlspecialchars($prog['title']); ?>
                                    </strong>
                                    <small style="color:#6b7280;">
                                        <i class="fas fa-link" style="font-size:10px;"></i>
                                        <?php echo htmlspecialchars($prog['slug']); ?>
                                    </small>
                                </div>
                            </div>
                        </td>

                        <td>
                            <?php if (!empty($prog['parent_title'])): ?>
                                <span class="parent-label">
                                    <i class="fas fa-sitemap"></i>
                                    <?php echo htmlspecialchars($prog['parent_title']); ?>
                                </span>
                            <?php else: ?>
                                <small style="color:#6b7280;">Main Program</small>
                            <?php endif; ?>
                        </td>

                        <td>
                            <small style="color:#6b7280;line-height:1.5;">
                                <?php echo htmlspecialchars(truncate($prog['summary'] ?? '', 100)); ?>
                            </small>
                        </td>

                        <td>
                            <span class="badge badge-<?php echo $prog['status'] === 'published' ? 'success' : ($prog['status'] === 'draft' ? 'warning' : 'secondary'); ?>">
                                <?php echo ucfirst(htmlspecialchars($prog['status'])); ?>
                            </span>
                        </td>

                        <td style="text-align:center;">
                            <?php if (!empty($prog['show_in_menu'])): ?>
                                <span style="color:#10b981;" title="Shown in menu">
                                    <i class="fas fa-check-circle"></i>
                                </span>
                            <?php else: ?>
                                <span style="color:#ef4444;" title="Hidden from menu">
                                    <i class="fas fa-times-circle"></i>
                                </span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div style="display:flex;gap:5px;">
                                <a href="../programme.php?slug=<?php echo urlencode($prog['slug']); ?>"
                                   class="btn btn-sm"
                                   target="_blank"
                                   title="View Programme">
                                    <i class="fas fa-eye"></i>
                                </a>

                                <button type="button"
                                        onclick='editProgramme(<?php echo json_encode($prog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                                        class="btn btn-sm btn-primary"
                                        title="Edit Programme">
                                    <i class="fas fa-edit"></i>
                                </button>

                                <button type="button"
                                        onclick="deleteProgramme(<?php echo intval($prog['id']); ?>, '<?php echo addslashes($prog['title']); ?>')"
                                        class="btn btn-sm btn-danger"
                                        title="Delete Programme">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="programmeModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:900px;max-height:90vh;overflow-y:auto;">
        <div class="modal-header">
            <h3>
                <i class="fas fa-rocket"></i>
                <span id="modalTitleText">Create Programme</span>
            </h3>
            <button class="modal-close" onclick="closeProgrammeModal()">&times;</button>
        </div>

        <form method="POST" enctype="multipart/form-data" id="programmeForm">
            <div class="modal-body">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="progId" value="0">

                <div class="form-group">
                    <label>Parent Program</label>
                    <select name="parent_id" id="progParent">
                        <option value="">None - Main Program</option>
                        <?php foreach ($parent_programmes as $parent): ?>
                            <option value="<?php echo intval($parent['id']); ?>">
                                <?php echo htmlspecialchars($parent['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Select a parent program if this programme belongs under another programme.</small>
                </div>

                <div class="form-row">
                    <div class="form-group col-8">
                        <label>Programme Title *</label>
                        <input type="text"
                               name="title"
                               id="progTitle"
                               required
                               placeholder="e.g., Tech Innovation Programme"
                               onkeyup="generateSlugFromTitle()">
                    </div>

                    <div class="form-group col-4">
                        <label>Display Order</label>
                        <input type="number"
                               name="display_order"
                               id="progOrder"
                               value="0"
                               min="0">
                        <small>Lower = higher priority</small>
                    </div>
                </div>

                <div class="form-group">
                    <label>URL Slug</label>
                    <input type="text"
                           name="slug"
                           id="progSlug"
                           placeholder="auto-generated-from-title">
                    <small>Leave empty to auto-generate.</small>
                </div>

                <div class="form-group">
                    <label>Summary *</label>
                    <textarea name="summary" id="progSummary" rows="2" required></textarea>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="progDescription" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label>Content</label>
                    <textarea name="content" id="progContent" rows="8"></textarea>
                    <small>HTML allowed.</small>
                </div>

                <div class="form-row">
                    <div class="form-group col-6">
                        <label>Icon Class</label>
                        <input type="text" name="icon" id="progIcon" placeholder="fas fa-rocket">
                    </div>

                    <div class="form-group col-6">
                        <label>Status</label>
                        <select name="status" id="progStatus">
                            <option value="published">Published</option>
                            <option value="draft">Draft</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Featured Image</label>
                    <input type="file" name="featured_image" id="progImage" accept="image/*">
                </div>

                <div id="currentImagePreview" style="display:none;margin-bottom:15px;">
                    <label>Current Image:</label>
                    <div style="margin-top:8px;">
                        <img id="currentImage" src="" style="max-width:300px;border-radius:8px;border:2px solid #e5e7eb;">
                    </div>
                </div>

                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="show_in_menu" id="progMenu" value="1" checked>
                        <span>Show in Navigation Menu</span>
                    </label>
                </div>

                <div class="form-group">
                    <label>SEO Meta Title</label>
                    <input type="text" name="meta_title" id="progMetaTitle">
                </div>

                <div class="form-group">
                    <label>SEO Meta Description</label>
                    <textarea name="meta_description" id="progMetaDesc" rows="2"></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeProgrammeModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Programme
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('modalTitleText').textContent = 'Create Programme';
    document.getElementById('programmeForm').reset();
    document.getElementById('progId').value = '0';
    document.getElementById('progParent').disabled = false;
    document.getElementById('progImage').required = false;
    document.getElementById('currentImagePreview').style.display = 'none';
    document.getElementById('programmeModal').style.display = 'flex';
}

function editProgramme(programme) {
    document.getElementById('modalTitleText').textContent = 'Edit Programme';

    document.getElementById('progId').value = programme.id || 0;
    document.getElementById('progParent').value = programme.parent_id || '';
    document.getElementById('progTitle').value = programme.title || '';
    document.getElementById('progSlug').value = programme.slug || '';
    document.getElementById('progSummary').value = programme.summary || '';
    document.getElementById('progDescription').value = programme.description || '';
    document.getElementById('progContent').value = programme.content || '';
    document.getElementById('progIcon').value = programme.icon || '';
    document.getElementById('progOrder').value = programme.display_order || 0;
    document.getElementById('progStatus').value = programme.status || 'published';
    document.getElementById('progMenu').checked = programme.show_in_menu == 1;
    document.getElementById('progMetaTitle').value = programme.meta_title || '';
    document.getElementById('progMetaDesc').value = programme.meta_description || '';

    Array.from(document.getElementById('progParent').options).forEach(option => {
        option.disabled = option.value == programme.id;
    });

    document.getElementById('progImage').required = false;

    if (programme.featured_image) {
        document.getElementById('currentImage').src = '../uploads/programmes/' + programme.featured_image;
        document.getElementById('currentImagePreview').style.display = 'block';
    } else {
        document.getElementById('currentImagePreview').style.display = 'none';
    }

    document.getElementById('programmeModal').style.display = 'flex';
}

function closeProgrammeModal() {
    document.getElementById('programmeModal').style.display = 'none';
    document.getElementById('programmeForm').reset();

    Array.from(document.getElementById('progParent').options).forEach(option => {
        option.disabled = false;
    });
}

function deleteProgramme(id, title) {
    if (confirm(`Are you sure you want to delete "${title}"?\n\nChild programmes will remain but their parent will be cleared.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function generateSlugFromTitle() {
    const title = document.getElementById('progTitle').value;
    const slug = title
        .toLowerCase()
        .replace(/[^\w\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/--+/g, '-')
        .replace(/^-+|-+$/g, '');

    document.getElementById('progSlug').placeholder = slug || 'auto-generated-from-title';
}

window.onclick = function(event) {
    if (event.target.id === 'programmeModal') {
        closeProgrammeModal();
    }
};

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeProgrammeModal();
    }
});
</script>

<?php include 'layout/footer.php'; ?>