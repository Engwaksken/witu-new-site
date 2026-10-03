<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/includes/admin-enhancements.php';
adminTrackActivity($conn);

requirePermission($conn, 'manage_reports');

$page_title = 'Manage Annual Reports';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $id = $_POST['id'] ?? 0;
        $title = clean($conn, $_POST['title']);
        $year = intval($_POST['year'] ?? date('Y'));
        $description = clean($conn, $_POST['description'] ?? '');
        $status = clean($conn, $_POST['status'] ?? 'published');
        $published_date = clean($conn, $_POST['published_date'] ?? date('Y-m-d'));
        
        $pdf_file = '';
        $file_size = 0;
        $cover_image = '';
        
        // Handle PDF upload
        if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === 0) {
            $upload_result = uploadFile($_FILES['pdf_file'], 'annual-reports');
            if ($upload_result['success']) {
                $pdf_file = $upload_result['filename'];
                $file_size = $_FILES['pdf_file']['size'];
            }
        }
        
        // Handle cover image upload
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === 0) {
            $upload_result = uploadFile($_FILES['cover_image'], 'annual-reports/covers');
            if ($upload_result['success']) {
                $cover_image = $upload_result['filename'];
            }
        }
        
        if ($id > 0) {
            // Update existing report
            $sql = "UPDATE annual_reports SET title = ?, year = ?, description = ?, status = ?, published_date = ?";
            $params = [$title, $year, $description, $status, $published_date];
            $types = "sisss";
            
            if ($pdf_file) {
                // Delete old PDF
                $stmt = $conn->prepare("SELECT pdf_file FROM annual_reports WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $old = $stmt->get_result()->fetch_assoc();
                if ($old && $old['pdf_file']) {
                    deleteFile($old['pdf_file'], 'annual-reports');
                }
                
                $sql .= ", pdf_file = ?, file_size = ?";
                $params[] = $pdf_file;
                $params[] = $file_size;
                $types .= "si";
            }
            
            if ($cover_image) {
                // Delete old cover
                $stmt = $conn->prepare("SELECT cover_image FROM annual_reports WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $old = $stmt->get_result()->fetch_assoc();
                if ($old && $old['cover_image']) {
                    deleteFile($old['cover_image'], 'annual-reports/covers');
                }
                
                $sql .= ", cover_image = ?";
                $params[] = $cover_image;
                $types .= "s";
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $id;
            $types .= "i";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            
            setFlashMessage('success', 'Annual report updated successfully');
        } else {
            // Insert new report
            if ($pdf_file) {
                $stmt = $conn->prepare("INSERT INTO annual_reports (title, year, description, pdf_file, file_size, cover_image, status, published_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sisssissi", $title, $year, $description, $pdf_file, $file_size, $cover_image, $status, $published_date, $_SESSION['user_id']);
                $stmt->execute();
                setFlashMessage('success', 'Annual report uploaded successfully');
            } else {
                setFlashMessage('error', 'Please upload a PDF file');
            }
        }
        
        header("Location: annual-reports.php");
        exit();
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        
        // Get files before deleting
        $stmt = $conn->prepare("SELECT pdf_file, cover_image FROM annual_reports WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $report = $result->fetch_assoc();
        
        if ($report) {
            if ($report['pdf_file']) {
                deleteFile($report['pdf_file'], 'annual-reports');
            }
            if ($report['cover_image']) {
                deleteFile($report['cover_image'], 'annual-reports/covers');
            }
        }
        
        $stmt = $conn->prepare("DELETE FROM annual_reports WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        setFlashMessage('success', 'Annual report deleted successfully');
        header("Location: annual-reports.php");
        exit();
    }
}

$edit_report = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM annual_reports WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_report = $result->fetch_assoc();
}

$reports = [];
$result = $conn->query("SELECT r.*, u.name as author_name FROM annual_reports r LEFT JOIN users u ON r.created_by = u.id ORDER BY r.year DESC, r.created_at DESC");
while ($row = $result->fetch_assoc()) {
    $reports[] = $row;
}

include 'layout/header.php';
include 'layout/sidebar.php';
?>

<div class="content-header">
    <h2>Manage Annual Reports</h2>
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
                    <th>Year</th>
                    <th>File Size</th>
                    <th>Downloads</th>
                    <th>Status</th>
                    <th>Published</th>
                    <th>Uploaded By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $report): ?>
                <tr>
                    <td>
                        <?php echo htmlspecialchars($report['title']); ?>
                        <?php if ($report['cover_image']): ?>
                            <i class="fas fa-image" title="Has cover image"></i>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $report['year']; ?></td>
                    <td><?php echo round($report['file_size'] / 1024 / 1024, 2); ?> MB</td>
                    <td><?php echo $report['download_count']; ?></td>
                    <td>
                        <span class="badge badge-<?php echo $report['status'] === 'published' ? 'success' : ($report['status'] === 'draft' ? 'warning' : 'secondary'); ?>">
                            <?php echo ucfirst($report['status']); ?>
                        </span>
                    </td>
                    <td><?php echo date('M j, Y', strtotime($report['published_date'])); ?></td>
                    <td><?php echo htmlspecialchars($report['author_name']); ?></td>
                    <td>
                        <a href="../uploads/annual-reports/<?php echo $report['pdf_file']; ?>" class="btn btn-sm" target="_blank"><i class="fas fa-eye"></i></a>
                        <a href="?edit=<?php echo $report['id']; ?>" class="btn btn-sm btn-edit"><i class="fas fa-edit"></i></a>
                        <button onclick="deleteReport(<?php echo $report['id']; ?>)" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
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
            <h3><?php echo $edit_report ? 'Edit Annual Report' : 'Upload Annual Report'; ?></h3>
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
                <label>Year *</label>
                <input type="number" name="year" required value="<?php echo $edit_report['year'] ?? date('Y'); ?>" min="1900" max="<?php echo date('Y') + 10; ?>">
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3"><?php echo htmlspecialchars($edit_report['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group col-6">
                    <label>Status</label>
                    <select name="status">
                        <option value="published" <?php echo ($edit_report['status'] ?? 'published') === 'published' ? 'selected' : ''; ?>>Published</option>
                        <option value="draft" <?php echo ($edit_report['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="archived" <?php echo ($edit_report['status'] ?? '') === 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>
                <div class="form-group col-6">
                    <label>Published Date</label>
                    <input type="date" name="published_date" value="<?php echo $edit_report['published_date'] ?? date('Y-m-d'); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>PDF File <?php echo $edit_report ? '(leave empty to keep current)' : '*'; ?></label>
                <input type="file" name="pdf_file" accept=".pdf" <?php echo $edit_report ? '' : 'required'; ?>>
                <small>Allowed format: PDF. Max size: 20MB</small>
            </div>
            
            <?php if ($edit_report && $edit_report['pdf_file']): ?>
                <div class="form-group">
                    <small>Current file: <?php echo htmlspecialchars($edit_report['pdf_file']); ?> (<?php echo round($edit_report['file_size'] / 1024 / 1024, 2); ?> MB)</small>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label>Cover Image (Optional)</label>
                <input type="file" name="cover_image" accept="image/*">
                <small>Recommended size: 800x1000px. Formats: JPG, PNG, WebP</small>
            </div>
            
            <?php if ($edit_report && $edit_report['cover_image']): ?>
                <div class="form-group">
                    <small>Current cover: <?php echo htmlspecialchars($edit_report['cover_image']); ?></small>
                    <br>
                    <img src="../uploads/annual-reports/covers/<?php echo $edit_report['cover_image']; ?>" style="max-width: 200px; margin-top: 10px; border-radius: 8px;">
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
    if (confirm('Are you sure you want to delete this report? This will also delete all associated files.')) {
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