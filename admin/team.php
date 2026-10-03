<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/includes/admin-enhancements.php';
adminTrackActivity($conn);

requirePermission($conn, 'manage_team');

$page_title = 'Manage Team';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $id = $_POST['id'] ?? 0;
        $name = clean($conn, $_POST['name']);
        $position = clean($conn, $_POST['position'] ?? '');
        $level = clean($conn, $_POST['level'] ?? 'staff');
        $bio = clean($conn, $_POST['bio'] ?? '');
        $email = clean($conn, $_POST['email'] ?? '');
        $phone = clean($conn, $_POST['phone'] ?? '');
        $linkedin_url = clean($conn, $_POST['linkedin_url'] ?? '');
        $twitter_url = clean($conn, $_POST['twitter_url'] ?? '');
        $display_order = intval($_POST['display_order'] ?? 0);
        $status = clean($conn, $_POST['status']);
        
        $photo = '';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
            $upload_result = uploadFile($_FILES['photo'], 'team');
            if ($upload_result['success']) {
                $photo = $upload_result['filename'];
            }
        }
        
        if ($id > 0) {
            $sql = "UPDATE team SET name = ?, position = ?, level = ?, bio = ?, email = ?, phone = ?, linkedin_url = ?, twitter_url = ?, display_order = ?, status = ?";
            $params = [$name, $position, $level, $bio, $email, $phone, $linkedin_url, $twitter_url, $display_order, $status];
            $types = "ssssssssss";
            
            if ($photo) {
                $sql .= ", photo = ?";
                $params[] = $photo;
                $types .= "s";
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $id;
            $types .= "i";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            
            setFlashMessage('success', 'Team member updated successfully');
        } else {
            $stmt = $conn->prepare("INSERT INTO team (name, position, level, bio, photo, email, phone, linkedin_url, twitter_url, display_order, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssssss", $name, $position, $level, $bio, $photo, $email, $phone, $linkedin_url, $twitter_url, $display_order, $status);
            $stmt->execute();
            
            setFlashMessage('success', 'Team member added successfully');
        }
        
        header("Location: team.php");
        exit();
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM team WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        setFlashMessage('success', 'Team member deleted successfully');
        header("Location: team.php");
        exit();
    }
}

$edit_member = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM team WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_member = $result->fetch_assoc();
}

$team = [];
$result = $conn->query("SELECT * FROM team ORDER BY display_order ASC, name ASC");
while ($row = $result->fetch_assoc()) {
    $team[] = $row;
}

include 'layout/header.php';
include 'layout/sidebar.php';
?>

<div class="content-header">
    <h2>Manage Team</h2>
    <button class="btn btn-primary" onclick="showModal('teamModal')">Add Team Member</button>
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
                    <th>Photo</th>
                    <th>Name</th>
                    <th>Position</th>
                    <th>Level</th>
                    <th>Email</th>
                    <th>Order</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($team as $member): ?>
                <tr>
                    <td>
                        <?php if ($member['photo']): ?>
                            <img src="../uploads/team/<?php echo $member['photo']; ?>" alt="<?php echo htmlspecialchars($member['name']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 50%;">
                        <?php else: ?>
                            <div style="width: 50px; height: 50px; background: #ddd; border-radius: 50%; display: flex; align-items: center; justify-content: center;">👤</div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($member['name']); ?></td>
                    <td><?php echo htmlspecialchars($member['position']); ?></td>
                    <td><span class="badge badge-info"><?php echo ucwords(str_replace('_', ' ', $member['level'])); ?></span></td>
                    <td><?php echo htmlspecialchars($member['email']); ?></td>
                    <td><?php echo $member['display_order']; ?></td>
                    <td><span class="badge badge-<?php echo $member['status']; ?>"><?php echo ucfirst($member['status']); ?></span></td>
                    <td>
                        <a href="?edit=<?php echo $member['id']; ?>" class="btn btn-sm btn-edit"><i class="fas fa-edit"></i></a>
                        <button onclick="deleteMember(<?php echo $member['id']; ?>)" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="teamModal" class="modal" style="display: <?php echo $edit_member ? 'flex' : 'none'; ?>">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3><?php echo $edit_member ? 'Edit Team Member' : 'Add Team Member'; ?></h3>
            <button class="modal-close" onclick="hideModal('teamModal')">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?php echo $edit_member['id'] ?? 0; ?>">
            
            <div class="form-row">
                <div class="form-group col-6">
                    <label>Name *</label>
                    <input type="text" name="name" required value="<?php echo htmlspecialchars($edit_member['name'] ?? ''); ?>">
                </div>
                <div class="form-group col-3">
                    <label>Level *</label>
                    <select name="level" required>
                        <option value="board_directors" <?php echo ($edit_member['level'] ?? '') === 'board_directors' ? 'selected' : ''; ?>>Board of Directors</option>
                        <option value="management_team" <?php echo ($edit_member['level'] ?? '') === 'management_team' ? 'selected' : ''; ?>>Management Team</option>
                        <option value="staff" <?php echo ($edit_member['level'] ?? 'staff') === 'staff' ? 'selected' : ''; ?>>Staff</option>
                    </select>
                </div>
                <div class="form-group col-3">
                    <label>Status *</label>
                    <select name="status" required>
                        <option value="active" <?php echo ($edit_member['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($edit_member['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-6">
                    <label>Position/Title</label>
                    <input type="text" name="position" value="<?php echo htmlspecialchars($edit_member['position'] ?? ''); ?>">
                </div>
                <div class="form-group col-6">
                    <label>Photo</label>
                    <input type="file" name="photo" accept="image/*">
                </div>
            </div>
            
            <div class="form-group">
                <label>Bio</label>
                <textarea name="bio" rows="4"><?php echo htmlspecialchars($edit_member['bio'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group col-6">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($edit_member['email'] ?? ''); ?>">
                </div>
                <div class="form-group col-6">
                    <label>Phone</label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($edit_member['phone'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-4">
                    <label>LinkedIn URL</label>
                    <input type="url" name="linkedin_url" value="<?php echo htmlspecialchars($edit_member['linkedin_url'] ?? ''); ?>">
                </div>
                <div class="form-group col-4">
                    <label>Twitter URL</label>
                    <input type="url" name="twitter_url" value="<?php echo htmlspecialchars($edit_member['twitter_url'] ?? ''); ?>">
                </div>
                <div class="form-group col-4">
                    <label>Display Order</label>
                    <input type="number" name="display_order" value="<?php echo $edit_member['display_order'] ?? 0; ?>">
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn" onclick="hideModal('teamModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Member</button>
            </div>
        </form>
    </div>
</div>

<script>
function deleteMember(id) {
    if (confirm('Are you sure you want to delete this team member?')) {
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