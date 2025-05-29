
<style>
     body {
            background-color: #f8f9fa;
        }
        .sidebar {
            min-height: 100vh;
            background: #2c3e50;
            padding: 20px;
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
</style>





<nav class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
    <div class="position-sticky pt-3">
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

