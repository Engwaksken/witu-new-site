<?php

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/core/helpers.php';

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$sort_by = $_GET['sort'] ?? 'latest';

// Build query based on filters
$where_clauses = [];
$params = [];
$types = '';

if ($status_filter !== 'all') {
    $where_clauses[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Determine sorting
$order_by = match($sort_by) {
    'oldest' => 'start_date ASC',
    'name' => 'title ASC',
    default => 'start_date DESC'
};

// Get programs
$programs = [];
$sql = "SELECT * FROM programs $where_sql ORDER BY $order_by";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $programs[] = $row;
    }
}

// Get statistics
$stats = [];
$stats['total'] = count($programs);
$stats['active'] = count(array_filter($programs, fn($p) => $p['status'] === 'active'));
$stats['completed'] = count(array_filter($programs, fn($p) => $p['status'] === 'completed'));
$stats['upcoming'] = count(array_filter($programs, fn($p) => $p['status'] === 'upcoming'));

// Set page variables
$page_title = 'Our Programs';
$meta_description = 'Explore our programs and initiatives that create positive change in communities worldwide.';
$body_class = 'programs-page';

// Include header
include 'header.php';
?>

<!-- Page Header -->
<div class="page-header" style="background: linear-gradient(135deg, #fe6501 0%, #fe6501a8 100%); color: white; padding: 80px 0; text-align: center;">
    <div class="container">
        <h1 style="font-size: 48px; margin-bottom: 15px; font-weight: 700;">Our Programs</h1>
        <p style="font-size: 20px; opacity: 0.95; max-width: 700px; margin: 0 auto 30px;">
            Discover our initiatives that create lasting impact in communities worldwide
        </p>
        
        <!-- Statistics -->
        <div style="display: flex; gap: 30px; justify-content: center; flex-wrap: wrap; margin-top: 30px;">
            <div style="background: rgba(255,255,255,0.15); padding: 15px 30px; border-radius: 25px;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo $stats['total']; ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Total Programs</div>
            </div>
            <div style="background: rgba(255,255,255,0.15); padding: 15px 30px; border-radius: 25px;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo $stats['active']; ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Active Programs</div>
            </div>
            <div style="background: rgba(255,255,255,0.15); padding: 15px 30px; border-radius: 25px;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo $stats['completed']; ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Completed</div>
            </div>
        </div>
    </div>
</div>

