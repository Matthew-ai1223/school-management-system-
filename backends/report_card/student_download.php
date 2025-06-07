<?php
require_once '../config.php';
require_once '../database.php';
require_once '../auth.php';

$message = '';
$reports = [];
$student = null;

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $registration_number = $conn->real_escape_string($_POST['registration_number']);
    
    try {
        // First, get student information
        $sql = "SELECT id, first_name, last_name, registration_number 
                FROM students 
                WHERE registration_number = '$registration_number'";
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $student = $result->fetch_assoc();
            
            // Then get all report cards for this student
            $sql = "SELECT rc.*, 
                    CONCAT(t.first_name, ' ', t.last_name) as teacher_name
                    FROM report_cards rc
                    LEFT JOIN teachers t ON rc.created_by = t.id
                    WHERE rc.student_id = '{$student['id']}'
                    AND rc.allow_download = 1
                    ORDER BY rc.academic_year DESC, 
                    CASE rc.term 
                        WHEN 'First Term' THEN 1 
                        WHEN 'Second Term' THEN 2 
                        WHEN 'Third Term' THEN 3 
                    END DESC";
            
            $result = $conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $reports[] = $row;
                }
            }
        } else {
            $message = "No student found with registration number: " . htmlspecialchars($registration_number);
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
    <title>Download Report Card</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .report-card {
            transition: transform 0.2s;
        }
        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Download Report Card</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-info"><?php echo $message; ?></div>
                        <?php endif; ?>

                        <form method="POST" class="mb-4">
                            <div class="input-group">
                                <input type="text" name="registration_number" class="form-control" 
                                       placeholder="Enter your registration number" required
                                       value="<?php echo isset($_POST['registration_number']) ? htmlspecialchars($_POST['registration_number']) : ''; ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </form>

                        <?php if ($student): ?>
                            <div class="alert alert-success mb-4">
                                <h4 class="alert-heading">Student Information</h4>
                                <p class="mb-0">
                                    <strong>Name:</strong> <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?><br>
                                    <strong>Registration Number:</strong> <?php echo htmlspecialchars($student['registration_number']); ?>
                                </p>
                            </div>

                            <?php if (empty($reports)): ?>
                                <div class="alert alert-warning">
                                    No report cards found for this student.
                                </div>
                            <?php else: ?>
                                <h4 class="mb-3">Available Report Cards</h4>
                                <div class="row">
                                    <?php foreach ($reports as $report): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="card report-card">
                                                <div class="card-body">
                                                    <h5 class="card-title"><?php echo htmlspecialchars($report['term']); ?></h5>
                                                    <p class="card-text">
                                                        <strong>Academic Year:</strong> <?php echo htmlspecialchars($report['academic_year']); ?><br>
                                                        <strong>Class:</strong> <?php echo htmlspecialchars($report['class']); ?><br>
                                                        <strong>Generated:</strong> <?php echo date('M d, Y', strtotime($report['created_at'])); ?>
                                                    </p>
                                                    <div class="d-flex justify-content-between">
                                                        <a href="download_pdf.php?id=<?php echo $report['id']; ?>" 
                                                           class="btn btn-primary btn-sm">
                                                            <i class="fas fa-download"></i> Download PDF
                                                        </a>
                                                        <!-- <a href="view_report.php?id=<?php echo $report['id']; ?>" 
                                                           class="btn btn-info btn-sm" target="_blank">
                                                            <i class="fas fa-eye"></i> View
                                                        </a> -->
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
