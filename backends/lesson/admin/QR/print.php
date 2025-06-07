<?php
require_once '../../confg.php';

// Function to fetch students
function getStudents($conn, $table) {
    $query = "SELECT * FROM $table WHERE is_active = 1";
    $result = mysqli_query($conn, $query);
    $students = [];
    
    if ($result) {
        while ($student = mysqli_fetch_assoc($result)) {
            $students[] = $student;
        }
    }
    
    return $students;
}

// Get all students
$morningStudents = getStudents($conn, 'morning_students');
$afternoonStudents = getStudents($conn, 'afternoon_students');
$allStudents = array_merge($morningStudents, $afternoonStudents);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print QR Codes</title>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 1cm;
            }
            body {
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none;
            }
            .qr-grid {
                page-break-inside: avoid;
            }
        }
        
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        
        .print-container {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .qr-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .qr-item {
            text-align: center;
            padding: 15px;
            border: 1px solid #ddd;
            background: white;
        }
        
        .qr-item h3 {
            margin: 10px 0;
            font-size: 14px;
        }
        
        .qr-item p {
            margin: 5px 0;
            font-size: 12px;
        }
        
        .qrcode {
            display: inline-block;
            padding: 10px;
            background: white;
        }
        
        .print-btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 20px 0;
            cursor: pointer;
            border: none;
            font-size: 16px;
        }
        
        .print-btn:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: center;">
        <button onclick="window.print()" class="print-btn">Print QR Codes</button>
    </div>
    
    <div class="print-container">
        <div class="qr-grid">
            <?php foreach ($allStudents as $student): 
                $qrData = json_encode([
                    'fullname' => $student['fullname'],
                    'department' => $student['department'],
                    'payment_reference' => $student['payment_reference'],
                    'payment_type' => $student['payment_type'],
                    'payment_amount' => $student['payment_amount'],
                    'registration_date' => $student['registration_date'],
                    'expiration_date' => $student['expiration_date']
                ]);
            ?>
                <div class="qr-item">
                    <div id="qrcode-<?php echo md5($student['fullname']); ?>" class="qrcode"></div>
                    <h3><?php echo htmlspecialchars($student['fullname']); ?></h3>
                    <p><?php echo htmlspecialchars($student['department']); ?></p>
                </div>
                <script>
                    new QRCode(document.getElementById("qrcode-<?php echo md5($student['fullname']); ?>"), {
                        text: <?php echo json_encode($qrData); ?>,
                        width: 150,
                        height: 150,
                        colorDark: "#000000",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.H
                    });
                </script>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>