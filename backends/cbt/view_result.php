<?php
require_once '../../config.php';
require_once '../../database.php';
session_start();

// Check if user is logged in as a student
if (!isset($_SESSION['student_id']) || !isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    // Redirect to student login page
    header('Location: ../student/login.php?error=Please+login+to+access+this+page');
    exit;
}

// Get student information
$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Get exam ID from GET parameter
$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

if ($exam_id <= 0) {
    // Redirect to dashboard if no valid exam ID
    header('Location: ../student/registration/student_dashboard.php?error=Invalid+exam');
    exit;
}

// Get the student exam result
$examResultQuery = "SELECT se.*, 
                   e.title AS exam_title, 
                   e.description AS exam_description,
                   e.passing_score,
                   s.name AS subject_name,
                   e.class_id AS class_name
                   FROM cbt_student_exams se
                   JOIN cbt_exams e ON se.exam_id = e.id
                   JOIN subjects s ON e.subject_id = s.id
                   WHERE se.student_id = ? AND se.exam_id = ?";

$stmt = $conn->prepare($examResultQuery);
$stmt->bind_param("ii", $student_id, $exam_id);
$stmt->execute();
$examResultSet = $stmt->get_result();

if ($examResultSet->num_rows === 0) {
    // Exam result not found
    header('Location: ../student/registration/student_dashboard.php?error=No+result+found+for+this+exam');
    exit;
}

$examResult = $examResultSet->fetch_assoc();

// Get student answers with questions
$answersQuery = "SELECT sa.*, 
                q.question_text, 
                q.question_type,
                q.marks,
                q.image_path
                FROM cbt_student_answers sa
                JOIN cbt_questions q ON sa.question_id = q.id
                WHERE sa.student_exam_id = ?
                ORDER BY q.id";

$stmt = $conn->prepare($answersQuery);
$stmt->bind_param("i", $examResult['id']);
$stmt->execute();
$answersResult = $stmt->get_result();

