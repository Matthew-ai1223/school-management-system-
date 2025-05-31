<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../fpdf_temp/fpdf.php';

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
$className = $_SESSION['class_name'] ?? '';

$teacherQuery = "SELECT ct.*, t.first_name, t.last_name
                FROM class_teachers ct
                JOIN teachers t ON ct.teacher_id = t.id
                WHERE ct.user_id = ? AND ct.is_active = 1";

$stmt = $conn->prepare($teacherQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$classTeacher = $result->fetch_assoc();

// Get students
$studentsQuery = "SELECT s.*, 
                 COALESCE(s.admission_number, s.registration_number) as display_number
                 FROM students s
                 WHERE s.class = ?
                 ORDER BY s.first_name, s.last_name";

$stmt = $conn->prepare($studentsQuery);
$stmt->bind_param("s", $className);
$stmt->execute();
$studentsResult = $stmt->get_result();
$students = [];

while ($row = $studentsResult->fetch_assoc()) {
    $students[] = $row;
}

// Custom PDF class with enhanced styling
class PDF extends FPDF {
    private $schoolName = 'ACE COLLEGE';
    private $className = '';
    private $teacherName = '';
    private $pageNumber = 0;
    
    // Define colors
    private $primaryColor = [0, 123, 255]; // Blue
    private $secondaryColor = [108, 117, 125]; // Gray
    private $accentColor = [40, 167, 69]; // Green
    private $headerBgColor = [242, 246, 249]; // Light blue-gray
    private $borderColor = [222, 226, 230]; // Light gray

    function setClassInfo($className, $teacherName) {
        $this->className = $className;
        $this->teacherName = $teacherName;
    }

    // Draw a styled cell with custom colors and borders
    function StyledCell($w, $h, $txt, $border=0, $ln=0, $align='L', $fill=false, $fontSize=10, $fontStyle='') {
        $this->SetFont('Arial', $fontStyle, $fontSize);
        if($fill) {
            $this->SetFillColor($this->headerBgColor[0], $this->headerBgColor[1], $this->headerBgColor[2]);
        }
        $this->Cell($w, $h, $txt, $border, $ln, $align, $fill);
    }

    // Draw a line with custom color
    function ColoredLine($x1, $y1, $x2, $y2, $color) {
        $this->SetDrawColor($color[0], $color[1], $color[2]);
        $this->Line($x1, $y1, $x2, $y2);
    }

    function Header() {
        // Set margins
        $this->SetMargins(15, 15, 15);
        
        // School name with styling
        $this->SetTextColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->SetFont('Arial', 'B', 24);
        $this->Cell(0, 15, $this->schoolName, 0, 1, 'C');
        
        // Decorative line
        $this->ColoredLine(15, $this->GetY(), 195, $this->GetY(), $this->primaryColor);
        $this->Ln(5);

        // Class list title with accent color
        $this->SetTextColor($this->secondaryColor[0], $this->secondaryColor[1], $this->secondaryColor[2]);
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'CLASS LIST - ' . $this->className, 0, 1, 'C');

        // Class teacher info
        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 8, 'Class Teacher: ' . $this->teacherName, 0, 1, 'C');

        // Date with styling
        $this->SetTextColor($this->secondaryColor[0], $this->secondaryColor[1], $this->secondaryColor[2]);
        $this->SetFont('Arial', 'I', 10);
        $this->Cell(0, 8, 'Generated on: ' . date('F d, Y'), 0, 1, 'C');

        // Add some space before table
        $this->Ln(8);

        // Table header with gradient-like effect
        $this->SetFillColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 11);
        
        // Draw table headers with white text on primary color background
        $this->Cell(10, 10, 'S/N', 1, 0, 'C', true);
        $this->Cell(30, 10, 'Reg. Number', 1, 0, 'C', true);
        $this->Cell(60, 10, 'Student Name', 1, 0, 'L', true);
        $this->Cell(25, 10, 'Gender', 1, 0, 'C', true);
        $this->Cell(35, 10, 'Date of Birth', 1, 0, 'C', true);
        $this->Cell(30, 10, 'Contact', 1, 1, 'C', true);

        // Reset text color for content
        $this->SetTextColor(33, 37, 41);
    }

    function Footer() {
        $this->SetY(-25);
        
        // Draw line above footer
        $this->ColoredLine(15, $this->GetY(), 195, $this->GetY(), $this->borderColor);
        $this->Ln(5);
        
        // Footer text with styling
        $this->SetTextColor($this->secondaryColor[0], $this->secondaryColor[1], $this->secondaryColor[2]);
        $this->SetFont('Arial', 'I', 8);
        
        // Left side: School name
        $this->Cell(97, 6, $this->schoolName, 0, 0, 'L');
        
        // Right side: Page numbers
        $this->Cell(97, 6, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'R');
    }
}

