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
$message = '';

// Get exam details
$query = "SELECT title FROM exams WHERE id = :exam_id";
$stmt = $db->prepare($query);
$stmt->execute([':exam_id' => $exam_id]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    header('Location: exams.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_FILES['questions_file']) || $_FILES['questions_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Please select a valid CSV file.');
        }

        $file = $_FILES['questions_file']['tmp_name'];
        $handle = fopen($file, 'r');
        
        if (!$handle) {
            throw new Exception('Error opening file.');
        }

        // Skip header row
        $header = fgetcsv($handle);
        $expected_headers = ['question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer', 'explanation'];
        if ($header !== $expected_headers) {
            throw new Exception('Invalid CSV format. Please use the template provided.');
        }

        $db->beginTransaction();
        $success_count = 0;
        $error_count = 0;
        $errors = [];
        $row_number = 1;

        $query = "INSERT INTO questions (exam_id, question_text, option_a, option_b, option_c, option_d, 
                                       correct_answer, explanation) 
                  VALUES (:exam_id, :question_text, :option_a, :option_b, :option_c, :option_d, 
                          :correct_answer, :explanation)";
        $stmt = $db->prepare($query);

        while (($row = fgetcsv($handle)) !== false) {
            $row_number++;
            
            // Validate row data
            if (count($row) !== 7) {
                $errors[] = "Row $row_number: Invalid number of columns";
                $error_count++;
                continue;
            }

            if (!in_array(strtoupper($row[5]), ['A', 'B', 'C', 'D'])) {
                $errors[] = "Row $row_number: Invalid correct answer. Must be A, B, C, or D";
                $error_count++;
                continue;
            }

            try {
                $result = $stmt->execute([
                    ':exam_id' => $exam_id,
                    ':question_text' => $row[0],
                    ':option_a' => $row[1],
                    ':option_b' => $row[2],
                    ':option_c' => $row[3],
                    ':option_d' => $row[4],
                    ':correct_answer' => strtoupper($row[5]),
                    ':explanation' => $row[6]
                ]);

                if ($result) {
                    $success_count++;
                }
            } catch (PDOException $e) {
                $errors[] = "Row $row_number: Database error";
                $error_count++;
            }
        }

        fclose($handle);

        if ($error_count === 0) {
            $db->commit();
            $_SESSION['message'] = "Successfully imported $success_count questions.";
            header("Location: manage-questions.php?exam_id=" . $exam_id);
            exit();
        } else {
            $db->rollBack();
            $message = '<div class="alert alert-warning">';
            $message .= "Imported: $success_count, Errors: $error_count<br>";
            $message .= "Errors:<br>" . implode("<br>", $errors);
            $message .= '</div>';
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
    <title>Bulk Upload Questions - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Bulk Upload Questions - <?php echo htmlspecialchars($exam['title']); ?></h1>
                </div>

                <?php echo $message; ?>

                <div class="card">
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h5>Instructions:</h5>
                            <ol>
                                <li>Download the <a href="templates/questions_template.csv">CSV template</a></li>
                                <li>Fill in your questions following the template format</li>
                                <li>Upload the completed CSV file</li>
                            </ol>
                            <p>Note: The correct answer should be A, B, C, or D</p>
                        </div>

                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="questions_file" class="form-label">Questions CSV File</label>
                                <input type="file" class="form-control" id="questions_file" 
                                       name="questions_file" accept=".csv" required>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="manage-questions.php?exam_id=<?php echo $exam_id; ?>" 
                                   class="btn btn-secondary me-md-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">Upload Questions</button>
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