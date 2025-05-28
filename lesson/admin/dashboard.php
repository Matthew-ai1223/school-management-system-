<?php
include '../confg.php';

// Fetch all students from both morning and afternoon tables
$morning_students = $conn->query("SELECT *, 'morning' as session FROM morning_students ORDER BY registration_date DESC");
$afternoon_students = $conn->query("SELECT *, 'afternoon' as session FROM afternoon_students ORDER BY registration_date DESC");

// Function to get payment status
function getPaymentStatus($expirationDate) {
    $today = new DateTime();
    $expiration = new DateTime($expirationDate);
    $daysRemaining = $today->diff($expiration)->days;
    
    if ($expiration < $today) {
        return ['status' => 'Expired', 'class' => 'danger'];
    } elseif ($daysRemaining <= 7) {
        return ['status' => 'Expiring Soon', 'class' => 'warning'];
    } else {
        return ['status' => 'Active', 'class' => 'success'];
    }
}

// Function to toggle account status
function toggleAccountStatus($id, $session, $action) {
    global $conn;
    $table = $session . '_students';
    
    if ($action === 'activate') {
        // Add 30 days from current date for activation
        $expiration_date = date('Y-m-d', strtotime('+30 days'));
        $stmt = $conn->prepare("UPDATE $table SET expiration_date = ? WHERE id = ?");
        $stmt->bind_param("si", $expiration_date, $id);
    } else {
        // Set expiration date to yesterday for deactivation
        $expiration_date = date('Y-m-d', strtotime('-1 day'));
        $stmt = $conn->prepare("UPDATE $table SET expiration_date = ? WHERE id = ?");
        $stmt->bind_param("si", $expiration_date, $id);
    }
    
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// Handle account status toggle
if (isset($_POST['toggle_status'])) {
    $id = intval($_POST['student_id']);
    $session = $_POST['session'];
    $action = $_POST['action'];
    
    if (toggleAccountStatus($id, $session, $action)) {
        header("Location: dashboard.php?status=success&message=" . urlencode("Account status updated successfully"));
    } else {
        header("Location: dashboard.php?status=error&message=" . urlencode("Failed to update account status"));
    }
    exit();
}

// Display status message if any
$status_message = '';
if (isset($_GET['status']) && isset($_GET['message'])) {
    $status_class = $_GET['status'] === 'success' ? 'success' : 'danger';
    $status_message = "<div class='alert alert-{$status_class} alert-dismissible fade show' role='alert'>
                        {$_GET['message']}
                        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                      </div>";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        /* Base styles */
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            overflow-x: hidden;
        }

        /* Sidebar styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            background: #2c3e50;
            color: white;
            padding: 20px;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .sidebar h4 {
            color: white;
            padding: 10px 0;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 10px 15px;
            border-radius: 5px;
            margin: 5px 0;
            transition: all 0.3s;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        /* Main content styles */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        /* Cards and tables */
        .stats-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            margin-bottom: 20px;
        }

        .dashboard-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        /* Responsive styles */
        @media (max-width: 991px) {
            .sidebar {
                width: 70px;
                padding: 10px;
            }

            .sidebar h4 {
                display: none;
            }

            .sidebar .nav-link span {
                display: none;
            }

            .main-content {
                margin-left: 70px;
            }

            .stats-card h3 {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }

            .sidebar .nav {
                display: flex;
                flex-direction: row;
                justify-content: space-around;
                padding: 10px;
            }

            .main-content {
                margin-left: 0;
                padding: 10px;
            }

            .table-responsive {
                overflow-x: auto;
            }

            .stats-card {
                margin-bottom: 15px;
            }

            .dashboard-card {
                padding: 15px;
            }

            /* Adjust table columns for mobile */
            .table td, .table th {
                white-space: nowrap;
                min-width: 100px;
            }

            /* Make modal responsive */
            .modal-dialog {
                margin: 10px;
                max-width: calc(100% - 20px);
            }
        }

        /* DataTables responsive fixes */
        .dataTables_wrapper {
            overflow-x: auto;
        }

        /* Toggle button for mobile menu */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 1001;
            background: #2c3e50;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
        }

        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }

            .sidebar.collapsed {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <button class="menu-toggle btn" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar" id="sidebar">
        <h4 class="text-center mb-4">Admin Dashboard</h4>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="#overview" data-bs-toggle="tab">
                    <i class="fas fa-home me-2"></i> Overview
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#morning-students" data-bs-toggle="tab">
                    <i class="fas fa-sun me-2"></i> Morning Students
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#afternoon-students" data-bs-toggle="tab">
                    <i class="fas fa-moon me-2"></i> Afternoon Students
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#payments" data-bs-toggle="tab">
                    <i class="fas fa-credit-card me-2"></i> Payments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#references" data-bs-toggle="tab">
                    <i class="fas fa-hashtag me-2"></i> Reference Numbers
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="../CBT_System/admin/dashboard.php">
                    <i class='bx bx-laptop'></i> CBT System
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="../logout.php">
                    <i class='bx bx-log-out'></i> Logout
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="tab-content">
            <!-- Overview Tab -->
            <div class="tab-pane fade show active" id="overview">
                <div class="row">
                    <div class="col-md-4">
                        <div class="stats-card" style="background-color: #007bff;">
                            <i class="fas fa-users fa-3x mb-3"></i>
                            <h3><?php echo $morning_students->num_rows + $afternoon_students->num_rows; ?></h3>
                            <p class="mb-0">Total Students</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card" style="background-color: #007bff;">
                            <i class="fas fa-sun fa-3x mb-3"></i>
                            <h3><?php echo $morning_students->num_rows; ?></h3>
                            <p class="mb-0">Morning Students</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card" style="background-color: #007bff;">
                            <i class="fas fa-moon fa-3x mb-3"></i>
                            <h3><?php echo $afternoon_students->num_rows; ?></h3>
                            <p class="mb-0">Afternoon Students</p>
                        </div>
                    </div>
                </div>

                <div class="dashboard-card mt-4">
                    <h4 class="mb-4">Recent Registrations</h4>
                    <div class="table-responsive">
                        <table class="table table-hover" id="recentRegistrationsTable">
                            <thead>
                                <tr>
                                    <th>Photo</th>
                                    <th>Name</th>
                                    <th>Session</th>
                                    <th>Registration Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $morning_students->data_seek(0);
                                $afternoon_students->data_seek(0);
                                $count = 0;
                                while ($count < 10 && ($morning = $morning_students->fetch_assoc())) {
                                    $status = getPaymentStatus($morning['expiration_date']);
                                    echo "<tr>
                                        <td><img src='../student/uploads/" . basename($morning['photo']) . "' class='user-avatar' onerror=\"this.src='https://via.placeholder.com/40'\"></td>
                                        <td>" . htmlspecialchars($morning['fullname']) . "</td>
                                        <td><span class='badge bg-primary'>Morning</span></td>
                                        <td>" . date('M j, Y', strtotime($morning['registration_date'])) . "</td>
                                        <td><span class='badge bg-" . $status['class'] . "'>" . $status['status'] . "</span></td>
                                        <td>
                                            <button class='btn btn-sm btn-info' onclick='viewStudent(" . $morning['id'] . ", \"morning\")'><i class='fas fa-eye'></i></button>
                                        </td>
                                    </tr>";
                                    $count++;
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Morning Students Tab -->
            <div class="tab-pane fade" id="morning-students">
                <?php echo $status_message; ?>
                <div class="dashboard-card">
                    <h4 class="mb-4">Morning Students</h4>
                    <div class="table-responsive">
                        <table class="table table-hover" id="morningStudentsTable">
                            <thead>
                                <tr>
                                    <th>Photo</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Department</th>
                                    <th>Registration Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $morning_students->data_seek(0);
                                while ($student = $morning_students->fetch_assoc()) {
                                    $status = getPaymentStatus($student['expiration_date']);
                                    echo "<tr>
                                        <td><img src='../student/uploads/" . basename($student['photo']) . "' class='user-avatar' onerror=\"this.src='https://via.placeholder.com/40'\"></td>
                                        <td>" . htmlspecialchars($student['fullname']) . "</td>
                                        <td>" . htmlspecialchars($student['email']) . "</td>
                                        <td>" . htmlspecialchars($student['phone']) . "</td>
                                        <td>" . htmlspecialchars($student['department']) . "</td>
                                        <td>" . date('M j, Y', strtotime($student['registration_date'])) . "</td>
                                        <td><span class='badge bg-" . $status['class'] . "'>" . $status['status'] . "</span></td>
                                        <td>
                                            <div class='btn-group'>
                                                <button class='btn btn-sm btn-info' onclick='viewStudent(" . $student['id'] . ", \"morning\")'><i class='fas fa-eye'></i></button>
                                                <form method='POST' style='display: inline;' onsubmit='return confirm(\"Are you sure you want to " . ($status['status'] === 'Expired' ? 'activate' : 'deactivate') . " this account?\");'>
                                                    <input type='hidden' name='student_id' value='" . $student['id'] . "'>
                                                    <input type='hidden' name='session' value='morning'>
                                                    <input type='hidden' name='toggle_status' value='1'>
                                                    <input type='hidden' name='action' value='" . ($status['status'] === 'Expired' ? 'activate' : 'deactivate') . "'>
                                                    <button type='submit' class='btn btn-sm " . ($status['status'] === 'Expired' ? 'btn-success' : 'btn-danger') . "'>
                                                        <i class='fas " . ($status['status'] === 'Expired' ? 'fa-check' : 'fa-ban') . "'></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Afternoon Students Tab -->
            <div class="tab-pane fade" id="afternoon-students">
                <?php echo $status_message; ?>
                <div class="dashboard-card">
                    <h4 class="mb-4">Afternoon Students</h4>
                    <div class="table-responsive">
                        <table class="table table-hover" id="afternoonStudentsTable">
                            <thead>
                                <tr>
                                    <th>Photo</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>School</th>
                                    <th>Class</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $afternoon_students->data_seek(0);
                                while ($student = $afternoon_students->fetch_assoc()) {
                                    $status = getPaymentStatus($student['expiration_date']);
                                    echo "<tr>
                                        <td><img src='../student/uploads/" . basename($student['photo']) . "' class='user-avatar' onerror=\"this.src='https://via.placeholder.com/40'\"></td>
                                        <td>" . htmlspecialchars($student['fullname']) . "</td>
                                        <td>" . htmlspecialchars($student['email']) . "</td>
                                        <td>" . htmlspecialchars($student['phone']) . "</td>
                                        <td>" . htmlspecialchars($student['school']) . "</td>
                                        <td>" . htmlspecialchars($student['class']) . "</td>
                                        <td><span class='badge bg-" . $status['class'] . "'>" . $status['status'] . "</span></td>
                                        <td>
                                            <div class='btn-group'>
                                                <button class='btn btn-sm btn-info' onclick='viewStudent(" . $student['id'] . ", \"afternoon\")'><i class='fas fa-eye'></i></button>
                                                <form method='POST' style='display: inline;' onsubmit='return confirm(\"Are you sure you want to " . ($status['status'] === 'Expired' ? 'activate' : 'deactivate') . " this account?\");'>
                                                    <input type='hidden' name='student_id' value='" . $student['id'] . "'>
                                                    <input type='hidden' name='session' value='afternoon'>
                                                    <input type='hidden' name='toggle_status' value='1'>
                                                    <input type='hidden' name='action' value='" . ($status['status'] === 'Expired' ? 'activate' : 'deactivate') . "'>
                                                    <button type='submit' class='btn btn-sm " . ($status['status'] === 'Expired' ? 'btn-success' : 'btn-danger') . "'>
                                                        <i class='fas " . ($status['status'] === 'Expired' ? 'fa-check' : 'fa-ban') . "'></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Payments Tab -->
            <div class="tab-pane fade" id="payments">
                <div class="dashboard-card">
                    <h4 class="mb-4">Payment History</h4>
                    <div class="table-responsive">
                        <table class="table table-hover" id="paymentsTable">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Session</th>
                                    <th>Payment Type</th>
                                    <th>Amount</th>
                                    <th>Reference</th>
                                    <th>Payment Date</th>
                                    <th>Expiration Date</th>
                                    <th>Receipt</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $morning_students->data_seek(0);
                                $afternoon_students->data_seek(0);
                                
                                while ($student = $morning_students->fetch_assoc()) {
                                    $receipt_path = "../student/uploads/receipt_" . $student['payment_reference'] . ".pdf";
                                    echo "<tr>
                                        <td>" . htmlspecialchars($student['fullname']) . "</td>
                                        <td><span class='badge bg-primary'>Morning</span></td>
                                        <td>" . ucfirst($student['payment_type']) . "</td>
                                        <td>₦" . number_format($student['payment_amount'], 2) . "</td>
                                        <td>" . htmlspecialchars($student['payment_reference']) . "</td>
                                        <td>" . date('M j, Y', strtotime($student['registration_date'])) . "</td>
                                        <td>" . date('M j, Y', strtotime($student['expiration_date'])) . "</td>
                                        <td>";
                                    if (file_exists($receipt_path)) {
                                        echo "<a href='" . $receipt_path . "' class='btn btn-sm btn-primary' target='_blank'><i class='fas fa-download'></i></a>";
                                    } else {
                                        echo "<span class='badge bg-secondary'>N/A</span>";
                                    }
                                    echo "</td></tr>";
                                }

                                while ($student = $afternoon_students->fetch_assoc()) {
                                    $receipt_path = "../student/uploads/receipt_" . $student['payment_reference'] . ".pdf";
                                    echo "<tr>
                                        <td>" . htmlspecialchars($student['fullname']) . "</td>
                                        <td><span class='badge bg-info'>Afternoon</span></td>
                                        <td>" . ucfirst($student['payment_type']) . "</td>
                                        <td>₦" . number_format($student['payment_amount'], 2) . "</td>
                                        <td>" . htmlspecialchars($student['payment_reference']) . "</td>
                                        <td>" . date('M j, Y', strtotime($student['registration_date'])) . "</td>
                                        <td>" . date('M j, Y', strtotime($student['expiration_date'])) . "</td>
                                        <td>";
                                    if (file_exists($receipt_path)) {
                                        echo "<a href='" . $receipt_path . "' class='btn btn-sm btn-primary' target='_blank'><i class='fas fa-download'></i></a>";
                                    } else {
                                        echo "<span class='badge bg-secondary'>N/A</span>";
                                    }
                                    echo "</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Reference Numbers Tab -->
            <div class="tab-pane fade" id="references">
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>Reference Numbers Management</h4>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addReferenceModal">
                            <i class="fas fa-plus me-2"></i>Add Reference Number
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover" id="referencesTable">
                            <thead>
                                <tr>
                                    <th>Reference Number</th>
                                    <th>Session Type</th>
                                    <th>Payment Type</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                    <th>Used At</th>
                                </tr>
                            </thead>
                            <tbody id="referenceTableBody">
                                <!-- Will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Details Modal -->
    <div class="modal fade" id="studentModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Student Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="studentDetails">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading student details...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Reference Modal -->
    <div class="modal fade" id="addReferenceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Reference Number</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addReferenceForm">
                        <div class="mb-3">
                            <label for="reference_number" class="form-label">Reference Number</label>
                            <input type="text" class="form-control" id="reference_number" required>
                        </div>
                        <div class="mb-3">
                            <label for="session_type" class="form-label">Session Type</label>
                            <select class="form-select" id="session_type" required>
                                <option value="morning">Morning</option>
                                <option value="afternoon">Afternoon</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="payment_type" class="form-label">Payment Type</label>
                            <select class="form-select" id="payment_type" required>
                                <option value="full">Full Payment</option>
                                <option value="half">Half Payment</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="addReference()">Add Reference</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTables with responsive configuration
            const dataTableConfig = {
                order: [[0, 'desc']],
                pageLength: 25,
                responsive: true,
                scrollX: true,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search records..."
                },
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
            };

            $('#recentRegistrationsTable').DataTable({
                ...dataTableConfig,
                pageLength: 10
            });
            $('#morningStudentsTable').DataTable(dataTableConfig);
            $('#afternoonStudentsTable').DataTable(dataTableConfig);
            $('#paymentsTable').DataTable(dataTableConfig);

            // Handle menu toggle for mobile
            $('#menuToggle').click(function() {
                $('#sidebar').toggleClass('collapsed');
                if ($(window).width() <= 768) {
                    $('.main-content').toggleClass('margin-left-0');
                }
            });

            // Handle window resize
            $(window).resize(function() {
                if ($(window).width() > 768) {
                    $('#sidebar').removeClass('collapsed');
                    $('.main-content').removeClass('margin-left-0');
                }
            });

            // Add touch support for tables on mobile
            $('.table-responsive').on('touchstart', function(e) {
                $(this).css('cursor', 'grab');
            });

            $('.table-responsive').on('touchmove', function(e) {
                $(this).css('cursor', 'grabbing');
            });

            $('.table-responsive').on('touchend', function(e) {
                $(this).css('cursor', 'grab');
            });
        });

        function viewStudent(id, session) {
            // Show loading state
            $('#studentDetails').html(`
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading student details...</p>
                </div>
            `);
            
            // Show modal
            $('#studentModal').modal('show');
            
            // Load student details via AJAX
            $.get('get_student_details.php', { 
                id: id, 
                session: session 
            })
            .done(function(data) {
                $('#studentDetails').html(data);
            })
            .fail(function() {
                $('#studentDetails').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Failed to load student details. Please try again.
                    </div>
                `);
            });
        }

        // Function to load reference numbers
        function loadReferenceNumbers() {
            fetch('create_reference_table.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const tbody = document.getElementById('referenceTableBody');
                        tbody.innerHTML = '';
                        
                        data.data.forEach(ref => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${ref.reference_number}</td>
                                <td><span class="badge bg-${ref.session_type === 'morning' ? 'primary' : 'info'}">${ref.session_type}</span></td>
                                <td>${ref.payment_type}</td>
                                <td><span class="badge bg-${ref.is_used ? 'success' : 'warning'}">${ref.is_used ? 'Used' : 'Available'}</span></td>
                                <td>${new Date(ref.created_at).toLocaleString()}</td>
                                <td>${ref.used_at ? new Date(ref.used_at).toLocaleString() : 'Not used'}</td>
                            `;
                            tbody.appendChild(row);
                        });
                    }
                })
                .catch(error => console.error('Error loading references:', error));
        }

        // Function to add a new reference number
        function addReference() {
            const formData = new FormData();
            formData.append('reference_number', document.getElementById('reference_number').value);
            formData.append('session_type', document.getElementById('session_type').value);
            formData.append('payment_type', document.getElementById('payment_type').value);

            fetch('create_reference_table.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('Reference number added successfully');
                    document.getElementById('addReferenceForm').reset();
                    $('#addReferenceModal').modal('hide');
                    loadReferenceNumbers();
                } else {
                    alert(data.message || 'Failed to add reference number');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to add reference number');
            });
        }

        // Load reference numbers when the tab is shown
        document.querySelector('a[href="#references"]').addEventListener('click', loadReferenceNumbers);
    </script>
</body>
</html> 