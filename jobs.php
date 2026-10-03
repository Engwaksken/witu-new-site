<?php

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/core/helpers.php';

$jobs = [];
$result = $conn->query("SELECT * FROM jobs WHERE status = 'open' ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $jobs[] = $row;
    }
}

// Set page variables
$page_title = 'Career Opportunities';
$meta_description = 'Join our team and make a difference. View our current job openings and career opportunities.';
$body_class = 'careers-page';

// Include header
include 'header.php';
?>

<!-- Page Header -->
<div class="page-header" style="background: linear-gradient(135deg, #fe6501 0%, #fe6501a8 100%); color: white; padding: 80px 0; text-align: center;">
    <div class="container">
        <h1 style="font-size: 48px; margin-bottom: 15px; font-weight: 700;">Career Opportunities</h1>
        <p style="font-size: 20px; opacity: 0.95; max-width: 600px; margin: 0 auto;">
            Join our team and make a difference in communities worldwide
        </p>
        <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
            <span style="background: rgba(255,255,255,0.2); padding: 8px 20px; border-radius: 25px; font-size: 14px;">
                <i class="fas fa-briefcase"></i> <?php echo count($jobs); ?> Open Position<?php echo count($jobs) !== 1 ? 's' : ''; ?>
            </span>
            <span style="background: rgba(255,255,255,0.2); padding: 8px 20px; border-radius: 25px; font-size: 14px;">
                <i class="fas fa-globe"></i> Multiple Locations
            </span>
            <span style="background: rgba(255,255,255,0.2); padding: 8px 20px; border-radius: 25px; font-size: 14px;">
                <i class="fas fa-heart"></i> Great Benefits
            </span>
        </div>
    </div>
</div>

