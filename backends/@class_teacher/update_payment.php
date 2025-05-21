<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../utils.php';

// Check if user is logged in and has class teacher role
$auth = new Auth();
if (!$auth->isLoggedIn() || $_SESSION['role'] != 'class_teacher') {
    header('Location: login.php');
    exit;
}

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Get class teacher information
$userId = $_SESSION['user_id'];
$teacherName = $_SESSION['name'] ?? '';

// Get class teacher ID
$stmt = $conn->prepare("SELECT id FROM class_teachers WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("Error: You are not assigned as a class teacher.");
}
$classTeacher = $result->fetch_assoc();
$classTeacherId = $classTeacher['id'];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $studentId = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    $paymentType = isset($_POST['payment_type']) ? trim($_POST['payment_type']) : '';
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $paymentMethod = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
    $referenceNumber = isset($_POST['reference_number']) ? trim($_POST['reference_number']) : '';
    $paymentDate = isset($_POST['payment_date']) ? trim($_POST['payment_date']) : date('Y-m-d');
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'completed';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    // Validation
    $errors = [];
    
    if (empty($studentId)) {
        $errors[] = "Student is required";
    }
    
    if (empty($paymentType)) {
        $errors[] = "Payment type is required";
    }
    
    if ($amount <= 0) {
        $errors[] = "Amount must be greater than zero";
    }
    
    if (empty($paymentMethod)) {
        $errors[] = "Payment method is required";
    }
    
    // Process if no errors
    if (empty($errors)) {
        try {
            // Use prepared statement for insertion
            $sql = "INSERT INTO payments (
                    student_id, payment_type, amount, payment_method, 
                    reference_number, payment_date, status, notes, created_by, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
                )";
                
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isdssssis", 
                $studentId, 
                $paymentType,
                $amount, 
                $paymentMethod,
                $referenceNumber, 
                $paymentDate, 
                $status, 
                $notes, 
                $userId
            );
            
            if (!$stmt->execute()) {
                throw new Exception("SQL Error: " . $stmt->error);
            }
            
            $paymentId = $stmt->insert_id;
            
            // Verify the data was saved correctly
            $verifyQuery = "SELECT payment_type, payment_method, status FROM payments WHERE id = $paymentId";
            $verifyResult = $conn->query($verifyQuery);
            
            if ($verifyResult && $verifyResult->num_rows > 0) {
                $savedData = $verifyResult->fetch_assoc();
                
                // If data is missing, update it directly
                if (empty($savedData['payment_type']) || empty($savedData['payment_method']) || empty($savedData['status'])) {
                    // Sanitize for direct SQL update
                    $sanitizedPaymentType = $conn->real_escape_string($paymentType);
                    $sanitizedPaymentMethod = $conn->real_escape_string($paymentMethod);
                    $sanitizedStatus = $conn->real_escape_string($status);
                    $sanitizedNotes = $conn->real_escape_string($notes);
                    
                    $updateSql = "UPDATE payments SET 
                                payment_type = '$sanitizedPaymentType',
                                payment_method = '$sanitizedPaymentMethod',
                                status = '$sanitizedStatus',
                                notes = '$sanitizedNotes'
                                WHERE id = $paymentId";
                    $conn->query($updateSql);
                }
            }
            
            // Record activity
            $activityQuery = "INSERT INTO class_teacher_activities (
                    class_teacher_id, student_id, activity_type, description, activity_date
                ) VALUES (?, ?, 'payment', ?, NOW())";
                
            $description = "Added $paymentType payment of ₦" . number_format($amount, 2) . " via $paymentMethod";
            
            $activityStmt = $conn->prepare($activityQuery);
            $activityStmt->bind_param("iis", $classTeacherId, $studentId, $description);
            $activityStmt->execute();
            
            // Set success message
            $_SESSION['success'] = "Payment has been successfully recorded with ID: " . $paymentId;
            
            // Redirect back
            if (isset($_POST['redirect']) && $_POST['redirect'] === 'student') {
                header("Location: student_details.php?id=" . $studentId);
            } else {
                header("Location: payments.php");
            }
            exit;
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
            
            // Log error for debugging
            error_log("Payment insert error: " . $e->getMessage());
        }
    }
    
    // If we get here, there were errors
    $_SESSION['errors'] = $errors;
}

