<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/includes/admin-enhancements.php';
adminTrackActivity($conn);

requirePermission($conn, 'manage_settings');

$page_title = 'Site Settings';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_settings') {
        $settings = $_POST['settings'] ?? [];
        
        foreach ($settings as $key => $value) {
            $key = clean($conn, $key);
            $value = clean($conn, $value);
            
            // Check if setting exists
            $stmt = $conn->prepare("SELECT id FROM site_settings WHERE setting_key = ?");
            $stmt->bind_param("s", $key);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing
                $stmt = $conn->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->bind_param("ss", $value, $key);
                $stmt->execute();
            } else {
                // Insert new
                $stmt = $conn->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)");
                $stmt->bind_param("ss", $key, $value);
                $stmt->execute();
            }
        }
        
        setFlashMessage('success', 'Settings updated successfully');
        header("Location: settings.php");
        exit();
    }
}

// Get all settings grouped
$settings_by_group = [];
$result = $conn->query("SELECT * FROM site_settings ORDER BY setting_group, setting_key");

while ($row = $result->fetch_assoc()) {
    $group = $row['setting_group'] ?? 'general';
    if (!isset($settings_by_group[$group])) {
        $settings_by_group[$group] = [];
    }
    $settings_by_group[$group][] = $row;
}

// Define group information
$group_info = [
    'general' => ['icon' => '<i class="fas fa-cog"></i>', 'title' => 'General Settings'],
    'contact' => ['icon' => '<i class="fas fa-envelope"></i>', 'title' => 'Contact Information'],
    'social' => ['icon' => '<i class="fas fa-globe"></i>', 'title' => 'Social Media'],
    'footer' => ['icon' => '<i class="fas fa-file-alt"></i>', 'title' => 'Footer Settings'],
    'appearance' => ['icon' => '<i class="fas fa-palette"></i>', 'title' => 'Appearance'],
    'email' => ['icon' => '<i class="fas fa-envelope"></i>', 'title' => 'Email Configuration'],
    'features' => ['icon' => '<i class="fas fa-tools"></i>', 'title' => 'Features'],
    'analytics' => ['icon' => '<i class="fas fa-chart-line"></i>', 'title' => 'Analytics'],
    'seo' => ['icon' => '<i class="fas fa-search"></i>', 'title' => 'SEO Settings']
];

include 'layout/header.php';
include 'layout/sidebar.php';
?>

<div class="content-header">
    <h2>Site Settings</h2>
    <button type="submit" form="settingsForm" class="btn btn-primary"><i class="fas fa-save"></i> Save All Settings</button>
</div>

<?php 
$flash = getFlashMessage();
if ($flash): 
?>
    <div class="alert alert-<?php echo $flash['type']; ?>">
        <?php echo $flash['message']; ?>
    </div>
<?php endif; ?>

