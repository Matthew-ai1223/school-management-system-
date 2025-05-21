<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo SCHOOL_NAME; ?> CBT System</title>

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="../assets/css/adminlte.min.css">
  <!-- Additional CSS -->
  <?php if(isset($extra_css)): ?>
    <?php echo $extra_css; ?>
  <?php endif; ?>

  <!-- Custom Header Styles -->
  <style>
:root {
  --primary-navy: #002147;
  --primary-light: #003166;
  --primary-lighter: #00458a;
  --accent-blue: #0d6efd;
  --accent-blue-hover: #0b5ed7;
  --text-white: #ffffff;
  --text-light: #f8f9fa;
  --text-muted: rgba(255, 255, 255, 0.7);
  --text-dark: #343a40;
  --border-radius: 8px;
  --border-radius-sm: 4px;
  --shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
  --transition: all 0.3s ease;
}

body {
  font-family: 'Source Sans Pro', -apple-system, BlinkMacSystemFont, sans-serif;
  background: #f8fafc;
  margin: 0;
  padding: 0;
  color: #333;
  line-height: 1.6;
}

/* Navbar */
.main-header {
  background: var(--primary-navy);
  height: 60px;
  position: fixed;
  width: 100%;
  z-index: 1040;
  box-shadow: var(--shadow);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.navbar {
  padding: 0 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  height: 60px;
}

.navbar-nav {
  display: flex;
  align-items: center;
}

.nav-item {
  margin-right: 0.5rem;
}

.nav-link {
  display: flex;
  align-items: center;
  padding: 0.5rem 0.75rem;
  transition: var(--transition);
  border-radius: var(--border-radius-sm);
}

.nav-link:hover {
  background: rgba(255, 255, 255, 0.1);
}

.nav-link i {
  margin-right: 0.5rem;
  font-size: 0.9em;
}

.nav-link.active {
  background: var(--primary-light);
  font-weight: 600;
}

/* Brand */
.brand-link {
  background: var(--primary-navy);
  color: var(--text-white) !important;
  display: flex;
  align-items: center;
  padding: 0 1rem;
  height: 60px;
  transition: var(--transition);
}

.brand-link:hover {
  text-decoration: none;
  opacity: 0.9;
}

.brand-image {
  max-height: 36px;
  margin-right: 12px;
  object-fit: contain;
}

.brand-text {
  font-weight: 700;
  font-size: 1.3rem;
  letter-spacing: 0.5px;
}

/* Sidebar */
.main-sidebar {
  background: var(--primary-navy);
  padding-top: 60px;
  min-height: 100vh;
  position: fixed;
  width: 250px;
  box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
  transition: var(--transition);
}

.sidebar {
  padding: 1rem 0.75rem;
  overflow-y: auto;
  height: calc(100vh - 60px);
}

/* Content Wrapper */
.content-wrapper {
  margin-left: 250px;
  margin-top: 60px;
  padding: 1.5rem;
  background-color: #f8fafc;
  min-height: calc(100vh - 60px);
  transition: var(--transition);
}

/* ================= MEDIA QUERIES ================= */

/* Large devices (desktops, 1200px and up) */
@media (min-width: 1200px) {
  .main-sidebar {
    width: 280px;
  }
  .content-wrapper {
    margin-left: 280px;
  }
}

/* Medium devices (tablets, 992px to 1199px) */
@media (max-width: 1199.98px) {
  .main-sidebar {
    width: 230px;
  }
  .content-wrapper {
    margin-left: 230px;
  }
}

/* Small devices (landscape phones, 768px to 991.98px) */
@media (max-width: 991.98px) {
  .main-sidebar {
    transform: translateX(-100%);
    z-index: 1035;
  }
  
  .sidebar-open .main-sidebar {
    transform: translateX(0);
  }
  
  .content-wrapper {
    margin-left: 0;
  }
  
  .navbar {
    padding: 0 1rem;
  }
  
  .brand-text {
    font-size: 1.1rem;
  }
  
  .nav-link span {
    display: none;
  }
  
  .nav-link i {
    margin-right: 0;
    font-size: 1.1em;
  }
  
  .create-cbt-btn span {
    display: none;
  }
  
  .create-cbt-btn i {
    margin-right: 0;
  }
}

/* Extra small devices (portrait phones, less than 768px) */
@media (max-width: 767.98px) {
  .content-wrapper {
    padding: 1rem;
  }
  
  .content-header h1 {
    font-size: 1.5rem;
  }
  
  .user-panel .info {
    display: none;
  }
  
  .user-panel .image i {
    font-size: 1.8rem;
    margin-right: 0;
  }
  
  .brand-text {
    display: none;
  }
  
  .create-cbt-btn {
    padding: 0.5rem;
    width: 40px;
    justify-content: center;
  }
  
  .nav-sidebar .nav-link p {
    display: none;
  }
  
  .nav-sidebar .nav-link i {
    min-width: auto;
    margin-right: 0;
    font-size: 1.2em;
  }
  
  .nav-header {
    display: none;
  }
}

/* Extra extra small devices (phones, less than 576px) */
@media (max-width: 575.98px) {
  .content-wrapper {
    padding: 0.75rem;
  }
  
  .main-header {
    height: 50px;
  }
  
  .navbar {
    height: 50px;
  }
  
  .brand-link {
    height: 50px;
  }
  
  .brand-image {
    max-height: 30px;
  }
  
  .main-sidebar {
    padding-top: 50px;
    width: 220px;
  }
  
  .sidebar {
    height: calc(100vh - 50px);
  }
  
  .content-wrapper {
    margin-top: 50px;
    min-height: calc(100vh - 50px);
  }
  
  .nav-item {
    margin-right: 0.25rem;
  }
  
  .nav-link {
    padding: 0.5rem;
  }
}

/* Very small devices (phones, less than 400px) */
@media (max-width: 399.98px) {
  .navbar {
    padding: 0 0.5rem;
  }
  
  .brand-link {
    padding: 0 0.5rem;
  }
  
  .content-wrapper {
    padding: 0.5rem;
  }
  
  .main-sidebar {
    width: 200px;
  }
}

/* Print media */
@media print {
  .main-header, .main-sidebar {
    display: none;
  }
  
  .content-wrapper {
    margin: 0;
    padding: 0;
  }
}
</style>

</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <!-- Navbar -->
  <nav class="main-header navbar navbar-expand navbar-dark">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button">
          <i class="fas fa-bars"></i>
        </a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
          <i class="fas fa-home"></i>
          <span>Dashboard</span>
        </a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="manage_cbt_exams.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_cbt_exams.php' ? 'active' : ''; ?>">
          <i class="fas fa-laptop"></i>
          <span>CBT Exams</span>
        </a>
      </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
      <li class="nav-item">
        <a href="create_cbt_exam.php" class="create-cbt-btn">
          <i class="fas fa-plus"></i>
          <span>Create CBT Exam</span>
        </a>
      </li>
    </ul>
  </nav>

  <!-- Main Sidebar Container -->
  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="dashboard.php" class="brand-link">
      <img src="../images/logo.png" alt="School Logo" class="brand-image">
      <span class="brand-text"><?php echo SCHOOL_SHORT_NAME ?? SCHOOL_NAME; ?></span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
      <!-- Sidebar user panel -->
      <div class="user-panel">
        <div class="image">
          <i class="fas fa-user-circle"></i>
        </div>
        <div class="info">
          <a href="profile.php" class="d-block">
            <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
            <small class="d-block">ID: <?php echo htmlspecialchars($_SESSION['employee_id'] ?? 'N/A'); ?></small>
          </a>
        </div>
      </div>

      <!-- Sidebar Menu -->
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <li class="nav-item">
            <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-tachometer-alt"></i>
              <p>Dashboard</p>
            </a>
          </li>
          <li class="nav-header">CBT MANAGEMENT</li>
          <li class="nav-item">
            <a href="manage_cbt_exams.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_cbt_exams.php' ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-laptop"></i>
              <p>Exams</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="create_cbt_exam.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'create_cbt_exam.php' ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-plus-circle"></i>
              <p>Create New Exam</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-chart-bar"></i>
              <p>Reports</p>
            </a>
          </li>
          <li class="nav-header">ACCOUNT</li>
          <li class="nav-item">
            <a href="profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-user"></i>
              <p>Profile</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="change_password.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'change_password.php' ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-key"></i>
              <p>Change Password</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="logout.php" class="nav-link">
              <i class="nav-icon fas fa-sign-out-alt"></i>
              <p>Logout</p>
            </a>
          </li>
        </ul>
      </nav>
    </div>
  </aside>

  <!-- Content Wrapper -->
  <div class="content-wrapper">
    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <!-- Content Header -->
        <div class="content-header">
          <div class="container-fluid">
            <div class="row mb-2">
              <div class="col-sm-6">
                <h1 class="m-0"><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h1>
              </div>
              <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                  <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                  <?php if(isset($page_title)): ?>
                  <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
                  <?php endif; ?>
                </ol>
              </div>
            </div>
          </div>
        </div>

        <!-- Main content -->
        <section class="content">
          <div class="container-fluid"> 