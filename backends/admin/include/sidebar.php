<?php
if (!isset($user)) {
    require_once '../auth.php';
    $auth = new Auth();
    $user = $auth->getCurrentUser();
}

// Initialize database connection if not already available
if (!isset($conn)) {
    require_once __DIR__ . '/../../database.php';
    $db = Database::getInstance();
    $conn = $db->getConnection();
}

// Function to resolve dashboard paths based on current location
function resolveDashboardPath($target) {
    $current_script = $_SERVER['SCRIPT_NAME'];
    $base_path = '';
    
    // If we're in the lesson admin section
    if (strpos($current_script, '/lesson/admin/') !== false) {
        $base_path = '../../admin/';
    }
    // If we're in the lib section
    else if (strpos($current_script, '/lib/') !== false) {
        $base_path = '../backends/admin/';
    }
    // If we're in the admin section
    else if (strpos($current_script, '/admin/') !== false) {
        $base_path = '';
    }
    
    switch ($target) {
        case 'admin':
            return $base_path . 'dashboard.php';
        case 'lesson':
            return $base_path . '../lesson/admin/dashboard.php';
        case 'learning':
            return $base_path . '../../lib/dashboard.php';
        default:
            return 'dashboard.php';
    }
}

// Create styles directory if it doesn't exist
$stylesDir = __DIR__ . '/styles';
if (!file_exists($stylesDir)) {
    mkdir($stylesDir, 0755, true);
}

// Determine current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar CSS -->
<link rel="stylesheet" href="include/styles/sidebar.css">

<!-- Mobile Toggle Button -->
<button class="btn btn-dark d-md-none sidebar-toggle" type="button" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="sidebar">
    <i class="bi bi-list"></i>
</button>

