<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../utils.php';

// Check if user is logged in and has class teacher role
$auth = new Auth();
if (!$auth->isLoggedIn() || $_SESSION['role'] != 'class_teacher') {
    die(json_encode(['success' => false, 'message' => 'Not authorized']));
}

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Get class teacher information
$userId = $_SESSION['user_id'];
$classTeacherId = $_SESSION['class_teacher_id'] ?? 0;

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request method']));
}

// Get POST data
$paymentId = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
$newStatus = isset($_POST['status']) ? trim($_POST['status']) : '';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

// Validate inputs
if ($paymentId <= 0 || empty($newStatus)) {
    die(json_encode(['success' => false, 'message' => 'Invalid payment ID or status']));
}

// Verify valid status
$validStatuses = ['pending', 'completed', 'failed'];
if (!in_array($newStatus, $validStatuses)) {
    die(json_encode(['success' => false, 'message' => 'Invalid status value']));
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Get payment details and verify ownership
    $query = "SELECT p.*, s.class 
             FROM payments p 
             JOIN students s ON p.student_id = s.id 
             WHERE p.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $paymentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Payment not found");
    }
    
    $payment = $result->fetch_assoc();
    
    // Verify class teacher has access to this student's class
    $teacherQuery = "SELECT class_name FROM class_teachers 
                    WHERE user_id = ? AND is_active = 1";
    $stmt = $conn->prepare($teacherQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $teacherResult = $stmt->get_result();
    
    if ($teacherResult->num_rows === 0) {
        throw new Exception("Class teacher not found");
    }
    
    $teacherClass = $teacherResult->fetch_assoc()['class_name'];
    
    if ($payment['class'] !== $teacherClass) {
        throw new Exception("You don't have permission to update this payment");
    }
    
    // Update payment status
    $updateQuery = "UPDATE payments SET 
                   status = ?,
                   notes = CONCAT(IFNULL(notes, ''), '\n\nStatus updated to ', ?, ' on ', NOW(), '. Notes: ', ?),
                   updated_at = NOW()
                   WHERE id = ?";
                   
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("sssi", $newStatus, $newStatus, $notes, $paymentId);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update payment status");
    }
    
    // Record activity
    $activityQuery = "INSERT INTO class_teacher_activities (
            class_teacher_id, student_id, activity_type, description, activity_date
        ) VALUES (?, ?, 'payment_update', ?, NOW())";
        
    $description = "Updated payment #$paymentId status to $newStatus";
    if (!empty($notes)) {
        $description .= ". Notes: $notes";
    }
    
    $stmt = $conn->prepare($activityQuery);
    $stmt->bind_param("iis", $classTeacherId, $payment['student_id'], $description);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Payment status updated successfully',
        'new_status' => $newStatus
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 