$answers = [];
while ($row = $answersResult->fetch_assoc()) {
    // Get correct answer for this question
    $correctAnswerQuery = "SELECT option_text FROM cbt_options 
                           WHERE question_id = ? AND is_correct = 1";
    
    $stmt = $conn->prepare($correctAnswerQuery);
    $stmt->bind_param("i", $row['question_id']);
    $stmt->execute();
    $correctOptionsResult = $stmt->get_result();
    
    $correctOptions = [];
    while ($option = $correctOptionsResult->fetch_assoc()) {
        $correctOptions[] = $option['option_text'];
    }
    
    $row['correct_answer'] = implode(', ', $correctOptions);
    $answers[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Result - <?php echo htmlspecialchars($examResult['exam_title']); ?></title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #3a56d4;
            --secondary-color: #f8f9fa;
            --success-color: #2ec4b6;
            --info-color: #4895ef;
            --warning-color: #ff9f1c;
            --danger-color: #e71d36;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
        }
        
        body {
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            padding-top: 20px;
            padding-bottom: 50px;
        }
        
        .result-container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        
        .result-header {
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .result-title {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .score-summary {
            background: linear-gradient(145deg, #f0f4ff, #fff);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
        }
        
        .score-circle {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            font-weight: 700;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .circle-bg {
            position: absolute;
            width: 100%;
            height: 100%;
            background: conic-gradient(
                var(--primary-color) <?php echo $examResult['percentage']; ?>%, 
                rgba(240, 244, 255, 0.3) <?php echo $examResult['percentage']; ?>%
            );
            transform: rotate(-90deg);
            transform-origin: center;
        }
        
        .circle-content {
            position: relative;
            z-index: 2;
            background: white;
            border-radius: 50%;
            width: 85%;
            height: 85%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: var(--primary-color);
        }
        
        .percentage-text {
            font-size: 2rem;
            font-weight: 800;
            line-height: 1;
        }
        
        .badge-result {
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 1rem;
            font-weight: 600;
            margin-top: 10px;
        }
        
        .badge-passed {
            background-color: rgba(46, 196, 182, 0.1);
            color: var(--success-color);
        }
        
        .badge-failed {
            background-color: rgba(231, 29, 54, 0.1);
            color: var(--danger-color);
        }
        
        .score-details {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 20px;
        }
        
        .detail-item {
            background: #fff;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            text-align: center;
            flex: 1;
            min-width: 150px;
        }
        
        .detail-label {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .questions-summary {
            margin-top: 40px;
        }
        
        .summary-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--dark-color);
        }
        
        .question-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.2s;
        }
        
        .question-card:hover {
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }
        
        .question-card.correct {
            border-left: 5px solid var(--success-color);
        }
        
        .question-card.incorrect {
            border-left: 5px solid var(--danger-color);
        }
        
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .question-number {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }
        
        .question-status {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .status-correct {
            color: var(--success-color);
        }
        
        .status-incorrect {
            color: var(--danger-color);
        }
        
        .question-content {
            margin-bottom: 15px;
        }
        
        .question-image {
            max-width: 100%;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .answer-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
        }
        
        .answer-row {
            display: flex;
            margin-bottom: 10px;
        }
        
        .answer-row:last-child {
            margin-bottom: 0;
        }
        
        .answer-label {
            font-weight: 600;
            min-width: 120px;
            color: #6c757d;
        }
        
        .student-answer {
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .correct-answer {
            color: var(--success-color);
            font-weight: 500;
        }
        
        .btn-back {
            background: #fff;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 10px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-back:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .marks-badge {
            background: #f0f4ff;
            color: var(--primary-color);
            border-radius: 20px;
            padding: 5px 10px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .result-container {
                padding: 20px 15px;
            }
            
            .score-circle {
                width: 120px;
                height: 120px;
            }
            
            .percentage-text {
                font-size: 1.5rem;
            }
            
            .detail-item {
                flex-basis: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="result-container">
            <div class="result-header">
                <h2 class="result-title">
                    <i class="fas fa-poll"></i>
                    Exam Result: <?php echo htmlspecialchars($examResult['exam_title']); ?>
                </h2>
                <p class="text-muted"><?php echo htmlspecialchars($examResult['exam_description']); ?></p>
                
                <div class="d-flex justify-content-between flex-wrap mt-3">
                    <div>
                        <small class="text-muted">Subject:</small>
                        <div><strong><?php echo htmlspecialchars($examResult['subject_name']); ?></strong></div>
                    </div>
                    <div>
                        <small class="text-muted">Class:</small>
                        <div><strong><?php echo htmlspecialchars($examResult['class_name']); ?></strong></div>
                    </div>
                    <div>
                        <small class="text-muted">Student:</small>
                        <div><strong><?php echo htmlspecialchars($student_name); ?></strong></div>
                    </div>
                    <div>
                        <small class="text-muted">Completed:</small>
                        <div><strong><?php echo date('M d, Y g:i A', strtotime($examResult['completed_at'])); ?></strong></div>
                    </div>
                </div>
            </div>
            
            <div class="score-summary">
                <div class="score-circle">
                    <div class="circle-bg"></div>
                    <div class="circle-content">
                        <div class="percentage-text">
                            <?php echo number_format($examResult['percentage'], 1); ?>%
                        </div>
                        <small>Score</small>
                    </div>
                </div>
                
                <div class="mt-3">
                    <?php if ($examResult['status'] === 'passed'): ?>
                        <div class="badge badge-result badge-passed">
                            <i class="fas fa-check-circle"></i> Passed
                        </div>
                    <?php else: ?>
                        <div class="badge badge-result badge-failed">
                            <i class="fas fa-times-circle"></i> Failed
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="score-details">
                    <div class="detail-item">
                        <div class="detail-label">Score</div>
                        <div class="detail-value"><?php echo $examResult['score']; ?> / <?php echo $examResult['total_score']; ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Passing Score</div>
                        <div class="detail-value"><?php echo $examResult['passing_score']; ?>%</div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Correct Answers</div>
                        <div class="detail-value">
                            <?php 
                            $correctCount = 0;
                            foreach ($answers as $answer) {
                                if ($answer['is_correct']) $correctCount++;
                            }
                            echo $correctCount . ' / ' . count($answers); 
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="questions-summary">
                <h3 class="summary-title">
                    <i class="fas fa-clipboard-list"></i> Question Review
                </h3>
                
                <?php foreach ($answers as $index => $answer): ?>
                    <div class="question-card <?php echo $answer['is_correct'] ? 'correct' : 'incorrect'; ?>">
                        <div class="question-header">
                            <div class="question-number">
                                <span>Question <?php echo $index + 1; ?></span>
                                <span class="marks-badge"><?php echo $answer['possible_marks']; ?> marks</span>
                            </div>
                            <div class="question-status">
                                <?php if ($answer['is_correct']): ?>
                                    <span class="status-correct">
                                        <i class="fas fa-check-circle"></i> Correct
                                    </span>
                                <?php else: ?>
                                    <span class="status-incorrect">
                                        <i class="fas fa-times-circle"></i> Incorrect
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="question-content">
                            <p><?php echo htmlspecialchars($answer['question_text']); ?></p>
                            
                            <?php if (!empty($answer['image_path'])): ?>
                                <img src="../../uploads/cbt_images/<?php echo $answer['image_path']; ?>" 
                                     alt="Question Image" class="question-image">
                            <?php endif; ?>
                        </div>
                        
                        <div class="answer-section">
                            <div class="answer-row">
                                <div class="answer-label">Your Answer:</div>
                                <div class="student-answer">
                                    <?php echo htmlspecialchars(str_replace('||', ', ', $answer['student_answer'])); ?>
                                </div>
                            </div>
                            
                            <div class="answer-row">
                                <div class="answer-label">Correct Answer:</div>
                                <div class="correct-answer">
                                    <?php echo htmlspecialchars($answer['correct_answer']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-4">
                <a href="../student/registration/student_dashboard.php?tab=exams" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS and dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.min.js"></script>
</body>
</html> 