<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/includes/admin-enhancements.php';
adminTrackActivity($conn);

requirePermission($conn, 'manage_reports');

$page_title = 'Manage Reports';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $id = $_POST['id'] ?? 0;
        $title = clean($conn, $_POST['title']);
        $description = clean($conn, $_POST['description'] ?? '');
        $year = intval($_POST['year'] ?? date('Y'));
        $type = clean($conn, $_POST['type'] ?? '');
        
        $file_path = '';
        $file_size = 0;
        
        if (isset($_FILES['report_file']) && $_FILES['report_file']['error'] === 0) {
            $upload_result = uploadFile($_FILES['report_file'], 'reports');
            if ($upload_result['success']) {
                $file_path = $upload_result['filename'];
                $file_size = $_FILES['report_file']['size'];
            }
        }
        
        if ($id > 0) {
            $sql = "UPDATE reports SET title = ?, description = ?, year = ?, type = ?";
            $params = [$title, $description, $year, $type];
            $types = "ssss";
            
            if ($file_path) {
                $sql .= ", file_path = ?, file_size = ?";
                $params[] = $file_path;
                $params[] = $file_size;
                $types .= "si";
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $id;
            $types .= "i";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            
            setFlashMessage('success', 'Report updated successfully');
        } else {
            if ($file_path) {
                $stmt = $conn->prepare("INSERT INTO reports (title, description, year, type, file_path, file_size, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssissii", $title, $description, $year, $type, $file_path, $file_size, $_SESSION['user_id']);
                $stmt->execute();
                setFlashMessage('success', 'Report uploaded successfully');
            } else {
                setFlashMessage('error', 'Please upload a file');
            }
        }
        
        header("Location: reports.php");
        exit();
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        
        // Get file path before deleting
        $stmt = $conn->prepare("SELECT file_path FROM reports WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $report = $result->fetch_assoc();
        
        if ($report && $report['file_path']) {
            deleteFile($report['file_path'], 'reports');
        }
        
        $stmt = $conn->prepare("DELETE FROM reports WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        setFlashMessage('success', 'Report deleted successfully');
        header("Location: reports.php");
        exit();
    }
}

$edit_report = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM reports WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_report = $result->fetch_assoc();
}

$reports = [];
$result = $conn->query("SELECT r.*, u.name as author_name FROM reports r LEFT JOIN users u ON r.created_by = u.id ORDER BY r.year DESC, r.created_at DESC");
while ($row = $result->fetch_assoc()) {
    $reports[] = $row;
}

include 'layout/header.php';
include 'layout/sidebar.php';
?>

<div class="content-header">
    <h2>Manage Reports</h2>
    <button class="btn btn-primary" onclick="showModal('reportModal')">Upload Report</button>
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
                    <th>Type</th>
                    <th>Year</th>
                    <th>File Size</th>
                    <th>Downloads</th>
                    <th>Uploaded By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $report): ?>
                <tr>
                    <td><?php echo htmlspecialchars($report['title']); ?></td>
                    <td><?php echo htmlspecialchars($report['type']); ?></td>
                    <td><?php echo $report['year']; ?></td>
                    <td><?php echo round($report['file_size'] / 1024, 2); ?> KB</td>
                    <td><?php echo $report['download_count']; ?></td>
                    <td><?php echo htmlspecialchars($report['author_name']); ?></td>
                    <td>
                        <a href="../uploads/reports/<?php echo $report['file_path']; ?>" class="btn btn-sm" target="_blank">Download</a>
                        <a href="?edit=<?php echo $report['id']; ?>" class="btn btn-sm btn-edit">Edit</a>
                        <button onclick="deleteReport(<?php echo $report['id']; ?>)" class="btn btn-sm btn-danger">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="reportModal" class="modal" style="display: <?php echo $edit_report ? 'flex' : 'none'; ?>">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php echo $edit_report ? 'Edit Report' : 'Upload Report'; ?></h3>
            <button class="modal-close" onclick="hideModal('reportModal')">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?php echo $edit_report['id'] ?? 0; ?>">
            
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" required value="<?php echo htmlspecialchars($edit_report['title'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3"><?php echo htmlspecialchars($edit_report['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group col-6">
                    <label>Year *</label>
                    <input type="number" name="year" required value="<?php echo $edit_report['year'] ?? date('Y'); ?>" min="1900" max="<?php echo date('Y') + 10; ?>">
                </div>
                <div class="form-group col-6">
                    <label>Type</label>
                    <input type="text" name="type" value="<?php echo htmlspecialchars($edit_report['type'] ?? ''); ?>" placeholder="e.g., Annual, Quarterly">
                </div>
            </div>
            
            <div class="form-group">
                <label>Report File <?php echo $edit_report ? '(leave empty to keep current)' : '*'; ?></label>
                <input type="file" name="report_file" accept=".pdf,.doc,.docx,.xls,.xlsx" <?php echo $edit_report ? '' : 'required'; ?>>
                <small>Allowed formats: PDF, DOC, DOCX, XLS, XLSX. Max size: 5MB</small>
            </div>
            
            <?php if ($edit_report && $edit_report['file_path']): ?>
                <div class="form-group">
                    <small>Current file: <?php echo htmlspecialchars($edit_report['file_path']); ?></small>
                </div>
            <?php endif; ?>
            
            <div class="modal-footer">
                <button type="button" class="btn" onclick="hideModal('reportModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Report</button>
            </div>
        </form>
    </div>
</div>

<script>
function deleteReport(id) {
    if (confirm('Are you sure you want to delete this report? This will also delete the file.')) {
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