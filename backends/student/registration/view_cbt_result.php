<?php
require_once '../../config.php';
require_once '../../database.php';
require_once '../../utils.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Get student information
$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];
$registration_number = $_SESSION['registration_number'];

// Get exam ID from URL
$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

if ($exam_id <= 0) {
    $_SESSION['error'] = "Invalid exam selected.";
    header('Location: student_dashboard.php#cbt-exams');
    exit;
}

// Get exam details
$examQuery = "SELECT e.*, s.name AS subject_name 
              FROM cbt_exams e
              JOIN subjects s ON e.subject_id = s.id
              WHERE e.id = ?";
$stmt = $conn->prepare($examQuery);
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$examResult = $stmt->get_result();

if ($examResult->num_rows === 0) {
    $_SESSION['error'] = "Exam not found.";
    header('Location: student_dashboard.php#cbt-exams');
    exit;
}

$exam = $examResult->fetch_assoc();

// Get student's exam attempt
$studentExamQuery = "SELECT * FROM cbt_student_exams 
                     WHERE student_id = ? AND exam_id = ?";
$stmt = $conn->prepare($studentExamQuery);
$stmt->bind_param("ii", $student_id, $exam_id);
$stmt->execute();
$studentExamResult = $stmt->get_result();

if ($studentExamResult->num_rows === 0) {
    $_SESSION['error'] = "You haven't taken this exam yet.";
    header('Location: student_dashboard.php#cbt-exams');
    exit;
}

$student_exam = $studentExamResult->fetch_assoc();
$student_exam_id = $student_exam['id'];

// Check if results are available to view
$can_view_results = ($student_exam['status'] === 'Completed' && $exam['show_results'] === 1) || 
                    ($student_exam['status'] === 'In Progress');

$show_score = $exam['show_results'] === 1;

// Get all questions and student answers
$questionsQuery = "SELECT q.*, sa.answer_text, sa.selected_options
                  FROM cbt_questions q
                  LEFT JOIN cbt_student_answers sa ON q.id = sa.question_id AND sa.student_exam_id = ?
                  WHERE q.exam_id = ?
                  ORDER BY q.id ASC";
$stmt = $conn->prepare($questionsQuery);
$stmt->bind_param("ii", $student_exam_id, $exam_id);
$stmt->execute();
$questionsResult = $stmt->get_result();

$questions = [];
$total_answered = 0;
$total_correct = 0;

while ($question = $questionsResult->fetch_assoc()) {
    // Get options for multiple choice questions
    if ($question['question_type'] === 'Multiple Choice') {
        $optionsQuery = "SELECT * FROM cbt_options WHERE question_id = ? ORDER BY id";
        $stmt = $conn->prepare($optionsQuery);
        $stmt->bind_param("i", $question['id']);
        $stmt->execute();
        $optionsResult = $stmt->get_result();
        
        $question['options'] = [];
        $correct_options = [];
        
        while ($option = $optionsResult->fetch_assoc()) {
            $question['options'][] = $option;
            if ($option['is_correct']) {
                $correct_options[] = $option['option_text'];
            }
        }
        
        $question['correct_options'] = $correct_options;
        
        // Check if answer is correct
        $selected_options = !empty($question['selected_options']) 
                          ? explode(',', $question['selected_options']) 
                          : [];
        
        $question['selected_options'] = $selected_options;
        
        if (!empty($selected_options)) {
            $total_answered++;
            $is_correct = count($selected_options) === count($correct_options);
            
            if ($is_correct) {
                $all_correct = true;
                foreach ($selected_options as $selected) {
                    if (!in_array($selected, $correct_options)) {
                        $all_correct = false;
                        break;
                    }
                }
                
                if ($all_correct && count($selected_options) === count($correct_options)) {
                    $total_correct++;
                    $question['is_correct'] = true;
                } else {
                    $question['is_correct'] = false;
                }
            } else {
                $question['is_correct'] = false;
            }
        } else {
            $question['is_correct'] = false;
        }
    } else if ($question['question_type'] === 'True/False') {
        // For true/false questions
        if (!empty($question['answer_text'])) {
            $total_answered++;
            $correct_answer = $question['correct_answer'] ?? '';
            
            if (strtolower($question['answer_text']) === strtolower($correct_answer)) {
                $total_correct++;
                $question['is_correct'] = true;
            } else {
                $question['is_correct'] = false;
            }
        } else {
            $question['is_correct'] = false;
        }
    }
    
    $questions[] = $question;
}

// Total questions
$total_questions = count($questions);

