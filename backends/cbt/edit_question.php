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

// Check if question ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid question ID.";
    header("Location: dashboard.php");
    exit;
}

// Check if exam ID is provided
if (!isset($_GET['exam_id']) || !is_numeric($_GET['exam_id'])) {
    $_SESSION['error_message'] = "Invalid exam ID.";
    header("Location: dashboard.php");
    exit;
}

$questionId = intval($_GET['id']);
$examId = intval($_GET['exam_id']);

// Verify the exam belongs to the current teacher and get the question
$query = "SELECT q.*, e.title as exam_title, e.teacher_id 
          FROM cbt_questions q 
          JOIN cbt_exams e ON q.exam_id = e.id 
          WHERE q.id = ? AND q.exam_id = ? AND e.teacher_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $questionId, $examId, $teacherId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Question not found or you don't have permission to edit it.";
    header("Location: dashboard.php");
    exit;
}

$question = $result->fetch_assoc();
$examTitle = $question['exam_title'];

// Process form submission
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_question'])) {
    $questionText = trim($_POST['question_text'] ?? '');
    $questionType = $_POST['question_type'] ?? 'multiple_choice';
    $marks = intval($_POST['marks'] ?? 1);
    $correctAnswer = trim($_POST['correct_answer'] ?? '');
    $optionA = isset($_POST['option_a']) ? trim($_POST['option_a']) : null;
    $optionB = isset($_POST['option_b']) ? trim($_POST['option_b']) : null;
    $optionC = isset($_POST['option_c']) ? trim($_POST['option_c']) : null;
    $optionD = isset($_POST['option_d']) ? trim($_POST['option_d']) : null;
    $explanation = trim($_POST['explanation'] ?? '');
    
    // Keep existing image by default
    $questionImage = $question['question_image'];
    
    // Handle image upload if a new one is provided
    if (isset($_FILES['question_image']) && $_FILES['question_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/question_images/';
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Delete the old image if it exists
        if (!empty($questionImage) && file_exists($uploadDir . $questionImage)) {
            unlink($uploadDir . $questionImage);
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
    
    // Handle image deletion
    if (isset($_POST['remove_image']) && $_POST['remove_image'] == 1) {
        $uploadDir = '../uploads/question_images/';
        
        // Delete the old image if it exists
        if (!empty($questionImage) && file_exists($uploadDir . $questionImage)) {
            unlink($uploadDir . $questionImage);
        }
        
        $questionImage = null;
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
            // Update question
            $updateQuery = "UPDATE cbt_questions SET question_text = ?, question_type = ?, marks = ?, 
                          correct_answer = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, 
                          explanation = ?, question_image = ? WHERE id = ? AND exam_id = ?";
            
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("ssissssssii", $questionText, $questionType, $marks, $correctAnswer, 
                            $optionA, $optionB, $optionC, $optionD, $explanation, $questionImage, $questionId, $examId);
            
            if ($stmt->execute()) {
                $successMessage = "Question updated successfully!";
                
                // Fetch the updated question
                $stmt = $conn->prepare($query);
                $stmt->bind_param("iii", $questionId, $examId, $teacherId);
                $stmt->execute();
                $result = $stmt->get_result();
                $question = $result->fetch_assoc();
            } else {
                $errorMessage = "Error updating question: " . $conn->error;
            }
        }
    }
}

// Page title
$pageTitle = "Edit Question";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo $pageTitle; ?> - ACE MODEL COLLEGE</title>
    
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
            <i class="fas fa-laptop-code mr-2"></i> ACE MODEL COLLEGE - CBT System
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
                        Edit Question for <span class="text-primary"><?php echo htmlspecialchars($examTitle); ?></span>
                    </h1>
                    <div>
                        <a href="view_questions.php?exam_id=<?php echo $examId; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left mr-1"></i> Back to Questions
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
                
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Question Details</h6>
                    </div>
                    <div class="card-body">
                        <form method="post" action="" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="question_text"><i class="fas fa-question-circle mr-1"></i> Question Text*</label>
                                <textarea class="form-control" id="question_text" name="question_text" rows="3" required><?php echo htmlspecialchars($question['question_text']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="question_image"><i class="fas fa-image mr-1"></i> Question Image (Optional)</label>
                                <?php if (!empty($question['question_image'])): ?>
                                    <div class="mb-2">
                                        <img src="../uploads/question_images/<?php echo htmlspecialchars($question['question_image']); ?>" alt="Current Question Image" class="question-image-preview">
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="remove_image" id="remove_image" value="1">
                                        <label class="form-check-label" for="remove_image">
                                            Remove current image
                                        </label>
                                    </div>
                                <?php endif; ?>
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
                                            <option value="multiple_choice" <?php echo ($question['question_type'] === 'multiple_choice') ? 'selected' : ''; ?>>Multiple Choice</option>
                                            <option value="true_false" <?php echo ($question['question_type'] === 'true_false') ? 'selected' : ''; ?>>True/False</option>
                                            <option value="short_answer" <?php echo ($question['question_type'] === 'short_answer') ? 'selected' : ''; ?>>Short Answer</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="marks"><i class="fas fa-star mr-1"></i> Marks</label>
                                        <input type="number" class="form-control" id="marks" name="marks" min="1" value="<?php echo htmlspecialchars($question['marks']); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div id="multiple_choice_options" <?php echo ($question['question_type'] !== 'multiple_choice') ? 'style="display: none;"' : ''; ?>>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="option_a"><i class="fas fa-check-circle mr-1"></i> Option A*</label>
                                            <input type="text" class="form-control" id="option_a" name="option_a" value="<?php echo htmlspecialchars($question['option_a'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="option_b"><i class="fas fa-check-circle mr-1"></i> Option B*</label>
                                            <input type="text" class="form-control" id="option_b" name="option_b" value="<?php echo htmlspecialchars($question['option_b'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="option_c"><i class="fas fa-check-circle mr-1"></i> Option C</label>
                                            <input type="text" class="form-control" id="option_c" name="option_c" value="<?php echo htmlspecialchars($question['option_c'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="option_d"><i class="fas fa-check-circle mr-1"></i> Option D</label>
                                            <input type="text" class="form-control" id="option_d" name="option_d" value="<?php echo htmlspecialchars($question['option_d'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="true_false_options" <?php echo ($question['question_type'] !== 'true_false') ? 'style="display: none;"' : ''; ?>>
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
                                <input type="hidden" id="current_correct_answer" value="<?php echo htmlspecialchars($question['correct_answer']); ?>">
                                
                                <div id="multiple_choice_answer" <?php echo ($question['question_type'] !== 'multiple_choice') ? 'style="display: none;"' : ''; ?>>
                                    <select class="form-control" id="mc_correct_answer" name="correct_answer" <?php echo ($question['question_type'] === 'multiple_choice') ? 'required' : ''; ?>>
                                        <option value="A" <?php echo ($question['question_type'] === 'multiple_choice' && $question['correct_answer'] === 'A') ? 'selected' : ''; ?>>A</option>
                                        <option value="B" <?php echo ($question['question_type'] === 'multiple_choice' && $question['correct_answer'] === 'B') ? 'selected' : ''; ?>>B</option>
                                        <option value="C" <?php echo ($question['question_type'] === 'multiple_choice' && $question['correct_answer'] === 'C') ? 'selected' : ''; ?>>C</option>
                                        <option value="D" <?php echo ($question['question_type'] === 'multiple_choice' && $question['correct_answer'] === 'D') ? 'selected' : ''; ?>>D</option>
                                    </select>
                                </div>
                                
                                <div id="true_false_answer" <?php echo ($question['question_type'] !== 'true_false') ? 'style="display: none;"' : ''; ?>>
                                    <select class="form-control" id="tf_correct_answer" <?php echo ($question['question_type'] === 'true_false') ? 'required' : ''; ?>>
                                        <option value="True" <?php echo ($question['question_type'] === 'true_false' && $question['correct_answer'] === 'True') ? 'selected' : ''; ?>>True</option>
                                        <option value="False" <?php echo ($question['question_type'] === 'true_false' && $question['correct_answer'] === 'False') ? 'selected' : ''; ?>>False</option>
                                    </select>
                                </div>
                                
                                <div id="short_answer_field" <?php echo ($question['question_type'] !== 'short_answer') ? 'style="display: none;"' : ''; ?>>
                                    <input type="text" class="form-control" id="sa_correct_answer" placeholder="Enter the correct answer" value="<?php echo ($question['question_type'] === 'short_answer') ? htmlspecialchars($question['correct_answer']) : ''; ?>" <?php echo ($question['question_type'] === 'short_answer') ? 'required' : ''; ?>>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="explanation"><i class="fas fa-info-circle mr-1"></i> Explanation (Optional)</label>
                                <textarea class="form-control" id="explanation" name="explanation" rows="2" placeholder="Explain why this answer is correct..."><?php echo htmlspecialchars($question['explanation'] ?? ''); ?></textarea>
                            </div>
                            
                            <hr>
                            
                            <button type="submit" name="update_question" class="btn btn-primary">
                                <i class="fas fa-save mr-1"></i> Update Question
                            </button>
                            
                            <a href="view_questions.php?exam_id=<?php echo $examId; ?>" class="btn btn-secondary ml-2">
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
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
        
        // Handle question type change
        $('#question_type').change(function() {
            var questionType = $(this).val();
            var currentAnswer = $('#current_correct_answer').val();
            
            // Hide all answer fields first
            $('#multiple_choice_options, #true_false_options').hide();
            $('#multiple_choice_answer, #true_false_answer, #short_answer_field').hide();
            
            // Show relevant fields based on question type
            if (questionType === 'multiple_choice') {
                $('#multiple_choice_options').show();
                $('#multiple_choice_answer').show();
                $('#mc_correct_answer').attr('name', 'correct_answer').attr('required', true);
                $('#tf_correct_answer').removeAttr('name').removeAttr('required');
                $('#sa_correct_answer').removeAttr('name').removeAttr('required');
            } else if (questionType === 'true_false') {
                $('#true_false_options').show();
                $('#true_false_answer').show();
                $('#tf_correct_answer').attr('name', 'correct_answer').attr('required', true);
                $('#mc_correct_answer').removeAttr('name').removeAttr('required');
                $('#sa_correct_answer').removeAttr('name').removeAttr('required');
            } else if (questionType === 'short_answer') {
                $('#short_answer_field').show();
                $('#sa_correct_answer').attr('name', 'correct_answer').attr('required', true);
                $('#mc_correct_answer').removeAttr('name').removeAttr('required');
                $('#tf_correct_answer').removeAttr('name').removeAttr('required');
            }
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
        
        // Handle remove image checkbox
        $('#remove_image').change(function() {
            if ($(this).is(':checked')) {
                $('#question_image').prop('disabled', true);
            } else {
                $('#question_image').prop('disabled', false);
            }
        });
    });
    </script>
</body>
</html> 