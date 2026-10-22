<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/includes/admin-enhancements.php';
adminTrackActivity($conn);

requirePermission($conn, 'manage_roles');

$page_title = 'Roles & Permissions';

// Handle AJAX requests for permission CRUD
if (isAjax() && isPost()) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_permission':
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if (empty($name)) {
                    throw new Exception('Permission name is required');
                }
                
                // Check if permission already exists
                $stmt = $conn->prepare("SELECT id FROM permissions WHERE name = ?");
                $stmt->bind_param("s", $name);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception('Permission already exists');
                }
                
                $stmt = $conn->prepare("INSERT INTO permissions (name, description) VALUES (?, ?)");
                $stmt->bind_param("ss", $name, $description);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Permission created successfully']);
                } else {
                    throw new Exception('Failed to create permission');
                }
                break;
                
            case 'update_permission':
                $id = intval($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if (empty($name)) {
                    throw new Exception('Permission name is required');
                }
                
                // Check if permission exists with different ID
                $stmt = $conn->prepare("SELECT id FROM permissions WHERE name = ? AND id != ?");
                $stmt->bind_param("si", $name, $id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception('Permission name already exists');
                }
                
                $stmt = $conn->prepare("UPDATE permissions SET name = ?, description = ? WHERE id = ?");
                $stmt->bind_param("ssi", $name, $description, $id);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Permission updated successfully']);
                } else {
                    throw new Exception('Failed to update permission');
                }
                break;
                
            case 'delete_permission':
                $id = intval($_POST['id'] ?? 0);
                
                // Check if permission is in use
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM role_permissions WHERE permission_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                
                if ($result['count'] > 0) {
                    throw new Exception('Cannot delete permission that is assigned to roles');
                }
                
                $stmt = $conn->prepare("DELETE FROM permissions WHERE id = ?");
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Permission deleted successfully']);
                } else {
                    throw new Exception('Failed to delete permission');
                }
                break;
                
            case 'toggle_role_permission':
                $role_id = intval($_POST['role_id'] ?? 0);
                $permission_id = intval($_POST['permission_id'] ?? 0);
                $enabled = $_POST['enabled'] === 'true';
                
                if ($enabled) {
                    // Add permission to role
                    $stmt = $conn->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                    $stmt->bind_param("ii", $role_id, $permission_id);
                    $stmt->execute();
                } else {
                    // Remove permission from role
                    $stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?");
                    $stmt->bind_param("ii", $role_id, $permission_id);
                    $stmt->execute();
                }
                
                echo json_encode(['success' => true, 'message' => 'Role permission updated']);
                break;
                
            case 'create_role':
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if (empty($name)) {
                    throw new Exception('Role name is required');
                }
                
                // Check if role already exists
                $stmt = $conn->prepare("SELECT id FROM roles WHERE name = ?");
                $stmt->bind_param("s", $name);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception('Role already exists');
                }
                
                $stmt = $conn->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
                $stmt->bind_param("ss", $name, $description);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Role created successfully']);
                } else {
                    throw new Exception('Failed to create role');
                }
                break;
                
            case 'update_role':
                $id = intval($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if (empty($name)) {
                    throw new Exception('Role name is required');
                }
                
                // Check if role exists with different ID
                $stmt = $conn->prepare("SELECT id FROM roles WHERE name = ? AND id != ?");
                $stmt->bind_param("si", $name, $id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception('Role name already exists');
                }
                
                $stmt = $conn->prepare("UPDATE roles SET name = ?, description = ? WHERE id = ?");
                $stmt->bind_param("ssi", $name, $description, $id);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Role updated successfully']);
                } else {
                    throw new Exception('Failed to update role');
                }
                break;
                
            case 'delete_role':
                $id = intval($_POST['id'] ?? 0);
                
                // Prevent deletion of system roles
                $system_roles = ['Administrator', 'Editor', 'Author', 'Viewer'];
                $stmt = $conn->prepare("SELECT name FROM roles WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $role = $stmt->get_result()->fetch_assoc();
                
                if (in_array($role['name'], $system_roles)) {
                    throw new Exception('Cannot delete system role');
                }
                
                // Check if role is assigned to users
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                
                if ($result['count'] > 0) {
                    throw new Exception('Cannot delete role that is assigned to users');
                }
                
                // Delete role permissions first
                $stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                
                // Delete role
                $stmt = $conn->prepare("DELETE FROM roles WHERE id = ?");
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Role deleted successfully']);
                } else {
                    throw new Exception('Failed to delete role');
                }
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Get all roles
$roles = [];
$result = $conn->query("SELECT * FROM roles ORDER BY name");
while ($row = $result->fetch_assoc()) {
    $roles[] = $row;
}

// Get all permissions
$permissions = [];
$result = $conn->query("SELECT * FROM permissions ORDER BY name");
while ($row = $result->fetch_assoc()) {
    $permissions[] = $row;
}