<!-- Page Content -->
<div class="page-content" style="padding: 60px 0;">
    <div class="container">
        
        <?php if (empty($jobs)): ?>
            <!-- No Jobs Available -->
            <div style="text-align: center; padding: 80px 20px; max-width: 600px; margin: 0 auto;">
                <div style="font-size: 64px; margin-bottom: 20px; opacity: 0.3;">
                    <i class="fas fa-briefcase"></i>
                </div>
                <h3 style="color: #1f2937; font-size: 28px; margin-bottom: 15px;">No Open Positions</h3>
                <p style="color: #6b7280; font-size: 18px; line-height: 1.6; margin-bottom: 30px;">
                    We don't have any open positions at the moment, but we're always looking for talented individuals to join our team.
                </p>
                <a href="contact.php" class="btn" style="display: inline-block; padding: 15px 40px; background: #fe6501; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">
                    <i class="fas fa-envelope"></i> Contact Us About Future Opportunities
                </a>
            </div>
            
        <?php else: ?>
            
            <!-- Jobs Grid (3 columns) -->
            <div class="jobs-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px;">
                
                <?php foreach ($jobs as $job): ?>
                <div class="job-card" style="background: white; border-radius: 15px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border: 1px solid #e5e7eb; transition: all 0.3s; display: flex; flex-direction: column;">
                    
                    <!-- Job Title -->
                    <h3 style="color: #1f2937; font-size: 22px; margin-bottom: 15px; line-height: 1.3;">
                        <?php echo htmlspecialchars($job['title']); ?>
                    </h3>
                    
                    <!-- Job Meta -->
                    <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 2px solid #f3f4f6;">
                        <div style="display: flex; align-items: center; gap: 8px; color: #6b7280; font-size: 14px;">
                            <i class="fas fa-map-marker-alt" style="color: #ef4444; width: 16px;"></i>
                            <span><?php echo htmlspecialchars($job['location']); ?></span>
                        </div>
                        
                        <div style="display: flex; align-items: center; gap: 8px; color: #6b7280; font-size: 14px;">
                            <i class="fas fa-briefcase" style="color: #3b82f6; width: 16px;"></i>
                            <span><?php echo ucfirst(str_replace('-', ' ', $job['type'])); ?></span>
                        </div>
                        
                        <?php if ($job['department']): ?>
                        <div style="display: flex; align-items: center; gap: 8px; color: #6b7280; font-size: 14px;">
                            <i class="fas fa-building" style="color: #8b5cf6; width: 16px;"></i>
                            <span><?php echo htmlspecialchars($job['department']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($job['salary_range']): ?>
                        <div style="display: flex; align-items: center; gap: 8px; color: #10b981; font-size: 14px; font-weight: 600;">
                            <i class="fas fa-dollar-sign" style="width: 16px;"></i>
                            <span><?php echo htmlspecialchars($job['salary_range']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Job Description (truncated) -->
                    <?php if ($job['description']): ?>
                    <div style="margin-bottom: 20px; flex-grow: 1;">
                        <p style="color: #4b5563; font-size: 14px; line-height: 1.7; margin: 0;">
                            <?php echo truncate(strip_tags($job['description']), 150); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Application Deadline -->
                    <?php if ($job['application_deadline']): ?>
                    <div style="background: #fef3c7; padding: 12px; border-radius: 8px; margin-bottom: 20px; border-left: 3px solid #f59e0b;">
                        <div style="display: flex; align-items: center; gap: 8px; color: #92400e; font-size: 13px;">
                            <i class="fas fa-clock"></i>
                            <span><strong>Deadline:</strong> <?php echo formatDate($job['application_deadline']); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- View Details Button -->
                    <div style="margin-top: auto;">
                        <a href="#job-<?php echo $job['id']; ?>" 
                           onclick="openJobModal(<?php echo $job['id']; ?>); return false;" 
                           class="btn-job-details" 
                           style="display: block; text-align: center; padding: 12px 20px; background: #fe6501; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; transition: all 0.2s;">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
                
            </div>
            
            <!-- Why Work With Us Section -->
            <div style="margin-top: 80px; background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%); padding: 60px 40px; border-radius: 20px; text-align: center;">
                <h2 style="color: #1f2937; font-size: 36px; margin-bottom: 40px;">Why Work With Us?</h2>
                
                <div class="benefits-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 40px; max-width: 1000px; margin: 0 auto;">
                    <div>
                        <div style="font-size: 48px; color: #fe6501; margin-bottom: 15px;">
                            <i class="fas fa-heart"></i>
                        </div>
                        <h4 style="color: #1f2937; margin-bottom: 10px; font-size: 18px;">Make an Impact</h4>
                        <p style="color: #6b7280; font-size: 14px; line-height: 1.6;">
                            Work on projects that create real change in communities worldwide
                        </p>
                    </div>
                    
                    <div>
                        <div style="font-size: 48px; color: #10b981; margin-bottom: 15px;">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4 style="color: #1f2937; margin-bottom: 10px; font-size: 18px;">Great Team</h4>
                        <p style="color: #6b7280; font-size: 14px; line-height: 1.6;">
                            Join a diverse team of passionate professionals from around the world
                        </p>
                    </div>
                    
                    <div>
                        <div style="font-size: 48px; color: #8b5cf6; margin-bottom: 15px;">
                            <i class="fas fa-rocket"></i>
                        </div>
                        <h4 style="color: #1f2937; margin-bottom: 10px; font-size: 18px;">Career Growth</h4>
                        <p style="color: #6b7280; font-size: 14px; line-height: 1.6;">
                            Access training, mentorship, and opportunities for advancement
                        </p>
                    </div>
                </div>
            </div>
            
        <?php endif; ?>
        
    </div>
</div>

<!-- Job Detail Modal -->
<div id="jobModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; overflow-y: auto; padding: 40px 20px;">
    <div style="max-width: 800px; margin: 0 auto; background: white; border-radius: 20px; padding: 0; position: relative;">
        <!-- Modal Header -->
        <div style="padding: 30px; border-bottom: 2px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
            <h2 id="modalTitle" style="color: #1f2937; font-size: 28px; margin: 0;"></h2>
            <button onclick="closeJobModal()" style="background: none; border: none; font-size: 28px; color: #9ca3af; cursor: pointer; padding: 0; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: all 0.2s;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- Modal Content -->
        <div id="modalContent" style="padding: 30px; max-height: 600px; overflow-y: auto;">
            <!-- Content will be loaded by JavaScript -->
        </div>
        
        <!-- Modal Footer -->
        <div style="padding: 30px; border-top: 2px solid #e5e7eb; background: #f9fafb; border-radius: 0 0 20px 20px;">
            <a id="modalApplyButton" href="#" class="btn" style="display: block; text-align: center; padding: 15px 30px; background: #fe6501; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px;">
                <i class="fas fa-paper-plane"></i> Apply for This Position
            </a>
        </div>
    </div>
</div>

<style>
/* Job Card Hover Effect */
.job-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.15) !important;
}

