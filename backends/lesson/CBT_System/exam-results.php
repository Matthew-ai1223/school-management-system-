<?php
require_once 'config/config.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';

session_start();

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$attempt_id = filter_input(INPUT_GET, 'attempt', FILTER_VALIDATE_INT);
if (!$attempt_id) {
    header('Location: dashboard.php');
    exit();
}

$db = Database::getInstance()->getConnection();

// Get attempt details with exam info
$query = "SELECT ea.*, e.title as exam_title, e.passing_score, e.duration
          FROM exam_attempts ea 
          JOIN exams e ON ea.exam_id = e.id 
          WHERE ea.id = :attempt_id AND ea.user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->execute([
    ':attempt_id' => $attempt_id,
    ':user_id' => $_SESSION['user_id']
]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
    header('Location: dashboard.php');
    exit();
}

// Get user responses with correct answers
$query = "SELECT q.question_text, q.correct_answer, ur.selected_answer,
          q.option_a, q.option_b, q.option_c, q.option_d, q.explanation
          FROM user_responses ur
          JOIN questions q ON ur.question_id = q.id
          WHERE ur.attempt_id = :attempt_id";
$stmt = $db->prepare($query);
$stmt->execute([':attempt_id' => $attempt_id]);
$responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate percentage
$total_questions = count($responses);
$percentage = $total_questions > 0 ? ($attempt['score'] / $total_questions) * 100 : 0;
$passed = $percentage >= $attempt['passing_score'];

// Generate certificate if passed
if ($passed) {
    $cert_query = "INSERT IGNORE INTO certificates (user_id, exam_id, certificate_number) 
                   VALUES (:user_id, :exam_id, :cert_number)";
    $cert_number = 'CERT-' . date('Y') . '-' . str_pad($attempt['exam_id'], 4, '0', STR_PAD_LEFT) 
                   . '-' . str_pad($_SESSION['user_id'], 6, '0', STR_PAD_LEFT);
    
    $stmt = $db->prepare($cert_query);
    $stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':exam_id' => $attempt['exam_id'],
        ':cert_number' => $cert_number
    ]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Results - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow">
                    <div class="card-header">
                        <h3 class="text-center">Exam Results</h3>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <h4><?php echo htmlspecialchars($attempt['exam_title']); ?></h4>
                            <div class="display-4 my-3">
                                Score: <?php echo $percentage; ?>%
                            </div>
                            <div class="alert <?php echo $passed ? 'alert-success' : 'alert-danger'; ?>">
                                <?php echo $passed ? 'Congratulations! You passed!' : 'Sorry, you did not pass.'; ?>
                            </div>
                            <?php if ($passed): ?>
                            <a href="download-certificate.php?attempt=<?php echo $attempt_id; ?>" 
                               class="btn btn-success">
                                Download Certificate
                            </a>
                            <?php endif; ?>
                        </div>

                        <h5>Detailed Results:</h5>
                        <?php foreach ($responses as $index => $response): ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6>Question <?php echo $index + 1; ?></h6>
                                <p><?php echo htmlspecialchars($response['question_text']); ?></p>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-1">Your Answer: 
                                            <span class="<?php echo $response['selected_answer'] === $response['correct_answer'] 
                                                ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo $response['selected_answer']; ?>
                                            </span>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1">Correct Answer: 
                                            <span class="text-success">
                                                <?php echo $response['correct_answer']; ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                                
                                <?php if ($response['selected_answer'] !== $response['correct_answer']): ?>
                                <div class="alert alert-info mt-2">
                                    <strong>Explanation:</strong> 
                                    <?php echo htmlspecialchars($response['explanation']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <div class="text-center mt-4">
                            <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 