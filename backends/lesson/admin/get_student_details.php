<?php
include '../confg.php';

if (isset($_GET['id']) && isset($_GET['session'])) {
    $id = intval($_GET['id']);
    $session = $_GET['session'];
    $table = $session . '_students';
    
    // Prepare the SQL statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($student = $result->fetch_assoc()) {
        $status = getPaymentStatus($student['expiration_date']);
        
        // Format the output HTML
        echo '<div class="container-fluid">';
        
        // Student Photo and Basic Info
        echo '<div class="row mb-4">
                <div class="col-md-3 text-center">
                    <img src="../student/uploads/' . basename($student['photo']) . '" 
                         class="img-fluid rounded-circle mb-3" 
                         style="width: 150px; height: 150px; object-fit: cover;"
                         onerror="this.src=\'https://via.placeholder.com/150\'">
                    <div class="badge bg-' . $status['class'] . ' fs-6 mb-2">' . $status['status'] . '</div>
                </div>
                <div class="col-md-9">
                    <h4>' . htmlspecialchars($student['fullname']) . '</h4>
                    <p class="text-muted mb-2">Registration Date: ' . date('F j, Y', strtotime($student['registration_date'])) . '</p>
                    <p class="text-muted">Expiration Date: ' . date('F j, Y', strtotime($student['expiration_date'])) . '</p>
                </div>
              </div>';

        // Contact Information
        echo '<div class="row mb-4">
                <div class="col-12">
                    <h5 class="border-bottom pb-2">Contact Information</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Email:</strong> ' . htmlspecialchars($student['email']) . '</p>
                            <p><strong>Phone:</strong> ' . htmlspecialchars($student['phone']) . '</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Address:</strong> ' . htmlspecialchars($student['address']) . '</p>
                        </div>
                    </div>
                </div>
              </div>';

        // Session Specific Information
        echo '<div class="row mb-4">
                <div class="col-12">
                    <h5 class="border-bottom pb-2">Academic Information</h5>
                    <div class="row">';
        
        if ($session === 'morning') {
            echo '<div class="col-md-6">
                    <p><strong>Department:</strong> ' . htmlspecialchars($student['department']) . '</p>
                    <p><strong>Level:</strong> ' . htmlspecialchars($student['level']) . '</p>
                  </div>';
        } else {
            echo '<div class="col-md-6">
                    <p><strong>School:</strong> ' . htmlspecialchars($student['school']) . '</p>
                    <p><strong>Class:</strong> ' . htmlspecialchars($student['class']) . '</p>
                  </div>';
        }
        
        echo '</div></div></div>';

        // Payment Information
        echo '<div class="row">
                <div class="col-12">
                    <h5 class="border-bottom pb-2">Payment Information</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Payment Type:</strong> ' . ucfirst(htmlspecialchars($student['payment_type'])) . '</p>
                            <p><strong>Amount:</strong> ₦' . number_format($student['payment_amount'], 2) . '</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Reference:</strong> ' . htmlspecialchars($student['payment_reference']) . '</p>';
        
        // Check if receipt exists
        $receipt_path = "../student/uploads/receipt_" . $student['payment_reference'] . ".pdf";
        if (file_exists($receipt_path)) {
            echo '<p><a href="' . $receipt_path . '" class="btn btn-primary btn-sm" target="_blank">
                    <i class="fas fa-download me-2"></i>Download Receipt
                  </a></p>';
        }
        
        echo '</div></div></div></div>';
        
        echo '</div>'; // Close container-fluid
    } else {
        echo '<div class="alert alert-danger">Student not found.</div>';
    }
    
    $stmt->close();
} else {
    echo '<div class="alert alert-danger">Invalid request parameters.</div>';
}

// Helper function to get payment status
function getPaymentStatus($expirationDate) {
    $today = new DateTime();
    $expiration = new DateTime($expirationDate);
    $daysRemaining = $today->diff($expiration)->days;
    
    if ($expiration < $today) {
        return ['status' => 'Expired', 'class' => 'danger'];
    } elseif ($daysRemaining <= 7) {
        return ['status' => 'Expiring Soon', 'class' => 'warning'];
    } else {
        return ['status' => 'Active', 'class' => 'success'];
    }
}
?> 