<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';
require_once 'class_teacher_auth.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Authentication is now handled by class_teacher_auth.php
// The check is automatically performed when the file is included

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Get class teacher information
$userId = $_SESSION['user_id'];
$teacherName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$className = $_SESSION['class_name'] ?? '';

// Try multiple approaches to find the teacher
$teacherQuery = "SELECT ct.*, t.first_name, t.last_name, t.id as teacher_id
                FROM class_teachers ct
                JOIN teachers t ON ct.teacher_id = t.id
                WHERE (ct.user_id = ? OR CONCAT(t.first_name, ' ', t.last_name) = ?)
                AND ct.is_active = 1";

$stmt = $conn->prepare($teacherQuery);
$stmt->bind_param("is", $userId, $teacherName);
$stmt->execute();
$result = $stmt->get_result();

// If no results, try a more general query by name
if ($result->num_rows === 0 && !empty($teacherName)) {
    $parts = explode(' ', $teacherName);
    $firstName = $parts[0] ?? '';
    $lastName = end($parts) ?? '';
    
    $teacherQuery = "SELECT ct.*, t.first_name, t.last_name, t.id as teacher_id
                    FROM class_teachers ct
                    JOIN teachers t ON ct.teacher_id = t.id
                    WHERE (t.first_name LIKE ? AND t.last_name LIKE ?)
                    AND ct.is_active = 1";
    
    $firstNameParam = "%$firstName%";
    $lastNameParam = "%$lastName%";
    
    $stmt = $conn->prepare($teacherQuery);
    $stmt->bind_param("ss", $firstNameParam, $lastNameParam);
    $stmt->execute();
    $result = $stmt->get_result();
}

if ($result->num_rows === 0) {
    echo "<div style='margin: 50px; padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<h3>Error: You are not assigned as a class teacher.</h3>";
    echo "<p><a href='login.php' class='btn btn-primary'>Return to Login</a></p>";
    echo "</div>";
    exit;
}

$classTeacher = $result->fetch_assoc();
$classTeacherId = $classTeacher['id'];
$className = $className ?: $classTeacher['class_name']; // Use from session or DB

// Get students assigned to this class
$studentsQuery = "SELECT s.* 
                 FROM students s
                 WHERE s.class = ?
                 ORDER BY s.first_name, s.last_name";

$stmt = $conn->prepare($studentsQuery);
$stmt->bind_param("s", $className);
$stmt->execute();
$studentsResult = $stmt->get_result();
$students = [];

while ($row = $studentsResult->fetch_assoc()) {
    $students[] = $row;
}

// If still no students found, try a LIKE query as a fallback
if (count($students) === 0) {
    $likePattern = "%" . $className . "%";
    $studentsQuery = "SELECT s.* 
                     FROM students s
                     WHERE s.class LIKE ?
                     ORDER BY s.first_name, s.last_name";
    
    $stmt = $conn->prepare($studentsQuery);
    $stmt->bind_param("s", $likePattern);
    $stmt->execute();
    $studentsResult = $stmt->get_result();
    
    while ($row = $studentsResult->fetch_assoc()) {
        $students[] = $row;
    }
}

// Get recent activities
$activitiesQuery = "SELECT cta.*, s.first_name, s.last_name
                   FROM class_teacher_activities cta
                   JOIN students s ON cta.student_id = s.id
                   WHERE cta.class_teacher_id = ?
                   ORDER BY cta.created_at DESC
                   LIMIT 10";

$stmt = $conn->prepare($activitiesQuery);
$stmt->bind_param("i", $classTeacherId);
$stmt->execute();
$activitiesResult = $stmt->get_result();
$activities = [];

while ($row = $activitiesResult->fetch_assoc()) {
    $activities[] = $row;
}

// Dashboard stats
$totalStudents = count($students);
$maleStudents = 0;
$femaleStudents = 0;

foreach ($students as $student) {
    if (isset($student['gender'])) {
        $gender = strtolower($student['gender']);
        if ($gender == 'male') {
            $maleStudents++;
        } else if ($gender == 'female') {
            $femaleStudents++;
        }
    }
}

// Include header/dashboard template
include '../admin/include/header.php';
?>

