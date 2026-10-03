<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/includes/admin-enhancements.php';
adminTrackActivity($conn);

requirePermission($conn, 'manage_jobs');

$page_title = 'Manage Jobs';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $id = $_POST['id'] ?? 0;
        $title = clean($conn, $_POST['title']);
        $department = clean($conn, $_POST['department'] ?? '');
        $location = clean($conn, $_POST['location'] ?? '');
        $type = clean($conn, $_POST['type']);
        $description = $_POST['description'];
        $requirements = $_POST['requirements'];
        $job_link = $_POST['job_link']??'';
        $responsibilities = $_POST['responsibilities'];
        $salary_range = clean($conn, $_POST['salary_range'] ?? '');
        $application_deadline = $_POST['application_deadline'] ?? null;
        $status = clean($conn, $_POST['status']?? 'open');
        
        if (!empty($job_link) && !filter_var($job_link, FILTER_VALIDATE_URL)) {
        $errors[] = 'Please enter a valid URL for the job link (e.g., https://example.com)';
    }
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE jobs SET title = ?, department = ?, location = ?, type = ?, description = ?, requirements = ?, responsibilities = ?, salary_range = ?, job_link = ?, application_deadline = ?, status = ? WHERE id = ?");
            $stmt->bind_param("sssssssssssi", $title, $department, $location, $type, $description, $requirements, $responsibilities, $salary_range, $job_link, $application_deadline, $status, $id);
            $stmt->execute();
            
            setFlashMessage('success', 'Job updated successfully');
        } else {
            $stmt = $conn->prepare("INSERT INTO jobs (title, department, location, type, description, requirements, responsibilities, salary_range, job_link, application_deadline, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssssssi", $title, $department, $location, $type, $description, $requirements, $responsibilities, $salary_range, $job_link, $application_deadline, $status, $_SESSION['user_id']);
            $stmt->execute();
            
            setFlashMessage('success', 'Job created successfully');
        }
        
        header("Location: jobs.php");
        exit();
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM jobs WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        setFlashMessage('success', 'Job deleted successfully');
        header("Location: jobs.php");
        exit();
    }
}

$edit_job = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_job = $result->fetch_assoc();
}

$jobs = [];
$result = $conn->query("SELECT j.*, u.name as author_name FROM jobs j LEFT JOIN users u ON j.created_by = u.id ORDER BY j.created_at DESC");
while ($row = $result->fetch_assoc()) {
    $jobs[] = $row;
}

include 'layout/header.php';
include 'layout/sidebar.php';
?>

<div class="content-header">
    <h2>Manage Jobs</h2>
    <button class="btn btn-primary" onclick="showModal('jobModal')">Add New Job</button>
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
                    <th>Department</th>
                    <th>Location</th>
                    <th>Type</th>
                    <th>Job Link</th>
                    <th>Status</th>
                    <th>Deadline</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jobs as $job): ?>
                <tr>
                    <td><?php echo htmlspecialchars($job['title']); ?></td>
                    <td><?php echo htmlspecialchars($job['department']); ?></td>
                    <td><?php echo htmlspecialchars($job['location']); ?></td>
                    <td><?php echo ucfirst($job['type']); ?></td>
                    <td><a href="<?php echo htmlspecialchars($job['job_link'] ?? '#'); ?>" target="_blank"><i class="fas fa-link"></i></a></td>
                    <td><span class="badge badge-<?php echo $job['status']; ?>"><?php echo ucfirst($job['status']); ?></span></td>
                    <td><?php echo $job['application_deadline'] ? formatDate($job['application_deadline']) : 'N/A'; ?></td>
                    <td>
                        <a href="?edit=<?php echo $job['id']; ?>" class="btn btn-sm btn-edit"><i class="fas fa-edit"></i></a>
                        <button onclick="deleteJob(<?php echo $job['id']; ?>)" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="jobModal" class="modal" style="display: <?php echo $edit_job ? 'flex' : 'none'; ?>">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3><?php echo $edit_job ? 'Edit Job' : 'Add New Job'; ?></h3>
            <button class="modal-close" onclick="hideModal('jobModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?php echo $edit_job['id'] ?? 0; ?>">
            
            <div class="form-row">
                <div class="form-group col-6">
                    <label>Job Title *</label>
                    <input type="text" name="title" required value="<?php echo htmlspecialchars($edit_job['title'] ?? ''); ?>">
                </div>
                <div class="form-group col-6">
                    <label>Department</label>
                    <input type="text" name="department" value="<?php echo htmlspecialchars($edit_job['department'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-6">
                    <label>Location</label>
                    <input type="text" name="location" value="<?php echo htmlspecialchars($edit_job['location'] ?? ''); ?>">
                </div>
                <div class="form-group col-6">
                    <label>Job Type *</label>
                    <select name="type" required>
                        <option value="full-time" <?php echo ($edit_job['type'] ?? '') === 'full-time' ? 'selected' : ''; ?>>Full-time</option>
                        <option value="part-time" <?php echo ($edit_job['type'] ?? '') === 'part-time' ? 'selected' : ''; ?>>Part-time</option>
                        <option value="contract" <?php echo ($edit_job['type'] ?? '') === 'contract' ? 'selected' : ''; ?>>Contract</option>
                        <option value="internship" <?php echo ($edit_job['type'] ?? '') === 'internship' ? 'selected' : ''; ?>>Internship</option>
                        <option value="volunteer" <?php echo ($edit_job['type'] ?? '') === 'volunteer' ? 'selected' : ''; ?>>Volunteer</option>
               
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-4">
                    <label>Salary Range</label>
                    <input type="text" name="salary_range" value="<?php echo htmlspecialchars($edit_job['salary_range'] ?? ''); ?>" placeholder="e.g. $50,000 - $70,000">
                </div>
                <div class="form-group col-4">
                    <label>Application Deadline</label>
                    <input type="date" name="application_deadline" value="<?php echo $edit_job['application_deadline'] ?? ''; ?>">
                </div>
                <div class="form-group col-4">
                    <label>Status *</label>
                    <select name="status" required>
                        <option value="open" <?php echo ($edit_job['status'] ?? '') === 'open' ? 'selected' : ''; ?>>Open</option>
                        <option value="closed" <?php echo ($edit_job['status'] ?? '') === 'closed' ? 'selected' : ''; ?>>Closed</option>
                        <option value="filled" <?php echo ($edit_job['status'] ?? '') === 'filled' ? 'selected' : ''; ?>>Filled</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="4"><?php echo htmlspecialchars($edit_job['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Requirements</label>
                <textarea name="requirements" rows="4"><?php echo htmlspecialchars($edit_job['requirements'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Responsibilities</label>
                <textarea name="responsibilities" rows="4"><?php echo htmlspecialchars($edit_job['responsibilities'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                        <label>
                            External Application Link 
                            <i class="fas fa-info-circle" title="URL to external job board or application form"></i>
                        </label>
                        <input type="url" 
                               name="job_link" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($edit_job['job_link'] ?? ''); ?>"
                               placeholder="https://careers.yourcompany.com/job/123">
                        <small class="form-text">
                            <strong>Optional:</strong> If provided, the "Apply" button will link here instead of the contact form.
                            <br>Examples: LinkedIn job post, Indeed listing, or your application page.
                        </small>
                    </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="hideModal('jobModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Job</button>
            </div>
        </form>
    </div>
</div>

<script>
function deleteJob(id) {
    if (confirm('Are you sure you want to delete this job?')) {
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