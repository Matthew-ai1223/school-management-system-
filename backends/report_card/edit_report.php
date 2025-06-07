<?php
require_once '../config.php';
require_once '../database.php';
require_once '../auth.php';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

$message = '';
$report_card = null;
$report_details = [];

if (isset($_GET['id'])) {
    try {
        $report_id = $conn->real_escape_string($_GET['id']);
        
        // Fetch report card
        $sql = "SELECT rc.*, 
                CONCAT(s.first_name, ' ', s.last_name) as student_name,
                s.registration_number,
                CONCAT(t.first_name, ' ', t.last_name) as teacher_name
                FROM report_cards rc
                LEFT JOIN students s ON rc.student_id = s.id
                LEFT JOIN teachers t ON rc.created_by = t.id
                WHERE rc.id = '$report_id'";
        
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $report_card = $result->fetch_assoc();

            // Fetch report details
            $sql = "SELECT rcd.*, rs.subject_name
                    FROM report_card_details rcd
                    LEFT JOIN report_subjects rs ON rcd.subject_id = rs.id
                    WHERE rcd.report_card_id = '$report_id'
                    ORDER BY rs.subject_name";
            
            $result = $conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $report_details[] = $row;
                }
            }
        }

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $conn->begin_transaction();
            
            try {
                // Update report card
                $teacher_comment = $conn->real_escape_string($_POST['teacher_comment']);
                $principal_comment = $conn->real_escape_string($_POST['principal_comment']);
                
                $sql = "UPDATE report_cards SET 
                        teacher_comment = '$teacher_comment',
                        principal_comment = '$principal_comment'
                        WHERE id = '$report_id'";
                
                if (!$conn->query($sql)) {
                    throw new Exception("Error updating report card: " . $conn->error);
                }

                // Update report details
                foreach ($_POST['scores'] as $detail_id => $scores) {
                    $test_score = $conn->real_escape_string($scores['test']);
                    $exam_score = $conn->real_escape_string($scores['exam']);
                    $total_score = $test_score + $exam_score;
                    $grade = $conn->real_escape_string($scores['grade']);
                    $remark = $conn->real_escape_string($scores['remark']);
                    $comment = $conn->real_escape_string($scores['comment']);
                    
                    $sql = "UPDATE report_card_details SET 
                            test_score = '$test_score',
                            exam_score = '$exam_score',
                            total_score = '$total_score',
                            grade = '$grade',
                            remark = '$remark',
                            teacher_comment = '$comment'
                            WHERE id = '$detail_id'";
                    
                    if (!$conn->query($sql)) {
                        throw new Exception("Error updating report details: " . $conn->error);
                    }
                }

                // Recalculate total and average scores
                $sql = "SELECT SUM(total_score) as total, COUNT(*) as count 
                        FROM report_card_details 
                        WHERE report_card_id = '$report_id'";
                $result = $conn->query($sql);
                if ($result && $row = $result->fetch_assoc()) {
                    $total_score = $row['total'];
                    $average_score = $total_score / $row['count'];
                    
                    $sql = "UPDATE report_cards SET 
                            total_score = '$total_score',
                            average_score = '$average_score'
                            WHERE id = '$report_id'";
                    
                    if (!$conn->query($sql)) {
                        throw new Exception("Error updating scores: " . $conn->error);
                    }
                }

                $conn->commit();
                $message = "Report card updated successfully!";
                
                // Refresh the data
                header("Location: view_report.php?id=$report_id");
                exit();
                
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Error: " . $e->getMessage();
            }
        }
    } catch(Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Report Card</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($report_card): ?>
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">Edit Report Card</h3>
                        <a href="view_report.php?id=<?php echo $report_card['id']; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to View
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <!-- Student Information -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <p><strong>Student Name:</strong> <?php echo htmlspecialchars($report_card['student_name'] ?? 'N/A'); ?></p>
                                <p><strong>Registration Number:</strong> <?php echo htmlspecialchars($report_card['registration_number'] ?? 'N/A'); ?></p>
                                <p><strong>Class:</strong> <?php echo htmlspecialchars($report_card['class']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Academic Year:</strong> <?php echo htmlspecialchars($report_card['academic_year']); ?></p>
                                <p><strong>Term:</strong> <?php echo htmlspecialchars($report_card['term']); ?></p>
                            </div>
                        </div>

                        <!-- Academic Performance -->
                        <div class="table-responsive mb-4">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Test Score (30%)</th>
                                        <th>Exam Score (70%)</th>
                                        <th>Total</th>
                                        <th>Grade</th>
                                        <th>Remark</th>
                                        <th>Comment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_details as $detail): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($detail['subject_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <input type="number" step="0.1" class="form-control" 
                                                   name="scores[<?php echo $detail['id']; ?>][test]" 
                                                   value="<?php echo $detail['test_score']; ?>" required>
                                        </td>
                                        <td>
                                            <input type="number" step="0.1" class="form-control" 
                                                   name="scores[<?php echo $detail['id']; ?>][exam]" 
                                                   value="<?php echo $detail['exam_score']; ?>" required>
                                        </td>
                                        <td><?php echo number_format($detail['total_score'], 1); ?></td>
                                        <td>
                                            <input type="text" class="form-control" 
                                                   name="scores[<?php echo $detail['id']; ?>][grade]" 
                                                   value="<?php echo $detail['grade']; ?>" required>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control" 
                                                   name="scores[<?php echo $detail['id']; ?>][remark]" 
                                                   value="<?php echo $detail['remark']; ?>">
                                        </td>
                                        <td>
                                            <textarea class="form-control" 
                                                      name="scores[<?php echo $detail['id']; ?>][comment]"><?php echo $detail['teacher_comment']; ?></textarea>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Comments -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Teacher's Comment:</label>
                                    <textarea class="form-control" name="teacher_comment" rows="3"><?php echo $report_card['teacher_comment']; ?></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Principal's Comment:</label>
                                    <textarea class="form-control" name="principal_comment" rows="3"><?php echo $report_card['principal_comment']; ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">Report card not found.</div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 