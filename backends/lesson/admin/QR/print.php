<?php
require_once '../../confg.php';
require_once 'functions.php'; // Include functions

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

// Chunk students into groups of 9 for pagination
$studentPages = array_chunk($allStudents, 9);
$totalPages = count($studentPages);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print QR Codes</title>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 1cm;
            }

            body {
                margin: 0;
                padding: 0;
                background-color: #fff;
            }

            .no-print {
                display: none !important;
            }

            .page-container {
                /* Reset styles from screen view */
                width: auto;
                min-height: initial;
                margin: 0;
                padding: 0;
                box-shadow: none;
                border: none;
                position: static;
                
                /* Print-specific styles */
                page-break-after: always;
            }

            .qr-item {
                page-break-inside: avoid;
            }

            .page-footer {
                position: fixed;
                bottom: 10px;
                right: 15px;
                font-size: 12px;
                color: #888;
            }
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px 0;
            background-color: #f0f2f5;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .page-container {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            background: white;
            padding: 1.5cm;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            position: relative;
            box-sizing: border-box;
        }
        
        .qr-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            grid-template-rows: repeat(3, 1fr);
            gap: 15px;
            height: 100%;
        }
        
        .qr-item {
            display: flex;
            flex-direction: column;
            justify-content: space-around;
            align-items: center;
            text-align: center;
            padding: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background: #ffffff;
            height: 8cm;
        }

        .qr-item.empty {
            border: 1px dashed #e0e0e0;
            background: #fafafa;
        }
        
        .qr-item h3 {
            margin: 10px 0 5px;
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }
        
        .qr-item p {
            margin: 0;
            font-size: 12px;
            color: #777;
        }
        
        .qr-code img {
            max-width: 150px;
            height: auto;
            margin-bottom: 10px;
        }
        
        .print-btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px auto;
            cursor: pointer;
            border: none;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        
        .print-btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="print-btn">Print All QR Codes</button>
    </div>
    
    <?php foreach ($studentPages as $pageNum => $pageStudents): ?>
    <div class="page-container">
        <div class="qr-grid">
            <?php 
            for ($i = 0; $i < 9; $i++):
                if (isset($pageStudents[$i])):
                    $student = $pageStudents[$i];
                    $qrFile = generateStudentQR($student);
            ?>
                    <div class="qr-item">
                        <div class="qr-code">
                            <img src="<?php echo htmlspecialchars($qrFile); ?>" alt="QR Code">
                        </div>
                        <div>
                            <h3><?php echo htmlspecialchars($student['fullname']); ?></h3>
                            <p><?php echo htmlspecialchars(ucfirst($student['department'])); ?></p>
                        </div>
                    </div>
            <?php else: ?>
                    <div class="qr-item empty"></div>
            <?php 
                endif;
            endfor; 
            ?>
        </div>
        <div class="page-footer">
            Page <?php echo $pageNum + 1; ?> of <?php echo $totalPages; ?>
        </div>
    </div>
    <?php endforeach; ?>
</body>
</html>