<div class="content-wrapper">
    <!-- Floating Quick Nav Menu -->
    <div class="quick-nav-menu">
        <button class="quick-nav-toggle" id="quickNavToggle">
            <i class="fas fa-compass"></i>
        </button>
        <div class="quick-nav-items" id="quickNavItems">
            <a href="#teacherInfo" class="quick-nav-item" data-toggle="tooltip" title="Teacher Info">
                <i class="fas fa-user-tie"></i>
            </a>
            <a href="#statsSection" class="quick-nav-item" data-toggle="tooltip" title="Statistics">
                <i class="fas fa-chart-pie"></i>
            </a>
            <a href="#activitiesSection" class="quick-nav-item" data-toggle="tooltip" title="Activities">
                <i class="fas fa-history"></i>
            </a>
            <a href="#actionsSection" class="quick-nav-item" data-toggle="tooltip" title="Quick Actions">
                <i class="fas fa-bolt"></i>
            </a>
            <a href="#studentsSection" class="quick-nav-item" data-toggle="tooltip" title="Students">
                <i class="fas fa-user-graduate"></i>
            </a>
        </div>
    </div>
    
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-3">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark"><i class="fas fa-chalkboard-teacher mr-2"></i>Class Teacher Dashboard</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="#"><i class="fas fa-home"></i> Home</a></li>
                        <li class="breadcrumb-item active">Class Teacher Dashboard</li>
                    </ol>
                </div>
            </div>
            <!-- Dashboard Actions Toolbar -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="dashboard-toolbar">
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#helpModal">
                                <i class="fas fa-question-circle"></i> Help
                            </button>
                            <a href="students.php" class="btn btn-outline-info">
                                <i class="fas fa-users"></i> Students
                            </a>
                            <a href="activities.php" class="btn btn-outline-warning">
                                <i class="fas fa-clipboard-list"></i> Activities
                            </a>
                            <a href="reports.php" class="btn btn-outline-success">
                                <i class="fas fa-chart-bar"></i> Reports
                            </a>
                        </div>
                        <div class="toolbar-search">
                            <div class="input-group">
                                <input type="text" id="globalSearch" class="form-control" placeholder="Search...">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="card card-primary card-outline elevation-3" id="teacherInfo">
                        <div class="card-header bg-gradient-primary text-white">
                            <h3 class="card-title">
                                <i class="fas fa-user-tie mr-2"></i> Class Teacher Information
                            </h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong><i class="fas fa-user mr-1"></i> Name:</strong> <?php echo $classTeacher['first_name'] . ' ' . $classTeacher['last_name']; ?></p>
                                    <p><strong><i class="fas fa-calendar-alt mr-1"></i> Academic Year:</strong> <?php echo $classTeacher['academic_year']; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong><i class="fas fa-chalkboard mr-1"></i> Class:</strong> <?php echo $className; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4" id="statsSection">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-gradient-info elevation-3">
                        <div class="inner">
                            <h3><?php echo $totalStudents; ?></h3>
                            <p>Total Students</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <a href="students.php" class="small-box-footer">
                            View Students <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-gradient-success elevation-3">
                        <div class="inner">
                            <h3><?php echo $maleStudents; ?></h3>
                            <p>Male Students</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-male"></i>
                        </div>
                        <a href="students.php?gender=male" class="small-box-footer">
                            View Male Students <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-gradient-warning elevation-3">
                        <div class="inner">
                            <h3><?php echo $femaleStudents; ?></h3>
                            <p>Female Students</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-female"></i>
                        </div>
                        <a href="students.php?gender=female" class="small-box-footer">
                            View Female Students <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-gradient-danger elevation-3">
                        <div class="inner">
                            <h3><?php echo count($activities); ?></h3>
                            <p>Recent Activities</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <a href="activities.php" class="small-box-footer">
                            View Activities <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="row" id="activitiesSection">
                <div class="col-md-6">
                    <div class="card card-primary elevation-3">
                        <div class="card-header bg-gradient-primary">
                            <h3 class="card-title"><i class="fas fa-history mr-2"></i>Recent Activities</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($activities) > 0): ?>
                            <ul class="list-group">
                                <?php foreach ($activities as $activity): ?>
                                <li class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h5 class="mb-1"><?php echo $activity['first_name'] . ' ' . $activity['last_name']; ?></h5>
                                        <small class="text-muted"><i class="far fa-calendar-alt mr-1"></i><?php echo date('M d, Y', strtotime($activity['activity_date'])); ?></small>
                                    </div>
                                    <p class="mb-1"><strong><?php echo ucfirst($activity['activity_type']); ?>:</strong> 
                                    <?php echo substr($activity['description'], 0, 100) . (strlen($activity['description']) > 100 ? '...' : ''); ?></p>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            <div class="alert alert-info m-3">
                                <i class="icon fas fa-info-circle"></i> No recent activities found.
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <a href="activities.php" class="btn btn-primary btn-block"><i class="fas fa-list mr-2"></i>View All Activities</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6" id="actionsSection">
                    <div class="card card-success elevation-3">
                        <div class="card-header bg-gradient-success">
                            <h3 class="card-title"><i class="fas fa-bolt mr-2"></i>Quick Actions</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <a href="students.php" class="btn btn-block btn-info btn-lg elevation-1">
                                        <i class="fas fa-users mr-2"></i> Manage Students
                                    </a>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <a href="activities.php" class="btn btn-block btn-warning btn-lg elevation-1">
                                        <i class="fas fa-clipboard-list mr-2"></i> Record Activities
                                    </a>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <a href="comments.php" class="btn btn-block btn-primary elevation-1">
                                        <i class="fas fa-comment mr-2"></i> Student Comments
                                    </a>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <a href="payments.php" class="btn btn-block btn-success elevation-1">
                                        <i class="fas fa-money-bill-wave mr-2"></i> Record Payments
                                    </a>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <a href="reports.php" class="btn btn-block btn-danger elevation-1">
                                        <i class="fas fa-chart-bar mr-2"></i> Generate Reports
                                    </a>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <a href="../cbt/results.php" class="btn btn-block btn-secondary elevation-1">
                                        <i class="fas fa-poll mr-2"></i> View Exam Results
                                    </a>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <a href="activate_results.php" class="btn btn-block btn-success elevation-1">
                                        <i class="fas fa-check-circle mr-2"></i> Activate Exam Results
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Student List Section -->
            <div class="row mt-4" id="studentsSection">
                <div class="col-md-12">
                    <div class="card card-secondary elevation-3">
                        <div class="card-header bg-gradient-secondary">
                            <h3 class="card-title">
                                <i class="fas fa-user-graduate mr-2"></i> Students in <?php echo $className; ?>
                            </h3>
                            <div class="card-tools">
                                <div class="input-group input-group-sm" style="width: 250px;">
                                    <input type="text" id="studentSearch" class="form-control float-right" placeholder="Search Student">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-default">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($students) > 0): ?>
                            <div class="table-responsive">
                                <div class="p-3 d-flex justify-content-between">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-outline-primary btn-filter" data-filter="all">
                                            <i class="fas fa-users"></i> All
                                        </button>
                                        <button type="button" class="btn btn-outline-info btn-filter" data-filter="male">
                                            <i class="fas fa-male"></i> Male
                                        </button>
                                        <button type="button" class="btn btn-outline-warning btn-filter" data-filter="female">
                                            <i class="fas fa-female"></i> Female
                                        </button>
                                    </div>
                                    <div class="d-flex">
                                        <select class="form-control mr-2" id="entriesPerPage">
                                            <option value="10">10 per page</option>
                                            <option value="25">25 per page</option>
                                            <option value="50">50 per page</option>
                                            <option value="100">100 per page</option>
                                        </select>
                                        <button class="btn btn-outline-secondary" id="printStudentList">
                                            <i class="fas fa-print"></i> Print
                                        </button>
                                    </div>
                                </div>
                                <table class="table table-hover text-nowrap" id="studentTable">
                                    <thead class="thead-light">
                                        <tr>
                                            <th><i class="fas fa-user mr-1"></i> Name <a href="#" class="sort-btn" data-sort="name"><i class="fas fa-sort"></i></a></th>
                                            <th><i class="fas fa-id-card mr-1"></i> Registration Number <a href="#" class="sort-btn" data-sort="reg"><i class="fas fa-sort"></i></a></th>
                                            <th><i class="fas fa-venus-mars mr-1"></i> Gender <a href="#" class="sort-btn" data-sort="gender"><i class="fas fa-sort"></i></a></th>
                                            <th><i class="fas fa-phone mr-1"></i> Contact</th>
                                            <th><i class="fas fa-cogs mr-1"></i> Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($students as $student): ?>
                                        <tr data-gender="<?php echo strtolower($student['gender'] ?? ''); ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($student['registration_number'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php if (isset($student['gender']) && strtolower($student['gender']) == 'male'): ?>
                                                    <span style="background-color: #007bff; color: #fff;" class="badge badge-info"><i class="fas fa-male mr-1"></i> Male</span>
                                                <?php elseif (isset($student['gender']) && strtolower($student['gender']) == 'female'): ?>
                                                    <span style="background-color: #ffc107; color: #fff;" class="badge badge-warning"><i class="fas fa-female mr-1"></i> Female</span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    $contact = $student['parent_phone'] ?? ($student['father_s_contact_phone_number_s_'] ?? ($student['mother_s_contact_phone_number_s_'] ?? 'N/A'));
                                                    echo htmlspecialchars($contact);
                                                ?>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="student_details.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                    <a href="student_edit.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-secondary dropdown-toggle" data-toggle="dropdown">
                                                        <i class="fas fa-cog"></i>
                                                    </button>
                                                    <div class="dropdown-menu">
                                                        <a class="dropdown-item" href="record_activity.php?student_id=<?php echo $student['id']; ?>">
                                                            <i class="fas fa-clipboard-list text-warning"></i> Record Activity
                                                        </a>
                                                        <a class="dropdown-item" href="student_comment.php?student_id=<?php echo $student['id']; ?>">
                                                            <i class="fas fa-comment text-primary"></i> Add Comment
                                                        </a>
                                                        <a class="dropdown-item" href="update_payment.php?student_id=<?php echo $student['id']; ?>">
                                                            <i class="fas fa-money-bill-wave text-success"></i> Record Payment
                                                        </a>
                                                        <div class="dropdown-divider"></div>
                                                        <a class="dropdown-item" href="student_report.php?student_id=<?php echo $student['id']; ?>">
                                                            <i class="fas fa-file-alt text-info"></i> Generate Report
                                                        </a>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <div class="card-footer p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            Showing <span id="shownEntries">1-<?php echo min(10, count($students)); ?></span> of <?php echo count($students); ?> entries
                                        </div>
                                        <div class="pagination-container">
                                            <ul class="pagination pagination-sm m-0">
                                                <li class="page-item"><a class="page-link" href="#" id="prevPage">&laquo;</a></li>
                                                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                                <li class="page-item"><a class="page-link" href="#">2</a></li>
                                                <li class="page-item"><a class="page-link" href="#">3</a></li>
                                                <li class="page-item"><a class="page-link" href="#" id="nextPage">&raquo;</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info m-3">
                                <i class="icon fas fa-info-circle"></i> No students found for class <?php echo $className; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <a href="students.php" class="btn btn-primary">
                                <i class="fas fa-users mr-2"></i> Manage All Students
                            </a>
                            <a href="add_student.php" class="btn btn-success float-right">
                                <i class="fas fa-user-plus mr-2"></i> Add New Student
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Help Modal -->
    <div class="modal fade" id="helpModal" tabindex="-1" role="dialog" aria-labelledby="helpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="helpModalLabel"><i class="fas fa-question-circle mr-2"></i>Dashboard Help</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5><i class="fas fa-compass mr-2"></i>Navigation</h5>
                                </div>
                                <div class="card-body">
                                    <ul class="list-group">
                                        <li class="list-group-item"><i class="fas fa-user-tie text-primary mr-2"></i> View your class information</li>
                                        <li class="list-group-item"><i class="fas fa-users text-info mr-2"></i> Manage your students</li>
                                        <li class="list-group-item"><i class="fas fa-clipboard-list text-warning mr-2"></i> Record student activities</li>
                                        <li class="list-group-item"><i class="fas fa-chart-bar text-success mr-2"></i> Generate reports</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5><i class="fas fa-lightbulb mr-2"></i>Quick Tips</h5>
                                </div>
                                <div class="card-body">
                                    <ul class="list-group">
                                        <li class="list-group-item">Use the floating navigation to quickly jump to sections</li>
                                        <li class="list-group-item">Use the search box to find specific students</li>
                                        <li class="list-group-item">Click on student names to view detailed profiles</li>
                                        <li class="list-group-item">Use the quick action buttons for common tasks</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
