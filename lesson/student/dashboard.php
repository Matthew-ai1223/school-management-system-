<?php
session_start();
include '../confg.php';
require_once 'check_account_status.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_table'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_table = $_SESSION['user_table'];

// Check account status
if (!isAccountActive($conn, $user_id, $user_table)) {
    session_destroy();
    header('Location: login.php?error=expired');
    exit();
}

// Get days remaining
$days_remaining = getDaysRemaining($conn, $user_id, $user_table);

// Fetch user info
$stmt = $conn->prepare("SELECT * FROM $user_table WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Get the photo filename from the path
$photo_path = $user['photo'];
$photo_filename = basename($photo_path);
$photo_url = 'uploads/' . $photo_filename;

// Logout logic
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Check if receipt exists
$receipt_filename = 'receipt_' . $user['payment_reference'] . '.pdf';
$receipt_path = 'uploads/' . $receipt_filename;
$receipt_exists = file_exists($receipt_path);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4a90e2;
            --secondary-color: #2c3e50;
            --sidebar-width: 250px;
            --header-height: 60px;
        }

        body {
            min-height: 100vh;
            margin: 0;
            background-color: #f4f6f9;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: var(--secondary-color);
            color: white;
            padding-top: var(--header-height);
            transition: all 0.3s;
            z-index: 1000;
        }

        .sidebar-header {
            text-align: center;
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .profile-image {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin-bottom: 10px;
            border: 3px solid white;
        }

        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            transition: all 0.3s;
        }

        .nav-link:hover {
            color: white;
            background: rgba(255,255,255,0.1);
        }

        .nav-link.active {
            background: var(--primary-color);
            color: white;
        }

        .nav-link i {
            width: 25px;
            margin-right: 10px;
        }

        /* Main Content Styles */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            padding-top: calc(var(--header-height) + 20px);
        }

        /* Header Styles */
        .main-header {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            height: var(--header-height);
            background: white;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            z-index: 900;
        }

        /* Card Styles */
        .dashboard-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stats-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, #357abd 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .stats-card i {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .main-header {
                left: 0;
            }

            .toggle-sidebar {
                display: block !important;
            }
        }

        .toggle-sidebar {
            display: none;
            background: none;
            border: none;
            color: var(--secondary-color);
            font-size: 1.5rem;
            cursor: pointer;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* CBT Section Styles */
        .cbt-section {
            background: linear-gradient(to right, #ffffff, #f8f9fa);
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .cbt-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }

        .cbt-section::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 150px;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(74, 144, 226, 0.1));
            z-index: 0;
        }

        .cbt-section h4 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .cbt-section .description {
            line-height: 1.6;
            color: #555;
        }

        .cbt-section .note-text {
            margin-top: 15px;
            padding: 10px;
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            border-radius: 4px;
        }

        .cbt-section .note-text span {
            color: #856404;
            font-weight: 600;
        }

        .cbt-button {
            padding: 12px 25px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            background: linear-gradient(45deg, var(--primary-color), #357abd);
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .cbt-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
            background: linear-gradient(45deg, #357abd, var(--primary-color));
        }

        .cbt-button i {
            transition: transform 0.3s ease;
        }

        .cbt-button:hover i {
            transform: translateX(5px);
        }

        @media (max-width: 768px) {
            .cbt-section {
                text-align: center;
            }

            .cbt-section .btn-container {
                margin-top: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="<?php echo htmlspecialchars($photo_url); ?>" alt="Profile" class="profile-image" onerror="this.src='https://via.placeholder.com/150?text=No+Photo'">
            <h6 class="mb-0 mt-2"><?php echo htmlspecialchars($user['fullname']); ?></h6>
            <small><?php echo $user_table === 'morning_students' ? 'Morning Class' : 'Afternoon Class'; ?></small>
        </div>
        <ul class="nav flex-column mt-4">
            <li class="nav-item">
                <a class="nav-link active" href="#dashboard" data-bs-toggle="tab">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#profile" data-bs-toggle="tab">
                    <i class="fas fa-user"></i> Profile
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#payment" data-bs-toggle="tab">
                    <i class="fas fa-credit-card"></i> Payment Info
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#documents" data-bs-toggle="tab">
                    <i class="fas fa-file-alt"></i> Documents
                </a>
            </li>
            <li class="nav-item mt-auto">
                <a class="nav-link text-danger" href="?logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Header -->
    <header class="main-header">
        <button class="toggle-sidebar">
            <i class="fas fa-bars"></i>
        </button>
        <div class="d-flex align-items-center">
            <div class="me-3">
                <i class="fas fa-clock"></i>
                Days Remaining: <span class="badge bg-<?php echo $days_remaining <= 7 ? 'danger' : 'primary'; ?>"><?php echo $days_remaining; ?> days</span>
            </div>
            <div>
                <i class="fas fa-calendar"></i>
                Expires: <?php echo date('M j, Y', strtotime($user['expiration_date'])); ?>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-content">
        <div class="tab-content">
            <!-- Dashboard Tab -->
            <div class="tab-pane fade show active" id="dashboard">
                <div class="row">
                    <div class="col-md-4">
                        <div class="stats-card">
                            <i class="fas fa-user-graduate"></i>
                            <h3 class="mb-2">Student Status</h3>
                            <p class="mb-0">Active</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <i class="fas fa-money-bill-wave"></i>
                            <h3 class="mb-2">Payment Status</h3>
                            <p class="mb-0"><?php echo ucfirst($user['payment_type']); ?> Payment</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <i class="fas fa-clock"></i>
                            <h3 class="mb-2">Time Remaining</h3>
                            <p class="mb-0"><?php echo $days_remaining; ?> Days</p>
                        </div>
                    </div>
                </div>

                <!-- CBT Section -->
                <div class="dashboard-card mt-4 cbt-section">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4>
                                <i class="fas fa-laptop-code me-2"></i>
                                Computer Based Test (CBT)
                            </h4>
                            <div class="description">
                                <p class="mb-0">
                                    Access our Computer Based Testing system to take practice tests, assessments, and track your progress. 
                                    The CBT system provides an interactive platform for evaluating your knowledge and improving your performance.
                                </p>
                                <div class="note-text">
                                    <span><i class="fas fa-info-circle me-2"></i>Note:</span> 
                                    You are to register for the CBT system to be able to access the system, if you are first login in the first time.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end btn-container">
                            <a href="../CBT_System/login.php" class="btn btn-primary btn-lg cbt-button">
                                <i class="fas fa-sign-in-alt me-2"></i>Access CBT System
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Tab -->
            <div class="tab-pane fade" id="profile">
                <div class="dashboard-card">
                    <h4 class="mb-4">Personal Information</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Full Name:</strong> <?php echo htmlspecialchars($user['fullname']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($user['phone']); ?></p>
                            <p><strong>Department:</strong> <?php echo ucfirst(htmlspecialchars($user['department'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Parent Name:</strong> <?php echo htmlspecialchars($user['parent_name']); ?></p>
                            <p><strong>Parent Phone:</strong> <?php echo htmlspecialchars($user['parent_phone']); ?></p>
                            <p><strong>Address:</strong> <?php echo htmlspecialchars($user['address']); ?></p>
                            <?php if ($user_table === 'afternoon_students'): ?>
                                <p><strong>Class:</strong> <?php echo htmlspecialchars($user['class']); ?></p>
                                <p><strong>School:</strong> <?php echo htmlspecialchars($user['school']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Info Tab -->
            <div class="tab-pane fade" id="payment">
                <div class="dashboard-card">
                    <h4 class="mb-4">Payment Details</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Payment Type:</strong> <?php echo ucfirst($user['payment_type']); ?></p>
                            <p><strong>Amount Paid:</strong> ₦<?php echo number_format($user['payment_amount'], 2); ?></p>
                            <p><strong>Payment Reference:</strong> <?php echo htmlspecialchars($user['payment_reference']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Registration Date:</strong> <?php echo date('F j, Y', strtotime($user['registration_date'])); ?></p>
                            <p><strong>Expiration Date:</strong> <?php echo date('F j, Y', strtotime($user['expiration_date'])); ?></p>
                            <?php if ($receipt_exists): ?>
                                <a href="<?php echo htmlspecialchars($receipt_path); ?>" class="btn btn-primary" target="_blank">
                                    <i class="fas fa-download me-2"></i>Download Receipt
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Documents Tab -->
            <div class="tab-pane fade" id="documents">
                <div class="dashboard-card">
                    <h4 class="mb-4">Documents</h4>
                    <div class="list-group">
                        <?php if ($receipt_exists): ?>
                            <a href="<?php echo htmlspecialchars($receipt_path); ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" target="_blank">
                                <div>
                                    <i class="fas fa-file-pdf me-2"></i>
                                    Payment Receipt
                                </div>
                                <span class="badge bg-primary rounded-pill">
                                    <i class="fas fa-download"></i>
                                </span>
                            </a>
                        <?php else: ?>
                            <div class="list-group-item text-muted">
                                <i class="fas fa-info-circle me-2"></i>
                                No documents available
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Receipt Download Modal -->
    <div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="receiptModalLabel">Download Your Receipt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Your payment receipt is ready for download. Please click the button below to download your receipt.</p>
                    <?php if ($receipt_exists): ?>
                        <div class="text-center">
                            <a href="<?php echo htmlspecialchars($receipt_path); ?>" class="btn btn-primary" target="_blank">
                                <i class="fas fa-download me-2"></i>Download Receipt
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle Sidebar on Mobile
        const sidebar = document.querySelector('.sidebar');
        const toggleBtn = document.querySelector('.toggle-sidebar');
        const mainContent = document.querySelector('.main-content');

        // Function to close sidebar
        function closeSidebar() {
            sidebar.classList.remove('active');
        }

        // Toggle sidebar when button is clicked
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('active');
        });

        // Close sidebar when clicking outside
        document.addEventListener('click', function(e) {
            if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                closeSidebar();
            }
        });

        // Handle Tab Navigation
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                if (!this.getAttribute('href').includes('logout')) {
                    e.preventDefault();
                    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Close sidebar on mobile when menu item is clicked
                    if (window.innerWidth <= 768) {
                        closeSidebar();
                    }
                }
            });
        });

        // Show Receipt Modal on Page Load if receipt exists
        <?php if ($receipt_exists): ?>
        window.addEventListener('load', function() {
            const receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
            receiptModal.show();
        });
        <?php endif; ?>

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('active');
            }
        });
    </script>
</body>
</html> 