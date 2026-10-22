
// Sidebar: collapses in place on desktop (remembered), slides over the page on
// tablets and phones (never remembered, so it can't stay hidden by accident).
const SIDEBAR_BREAKPOINT = 1024;

function isMobileSidebar() {
    return window.innerWidth <= SIDEBAR_BREAKPOINT;
}

function setMobileSidebar(open) {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggle = document.querySelector('.sidebar-toggle');
    if (!sidebar) return;
    sidebar.classList.toggle('show', open);
    if (overlay) overlay.hidden = !open;
    document.body.classList.toggle('sidebar-open', open);
    if (toggle) toggle.setAttribute('aria-expanded', String(open));
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    if (isMobileSidebar()) {
        setMobileSidebar(!sidebar.classList.contains('show'));
        return;
    }
    const collapsed = document.documentElement.classList.toggle('sidebar-collapsed');
    try { localStorage.setItem('adminSidebarCollapsed', String(collapsed)); } catch (e) {}
}

document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.querySelector('.sidebar-toggle');
    const overlay = document.getElementById('sidebarOverlay');
    const sidebar = document.getElementById('sidebar');

    if (toggle) toggle.addEventListener('click', toggleSidebar);
    if (overlay) overlay.addEventListener('click', function() { setMobileSidebar(false); });

    // Following a link from the off-canvas menu closes it.
    if (sidebar) sidebar.addEventListener('click', function(e) {
        if (isMobileSidebar() && e.target.closest('a[href]:not(.nav-dropdown-toggle)')) setMobileSidebar(false);
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('show')) setMobileSidebar(false);
    });

    // Reset the off-canvas state when the window grows back to desktop width.
    window.addEventListener('resize', function() {
        if (!isMobileSidebar() && sidebar && sidebar.classList.contains('show')) setMobileSidebar(false);
    });
});

// Navigation Dropdown Toggle
document.addEventListener('DOMContentLoaded', function() {
    // Handle dropdown toggles
    const dropdownToggles = document.querySelectorAll('.nav-dropdown-toggle');
    
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const dropdown = this.closest('.nav-dropdown');
            
            // Close other dropdowns
            document.querySelectorAll('.nav-dropdown').forEach(d => {
                if (d !== dropdown) {
                    d.classList.remove('open');
                }
            });
            
            // Toggle current dropdown
            dropdown.classList.toggle('open');
        });
    });
    
    // Auto-open dropdown if active item is inside
    const activeDropdownItem = document.querySelector('.nav-dropdown-item.active');
    if (activeDropdownItem) {
        const parentDropdown = activeDropdownItem.closest('.nav-dropdown');
        if (parentDropdown) {
            parentDropdown.classList.add('open');
        }
    }
    
    // Also auto-open if nav-item inside dropdown is active
    const activeNavItem = document.querySelector('.nav-dropdown .nav-item.active');
    if (activeNavItem) {
        const parentDropdown = activeNavItem.closest('.nav-dropdown');
        if (parentDropdown) {
            parentDropdown.classList.add('open');
        }
    }
});

// Show modal
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

// Hide modal
function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Auto-hide alerts
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
});

// Confirm delete actions
function confirmDelete(message) {
    return confirm(message || 'Are you sure you want to delete this item?');
}

// Form validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;
    
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.style.borderColor = '#ef4444';
            isValid = false;
        } else {
            field.style.borderColor = '#e5e7eb';
        }
    });
    
    return isValid;
}

// Image preview
function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const preview = document.getElementById(previewId);
            if (preview) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Character counter for textareas
function addCharacterCounter(textareaId, maxLength) {
    const textarea = document.getElementById(textareaId);
    if (!textarea) return;
    
    const counter = document.createElement('small');
    counter.style.color = '#6b7280';
    counter.style.float = 'right';
    
    textarea.parentNode.appendChild(counter);
    
    function updateCounter() {
        const length = textarea.value.length;
        counter.textContent = `${length}/${maxLength} characters`;
        
        if (length > maxLength) {
            counter.style.color = '#ef4444';
        } else {
            counter.style.color = '#6b7280';
        }
    }
    
    textarea.addEventListener('input', updateCounter);
    updateCounter();
}

// Sortable table rows (for reordering)
function makeSortable(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const tbody = table.querySelector('tbody');
    let draggedRow = null;
    
    Array.from(tbody.rows).forEach(row => {
        row.draggable = true;
        
        row.addEventListener('dragstart', function() {
            draggedRow = this;
            this.style.opacity = '0.5';
        });
        
        row.addEventListener('dragend', function() {
            this.style.opacity = '1';
        });
        
        row.addEventListener('dragover', function(e) {
            e.preventDefault();
        });
        
        row.addEventListener('drop', function(e) {
            e.preventDefault();
            if (this !== draggedRow) {
                const allRows = Array.from(tbody.rows);
                const draggedIndex = allRows.indexOf(draggedRow);
                const droppedIndex = allRows.indexOf(this);
                
                if (draggedIndex < droppedIndex) {
                    this.after(draggedRow);
                } else {
                    this.before(draggedRow);
                }
            }
        });
    });
}

// AJAX helper function
async function sendAjaxRequest(url, data, method = 'POST') {
    try {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            }
        };
        
        if (method !== 'GET') {
            options.body = JSON.stringify(data);
        }
        
        const response = await fetch(url, options);
        const result = await response.json();
        
        return result;
    } catch (error) {
        console.error('AJAX Error:', error);
        return { success: false, message: 'An error occurred' };
    }
}

