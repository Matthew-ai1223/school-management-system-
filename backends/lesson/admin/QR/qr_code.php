<?php
require_once('../../confg.php');
require_once('tcpdf/tcpdf.php');

// Define absolute paths
define('QR_BASE_PATH', __DIR__);
define('QR_CACHE_PATH', QR_BASE_PATH . DIRECTORY_SEPARATOR . 'cache');

// Define base URL for the application
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$baseUrl = $protocol . $_SERVER['HTTP_HOST'] . '/ACE%20MODEL%20COLLEGE';

// Create cache directory if it doesn't exist
if (!file_exists(QR_CACHE_PATH)) {
    mkdir(QR_CACHE_PATH, 0777, true);
} else {
    // Ensure directory is writable
    chmod(QR_CACHE_PATH, 0777);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['mode'])) {
            if ($_POST['mode'] === 'single' && isset($_POST['student_id']) && isset($_POST['session'])) {
                $qrCodePath = getStudentQRCode($_POST['student_id'], $_POST['session']);
                $successMessage = "QR Code generated successfully!";
            } elseif ($_POST['mode'] === 'multiple' && isset($_POST['student_ids']) && isset($_POST['session'])) {
                $qrCodePath = generateMultipleQRCodes($_POST['student_ids'], $_POST['session']);
                $successMessage = "Multiple QR Codes generated successfully!";
            }
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

/**
 * Format the student data for QR code
 * @param array $studentData Raw student data
 * @return array Formatted student data
 */
function formatStudentData($studentData) {
    global $baseUrl;
    
    // Format dates
    $registrationDate = new DateTime($studentData['registration_date']);
    $expirationDate = new DateTime($studentData['expiration_date']);
    
    // Format payment amount
    $paymentAmount = number_format((float)$studentData['payment_amount'], 2);
    
    // Convert local file path to URL
    $photoPath = $studentData['photo'];
    $photoPath = str_replace('C:\\xampp\\htdocs\\ACE MODEL COLLEGE\\', '', $photoPath);
    $photoPath = str_replace('\\', '/', $photoPath);
    
    // Clean up the photo path
    $photoPath = trim($photoPath, '/');
    $photoPath = str_replace(' ', '%20', $photoPath);
    
    // Create clean URL
    $photoUrl = $baseUrl . '/' . $photoPath;
    
    // Remove any double slashes in the URL (except for http://)
    $photoUrl = preg_replace('#(?<!:)//+#', '/', $photoUrl);
    
    return [
        'Student Information' => [
            'Name' => $studentData['fullname'],
            'Department' => ucfirst($studentData['department']),
            'Phone' => $studentData['phone']
        ],
        'Payment Details' => [
            'Reference' => $studentData['payment_reference'],
            'Type' => ucfirst($studentData['payment_type']),
            'Amount' => '₦' . $paymentAmount
        ],
        'Dates' => [
            'Registration' => $registrationDate->format('F j, Y'),
            'Expiration' => $expirationDate->format('F j, Y')
        ],
        'Status' => [
            'Active' => $studentData['is_active'] ? 'Yes' : 'No'
        ],
        'Photo' => [
            'URL' => $photoUrl
        ]
    ];
}

/**
 * Generate QR code for student data using TCPDF
 * @param array $studentData Array containing student information
 * @param string $outputPath Path where QR code PDF will be saved
 * @return string Path to the generated QR code PDF
 */
function generateStudentQRCode($studentData, $outputPath) {
    // Format the data
    $formattedData = formatStudentData($studentData);
    
    // Create JSON with proper formatting for QR code
    $qrData = json_encode($formattedData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('ACE COLLEGE');
    $pdf->SetAuthor('ACE  COLLEGE');
    $pdf->SetTitle('Student QR Code - ' . $studentData['fullname']);

    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Set margins
    $pdf->SetMargins(15, 15, 15);

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', 'B', 16);

    // Add title
    $pdf->Cell(0, 10, 'ACE COLLEGE', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Student Information Card', 0, 1, 'C');
    $pdf->Ln(5);

    // Set font for content
    $pdf->SetFont('helvetica', '', 11);

    // Add student information
    foreach ($formattedData as $section => $data) {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 7, $section, 0, 1);
        $pdf->SetFont('helvetica', '', 11);
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    $pdf->Cell(40, 7, $subKey . ':', 0);
                    $pdf->Cell(0, 7, $subValue, 0, 1);
                }
            } else {
                $pdf->Cell(40, 7, $key . ':', 0);
                $pdf->Cell(0, 7, $value, 0, 1);
            }
        }
        $pdf->Ln(3);
    }

    // Generate QR code
    $style = array(
        'border' => false,
        'padding' => 2,
        'fgcolor' => array(0, 0, 0),
        'bgcolor' => false
    );

    // Add QR code to PDF
    $pdf->Ln(5);
    $pdf->write2DBarcode($qrData, 'QRCODE,H', 70, $pdf->GetY(), 70, 70, $style);

    // Add note below QR code
    $pdf->Ln(75);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 10, 'Scan this QR code to verify student information', 0, 1, 'C');

    // Save PDF
    try {
        $pdf->Output($outputPath, 'F');
    } catch (Exception $e) {
        throw new Exception('Failed to generate PDF: ' . $e->getMessage());
    }

    return str_replace(QR_BASE_PATH . DIRECTORY_SEPARATOR, '', $outputPath);
}