<!-- Page Content -->
<div class="page-content" style="padding: 60px 0;">
    <div class="container">
        
        <!-- Filters and Sort -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
            
            <!-- Status Filter -->
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="?status=all&sort=<?php echo $sort_by; ?>" 
                   class="filter-btn <?php echo $status_filter === 'all' ? 'active' : ''; ?>"
                   style="padding: 10px 20px; border-radius: 25px; text-decoration: none; font-size: 14px; font-weight: 600; transition: all 0.2s; <?php echo $status_filter === 'all' ? 'background: #fe6501; color: white;' : 'background: #f3f4f6; color: #6b7280;'; ?>">
                    All Programs
                </a>
                <a href="?status=active&sort=<?php echo $sort_by; ?>" 
                   class="filter-btn <?php echo $status_filter === 'active' ? 'active' : ''; ?>"
                   style="padding: 10px 20px; border-radius: 25px; text-decoration: none; font-size: 14px; font-weight: 600; transition: all 0.2s; <?php echo $status_filter === 'active' ? 'background: #10b981; color: white;' : 'background: #f3f4f6; color: #6b7280;'; ?>">
                    <i class="fas fa-play-circle"></i> Active
                </a>
                <a href="?status=upcoming&sort=<?php echo $sort_by; ?>" 
                   class="filter-btn <?php echo $status_filter === 'upcoming' ? 'active' : ''; ?>"
                   style="padding: 10px 20px; border-radius: 25px; text-decoration: none; font-size: 14px; font-weight: 600; transition: all 0.2s; <?php echo $status_filter === 'upcoming' ? 'background: #f59e0b; color: white;' : 'background: #f3f4f6; color: #6b7280;'; ?>">
                    <i class="fas fa-calendar"></i> Upcoming
                </a>
                <a href="?status=completed&sort=<?php echo $sort_by; ?>" 
                   class="filter-btn <?php echo $status_filter === 'completed' ? 'active' : ''; ?>"
                   style="padding: 10px 20px; border-radius: 25px; text-decoration: none; font-size: 14px; font-weight: 600; transition: all 0.2s; <?php echo $status_filter === 'completed' ? 'background: #8b5cf6; color: white;' : 'background: #f3f4f6; color: #6b7280;'; ?>">
                    <i class="fas fa-check-circle"></i> Completed
                </a>
            </div>
            
            <!-- Sort Dropdown -->
            <div style="display: flex; align-items: center; gap: 10px;">
                <label style="font-size: 14px; color: #6b7280; font-weight: 600;">Sort by:</label>
                <select onchange="window.location.href='?status=<?php echo $status_filter; ?>&sort=' + this.value" 
                        style="padding: 10px 15px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; cursor: pointer; background: white;">
                    <option value="latest" <?php echo $sort_by === 'latest' ? 'selected' : ''; ?>>Latest First</option>
                    <option value="oldest" <?php echo $sort_by === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                    <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>Name (A-Z)</option>
                </select>
            </div>
        </div>
        
        <?php if (empty($programs)): ?>
            <!-- No Programs -->
            <div style="text-align: center; padding: 80px 20px; max-width: 600px; margin: 0 auto;">
                <div style="font-size: 64px; margin-bottom: 20px; opacity: 0.3;">
                    <i class="fas fa-project-diagram"></i>
                </div>
                <h3 style="color: #1f2937; font-size: 28px; margin-bottom: 15px;">No Programs Found</h3>
                <p style="color: #6b7280; font-size: 18px; line-height: 1.6; margin-bottom: 30px;">
                    <?php if ($status_filter !== 'all'): ?>
                        No <?php echo $status_filter; ?> programs at the moment. Try a different filter.
                    <?php else: ?>
                        We're working on new programs. Check back soon!
                    <?php endif; ?>
                </p>
                <?php if ($status_filter !== 'all'): ?>
                <a href="programs.php" class="btn" style="display: inline-block; padding: 12px 30px; background: #fe6501; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">
                    <i class="fas fa-list"></i> View All Programs
                </a>
                <?php endif; ?>
            </div>
            
        <?php else: ?>
            
            <!-- Programs Grid -->
            <div class="programs-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px;">
                
                <?php foreach ($programs as $program): ?>
                <div class="program-card" style="background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border: 1px solid #e5e7eb; transition: all 0.3s; display: flex; flex-direction: column;">
                    
                    <!-- Program Image -->
                    <div style="position: relative; height: 200px; overflow: hidden; background: linear-gradient(135deg, #fe6501 0%, #fe6501a8 100%);">
                        <?php if ($program['featured_image']): ?>
                            <img src="uploads/programs/<?php echo $program['featured_image']; ?>" 
                                 alt="<?php echo htmlspecialchars($program['title']); ?>"
                                 style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: white; font-size: 48px;">
                                <i class="fas fa-project-diagram"></i>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Status Badge -->
                        <?php
                        $status_styles = [
                            'active' => 'background: #10b981; color: white;',
                            'upcoming' => 'background: #f59e0b; color: white;',
                            'completed' => 'background: #8b5cf6; color: white;',
                            'on-hold' => 'background: #6b7280; color: white;'
                        ];
                        $badge_style = $status_styles[$program['status']] ?? 'background: #6b7280; color: white;';
                        ?>
                        <div style="position: absolute; top: 15px; right: 15px; <?php echo $badge_style; ?> padding: 6px 15px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase;">
                            <?php echo htmlspecialchars($program['status']); ?>
                        </div>
                    </div>
                    
                    <!-- Program Content -->
                    <div style="padding: 25px; flex-grow: 1; display: flex; flex-direction: column;">
                        
                        <!-- Title -->
                        <h3 style="color: #1f2937; font-size: 22px; margin-bottom: 12px; line-height: 1.3;">
                            <?php echo htmlspecialchars($program['title']); ?>
                        </h3>
                        
                        <!-- Location & Dates -->
                        <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 2px solid #f3f4f6;">
                            <?php if ($program['location']): ?>
                            <div style="display: flex; align-items: center; gap: 8px; color: #6b7280; font-size: 13px;">
                                <i class="fas fa-map-marker-alt" style="color: #ef4444; width: 14px;"></i>
                                <span><?php echo htmlspecialchars($program['location']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div style="display: flex; align-items: center; gap: 8px; color: #6b7280; font-size: 13px;">
                                <i class="far fa-calendar" style="color:#fe6501; width: 14px;"></i>
                                <span><?php echo formatDate($program['start_date']); ?> 
                                <?php if ($program['end_date']): ?>
                                    - <?php echo formatDate($program['end_date']); ?>
                                <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <div style="margin-bottom: 20px; flex-grow: 1;">
                            <p style="color: #4b5563; font-size: 14px; line-height: 1.7; margin: 0;">
                                <?php echo truncate(strip_tags($program['description']), 120); ?>
                            </p>
                        </div>
                        
                        <!-- View Details Button -->
                        <div style="margin-top: auto;">
                            <a href="#program-<?php echo $program['id']; ?>" 
                               onclick="openProgramModal(<?php echo $program['id']; ?>); return false;" 
                               class="btn-program-details" 
                               style="display: block; text-align: center; padding: 12px 20px; background: #fe6501; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; transition: all 0.2s;">
                                <i class="fas fa-info-circle"></i> View Details
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
            </div>
            
            <!-- Call to Action -->
            <div style="margin-top: 80px; background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%); padding: 60px 40px; border-radius: 20px; text-align: center;">
                <h2 style="color: #1f2937; font-size: 36px; margin-bottom: 15px;">Want to Get Involved?</h2>
                <p style="color: #6b7280; font-size: 18px; margin-bottom: 30px; max-width: 600px; margin-left: auto; margin-right: auto;">
                    Join us in making a difference. Whether through volunteering, partnerships, or donations, your support matters.
                </p>
                <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                    <a href="contact.php" class="btn" style="display: inline-block; padding: 15px 35px; background: #fe6501; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px;">
                        <i class="fas fa-envelope"></i> Contact Us
                    </a>
                    <?php if (isFeatureEnabled($conn, 'donations')): ?>
                    <a href="donate.php" class="btn" style="display: inline-block; padding: 15px 35px; background: #10b981; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px;">
                        <i class="fas fa-heart"></i> Make a Donation
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php endif; ?>
        
    </div>
</div>

<!-- Program Detail Modal -->
<div id="programModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; overflow-y: auto; padding: 40px 20px;">
    <div style="max-width: 900px; margin: 0 auto; background: white; border-radius: 20px; padding: 0; position: relative;">
        
        <!-- Modal Header with Image -->
        <div id="modalHeader" style="position: relative; height: 300px; border-radius: 20px 20px 0 0; overflow: hidden; background: linear-gradient(135deg, #fe6501 0%, #fe6501a8 100%);">
            <!-- Background image will be set by JavaScript -->
            <div style="position: absolute; top: 0; right: 0; padding: 20px;">
                <button onclick="closeProgramModal()" style="background: rgba(0,0,0,0.5); border: none; font-size: 24px; color: white; cursor: pointer; padding: 10px; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: all 0.2s;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(to top, rgba(0,0,0,0.8), transparent); padding: 30px;">
                <h2 id="modalTitle" style="color: white; font-size: 32px; margin: 0;"></h2>
                <div id="modalStatus" style="margin-top: 10px;"></div>
            </div>
        </div>
        
        <!-- Modal Content -->
        <div id="modalContent" style="padding: 40px; max-height: 500px; overflow-y: auto;">
            <!-- Content will be loaded by JavaScript -->
        </div>
        
        <!-- Modal Footer -->
        <div style="padding: 30px; border-top: 2px solid #e5e7eb; background: #f9fafb; border-radius: 0 0 20px 20px;">
            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="contact.php" class="btn" style="display: inline-block; padding: 12px 30px; background: #fe6501; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">
                    <i class="fas fa-info-circle"></i> Learn More
                </a>
                <?php if (isFeatureEnabled($conn, 'donations')): ?>
                <a href="donate.php" class="btn" style="display: inline-block; padding: 12px 30px; background: #10b981; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">
                    <i class="fas fa-heart"></i> Support This Program
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Program Card Hover */
.program-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.15) !important;
}

