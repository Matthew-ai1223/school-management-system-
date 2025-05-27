<?php
require_once '../config.php';
require_once '../database.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if teacher is logged in
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

// Get attempt ID from URL
$attemptId = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : null;

if (!$attemptId) {
    header("Location: results.php");
    exit;
}

// Get attempt details
$attemptQuery = "SELECT 
    se.*,
    s.first_name,
    s.last_name,
    s.registration_number,
    e.title as exam_title,
    e.subject,
    e.class,
    e.passing_score,
    (SELECT COUNT(*) FROM cbt_questions WHERE exam_id = e.id) as total_questions
FROM cbt_student_exams se
JOIN students s ON se.student_id = s.id
JOIN cbt_exams e ON se.exam_id = e.id
WHERE se.id = ? AND e.teacher_id = ?";

$stmt = $conn->prepare($attemptQuery);
$stmt->bind_param("ii", $attemptId, $teacherId);
$stmt->execute();
$attemptResult = $stmt->get_result();

if ($attemptResult->num_rows === 0) {
    header("Location: results.php");
    exit;
}

$attempt = $attemptResult->fetch_assoc();

// Get student's answers
$answersQuery = "SELECT 
    sa.*,
    q.question_text,
    q.question_type,
    q.marks,
    GROUP_CONCAT(o.option_text ORDER BY o.id SEPARATOR '||') as options,
    GROUP_CONCAT(o.is_correct ORDER BY o.id SEPARATOR '||') as correct_options
FROM cbt_student_answers sa
JOIN cbt_questions q ON sa.question_id = q.id
LEFT JOIN cbt_options o ON q.id = o.question_id
WHERE sa.attempt_id = ?
GROUP BY sa.id, q.id
ORDER BY q.id";

$stmt = $conn->prepare($answersQuery);
$stmt->bind_param("i", $attemptId);
$stmt->execute();
$answersResult = $stmt->get_result();
$answers = [];

while ($row = $answersResult->fetch_assoc()) {
    $answers[] = $row;
}

// Calculate duration
$duration = 0;
if ($attempt['submitted_at'] && $attempt['started_at']) {
    $duration = strtotime($attempt['submitted_at']) - strtotime($attempt['started_at']);
}
$minutes = floor($duration / 60);
$seconds = $duration % 60;

// Page title
$pageTitle = "Exam Attempt Details";
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
        
        .score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: bold;
            margin: 0 auto;
        }
        
        .score-excellent {
            background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%);
            color: white;
        }
        
        .score-good {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
        }
        
        .score-average {
            background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%);
            color: white;
        }
        
        .score-poor {
            background: linear-gradient(135deg, #e74a3b 0%, #be2617 100%);
            color: white;
        }
        
        .question-card {
            transition: transform 0.2s;
        }
        
        .question-card:hover {
            transform: translateY(-5px);
        }
        
        .correct-answer {
            color: var(--success);
            font-weight: bold;
        }
        
        .wrong-answer {
            color: var(--danger);
            text-decoration: line-through;
        }
        
        .option-list {
            list-style: none;
            padding-left: 0;
        }
        
        .option-list li {
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            border-radius: 0.35rem;
            background-color: #f8f9fc;
        }
        
        .option-list li.correct {
            background-color: #e8f5e9;
            border-left: 4px solid var(--success);
        }
        
        .option-list li.incorrect {
            background-color: #ffebee;
            border-left: 4px solid var(--danger);
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
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="results.php">
                        <i class="fas fa-chart-bar mr-1"></i> Results
                    </a>
                </li>
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

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-file-alt mr-2"></i> Exam Attempt Details
                        </h5>
                        <a href="results.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left mr-1"></i> Back to Results
                        </a>
                    </div>
                    <div class="card-body">
                        <!-- Student and Exam Information -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title text-primary">Student Information</h6>
                                        <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($attempt['first_name'] . ' ' . $attempt['last_name']); ?></p>
                                        <p class="mb-1"><strong>Registration Number:</strong> <?php echo htmlspecialchars($attempt['registration_number']); ?></p>
                                        <p class="mb-0"><strong>Class:</strong> <?php echo htmlspecialchars($attempt['class']); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title text-primary">Exam Information</h6>
                                        <p class="mb-1"><strong>Title:</strong> <?php echo htmlspecialchars($attempt['exam_title']); ?></p>
                                        <p class="mb-1"><strong>Subject:</strong> <?php echo htmlspecialchars($attempt['subject']); ?></p>
                                        <p class="mb-0"><strong>Duration:</strong> <?php echo $minutes . 'm ' . $seconds . 's'; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Score Summary -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h6 class="card-title text-primary">Score</h6>
                                        <div class="score-circle <?php
                                            if ($attempt['score'] >= 70) echo 'score-excellent';
                                            elseif ($attempt['score'] >= 60) echo 'score-good';
                                            elseif ($attempt['score'] >= 50) echo 'score-average';
                                            else echo 'score-poor';
                                        ?>">
                                            <?php echo $attempt['score']; ?>%
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h6 class="card-title text-primary">Status</h6>
                                        <h2 class="mb-0">
                                            <?php if ($attempt['status'] == 'completed'): ?>
                                                <span class="badge badge-success">Completed</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">Incomplete</span>
                                            <?php endif; ?>
                                        </h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h6 class="card-title text-primary">Passing Score</h6>
                                        <h2 class="mb-0"><?php echo $attempt['passing_score']; ?>%</h2>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Questions and Answers -->
                        <div class="row">
                            <div class="col-12">
                                <h5 class="mb-3">Questions and Answers</h5>
                                <?php foreach ($answers as $index => $answer): ?>
                                    <div class="card question-card mb-3">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                Question <?php echo $index + 1; ?> 
                                                <span class="badge badge-info float-right"><?php echo $answer['marks']; ?> marks</span>
                                            </h6>
                                            <p class="card-text"><?php echo nl2br(htmlspecialchars($answer['question_text'])); ?></p>
                                            
                                            <?php if ($answer['question_type'] == 'Multiple Choice'): ?>
                                                <ul class="option-list">
                                                    <?php 
                                                    $options = explode('||', $answer['options']);
                                                    $correctOptions = explode('||', $answer['correct_options']);
                                                    $selectedOption = $answer['student_answer'];
                                                    foreach ($options as $i => $option): 
                                                        $isCorrect = $correctOptions[$i] == 1;
                                                        $isSelected = ($selectedOption == ($i + 1));
                                                    ?>
                                                        <li class="<?php echo $isCorrect ? 'correct' : ($isSelected ? 'incorrect' : ''); ?>">
                                                            <?php echo htmlspecialchars($option); ?>
                                                            <?php if ($isCorrect): ?>
                                                                <i class="fas fa-check text-success ml-2"></i>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <div class="mt-3">
                                                    <strong>Student's Answer:</strong>
                                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($answer['student_answer'] ?? 'No answer provided')); ?></p>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($answer['marks_awarded'] !== null): ?>
                                                <div class="mt-3">
                                                    <strong>Marks Awarded:</strong> <?php echo $answer['marks_awarded']; ?>/<?php echo $answer['marks']; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 