<?php
if (!isset($user)) {
    require_once '../auth.php';
    $auth = new Auth();
    $user = $auth->getCurrentUser();
}
?>
<!-- Mobile Toggle Button -->
<button class="btn btn-dark d-md-none sidebar-toggle" type="button" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="sidebar">
    <i class="bi bi-list"></i>
</button>

<div class="col-md-3 col-lg-2 sidebar p-3" id="sidebar" role="navigation" aria-label="Main navigation">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="logo-container">
            <h3 class="mb-0" title="<?php echo SCHOOL_NAME; ?>"><?php echo SCHOOL_NAME; ?></h3>
            <div class="logo-underline"></div>
        </div>
        <button class="btn-close d-md-none" id="sidebar-close" aria-label="Close navigation menu"></button>
    </div>
    
    <div class="user-info mb-4">
        <div class="user-avatar">
            <i class="bi bi-person-circle"></i>
        </div>
        <div class="user-details">
            <p class="welcome-text mb-1">Welcome,</p>
            <h5 title="<?php echo $user['name']; ?>"><?php echo $user['name']; ?></h5>
            <span class="role-badge"><?php echo ucfirst($user['role']); ?></span>
        </div>
    </div>

    <ul class="nav flex-column" role="list">
        <li class="nav-item" role="listitem">
            <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>" aria-current="<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'page' : 'false'; ?>">
                <i class="bi bi-speedometer2"></i>
                <span class="nav-text">Dashboard</span>
                <span class="hover-indicator"></span>
            </a>
        </li>
        <li class="nav-item" role="listitem">
            <a href="students.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'students.php' ? 'active' : ''; ?>" aria-current="<?php echo basename($_SERVER['PHP_SELF']) === 'students.php' ? 'page' : 'false'; ?>">
                <i class="bi bi-people"></i>
                <span class="nav-text">Students</span>
                <span class="hover-indicator"></span>
            </a>
        </li>
        <li class="nav-item" role="listitem">
            <a href="applications.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'applications.php' ? 'active' : ''; ?>" aria-current="<?php echo basename($_SERVER['PHP_SELF']) === 'applications.php' ? 'page' : 'false'; ?>">
                <i class="bi bi-file-text"></i>
                <span class="nav-text">Applications</span>
                <span class="hover-indicator"></span>
            </a>
        </li>
        <li class="nav-item" role="listitem">
            <a href="application_form_filed_update.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'application_form_filed_update.php' ? 'active' : ''; ?>" aria-current="<?php echo basename($_SERVER['PHP_SELF']) === 'application_form_filed_update.php' ? 'page' : 'false'; ?>">
                <i class="bi bi-list-check"></i>
                <span class="nav-text">Application Fields</span>
                <span class="hover-indicator"></span>
            </a>
        </li>
        <li class="nav-item" role="listitem">
            <a href="registration_form_filed_update.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'registration_form_filed_update.php' ? 'active' : ''; ?>" aria-current="<?php echo basename($_SERVER['PHP_SELF']) === 'registration_form_filed_update.php' ? 'page' : 'false'; ?>">
                <i class="bi bi-card-checklist"></i>
                <span class="nav-text">Registration Fields</span>
                <span class="hover-indicator"></span>
            </a>
        </li>
        <li class="nav-item" role="listitem">
            <a href="payments.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'payments.php' ? 'active' : ''; ?>" aria-current="<?php echo basename($_SERVER['PHP_SELF']) === 'payments.php' ? 'page' : 'false'; ?>">
                <i class="bi bi-cash"></i>
                <span class="nav-text">Payments</span>
                <span class="hover-indicator"></span>
            </a>
        </li>
        <li class="nav-item" role="listitem">
            <a href="exams.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'exams.php' ? 'active' : ''; ?>" aria-current="<?php echo basename($_SERVER['PHP_SELF']) === 'exams.php' ? 'page' : 'false'; ?>">
                <i class="bi bi-pencil-square"></i>
                <span class="nav-text">Exams</span>
                <span class="hover-indicator"></span>
            </a>
        </li>
        <li class="nav-item" role="listitem">
            <a href="users.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>" aria-current="<?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'page' : 'false'; ?>">
                <i class="bi bi-person"></i>
                <span class="nav-text">Users</span>
                <span class="hover-indicator"></span>
            </a>
        </li>
        <li class="nav-item" role="listitem">
            <a href="settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>" aria-current="<?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'page' : 'false'; ?>">
                <i class="bi bi-gear"></i>
                <span class="nav-text">Settings</span>
                <span class="hover-indicator"></span>
            </a>
        </li>
        <li class="nav-item mt-4" role="listitem">
            <a href="logout.php" class="nav-link text-danger" onclick="return confirm('Are you sure you want to logout?')">
                <i class="bi bi-box-arrow-right"></i>
                <span class="nav-text">Logout</span>
                <span class="hover-indicator"></span>
            </a>
        </li>
    </ul>
</div>

<style>
/* Base Sidebar Styles */
.sidebar {
    min-height: 100vh;
    background: linear-gradient(145deg, #2c3338, #343a40);
    color: white;
    position: fixed;
    top: 0;
    left: 0;
    z-index: 1000;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow-y: auto;
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.15);
}

/* Logo Styles */
.logo-container {
    position: relative;
}

.logo-underline {
    height: 2px;
    background: linear-gradient(90deg, rgba(255,255,255,0.8) 0%, rgba(255,255,255,0) 100%);
    margin-top: 5px;
    width: 50%;
    transition: width 0.3s ease;
}

.logo-container:hover .logo-underline {
    width: 80%;
}

/* Sidebar Toggle Button */
.sidebar-toggle {
    position: fixed;
    top: 1rem;
    left: 1rem;
    z-index: 1031;
    padding: 0.75rem;
    border-radius: 12px;
    backdrop-filter: blur(10px);
    background: rgba(33, 37, 41, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.1);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    width: 45px;
    height: 45px;
}