/**
 * Get student data and generate QR code
 * @param string $studentId Student ID
 * @param string $session 'morning' or 'afternoon'
 * @return string Path to the generated QR code PDF
 */
function getStudentQRCode($studentId, $session = 'morning') {
    global $conn;

    // Determine which table to query based on session
    $table = ($session === 'morning') ? 'morning_students' : 'afternoon_students';
    
    // Query to get student data
    $query = "SELECT 
        fullname, phone, photo, department, 
        payment_reference, payment_type, payment_amount,
        registration_date, expiration_date, is_active
        FROM $table 
        WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Student not found");
    }
    
    $studentData = $result->fetch_assoc();
    
    // Generate unique filename for QR code PDF
    $qrFileName = 'student_' . $studentId . '_' . time() . '.pdf';
    $outputPath = QR_CACHE_PATH . DIRECTORY_SEPARATOR . $qrFileName;
    
    // Generate QR code
    return generateStudentQRCode($studentData, $outputPath);
}

// Function to get students list
function getStudentsList($session) {
    global $conn;
    $table = ($session === 'morning') ? 'morning_students' : 'afternoon_students';
    $query = "SELECT id, fullname, department FROM $table ORDER BY fullname";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Generate multiple QR codes on a single page
 * @param array $studentIds Array of student IDs
 * @param string $session Session (morning/afternoon)
 * @return string Path to the generated PDF
 */
function generateMultipleQRCodes($studentIds, $session) {
    global $conn;
    
    // Limit to maximum 6 students
    $studentIds = array_slice($studentIds, 0, 6);
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('ACE COLLEGE');
    $pdf->SetAuthor('ACE COLLEGE');
    $pdf->SetTitle('Student QR Codes - Batch Print');

    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Set margins
    $pdf->SetMargins(10, 10, 10);

    // Add a page
    $pdf->AddPage();

    // Set font for title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'ACE  COLLEGE', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Student QR Codes - ' . ucfirst($session) . ' Session', 0, 1, 'C');
    $pdf->Ln(5);

    // Calculate layout
    $pageWidth = $pdf->getPageWidth() - 20; // 10mm margin on each side
    $pageHeight = $pdf->getPageHeight() - 40; // Account for title and margins
    
    $qrSize = 90; // Size of each QR code section
    $cols = 2; // Number of columns
    $rows = 3; // Number of rows
    
    $xSpacing = ($pageWidth - ($cols * $qrSize)) / ($cols + 1);
    $ySpacing = 15; // Increased vertical spacing for additional information

    $currentX = $xSpacing;
    $currentY = $pdf->GetY();
    $col = 0;
    $row = 0;

    // QR code style
    $style = array(
        'border' => false,
        'padding' => 2,
        'fgcolor' => array(0, 0, 0),
        'bgcolor' => false
    );

    // Generate QR codes for each student
    foreach ($studentIds as $index => $studentId) {
        // Get student data
        $table = ($session === 'morning') ? 'morning_students' : 'afternoon_students';
        $query = "SELECT 
            fullname, phone, photo, department, 
            payment_reference, payment_type, payment_amount,
            registration_date, expiration_date, is_active
            FROM $table 
            WHERE id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $studentData = $result->fetch_assoc();
            $formattedData = formatStudentData($studentData);
            $qrData = json_encode($formattedData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            // Calculate position for current QR code
            $x = $currentX + ($col * ($qrSize + $xSpacing));
            $y = $currentY + ($row * ($qrSize + $ySpacing + 15)); // Added extra space for dates

            // Set position
            $pdf->SetXY($x, $y);

            // Add student name
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell($qrSize, 5, $studentData['fullname'], 0, 2, 'C');
            
            // Add QR code
            $pdf->write2DBarcode($qrData, 'QRCODE,H', $x, $y + 5, 60, 60, $style);
            
            // Add department
            $pdf->SetXY($x, $y + 65);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->Cell($qrSize, 5, $studentData['department'], 0, 2, 'C');

            // Format dates
            $registrationDate = new DateTime($studentData['registration_date']);
            $expirationDate = new DateTime($studentData['expiration_date']);

            // Add registration date
            $pdf->SetXY($x, $y + 70);
            $pdf->SetFont('helvetica', '', 7);
            $pdf->Cell($qrSize, 5, 'Reg: ' . $registrationDate->format('d/m/Y'), 0, 2, 'C');

            // Add expiration date
            $pdf->SetXY($x, $y + 75);
            $pdf->SetTextColor(255, 0, 0); // Red color for expiration date
            $pdf->Cell($qrSize, 5, 'Exp: ' . $expirationDate->format('d/m/Y'), 0, 2, 'C');
            $pdf->SetTextColor(0, 0, 0); // Reset to black color

            // Update position
            $col++;
            if ($col >= $cols) {
                $col = 0;
                $row++;
            }
        }
    }

    // Generate unique filename
    $filename = 'batch_qrcodes_' . time() . '.pdf';
    $outputPath = QR_CACHE_PATH . DIRECTORY_SEPARATOR . $filename;

    // Save PDF
    try {
        $pdf->Output($outputPath, 'F');
    } catch (Exception $e) {
        throw new Exception('Failed to generate PDF: ' . $e->getMessage());
    }

    return str_replace(QR_BASE_PATH . DIRECTORY_SEPARATOR, '', $outputPath);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student QR Code Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .qr-container {
            max-width: 300px;
            margin: 20px auto;
            text-align: center;
        }
        .qr-image {
            max-width: 100%;
            height: auto;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2 class="mb-4">Student QR Code Generator</h2>

        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success"><?php echo $successMessage; ?></div>
        <?php endif; ?>

        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <ul class="nav nav-tabs mb-3" id="generatorTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="single-tab" data-bs-toggle="tab" data-bs-target="#single" type="button" role="tab">Single QR Code</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="multiple-tab" data-bs-toggle="tab" data-bs-target="#multiple" type="button" role="tab">Multiple QR Codes</button>
                    </li>
                </ul>

                <div class="tab-content" id="generatorTabsContent">
                    <!-- Single QR Code Generator -->
                    <div class="tab-pane fade show active" id="single" role="tabpanel">
                        <form method="POST" action="" class="mb-4">
                            <input type="hidden" name="mode" value="single">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="session" class="form-label">Select Session</label>
                                    <select class="form-select" id="session" name="session" required>
                                        <option value="morning">Morning Session</option>
                                        <option value="afternoon">Afternoon Session</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="student_id" class="form-label">Select Student</label>
                                    <select class="form-select" id="student_id" name="student_id" required>
                                        <option value="">Select a student...</option>
                                        <?php
                                        $students = getStudentsList('morning');
                                        foreach ($students as $student) {
                                            echo "<option value='{$student['id']}'>{$student['fullname']} - {$student['department']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Generate Single QR Code</button>
                        </form>
                    </div>

                    <!-- Multiple QR Code Generator -->
                    <div class="tab-pane fade" id="multiple" role="tabpanel">
                        <form method="POST" action="" class="mb-4">
                            <input type="hidden" name="mode" value="multiple">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="multi_session" class="form-label">Select Session</label>
                                    <select class="form-select" id="multi_session" name="session" required>
                                        <option value="morning">Morning Session</option>
                                        <option value="afternoon">Afternoon Session</option>
                                    </select>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label for="multi_student_ids" class="form-label">Select Students (Max 6)</label>
                                    <select class="form-select" id="multi_student_ids" name="student_ids[]" multiple required>
                                        <?php
                                        foreach ($students as $student) {
                                            echo "<option value='{$student['id']}'>{$student['fullname']} - {$student['department']}</option>";
                                        }
                                        ?>
                                    </select>
                                    <div class="form-text">Hold Ctrl/Cmd to select multiple students (maximum 6)</div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Generate Multiple QR Codes</button>
                        </form>
                    </div>
                </div>

                <?php if (isset($qrCodePath)): ?>
                    <div class="qr-container">
                        <h4>Generated QR Code</h4>
                        <div class="mt-2">
                            <a href="<?php echo $qrCodePath; ?>" class="btn btn-success" target="_blank">View QR Code PDF</a>
                            <a href="<?php echo $qrCodePath; ?>" download class="btn btn-primary">Download QR Code PDF</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update student list when session changes (for single QR code)
        document.getElementById('session').addEventListener('change', function() {
            updateStudentList(this.value, 'student_id');
        });

        // Update student list when session changes (for multiple QR codes)
        document.getElementById('multi_session').addEventListener('change', function() {
            updateStudentList(this.value, 'multi_student_ids');
        });

        function updateStudentList(session, targetId) {
            const studentSelect = document.getElementById(targetId);
            
            // Clear current options
            studentSelect.innerHTML = targetId === 'student_id' ? 
                '<option value="">Select a student...</option>' : '';
            
            // Fetch students for selected session
            fetch(`get_students.php?session=${session}`)
                .then(response => response.json())
                .then(students => {
                    students.forEach(student => {
                        const option = document.createElement('option');
                        option.value = student.id;
                        option.textContent = `${student.fullname} - ${student.department}`;
                        studentSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    studentSelect.innerHTML = targetId === 'student_id' ? 
                        '<option value="">Error loading students</option>' : 
                        '<option value="">Error loading students</option>';
                });
        }

        // Limit multiple selection to 6 students
        document.getElementById('multi_student_ids').addEventListener('change', function() {
            if (this.selectedOptions.length > 6) {
                alert('You can only select up to 6 students at a time.');
                Array.from(this.selectedOptions)
                    .slice(6)
                    .forEach(option => option.selected = false);
            }
        });
    </script>
</body>
</html>