<!-- Overlay for mobile -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<div class="sidebar" id="sidebar" role="navigation" aria-label="Main navigation">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="logo-container">
            <h3 class="mb-0" title="<?php echo SCHOOL_NAME; ?>"><?php echo SCHOOL_NAME; ?></h3>
            <div class="logo-underline"></div>
        </div>
        <button class="btn-close d-md-none" id="sidebar-close" aria-label="Close navigation menu"></button>
    </div>
    
    <div class="user-info">
        <div class="user-avatar">
            <i class="bi bi-person-circle"></i>
        </div>
        <div class="user-details">
            <p class="welcome-text mb-1">Welcome,</p>
            <h5 title="<?php echo $user['name']; ?>"><?php echo $user['name']; ?></h5>
            <span class="role-badge"><?php echo ucfirst((string)$user['role']); ?></span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <!-- Dashboard Links -->
            <li class="nav-item">
                <a href="#dashboards-menu" class="nav-link has-dropdown">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboards</span>
                    <i class="dropdown-icon bi bi-chevron-down"></i>
                </a>
                <ul class="dropdown-menu" id="dashboards-menu">
                    <li><a href="<?php echo resolveDashboardPath('admin'); ?>" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">Admin Dashboard</a></li>
                    <li><a href="<?php echo resolveDashboardPath('lesson'); ?>">Lesson Dashboard</a></li>
                    <li><a href="<?php echo resolveDashboardPath('learning'); ?>">Library Dashboard</a></li>
                </ul>
            </li>
            
            <!-- Students -->
            <li class="nav-item <?php echo in_array($currentPage, ['students.php', 'student_details.php', 'edit_student.php']) ? 'active' : ''; ?>">
                <a href="#students-menu" class="nav-link has-dropdown <?php echo in_array($currentPage, ['students.php', 'student_details.php', 'edit_student.php']) ? 'open' : ''; ?>">
                    <i class="bi bi-people"></i>
                    <span>Students</span>
                    <i class="dropdown-icon bi bi-chevron-down"></i>
                </a>
                <ul class="dropdown-menu" id="students-menu">
                    <li><a href="students.php" class="<?php echo $currentPage === 'students.php' ? 'active' : ''; ?>">All Students</a></li>
                    <!-- <li><a href="student_details.php" class="<?php echo $currentPage === 'student_details.php' ? 'active' : ''; ?>">Student Details</a></li> -->
                </ul>
            </li>
            
            <!-- Applications -->
            <li class="nav-item <?php echo in_array($currentPage, ['applications.php', 'view_application.php', 'download_application_pdf.php']) ? 'active' : ''; ?>">
                <a href="#applications-menu" class="nav-link has-dropdown <?php echo in_array($currentPage, ['applications.php', 'view_application.php', 'download_application_pdf.php']) ? 'open' : ''; ?>">
                    <i class="bi bi-file-text"></i>
                    <span>Applications</span>
                    <i class="dropdown-icon bi bi-chevron-down"></i>
                </a>
                <ul class="dropdown-menu" id="applications-menu">
                    <li><a href="applications.php" class="<?php echo $currentPage === 'applications.php' ? 'active' : ''; ?>">All Applications</a></li>
                    <!-- <li><a href="view_application.php" class="<?php echo $currentPage === 'view_application.php' ? 'active' : ''; ?>">View Application</a></li>
                    <li><a href="download_application_pdf.php" class="<?php echo $currentPage === 'download_application_pdf.php' ? 'active' : ''; ?>">Download PDF</a></li> -->
                </ul>
            </li>
            
            <!-- Forms -->
            <li class="nav-item <?php echo in_array($currentPage, ['application_form_filed_update.php', 'registration_form_filed_update.php', 'registration_form_management.php']) ? 'active' : ''; ?>">
                <a href="#forms-menu" class="nav-link has-dropdown <?php echo in_array($currentPage, ['application_form_filed_update.php', 'registration_form_filed_update.php', 'registration_form_management.php']) ? 'open' : ''; ?>">
                    <i class="bi bi-list-check"></i>
                    <span>Form Management</span>
                    <i class="dropdown-icon bi bi-chevron-down"></i>
                </a>
                <ul class="dropdown-menu" id="forms-menu">
                    <li><a href="application_form_filed_update.php" class="<?php echo $currentPage === 'application_form_filed_update.php' ? 'active' : ''; ?>">Application Fields</a></li>
                    <li><a href="registration_form_filed_update.php" class="<?php echo $currentPage === 'registration_form_filed_update.php' ? 'active' : ''; ?>">Registration Fields</a></li>
                    <!-- <li><a href="registration_form_management.php" class="<?php echo $currentPage === 'registration_form_management.php' ? 'active' : ''; ?>">Form Management</a></li> -->
                </ul>
            </li>
            
            <!-- Payments -->
            <li class="nav-item <?php echo in_array($currentPage, ['payments.php', 'payment_details.php', 'payment_verification.php', 'update_student_payment.php']) ? 'active' : ''; ?>">
                <a href="#payments-menu" class="nav-link has-dropdown <?php echo in_array($currentPage, ['payments.php', 'payment_details.php', 'payment_verification.php', 'update_student_payment.php']) ? 'open' : ''; ?>">
                    <i class="bi bi-cash"></i>
                    <span>Payments</span>
                    <i class="dropdown-icon bi bi-chevron-down"></i>
                </a>
                <ul class="dropdown-menu" id="payments-menu">
                    <li><a href="payments.php" class="<?php echo $currentPage === 'payments.php' ? 'active' : ''; ?>">All Payments</a></li>
                    <li><a href="payment_details.php" class="<?php echo $currentPage === 'payment_details.php' ? 'active' : ''; ?>">Payment Details</a></li>
                    <li><a href="payment_verification.php" class="<?php echo $currentPage === 'payment_verification.php' ? 'active' : ''; ?>">Verify Payments</a></li>
                    <li><a href="update_student_payment.php" class="<?php echo $currentPage === 'update_student_payment.php' ? 'active' : ''; ?>">Update Student Payment</a></li>
                </ul>
            </li>
            
            <!-- Exams -->
            <li class="nav-item <?php echo in_array($currentPage, ['exams.php', 'exam_details.php', 'edit_exam.php']) ? 'active' : ''; ?>">
                <a href="#exams-menu" class="nav-link has-dropdown <?php echo in_array($currentPage, ['exams.php', 'exam_details.php', 'edit_exam.php']) ? 'open' : ''; ?>">
                    <i class="bi bi-pencil-square"></i>
                    <span>Exams</span>
                    <i class="dropdown-icon bi bi-chevron-down"></i>
                </a>
                <ul class="dropdown-menu" id="exams-menu">
                    <li><a href="exams.php" class="<?php echo $currentPage === 'exams.php' ? 'active' : ''; ?>">All Exams</a></li>
                    <li><a href="exam_details.php" class="<?php echo $currentPage === 'exam_details.php' ? 'active' : ''; ?>">Exam Details</a></li>
                    <li><a href="edit_exam.php" class="<?php echo $currentPage === 'edit_exam.php' ? 'active' : ''; ?>">Edit Exam</a></li>
                    <li><a href="../cbt/view_exam_results.php" class="<?php echo $currentPage === 'view_exam_results.php' ? 'active' : ''; ?>">View Exam Results</a></li>
                </ul>
            </li>
            
            <!-- Staff -->
            <li class="nav-item <?php echo in_array($currentPage, ['manage_teachers.php', 'pending_teachers.php', 'class_teachers.php']) ? 'active' : ''; ?>">
                <a href="#staff-menu" class="nav-link has-dropdown <?php echo in_array($currentPage, ['manage_teachers.php', 'pending_teachers.php', 'class_teachers.php']) ? 'open' : ''; ?>">
                    <i class="bi bi-people-fill"></i>
                    <span>Staff Management</span>
                    <i class="dropdown-icon bi bi-chevron-down"></i>
                </a>
                <ul class="dropdown-menu" id="staff-menu">
                    <li><a href="manage_teachers.php" class="<?php echo $currentPage === 'manage_teachers.php' ? 'active' : ''; ?>">Manage Teachers</a></li>
                    <li>
                        <a href="pending_teachers.php" class="<?php echo $currentPage === 'pending_teachers.php' ? 'active' : ''; ?>">
                            Pending Registrations
                            <?php 
                            // Count pending teacher registrations
                            $pendingCount = 0;
                            $countQuery = "SELECT COUNT(*) as count FROM users WHERE role = 'teacher' AND status = 'pending'";
                            $countResult = $conn->query($countQuery);
                            if ($countResult && $countResult->num_rows > 0) {
                                $pendingCount = $countResult->fetch_assoc()['count'];
                            }
                            if ($pendingCount > 0): 
                            ?>
                            <span class="badge badge-danger"><?php echo $pendingCount; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li><a href="class_teachers.php" class="<?php echo $currentPage === 'class_teachers.php' ? 'active' : ''; ?>">Class Teachers</a></li>
                </ul>
            </li>
            
            <!-- System -->
            <li class="nav-item <?php echo in_array($currentPage, ['users.php', 'settings.php', 'registration_number_settings.php', 'setup_admin.php']) ? 'active' : ''; ?>">
                <a href="#system-menu" class="nav-link has-dropdown <?php echo in_array($currentPage, ['users.php', 'settings.php', 'registration_number_settings.php', 'setup_admin.php']) ? 'open' : ''; ?>">
                    <i class="bi bi-gear"></i>
                    <span>System</span>
                    <i class="dropdown-icon bi bi-chevron-down"></i>
                </a>
                <ul class="dropdown-menu" id="system-menu">
                    <li><a href="users.php" class="<?php echo $currentPage === 'users.php' ? 'active' : ''; ?>">Users</a></li>
                    <li><a href="settings.php" class="<?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>">Settings</a></li>
                    <li><a href="registration_number_settings.php" class="<?php echo $currentPage === 'registration_number_settings.php' ? 'active' : ''; ?>">Registration Numbers</a></li>
                    <li><a href="setup_admin.php" class="<?php echo $currentPage === 'setup_admin.php' ? 'active' : ''; ?>">Setup Admin</a></li>
                </ul>
            </li>
            
            <!-- Logout -->
            <li class="nav-item mt-4">
                <a href="logout.php" class="nav-link text-danger" onclick="return confirm('Are you sure you want to logout?')">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>
