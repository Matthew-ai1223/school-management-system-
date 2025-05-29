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

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance()->getConnection();
    
    try {
        $query = "INSERT INTO exams (title, description, duration, passing_score, max_attempts, 
                                   is_active, created_by) 
                  VALUES (:title, :description, :duration, :passing_score, :max_attempts, 
                          :is_active, :created_by)";
        
        $stmt = $db->prepare($query);
        $result = $stmt->execute([
            ':title' => $_POST['title'],
            ':description' => $_POST['description'],
            ':duration' => $_POST['duration'],
            ':passing_score' => $_POST['passing_score'],
            ':max_attempts' => $_POST['max_attempts'],
            ':is_active' => isset($_POST['is_active']) ? 1 : 0,
            ':created_by' => $_SESSION['user_id']
        ]);

        if ($result) {
            $exam_id = $db->lastInsertId();
            header("Location: manage-questions.php?exam_id=" . $exam_id);
            exit();
        }
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Error creating exam: ' . $e->getMessage() . '</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Exam - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Create New Exam</h1>
                </div>

                <?php echo $message; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="title" class="form-label">Exam Title</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="duration" class="form-label">Duration (minutes)</label>
                                        <input type="number" class="form-control" id="duration" name="duration" 
                                               min="1" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="passing_score" class="form-label">Passing Score (%)</label>
                                        <input type="number" class="form-control" id="passing_score" 
                                               name="passing_score" min="0" max="100" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="max_attempts" class="form-label">Maximum Attempts</label>
                                        <input type="number" class="form-control" id="max_attempts" 
                                               name="max_attempts" min="1" value="1" required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_active" 
                                           name="is_active" checked>
                                    <label class="form-check-label" for="is_active">
                                        Make exam active
                                    </label>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="exams.php" class="btn btn-secondary me-md-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">Create Exam</button>
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