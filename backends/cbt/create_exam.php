<?php
require_once '../config.php';
require_once '../database.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if teacher is logged in (either regular teacher or class teacher)
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['role']) || ($_SESSION['role'] !== 'teacher' && $_SESSION['role'] !== 'class_teacher')) {
    header("Location: login.php");
    exit;
}

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Get teacher details
$teacherId = $_SESSION['teacher_id'];
$teacherName = $_SESSION['name'];

// Process form submission
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_exam'])) {
    $title = trim($_POST['title'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $class = trim($_POST['class'] ?? '');
    $duration = intval($_POST['duration'] ?? 0);
    $instructions = trim($_POST['instructions'] ?? '');
    $passing_score = intval($_POST['passing_score'] ?? 0);
    $random_questions = isset($_POST['random_questions']) ? 1 : 0;
    $show_result = isset($_POST['show_result']) ? 1 : 0;
    
    // Validation
    if (empty($title) || empty($subject) || empty($class) || $duration <= 0) {
        $errorMessage = "Please fill in all required fields.";
    } else {
        // Insert exam
        $insertExam = "INSERT INTO cbt_exams (teacher_id, title, subject, class, duration, instructions, passing_score, random_questions, show_result, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($insertExam);
        $stmt->bind_param("isssissii", $teacherId, $title, $subject, $class, $duration, $instructions, $passing_score, $random_questions, $show_result);
        
        if ($stmt->execute()) {
            $examId = $conn->insert_id;
            $successMessage = "Exam created successfully! Now you can add questions.";
            
            // Redirect to add questions page
            header("Location: add_questions.php?exam_id=$examId");
            exit;
        } else {
            $errorMessage = "Error creating exam: " . $conn->error;
        }
    }
}

// Get classes for dropdown
$classesQuery = "SELECT DISTINCT class as name FROM students WHERE class IS NOT NULL AND class != '' ORDER BY class";
$classesResult = $conn->query($classesQuery);
$classes = [];

if ($classesResult && $classesResult->num_rows > 0) {
    while ($row = $classesResult->fetch_assoc()) {
        $classes[] = $row;
    }
}

// Get subjects for dropdown
$subjectsQuery = "SELECT DISTINCT name FROM subjects ORDER BY name";
$subjectsResult = $conn->query($subjectsQuery);
$subjects = [];

if ($subjectsResult && $subjectsResult->num_rows > 0) {
    while ($row = $subjectsResult->fetch_assoc()) {
        $subjects[] = $row;
    }
}

// Page title
$pageTitle = "Create New Exam";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo $pageTitle; ?> - ACE COLLEGE</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    
    <!-- Custom styles -->
    <style>
        :root {
            --primary: #4e73df;
            --secondary: #f6c23e;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --light: #f8f9fc;
            --dark: #5a5c69;
        }
        
        body {
            background-color: #f8f9fc;
        }
        
        .navbar {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            padding: 1rem;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.2rem;
        }
        
        .sidebar {
            min-height: calc(100vh - 56px);
            background-color: #fff;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .sidebar-link {
            display: block;
            padding: 1rem;
            color: #3a3b45;
            text-decoration: none;
            border-left: 4px solid transparent;
            transition: all 0.2s;
        }
        
        .sidebar-link:hover, .sidebar-link.active {
            background-color: #f8f9fc;
            border-left-color: #4e73df;
            color: #4e73df;
        }
        
        .content-wrapper {
            padding: 1.5rem;
        }
        
        .card {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            border: none;
            border-radius: 0.35rem;
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 0.75rem 1.25rem;
        }
        
        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
        }
        
        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2653d4;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <a class="navbar-brand" href="dashboard.php">
            <i class="fas fa-laptop-code mr-2"></i> ACE COLLEGE - CBT System
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown">
                        <i class="fas fa-user-circle mr-1"></i> <?php echo htmlspecialchars($teacherName); ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="logout.php">
                            <i class="fas fa-sign-out-alt mr-2"></i> Logout
                        </a>
                    </div>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar py-3">
                <a href="dashboard.php" class="sidebar-link">
                    <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                </a>
                <a href="create_exam.php" class="sidebar-link active">
                    <i class="fas fa-plus-circle mr-2"></i> Create New Exam
                </a>
                <a href="view_results.php" class="sidebar-link">
                    <i class="fas fa-chart-bar mr-2"></i> View Results
                </a>
                <a href="question_bank.php" class="sidebar-link">
                    <i class="fas fa-database mr-2"></i> Question Bank
                </a>
                <a href="logout.php" class="sidebar-link">
                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                </a>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 content-wrapper">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Create New Exam</h1>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
                    </a>
                </div>
                
                <?php if (!empty($successMessage)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle mr-2"></i> <?php echo $successMessage; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errorMessage)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $errorMessage; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Exam Details</h6>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="title"><i class="fas fa-heading mr-1"></i> Exam Title*</label>
                                        <input type="text" class="form-control" id="title" name="title" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="subject"><i class="fas fa-book mr-1"></i> Subject*</label>
                                        <select class="form-control" id="subject" name="subject" required>
                                            <option value="">Select Subject</option>
                                            <?php foreach ($subjects as $subject): ?>
                                                <option value="<?php echo htmlspecialchars($subject['name']); ?>"><?php echo htmlspecialchars($subject['name']); ?></option>
                                            <?php endforeach; ?>
                                            <!-- Allow custom subject if not in list -->
                                            <option value="other">Other (specify)</option>
                                        </select>
                                        <input type="text" class="form-control mt-2" id="custom_subject" name="custom_subject" placeholder="Enter subject name" style="display: none;">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="class"><i class="fas fa-users mr-1"></i> Class*</label>
                                        <select class="form-control" id="class" name="class" required>
                                            <option value="">Select Class</option>
                                            <?php foreach ($classes as $class): ?>
                                                <option value="<?php echo htmlspecialchars($class['name']); ?>"><?php echo htmlspecialchars($class['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="duration"><i class="fas fa-clock mr-1"></i> Duration (minutes)*</label>
                                        <input type="number" class="form-control" id="duration" name="duration" min="5" value="60" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="instructions"><i class="fas fa-info-circle mr-1"></i> Instructions</label>
                                <textarea class="form-control" id="instructions" name="instructions" rows="4" placeholder="Enter exam instructions for students..."></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="passing_score"><i class="fas fa-percentage mr-1"></i> Passing Score (%)</label>
                                        <input type="number" class="form-control" id="passing_score" name="passing_score" min="0" max="100" value="50">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="d-block mb-2"><i class="fas fa-cog mr-1"></i> Exam Settings</label>
                                        <div class="custom-control custom-switch mb-2">
                                            <input type="checkbox" class="custom-control-input" id="random_questions" name="random_questions" checked>
                                            <label class="custom-control-label" for="random_questions">Randomize questions</label>
                                        </div>
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="show_result" name="show_result" checked>
                                            <label class="custom-control-label" for="show_result">Show result immediately after submission</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <button type="submit" name="create_exam" class="btn btn-primary">
                                <i class="fas fa-save mr-1"></i> Save & Continue to Add Questions
                            </button>
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-times mr-1"></i> Cancel
                            </a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Handle custom subject input
        $('#subject').change(function() {
            if ($(this).val() === 'other') {
                $('#custom_subject').show().attr('required', true);
            } else {
                $('#custom_subject').hide().attr('required', false);
            }
        });
        
        // Form submission handling for custom subject
        $('form').submit(function() {
            if ($('#subject').val() === 'other') {
                $('#subject').val($('#custom_subject').val());
            }
        });
    });
    </script>
</body>
</html> 