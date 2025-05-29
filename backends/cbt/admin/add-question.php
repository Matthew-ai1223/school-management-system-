<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

session_start();

$auth = new Auth();

// Check teacher authentication
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}

$exam_id = filter_input(INPUT_GET, 'exam_id', FILTER_VALIDATE_INT);
if (!$exam_id) {
    header('Location: exams.php');
    exit();
}

$db = Database::getInstance()->getConnection();
$message = '';

// Get exam details and verify ownership
$query = "SELECT title FROM exams WHERE id = :exam_id AND created_by = :teacher_id";
$stmt = $db->prepare($query);
$stmt->execute([
    ':exam_id' => $exam_id,
    ':teacher_id' => $_SESSION['teacher_id']
]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    header('Location: exams.php');
    exit();
}

// Validate uploaded file
function validateImage($file) {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Error uploading file. Code: ' . $file['error'];
        return $errors;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);

    $allowed_types = [
        'image/jpeg',
        'image/png',
        'image/gif'
    ];

    if (!in_array($mime_type, $allowed_types)) {
        $errors[] = 'Invalid file type. Only JPG, PNG and GIF files are allowed.';
    }

    if ($file['size'] > 5242880) { // 5MB limit
        $errors[] = 'File is too large. Maximum size is 5MB.';
    }

    return $errors;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle image upload if present
        $image_url = null;
        if (isset($_FILES['question_image']) && $_FILES['question_image']['error'] === UPLOAD_ERR_OK) {
            $errors = validateImage($_FILES['question_image']);
            if (!empty($errors)) {
                throw new Exception(implode("\n", $errors));
            }

            $upload_dir = '../uploads/questions/';
            
            // Create directory if it doesn't exist
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
                $image_url = 'uploads/questions/' . $filename;
            }
        }

        $query = "INSERT INTO questions (exam_id, question_text, image_url, option_a, option_b, 
                                       option_c, option_d, correct_answer, explanation) 
                  VALUES (:exam_id, :question_text, :image_url, :option_a, :option_b, 
                          :option_c, :option_d, :correct_answer, :explanation)";
        
        $stmt = $db->prepare($query);
        $result = $stmt->execute([
            ':exam_id' => $exam_id,
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
            $_SESSION['message'] = 'Question added successfully.';
            header("Location: manage-questions.php?exam_id=" . $exam_id);
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
    <title>Add Question - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Add Question - <?php echo htmlspecialchars($exam['title']); ?></h1>
                </div>

                <?php echo $message; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="question_text" class="form-label">Question Text</label>
                                <textarea class="form-control" id="question_text" name="question_text" 
                                         rows="3" required></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="question_image" class="form-label">Question Image (optional)</label>
                                <input type="file" class="form-control" id="question_image" name="question_image" 
                                       accept="image/*">
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="option_a" class="form-label">Option A</label>
                                        <input type="text" class="form-control" id="option_a" name="option_a" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="option_b" class="form-label">Option B</label>
                                        <input type="text" class="form-control" id="option_b" name="option_b" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="option_c" class="form-label">Option C</label>
                                        <input type="text" class="form-control" id="option_c" name="option_c" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="option_d" class="form-label">Option D</label>
                                        <input type="text" class="form-control" id="option_d" name="option_d" required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="correct_answer" class="form-label">Correct Answer</label>
                                <select class="form-select" id="correct_answer" name="correct_answer" required>
                                    <option value="">Select correct answer</option>
                                    <option value="A">Option A</option>
                                    <option value="B">Option B</option>
                                    <option value="C">Option C</option>
                                    <option value="D">Option D</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="explanation" class="form-label">Explanation (optional)</label>
                                <textarea class="form-control" id="explanation" name="explanation" rows="3"></textarea>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="manage-questions.php?exam_id=<?php echo $exam_id; ?>" 
                                   class="btn btn-secondary me-md-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">Add Question</button>
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