// Get role permissions for each role
$role_permissions = [];
foreach ($roles as $role) {
    $stmt = $conn->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
    $stmt->bind_param("i", $role['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $role_permissions[$role['id']] = [];
    while ($row = $result->fetch_assoc()) {
        $role_permissions[$role['id']][] = $row['permission_id'];
    }
}

include 'layout/header.php';
include 'layout/sidebar.php';
?>

<style>
.permission-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(300px, 100%), 1fr));
    gap: 20px;
    margin-top: 20px;
}

.permission-card {
    padding: 15px;
    background: #f9fafb;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    position: relative;
    transition: all 0.2s;
}

.permission-card:hover {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.permission-card h4 {
    margin-bottom: 5px;
    color: #f86505;
    font-size: 16px;
}

.permission-card p {
    margin: 0;
    color: #6b7280;
    font-size: 14px;
}

.permission-actions {
    position: absolute;
    top: 10px;
    right: 10px;
    display: flex;
    gap: 5px;
}

.permission-actions button {
    padding: 5px 10px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s;
}

.btn-edit {
    background:	#ffcc00;
    color: white;
}

.btn-edit:hover {
    background: #f86505;
}

.btn-delete {
    background: #ef4444;
    color: white;
}

.btn-delete:hover {
    background: #dc2626;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    animation: fadeIn 0.2s;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background-color: #fefefe;
    margin: 10% auto;
    padding: 30px;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    animation: slideDown 0.3s;
}

@keyframes slideDown {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e5e7eb;
}

.modal-header h3 {
    margin: 0;
    color: #1f2937;
}

.close {
    color: #9ca3af;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    transition: color 0.2s;
}

.close:hover {
    color: #1f2937;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.2s;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #3b82f6;
}

.form-group textarea {
    resize: vertical;
    min-height: 80px;
}

.modal-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 25px;
}

.alert {
    padding: 12px 15px;
    border-radius: 6px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background-color: #d1fae5;
    color: #065f46;
    border: 1px solid #10b981;
}

.alert-error {
    background-color: #fee2e2;
    color: #991b1b;
    border: 1px solid #ef4444;
}

.role-matrix {
    overflow-x: auto;
    margin-top: 20px;
}

.role-matrix table {
    width: 100%;
    border-collapse: collapse;
}

.role-matrix th,
.role-matrix td {
    padding: 12px;
    text-align: left;
    border: 1px solid #e5e7eb;
}

.role-matrix th {
    background: #f3f4f6;
    font-weight: 600;
}

.role-matrix td {
    text-align: center;
}

