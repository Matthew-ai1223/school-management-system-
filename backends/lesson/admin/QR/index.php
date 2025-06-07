<?php
require_once '../../confg.php';
require_once 'vendor/autoload.php';
require_once 'functions.php';

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use BaconQrCode\Common\ErrorCorrectionLevel;

// Function to format date to a more readable format
function formatDate($dateString) {
    $date = new DateTime($dateString);
    return $date->format('F j, Y g:i A'); // Example: May 28, 2025 12:17 AM
}

// Function to format amount with currency
function formatAmount($amount) {
    return 'NGN ' . number_format((float)$amount, 2); // Using NGN instead of ₦ symbol
}

// Function to determine account status
function getAccountStatus($expirationDate) {
    $today = new DateTime();
    $expiration = new DateTime($expirationDate);
    
    if ($today > $expiration) {
        return "Expired";
    } else {
        $diff = $today->diff($expiration);
        if ($diff->days <= 7) {
            return "Expiring Soon";
        }
        return "Active";
    }
}

// Function to generate QR code for a student
function generateStudentQR($studentData) {
    // Create a unique filename for the QR code
    $filename = 'qrcodes/' . md5($studentData['fullname'] . time()) . '.svg';
    
    // Create the directory if it doesn't exist
    if (!file_exists('qrcodes')) {
        mkdir('qrcodes', 0777, true);
    }
    
    // Format the dates
    $registrationDate = formatDate($studentData['registration_date']);
    $expirationDate = formatDate($studentData['expiration_date']);
    
    // Get account status
    $accountStatus = getAccountStatus($studentData['expiration_date']);
    
    // Format the payment amount
    $formattedAmount = formatAmount($studentData['payment_amount']);
    
    // Prepare the data to be encoded in QR code with a more readable format
    $qrData = "STUDENT INFORMATION\n" .
              "==================\n" .
              "Name: " . $studentData['fullname'] . "\n" .
              "Department: " . ucfirst($studentData['department']) . "\n" .
              "Status: " . $accountStatus . "\n" .
              "\nPAYMENT DETAILS\n" .
              "==================\n" .
              "Reference: " . $studentData['payment_reference'] . "\n" .
              "Type: " . ucfirst($studentData['payment_type']) . " Payment\n" .
              "Amount: " . $formattedAmount . "\n" .
              "\nDATES\n" .
              "==================\n" .
              "Registered: " . $registrationDate . "\n" .
              "Expires: " . $expirationDate;
    
    // Create QR code renderer with larger size for better readability
    $renderer = new ImageRenderer(
        new RendererStyle(400, 4),  // Size 400px, margin 4 modules
        new SvgImageBackEnd()
    );
    
    // Create writer instance with UTF-8 encoding and high error correction
    $writer = new Writer($renderer);
    
    try {
        // Generate and save QR code with explicit UTF-8 encoding
        $qrCode = $writer->writeString($qrData, 'UTF-8', ErrorCorrectionLevel::Q());
        file_put_contents($filename, $qrCode);
    } catch (Exception $e) {
        // If UTF-8 fails, try with basic ASCII encoding
        $qrData = iconv('UTF-8', 'ASCII//TRANSLIT', $qrData);
        $qrCode = $writer->writeString($qrData);
        file_put_contents($filename, $qrCode);
    }
    
    return $filename;
}

