<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/includes/admin-enhancements.php';
adminTrackActivity($conn);

requirePermission($conn, 'manage_programs');

$page_title = 'Manage Programs';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $id = $_POST['id'] ?? 0;
        $title = clean($conn, $_POST['title']);
        $slug = generateSlug($_POST['slug'] ?: $title);
        $description = clean($conn, $_POST['description'] ?? '');
        $content = $_POST['content'];
        $location = clean($conn, $_POST['location'] ?? '');
        $start_date = $_POST['start_date'] ?? null;
        $end_date = $_POST['end_date'] ?? null;
        $status = clean($conn, $_POST['status']);
        
        $featured_image = '';
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === 0) {
            $upload_result = uploadFile($_FILES['featured_image'], 'programs');
            if ($upload_result['success']) {
                $featured_image = $upload_result['filename'];
            }
        }
        
        if ($id > 0) {
            $sql = "UPDATE programs SET title = ?, slug = ?, description = ?, content = ?, location = ?, start_date = ?, end_date = ?, status = ?";
            $params = [$title, $slug, $description, $content, $location, $start_date, $end_date, $status];
            $types = "ssssssss";
            
            if ($featured_image) {
                $sql .= ", featured_image = ?";
                $params[] = $featured_image;
                $types .= "s";
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $id;
            $types .= "i";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            
            setFlashMessage('success', 'Program updated successfully');
        } else {
            $stmt = $conn->prepare("INSERT INTO programs (title, slug, description, content, featured_image, location, start_date, end_date, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssssi", $title, $slug, $description, $content, $featured_image, $location, $start_date, $end_date, $status, $_SESSION['user_id']);
            $stmt->execute();
            
            setFlashMessage('success', 'Program created successfully');
        }
        
        header("Location: programs.php");
        exit();
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM programs WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        setFlashMessage('success', 'Program deleted successfully');
        header("Location: programs.php");
        exit();
    }
}

$edit_program = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM programs WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_program = $result->fetch_assoc();
}

$programs = [];
$result = $conn->query("SELECT p.*, u.name as author_name FROM programs p LEFT JOIN users u ON p.created_by = u.id ORDER BY p.created_at DESC");
while ($row = $result->fetch_assoc()) {
    $programs[] = $row;
}

include 'layout/header.php';
include 'layout/sidebar.php';
?>

<div class="content-header">
    <h2>Manage Programs</h2>
    <button class="btn btn-primary" onclick="showModal('programModal')">Add New Program</button>
</div>

<?php 
$flash = getFlashMessage();
if ($flash): 
?>
    <div class="alert alert-<?php echo $flash['type']; ?>">
        <?php echo $flash['message']; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Location</th>
                    <th>Start Date</th>
                    <th>Status</th>
                    <th>Author</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($programs as $program): ?>
                <tr>
                    <td><?php echo htmlspecialchars($program['title']); ?></td>
                    <td><?php echo htmlspecialchars($program['location']); ?></td>
                    <td><?php echo $program['start_date'] ? formatDate($program['start_date']) : 'N/A'; ?></td>
                    <td><span class="badge badge-<?php echo $program['status']; ?>"><?php echo ucfirst($program['status']); ?></span></td>
                    <td><?php echo htmlspecialchars($program['author_name']); ?></td>
                    <td>
                        <a href="?edit=<?php echo $program['id']; ?>" class="btn btn-sm btn-edit"><i class="fas fa-edit"></i>  </a>
                        <button onclick="deleteProgram(<?php echo $program['id']; ?>)" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="programModal" class="modal" style="display: <?php echo $edit_program ? 'flex' : 'none'; ?>">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3><?php echo $edit_program ? 'Edit Program' : 'Add New Program'; ?></h3>
            <button class="modal-close" onclick="hideModal('programModal')">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?php echo $edit_program['id'] ?? 0; ?>">
            
            <div class="form-row">
                <div class="form-group col-8">
                    <label>Title *</label>
                    <input type="text" name="title" required value="<?php echo htmlspecialchars($edit_program['title'] ?? ''); ?>">
                </div>
                <div class="form-group col-4">
                    <label>Status *</label>
                    <select name="status" required>
                        <option value="active" <?php echo ($edit_program['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($edit_program['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="completed" <?php echo ($edit_program['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-6">
                    <label>Slug (URL)</label>
                    <input type="text" name="slug" value="<?php echo htmlspecialchars($edit_program['slug'] ?? ''); ?>">
                </div>
                <div class="form-group col-6">
                    <label>Location</label>
                    <input type="text" name="location" value="<?php echo htmlspecialchars($edit_program['location'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-6">
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="<?php echo $edit_program['start_date'] ?? ''; ?>">
                </div>
                <div class="form-group col-6">
                    <label>End Date</label>
                    <input type="date" name="end_date" value="<?php echo $edit_program['end_date'] ?? ''; ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Featured Image</label>
                <input type="file" name="featured_image" accept="image/*">
            </div>
            
            <div class="form-group">
                <label>Short Description</label>
                <textarea name="description" rows="3"><?php echo htmlspecialchars($edit_program['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Full Content</label>
                <textarea name="content" rows="8"><?php echo htmlspecialchars($edit_program['content'] ?? ''); ?></textarea>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn" onclick="hideModal('programModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Program</button>
            </div>
        </form>
    </div>
</div>

<script>
function deleteProgram(id) {
    if (confirm('Are you sure you want to delete this program?')) {
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
</script>

<?php include 'layout/footer.php'; ?>