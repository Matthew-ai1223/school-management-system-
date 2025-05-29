<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

session_start();

$auth = new Auth();

// if (!$auth->isLoggedIn() || !in_array($_SESSION['user_role'], ['admin', 'super_admin'])) {
//     header('Location: login.php');
//     exit();
// }

$exam_id = filter_input(INPUT_GET, 'exam_id', FILTER_VALIDATE_INT);
if (!$exam_id) {
    header('Location: exams.php');
    exit();
}

$db = Database::getInstance()->getConnection();

// Get exam details
$query = "SELECT * FROM exams WHERE id = :exam_id";
$stmt = $db->prepare($query);
$stmt->execute([':exam_id' => $exam_id]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    header('Location: exams.php');
    exit();
}

// Get questions for this exam
$query = "SELECT * FROM questions WHERE exam_id = :exam_id ORDER BY id";
$stmt = $db->prepare($query);
$stmt->execute([':exam_id' => $exam_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Questions - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Questions - <?php echo htmlspecialchars($exam['title']); ?></h1>
                    <div>
                        <a href="add-question.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-primary me-2">
                            <i class='bx bx-plus'></i> Add Question
                        </a>
                        <a href="bulk-upload-questions.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-success">
                            <i class='bx bx-upload'></i> Bulk Upload
                        </a>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-info"><?php echo $message; ?></div>
                <?php endif; ?>

                <?php if (empty($questions)): ?>
                    <div class="alert alert-warning">
                        No questions have been added to this exam yet. 
                        <a href="add-question.php?exam_id=<?php echo $exam_id; ?>">Add your first question</a>
                    </div>
                <?php else: ?>
                    <div class="accordion" id="questionAccordion">
                        <?php foreach ($questions as $index => $question): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading<?php echo $question['id']; ?>">
                                <button class="accordion-button collapsed" type="button" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#collapse<?php echo $question['id']; ?>">
                                    Question <?php echo $index + 1; ?>: 
                                    <?php echo htmlspecialchars(substr($question['question_text'], 0, 100)); ?>...
                                </button>
                            </h2>
                            <div id="collapse<?php echo $question['id']; ?>" 
                                 class="accordion-collapse collapse" 
                                 data-bs-parent="#questionAccordion">
                                <div class="accordion-body">
                                    <div class="mb-3">
                                        <strong>Question:</strong>
                                        <p><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>
                                        
                                        <?php if ($question['image_url']): ?>
                                        <div class="mb-2">
                                            <img src="<?php echo htmlspecialchars($question['image_url']); ?>" 
                                                 class="img-fluid" style="max-height: 200px;" alt="Question Image">
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Options:</strong>
                                            <ul class="list-group">
                                                <li class="list-group-item <?php echo $question['correct_answer'] === 'A' ? 'list-group-item-success' : ''; ?>">
                                                    A) <?php echo htmlspecialchars($question['option_a']); ?>
                                                </li>
                                                <li class="list-group-item <?php echo $question['correct_answer'] === 'B' ? 'list-group-item-success' : ''; ?>">
                                                    B) <?php echo htmlspecialchars($question['option_b']); ?>
                                                </li>
                                                <li class="list-group-item <?php echo $question['correct_answer'] === 'C' ? 'list-group-item-success' : ''; ?>">
                                                    C) <?php echo htmlspecialchars($question['option_c']); ?>
                                                </li>
                                                <li class="list-group-item <?php echo $question['correct_answer'] === 'D' ? 'list-group-item-success' : ''; ?>">
                                                    D) <?php echo htmlspecialchars($question['option_d']); ?>
                                                </li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Explanation:</strong>
                                            <p><?php echo nl2br(htmlspecialchars($question['explanation'] ?? '')); ?></p>
                                        </div>
                                    </div>

                                    <div class="btn-group">
                                        <a href="edit-question.php?id=<?php echo $question['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class='bx bx-edit'></i> Edit
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                onclick="deleteQuestion(<?php echo $question['id']; ?>)">
                                            <i class='bx bx-trash'></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteQuestion(questionId) {
            if (confirm('Are you sure you want to delete this question?')) {
                fetch('delete-question.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        question_id: questionId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Error deleting question: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the question.');
                });
            }
        }
    </script>
</body>
</html> 