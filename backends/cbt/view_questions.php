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

// Check if exam_id is provided
if (!isset($_GET['exam_id']) || !is_numeric($_GET['exam_id'])) {
    header("Location: dashboard.php");
    exit;
}

$examId = intval($_GET['exam_id']);

// Verify the exam belongs to the current teacher
$examQuery = "SELECT * FROM cbt_exams WHERE id = ? AND teacher_id = ?";
$stmt = $conn->prepare($examQuery);
$stmt->bind_param("ii", $examId, $teacherId);
$stmt->execute();
$examResult = $stmt->get_result();

if ($examResult->num_rows === 0) {
    header("Location: dashboard.php");
    exit;
}

$exam = $examResult->fetch_assoc();

// Get all questions for this exam
$questionsQuery = "SELECT * FROM cbt_questions WHERE exam_id = ? ORDER BY sort_order ASC";
$stmt = $conn->prepare($questionsQuery);
$stmt->bind_param("i", $examId);
$stmt->execute();
$questionsResult = $stmt->get_result();
$questions = [];

if ($questionsResult && $questionsResult->num_rows > 0) {
    while ($row = $questionsResult->fetch_assoc()) {
        $questions[] = $row;
    }
}

// Process delete question request
$successMessage = '';
$errorMessage = '';

if (isset($_POST['delete_question'])) {
    $questionId = intval($_POST['question_id'] ?? 0);
    
    if ($questionId > 0) {
        $deleteQuery = "DELETE FROM cbt_questions WHERE id = ? AND exam_id = ?";
        $stmt = $conn->prepare($deleteQuery);
        $stmt->bind_param("ii", $questionId, $examId);
        
        if ($stmt->execute()) {
            $successMessage = "Question deleted successfully!";
            
            // Refresh questions list
            $stmt = $conn->prepare($questionsQuery);
            $stmt->bind_param("i", $examId);
            $stmt->execute();
            $questionsResult = $stmt->get_result();
            $questions = [];
            
            if ($questionsResult && $questionsResult->num_rows > 0) {
                while ($row = $questionsResult->fetch_assoc()) {
                    $questions[] = $row;
                }
            }
        } else {
            $errorMessage = "Error deleting question: " . $conn->error;
        }
    }
}