// Create PDF document
$pdf = new PDF('P', 'mm', 'A4');
$pdf->setClassInfo(
    $className,
    $classTeacher['first_name'] . ' ' . $classTeacher['last_name']
);

$pdf->AliasNbPages();
$pdf->AddPage();

// Set default font for content
$pdf->SetFont('Arial', '', 10);

// Initialize row counter and alternating colors
$sn = 1;
$alternate = false;

// Add student rows with alternating background
foreach ($students as $student) {
    // Check if we need a new page
    if ($pdf->GetY() > 250) {
        $pdf->AddPage();
    }

    // Set alternating background color
    if($alternate) {
        $pdf->SetFillColor(249, 249, 249);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }

    // Draw row with consistent height and styling
    $pdf->Cell(10, 8, $sn, 1, 0, 'C', true);
    $pdf->Cell(30, 8, $student['display_number'], 1, 0, 'C', true);
    $pdf->Cell(60, 8, ($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''), 1, 0, 'L', true);
    $pdf->Cell(25, 8, ucfirst(strtolower($student['gender'] ?? 'N/A')), 1, 0, 'C', true);
    $pdf->Cell(35, 8, isset($student['date_of_birth']) ? date('d/m/Y', strtotime($student['date_of_birth'])) : 'N/A', 1, 0, 'C', true);
    $pdf->Cell(30, 8, $student['parent_phone'] ?? 'N/A', 1, 1, 'C', true);
    
    $sn++;
    $alternate = !$alternate;
}

// Add summary section with styling
$pdf->Ln(15);
$pdf->SetDrawColor(222, 226, 230);
$pdf->SetFillColor(248, 249, 250);

// Summary header
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(0, 123, 255);
$pdf->Cell(0, 10, 'Class Summary', 0, 1, 'L');
$pdf->SetTextColor(33, 37, 41);

// Count gender distribution
$maleCount = 0;
$femaleCount = 0;
foreach ($students as $student) {
    if (strtolower($student['gender'] ?? '') === 'male') {
        $maleCount++;
    } elseif (strtolower($student['gender'] ?? '') === 'female') {
        $femaleCount++;
    }
}

// Summary content with styled boxes
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(60, 8, 'Total Students:', 1, 0, 'L', true);
$pdf->Cell(30, 8, count($students), 1, 1, 'C', true);

$pdf->Cell(60, 8, 'Male Students:', 1, 0, 'L', true);
$pdf->Cell(30, 8, $maleCount, 1, 1, 'C', true);

$pdf->Cell(60, 8, 'Female Students:', 1, 0, 'L', true);
$pdf->Cell(30, 8, $femaleCount, 1, 1, 'C', true);

// Add signature section with styling
$pdf->Ln(20);
$pdf->SetFont('Arial', '', 10);

// Draw signature lines with subtle styling
$pdf->SetDrawColor(222, 226, 230);
$pdf->Cell(90, 6, '', 'B', 0, 'C');
$pdf->Cell(10, 6, '', 0, 0);
$pdf->Cell(90, 6, '', 'B', 1, 'C');

// Add signature labels with styling
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(108, 117, 125);
$pdf->Cell(90, 6, 'Class Teacher\'s Signature & Date', 0, 0, 'C');
$pdf->Cell(10, 6, '', 0, 0);
$pdf->Cell(90, 6, 'Principal\'s Signature & Date', 0, 1, 'C');

// Output PDF
$pdf->Output('Class_List_' . $className . '.pdf', 'I'); 