.btn-job-details:hover {
    background: #1e40af !important;
    transform: scale(1.02);
}

/* Responsive Grid */
@media (max-width: 1024px) {
    .jobs-grid {
        grid-template-columns: repeat(2, 1fr) !important;
    }
    
    .page-header h1 {
        font-size: 36px !important;
    }
}

@media (max-width: 640px) {
    .jobs-grid {
        grid-template-columns: 1fr !important;
    }
    
    .benefits-grid {
        grid-template-columns: 1fr !important;
    }
    
    .page-header h1 {
        font-size: 32px !important;
    }
    
    .page-header p {
        font-size: 16px !important;
    }
}

/* Modal Animation */
#jobModal {
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

#jobModal > div {
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
#jobModal button:hover {
    background: #f3f4f6 !important;
    color: #1f2937 !important;
}
</style>

<script>
// Job data for modal
const jobs = <?php echo json_encode($jobs); ?>;

// Open job modal
function openJobModal(jobId) {
    const job = jobs.find(j => j.id == jobId);
    if (!job) return;
    
    // Set modal title
    document.getElementById('modalTitle').textContent = job.title;
    
    // Build modal content
    let content = '';
    
    // Meta information
    content += '<div style="display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #e5e7eb;">';
    content += `<span style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; background: #f3f4f6; border-radius: 20px; font-size: 14px; color: #4b5563;"><i class="fas fa-map-marker-alt" style="color: #ef4444;"></i> ${job.location}</span>`;
    content += `<span style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; background: #f3f4f6; border-radius: 20px; font-size: 14px; color: #4b5563;"><i class="fas fa-briefcase" style="color: #3b82f6;"></i> ${job.type.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</span>`;
    if (job.department) {
        content += `<span style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; background: #f3f4f6; border-radius: 20px; font-size: 14px; color: #4b5563;"><i class="fas fa-building" style="color: #8b5cf6;"></i> ${job.department}</span>`;
    }
    if (job.salary_range) {
        content += `<span style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; background: #d1fae5; border-radius: 20px; font-size: 14px; color: #065f46; font-weight: 600;"><i class="fas fa-dollar-sign"></i> ${job.salary_range}</span>`;
    }
    content += '</div>';
    
    // Application deadline
    if (job.application_deadline) {
        content += `<div style="background: #fef3c7; padding: 15px 20px; border-radius: 10px; margin-bottom: 30px; border-left: 4px solid #f59e0b;">`;
        content += `<div style="display: flex; align-items: center; gap: 10px; color: #92400e;"><i class="fas fa-clock"></i><span><strong>Application Deadline:</strong> ${formatDate(job.application_deadline)}</span></div>`;
        content += '</div>';
    }
    
    // Description
    if (job.description) {
        content += '<div style="margin-bottom: 30px;">';
        content += '<h4 style="color: #1f2937; font-size: 18px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px;"><i class="fas fa-info-circle" style="color: #fe6501;"></i> Description</h4>';
        content += `<p style="color: #4b5563; line-height: 1.8; white-space: pre-line;">${job.description}</p>`;
        content += '</div>';
    }
    
    // Responsibilities
    if (job.responsibilities) {
        content += '<div style="margin-bottom: 30px;">';
        content += '<h4 style="color: #1f2937; font-size: 18px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px;"><i class="fas fa-tasks" style="color: #10b981;"></i> Responsibilities</h4>';
        content += `<p style="color: #4b5563; line-height: 1.8; white-space: pre-line;">${job.responsibilities}</p>`;
        content += '</div>';
    }
    
    // Requirements
    if (job.requirements) {
        content += '<div style="margin-bottom: 30px;">';
        content += '<h4 style="color: #1f2937; font-size: 18px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px;"><i class="fas fa-check-circle" style="color: #8b5cf6;"></i> Requirements</h4>';
        content += `<p style="color: #4b5563; line-height: 1.8; white-space: pre-line;">${job.requirements}</p>`;
        content += '</div>';
    }
    
    document.getElementById('modalContent').innerHTML = content;
    
    // Set apply button link
    document.getElementById('modalApplyButton').href = `contact.php?job=${job.id}`;
    
    // Show modal
    document.getElementById('jobModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

// Close job modal
function closeJobModal() {
    document.getElementById('jobModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Close modal on outside click
document.getElementById('jobModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeJobModal();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeJobModal();
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