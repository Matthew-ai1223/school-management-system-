<?php
// Get the current page name to highlight active link
$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
    /* Sidebar Styles */
    .sidebar {
        min-height: 100vh;
        background: linear-gradient(180deg, #2c3e50 0%, #3498db 100%);
        padding: 0;
        position: fixed;
        top: 0;
        left: 0;
        z-index: 1000;
        transition: all 0.3s ease;
        width: 250px;
    }

    .sidebar-content {
        padding: 20px;
        height: 100vh;
        overflow-y: auto;
        position: relative;
    }

    .sidebar-content::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar-content::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.1);
    }

    .sidebar-content::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.2);
        border-radius: 3px;
    }

    .sidebar-header {
        padding: 20px;
        background: rgba(0, 0, 0, 0.1);
        margin: -20px -20px 20px -20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .sidebar-header h4 {
        color: #fff;
        margin: 0;
        font-size: 1.5rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .sidebar .nav-link {
        color: rgba(255, 255, 255, 0.9);
        padding: 12px 20px;
        border-radius: 8px;
        margin-bottom: 5px;
        transition: all 0.3s ease;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 12px;
        position: relative;
        overflow: hidden;
    }

    .sidebar .nav-link i {
        font-size: 1.2rem;
        width: 24px;
        text-align: center;
        transition: all 0.3s ease;
    }

    .sidebar .nav-link:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #fff;
        transform: translateX(5px);
    }

    .sidebar .nav-link.active {
        background: #fff;
        color: #2c3e50;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .sidebar .nav-link.active i {
        color: #3498db;
    }

    .sidebar .nav-link.text-danger {
        background: rgba(220, 53, 69, 0.1);
        margin-top: 20px;
    }

    .sidebar .nav-link.text-danger:hover {
        background: rgba(220, 53, 69, 0.2);
    }

    .sidebar .nav-link span {
        font-size: 0.95rem;
    }

    /* Mobile Toggle Button */
    .sidebar-toggle {
        display: none;
        position: fixed;
        top: 15px;
        left: 15px;
        z-index: 1001;
        background: #2c3e50;
        border: none;
        color: white;
        padding: 8px;
        border-radius: 6px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        cursor: pointer;
        transition: all 0.3s ease;
        width: 35px;
        height: 35px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .sidebar-toggle i {
        font-size: 1.2rem;
        transition: transform 0.3s ease;
    }

    .sidebar-toggle:hover {
        background: #34495e;
    }

    .sidebar-toggle:hover i {
        transform: scale(1.1);
    }

    .sidebar-toggle:active i {
        transform: scale(0.95);
    }

    @media (max-width: 991px) {
        .sidebar-toggle {
            display: flex;
            padding: 6px;
            width: 32px;
            height: 32px;
        }

        .sidebar-toggle i {
            font-size: 1.1rem;
        }
    }

    @media (max-width: 576px) {
        .sidebar-toggle {
            padding: 5px;
            width: 28px;
            height: 28px;
            top: 10px;
            left: 10px;
        }

        .sidebar-toggle i {
            font-size: 1rem;
        }
    }

    /* Main Content Wrapper */
    .main-wrapper {
        min-height: 100vh;
        padding-left: 250px;
        transition: all 0.3s ease;
    }

    /* Mobile Responsiveness */
    @media (max-width: 991px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .sidebar.show {
            transform: translateX(0);
        }

        .main-wrapper {
            padding-left: 0;
        }

        .main-wrapper.sidebar-open {
            transform: translateX(250px);
        }

        /* Overlay when sidebar is open */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
        }

        .sidebar-overlay.show {
            display: block;
        }
    }
</style>

<!-- Sidebar Toggle Button -->
<button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Menu">
    <i class='bx bx-menu'></i>
</button>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-content">
        <div class="sidebar-header">
            <h4>
                <i class='bx bxs-graduation'></i>
                <span>Admin Panel</span>
            </h4>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                <i class='bx bxs-dashboard'></i>
                <span>Dashboard</span>
            </a>
            <a class="nav-link <?php echo $current_page === 'students.php' ? 'active' : ''; ?>" href="students.php">
                <i class='bx bxs-user-detail'></i>
                <span>Students</span>
            </a>
            <a class="nav-link <?php echo $current_page === 'exams.php' ? 'active' : ''; ?>" href="exams.php">
                <i class='bx bxs-book'></i>
                <span>Exams</span>
            </a>
            <a class="nav-link <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                <i class='bx bxs-report'></i>
                <span>Reports</span>
            </a>
            <a class="nav-link <?php echo $current_page === 'admins.php' ? 'active' : ''; ?>" href="admins.php">
                <i class='bx bxs-user'></i>
                <span>Admins</span>
            </a>
            <a class="nav-link text-danger" href="logout.php">
                <i class='bx bxs-log-out'></i>
                <span>Logout</span>
            </a>
        </nav>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mainWrapper = document.querySelector('.main-wrapper');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    function toggleSidebar() {
        sidebar.classList.toggle('show');
        if (mainWrapper) mainWrapper.classList.toggle('sidebar-open');
        sidebarOverlay.classList.toggle('show');
    }

    sidebarToggle.addEventListener('click', toggleSidebar);
    sidebarOverlay.addEventListener('click', toggleSidebar);

    // Close sidebar when clicking a link on mobile
    const sidebarLinks = document.querySelectorAll('.sidebar .nav-link');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 991) {
                toggleSidebar();
            }
        });
    });

    // Handle window resize
    let windowWidth = window.innerWidth;
    window.addEventListener('resize', () => {
        if (window.innerWidth !== windowWidth) {
            windowWidth = window.innerWidth;
            if (windowWidth > 991) {
                sidebar.classList.remove('show');
                if (mainWrapper) mainWrapper.classList.remove('sidebar-open');
                sidebarOverlay.classList.remove('show');
            }
        }
    });
});
</script>