// Get students for dropdown
$studentsQuery = "SELECT s.* 
                 FROM students s
                 JOIN class_teachers ct ON s.class = ct.class_name
                 WHERE ct.id = ? AND ct.is_active = 1
                 ORDER BY s.first_name, s.last_name";

$stmt = $conn->prepare($studentsQuery);
$stmt->bind_param("i", $classTeacherId);
$stmt->execute();
$studentsResult = $stmt->get_result();
$students = [];

while ($row = $studentsResult->fetch_assoc()) {
    $students[] = $row;
}

// Include header
include '../admin/include/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Record Student Payment</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="students.php">Students</a></li>
                        <li class="breadcrumb-item active">Record Payment</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <?php if (isset($_SESSION['errors'])): ?>
                        <div class="alert alert-danger">
                            <h5><i class="icon fas fa-ban"></i> Error!</h5>
                            <ul>
                                <?php foreach ($_SESSION['errors'] as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php unset($_SESSION['errors']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success">
                            <h5><i class="icon fas fa-check"></i> Success!</h5>
                            <?php echo $_SESSION['success']; ?>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>

                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Payment Information</h3>
                        </div>
                        <form method="POST" action="">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="student_id">Select Student <span class="text-danger">*</span></label>
                                            <select name="student_id" id="student_id" class="form-control select2" required>
                                                <option value="">-- Select Student --</option>
                                                <?php foreach ($students as $student): ?>
                                                    <option value="<?php echo $student['id']; ?>" <?php echo (isset($_GET['student_id']) && $_GET['student_id'] == $student['id']) ? 'selected' : ''; ?>>
                                                        <?php echo $student['first_name'] . ' ' . $student['last_name']; ?> 
                                                        (<?php echo $student['registration_number']; ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="payment_type">Payment Type <span class="text-danger">*</span></label>
                                            <select name="payment_type" id="payment_type" class="form-control" required>
                                                <option value="">-- Select Type --</option>
                                                <option value="tuition_fee">Tuition Fee</option>
                                                <option value="registration_fee">Registration Fee</option>
                                                <option value="development_levy">Development Levy</option>
                                                <option value="book_fee">Book Fee</option>
                                                <option value="uniform_fee">Uniform Fee</option>
                                                <option value="exam_fee">Examination Fee</option>
                                                <option value="transportation_fee">Transportation Fee</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="amount">Amount (₦) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" min="0" name="amount" id="amount" class="form-control" placeholder="Enter amount" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="payment_method">Payment Method <span class="text-danger">*</span></label>
                                            <select name="payment_method" id="payment_method" class="form-control" required>
                                                <option value="">-- Select Method --</option>
                                                <option value="cash">Cash</option>
                                                <option value="bank_transfer">Bank Transfer</option>
                                                <option value="card">Card Payment</option>
                                                <option value="cheque">Cheque</option>
                                                <option value="online">Online Payment</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="reference_number">Reference Number</label>
                                            <input type="text" name="reference_number" id="reference_number" class="form-control" placeholder="Enter reference number">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="payment_date">Payment Date <span class="text-danger">*</span></label>
                                            <input type="date" name="payment_date" id="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="status">Status <span class="text-danger">*</span></label>
                                            <select name="status" id="status" class="form-control" required>
                                                <option value="completed">Completed</option>
                                                <option value="pending">Pending</option>
                                                <option value="failed">Failed</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="notes">Notes</label>
                                            <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="Enter any additional notes"></textarea>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (isset($_GET['student_id']) && isset($_GET['redirect']) && $_GET['redirect'] === 'student'): ?>
                                    <input type="hidden" name="redirect" value="student">
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary">Record Payment</button>
                                <a href="<?php echo isset($_GET['student_id']) ? 'student_details.php?id=' . $_GET['student_id'] : 'payments.php'; ?>" class="btn btn-default">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2();
    
    // Automatically capitalize reference number
    $('#reference_number').on('input', function() {
        $(this).val($(this).val().toUpperCase());
    });
});
</script>

<?php include '../admin/include/footer.php'; ?> 