:root {
    --primary: #4e73df;
    --primary-dark: #3a56b8;
    --secondary: #f44336;
    --success: #1cc88a;
    --warning: #f6c23e;
    --danger: #e74a3b;
    --info: #36b9cc;
    --dark: #5a5c69;
    --light: #f8f9fc;
}

/* Enhanced General Styles */
.zoom-effect {
    transform: scale(1.05);
    transition: transform 0.3s ease-in-out;
}

.small-box {
    transition: all 0.3s ease;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.small-box:hover {
    box-shadow: 0 8px 15px rgba(0,0,0,0.2);
}

.btn {
    transition: all 0.3s ease;
    border-radius: 6px;
    font-weight: 500;
    letter-spacing: 0.3px;
}

.btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.card {
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
    border: none;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    overflow: hidden;
}

.card:hover {
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

.card-header {
    padding: 1rem 1.25rem;
    border-bottom: none;
}

.list-group-item-action:hover {
    background-color: #f8f9fa;
    transform: translateX(5px);
    transition: all 0.3s ease;
}

/* Enhanced Dashboard Toolbar */
.dashboard-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.toolbar-search {
    width: 280px;
}

.toolbar-search .input-group {
    box-shadow: 0 2px 5px rgba(0,0,0,0.08);
    border-radius: 50px;
    overflow: hidden;
}

.toolbar-search .form-control {
    border-radius: 50px 0 0 50px;
    border: none;
    padding-left: 15px;
}

.toolbar-search .input-group-append button {
    border-radius: 0 50px 50px 0;
    border: none;
    background: var(--primary);
    color: white;
}

/* Enhanced Quick Navigation Menu */
.quick-nav-menu {
    position: fixed;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 1000;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.quick-nav-toggle {
    width: 55px;
    height: 55px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
    color: white;
    border: none;
    display: flex;
    justify-content: center;
    align-items: center;
    box-shadow: 0 4px 15px rgba(78, 115, 223, 0.4);
    cursor: pointer;
    margin-bottom: 15px;
    transition: all 0.3s ease;
}

.quick-nav-toggle:hover {
    background: linear-gradient(135deg, #3a56b8 0%, #1a3a9c 100%);
    transform: scale(1.1) rotate(45deg);
}

.quick-nav-items {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.quick-nav-item {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: white;
    color: var(--dark);
    display: flex;
    justify-content: center;
    align-items: center;
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    transition: all 0.3s ease;
}

.quick-nav-item:hover {
    background: var(--light);
    color: var(--primary);
    transform: scale(1.15);
}

.quick-nav-item.active {
    background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
    color: white;
}

/* Enhanced Small Boxes */
.small-box .inner {
    padding: 20px;
}

.small-box .icon {
    transition: all 0.3s ease;
    font-size: 50px;
    opacity: 0.3;
}

.small-box:hover .icon {
    transform: scale(1.1);
    opacity: 0.5;
}

.small-box .small-box-footer {
    background: rgba(0, 0, 0, 0.1);
    padding: 10px;
    color: rgba(255, 255, 255, 0.9);
    transition: all 0.3s ease;
}

.small-box:hover .small-box-footer {
    background: rgba(0, 0, 0, 0.15);
    padding-right: 5px;
}

.bg-gradient-info {
    background: linear-gradient(135deg, #36b9cc 0%, #1a8ea0 100%);
}

.bg-gradient-success {
    background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%);
}

.bg-gradient-warning {
    background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%);
}

.bg-gradient-danger {
    background: linear-gradient(135deg, #e74a3b 0%, #be2617 100%);
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
}

.bg-gradient-secondary {
    background: linear-gradient(135deg, #858796 0%, #60616f 100%);
}

/* Enhanced Table Styling */
.table {
    color: #333;
}

.table thead th {
    background: linear-gradient(to right, #f8f9fa, #ffffff);
    border-top: none;
    border-bottom: 2px solid #e3e6f0;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.05em;
    padding: 12px;
}

.table tbody tr {
    transition: all 0.3s ease;
}

.table tbody tr:hover {
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
    transform: translateY(-2px);
    z-index: 1;
    position: relative;
    background-color: #f8f9fc;
}

.table .badge {
    font-size: 85%;
    font-weight: 500;
    padding: 0.4em 0.8em;
    border-radius: 30px;
}

.table .btn-group .btn {
    border-radius: 6px;
    margin-right: 2px;
}

/* Enhanced Cards Styling */
.card-primary .card-header {
    background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
    color: white;
    border-bottom: none;
}

.card-success .card-header {
    background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%);
    color: white;
    border-bottom: none;
}

.card-secondary .card-header {
    background: linear-gradient(135deg, #858796 0%, #60616f 100%);
    color: white;
    border-bottom: none;
}

.card-info .card-header {
    background: linear-gradient(135deg, #36b9cc 0%, #1a8ea0 100%);
    color: white;
    border-bottom: none;
}

.card-warning .card-header {
    background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%);
    color: white;
    border-bottom: none;
}

.card-danger .card-header {
    background: linear-gradient(135deg, #e74a3b 0%, #be2617 100%);
    color: white;
    border-bottom: none;
}

/* List Group Enhancements */
.list-group-item {
    border-left: none;
    border-right: none;
    padding: 15px;
    transition: all 0.3s ease;
}

.list-group-item:first-child {
    border-top: none;
    border-top-left-radius: 10px;
    border-top-right-radius: 10px;
}

.list-group-item:last-child {
    border-bottom: none;
    border-bottom-left-radius: 10px;
    border-bottom-right-radius: 10px;
}

.list-group-item h5 {
    font-weight: 600;
    color: #4e73df;
}

.list-group-item small {
    font-weight: 500;
}

/* Button Enhancements */
.btn-primary {
    background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
    border: none;
}

.btn-success {
    background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%);
    border: none;
}

.btn-info {
    background: linear-gradient(135deg, #36b9cc 0%, #1a8ea0 100%);
    border: none;
}

.btn-warning {
    background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%);
    border: none;
}

.btn-danger {
    background: linear-gradient(135deg, #e74a3b 0%, #be2617 100%);
    border: none;
}

.btn-secondary {
    background: linear-gradient(135deg, #858796 0%, #60616f 100%);
    border: none;
}

.btn-outline-primary {
    color: #4e73df;
    border-color: #4e73df;
}

.btn-outline-primary:hover {
    background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
    border-color: #224abe;
}

.btn-outline-success {
    color: #1cc88a;
    border-color: #1cc88a;
}

.btn-outline-success:hover {
    background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%);
    border-color: #13855c;
}

.btn-outline-info {
    color: #36b9cc;
    border-color: #36b9cc;
}

.btn-outline-info:hover {
    background: linear-gradient(135deg, #36b9cc 0%, #1a8ea0 100%);
    border-color: #1a8ea0;
}

.btn-outline-warning {
    color: #f6c23e;
    border-color: #f6c23e;
}

.btn-outline-warning:hover {
    background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%);
    border-color: #dda20a;
}

/* Pagination Styling */
.pagination {
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.pagination .page-item .page-link {
    padding: 0.5rem 0.75rem;
    margin: 0;
    border: none;
    color: #4e73df;
    font-weight: 500;
}

.pagination .page-item.active .page-link {
    background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
    color: white;
}

.pagination .page-item .page-link:hover {
    background-color: #f8f9fc;
    color: #224abe;
}

/* Enhanced Search Functionality */
#globalSearch, #studentSearch {
    border-radius: 50px;
    padding-left: 15px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    border: none;
    transition: all 0.3s ease;
}

#globalSearch:focus, #studentSearch:focus {
    box-shadow: 0 3px 10px rgba(78, 115, 223, 0.25);
}

.search-highlight {
    box-shadow: 0 0 15px rgba(78, 115, 223, 0.5) !important;
    border: 2px solid #4e73df !important;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(78, 115, 223, 0.5);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(78, 115, 223, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(78, 115, 223, 0);
    }
}

/* Dropdown Menu Enhancements */
.dropdown-menu {
    border: none;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    padding: 0.5rem 0;
}

.dropdown-item {
    padding: 0.6rem 1.5rem;
    transition: all 0.2s ease;
}

.dropdown-item:hover {
    background-color: #f8f9fc;
    color: #4e73df;
    transform: translateX(5px);
}

.dropdown-item i {
    margin-right: 10px;
    font-size: 0.9rem;
}

.dropdown-divider {
    margin: 0.5rem 0;
}

/* Modal Enhancements */
.modal-content {
    border: none;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    overflow: hidden;
}

.modal-header {
    border-bottom: none;
    padding: 20px 25px;
}

.modal-body {
    padding: 25px;
}

.modal-footer {
    border-top: none;
    padding: 20px 25px;
}

/* Enhanced Filter Buttons */
.btn-filter {
    border-radius: 30px;
    padding: 0.5rem 1rem;
    transition: all 0.3s ease;
}

.btn-filter.active {
    transform: scale(1.05);
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}

/* Card Footer Enhancements */
.card-footer {
    background: linear-gradient(to right, #f8f9fa, #ffffff);
    border-top: none;
    padding: 15px 25px;
}

/* Form Control Enhancements */
.form-control {
    border-radius: 6px;
    border: 1px solid #e3e6f0;
    padding: 0.6rem 1rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
    border-color: #bac8f3;
}

/* Enhanced Mobile Responsiveness */
@media (max-width: 768px) {
    /* Container adjustments */
    .container-fluid {
        padding: 10px;
    }

    /* Card adjustments */
    .card {
        margin-bottom: 15px;
    }

    .card-body {
        padding: 15px;
    }

    /* Stats boxes */
    .small-box {
        margin-bottom: 15px;
    }

    .small-box h3 {
        font-size: 1.5rem;
    }

    .small-box p {
        font-size: 0.9rem;
    }

    /* Quick nav menu repositioning */
    .quick-nav-menu {
        position: fixed;
        bottom: 20px;
        right: 20px;
        top: auto;
        transform: none;
        flex-direction: row;
        z-index: 1050;
    }

    .quick-nav-items {
        display: none;
        position: absolute;
        bottom: 60px;
        right: 0;
        background: white;
        border-radius: 10px;
        box-shadow: 0 0 15px rgba(0,0,0,0.1);
        padding: 10px;
        flex-direction: column;
    }

    .quick-nav-items.show {
        display: flex;
    }

    .quick-nav-item {
        margin: 5px;
    }

    /* Dashboard toolbar */
    .dashboard-toolbar {
        flex-direction: column;
        gap: 10px;
        padding: 10px;
    }

    .dashboard-toolbar .btn-group {
        width: 100%;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 5px;
    }

    .dashboard-toolbar .btn {
        margin: 0;
        width: 100%;
    }

    .toolbar-search {
        width: 100%;
    }

    /* Table responsiveness */
    .table-responsive {
        margin-bottom: 15px;
        border: 0;
    }

    .table {
        min-width: 650px;
    }

    /* Card header adjustments */
    .card-header {
        padding: 12px 15px;
    }

    .card-header h3 {
        font-size: 1.1rem;
    }

    /* Button adjustments */
    .btn {
        padding: 0.375rem 0.75rem;
        font-size: 0.9rem;
    }

    /* Pagination adjustments */
    .pagination {
        justify-content: center;
        flex-wrap: wrap;
    }

    .pagination .page-link {
        padding: 0.4rem 0.6rem;
    }
}

/* Extra small devices */
@media (max-width: 576px) {
    /* Header adjustments */
    .content-header h1 {
        font-size: 1.5rem;
    }

    .breadcrumb {
        display: none;
    }

    /* Table adjustments for mobile */
    .table-responsive table {
        display: block;
    }

    .table-responsive thead {
        display: none;
    }

    .table-responsive tbody tr {
        display: block;
        margin-bottom: 1rem;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    .table-responsive td {
        display: block;
        text-align: left;
        padding: 0.75rem;
        position: relative;
        padding-left: 50%;
    }

    .table-responsive td::before {
        content: attr(data-label);
        position: absolute;
        left: 0.75rem;
        width: 45%;
        font-weight: bold;
    }

    /* Action buttons in table */
    .table-responsive .btn-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .table-responsive .btn-group .btn {
        width: 100%;
        margin: 0;
    }

    /* Stats boxes full width */
    .col-lg-3.col-6 {
        flex: 0 0 100%;
        max-width: 100%;
    }

    /* Modal adjustments */
    .modal-dialog {
        margin: 0.5rem;
    }

    .modal-content {
        border-radius: 10px;
    }

    /* Help modal adjustments */
    #helpModal .row {
        flex-direction: column;
    }

    #helpModal .col-md-6 {
        margin-bottom: 1rem;
    }

    /* Filter buttons */
    .btn-filter {
        font-size: 0.8rem;
        padding: 0.3rem 0.6rem;
    }

    /* Entries per page selector */
    #entriesPerPage {
        width: 100%;
        margin-bottom: 10px;
    }

    /* Print button */
    #printStudentList {
        width: 100%;
    }
}

/* Landscape orientation adjustments */
@media (max-height: 500px) and (orientation: landscape) {
    .quick-nav-menu {
        display: none;
    }

    .content-header {
        margin-bottom: 10px;
    }

    .small-box {
        margin-bottom: 10px;
    }
}

/* Dark mode support for OLED screens */
@media (prefers-color-scheme: dark) {
    .table-responsive tbody tr {
        background:rgb(255, 255, 255);
        border-color: #rgb(255, 255, 255);;
    }

    .card {
        background: #rgb(255, 255, 255);;
    }

    .modal-content {
        background: #rgb(255, 255, 255);;
    }
}

/* Print optimization */
@media print {
    .quick-nav-menu,
    .dashboard-toolbar,
    .btn-group,
    .card-tools {
        display: none !important;
    }

    .card {
        break-inside: avoid;
    }

    .table-responsive {
        overflow: visible !important;
    }
}
</style>

<script>
$(document).ready(function() {
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Student search functionality
    $("#studentSearch").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#studentTable tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
    
    // Global search functionality
    $("#globalSearch").on("keyup", function() {
        const searchValue = $(this).val().toLowerCase();
        
        // Search in student table
        $("#studentTable tbody tr").each(function() {
            const studentData = $(this).text().toLowerCase();
            $(this).toggle(studentData.includes(searchValue));
        });
        
        // Search in activities list
        $(".list-group-item").each(function() {
            const activityData = $(this).text().toLowerCase();
            $(this).toggle(activityData.includes(searchValue));
        });
        
        // Search in cards
        $(".card").each(function() {
            const cardData = $(this).text().toLowerCase();
            if (cardData.includes(searchValue)) {
                $(this).show();
                // Highlight matching card with a subtle animation
                $(this).addClass('search-highlight');
                setTimeout(() => {
                    $(this).removeClass('search-highlight');
                }, 2000);
            } else {
                // Only hide cards that are not main section cards
                if (!$(this).closest('.content-wrapper').length) {
                    $(this).hide();
                }
            }
        });
        
        // Update "Showing X-Y of Z entries" text for student table
        updateEntriesInfo();
    });
    
    // Add search button click handler
    $('.toolbar-search .btn').on('click', function() {
        // Trigger search on button click
        $('#globalSearch').trigger('keyup');
    });
    
    // Clear search when clicking the clear button (x) in the search input
    $('#globalSearch').on('search', function() {
        if ($(this).val() === '') {
            // Show all elements when search is cleared
            $("#studentTable tbody tr").show();
            $(".list-group-item").show();
            $(".card").show();
            updateEntriesInfo();
        }
    });
    
    // Function to update entries info
    function updateEntriesInfo() {
        const visibleRows = $("#studentTable tbody tr:visible").length;
        const totalRows = $("#studentTable tbody tr").length;
        const entriesPerPage = parseInt($("#entriesPerPage").val()) || 10;
        const start = Math.min(1, visibleRows);
        const end = Math.min(entriesPerPage, visibleRows);
        
        $("#shownEntries").text(start + "-" + end + " of " + visibleRows);
        
        // Update pagination if needed
        updatePagination();
    }
    
    // Add keyboard shortcut (Ctrl/Cmd + F) to focus search
    $(document).on('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            $('#globalSearch').focus();
        }
    });
    
    // Add clear search button
    $('.toolbar-search .input-group').append(
        '<div class="input-group-append">' +
        '<button class="btn btn-outline-secondary clear-search" type="button" title="Clear Search">' +
        '<i class="fas fa-times"></i>' +
        '</button>' +
        '</div>'
    );
    
    // Clear search button handler
    $('.clear-search').on('click', function() {
        $('#globalSearch').val('').trigger('keyup');
    });
    
    // Add search type indicator
    let searchTimeout;
    $('#globalSearch').on('input', function() {
        clearTimeout(searchTimeout);
        const $this = $(this);
        
        // Show searching indicator
        if ($this.val()) {
            $('.toolbar-search .input-group').addClass('searching');
        } else {
            $('.toolbar-search .input-group').removeClass('searching');
        }
        
        // Debounce search for better performance
        searchTimeout = setTimeout(function() {
            $this.trigger('keyup');
        }, 300);
    });
    
    // Quick navigation toggle
    $("#quickNavToggle").click(function() {
        $("#quickNavItems").toggle();
    });
    
    // Smooth scrolling for navigation
    $(".quick-nav-item").click(function(e) {
        e.preventDefault();
        var target = $(this).attr("href");
        $('html, body').animate({
            scrollTop: $(target).offset().top - 70
        }, 300);
        $(".quick-nav-item").removeClass("active");
        $(this).addClass("active");
    });
    
    // Scroll spy for navigation highlighting
    $(window).scroll(function() {
        var scrollPosition = $(window).scrollTop();
        
        // Check each section and update active class
        $("section, .card").each(function() {
            var offsetTop = $(this).offset().top - 100;
            var height = $(this).height();
            
            if(scrollPosition >= offsetTop && scrollPosition < offsetTop + height) {
                var id = $(this).attr('id');
                $('.quick-nav-item').removeClass('active');
                $('.quick-nav-item[href="#'+id+'"]').addClass('active');
            }
        });
    });
    
    // Add table hover effect
    $("#studentTable tbody tr").hover(
        function() {
            $(this).addClass('bg-light');
        },
        function() {
            $(this).removeClass('bg-light');
        }
    );
    
    // Add animation to info boxes
    $('.small-box').hover(
        function() {
            $(this).addClass('zoom-effect');
        },
        function() {
            $(this).removeClass('zoom-effect');
        }
    );
    
    // Collapsible cards
    $('[data-card-widget="collapse"]').click(function() {
        var card = $(this).closest('.card');
        if (card.hasClass('collapsed-card')) {
            card.removeClass('collapsed-card');
            card.find('.card-body, .card-footer').slideDown();
            $(this).find('i').removeClass('fa-plus').addClass('fa-minus');
        } else {
            card.addClass('collapsed-card');
            card.find('.card-body, .card-footer').slideUp();
            $(this).find('i').removeClass('fa-minus').addClass('fa-plus');
        }
    });
    
    // Student table filtering
    $(".btn-filter").click(function() {
        var filter = $(this).data("filter");
        
        // Update active button
        $(".btn-filter").removeClass("active");
        $(this).addClass("active");
        
        if (filter === "all") {
            $("#studentTable tbody tr").show();
        } else {
            $("#studentTable tbody tr").hide();
            $("#studentTable tbody tr[data-gender='" + filter + "']").show();
        }
        
        // Update pagination after filtering
        updatePagination();
    });
    
    // Table sorting
    $(".sort-btn").click(function(e) {
        e.preventDefault();
        var sortType = $(this).data("sort");
        var rows = $("#studentTable tbody tr").get();
        
        rows.sort(function(a, b) {
            var A, B;
            
            if (sortType === "name") {
                A = $(a).find("td:first-child").text().trim();
                B = $(b).find("td:first-child").text().trim();
            } else if (sortType === "reg") {
                A = $(a).find("td:nth-child(2)").text().trim();
                B = $(b).find("td:nth-child(2)").text().trim();
            } else if (sortType === "gender") {
                A = $(a).data("gender");
                B = $(b).data("gender");
            }
            
            if ($(this).hasClass("asc")) {
                return A.localeCompare(B);
            } else {
                return B.localeCompare(A);
            }
        });
        
        // Toggle sort direction
        if ($(this).hasClass("asc")) {
            $(this).removeClass("asc").addClass("desc");
            $(this).find("i").removeClass("fa-sort").addClass("fa-sort-down");
        } else {
            $(this).removeClass("desc").addClass("asc");
            $(this).find("i").removeClass("fa-sort-down").addClass("fa-sort-up");
        }
        
        // Clear sorting indicators from other columns
        $(".sort-btn").not(this).find("i").removeClass("fa-sort-up fa-sort-down").addClass("fa-sort");
        $(".sort-btn").not(this).removeClass("asc desc");
        
        // Rebuild the table with sorted rows
        $.each(rows, function(index, row) {
            $("#studentTable tbody").append(row);
        });
        
        // Update pagination after sorting
        updatePagination();
    });
    
    // Entries per page change
    $("#entriesPerPage").change(function() {
        currentPage = 1;
        entriesPerPage = parseInt($(this).val());
        updatePagination();
    });
    
    // Print student list
    $("#printStudentList").click(function() {
        var printContents = "<html><head><title>Student List</title>";
        printContents += "<link rel='stylesheet' href='https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css'>";
        printContents += "<style>body { padding: 20px; } .table th, .table td { padding: 8px; }</style>";
        printContents += "</head><body>";
        printContents += "<h3>Class: " + "<?php echo $className; ?>" + " - Student List</h3>";
        printContents += "<table class='table table-bordered'>";
        printContents += $("#studentTable thead").html();
        
        // Only include visible rows (respecting filters)
        printContents += "<tbody>";
        $("#studentTable tbody tr:visible").each(function() {
            var row = $(this).clone();
            row.find("td:last-child").remove(); // Remove action column
            printContents += "<tr>" + row.html() + "</tr>";
        });
        printContents += "</tbody></table>";
        printContents += "<div class='text-center mt-3'><small>Printed on: " + new Date().toLocaleString() + "</small></div>";
        printContents += "</body></html>";
        
        var printWindow = window.open('', '_blank');
        printWindow.document.write(printContents);
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    });
    
    // Pagination variables
    var entriesPerPage = 10;
    var currentPage = 1;
    
    // Initialize pagination
    updatePagination();
    
    // Previous page click
    $("#prevPage").click(function(e) {
        e.preventDefault();
        if (currentPage > 1) {
            currentPage--;
            updatePagination();
        }
    });
    
    // Next page click
    $("#nextPage").click(function(e) {
        e.preventDefault();
        var totalRows = $("#studentTable tbody tr:visible").length;
        var totalPages = Math.ceil(totalRows / entriesPerPage);
        
        if (currentPage < totalPages) {
            currentPage++;
            updatePagination();
        }
    });
    
    // Page number click
    $(document).on("click", ".page-number", function(e) {
        e.preventDefault();
        currentPage = parseInt($(this).text());
        updatePagination();
    });
    
    // Function to update pagination
    function updatePagination() {
        var totalRows = $("#studentTable tbody tr:visible").length;
        var totalPages = Math.ceil(totalRows / entriesPerPage);
        
        // Show/hide rows based on current page
        $("#studentTable tbody tr:visible").each(function(index) {
            var start = (currentPage - 1) * entriesPerPage;
            var end = start + entriesPerPage - 1;
            
            if (index >= start && index <= end) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        
        // Update "Showing X-Y of Z entries" text
        var start = Math.min((currentPage - 1) * entriesPerPage + 1, totalRows);
        var end = Math.min(start + entriesPerPage - 1, totalRows);
        $("#shownEntries").text(start + "-" + end);
        
        // Build pagination links
        var paginationHtml = "";
        paginationHtml += '<li class="page-item' + (currentPage === 1 ? ' disabled' : '') + '"><a class="page-link" href="#" id="prevPage">&laquo;</a></li>';
        
        // Calculate which page numbers to show
        var startPage = Math.max(1, currentPage - 2);
        var endPage = Math.min(totalPages, startPage + 4);
        
        if (endPage - startPage < 4 && totalPages > 5) {
            startPage = Math.max(1, endPage - 4);
        }
        
        for (var i = startPage; i <= endPage; i++) {
            paginationHtml += '<li class="page-item' + (i === currentPage ? ' active' : '') + '"><a class="page-link page-number" href="#">' + i + '</a></li>';
        }
        
        paginationHtml += '<li class="page-item' + (currentPage === totalPages ? ' disabled' : '') + '"><a class="page-link" href="#" id="nextPage">&raquo;</a></li>';
        
        // Update pagination container
        $(".pagination").html(paginationHtml);
    }
    
    // Welcome tour (runs on first visit)
    if (!localStorage.getItem('dashboardTourCompleted')) {
        // Check if intro.js is loaded
        if (typeof introJs !== 'undefined') {
            var tour = introJs();
            tour.setOptions({
                steps: [
                    {
                        element: document.querySelector('.content-header'),
                        intro: "Welcome to your Class Teacher Dashboard!"
                    },
                    {
                        element: document.querySelector('#teacherInfo'),
                        intro: "Here you can see your class information."
                    },
                    {
                        element: document.querySelector('#statsSection'),
                        intro: "This section shows key statistics about your class."
                    },
                    {
                        element: document.querySelector('#activitiesSection'),
                        intro: "Recent activities of your students appear here."
                    },
                    {
                        element: document.querySelector('#studentsSection'),
                        intro: "Manage all your students from this section."
                    },
                    {
                        element: document.querySelector('.quick-nav-menu'),
                        intro: "Use this quick navigation menu to jump between sections."
                    }
                ],
                showProgress: true,
                showBullets: false,
                disableInteraction: false
            });
            tour.start();
            
            // Mark tour as completed
            localStorage.setItem('dashboardTourCompleted', 'true');
        }
    }
});
</script>

<?php include '../admin/include/footer.php'; ?> 