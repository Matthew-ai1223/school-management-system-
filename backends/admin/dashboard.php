<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();
$mysqli = $db->getConnection();
$user = $auth->getCurrentUser();

// Function to safely extract data from JSON
function safe_json_extract($json_column, $path, $default = '') {
    if (empty($json_column)) {
        return $default;
    }
    
    // Try JSON_EXTRACT first
    $extracted = null;
    try {
        $extracted = json_decode($json_column, true);
        
        // Remove $ prefix if present in path
        $path = ltrim($path, '$.');
        $parts = explode('.', $path);
        
        // Navigate through the JSON structure
        foreach ($parts as $part) {
            // Handle array access notation field_name[0]
            if (preg_match('/^(.*)\[(\d+)\]$/', $part, $matches)) {
                $name = $matches[1];
                $index = (int)$matches[2];
                if (isset($extracted[$name]) && isset($extracted[$name][$index])) {
                    $extracted = $extracted[$name][$index];
                } else {
                    return $default;
                }
            } 
            // Regular field access
            else if (isset($extracted[$part])) {
                $extracted = $extracted[$part];
            } else {
                // Try to find a case-insensitive match
                $found = false;
                foreach (array_keys($extracted) as $key) {
                    if (strtolower($key) === strtolower($part)) {
                        $extracted = $extracted[$key];
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    return $default;
                }
            }
        }
        
        return (is_string($extracted) || is_numeric($extracted)) ? $extracted : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// Get statistics
$stats = [
    'total_applications' => 0,
    'pending_applications' => 0,
    'total_payments' => 0,
    'approved_applications' => 0
];

// Get total applications
$result = $mysqli->query("SELECT COUNT(*) as count FROM applications");
if ($result) {
    $stats['total_applications'] = $result->fetch_assoc()['count'];
}

// Get pending applications
$result = $mysqli->query("SELECT COUNT(*) as count FROM applications WHERE status = 'pending'");
if ($result) {
    $stats['pending_applications'] = $result->fetch_assoc()['count'];
}

// Get approved applications
$result = $mysqli->query("SELECT COUNT(*) as count FROM applications WHERE status = 'approved'");
if ($result) {
    $stats['approved_applications'] = $result->fetch_assoc()['count'];
}

// Get total payments
$result = $mysqli->query("SELECT COUNT(*) as count FROM application_payments WHERE status = 'completed'");
if ($result) {
    $stats['total_payments'] = $result->fetch_assoc()['count'];
}

// Get total amount from payments
$result = $mysqli->query("SELECT SUM(amount) as total FROM application_payments WHERE status = 'completed'");
$total_amount = 0;
if ($result) {
    $total_amount = $result->fetch_assoc()['total'] ?? 0;
}

// Get newly registered students with their registration numbers
$recent_students = [];

// First try to get approved applications directly
$applications_query = "
    SELECT a.* 
    FROM applications a 
    WHERE a.status = 'approved'
    ORDER BY a.id DESC LIMIT 5
";

$result = $mysqli->query($applications_query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Generate a placeholder registration number if we don't have students table
        $row['registration_number'] = 'ACE-' . date('Y') . '-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT);
        
        // Extract student name and class using our safe function
        $row['student_name'] = safe_json_extract($row['applicant_data'], 'field_full_name', '');
        $row['class'] = safe_json_extract($row['applicant_data'], 'field_class_admission', 'N/A');
        
        // If name is still empty, try to find it in any field that contains 'name'
        if (empty($row['student_name'])) {
            $applicant_data = json_decode($row['applicant_data'], true);
            if (is_array($applicant_data)) {
                foreach ($applicant_data as $key => $value) {
                    if (stripos($key, 'name') !== false && !empty($value) && is_string($value)) {
                        $row['student_name'] = $value;
                        break;
                    }
                }
            }
            
            // If still empty, use a placeholder
            if (empty($row['student_name'])) {
                $row['student_name'] = 'Applicant #' . $row['id'];
            }
        }
        
        $recent_students[] = $row;
    }
}

// Debug the result
error_log("Final recent_students count: " . count($recent_students));
if (count($recent_students) > 0) {
    error_log("First student data: " . json_encode($recent_students[0]));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SCHOOL_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #6c757d;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
            --transition: all 0.3s ease;
        }
        
        body {
            background-color: #f8f9fc;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        .sidebar {
            min-height: 100vh;
            background: #4e73df;
            background: linear-gradient(180deg, #4e73df 10%, #224abe 100%);
            color: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .sidebar a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .sidebar a:hover {
            color: white;
            font-weight: 700;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: block;
            padding: 10px 15px;
            border-radius: 4px;
            transition: var(--transition);
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-menu i {
            margin-right: 8px;
            width: 20px;
            text-align: center;
        }
        
        .main-content {
            padding: 20px;
        }
        
        .stat-card {
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            transition: var(--transition);
            border-left: 0.25rem solid;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.primary { border-left-color: var(--primary-color); }
        .stat-card.success { border-left-color: var(--success-color); }
        .stat-card.warning { border-left-color: var(--warning-color); }
        .stat-card.info { border-left-color: var(--info-color); }
        
        .stat-card h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
            color: #5a5c69;
        }
        
        .stat-card p {
            font-size: 0.9rem;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 0;
            color: var(--secondary-color);
        }
        
        .stat-card .icon {
            font-size: 2rem;
            opacity: 0.3;
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .activity-item {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: var(--transition);
            border-left: 0.25rem solid transparent;
        }
        
        .activity-item:hover {
            background-color: var(--light-color);
            border-left-color: var(--primary-color);
        }
        
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 24px;
            transition: var(--transition);
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0,0,0,.05);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .card-header h5 {
            margin-bottom: 0;
            font-weight: 700;
            color: var(--dark-color);
        }
        
        .card-header .icon {
            color: var(--primary-color);
            font-size: 1.2rem;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .student-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: var(--transition);
        }
        
        .student-item:hover {
            background-color: var(--light-color);
        }
        
        .student-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: #e0e0ff;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .student-info {
            flex: 1;
        }
        
        .student-name {
            font-weight: 600;
            margin-bottom: 3px;
        }
        
        .student-reg {
            font-size: 0.85rem;
            color: var(--secondary-color);
        }
        
        .student-class {
            font-size: 0.8rem;
            background-color: #e0e0ff;
            color: var(--primary-color);
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 600;
        }
        
        .top-bar {
            padding: 16px 20px;
            background-color: white;
            border-bottom: 1px solid rgba(0,0,0,.05);
            margin-bottom: 24px;
            border-radius: 8px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .welcome-text {
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 5px;
        }
        
        .date-display {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .badge-custom {
            padding: 5px 10px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.75rem;
        }
        
        .badge-pending {
            background-color: #fff4de;
            color: #ffc107;
        }
        
        .badge-approved {
            background-color: #e0f8e9;
            color: #28a745;
        }
        
        .badge-rejected {
            background-color: #feeeee;
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'include/sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <!-- Top Bar -->
                <div class="top-bar d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="welcome-text">Welcome, <?php echo htmlspecialchars($user['first_name']); ?>!</h4>
                        <p class="date-display mb-0">
                            <i class="bi bi-calendar3"></i> <?php echo date('l, F j, Y'); ?>
                        </p>
                    </div>
                    <div>
                        <a href="profile.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-person-circle"></i> My Profile
                        </a>
                    </div>
                </div>
                <a href="../cbt/admin/send_message.php" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-envelope"></i> Contact Us
                </a>
                <!-- Stats Row -->
                <div class="row animate__animated animate__fadeIn">
                    <div class="col-md-3">
                        <div class="stat-card primary bg-white">
                            <div class="icon"><i class="bi bi-file-text"></i></div>
                            <h3><?php echo number_format($stats['total_applications']); ?></h3>
                            <p>Total Applications</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card warning bg-white">
                            <div class="icon"><i class="bi bi-clock"></i></div>
                            <h3><?php echo number_format($stats['pending_applications']); ?></h3>
                            <p>Pending Applications</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card success bg-white">
                            <div class="icon"><i class="bi bi-check-circle"></i></div>
                            <h3><?php echo number_format($stats['approved_applications']); ?></h3>
                            <p>Approved Applications</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card info bg-white">
                            <div class="icon"><i class="bi bi-cash"></i></div>
                            <h3>₦<?php echo number_format($total_amount); ?></h3>
                            <p>Total Payments</p>
                        </div>
                    </div>
                </div>

                <!-- Content Row -->
                <div class="row mt-4">
                    <!-- Recently Registered Students -->
                    <div class="col-md-6">
                        <div class="card animate__animated animate__fadeIn animate__delay-1s" id="recent-students-card">
                            <div class="card-header">
                                <h5 class="card-title">
                                    <i class="bi bi-mortarboard-fill icon me-2"></i> Recently Registered Students
                                </h5>
                                <div>
                                    <button id="refresh-students-btn" class="btn btn-sm btn-outline-primary me-2" title="Refresh">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                    <a href="students.php" class="btn btn-sm btn-primary">View All</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($recent_students)): ?>
                                    <?php foreach ($recent_students as $student): ?>
                                        <div class="student-item">
                                            <div class="student-avatar">
                                                <?php 
                                                $initials = '';
                                                $student_name = isset($student['student_name']) ? trim($student['student_name']) : '';
                                                
                                                if (!empty($student_name)) {
                                                    $name_parts = explode(' ', $student_name);
                                                    if (count($name_parts) >= 2) {
                                                        $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
                                                    } else {
                                                        $initials = strtoupper(substr($student_name, 0, 1));
                                                    }
                                                }
                                                
                                                echo !empty($initials) ? htmlspecialchars($initials) : 'S';
                                                ?>
                                            </div>
                                            <div class="student-info">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div class="student-name">
                                                        <?php 
                                                        echo !empty($student['student_name']) ? htmlspecialchars($student['student_name']) : 'Student #' . $student['id'];
                                                        ?>
                                                    </div>
                                                    <span class="student-class">
                                                        <?php echo htmlspecialchars($student['class'] ?? 'N/A'); ?>
                                                    </span>
                                                </div>
                                                <div class="student-reg">
                                                    <strong>Reg. No:</strong> <?php echo isset($student['registration_number']) ? htmlspecialchars($student['registration_number']) : 'ACE-' . date('Y') . '-' . str_pad($student['id'], 4, '0', STR_PAD_LEFT); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-info-circle text-primary" style="font-size: 2rem;"></i>
                                        <p class="mt-2 mb-0">No registered students found</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Applications -->
                    <div class="col-md-6">
                        <div class="card animate__animated animate__fadeIn animate__delay-1s">
                            <div class="card-header">
                                <h5 class="card-title">
                                    <i class="bi bi-clipboard-check icon me-2"></i> Recent Applications
                                </h5>
                                <a href="applications.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php
                                $result = $mysqli->query("
                                    SELECT a.*
                                    FROM applications a 
                                    ORDER BY submission_date DESC LIMIT 5
                                ");
                                if ($result && $result->num_rows > 0):
                                    while ($row = $result->fetch_assoc()):
                                        $name = safe_json_extract($row['applicant_data'], 'field_full_name', '');
                                        if (empty($name)) $name = "Applicant #" . $row['id'];
                                ?>
                                    <div class="activity-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo htmlspecialchars($name); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo ucfirst((string)$row['application_type']); ?> Application
                                                    <span class="ms-2">•</span>
                                                    <span class="ms-2"><?php echo date('M j, Y', strtotime($row['submission_date'])); ?></span>
                                                </small>
                                            </div>
                                            <span class="badge badge-custom badge-<?php 
                                                echo $row['status'] === 'pending' ? 'pending' : 
                                                    ($row['status'] === 'approved' ? 'approved' : 'rejected'); 
                                            ?>">
                                                <?php echo ucfirst((string)$row['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php
                                    endwhile;
                                else:
                                ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-info-circle text-primary" style="font-size: 2rem;"></i>
                                        <p class="mt-2 mb-0">No recent applications</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Second Content Row -->
                <div class="row">
                    <!-- Recent Payments -->
                    <div class="col-md-12">
                        <div class="card animate__animated animate__fadeIn animate__delay-2s">
                            <div class="card-header">
                                <h5 class="card-title">
                                    <i class="bi bi-credit-card icon me-2"></i> Recent Payments
                                </h5>
                                <a href="payments.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Amount</th>
                                                <th>Reference</th>
                                                <th>Method</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $result = $mysqli->query("
                                            SELECT p.*, a.applicant_data, a.id as application_id
                                            FROM application_payments p
                                            JOIN applications a ON p.reference = JSON_UNQUOTE(JSON_EXTRACT(a.applicant_data, '$.payment_reference'))
                                            ORDER BY p.payment_date DESC LIMIT 5
                                        ");
                                        if ($result && $result->num_rows > 0):
                                            while ($row = $result->fetch_assoc()):
                                                $name = safe_json_extract($row['applicant_data'], 'field_full_name', '');
                                                if (empty($name)) $name = "Applicant #" . $row['application_id'];
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($name); ?></td>
                                                <td><strong class="text-success">₦<?php echo number_format($row['amount'], 2); ?></strong></td>
                                                <td><small><?php echo $row['reference']; ?></small></td>
                                                <td><?php echo ucfirst((string)$row['payment_method']); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($row['payment_date'])); ?></td>
                                                <td>
                                                    <span class="badge badge-custom badge-<?php 
                                                        echo $row['status'] === 'completed' ? 'approved' : 
                                                            ($row['status'] === 'pending' ? 'pending' : 'rejected'); 
                                                    ?>">
                                                        <?php echo ucfirst((string)$row['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php
                                            endwhile;
                                        else:
                                        ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4">
                                                    <i class="bi bi-info-circle text-primary" style="font-size: 2rem;"></i>
                                                    <p class="mt-2 mb-0">No recent payments found</p>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effect to all cards
            document.querySelectorAll('.card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                    this.style.boxShadow = '0 0.5rem 2rem 0 rgba(58, 59, 69, 0.25)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = '';
                    this.style.boxShadow = '';
                });
            });

            // Auto-refresh for Recently Registered Students section
            const refreshStudentsSection = () => {
                const studentsCard = document.querySelector('#recent-students-card .card-body');
                if (!studentsCard) return;
                
                // Add loading indicator
                const loadingIndicator = document.createElement('div');
                loadingIndicator.className = 'text-center py-1 refresh-indicator';
                loadingIndicator.innerHTML = '<small class="text-muted"><i class="bi bi-arrow-repeat spin"></i> Refreshing...</small>';
                studentsCard.insertAdjacentElement('beforebegin', loadingIndicator);
                
                console.log('Refreshing students section...');
                
                fetch('ajax/get_recent_students.php')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        console.log('Response received, status:', response.status);
                        return response.text();
                    })
                    .then(html => {
                        console.log('HTML received, length:', html.length);
                        
                        // Check if there are any HTML comments with debug info
                        const debugComments = html.match(/<!--(.*?)-->/gs);
                        if (debugComments) {
                            console.log('Debug comments found:', debugComments);
                        }
                        
                        // Update the students section with new content
                        if (studentsCard) {
                            studentsCard.innerHTML = html;
                            
                            // Add a subtle flash effect to indicate new content
                            studentsCard.style.transition = 'background-color 1s';
                            studentsCard.style.backgroundColor = 'rgba(230, 255, 230, 0.5)';
                            setTimeout(() => {
                                studentsCard.style.backgroundColor = '';
                            }, 1000);
                        }
                    })
                    .catch(error => {
                        console.error('Failed to refresh students section:', error);
                    })
                    .finally(() => {
                        // Remove loading indicator
                        document.querySelector('.refresh-indicator')?.remove();
                    });
            };
            
            // Refresh every 30 seconds
            setInterval(refreshStudentsSection, 30000);

            // Add spinning animation style
            const style = document.createElement('style');
            style.textContent = `
                .spin {
                    animation: spin 1s linear infinite;
                }
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            `;
            document.head.appendChild(style);
            
            // Add manual refresh button event listener
            document.getElementById('refresh-students-btn').addEventListener('click', function() {
                // Add spinning animation to button icon
                this.querySelector('i').classList.add('spin');
                this.disabled = true;
                
                // Refresh the section
                refreshStudentsSection();
                
                // Remove spinning animation after a delay
                setTimeout(() => {
                    this.querySelector('i').classList.remove('spin');
                    this.disabled = false;
                }, 1000);
            });
        });
    </script>
</body>
</html> 