<?php
require_once '../config.php';
require_once '../database.php';
require_once '../auth.php';

// Check if user is logged in and is a teacher
// session_start();
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
//     header('Location: ../unauthorized.php');
//     exit();
// }

$message = '';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Handle subject addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subject'])) {
    $subject_name = trim($_POST['subject_name']);
    $subject_code = trim($_POST['subject_code']);
    
    try {
        $subject_name = $conn->real_escape_string($subject_name);
        $subject_code = $conn->real_escape_string($subject_code);
        
        $sql = "INSERT INTO report_subjects (subject_name, subject_code) VALUES ('$subject_name', '$subject_code')";
        if ($conn->query($sql)) {
            $message = "Subject added successfully!";
        } else {
            throw new Exception($conn->error);
        }
    } catch(Exception $e) {
        $message = "Error adding subject: " . $e->getMessage();
    }
}

// Fetch existing subjects
try {
    $sql = "SELECT * FROM report_subjects ORDER BY subject_name";
    $result = $conn->query($sql);
    $subjects = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
    }
} catch(Exception $e) {
    $message = "Error fetching subjects: " . $e->getMessage();
    $subjects = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subjects - Report Card System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Manage Subjects</h2>
        <a href="generate_report.php" style="background-color:rgb(255, 183, 0); color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;">Back</a>
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- Add Subject Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h4>Add New Subject</h4>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label for="subject_name" class="form-label">Subject Name</label>
                                <input type="text" class="form-control" id="subject_name" name="subject_name" required>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label for="subject_code" class="form-label">Subject Code</label>
                                <input type="text" class="form-control" id="subject_code" name="subject_code" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" name="add_subject" class="btn btn-primary w-100">Add Subject</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Subjects List -->
        <div class="card">
            <div class="card-header">
                <h4>Existing Subjects</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Subject Name</th>
                                <th>Subject Code</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subjects as $subject): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                <td><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($subject['created_at'])); ?></td>
                                <td>
                                    <a href="edit_subject.php?id=<?php echo $subject['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="delete_subject.php?id=<?php echo $subject['id']; ?>" class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Are you sure you want to delete this subject?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 