</div>

<script>
/**
 * Sidebar Navigation Script
 * Handles sidebar functionality including dropdowns, mobile responsiveness, and smooth scrolling
 */
document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const toggleButton = document.querySelector('.sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const closeButton = document.getElementById('sidebar-close');
    const overlay = document.getElementById('sidebar-overlay');
    const dropdownLinks = document.querySelectorAll('.sidebar-nav .has-dropdown');
    const navLinks = document.querySelectorAll('.sidebar-nav .nav-link:not(.has-dropdown)');
    
    // Toggle sidebar on mobile devices
    function toggleSidebar() {
        const isVisible = sidebar.classList.contains('show');
        
        if (isVisible) {
            hideSidebar();
        } else {
            showSidebar();
        }
    }
    
    // Show sidebar on mobile
    function showSidebar() {
        sidebar.classList.add('show');
        overlay.classList.add('show');
        document.body.classList.add('sidebar-open');
        
        if (toggleButton) {
            toggleButton.setAttribute('aria-expanded', 'true');
        }
    }
    
    // Hide sidebar on mobile
    function hideSidebar() {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        document.body.classList.remove('sidebar-open');
        
        if (toggleButton) {
            toggleButton.setAttribute('aria-expanded', 'false');
        }
    }
    
    // Event Listeners
    if (toggleButton) {
        toggleButton.addEventListener('click', toggleSidebar);
    }
    
    if (closeButton) {
        closeButton.addEventListener('click', hideSidebar);
    }
    
    if (overlay) {
        overlay.addEventListener('click', hideSidebar);
    }
    
    // Handle dropdown toggling
    dropdownLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const parentItem = this.closest('.nav-item');
            
            // Toggle current dropdown
            this.classList.toggle('open');
            parentItem.classList.toggle('active');
            
            // If you want only one dropdown open at a time, uncomment this:
            /*
            dropdownLinks.forEach(otherLink => {
                if (otherLink !== link) {
                    otherLink.classList.remove('open');
                    otherLink.closest('.nav-item').classList.remove('active');
                }
            });
            */
        });
    });
    
    // Close sidebar when clicking regular links on mobile
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth < 768) {
                setTimeout(hideSidebar, 150);
            }
        });
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 768) {
            hideSidebar();
        }
    });
    
    // Scroll to active menu item
    const activeNavItem = document.querySelector('.sidebar-nav .nav-item.active');
    if (activeNavItem) {
        setTimeout(() => {
            activeNavItem.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'nearest'
            });
        }, 300);
    }
});
</script> 