.permission-toggle {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.action-buttons {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.role-card {
    padding: 15px;
    background: white;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    margin-bottom: 15px;
}

.role-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.role-card h4 {
    margin: 0;
    color: #1f2937;
}

.role-actions {
    display: flex;
    gap: 5px;
}
</style>

<div class="content-header">
    <h2>Roles & Permissions Management</h2>
</div>

<div id="alertContainer"></div>

<!-- Permissions Section -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3>Permissions</h3>
        <a href="permissions.php" class="btn btn-primary" >
            <i class="fas fa-shield-alt"></i> Add Permission
        </a>
    </div>
    
    <div class="permission-grid" id="permissionGrid">
        <?php foreach ($permissions as $permission): ?>
        <div class="permission-card" data-permission-id="<?php echo $permission['id']; ?>">
            <div class="permission-actions">
                <button class="btn-edit" onclick="editPermission(<?php echo $permission['id']; ?>, '<?php echo addslashes($permission['name']); ?>', '<?php echo addslashes($permission['description']); ?>')">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-delete" onclick="deletePermission(<?php echo $permission['id']; ?>, '<?php echo addslashes($permission['name']); ?>')">
                    <i class="fas fa-trash"></i> 
                </button>
            </div>
            <h4><?php echo htmlspecialchars($permission['name']); ?></h4>
            <p><?php echo htmlspecialchars($permission['description']); ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Roles Section -->
<div class="card" style="margin-top: 30px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3>Roles</h3>
        <button class="btn btn-primary" onclick="openRoleModal()">
            <i class="fas fa-plus"></i> Add Role
        </button>
    </div>
    
    <div id="rolesContainer">
        <?php foreach ($roles as $role): ?>
        <div class="role-card" data-role-id="<?php echo $role['id']; ?>">
            <div class="role-card-header">
                <h4><?php echo htmlspecialchars($role['name']); ?></h4>
                <div class="role-actions">
                    <button class="btn btn-sm btn-primary" onclick="editRole(<?php echo $role['id']; ?>, '<?php echo addslashes($role['name']); ?>', '<?php echo addslashes($role['description']); ?>')">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <?php if (!in_array($role['name'], ['Administrator', 'Editor', 'Author', 'Viewer'])): ?>
                    <button class="btn btn-sm btn-danger" onclick="deleteRole(<?php echo $role['id']; ?>, '<?php echo addslashes($role['name']); ?>')">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <p style="color: #6b7280; margin-bottom: 15px;"><?php echo htmlspecialchars($role['description']); ?></p>
            <div>
                <strong style="display: block; margin-bottom: 10px;">Permissions:</strong>
                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                    <?php foreach ($permissions as $perm): ?>
                    <label style="display: flex; align-items: center; gap: 5px; font-size: 14px;">
                        <input type="checkbox" 
                               class="permission-toggle" 
                               data-role-id="<?php echo $role['id']; ?>" 
                               data-permission-id="<?php echo $perm['id']; ?>"
                               <?php echo in_array($perm['id'], $role_permissions[$role['id']] ?? []) ? 'checked' : ''; ?>
                               onchange="toggleRolePermission(this)">
                        <?php echo htmlspecialchars($perm['name']); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>


<!-- Role Modal -->
<div id="roleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="roleModalTitle">Add Role</h3>
            <span class="close" onclick="closeRoleModal()">&times;</span>
        </div>
        <form id="roleForm">
            <input type="hidden" id="roleId" name="id">
            <div class="form-group">
                <label for="roleName">Role Name *</label>
                <input type="text" id="roleName" name="name" required placeholder="e.g., Content Manager">
            </div>
            <div class="form-group">
                <label for="roleDescription">Description</label>
                <textarea id="roleDescription" name="description" placeholder="Describe this role..."></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeRoleModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Role</button>
            </div>
        </form>
    </div>
</div>

<script>
// Permission Modal Functions
function openPermissionModal(id = null, name = '', description = '') {
    document.getElementById('permissionModal').style.display = 'block';
    document.getElementById('permissionId').value = id || '';
    document.getElementById('permissionName').value = name;
    document.getElementById('permissionDescription').value = description;
    document.getElementById('permissionModalTitle').textContent = id ? 'Edit Permission' : 'Add Permission';
}

function closePermissionModal() {
    document.getElementById('permissionModal').style.display = 'none';
    document.getElementById('permissionForm').reset();
}

function editPermission(id, name, description) {
    openPermissionModal(id, name, description);
}

// Role Modal Functions
function openRoleModal(id = null, name = '', description = '') {
    document.getElementById('roleModal').style.display = 'block';
    document.getElementById('roleId').value = id || '';
    document.getElementById('roleName').value = name;
    document.getElementById('roleDescription').value = description;
    document.getElementById('roleModalTitle').textContent = id ? 'Edit Role' : 'Add Role';
}

function closeRoleModal() {
    document.getElementById('roleModal').style.display = 'none';
    document.getElementById('roleForm').reset();
}

function editRole(id, name, description) {
    openRoleModal(id, name, description);
}

// Alert Functions
function showAlert(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    
    const container = document.getElementById('alertContainer');
    container.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Permission Form Submission
document.getElementById('permissionForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const id = document.getElementById('permissionId').value;
    formData.append('action', id ? 'update_permission' : 'create_permission');
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            closePermissionModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        showAlert('An error occurred. Please try again.', 'error');
    });
});

// Role Form Submission
document.getElementById('roleForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const id = document.getElementById('roleId').value;
    formData.append('action', id ? 'update_role' : 'create_role');
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            closeRoleModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        showAlert('An error occurred. Please try again.', 'error');
    });
});

// Delete Permission
function deletePermission(id, name) {
    if (!confirm(`Are you sure you want to delete the permission "${name}"?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_permission');
    formData.append('id', id);
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        showAlert('An error occurred. Please try again.', 'error');
    });
}

// Delete Role
function deleteRole(id, name) {
    if (!confirm(`Are you sure you want to delete the role "${name}"?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_role');
    formData.append('id', id);
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        showAlert('An error occurred. Please try again.', 'error');
    });
}

// Toggle Role Permission
function toggleRolePermission(checkbox) {
    const roleId = checkbox.dataset.roleId;
    const permissionId = checkbox.dataset.permissionId;
    const enabled = checkbox.checked;
    
    const formData = new FormData();
    formData.append('action', 'toggle_role_permission');
    formData.append('role_id', roleId);
    formData.append('permission_id', permissionId);
    formData.append('enabled', enabled);
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showAlert(data.message, 'error');
            checkbox.checked = !enabled; // Revert checkbox
        }
    })
    .catch(error => {
        showAlert('An error occurred. Please try again.', 'error');
        checkbox.checked = !enabled; // Revert checkbox
    });
}

// Close modals when clicking outside
window.onclick = function(event) {
    const permissionModal = document.getElementById('permissionModal');
    const roleModal = document.getElementById('roleModal');
    
    if (event.target === permissionModal) {
        closePermissionModal();
    }
    if (event.target === roleModal) {
        closeRoleModal();
    }
}
</script>

<?php include 'layout/footer.php'; ?>