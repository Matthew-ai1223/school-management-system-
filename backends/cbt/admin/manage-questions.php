<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$auth = new Auth();

// Check teacher authentication
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}

// Validate exam_id parameter
if (!isset($_GET['exam_id'])) {
    error_log("No exam_id provided in URL");
    $_SESSION['error_message'] = "No exam ID provided.";
    header('Location: exams.php');
    exit();
}

$exam_id = filter_var($_GET['exam_id'], FILTER_VALIDATE_INT);
if ($exam_id === false || $exam_id <= 0) {
    error_log("Invalid exam_id format: " . $_GET['exam_id']);
    $_SESSION['error_message'] = "Invalid exam ID format.";
    header('Location: exams.php');
    exit();
}

$db = Database::getInstance()->getConnection();

// Debug information
echo "<!-- Debug Info: Attempting to load exam ID: {$exam_id} -->\n";
echo "<!-- Debug Info: Teacher ID: {$_SESSION['teacher_id']} -->\n";

try {
    // First verify the exam exists and belongs to the teacher
    $verifyQuery = "SELECT id FROM exams 
                   WHERE id = :exam_id AND created_by = :teacher_id";
    $verifyStmt = $db->prepare($verifyQuery);
    
    // Debug query
    error_log("Debug - Verify Query: " . $verifyQuery);
    error_log("Debug - Params: exam_id={$exam_id}, teacher_id={$_SESSION['teacher_id']}");
    
    $verifyStmt->execute([
        ':exam_id' => $exam_id,
        ':teacher_id' => $_SESSION['teacher_id']
    ]);
    
    $verifyResult = $verifyStmt->fetch();
    error_log("Debug - Verify Result: " . print_r($verifyResult, true));
    
    if (!$verifyResult) {
        error_log("Exam not found or access denied. Exam ID: {$exam_id}, Teacher ID: {$_SESSION['teacher_id']}");
        throw new Exception('Exam not found or access denied. Please verify the exam exists and you have permission to access it.');
    }

    // Get exam details with question count
    $query = "SELECT e.*, COUNT(q.id) as total_questions 
              FROM exams e 
              LEFT JOIN questions q ON e.id = q.exam_id 
              WHERE e.id = :exam_id AND e.created_by = :teacher_id 
              GROUP BY e.id";  // Simplified GROUP BY
              
    // Debug query
    error_log("Debug - Main Query: " . $query);
              
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':exam_id' => $exam_id,
        ':teacher_id' => $_SESSION['teacher_id']
    ]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("Debug - Exam Result: " . print_r($exam, true));

    if (!$exam) {
        error_log("No exam details found for exam_id: {$exam_id}");
        throw new Exception('Error loading exam details: No exam found with the specified ID.');
    }

    // Get questions for this exam
    $query = "SELECT * FROM questions WHERE exam_id = :exam_id ORDER BY id";
    $stmt = $db->prepare($query);
    $stmt->execute([':exam_id' => $exam_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Debug - Questions Count: " . count($questions));

    // Get attempt statistics
    $query = "SELECT COUNT(*) as attempt_count, COALESCE(AVG(score), 0) as avg_score 
              FROM exam_attempts 
              WHERE exam_id = :exam_id AND status = 'completed'";
    $stmt = $db->prepare($query);
    $stmt->execute([':exam_id' => $exam_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("Debug - Stats Result: " . print_r($stats, true));

    $exam['attempt_count'] = $stats['attempt_count'];
    $exam['avg_score'] = round($stats['avg_score'], 1);

    error_log("Successfully loaded exam ID {$exam_id} with " . count($questions) . " questions");

} catch (PDOException $e) {
    error_log("Database error in manage-questions.php: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
    error_log("Error Details: " . print_r($e->errorInfo, true));
    
    // Display error directly on page for debugging
    echo "<div class='alert alert-danger'>";
    echo "<h4>Database Error Details:</h4>";
    echo "<pre>";
    echo "Error: " . htmlspecialchars($e->getMessage()) . "\n";
    echo "SQL State: " . htmlspecialchars($e->getCode()) . "\n";
    echo "Error Info: " . htmlspecialchars(print_r($e->errorInfo, true));
    echo "</pre>";
    echo "</div>";
    exit();
    
} catch (Exception $e) {
    error_log("Error in manage-questions.php: " . $e->getMessage());
    
    // Display error directly on page for debugging
    echo "<div class='alert alert-danger'>";
    echo "<h4>Error Details:</h4>";
    echo "<pre>";
    echo htmlspecialchars($e->getMessage());
    echo "</pre>";
    echo "</div>";
    exit();
}

// Debug information before displaying page
echo "<!-- Debug Info: Successfully loaded exam data -->\n";

$message = '';
if (isset($_SESSION['message'])) {
    $message = '<div class="alert alert-info">' . $_SESSION['message'] . '</div>';
    unset($_SESSION['message']);
}
if (isset($_SESSION['error_message'])) {
    $message = '<div class="alert alert-danger">' . $_SESSION['error_message'] . '</div>';
    unset($_SESSION['error_message']);
}
if (isset($_SESSION['success_message'])) {
    $message = '<div class="alert alert-success">' . $_SESSION['success_message'] . '</div>';
    unset($_SESSION['success_message']);
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
    <style>
        .exam-info-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .exam-info-card .badge {
            font-size: 0.9em;
            padding: 8px 12px;
        }
        .exam-stat {
            text-align: center;
            padding: 10px;
            border-right: 1px solid #dee2e6;
        }
        .exam-stat:last-child {
            border-right: none;
        }
        .exam-stat .stat-value {
            font-size: 1.5em;
            font-weight: bold;
            color: #2c3e50;
        }
        .exam-stat .stat-label {
            font-size: 0.9em;
            color: #6c757d;
        }
        .breadcrumb {
            margin-bottom: 0.5rem;
        }
        .breadcrumb-item a {
            color: #3498db;
        }
        .breadcrumb-item a:hover {
            color: #2980b9;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
                    <div>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="exams.php" class="text-decoration-none">
                                        <i class='bx bx-chevron-left'></i> Back to Exams
                                    </a>
                                </li>
                                <li class="breadcrumb-item active" aria-current="page">
                                    <?php echo htmlspecialchars($exam['title']); ?>
                                </li>
                            </ol>
                        </nav>
                        <h1 class="h2 mb-0"><?php echo htmlspecialchars($exam['title']); ?></h1>
                        <p class="text-muted mb-0">Manage Questions</p>
                    </div>
                    <div>
                        <a href="add-question.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-primary me-2">
                            <i class='bx bx-plus'></i> Add Question
                        </a>
                        <a href="bulk-upload-questions.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-success">
                            <i class='bx bx-upload'></i> Bulk Upload
                        </a>
                    </div>
                </div>

                <!-- Exam Information Card -->
                <div class="exam-info-card">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <p class="mb-2"><?php echo nl2br(htmlspecialchars($exam['description'])); ?></p>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-primary">Class: <?php echo htmlspecialchars($exam['class']); ?></span>
                                <span class="badge bg-<?php echo $exam['is_active'] ? 'success' : 'warning'; ?>">
                                    <?php echo $exam['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="row">
                                <div class="col-4 exam-stat">
                                    <div class="stat-value"><?php echo $exam['duration']; ?></div>
                                    <div class="stat-label">Minutes</div>
                                </div>
                                <div class="col-4 exam-stat">
                                    <div class="stat-value"><?php echo count($questions); ?></div>
                                    <div class="stat-label">Questions</div>
                                </div>
                                <div class="col-4 exam-stat">
                                    <div class="stat-value"><?php echo $exam['passing_score']; ?>%</div>
                                    <div class="stat-label">Pass Mark</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($message): ?>
                    <?php echo $message; ?>
                <?php endif; ?>

                <?php if (empty($questions)): ?>
                    <div class="alert alert-warning">
                        <i class='bx bx-info-circle me-2'></i>
                        No questions have been added to this exam yet. 
                        <a href="add-question.php?exam_id=<?php echo $exam_id; ?>" class="alert-link">Add your first question</a>
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
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Show success message before reload
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-success';
                        alertDiv.textContent = data.message;
                        document.querySelector('main').insertBefore(alertDiv, document.querySelector('.accordion'));
                        
                        // Reload after a short delay to show the message
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        // Show error in a Bootstrap alert
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-danger';
                        alertDiv.textContent = data.message || 'Error deleting question';
                        document.querySelector('main').insertBefore(alertDiv, document.querySelector('.accordion'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Show error in a Bootstrap alert
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger';
                    alertDiv.textContent = 'An error occurred while deleting the question. Please try again.';
                    document.querySelector('main').insertBefore(alertDiv, document.querySelector('.accordion'));
                });
            }
        }
    </script>
</body>
</html> 