/* Button Hover */
.btn-program-details:hover {
    background: #1e40af !important;
    transform: scale(1.02);
}

/* Filter Button Hover */
.filter-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* CTA Buttons Hover */
div[style*="Get Involved"] .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

/* Modal Animation */
#programModal {
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

#programModal > div {
    animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
    from {
        transform: translateY(50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Modal Close Button Hover */
#modalHeader button:hover {
    background: rgba(0,0,0,0.7) !important;
    transform: scale(1.1);
}

/* Responsive */
@media (max-width: 1024px) {
    .programs-grid {
        grid-template-columns: repeat(2, 1fr) !important;
    }
}

@media (max-width: 640px) {
    .programs-grid {
        grid-template-columns: 1fr !important;
    }
    
    .page-header h1 {
        font-size: 32px !important;
    }
    
    .page-header p {
        font-size: 16px !important;
    }
    
    div[style*="Statistics"] {
        flex-direction: column !important;
    }
    
    #modalHeader {
        height: 200px !important;
    }
}
</style>

<script>
// Program data for modal
const programs = <?php echo json_encode($programs); ?>;

// Open program modal
function openProgramModal(programId) {
    const program = programs.find(p => p.id == programId);
    if (!program) return;
    
    // Set modal title
    document.getElementById('modalTitle').textContent = program.title;
    
    // Set header background
    const modalHeader = document.getElementById('modalHeader');
    if (program.featured_image) {
        modalHeader.style.backgroundImage = `url('uploads/programs/${program.featured_image}')`;
        modalHeader.style.backgroundSize = 'cover';
        modalHeader.style.backgroundPosition = 'center';
    }
    
    // Set status badge
    const statusColors = {
        'active': 'background: #10b981; color: white;',
        'upcoming': 'background: #f59e0b; color: white;',
        'completed': 'background: #8b5cf6; color: white;',
        'on-hold': 'background: #6b7280; color: white;'
    };
    const statusStyle = statusColors[program.status] || 'background: #6b7280; color: white;';
    document.getElementById('modalStatus').innerHTML = `
        <span style="${statusStyle} padding: 6px 15px; border-radius: 20px; font-size: 13px; font-weight: 600; text-transform: uppercase;">
            ${program.status}
        </span>
    `;
    
    // Build modal content
    let content = '';
    
    // Info boxes
    content += '<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px;">';
    
    // Location
    if (program.location) {
        content += `
        <div style="background: #f3f4f6; padding: 20px; border-radius: 12px;">
            <div style="display: flex; align-items: center; gap: 10px; color: #6b7280; font-size: 13px; margin-bottom: 8px;">
                <i class="fas fa-map-marker-alt" style="color: #ef4444;"></i>
                <span style="font-weight: 600; text-transform: uppercase;">Location</span>
            </div>
            <div style="color: #1f2937; font-size: 16px; font-weight: 600;">${program.location}</div>
        </div>`;
    }
    
    // Duration
    content += `
    <div style="background: #f3f4f6; padding: 20px; border-radius: 12px;">
        <div style="display: flex; align-items: center; gap: 10px; color: #6b7280; font-size: 13px; margin-bottom: 8px;">
            <i class="far fa-calendar" style="color:#fe6501;"></i>
            <span style="font-weight: 600; text-transform: uppercase;">Duration</span>
        </div>
        <div style="color: #1f2937; font-size: 16px; font-weight: 600;">
            ${formatDate(program.start_date)}
            ${program.end_date ? ' - ' + formatDate(program.end_date) : ''}
        </div>
    </div>`;
    
    content += '</div>';
    
    // Description
    if (program.description) {
        content += '<div style="margin-bottom: 30px;">';
        content += '<h4 style="color: #1f2937; font-size: 20px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px;"><i class="fas fa-align-left" style="color: #fe6501;"></i> Description</h4>';
        content += `<p style="color: #4b5563; line-height: 1.8; white-space: pre-line;">${program.description}</p>`;
        content += '</div>';
    }
    
    // Goals
    if (program.goals) {
        content += '<div style="margin-bottom: 30px;">';
        content += '<h4 style="color: #1f2937; font-size: 20px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px;"><i class="fas fa-bullseye" style="color: #10b981;"></i> Goals & Objectives</h4>';
        content += `<p style="color: #4b5563; line-height: 1.8; white-space: pre-line;">${program.goals}</p>`;
        content += '</div>';
    }
    
    // Impact
    if (program.impact) {
        content += '<div style="margin-bottom: 30px; background: #dbeafe; padding: 20px; border-radius: 12px; border-left: 4px solid #fe6501;">';
        content += '<h4 style="color: #1e40af; font-size: 18px; margin-bottom: 12px; display: flex; align-items: center; gap: 10px;"><i class="fas fa-chart-line"></i> Impact</h4>';
        content += `<p style="color: #1e3a8a; line-height: 1.8; white-space: pre-line; margin: 0;">${program.impact}</p>`;
        content += '</div>';
    }
    
    document.getElementById('modalContent').innerHTML = content;
    
    // Show modal
    document.getElementById('programModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

// Close program modal
function closeProgramModal() {
    document.getElementById('programModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Close modal on outside click
document.getElementById('programModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeProgramModal();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeProgramModal();
    }
});

// Format date helper
function formatDate(dateString) {
    const date = new Date(dateString);
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return date.toLocaleDateString('en-US', options);
}
</script>

<?php include 'footer.php'; ?>