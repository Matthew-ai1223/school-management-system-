<?php
require_once '../config.php';
require_once '../database.php';
require_once '../auth.php';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

$message = '';
// Set a default teacher ID for testing (you should replace this with actual teacher ID)
$teacher_id = 1; // This should be replaced with the actual teacher ID from your system

// Define available schools
$schools = [
    'ACE COLLEGE' => 'ACE COLLEGE',
    'ACE KIDDIS' => 'ACE KIDDIS'
];

// Fetch classes
try {
    $sql = "SELECT DISTINCT class FROM students ORDER BY class";
    $result = $conn->query($sql);
    $classes = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $classes[] = $row['class'];
        }
    }
} catch(Exception $e) {
    $message = "Error fetching classes: " . $e->getMessage();
    $classes = [];
}

// Fetch subjects
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    try {
        $conn->begin_transaction();

        $student_id = $conn->real_escape_string($_POST['student_id']);
        $academic_year = $conn->real_escape_string($_POST['academic_year']);
        $term = $conn->real_escape_string($_POST['term']);
        $class = $conn->real_escape_string($_POST['class']);
        $school_name = $conn->real_escape_string($_POST['school_name']);
        $teacher_comment = $conn->real_escape_string($_POST['teacher_comment']);
        $principal_comment = $conn->real_escape_string($_POST['principal_comment']);

        // Insert report card with school name
        $sql = "INSERT INTO report_cards (student_id, academic_year, term, class, school_name, created_by, teacher_comment, principal_comment) 
                VALUES ('$student_id', '$academic_year', '$term', '$class', '$school_name', '$teacher_id', '$teacher_comment', '$principal_comment')";
        
        if (!$conn->query($sql)) {
            throw new Exception($conn->error);
        }
        
        $report_card_id = $conn->insert_id;

        // Insert subject scores
        foreach ($_POST['subjects'] as $subject_id => $scores) {
            // Skip if both test and exam scores are empty
            if (empty($scores['test']) && empty($scores['exam'])) {
                continue;
            }
            
            $subject_id = $conn->real_escape_string($subject_id);
            $test_score = !empty($scores['test']) ? $conn->real_escape_string($scores['test']) : 0;
            $exam_score = !empty($scores['exam']) ? $conn->real_escape_string($scores['exam']) : 0;
            $total_score = $test_score + $exam_score;
            $comment = $conn->real_escape_string($scores['comment']);
            
            // Calculate grade
            $grade = '';
            $remark = '';
            if ($total_score >= 80) {
                $grade = 'A';
                $remark = 'Excellent';
            } elseif ($total_score >= 70) {
                $grade = 'B';
                $remark = 'Very Good';
            } elseif ($total_score >= 60) {
                $grade = 'C';
                $remark = 'Good';
            } elseif ($total_score >= 50) {
                $grade = 'D';
                $remark = 'Pass';
            } else {
                $grade = 'F';
                $remark = 'Fail';
            }

            $sql = "INSERT INTO report_card_details (report_card_id, subject_id, test_score, exam_score, total_score, grade, remark, teacher_comment) 
                    VALUES ('$report_card_id', '$subject_id', '$test_score', '$exam_score', '$total_score', '$grade', '$remark', '$comment')";
            
            if (!$conn->query($sql)) {
                throw new Exception($conn->error);
            }
        }

        // Calculate total and average scores
        $sql = "SELECT SUM(total_score) as total, COUNT(*) as count FROM report_card_details WHERE report_card_id = '$report_card_id'";
        $result = $conn->query($sql);
        $row = $result->fetch_assoc();
        
        $total_score = $row['total'];
        $average_score = $total_score / $row['count'];

        // Update report card with totals
        $sql = "UPDATE report_cards SET total_score = '$total_score', average_score = '$average_score' WHERE id = '$report_card_id'";
        if (!$conn->query($sql)) {
            throw new Exception($conn->error);
        }

        $conn->commit();
        $message = "Report card generated successfully!";
    } catch(Exception $e) {
        $conn->rollback();
        $message = "Error generating report card: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Report Card</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Generate Report Card</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>

        <a href="manage_subjects.php" style="background-color: #007bff; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;">Add Subject</a>
        <a href="view_report.php" style="background-color:rgb(255, 183, 0); color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;">Back</a>

        <div class="card">
            <div class="card-header">
                <h4>School Information</h4>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="reportForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="school_name" class="form-label">School Name</label>
                            <select class="form-select" id="school_name" name="school_name" required>
                                <option value="">Select School</option>
                                <?php foreach ($schools as $key => $value): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="card-header">
                        <h4>Student Information</h4>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label for="class" class="form-label">Class</label>
                            <select class="form-select" id="class" name="class" required>
                                <option value="">Select Class</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo htmlspecialchars($class); ?>"><?php echo htmlspecialchars($class); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="student_id" class="form-label">Student</label>
                            <select class="form-select" id="student_id" name="student_id" required>
                                <option value="">Select Student</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="academic_year" class="form-label">Academic Year</label>
                            <input type="text" class="form-control" id="academic_year" name="academic_year" required>
                        </div>
                        <div class="col-md-3">
                            <label for="term" class="form-label">Term</label>
                            <select class="form-select" id="term" name="term" required>
                                <option value="First Term">First Term</option>
                                <option value="Second Term">Second Term</option>
                                <option value="Third Term">Third Term</option>
                            </select>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header">
                            <h5>Subject Scores</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
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
                                        <?php foreach ($subjects as $subject): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                            <td>
                                                <input type="number" class="form-control test-score" 
                                                       name="subjects[<?php echo $subject['id']; ?>][test]" 
                                                       min="0" max="30">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control exam-score" 
                                                       name="subjects[<?php echo $subject['id']; ?>][exam]" 
                                                       min="0" max="70">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control total-score" readonly>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control grade" readonly>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control remark" readonly>
                                            </td>
                                            <td>
                                                <textarea class="form-control" 
                                                          name="subjects[<?php echo $subject['id']; ?>][comment]" 
                                                          rows="1"></textarea>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="teacher_comment" class="form-label">Class Teacher's Comment (Optional)</label>
                            <textarea class="form-control" id="teacher_comment" name="teacher_comment" rows="3" placeholder="Enter teacher's comment (optional)"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="principal_comment" class="form-label">Principal's Comment (Optional)</label>
                            <textarea class="form-control" id="principal_comment" name="principal_comment" rows="3" placeholder="Enter principal's comment (optional)"></textarea>
                        </div>
                    </div>

                    <button type="submit" name="generate_report" class="btn btn-primary">Generate Report Card</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fetch students when class is selected
        document.getElementById('class').addEventListener('change', function() {
            const classValue = this.value;
            const studentSelect = document.getElementById('student_id');
            
            if (classValue) {
                fetch(`get_students.php?class=${encodeURIComponent(classValue)}`)
                    .then(response => response.json())
                    .then(students => {
                        studentSelect.innerHTML = '<option value="">Select Student</option>';
                        students.forEach(student => {
                            studentSelect.innerHTML += `<option value="${student.id}">${student.name}</option>`;
                        });
                    });
            }
        });

        // Calculate scores and grades
        document.querySelectorAll('.test-score, .exam-score').forEach(input => {
            input.addEventListener('input', function() {
                const row = this.closest('tr');
                const testScore = parseFloat(row.querySelector('.test-score').value) || 0;
                const examScore = parseFloat(row.querySelector('.exam-score').value) || 0;
                const totalScore = testScore + examScore;
                
                row.querySelector('.total-score').value = totalScore;
                
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
                
                row.querySelector('.grade').value = grade;
                row.querySelector('.remark').value = remark;
            });
        });

        // Add event listener for school selection
        document.getElementById('school_name').addEventListener('change', function() {
            const schoolName = this.value;
            // You can add additional logic here if needed when school is selected
            console.log('Selected school:', schoolName);
        });
    </script>
</body>
</html> 