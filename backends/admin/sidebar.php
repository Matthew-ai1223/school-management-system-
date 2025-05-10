<?php
if (!isset($user)) {
    require_once '../auth.php';
    $auth = new Auth();
    $user = $auth->getCurrentUser();
}
?>
<div class="col-md-3 col-lg-2 sidebar p-3">
    <h3 class="mb-4"><?php echo SCHOOL_NAME; ?></h3>
    <div class="mb-4">
        <p class="mb-1">Welcome,</p>
        <h5><?php echo $user['name']; ?></h5>
        <small class="text-muted"><?php echo ucfirst($user['role']); ?></small>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item mb-2">
            <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </li>
        <li class="nav-item mb-2">
            <a href="students.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'students.php' ? 'active' : ''; ?>">
                <i class="bi bi-people"></i> Students
            </a>
        </li>
        <li class="nav-item mb-2">
            <a href="applications.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'applications.php' ? 'active' : ''; ?>">
                <i class="bi bi-file-text"></i> Applications
            </a>
        </li>
        <li class="nav-item mb-2">
            <a href="application_form_filed_update.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'application_form_filed_update.php' ? 'active' : ''; ?>">
                <i class="bi bi-list-check"></i> Form Fields
            </a>
        </li>
        <li class="nav-item mb-2">
            <a href="payments.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'payments.php' ? 'active' : ''; ?>">
                <i class="bi bi-cash"></i> Payments
            </a>
        </li>
        <li class="nav-item mb-2">
            <a href="exams.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'exams.php' ? 'active' : ''; ?>">
                <i class="bi bi-pencil-square"></i> Exams
            </a>
        </li>
        <li class="nav-item mb-2">
            <a href="users.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>">
                <i class="bi bi-person"></i> Users
            </a>
        </li>
        <li class="nav-item mb-2">
            <a href="settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
                <i class="bi bi-gear"></i> Settings
            </a>
        </li>
        <li class="nav-item mt-4">
            <a href="logout.php" class="nav-link text-danger">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </li>
    </ul>
</div>

<style>
.sidebar {
    min-height: 100vh;
    background: #343a40;
    color: white;
    position: sticky;
    top: 0;
}

.sidebar .nav-link {
    color: rgba(255, 255, 255, 0.8);
    padding: 0.5rem 1rem;
    border-radius: 0.25rem;
    transition: all 0.3s ease;
}

.sidebar .nav-link:hover {
    color: white;
    background: rgba(255, 255, 255, 0.1);
}

.sidebar .nav-link.active {
    color: white;
    background: rgba(255, 255, 255, 0.2);
}

.sidebar .nav-link i {
    margin-right: 0.5rem;
}

.sidebar h3 {
    font-size: 1.2rem;
    font-weight: bold;
}

.sidebar h5 {
    font-size: 1rem;
    margin-bottom: 0;
}

.sidebar .text-muted {
    color: rgba(255, 255, 255, 0.6) !important;
}
</style> 