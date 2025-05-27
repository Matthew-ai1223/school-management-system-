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

// Process form submissions
$successMessage = '';
$errorMessage = '';

// Get existing questions for this exam
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
if (isset($_POST['delete_question'])) {
    $questionId = intval($_POST['question_id'] ?? 0);
    
    if ($questionId > 0) {
        // Get question image before deleting
        $imgQuery = "SELECT question_image FROM cbt_questions WHERE id = ? AND exam_id = ?";
        $stmt = $conn->prepare($imgQuery);
        $stmt->bind_param("ii", $questionId, $examId);
        $stmt->execute();
        $imgResult = $stmt->get_result();
        
        if ($imgResult && $imgResult->num_rows > 0) {
            $question = $imgResult->fetch_assoc();
            // Delete the image file if it exists
            if (!empty($question['question_image'])) {
                $imagePath = '../uploads/question_images/' . $question['question_image'];
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
        }
        
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

// Process add question form submission
if (isset($_POST['add_question'])) {
    $questionText = trim($_POST['question_text'] ?? '');
    $questionType = $_POST['question_type'] ?? 'multiple_choice';
    $marks = intval($_POST['marks'] ?? 1);
    $correctAnswer = trim($_POST['correct_answer'] ?? '');
    $optionA = isset($_POST['option_a']) ? trim($_POST['option_a']) : null;
    $optionB = isset($_POST['option_b']) ? trim($_POST['option_b']) : null;
    $optionC = isset($_POST['option_c']) ? trim($_POST['option_c']) : null;
    $optionD = isset($_POST['option_d']) ? trim($_POST['option_d']) : null;
    $explanation = trim($_POST['explanation'] ?? '');
    $sortOrder = count($questions) + 1;
    
    // Image upload handling
    $questionImage = null;
    if (isset($_FILES['question_image']) && $_FILES['question_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/question_images/';
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = basename($_FILES['question_image']['name']);
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        $uniqueName = uniqid('question_') . '.' . $fileExtension;
        $targetFile = $uploadDir . $uniqueName;
        
        // Check file type
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array(strtolower($fileExtension), $allowedTypes)) {
            if (move_uploaded_file($_FILES['question_image']['tmp_name'], $targetFile)) {
                $questionImage = $uniqueName;
            } else {
                $errorMessage = "Error uploading image.";
            }
        } else {
            $errorMessage = "Only JPG, JPEG, PNG, and GIF files are allowed.";
        }
    }
    
    // Validation
    if (empty($questionText)) {
        $errorMessage = "Question text cannot be empty.";
    } elseif ($questionType === 'multiple_choice' && (empty($optionA) || empty($optionB))) {
        $errorMessage = "Multiple choice questions must have at least two options.";
    } elseif (empty($correctAnswer)) {
        $errorMessage = "Correct answer cannot be empty.";
    } else {
        // For multiple choice, validate that correct answer matches one of the options
        if ($questionType === 'multiple_choice') {
            if (!in_array($correctAnswer, ['A', 'B', 'C', 'D'])) {
                $errorMessage = "Correct answer must be A, B, C, or D for multiple choice questions.";
            }
        } elseif ($questionType === 'true_false') {
            if (!in_array($correctAnswer, ['True', 'False'])) {
                $errorMessage = "Correct answer must be True or False for true/false questions.";
            }
        }
        
        if (empty($errorMessage)) {
            // Insert question
            $insertQuery = "INSERT INTO cbt_questions (exam_id, question_text, question_type, marks, correct_answer, 
                          option_a, option_b, option_c, option_d, explanation, question_image, sort_order) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param("issiissssssi", $examId, $questionText, $questionType, $marks, $correctAnswer, 
                            $optionA, $optionB, $optionC, $optionD, $explanation, $questionImage, $sortOrder);
            
            if ($stmt->execute()) {
                $successMessage = "Question added successfully!";
                
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
                $errorMessage = "Error adding question: " . $conn->error;
            }
        }
    }
}

// Process finish exam setup
if (isset($_POST['finish_setup'])) {
    if (count($questions) > 0) {
        header("Location: dashboard.php");
        exit;
    } else {
        $errorMessage = "Please add at least one question before finishing.";
    }
}

// Page title
$pageTitle = "Add Questions - " . htmlspecialchars($exam['title']);
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
        
        .question-image-preview {
            max-width: 100%;
            max-height: 300px;
            margin: 10px 0;
            border: 1px solid #e3e6f0;
            border-radius: 4px;
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
                        Add Questions: <span class="text-primary"><?php echo htmlspecialchars($exam['title']); ?></span>
                    </h1>
                    <div>
                        <a href="view_questions.php?exam_id=<?php echo $examId; ?>" class="btn btn-info">
                            <i class="fas fa-eye mr-1"></i> View Questions
                        </a>
                        <form method="post" action="" class="d-inline">
                            <button type="submit" name="finish_setup" class="btn btn-success">
                                <i class="fas fa-check-circle mr-1"></i> Finish Exam Setup
                            </button>
                        </form>
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
                                        <p class="mb-0 text-muted">Questions:</p>
                                        <p class="font-weight-bold"><?php echo count($questions); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Add New Question</h6>
                            </div>
                            <div class="card-body">
                                <form method="post" action="" enctype="multipart/form-data">
                                    <div class="form-group">
                                        <label for="question_text"><i class="fas fa-question-circle mr-1"></i> Question Text*</label>
                                        <textarea class="form-control" id="question_text" name="question_text" rows="3" required></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="question_image"><i class="fas fa-image mr-1"></i> Question Image (Optional)</label>
                                        <input type="file" class="form-control-file" id="question_image" name="question_image">
                                        <small class="form-text text-muted">Supported formats: JPG, JPEG, PNG, GIF (Max size: 2MB)</small>
                                        <div id="image-preview-container" class="mt-2" style="display: none;">
                                            <img id="image-preview" class="question-image-preview">
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="question_type"><i class="fas fa-list-ul mr-1"></i> Question Type*</label>
                                                <select class="form-control" id="question_type" name="question_type" required>
                                                    <option value="multiple_choice">Multiple Choice</option>
                                                    <option value="true_false">True/False</option>
                                                    <option value="short_answer">Short Answer</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="marks"><i class="fas fa-star mr-1"></i> Marks</label>
                                                <input type="number" class="form-control" id="marks" name="marks" min="1" value="1">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div id="multiple_choice_options">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="option_a"><i class="fas fa-check-circle mr-1"></i> Option A*</label>
                                                    <input type="text" class="form-control" id="option_a" name="option_a">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="option_b"><i class="fas fa-check-circle mr-1"></i> Option B*</label>
                                                    <input type="text" class="form-control" id="option_b" name="option_b">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="option_c"><i class="fas fa-check-circle mr-1"></i> Option C</label>
                                                    <input type="text" class="form-control" id="option_c" name="option_c">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="option_d"><i class="fas fa-check-circle mr-1"></i> Option D</label>
                                                    <input type="text" class="form-control" id="option_d" name="option_d">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div id="true_false_options" style="display: none;">
                                        <div class="form-group">
                                            <label><i class="fas fa-check-circle mr-1"></i> Options</label>
                                            <div class="form-control bg-light" style="height: auto;">
                                                <p class="mb-0">True</p>
                                                <p class="mb-0">False</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="correct_answer"><i class="fas fa-key mr-1"></i> Correct Answer*</label>
                                        <div id="multiple_choice_answer">
                                            <select class="form-control" id="mc_correct_answer" name="correct_answer">
                                                <option value="A">A</option>
                                                <option value="B">B</option>
                                                <option value="C">C</option>
                                                <option value="D">D</option>
                                            </select>
                                        </div>
                                        <div id="true_false_answer" style="display: none;">
                                            <select class="form-control" id="tf_correct_answer">
                                                <option value="True">True</option>
                                                <option value="False">False</option>
                                            </select>
                                        </div>
                                        <div id="short_answer_field" style="display: none;">
                                            <input type="text" class="form-control" id="sa_correct_answer" placeholder="Enter the correct answer">
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="explanation"><i class="fas fa-info-circle mr-1"></i> Explanation (Optional)</label>
                                        <textarea class="form-control" id="explanation" name="explanation" rows="2" placeholder="Explain why this answer is correct..."></textarea>
                                    </div>
                                    
                                    <button type="submit" name="add_question" class="btn btn-primary">
                                        <i class="fas fa-plus-circle mr-1"></i> Add Question
                                    </button>
                                </form>
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
                            </div>
                            <div class="card-body">
                                <div class="accordion" id="questionsAccordion">
                                    <?php foreach ($questions as $index => $question): ?>
                                    <div class="card question-card mb-3">
                                        <div class="card-header py-3" id="heading<?php echo $question['id']; ?>">
                                            <h2 class="mb-0 d-flex justify-content-between align-items-center">
                                                <button class="btn btn-link text-left text-decoration-none question-text text-dark" type="button" data-toggle="collapse" data-target="#collapse<?php echo $question['id']; ?>">
                                                    <span class="mr-2">Q<?php echo $index + 1; ?>.</span>
                                                    <?php echo htmlspecialchars(substr($question['question_text'], 0, 100)) . (strlen($question['question_text']) > 100 ? '...' : ''); ?>
                                                    <?php if (!empty($question['question_image'])): ?>
                                                        <i class="fas fa-image ml-1 text-info"></i>
                                                    <?php endif; ?>
                                                </button>
                                                <div>
                                                    <span class="badge badge-info mr-2"><?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?></span>
                                                    <span class="badge badge-primary"><?php echo $question['marks']; ?> mark<?php echo $question['marks'] > 1 ? 's' : ''; ?></span>
                                                </div>
                                            </h2>
                                        </div>
                                        <div id="collapse<?php echo $question['id']; ?>" class="collapse" aria-labelledby="heading<?php echo $question['id']; ?>" data-parent="#questionsAccordion">
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <strong>Question:</strong> <?php echo nl2br(htmlspecialchars($question['question_text'])); ?>
                                                </div>
                                                
                                                <?php if (!empty($question['question_image'])): ?>
                                                <div class="mb-3">
                                                    <img src="../uploads/question_images/<?php echo htmlspecialchars($question['question_image']); ?>" alt="Question Image" class="img-fluid" style="max-height: 300px;">
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($question['question_type'] === 'multiple_choice'): ?>
                                                <div class="mb-3">
                                                    <strong>Options:</strong>
                                                    <div class="row mt-2">
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
                                                </div>
                                                <?php elseif ($question['question_type'] === 'true_false'): ?>
                                                <div class="mb-3">
                                                    <strong>Options:</strong>
                                                    <div class="mt-2">
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
                                                </div>
                                                <?php else: ?>
                                                <div class="mb-3">
                                                    <strong>Correct Answer:</strong> <?php echo htmlspecialchars($question['correct_answer']); ?>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($question['explanation'])): ?>
                                                <div class="mb-3">
                                                    <strong>Explanation:</strong> <?php echo nl2br(htmlspecialchars($question['explanation'])); ?>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <div class="mt-3">
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
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
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
        
        // Handle question type change
        $('#question_type').change(function() {
            var questionType = $(this).val();
            
            // Hide all answer fields first
            $('#multiple_choice_options, #true_false_options').hide();
            $('#multiple_choice_answer, #true_false_answer, #short_answer_field').hide();
            
            // Show relevant fields based on question type
            if (questionType === 'multiple_choice') {
                $('#multiple_choice_options').show();
                $('#multiple_choice_answer').show();
                $('#correct_answer').val($('#mc_correct_answer').val());
            } else if (questionType === 'true_false') {
                $('#true_false_options').show();
                $('#true_false_answer').show();
                $('#correct_answer').val($('#tf_correct_answer').val());
            } else if (questionType === 'short_answer') {
                $('#short_answer_field').show();
                $('#correct_answer').val($('#sa_correct_answer').val());
            }
        });
        
        // Handle correct answer changes
        $('#mc_correct_answer').change(function() {
            $('#correct_answer').val($(this).val());
        });
        
        $('#tf_correct_answer').change(function() {
            $('#correct_answer').val($(this).val());
        });
        
        $('#sa_correct_answer').keyup(function() {
            $('#correct_answer').val($(this).val());
        });
        
        // Image preview functionality
        $('#question_image').change(function() {
            var file = this.files[0];
            if (file) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    $('#image-preview').attr('src', e.target.result);
                    $('#image-preview-container').show();
                }
                reader.readAsDataURL(file);
            } else {
                $('#image-preview-container').hide();
            }
        });
    });
    </script>
</body>
</html> 