// Show loading spinner
function showLoader() {
    const loader = document.createElement('div');
    loader.id = 'loader';
    loader.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    `;
    loader.innerHTML = '<div style="border: 4px solid #f3f3f3; border-top: 4px solid #fd6501; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite;"></div>';
    
    const style = document.createElement('style');
    style.textContent = '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
    document.head.appendChild(style);
    
    document.body.appendChild(loader);
}

function hideLoader() {
    const loader = document.getElementById('loader');
    if (loader) {
        loader.remove();
    }
}

// Toast notification
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type}`;
    toast.style.cssText = `
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        animation: slideIn 0.3s ease;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    `;
    toast.textContent = message;
    
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    `;
    document.head.appendChild(style);
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.5s';
        setTimeout(() => toast.remove(), 500);
    }, 3000);
}

// Copy to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Copied to clipboard!', 'success');
    }).catch(() => {
        showToast('Failed to copy', 'error');
    });
}

// Search/filter table
function filterTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    
    if (!input || !table) return;
    
    input.addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });
}

// Initialize tooltips (simple version)
function initTooltips() {
    document.querySelectorAll('[data-tooltip]').forEach(element => {
        element.style.position = 'relative';
        
        element.addEventListener('mouseenter', function() {
            const tooltip = document.createElement('div');
            tooltip.className = 'custom-tooltip';
            tooltip.textContent = this.dataset.tooltip;
            tooltip.style.cssText = `
                position: absolute;
                bottom: 100%;
                left: 50%;
                transform: translateX(-50%);
                background: #1f2937;
                color: white;
                padding: 5px 10px;
                border-radius: 4px;
                font-size: 12px;
                white-space: nowrap;
                z-index: 1000;
                margin-bottom: 5px;
            `;
            this.appendChild(tooltip);
        });
        
        element.addEventListener('mouseleave', function() {
            const tooltip = this.querySelector('.custom-tooltip');
            if (tooltip) tooltip.remove();
        });
    });
}

// Toggle dropdown menu (for user menu, etc)
function toggleDropdown(dropdownId) {
    const dropdown = document.getElementById(dropdownId);
    if (dropdown) {
        dropdown.classList.toggle('show');
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdowns = document.querySelectorAll('.dropdown-menu');
    dropdowns.forEach(dropdown => {
        if (!dropdown.contains(event.target) && !event.target.matches('.dropdown-toggle')) {
            dropdown.classList.remove('show');
        }
    });
});

// Batch actions for tables
function toggleSelectAll(checkboxId, tableId) {
    const checkbox = document.getElementById(checkboxId);
    const table = document.getElementById(tableId);
    
    if (checkbox && table) {
        const checkboxes = table.querySelectorAll('tbody input[type="checkbox"]');
        checkboxes.forEach(cb => {
            cb.checked = checkbox.checked;
        });
        updateBatchActions(tableId);
    }
}

function updateBatchActions(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const checkedBoxes = table.querySelectorAll('tbody input[type="checkbox"]:checked');
    const batchActions = document.getElementById('batch-actions');
    
    if (batchActions) {
        if (checkedBoxes.length > 0) {
            batchActions.style.display = 'block';
            batchActions.querySelector('.selected-count').textContent = checkedBoxes.length;
        } else {
            batchActions.style.display = 'none';
        }
    }
}

// Slug generator from title
function generateSlug(titleInputId, slugInputId) {
    const titleInput = document.getElementById(titleInputId);
    const slugInput = document.getElementById(slugInputId);
    
    if (titleInput && slugInput) {
        titleInput.addEventListener('input', function() {
            const slug = this.value
                .toLowerCase()
                .trim()
                .replace(/[^\w\s-]/g, '')
                .replace(/[\s_-]+/g, '-')
                .replace(/^-+|-+$/g, '');
            slugInput.value = slug;
        });
    }
}

// Confirm action with custom message
function confirmAction(message, callback) {
    if (confirm(message)) {
        if (typeof callback === 'function') {
            callback();
        }
        return true;
    }
    return false;
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initTooltips();
    
    // Add smooth scroll behavior
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            if (targetId !== '#') {
                e.preventDefault();
                const target = document.querySelector(targetId);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });
    });
    
    // Initialize character counters if data attribute exists
    document.querySelectorAll('[data-max-length]').forEach(textarea => {
        const maxLength = parseInt(textarea.dataset.maxLength);
        addCharacterCounter(textarea.id, maxLength);
    });
    
    // Initialize slug generators if data attribute exists
    document.querySelectorAll('[data-slug-source]').forEach(slugInput => {
        const sourceId = slugInput.dataset.slugSource;
        generateSlug(sourceId, slugInput.id);
    });
});

// Prevent double form submission
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('[type="submit"]');
            if (submitBtn) {
                submitBtn.dataset.originalText = submitBtn.dataset.originalText || submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                
                // Re-enable after 3 seconds as fallback
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = submitBtn.dataset.originalText || 'Submit';
                }, 3000);
            }
        });
    });
});

// Local storage helper
const Storage = {
    set: function(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch (e) {
            console.error('Storage error:', e);
            return false;
        }
    },
    
    get: function(key, defaultValue = null) {
        try {
            const item = localStorage.getItem(key);
            return item ? JSON.parse(item) : defaultValue;
        } catch (e) {
            console.error('Storage error:', e);
            return defaultValue;
        }
    },
    
    remove: function(key) {
        try {
            localStorage.removeItem(key);
            return true;
        } catch (e) {
            console.error('Storage error:', e);
            return false;
        }
    }
};

// Export functions for use in other scripts
window.AdminJS = {
    toggleSidebar,
    showModal,
    hideModal,
    confirmDelete,
    validateForm,
    previewImage,
    addCharacterCounter,
    makeSortable,
    sendAjaxRequest,
    showLoader,
    hideLoader,
    showToast,
    copyToClipboard,
    filterTable,
    toggleDropdown,
    toggleSelectAll,
    updateBatchActions,
    generateSlug,
    confirmAction,
    Storage
};