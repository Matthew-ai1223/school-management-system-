<?php
// Start output buffering
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

$auth = new Auth();

// if (!$auth->isLoggedIn() || !in_array($_SESSION['user_role'], ['admin', 'super_admin'])) {
//     header('Location: login.php');
//     exit();
// }

$question_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$question_id) {
    header('Location: exams.php');
    exit();
}

$db = Database::getInstance()->getConnection();
$message = '';

// Get question details
$query = "SELECT q.*, e.title as exam_title, e.id as exam_id 
          FROM questions q 
          JOIN exams e ON q.exam_id = e.id 
          WHERE q.id = :question_id";
$stmt = $db->prepare($query);
$stmt->execute([':question_id' => $question_id]);
$question = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$question) {
    header('Location: exams.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle image upload if present
        $image_url = $question['image_url']; // Keep existing image by default
        if (isset($_FILES['question_image']) && $_FILES['question_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/questions/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['question_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception('Invalid file type. Only JPG, PNG and GIF files are allowed.');
            }

            $filename = uniqid('question_') . '.' . $file_extension;
            $filepath = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['question_image']['tmp_name'], $filepath)) {
                // Delete old image if exists
                if ($question['image_url']) {
                    $old_image_path = '../' . $question['image_url'];
                    if (file_exists($old_image_path)) {
                        unlink($old_image_path);
                    }
                }
                $image_url = 'uploads/questions/' . $filename;
            }
        } elseif (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
            // Delete existing image if remove checkbox is checked
            if ($question['image_url']) {
                $old_image_path = '../' . $question['image_url'];
                if (file_exists($old_image_path)) {
                    unlink($old_image_path);
                }
            }
            $image_url = null;
        }

        $query = "UPDATE questions 
                 SET question_text = :question_text,
                     image_url = :image_url,
                     option_a = :option_a,
                     option_b = :option_b,
                     option_c = :option_c,
                     option_d = :option_d,
                     correct_answer = :correct_answer,
                     explanation = :explanation
                 WHERE id = :question_id";
        
        $stmt = $db->prepare($query);
        $result = $stmt->execute([
            ':question_id' => $question_id,
            ':question_text' => $_POST['question_text'],
            ':image_url' => $image_url,
            ':option_a' => $_POST['option_a'],
            ':option_b' => $_POST['option_b'],
            ':option_c' => $_POST['option_c'],
            ':option_d' => $_POST['option_d'],
            ':correct_answer' => $_POST['correct_answer'],
            ':explanation' => $_POST['explanation']
        ]);

        if ($result) {
            $_SESSION['message'] = 'Question updated successfully.';
            header("Location: manage-questions.php?exam_id=" . $question['exam_id']);
            exit();
        }
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Question - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Edit Question - <?php echo htmlspecialchars($question['exam_title']); ?></h1>
                </div>

                <?php echo $message; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="question_text" class="form-label">Question Text</label>
                                <textarea class="form-control" id="question_text" name="question_text" 
                                         rows="3" required><?php echo htmlspecialchars($question['question_text']); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="question_image" class="form-label">Question Image</label>
                                <?php if ($question['image_url']): ?>
                                    <div class="mb-2">
                                        <img src="../<?php echo htmlspecialchars($question['image_url']); ?>" 
                                             class="img-fluid" style="max-height: 200px;" alt="Current Image">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="remove_image" id="remove_image" value="1">
                                            <label class="form-check-label" for="remove_image">
                                                Remove current image
                                            </label>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="question_image" 
                                       name="question_image" accept="image/*">
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="option_a" class="form-label">Option A</label>
                                        <input type="text" class="form-control" id="option_a" name="option_a" 
                                               value="<?php echo htmlspecialchars($question['option_a']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="option_b" class="form-label">Option B</label>
                                        <input type="text" class="form-control" id="option_b" name="option_b" 
                                               value="<?php echo htmlspecialchars($question['option_b']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="option_c" class="form-label">Option C</label>
                                        <input type="text" class="form-control" id="option_c" name="option_c" 
                                               value="<?php echo htmlspecialchars($question['option_c']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="option_d" class="form-label">Option D</label>
                                        <input type="text" class="form-control" id="option_d" name="option_d" 
                                               value="<?php echo htmlspecialchars($question['option_d']); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="correct_answer" class="form-label">Correct Answer</label>
                                <select class="form-select" id="correct_answer" name="correct_answer" required>
                                    <option value="">Select correct answer</option>
                                    <option value="A" <?php echo $question['correct_answer'] === 'A' ? 'selected' : ''; ?>>Option A</option>
                                    <option value="B" <?php echo $question['correct_answer'] === 'B' ? 'selected' : ''; ?>>Option B</option>
                                    <option value="C" <?php echo $question['correct_answer'] === 'C' ? 'selected' : ''; ?>>Option C</option>
                                    <option value="D" <?php echo $question['correct_answer'] === 'D' ? 'selected' : ''; ?>>Option D</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="explanation" class="form-label">Explanation (optional)</label>
                                <textarea class="form-control" id="explanation" name="explanation" 
                                         rows="3"><?php echo htmlspecialchars($question['explanation'] ?? ''); ?></textarea>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="manage-questions.php?exam_id=<?php echo $question['exam_id']; ?>" 
                                   class="btn btn-secondary me-md-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">Update Question</button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 