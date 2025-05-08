<?php
// Teacher CBT Interface
session_start();

// Include required files
require_once '../database.php';
require_once '../config.php';
require_once '../utils.php';
require_once '../auth.php';

// Require teacher login
requireLogin('teacher', '../../login.php');

// Create database connection
$database = new Database();
$conn = $database->getConnection();

// Get teacher information
$userId = $_SESSION[SESSION_PREFIX . 'user_id'];
$profileId = $_SESSION[SESSION_PREFIX . 'profile_id'];

try {
    // Get subjects taught by teacher
    $stmt = $conn->prepare("SELECT cs.id, s.id as subject_id, s.name as subject_name, s.code as subject_code, 
                          c.id as class_id, c.name as class_name, c.section
                          FROM class_subjects cs
                          JOIN subjects s ON cs.subject_id = s.id
                          JOIN classes c ON cs.class_id = c.id
                          WHERE cs.teacher_id = :teacher_id
                          ORDER BY c.name, s.name");
    $stmt->bindParam(':teacher_id', $profileId);
    $stmt->execute();
    $teacherSubjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get CBT exams created by the teacher
    $stmt = $conn->prepare("SELECT ce.*, s.name as subject_name, s.code as subject_code,
                          c.name as class_name, c.section,
                          (SELECT COUNT(*) FROM cbt_student_exams WHERE exam_id = ce.id) as attempts,
                          (SELECT COUNT(*) FROM cbt_questions WHERE exam_id = ce.id) as question_count
                          FROM cbt_exams ce
                          JOIN subjects s ON ce.subject_id = s.id
                          JOIN classes c ON ce.class_id = c.id
                          WHERE ce.teacher_id = :teacher_id
                          ORDER BY ce.created_at DESC");
    $stmt->bindParam(':teacher_id', $profileId);
    $stmt->execute();
    $teacherExams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Process form submission
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'create_exam') {
        try {
            // Start transaction
            $conn->beginTransaction();
            
            // Get form data
            $title = sanitize($_POST['title']);
            $description = sanitize($_POST['description']);
            $subjectId = (int)$_POST['subject_id'];
            $classId = (int)$_POST['class_id'];
            $passingScore = (float)$_POST['passing_score'];
            $timeLimit = (int)$_POST['time_limit'];
            $startDatetime = $_POST['start_datetime'];
            $endDatetime = $_POST['end_datetime'];
            $instructions = sanitize($_POST['instructions']);
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            // Validate data
            if (empty($title) || empty($description) || $subjectId <= 0 || $classId <= 0 || $timeLimit <= 0) {
                throw new Exception('Please fill in all required fields.');
            }
            
            // Verify teacher is assigned to the subject for this class
            $stmt = $conn->prepare("SELECT id FROM class_subjects 
                                  WHERE subject_id = :subject_id 
                                  AND class_id = :class_id 
                                  AND teacher_id = :teacher_id");
            $stmt->bindParam(':subject_id', $subjectId);
            $stmt->bindParam(':class_id', $classId);
            $stmt->bindParam(':teacher_id', $profileId);
            $stmt->execute();
            
            if ($stmt->rowCount() == 0) {
                throw new Exception('You are not assigned to teach this subject for this class.');
            }
            
            // Insert exam
            $stmt = $conn->prepare("INSERT INTO cbt_exams (title, description, subject_id, class_id, teacher_id, 
                                  passing_score, time_limit, start_datetime, end_datetime, instructions, is_active) 
                                  VALUES (:title, :description, :subject_id, :class_id, :teacher_id, 
                                  :passing_score, :time_limit, :start_datetime, :end_datetime, :instructions, :is_active)");
            
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':subject_id', $subjectId);
            $stmt->bindParam(':class_id', $classId);
            $stmt->bindParam(':teacher_id', $profileId);
            $stmt->bindParam(':passing_score', $passingScore);
            $stmt->bindParam(':time_limit', $timeLimit);
            $stmt->bindParam(':start_datetime', $startDatetime);
            $stmt->bindParam(':end_datetime', $endDatetime);
            $stmt->bindParam(':instructions', $instructions);
            $stmt->bindParam(':is_active', $isActive);
            
            $stmt->execute();
            
            $examId = $conn->lastInsertId();
            
            // Log activity
            logActivity($conn, 'create_cbt_exam', "Created new CBT exam: $title", $userId);
            
            // Commit transaction
            $conn->commit();
            
            $successMessage = "Exam created successfully. You can now add questions to this exam.";
            
            // Redirect to add questions
            header("Location: add_questions.php?exam_id=$examId");
            exit;
            
        } catch (Exception $e) {
            $conn->rollBack();
            $errorMessage = $e->getMessage();
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'toggle_exam_status') {
        try {
            $examId = (int)$_POST['exam_id'];
            $newStatus = (int)$_POST['status'];
            
            // Verify the exam belongs to this teacher
            $stmt = $conn->prepare("SELECT id FROM cbt_exams 
                                  WHERE id = :exam_id AND teacher_id = :teacher_id");
            $stmt->bindParam(':exam_id', $examId);
            $stmt->bindParam(':teacher_id', $profileId);
            $stmt->execute();
            
            if ($stmt->rowCount() == 0) {
                throw new Exception('You do not have permission to modify this exam.');
            }
            
            // Update exam status
            $stmt = $conn->prepare("UPDATE cbt_exams SET is_active = :is_active WHERE id = :exam_id");
            $stmt->bindParam(':is_active', $newStatus);
            $stmt->bindParam(':exam_id', $examId);
            $stmt->execute();
            
            $statusText = $newStatus ? 'activated' : 'deactivated';
            $successMessage = "Exam $statusText successfully.";
            
            // Refresh exam list
            $stmt = $conn->prepare("SELECT ce.*, s.name as subject_name, s.code as subject_code,
                                  c.name as class_name, c.section,
                                  (SELECT COUNT(*) FROM cbt_student_exams WHERE exam_id = ce.id) as attempts,
                                  (SELECT COUNT(*) FROM cbt_questions WHERE exam_id = ce.id) as question_count
                                  FROM cbt_exams ce
                                  JOIN subjects s ON ce.subject_id = s.id
                                  JOIN classes c ON ce.class_id = c.id
                                  WHERE ce.teacher_id = :teacher_id
                                  ORDER BY ce.created_at DESC");
            $stmt->bindParam(':teacher_id', $profileId);
            $stmt->execute();
            $teacherExams = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
        }
    }
}

// Page title
$pageTitle = "Teacher CBT Panel";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    
    <style>
        .exam-card {
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .exam-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .badge-corner {
            position: absolute;
            top: 0;
            right: 0;
            padding: 5px 10px;
            transform: translate(10px, -10px);
            border-radius: 30px;
        }
        
        .header-bar {
            background-color: #343a40;
            color: white;
            padding: 15px 0;
            margin-bottom: 30px;
        }
        
        .user-welcome {
            display: flex;
            align-items: center;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header-bar">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h4 class="mb-0">
                        <i class="fas fa-laptop-code"></i> 
                        <?php echo $pageTitle; ?>
                    </h4>
                </div>
                <div class="col-md-6 text-end">
                    <div class="user-welcome d-inline-flex">
                        <img src="<?php echo !empty($_SESSION[SESSION_PREFIX . 'profile_image']) ? $_SESSION[SESSION_PREFIX . 'profile_image'] : DEFAULT_TEACHER_IMAGE; ?>" alt="Profile" class="user-avatar">
                        <span>Welcome, <?php echo $_SESSION[SESSION_PREFIX . 'name']; ?></span>
                    </div>
                    <a href="../teacher/dashboard.php" class="btn btn-outline-light btn-sm ms-3">
                        <i class="fas fa-tachometer-alt"></i> Teacher Dashboard
                    </a>
                    <a href="../../logout.php" class="btn btn-outline-light btn-sm ms-2">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <!-- Success and Error Messages -->
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $successMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $errorMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Create Exam Button -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title">
                                <i class="fas fa-tasks"></i> Computer-Based Tests Management
                            </h5>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createExamModal">
                                <i class="fas fa-plus"></i> Create New Exam
                            </button>
                        </div>
                        <p class="text-muted">Create and manage online exams for your subjects.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Subjects and Exams Tabs -->
        <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="exams-tab" data-bs-toggle="tab" data-bs-target="#exams" type="button" role="tab" aria-controls="exams" aria-selected="true">
                    <i class="fas fa-file-alt"></i> My Exams
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="subjects-tab" data-bs-toggle="tab" data-bs-target="#subjects" type="button" role="tab" aria-controls="subjects" aria-selected="false">
                    <i class="fas fa-book"></i> My Subjects
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="results-tab" data-bs-toggle="tab" data-bs-target="#results" type="button" role="tab" aria-controls="results" aria-selected="false">
                    <i class="fas fa-chart-bar"></i> Exam Results
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="myTabContent">
            <!-- Exams Tab -->
            <div class="tab-pane fade show active" id="exams" role="tabpanel" aria-labelledby="exams-tab">
                <?php if (empty($teacherExams)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> You haven't created any exams yet. Click the "Create New Exam" button to get started.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($teacherExams as $exam): ?>
                            <div class="col-md-4">
                                <div class="card exam-card">
                                    <?php if ($exam['is_active']): ?>
                                        <div class="badge bg-success badge-corner">Active</div>
                                    <?php else: ?>
                                        <div class="badge bg-secondary badge-corner">Inactive</div>
                                    <?php endif; ?>
                                    
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="card-title mb-0"><?php echo $exam['title']; ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="card-text">
                                            <strong>Subject:</strong> <?php echo $exam['subject_name']; ?> (<?php echo $exam['subject_code']; ?>)<br>
                                            <strong>Class:</strong> <?php echo $exam['class_name'] . ' ' . $exam['section']; ?><br>
                                            <strong>Duration:</strong> <?php echo $exam['time_limit']; ?> minutes<br>
                                            <strong>Start:</strong> <?php echo formatDate($exam['start_datetime'], 'M d, Y h:i A'); ?><br>
                                            <strong>End:</strong> <?php echo formatDate($exam['end_datetime'], 'M d, Y h:i A'); ?><br>
                                            <strong>Questions:</strong> <?php echo $exam['question_count']; ?><br>
                                            <strong>Attempts:</strong> <?php echo $exam['attempts']; ?>
                                        </p>
                                        <div class="d-grid gap-2">
                                            <a href="manage_exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-cog"></i> Manage Exam
                                            </a>
                                            <a href="view_results.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-info btn-sm">
                                                <i class="fas fa-chart-bar"></i> View Results
                                            </a>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="action" value="toggle_exam_status">
                                                <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                                                <input type="hidden" name="status" value="<?php echo $exam['is_active'] ? 0 : 1; ?>">
                                                <button type="submit" class="btn btn-<?php echo $exam['is_active'] ? 'warning' : 'success'; ?> btn-sm w-100">
                                                    <i class="fas <?php echo $exam['is_active'] ? 'fa-ban' : 'fa-check-circle'; ?>"></i>
                                                    <?php echo $exam['is_active'] ? 'Deactivate Exam' : 'Activate Exam'; ?>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Subjects Tab -->
            <div class="tab-pane fade" id="subjects" role="tabpanel" aria-labelledby="subjects-tab">
                <?php if (empty($teacherSubjects)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> You are not assigned to any subjects yet. Please contact the admin.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="subjectsTable">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Code</th>
                                    <th>Class</th>
                                    <th>Total Exams</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teacherSubjects as $subject): 
                                    // Count exams for this subject-class combination
                                    $stmt = $conn->prepare("SELECT COUNT(*) as exam_count FROM cbt_exams 
                                                         WHERE subject_id = :subject_id AND class_id = :class_id AND teacher_id = :teacher_id");
                                    $stmt->bindParam(':subject_id', $subject['subject_id']);
                                    $stmt->bindParam(':class_id', $subject['class_id']);
                                    $stmt->bindParam(':teacher_id', $profileId);
                                    $stmt->execute();
                                    $examCount = $stmt->fetch(PDO::FETCH_ASSOC)['exam_count'];
                                ?>
                                <tr>
                                    <td><?php echo $subject['subject_name']; ?></td>
                                    <td><?php echo $subject['subject_code']; ?></td>
                                    <td><?php echo $subject['class_name'] . ' ' . $subject['section']; ?></td>
                                    <td><?php echo $examCount; ?></td>
                                    <td>
                                        <button type="button" class="btn btn-primary btn-sm create-exam-for-subject"
                                            data-subject-id="<?php echo $subject['subject_id']; ?>"
                                            data-subject-name="<?php echo $subject['subject_name']; ?>"
                                            data-class-id="<?php echo $subject['class_id']; ?>"
                                            data-class-name="<?php echo $subject['class_name'] . ' ' . $subject['section']; ?>">
                                            <i class="fas fa-plus"></i> Create Exam
                                        </button>
                                        <a href="subject_exams.php?subject_id=<?php echo $subject['subject_id']; ?>&class_id=<?php echo $subject['class_id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i> View Exams
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Results Tab -->
            <div class="tab-pane fade" id="results" role="tabpanel" aria-labelledby="results-tab">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Exam Results Summary</h5>
                    </div>
                    <div class="card-body">
                        <p>Select an exam to view detailed results:</p>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="resultsTable">
                                <thead>
                                    <tr>
                                        <th>Exam Title</th>
                                        <th>Subject</th>
                                        <th>Class</th>
                                        <th>Total Students</th>
                                        <th>Attempts</th>
                                        <th>Average Score</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Get exams with results
                                    $stmt = $conn->prepare("SELECT ce.id, ce.title, s.name as subject_name, 
                                                         c.name as class_name, c.section,
                                                         (SELECT COUNT(*) FROM students WHERE class_id = ce.class_id) as total_students,
                                                         COUNT(cse.id) as attempts,
                                                         AVG(cse.score) as avg_score
                                                         FROM cbt_exams ce
                                                         JOIN subjects s ON ce.subject_id = s.id
                                                         JOIN classes c ON ce.class_id = c.id
                                                         LEFT JOIN cbt_student_exams cse ON ce.id = cse.exam_id
                                                         WHERE ce.teacher_id = :teacher_id
                                                         GROUP BY ce.id
                                                         ORDER BY ce.created_at DESC");
                                    $stmt->bindParam(':teacher_id', $profileId);
                                    $stmt->execute();
                                    $examResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    foreach ($examResults as $result):
                                    ?>
                                    <tr>
                                        <td><?php echo $result['title']; ?></td>
                                        <td><?php echo $result['subject_name']; ?></td>
                                        <td><?php echo $result['class_name'] . ' ' . $result['section']; ?></td>
                                        <td><?php echo $result['total_students']; ?></td>
                                        <td><?php echo $result['attempts']; ?></td>
                                        <td>
                                            <?php if ($result['avg_score']): ?>
                                                <?php echo number_format($result['avg_score'], 2); ?>%
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="view_results.php?exam_id=<?php echo $result['id']; ?>" class="btn btn-info btn-sm">
                                                <i class="fas fa-chart-bar"></i> View
                                            </a>
                                            <a href="export_results.php?exam_id=<?php echo $result['id']; ?>" class="btn btn-secondary btn-sm">
                                                <i class="fas fa-download"></i> Export
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create Exam Modal -->
    <div class="modal fade" id="createExamModal" tabindex="-1" aria-labelledby="createExamModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="createExamModalLabel">Create New CBT Exam</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_exam">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="title" class="form-label">Exam Title *</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            <div class="col-md-6">
                                <label for="time_limit" class="form-label">Time Limit (minutes) *</label>
                                <input type="number" class="form-control" id="time_limit" name="time_limit" min="1" value="60" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description *</label>
                            <textarea class="form-control" id="description" name="description" rows="2" required></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="subject_id" class="form-label">Subject *</label>
                                <select class="form-select" id="subject_id" name="subject_id" required>
                                    <option value="">Select Subject</option>
                                    <?php
                                    // Get unique subjects
                                    $uniqueSubjects = [];
                                    foreach ($teacherSubjects as $subject) {
                                        $uniqueSubjects[$subject['subject_id']] = $subject['subject_name'] . ' (' . $subject['subject_code'] . ')';
                                    }
                                    foreach ($uniqueSubjects as $id => $name) {
                                        echo "<option value=\"$id\">$name</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="class_id" class="form-label">Class *</label>
                                <select class="form-select" id="class_id" name="class_id" required>
                                    <option value="">Select Class</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="start_datetime" class="form-label">Start Date & Time *</label>
                                <input type="text" class="form-control" id="start_datetime" name="start_datetime" required>
                            </div>
                            <div class="col-md-6">
                                <label for="end_datetime" class="form-label">End Date & Time *</label>
                                <input type="text" class="form-control" id="end_datetime" name="end_datetime" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="passing_score" class="form-label">Passing Score (%) *</label>
                            <input type="number" class="form-control" id="passing_score" name="passing_score" min="1" max="100" value="40" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="instructions" class="form-label">Instructions for Students *</label>
                            <textarea class="form-control" id="instructions" name="instructions" rows="3" required></textarea>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_active" name="is_active">
                            <label class="form-check-label" for="is_active">Activate exam immediately</label>
                            <small class="form-text text-muted d-block">If unchecked, you'll need to activate it later.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Exam</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTables
            $('#subjectsTable').DataTable({
                "paging": true,
                "lengthChange": false,
                "searching": true,
                "ordering": true,
                "info": true,
                "autoWidth": false,
                "responsive": true
            });
            
            $('#resultsTable').DataTable({
                "paging": true,
                "lengthChange": false,
                "searching": true,
                "ordering": true,
                "info": true,
                "autoWidth": false,
                "responsive": true
            });
            
            // Initialize Flatpickr for date and time pickers
            flatpickr("#start_datetime", {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                minDate: "today"
            });
            
            flatpickr("#end_datetime", {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                minDate: "today"
            });
            
            // Populate classes based on selected subject
            $('#subject_id').change(function() {
                const subjectId = $(this).val();
                const classSelect = $('#class_id');
                
                // Clear current options
                classSelect.empty().append('<option value="">Select Class</option>');
                
                if (subjectId) {
                    // Get classes for this subject
                    <?php
                    // Create a JavaScript array of subject-class mappings
                    echo "const subjectClasses = " . json_encode(array_map(function($item) {
                        return [
                            'subject_id' => $item['subject_id'],
                            'class_id' => $item['class_id'],
                            'class_name' => $item['class_name'] . ' ' . $item['section']
                        ];
                    }, $teacherSubjects)) . ";";
                    ?>
                    
                    // Filter classes for the selected subject
                    const filteredClasses = subjectClasses.filter(item => item.subject_id == subjectId);
                    
                    // Add options
                    filteredClasses.forEach(item => {
                        classSelect.append(`<option value="${item.class_id}">${item.class_name}</option>`);
                    });
                }
            });
            
            // Create exam for specific subject
            $('.create-exam-for-subject').click(function() {
                const subjectId = $(this).data('subject-id');
                const subjectName = $(this).data('subject-name');
                const classId = $(this).data('class-id');
                const className = $(this).data('class-name');
                
                // Set values in the modal
                $('#subject_id').val(subjectId).trigger('change');
                
                // Wait a bit for classes to populate, then set the class
                setTimeout(() => {
                    $('#class_id').val(classId);
                }, 100);
                
                // Set default title
                $('#title').val(`${subjectName} Exam - ${className}`);
                
                // Open the modal
                $('#createExamModal').modal('show');
            });
        });
    </script>
</body>
</html> 