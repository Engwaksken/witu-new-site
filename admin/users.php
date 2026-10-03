<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/includes/admin-enhancements.php';
adminTrackActivity($conn);

requirePermission($conn, 'manage_users');

$page_title = 'Manage Users';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $id = $_POST['id'] ?? 0;
        $name = clean($conn, $_POST['name']);
        $email = clean($conn, $_POST['email']);
        $role_id = intval($_POST['role_id']);
        $status = clean($conn, $_POST['status']);
        $password = $_POST['password'] ?? '';
        
        if ($id > 0) {
            // Update user
            if ($password) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role_id = ?, status = ?, password = ? WHERE id = ?");
                $stmt->bind_param("ssissi", $name, $email, $role_id, $status, $hashed, $id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role_id = ?, status = ? WHERE id = ?");
                $stmt->bind_param("ssisi", $name, $email, $role_id, $status, $id);
            }
            $stmt->execute();
            setFlashMessage('success', 'User updated successfully');
        } else {
            // Create user
            if ($password) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (name, email, password, role_id, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssis", $name, $email, $hashed, $role_id, $status);
                $stmt->execute();
                setFlashMessage('success', 'User created successfully');
            } else {
                setFlashMessage('error', 'Password is required for new users');
            }
        }
        
        header("Location: users.php");
        exit();
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        if ($id != $_SESSION['user_id']) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            setFlashMessage('success', 'User deleted successfully');
        } else {
            setFlashMessage('error', 'Cannot delete your own account');
        }
        header("Location: users.php");
        exit();
    }
}

$edit_user = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_user = $result->fetch_assoc();
}

// Get all users
$users = [];
$result = $conn->query("SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id ORDER BY u.created_at DESC");
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// Get all roles
$roles = [];
$result = $conn->query("SELECT * FROM roles ORDER BY name");
while ($row = $result->fetch_assoc()) {
    $roles[] = $row;
}

include 'layout/header.php';
include 'layout/sidebar.php';
?>

<div class="content-header">
    <h2>Manage Users</h2>
    <button class="btn btn-primary" onclick="showModal('userModal')">Add New User</button>
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
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['role_name']); ?></td>
                    <td><span class="badge badge-<?php echo $user['status']; ?>"><?php echo ucfirst($user['status']); ?></span></td>
                    <td><?php echo $user['last_login'] ? formatDateTime($user['last_login']) : 'Never'; ?></td>
                    <td>
                        <a href="?edit=<?php echo $user['id']; ?>" class="btn btn-sm btn-edit"><i class="fas fa-edit"></i></a>
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <button onclick="deleteUser(<?php echo $user['id']; ?>)" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="userModal" class="modal" style="display: <?php echo $edit_user ? 'flex' : 'none'; ?>">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php echo $edit_user ? 'Edit User' : 'Add New User'; ?></h3>
            <button class="modal-close" onclick="hideModal('userModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?php echo $edit_user['id'] ?? 0; ?>">
            
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" required value="<?php echo htmlspecialchars($edit_user['name'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" required value="<?php echo htmlspecialchars($edit_user['email'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>Password <?php echo $edit_user ? '(leave blank to keep current)' : '*'; ?></label>
                <input type="password" name="password" <?php echo $edit_user ? '' : 'required'; ?>>
            </div>
            
            <div class="form-row">
                <div class="form-group col-6">
                    <label>Role *</label>
                    <select name="role_id" required>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>" <?php echo ($edit_user['role_id'] ?? 0) == $role['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-6">
                    <label>Status *</label>
                    <select name="status" required>
                        <option value="active" <?php echo ($edit_user['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($edit_user['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn" onclick="hideModal('userModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save User</button>
            </div>
        </form>
    </div>
</div>

<script>
function deleteUser(id) {
    if (confirm('Are you sure you want to delete this user?')) {
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