<form id="settingsForm" method="POST">
    <input type="hidden" name="action" value="update_settings">
    
    <!-- Settings Tabs -->
    <div class="settings-tabs">
        <?php 
        $first = true;
        foreach ($settings_by_group as $group => $settings): 
            $info = $group_info[$group] ?? ['icon' => '<i class="fas fa-pin"></i>', 'title' => ucfirst($group)];
        ?>
            <button type="button" 
                    class="tab-button <?php echo $first ? 'active' : ''; ?>" 
                    onclick="switchTab('<?php echo $group; ?>')">
                <?php echo $info['icon']; ?> <?php echo $info['title']; ?>
            </button>
        <?php 
            $first = false;
        endforeach; 
        ?>
    </div>
    
    <!-- Settings Content -->
    <div class="settings-content">
        <?php 
        $first = true;
        foreach ($settings_by_group as $group => $settings): 
            $info = $group_info[$group] ?? ['icon' => '<i class="fas fa-pin"></i>', 'title' => ucfirst($group)];
        ?>
            <div id="tab-<?php echo $group; ?>" class="tab-content <?php echo $first ? 'active' : ''; ?>">
                <div class="card">
                    <h3 style="margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #e5e7eb;">
                        <?php echo $info['icon']; ?> <?php echo $info['title']; ?>
                    </h3>
                    
                    <div style="display: grid; gap: 20px;">
                        <?php foreach ($settings as $setting): ?>
                            <div class="form-group">
                                <label style="display: flex; justify-content: space-between; align-items: center;">
                                    <span>
                                        <strong><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></strong>
                                    </span>
                                    <small style="color: #6b7280;"><?php echo $setting['setting_key']; ?></small>
                                </label>
                                
                                <?php 
                                $type = $setting['setting_type'] ?? 'text';
                                $value = $setting['setting_value'] ?? '';
                                $name = 'settings[' . $setting['setting_key'] . ']';
                                
                                switch ($type):
                                    case 'textarea':
                                ?>
                                    <textarea name="<?php echo $name; ?>" rows="4"><?php echo htmlspecialchars($value); ?></textarea>
                                <?php
                                        break;
                                    case 'checkbox':
                                ?>
                                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                        <input type="hidden" name="<?php echo $name; ?>" value="0">
                                        <input type="checkbox" 
                                               name="<?php echo $name; ?>" 
                                               value="1" 
                                               <?php echo $value == '1' ? 'checked' : ''; ?>
                                               style="width: 20px; height: 20px;">
                                        <span>Enable this feature</span>
                                    </label>
                                <?php
                                        break;
                                    case 'color':
                                ?>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <input type="color" 
                                               name="<?php echo $name; ?>" 
                                               value="<?php echo htmlspecialchars($value ?: '#2563eb'); ?>"
                                               style="width: 60px; height: 40px; border: 1px solid #e5e7eb; border-radius: 5px;">
                                        <input type="text" 
                                               value="<?php echo htmlspecialchars($value ?: '#2563eb'); ?>"
                                               readonly
                                               style="flex: 1;">
                                    </div>
                                <?php
                                        break;
                                    case 'number':
                                ?>
                                    <input type="number" 
                                           name="<?php echo $name; ?>" 
                                           value="<?php echo htmlspecialchars($value); ?>">
                                <?php
                                        break;
                                    case 'email':
                                ?>
                                    <input type="email" 
                                           name="<?php echo $name; ?>" 
                                           value="<?php echo htmlspecialchars($value); ?>"
                                           placeholder="email@example.com">
                                <?php
                                        break;
                                    case 'url':
                                ?>
                                    <input type="url" 
                                           name="<?php echo $name; ?>" 
                                           value="<?php echo htmlspecialchars($value); ?>"
                                           placeholder="https://example.com">
                                <?php
                                        break;
                                    default:
                                ?>
                                    <input type="text" 
                                           name="<?php echo $name; ?>" 
                                           value="<?php echo htmlspecialchars($value); ?>">
                                <?php
                                endswitch;
                                ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php 
            $first = false;
        endforeach; 
        ?>
    </div>
    
    <!-- Save Button at Bottom -->
    <div style="margin-top: 30px; text-align: right;">
        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Save All Settings</button>
    </div>
</form>

<script>
function switchTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById('tab-' + tabName).classList.add('active');
    
    // Add active class to clicked button
    event.target.classList.add('active');
}

// Color picker sync
document.querySelectorAll('input[type="color"]').forEach(colorInput => {
    const textInput = colorInput.nextElementSibling.nextElementSibling;
    
    colorInput.addEventListener('input', function() {
        textInput.value = this.value;
    });
});

// Form change detection
let formChanged = false;
document.getElementById('settingsForm').addEventListener('change', function() {
    formChanged = true;
});

window.addEventListener('beforeunload', function(e) {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        return e.returnValue;
    }
});

document.getElementById('settingsForm').addEventListener('submit', function() {
    formChanged = false;
});
</script>

<style>
/* Settings Tabs */
.settings-tabs {
    display: flex;
    gap: 5px;
    margin-bottom: 0;
    overflow-x: auto;
    background: white;
    padding: 10px 10px 0;
    border-radius: 10px 10px 0 0;
}

.tab-button {
    padding: 12px 20px;
    border: none;
    background: transparent;
    color: #6b7280;
    cursor: pointer;
    font-weight: 500;
    white-space: nowrap;
    border-bottom: 3px solid transparent;
    transition: all 0.2s;
}

.tab-button:hover {
    background: #f9fafb;
    color: #1f2937;
}

.tab-button.active {
    color: #2563eb;
    border-bottom-color: #2563eb;
    background: #eff6ff;
}

/* Settings Content */
.settings-content {
    background: white;
    border-radius: 0 0 10px 10px;
    min-height: 400px;
}

.tab-content {
    display: none;
    padding: 30px;
    animation: fadeIn 0.3s;
}

.tab-content.active {
    display: block;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Form styling improvements */
.form-group label strong {
    color: #1f2937;
    font-size: 15px;
}

.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="url"],
.form-group input[type="number"],
.form-group textarea {
    font-size: 14px;
}

.form-group textarea {
    font-family: inherit;
    resize: vertical;
}

/* Responsive */
@media (max-width: 768px) {
    .settings-tabs {
        flex-wrap: nowrap;
    }
    
    .tab-button {
        font-size: 13px;
        padding: 10px 15px;
    }
    
    .tab-content {
        padding: 20px 15px;
    }
}
</style>

<?php include 'layout/footer.php'; ?>