// Page title
$pageTitle = "View Questions - " . htmlspecialchars($exam['title']);
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
        
        .question-card {
            border-left: 4px solid #4e73df;
        }
        
        .question-text {
            font-weight: 600;
        }
        
        .option-item {
            margin-bottom: 0.5rem;
        }
        
        .correct-answer {
            color: #1cc88a;
            font-weight: 600;
        }
        
        .question-number {
            background-color: #4e73df;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
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
                <a href="create_exam.php" class="sidebar-link">
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
                    <h1 class="h3 mb-0 text-gray-800">
                        Questions: <span class="text-primary"><?php echo htmlspecialchars($exam['title']); ?></span>
                    </h1>
                    <div>
                        <a href="add_questions.php?exam_id=<?php echo $examId; ?>" class="btn btn-primary">
                            <i class="fas fa-plus-circle mr-1"></i> Add More Questions
                        </a>
                        <a href="dashboard.php" class="btn btn-secondary ml-2">
                            <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
                        </a>
                    </div>
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
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Exam Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <p class="mb-0 text-muted">Subject:</p>
                                        <p class="font-weight-bold"><?php echo htmlspecialchars($exam['subject']); ?></p>
                                    </div>
                                    <div class="col-md-3">
                                        <p class="mb-0 text-muted">Class:</p>
                                        <p class="font-weight-bold"><?php echo htmlspecialchars($exam['class']); ?></p>
                                    </div>
                                    <div class="col-md-3">
                                        <p class="mb-0 text-muted">Duration:</p>
                                        <p class="font-weight-bold"><?php echo $exam['duration']; ?> minutes</p>
                                    </div>
                                    <div class="col-md-3">
                                        <p class="mb-0 text-muted">Total Questions:</p>
                                        <p class="font-weight-bold"><?php echo count($questions); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (count($questions) > 0): ?>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card shadow">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">Questions List (<?php echo count($questions); ?>)</h6>
                                <div>
                                    <?php if ($exam['is_active']): ?>
                                        <span class="badge badge-success">Exam is Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Exam is Inactive</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="list-group">
                                    <?php foreach ($questions as $index => $question): ?>
                                    <div class="list-group-item list-group-item-action flex-column align-items-start mb-3 border shadow-sm">
                                        <div class="d-flex w-100 justify-content-between align-items-center mb-2">
                                            <h5 class="mb-1">
                                                <span class="question-number"><?php echo $index + 1; ?></span>
                                                <?php echo htmlspecialchars(substr($question['question_text'], 0, 100)) . (strlen($question['question_text']) > 100 ? '...' : ''); ?>
                                            </h5>
                                            <div>
                                                <span class="badge badge-info"><?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?></span>
                                                <span class="badge badge-primary ml-1"><?php echo $question['marks']; ?> mark<?php echo $question['marks'] > 1 ? 's' : ''; ?></span>
                                            </div>
                                        </div>
                                        
                                        <p class="mb-2 text-muted"><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>
                                        
                                        <?php if (!empty($question['question_image'])): ?>
                                        <div class="mb-3">
                                            <img src="../uploads/question_images/<?php echo htmlspecialchars($question['question_image']); ?>" alt="Question Image" class="img-fluid" style="max-height: 300px;">
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($question['question_type'] === 'multiple_choice'): ?>
                                        <div class="row mb-2">
                                            <div class="col-md-6">
                                                <div class="option-item <?php echo $question['correct_answer'] === 'A' ? 'correct-answer' : ''; ?>">
                                                    A: <?php echo htmlspecialchars($question['option_a']); ?>
                                                    <?php if ($question['correct_answer'] === 'A'): ?>
                                                        <i class="fas fa-check-circle text-success ml-1"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="option-item <?php echo $question['correct_answer'] === 'B' ? 'correct-answer' : ''; ?>">
                                                    B: <?php echo htmlspecialchars($question['option_b']); ?>
                                                    <?php if ($question['correct_answer'] === 'B'): ?>
                                                        <i class="fas fa-check-circle text-success ml-1"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <?php if (!empty($question['option_c'])): ?>
                                                <div class="option-item <?php echo $question['correct_answer'] === 'C' ? 'correct-answer' : ''; ?>">
                                                    C: <?php echo htmlspecialchars($question['option_c']); ?>
                                                    <?php if ($question['correct_answer'] === 'C'): ?>
                                                        <i class="fas fa-check-circle text-success ml-1"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <?php endif; ?>
                                                <?php if (!empty($question['option_d'])): ?>
                                                <div class="option-item <?php echo $question['correct_answer'] === 'D' ? 'correct-answer' : ''; ?>">
                                                    D: <?php echo htmlspecialchars($question['option_d']); ?>
                                                    <?php if ($question['correct_answer'] === 'D'): ?>
                                                        <i class="fas fa-check-circle text-success ml-1"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php elseif ($question['question_type'] === 'true_false'): ?>
                                        <div class="mb-2">
                                            <div class="option-item <?php echo $question['correct_answer'] === 'True' ? 'correct-answer' : ''; ?>">
                                                True
                                                <?php if ($question['correct_answer'] === 'True'): ?>
                                                    <i class="fas fa-check-circle text-success ml-1"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="option-item <?php echo $question['correct_answer'] === 'False' ? 'correct-answer' : ''; ?>">
                                                False
                                                <?php if ($question['correct_answer'] === 'False'): ?>
                                                    <i class="fas fa-check-circle text-success ml-1"></i>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php else: ?>
                                        <div class="mb-2">
                                            <strong>Correct Answer:</strong> <?php echo htmlspecialchars($question['correct_answer']); ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($question['explanation'])): ?>
                                        <div class="mb-2 text-muted">
                                            <strong>Explanation:</strong> <?php echo nl2br(htmlspecialchars($question['explanation'])); ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="mt-2">
                                            <a href="edit_question.php?id=<?php echo $question['id']; ?>&exam_id=<?php echo $examId; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <form method="post" action="" class="d-inline ml-2">
                                                <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                                <button type="submit" name="delete_question" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this question?')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card shadow">
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle mr-2"></i> No questions have been added to this exam yet. Click the "Add More Questions" button to get started.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
    });
    </script>
</body>
</html> 