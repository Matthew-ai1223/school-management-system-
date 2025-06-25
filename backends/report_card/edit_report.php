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
                // Handle new subject addition
                if (isset($_POST['action']) && $_POST['action'] === 'add_subject') {
                    $subject_id = $conn->real_escape_string($_POST['subject_id']);
                    
                    // Check if subject already exists in report card
                    $check_sql = "SELECT id FROM report_card_details 
                                WHERE report_card_id = '$report_id' 
                                AND subject_id = '$subject_id'";
                    $check_result = $conn->query($check_sql);
                    
                    if ($check_result && $check_result->num_rows > 0) {
                        throw new Exception("This subject already exists in the report card.");
                    }
                    
                    // Insert new subject
                    $sql = "INSERT INTO report_card_details 
                            (report_card_id, subject_id, test_score, exam_score, total_score) 
                            VALUES ('$report_id', '$subject_id', 0, 0, 0)";
                    
                    if (!$conn->query($sql)) {
                        throw new Exception("Error adding new subject: " . $conn->error);
                    }
                    
                    $conn->commit();
                    header("Location: edit_report.php?id=$report_id");
                    exit();
                }

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
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4>Academic Performance</h4>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                                <i class="fas fa-plus"></i> Add Subject
                            </button>
                        </div>
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

    <!-- Add Subject Modal -->
    <div class="modal fade" id="addSubjectModal" tabindex="-1" aria-labelledby="addSubjectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addSubjectModalLabel">Add New Subject</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_subject">
                        <div class="mb-3">
                            <label for="subject_id" class="form-label">Select Subject</label>
                            <select class="form-select" id="subject_id" name="subject_id" required>
                                <option value="">Choose a subject...</option>
                                <?php
                                // Fetch available subjects that are not already in the report card
                                $sql = "SELECT rs.* FROM report_subjects rs 
                                        WHERE rs.id NOT IN (
                                            SELECT subject_id FROM report_card_details 
                                            WHERE report_card_id = '$report_id'
                                        )
                                        ORDER BY rs.subject_name";
                                $subjects_result = $conn->query($sql);
                                if ($subjects_result) {
                                    while ($subject = $subjects_result->fetch_assoc()) {
                                        echo '<option value="' . $subject['id'] . '">' . 
                                             htmlspecialchars($subject['subject_name']) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Add Subject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Calculate scores and grades for all rows when page loads
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('tr').forEach(calculateRowScores);
        });

        // Add event listeners to test and exam score inputs
        document.querySelectorAll('input[name^="scores"][name$="[test]"], input[name^="scores"][name$="[exam]"]').forEach(input => {
            input.addEventListener('input', function() {
                const row = this.closest('tr');
                calculateRowScores(row);
            });
        });

        // Function to calculate scores, grades and remarks for a row
        function calculateRowScores(row) {
            const testInput = row.querySelector('input[name$="[test]"]');
            const examInput = row.querySelector('input[name$="[exam]"]');
            
            if (!testInput || !examInput) return;

            const testScore = parseFloat(testInput.value) || 0;
            const examScore = parseFloat(examInput.value) || 0;
            const totalScore = testScore + examScore;
            
            // Update total score cell
            const totalCell = row.cells[3];
            if (totalCell) {
                totalCell.textContent = totalScore.toFixed(1);
            }
            
            // Calculate grade and remark
            let grade = '';
            let remark = '';
            if (totalScore >= 80) {
                grade = 'A';
                remark = 'Excellent';
            } else if (totalScore >= 70) {
                grade = 'B';
                remark = 'Very Good';
            } else if (totalScore >= 60) {
                grade = 'C';
                remark = 'Good';
            } else if (totalScore >= 50) {
                grade = 'D';
                remark = 'Pass';
            } else {
                grade = 'F';
                remark = 'Fail';
            }
            
            // Update grade input
            const gradeInput = row.querySelector('input[name$="[grade]"]');
            if (gradeInput) {
                gradeInput.value = grade;
            }
            
            // Update remark input
            const remarkInput = row.querySelector('input[name$="[remark]"]');
            if (remarkInput) {
                remarkInput.value = remark;
            }
        }
    </script>
</body>
</html> 