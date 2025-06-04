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

$message = '';
$db = Database::getInstance()->getConnection();

// Fetch subjects assigned to the logged-in teacher
try {
    $subjectQuery = "SELECT DISTINCT subject 
                     FROM teacher_subjects 
                     WHERE teacher_id = :teacher_id 
                     ORDER BY subject";
    $subjectStmt = $db->prepare($subjectQuery);
    $subjectStmt->execute([':teacher_id' => $_SESSION['teacher_id']]);
    $subjects = $subjectStmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($subjects)) {
        $message = '<div class="alert alert-warning">No subjects have been assigned to you yet. Please contact the administrator.</div>';
    }
} catch (PDOException $e) {
    $subjects = [];
    $message .= '<div class="alert alert-warning">Could not fetch subject list: ' . $e->getMessage() . '</div>';
}

// Fetch available classes from the database
try {
    $classQuery = "SELECT DISTINCT class FROM students WHERE class IS NOT NULL AND class != '' ORDER BY class";
    $classStmt = $db->query($classQuery);
    $classes = $classStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $classes = [];
    $message = '<div class="alert alert-warning">Could not fetch class list: ' . $e->getMessage() . '</div>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $db->beginTransaction();

        // Insert into exams table
        $query = "INSERT INTO exams (
                    title, 
                    subject,
                    description, 
                    duration, 
                    passing_score, 
                    max_attempts, 
                    is_active, 
                    created_by, 
                    class,
                    created_at
                ) VALUES (
                    :title, 
                    :subject,
                    :description, 
                    :duration, 
                    :passing_score, 
                    :max_attempts, 
                    :is_active, 
                    :created_by, 
                    :class,
                    NOW()
                )";
        
        $stmt = $db->prepare($query);
        $result = $stmt->execute([
            ':title' => $_POST['subject'] . ' Exam',
            ':subject' => $_POST['subject'],
            ':description' => $_POST['description'],
            ':duration' => $_POST['duration'],
            ':passing_score' => $_POST['passing_score'],
            ':max_attempts' => $_POST['max_attempts'],
            ':is_active' => isset($_POST['is_active']) ? 1 : 0,
            ':created_by' => $_SESSION['teacher_id'],
            ':class' => $_POST['class']
        ]);

        if ($result) {
            $exam_id = $db->lastInsertId();
            
            // Verify the exam was created
            $verifyQuery = "SELECT id FROM exams WHERE id = :exam_id";
            $verifyStmt = $db->prepare($verifyQuery);
            $verifyStmt->execute([':exam_id' => $exam_id]);
            
            if ($verifyStmt->fetch()) {
                $db->commit();
                $_SESSION['success_message'] = "Exam created successfully.";
                header("Location: manage-questions.php?exam_id=" . $exam_id);
                exit();
            } else {
                throw new Exception("Failed to verify exam creation");
            }
        } else {
            throw new Exception("Failed to create exam");
        }
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Database error in create-exam.php: " . $e->getMessage());
        $message = '<div class="alert alert-danger">Error creating exam: ' . $e->getMessage() . '</div>';
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error in create-exam.php: " . $e->getMessage());
        $message = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
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
    <style>
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #10b981;
            --background-color: #f9fafb;
            --border-color: #e5e7eb;
        }

        body {
            background-color: var(--background-color);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .card {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .card-body {
            padding: 2rem;
        }

        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .form-select, .form-control {
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 0.625rem 1rem;
            font-size: 1rem;
            transition: all 0.2s ease;
        }

        .form-select:focus, .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        .form-text {
            color: #6b7280;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .btn {
            padding: 0.625rem 1.25rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: #4338ca;
            border-color: #4338ca;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: #fff;
            border: 2px solid var(--border-color);
            color: #374151;
        }

        .btn-secondary:hover {
            background-color: #f3f4f6;
            border-color: #d1d5db;
            color: #111827;
        }

        .alert {
            border-radius: 8px;
            border: 1px solid transparent;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }

        .alert-warning {
            background-color: #fffbeb;
            border-color: #fef3c7;
            color: #92400e;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .d-grid {
            margin-top: 2rem;
        }

        @media (max-width: 768px) {
            .card-body {
                padding: 1.5rem;
            }
        }
    </style>
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
                                <label for="subject" class="form-label">Select Subject</label>
                                <select class="form-select" id="subject" name="subject" required>
                                    <option value="">Choose a subject...</option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?php echo htmlspecialchars($subject); ?>">
                                            <?php echo htmlspecialchars($subject); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">The exam title will be automatically generated based on the selected subject.</div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="duration" class="form-label">Duration (minutes)</label>
                                        <input type="number" class="form-control" id="duration" name="duration" 
                                               min="1" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="passing_score" class="form-label">Passing Score (%)</label>
                                        <input type="number" class="form-control" id="passing_score" 
                                               name="passing_score" min="0" max="100" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="max_attempts" class="form-label">Maximum Attempts</label>
                                        <input type="number" class="form-control" id="max_attempts" 
                                               name="max_attempts" min="1" value="1" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="class" class="form-label">Class</label>
                                        <select class="form-select" id="class" name="class" required>
                                            <option value="">Select Class</option>
                                            <?php foreach ($classes as $class): ?>
                                                <option value="<?php echo htmlspecialchars($class); ?>">
                                                    <?php echo htmlspecialchars($class); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
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