// Include header
include 'includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><?php echo htmlspecialchars($exam['title']); ?> - Results</h4>
                        <a href="student_dashboard.php#cbt-exams" class="btn btn-light btn-sm">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Subject:</strong> <?php echo htmlspecialchars($exam['subject_name']); ?></p>
                            <p><strong>Total Questions:</strong> <?php echo $total_questions; ?></p>
                            <p><strong>Questions Answered:</strong> <?php echo $total_answered; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Started:</strong> <?php echo date('M d, Y g:i A', strtotime($student_exam['started_at'])); ?></p>
                            <p><strong>Submitted:</strong> 
                                <?php echo $student_exam['submitted_at'] 
                                      ? date('M d, Y g:i A', strtotime($student_exam['submitted_at'])) 
                                      : 'Not submitted'; ?>
                            </p>
                            <p><strong>Status:</strong> 
                                <span class="badge bg-<?php echo $student_exam['status'] === 'Completed' ? 'success' : 'warning'; ?>">
                                    <?php echo $student_exam['status']; ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    
                    <?php if ($student_exam['status'] === 'In Progress'): ?>
                        <div class="alert alert-warning">
                            <h5><i class="fas fa-exclamation-triangle"></i> Exam In Progress</h5>
                            <p>You have not completed this exam yet. Return to the exam to continue.</p>
                            <a href="take_cbt_exam.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-primary">
                                Continue Exam
                            </a>
                        </div>
                    <?php elseif (!$exam['show_results']): ?>
                        <div class="alert alert-info">
                            <h5><i class="fas fa-info-circle"></i> Results Not Available Yet</h5>
                            <p>The results for this exam are not available for viewing at this time. Please check back later.</p>
                        </div>
                    <?php else: ?>
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5 class="mb-3">Your Result</h5>
                                        <div class="display-4 font-weight-bold mb-3 <?php echo $student_exam['score'] >= $exam['passing_score'] ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $student_exam['score']; ?>%
                                        </div>
                                        <?php if ($student_exam['score'] >= $exam['passing_score']): ?>
                                            <div class="alert alert-success">
                                                <i class="fas fa-check-circle"></i> Congratulations! You passed the exam.
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-danger">
                                                <i class="fas fa-times-circle"></i> You did not meet the passing score.
                                            </div>
                                        <?php endif; ?>
                                        <div class="progress mt-3" style="height: 25px;">
                                            <div class="progress-bar <?php echo $student_exam['score'] >= $exam['passing_score'] ? 'bg-success' : 'bg-danger'; ?>" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $student_exam['score']; ?>%" 
                                                 aria-valuenow="<?php echo $student_exam['score']; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <?php echo $student_exam['score']; ?>%
                                            </div>
                                        </div>
                                        <div class="text-muted mt-2">
                                            Passing Score: <?php echo $exam['passing_score']; ?>%
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <h5 class="mb-3">Question Analysis</h5>
                        
                        <?php foreach ($questions as $index => $question): ?>
                            <div class="card mb-3">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Question <?php echo $index + 1; ?></h6>
                                    <?php if (!empty($question['answer_text']) || !empty($question['selected_options'])): ?>
                                        <?php if ($question['is_correct']): ?>
                                            <span class="badge bg-success">Correct</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Incorrect</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Not Answered</span>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <div class="question-text mb-3">
                                        <?php echo nl2br(htmlspecialchars($question['question_text'])); ?>
                                        
                                        <?php if (!empty($question['image_path'])): ?>
                                            <div class="mt-2">
                                                <img src="../../uploads/cbt_images/<?php echo $question['image_path']; ?>" 
                                                     alt="Question Image" class="img-fluid" style="max-height: 200px;">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="answer-analysis">
                                        <?php if ($question['question_type'] === 'Multiple Choice'): ?>
                                            <h6>Options:</h6>
                                            <ul class="list-group">
                                                <?php foreach ($question['options'] as $option): ?>
                                                    <li class="list-group-item <?php 
                                                    if (in_array($option['option_text'], $question['selected_options']) && $option['is_correct']) {
                                                        echo 'list-group-item-success';
                                                    } elseif (in_array($option['option_text'], $question['selected_options']) && !$option['is_correct']) {
                                                        echo 'list-group-item-danger';
                                                    } elseif (!in_array($option['option_text'], $question['selected_options']) && $option['is_correct']) {
                                                        echo 'list-group-item-warning';
                                                    }
                                                    ?>">
                                                        <?php if (in_array($option['option_text'], $question['selected_options'])): ?>
                                                            <i class="fas fa-check-circle mr-2"></i>
                                                        <?php else: ?>
                                                            <i class="far fa-circle mr-2"></i>
                                                        <?php endif; ?>
                                                        
                                                        <?php echo htmlspecialchars($option['option_text']); ?>
                                                        
                                                        <?php if ($option['is_correct']): ?>
                                                            <span class="badge bg-success float-right">Correct Answer</span>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php elseif ($question['question_type'] === 'True/False'): ?>
                                            <h6>Your Answer:</h6>
                                            <?php if (!empty($question['answer_text'])): ?>
                                                <div class="<?php echo $question['is_correct'] ? 'text-success' : 'text-danger'; ?>">
                                                    <strong><?php echo htmlspecialchars($question['answer_text']); ?></strong>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-muted">Not answered</div>
                                            <?php endif; ?>
                                            
                                            <?php if (!$question['is_correct'] && !empty($question['answer_text'])): ?>
                                                <div class="text-success mt-2">
                                                    <strong>Correct Answer: <?php echo htmlspecialchars($question['correct_answer'] ?? ''); ?></strong>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <div class="text-center mt-4">
                        <a href="student_dashboard.php#cbt-exams" class="btn btn-primary">
                            Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 