.sidebar-toggle i {
    font-size: 1.5rem;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.sidebar-toggle:hover {
    background: rgba(33, 37, 41, 1);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transform: translateY(-1px);
}

.sidebar-toggle:active {
    transform: scale(0.95);
}

.sidebar-toggle[aria-expanded="true"] i {
    transform: rotate(90deg);
}

/* User Info Styles */
.user-info {
    padding: 1.5rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.user-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
}

.user-avatar i {
    font-size: 1.5rem;
    color: rgba(255, 255, 255, 0.8);
}

.user-details {
    flex: 1;
}

.welcome-text {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
    margin-bottom: 0.25rem;
}

.role-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 1rem;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.8);
}

/* Navigation Links */
.sidebar .nav-link {
    color: rgba(255, 255, 255, 0.8);
    padding: 0.75rem 1rem;
    border-radius: 0.75rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    margin-bottom: 0.375rem;
    display: flex;
    align-items: center;
    position: relative;
    overflow: hidden;
}

.sidebar .nav-link:hover {
    color: white;
    background: rgba(255, 255, 255, 0.1);
    transform: translateX(5px);
}

.sidebar .nav-link.active {
    color: white;
    background: rgba(255, 255, 255, 0.15);
    font-weight: 500;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.sidebar .nav-link i {
    margin-right: 0.75rem;
    font-size: 1.1rem;
    width: 1.5rem;
    text-align: center;
    transition: transform 0.2s ease;
}

.sidebar .nav-link:hover i {
    transform: scale(1.1);
}

.hover-indicator {
    position: absolute;
    left: 0;
    bottom: 0;
    width: 100%;
    height: 2px;
    background: linear-gradient(90deg, rgba(255,255,255,0.8) 0%, rgba(255,255,255,0) 100%);
    transform: translateX(-100%);
    transition: transform 0.3s ease;
}

.sidebar .nav-link:hover .hover-indicator {
    transform: translateX(0);
}

/* Responsive Styles */
@media (max-width: 767.98px) {
    .sidebar {
        transform: translateX(-100%);
        width: 280px !important;
        backdrop-filter: blur(10px);
        background: rgba(33, 37, 41, 0.95);
    }

    .sidebar.show {
        transform: translateX(0);
    }

    .main-content {
        padding-top: 4rem !important;
    }
    
    .sidebar-toggle {
        transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .sidebar.show + .main-content .sidebar-toggle {
        left: calc(280px + 1rem);
    }
}

@media (min-width: 768px) {
    .sidebar-toggle, #sidebar-close {
        display: none !important;
    }

    .main-content {
        margin-left: 25%;
    }
}

@media (min-width: 992px) {
    .main-content {
        margin-left: 16.666667%;
    }
}

/* Scrollbar Styles */
.sidebar::-webkit-scrollbar {
    width: 5px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.25);
}

/* Animations */
@keyframes slideIn {
    from { transform: translateX(-100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@keyframes slideOut {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(-100%); opacity: 0; }
}

.sidebar.animating-in {
    animation: slideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
}

.sidebar.animating-out {
    animation: slideOut 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
}

/* Loading State */
.nav-link.loading {
    position: relative;
    overflow: hidden;
}

.nav-link.loading::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.1),
        transparent
    );
    animation: loading 1.5s infinite;
}

@keyframes loading {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

/* Add focus styles for accessibility */
.sidebar-toggle:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25), 0 2px 8px rgba(0, 0, 0, 0.1);
}

/* Add touch target size for mobile */
@media (max-width: 576px) {
    .sidebar-toggle {
        width: 50px;
        height: 50px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.querySelector('.sidebar-toggle');
    const closeBtn = document.getElementById('sidebar-close');
    const mainContent = document.querySelector('.main-content');
    
    // Toggle sidebar on mobile
    toggleBtn?.addEventListener('click', function() {
        sidebar.classList.add('show');
        sidebar.classList.add('animating-in');
        toggleBtn.setAttribute('aria-expanded', 'true');
        setTimeout(() => {
            sidebar.classList.remove('animating-in');
        }, 300);
    });

    // Close sidebar on mobile
    closeBtn?.addEventListener('click', function() {
        sidebar.classList.add('animating-out');
        toggleBtn.setAttribute('aria-expanded', 'false');
        setTimeout(() => {
            sidebar.classList.remove('show');
            sidebar.classList.remove('animating-out');
        }, 300);
    });

    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth >= 768) {
                sidebar.classList.remove('show');
                toggleBtn.setAttribute('aria-expanded', 'false');
            }
        }, 250);
    });

    // Handle escape key to close sidebar
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('show')) {
            closeBtn.click();
        }
    });

    // Close sidebar when clicking outside
    document.addEventListener('click', function(e) {
        if (window.innerWidth < 768 && 
            !sidebar.contains(e.target) && 
            !toggleBtn.contains(e.target) && 
            sidebar.classList.contains('show')) {
            closeBtn.click();
        }
    });

    // Add smooth transition for toggle button position
    const updateTogglePosition = () => {
        if (window.innerWidth < 768) {
            const sidebarWidth = sidebar.offsetWidth;
            if (sidebar.classList.contains('show')) {
                toggleBtn.style.left = `${sidebarWidth + 16}px`;
            } else {
                toggleBtn.style.left = '1rem';
            }
        } else {
            toggleBtn.style.left = '';
        }
    };

    // Update toggle position on sidebar toggle
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.attributeName === 'class') {
                updateTogglePosition();
            }
        });
    });

    observer.observe(sidebar, { attributes: true });

    // Initial position
    updateTogglePosition();
});
</script> 