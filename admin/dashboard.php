<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/includes/admin-enhancements.php';
adminTrackActivity($conn);

requireLogin();

$page_title = 'Dashboard';

// Get statistics
$stats = [];

// Total pages
$result = $conn->query("SELECT COUNT(*) as count FROM pages");
$stats['pages'] = $result->fetch_assoc()['count'];

// Total programs
$result = $conn->query("SELECT COUNT(*) as count FROM programs");
$stats['programs'] = $result->fetch_assoc()['count'];

// Total jobs
$result = $conn->query("SELECT COUNT(*) as count FROM jobs WHERE status = 'open'");
$stats['jobs'] = $result->fetch_assoc()['count'];

// Total team members
$result = $conn->query("SELECT COUNT(*) as count FROM team WHERE status = 'active'");
$stats['team'] = $result->fetch_assoc()['count'];

// Recent pages
$recent_pages = [];
$result = $conn->query("SELECT * FROM pages ORDER BY created_at DESC LIMIT 5");
while ($row = $result->fetch_assoc()) {
    $recent_pages[] = $row;
}

// Recent contact submissions
$recent_contacts = [];
$result = $conn->query("SELECT * FROM contact_submissions ORDER BY created_at DESC LIMIT 5");
while ($row = $result->fetch_assoc()) {
    $recent_contacts[] = $row;
}

include 'layout/header.php';
include 'layout/sidebar.php';
?>

<div class="dashboard">
    <h2>Dashboard</h2>
    
    <?php 
    $flash = getFlashMessage();
    if ($flash): 
    ?>
        <div class="alert alert-<?php echo $flash['type']; ?>">
            <?php echo $flash['message']; ?>
        </div>
    <?php endif; ?>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?php echo $stats['pages']; ?></div>
                <div class="stat-label">Total Pages</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?php echo $stats['programs']; ?></div>
                <div class="stat-label">Programs</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-briefcase"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?php echo $stats['jobs']; ?></div>
                <div class="stat-label">Open Jobs</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?php echo $stats['team']; ?></div>
                <div class="stat-label">Team Members</div>
            </div>
        </div>
    </div>
    
    <div class="dashboard-grid">
        <div class="dashboard-section">
            <h3>Recent Pages</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_pages as $page): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($page['title']); ?></td>
                            <td><span class="badge badge-<?php echo $page['status']; ?>"><?php echo ucfirst($page['status']); ?></span></td>
                            <td><?php echo formatDate($page['created_at']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="dashboard-section">
            <h3>Recent Contact Submissions</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Subject</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_contacts as $contact): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($contact['name']); ?></td>
                            <td><?php echo truncate($contact['subject'] ?? 'No subject', 30); ?></td>
                            <td><?php echo formatDate($contact['created_at']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'layout/footer.php'; ?>