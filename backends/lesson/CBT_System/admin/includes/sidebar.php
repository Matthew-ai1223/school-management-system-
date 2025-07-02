<style>
     body {
            background-color: #f8f9fa;
        }
        .sidebar {
            min-height: 100vh;
            background: #2c3e50;
            padding: 20px;
            transition: all 0.3s ease;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            width: 250px;
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 5px;
        }
        .sidebar .nav-link:hover {
            background: #34495e;
        }
        .sidebar .nav-link.active {
            background: #3498db;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .main-content {
            padding: 20px;
            margin-left: 250px;
            transition: all 0.3s ease;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card i {
            font-size: 2rem;
            color: #3498db;
        }
        
        /* Mobile responsive styles */
        .sidebar-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            background: #2c3e50;
            border: none;
            color: white;
            padding: 10px;
            border-radius: 5px;
            font-size: 1.2rem;
        }
        
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding-top: 70px;
            }
            .sidebar-toggle {
                display: block;
            }
            .sidebar-overlay.show {
                display: block;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 15px;
                padding-top: 70px;
            }
            .stat-card {
                padding: 15px;
            }
        }
</style>

<!-- Mobile sidebar toggle button -->
<button class="sidebar-toggle" id="sidebarToggle">
    <i class='bx bx-menu'></i>
</button>

<!-- Sidebar overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<nav class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse" id="sidebar">
    <div class="position-sticky pt-3">
        <h4 class="text-white mb-4">Admin Panel</h4>
        <nav class="nav flex-column">
            <a class="nav-link active" href="dashboard.php">
                <i class='bx bxs-dashboard'></i> Dashboard
            </a>
            <a class="nav-link" href="students.php">
                <i class='bx bxs-user-detail'></i> Students
            </a>
            <a class="nav-link" href="exams.php">
                <i class='bx bxs-book'></i> Exams
            </a>
            <a class="nav-link" href="reports.php">
                <i class='bx bxs-report'></i> Reports
            </a>
            <a class="nav-link" href="admins.php">
                <i class='bx bxs-user'></i> Admins
            </a>
            <a class="nav-link text-danger" href="logout.php">
                <i class='bx bxs-log-out'></i> Logout
            </a>
        </nav>
    </div>
</nav>

<script>
// Mobile sidebar functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    // Toggle sidebar
    sidebarToggle.addEventListener('click', function() {
        sidebar.classList.toggle('show');
        sidebarOverlay.classList.toggle('show');
    });
    
    // Close sidebar when clicking overlay
    sidebarOverlay.addEventListener('click', function() {
        sidebar.classList.remove('show');
        sidebarOverlay.classList.remove('show');
    });
    
    // Close sidebar when clicking on a nav link (mobile)
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
            }
        });
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        }
    });
});
</script>