// Function to get printable QR code page
function getPrintableQRCode($filename, $student) {
    if (file_exists($filename)) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Print QR Code - <?php echo htmlspecialchars($student['fullname']); ?></title>
            <style>
                @media print {
                    @page {
                        size: A4;
                        margin: 1cm;
                    }
                    .no-print {
                        display: none;
                    }
                }
                body {
                    font-family: Arial, sans-serif;
                    margin: 0;
                    padding: 20px;
                    text-align: center;
                }
                .print-container {
                    max-width: 800px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .qr-card {
                    border: 1px solid #ddd;
                    padding: 20px;
                    margin: 20px 0;
                    background: white;
                }
                .student-photo {
                    width: 100px;
                    height: 100px;
                    border-radius: 50%;
                    object-fit: cover;
                    margin: 10px 0;
                }
                .qr-code {
                    margin: 20px 0;
                }
                .qr-code img {
                    max-width: 300px;
                    height: auto;
                }
                .student-info {
                    margin: 20px 0;
                }
                .student-info h2 {
                    margin: 10px 0;
                    color: #333;
                }
                .student-info p {
                    margin: 5px 0;
                    color: #666;
                }
                .print-btn {
                    background: #4CAF50;
                    color: white;
                    padding: 10px 20px;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 16px;
                }
                .status {
                    display: inline-block;
                    padding: 5px 15px;
                    border-radius: 4px;
                    font-weight: bold;
                    margin: 10px 0;
                }
                .active { background: #4CAF50; color: white; }
                .expired { background: #f44336; color: white; }
                .expiring-soon { background: #ff9800; color: white; }
            </style>
        </head>
        <body>
            <div class="no-print">
                <button onclick="window.print()" class="print-btn">Print QR Code</button>
            </div>
            <div class="print-container">
                <div class="qr-card">
                    <img src="<?php echo htmlspecialchars($student['photo']); ?>" alt="Student Photo" class="student-photo">
                    <div class="student-info">
                        <h2><?php echo htmlspecialchars($student['fullname']); ?></h2>
                        <p><strong>Department:</strong> <?php echo htmlspecialchars(ucfirst($student['department'])); ?></p>
                        <p><strong>Payment Reference:</strong> <?php echo htmlspecialchars($student['payment_reference']); ?></p>
                        <p><strong>Payment Type:</strong> <?php echo htmlspecialchars(ucfirst($student['payment_type'])); ?></p>
                        <p><strong>Amount:</strong> NGN <?php echo htmlspecialchars(number_format($student['payment_amount'], 2)); ?></p>
                        <p><strong>Registration:</strong> <?php echo htmlspecialchars(date('F j, Y', strtotime($student['registration_date']))); ?></p>
                        <p><strong>Expiration:</strong> <?php echo htmlspecialchars(date('F j, Y', strtotime($student['expiration_date']))); ?></p>
                        <?php 
                        $status = getAccountStatus($student['expiration_date']);
                        $statusClass = strtolower(str_replace(' ', '-', $status));
                        ?>
                        <div class="status <?php echo $statusClass; ?>">
                            <?php echo $status; ?>
                        </div>
                    </div>
                    <div class="qr-code">
                        <img src="<?php echo $filename; ?>" alt="QR Code">
                    </div>
                </div>
            </div>
            <script>
                // Automatically open print dialog when page loads
                window.onload = function() {
                    // Small delay to ensure everything is loaded
                    setTimeout(function() {
                        window.print();
                    }, 500);
                };
            </script>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    return false;
}

// Handle print request
if (isset($_GET['print']) && isset($_GET['file'])) {
    $filename = 'qrcodes/' . basename($_GET['file']);
    $studentId = isset($_GET['student_id']) ? $_GET['student_id'] : null;
    
    if ($studentId) {
        // Get student data
        $table = isset($_GET['table']) ? $_GET['table'] : 'morning_students';
        $query = "SELECT * FROM $table WHERE id = ? AND is_active = 1";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $studentId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $student = mysqli_fetch_assoc($result);
        
        if ($student) {
            echo getPrintableQRCode($filename, $student);
            exit;
        }
    }
}

// Function to fetch and display students
function displayStudents($conn, $table) {
    $query = "SELECT * FROM $table WHERE is_active = 1";
    $result = mysqli_query($conn, $query);
    
    if ($result) {
        while ($student = mysqli_fetch_assoc($result)) {
            $qrFile = generateStudentQR($student);
            $qrFileName = basename($qrFile);
            $accountStatus = getAccountStatus($student['expiration_date']);
            $statusClass = strtolower(str_replace(' ', '-', $accountStatus));
            ?>
            <div class="student-card">
                <div class="student-info">
                    <img src="<?php echo $student['photo']; ?>" alt="Student Photo" class="student-photo">
                    <h3><?php echo htmlspecialchars($student['fullname']); ?></h3>
                    <p>Department: <?php echo htmlspecialchars($student['department']); ?></p>
                    <p>Payment Reference: <?php echo htmlspecialchars($student['payment_reference']); ?></p>
                    <p>Payment Type: <?php echo htmlspecialchars($student['payment_type']); ?></p>
                    <p>Amount: <?php echo htmlspecialchars($student['payment_amount']); ?></p>
                    <p>Registration: <?php echo htmlspecialchars($student['registration_date']); ?></p>
                    <p>Expiration: <?php echo htmlspecialchars($student['expiration_date']); ?></p>
                    <p class="status <?php echo $statusClass; ?>">Status: <?php echo $accountStatus; ?></p>
                </div>
                <div class="qr-code">
                    <img src="<?php echo $qrFile; ?>" alt="QR Code" style="width:200px; height:200px;">
                    <div class="qr-actions">
                        <a href="?print=1&file=<?php echo urlencode($qrFileName); ?>&student_id=<?php echo $student['id']; ?>&table=<?php echo urlencode($table); ?>" 
                           class="print-btn" title="Print QR Code" target="_blank">
                            Print QR Code
                        </a>
                    </div>
                </div>
            </div>
            <?php
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student QR Code Generator</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .student-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .student-info {
            flex: 1;
        }
        .student-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
        }
        .qr-code {
            margin-left: 20px;
            text-align: center;
        }
        .qr-code iframe {
            border: 1px solid #ccc;
            background: white;
            margin-bottom: 10px;
        }
        .qr-actions {
            margin-top: 10px;
        }
        .download-btn, .print-btn {
            display: inline-block;
            padding: 8px 16px;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
            margin: 0 5px;
        }
        .download-btn {
            background-color: #4CAF50;
        }
        .download-btn:hover {
            background-color: #45a049;
        }
        .print-btn {
            background-color: #2196F3;
        }
        .print-btn:hover {
            background-color: #1976D2;
        }
        h1 {
            color: #333;
            text-align: center;
        }
        h2 {
            color: #666;
            margin-top: 30px;
        }
        .bulk-actions {
            text-align: center;
            margin: 20px 0;
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        .bulk-actions a {
            padding: 10px 20px;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        .back-btn {
            background-color: #2196F3;
        }
        .back-btn:hover {
            background-color: #1976D2;
        }
        .bulk-print-btn {
            background-color: #4CAF50;
        }
        .bulk-print-btn:hover {
            background-color: #45a049;
        }
        .status {
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 10px;
        }
        
        .active {
            background-color: #4CAF50;
            color: white;
        }
        
        .expired {
            background-color: #f44336;
            color: white;
        }
        
        .expiring-soon {
            background-color: #ff9800;
            color: white;
        }
    </style>
</head>
<body>
    <h1>Student QR Code Generator</h1>
    
    <div class="bulk-actions">
        <a href="../dashboard.php" class="back-btn">Back to Dashboard</a>
        <a href="bulk_print.php" class="bulk-print-btn">Bulk Print QR Codes</a>
    </div>
    
    <h2>Morning Students</h2>
    <?php displayStudents($conn, 'morning_students'); ?>
    
    <h2>Afternoon Students</h2>
    <?php displayStudents($conn, 'afternoon_students'); ?>
</body>
</html>
