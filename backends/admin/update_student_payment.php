<?php
require_once '../config.php';
require_once '../database.php';
require_once '../auth.php';
require_once '../utils.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has admin role
$auth = new Auth();
if (!$auth->isLoggedIn() || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit;
}

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Get admin information
$userId = $_SESSION['user_id'];
$adminName = $_SESSION['name'] ?? '';

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
        // Create payment record
        $query = "INSERT INTO payments (
                student_id, payment_type, amount, payment_method, 
                reference_number, payment_date, status, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            "isdsssis", 
            $studentId, $paymentType, $amount, $paymentMethod,
            $referenceNumber, $paymentDate, $status, $userId
        );
        
        if ($stmt->execute()) {
            // Set success message
            $_SESSION['success_message'] = "Payment of ₦" . number_format($amount, 2) . " has been successfully recorded for the student";
            
            // Redirect back
            if (isset($_POST['redirect']) && $_POST['redirect'] === 'student') {
                header("Location: student_details.php?id=" . $studentId);
            } else {
                header("Location: payments.php");
            }
            exit;
        } else {
            $errors[] = "Error recording payment: " . $stmt->error;
        }
    }
    
    // If we get here, there were errors
    $_SESSION['error_message'] = implode('<br>', $errors);
}

// Get specific student if ID is provided
$studentName = '';
$specificStudent = null;
if (isset($_GET['student_id']) && intval($_GET['student_id']) > 0) {
    $studentId = intval($_GET['student_id']);
    $stmt = $conn->prepare("SELECT id, first_name, last_name, registration_number, class FROM students WHERE id = ?");
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $specificStudent = $result->fetch_assoc();
        $studentName = $specificStudent['first_name'] . ' ' . $specificStudent['last_name'];
    }
}

// Include header
include 'include/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0"><?php echo $specificStudent ? "Record Payment for $studentName" : "Record Student Payment"; ?></h1>
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

    <section class="content">
        <div class="container-fluid">
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h5><i class="icon fas fa-ban"></i> Error!</h5>
                    <?php 
                        echo $_SESSION['error_message']; 
                        unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h5><i class="icon fas fa-check"></i> Success!</h5>
                    <?php 
                        echo $_SESSION['success_message'];
                        unset($_SESSION['success_message']);
                    ?>
                </div>
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
                                    <label for="student_id">Student <span class="text-danger">*</span></label>
                                    <?php if ($specificStudent): ?>
                                        <input type="hidden" name="student_id" value="<?php echo $specificStudent['id']; ?>">
                                        <input type="text" class="form-control" value="<?php echo $studentName; ?> (<?php echo $specificStudent['registration_number']; ?>)" readonly>
                                    <?php else: ?>
                                        <select name="student_id" id="student_id" class="form-control select2bs4" required>
                                            <option value="">-- Select Student --</option>
                                            <?php
                                            $query = "SELECT id, first_name, last_name, registration_number, class FROM students ORDER BY class, first_name, last_name";
                                            $result = $conn->query($query);
                                            
                                            while ($student = $result->fetch_assoc()):
                                            ?>
                                                <option value="<?php echo $student['id']; ?>">
                                                    <?php echo $student['first_name'] . ' ' . $student['last_name']; ?> 
                                                    (<?php echo $student['registration_number']; ?>) - 
                                                    Class: <?php echo $student['class']; ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="payment_type">Payment Type <span class="text-danger">*</span></label>
                                    <select name="payment_type" id="payment_type" class="form-control select2bs4" required>
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
                                    <select name="payment_method" id="payment_method" class="form-control select2bs4" required>
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
    </section>
</div>

<script>
$(function () {
    // Initialize Select2 Elements
    $('.select2bs4').select2({
        theme: 'bootstrap4'
    });
    
    // Capitalize reference number
    $('#reference_number').on('input', function() {
        $(this).val($(this).val().toUpperCase());
    });
});
</script>

<?php include 'include/footer.php'; ?> 