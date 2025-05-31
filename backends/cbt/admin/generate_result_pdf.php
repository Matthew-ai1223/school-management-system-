<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once 'fpdf/fpdf.php';

class ResultPDF extends FPDF {
    function Header() {
        // School Logo and Name
        if (file_exists('../../school_logo.png')) {
            $this->Image('../../school_logo.png', 10, 6, 30);
        }
        $this->SetFont('Arial', 'B', 20);
        $this->Cell(30);
        $this->Cell(140, 10, 'ACE COLLEGE', 0, 1, 'C');
        
        // Add subtitle
        $this->SetFont('Arial', 'I', 12);
        $this->Cell(200, 10, 'Excellence With Integrity', 0, 1, 'C');
        
        // Add horizontal line
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(10);
    }

    function Footer() {
        $this->SetY(-20);
        $this->SetFont('Arial', 'I', 8);
        
        // Add horizontal line
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);
        
        // Add footer text
        $this->Cell(0, 10, 'Generated on: ' . date('d/m/Y H:i:s'), 0, 0, 'L');
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }

    function StudentInfo($student) {
        $this->SetFont('Arial', 'B', 16);
        $this->SetFillColor(220, 220, 220);
        $this->Cell(0, 10, 'STUDENT RESULT REPORT', 0, 1, 'C', true);
        $this->Ln(10);

        // Create a box for student details
        $this->SetFillColor(245, 245, 245);
        $this->RoundedRect(10, $this->GetY(), 190, 40, 3.5, 'DF');
        
        // Student Details
        $this->SetFont('Arial', 'B', 11);
        $this->SetXY(15, $this->GetY() + 5);
        
        // Left column
        $this->Cell(40, 7, 'Student Name:', 0);
        $this->SetFont('Arial', '', 11);
        $this->Cell(100, 7, $student['first_name'] . ' ' . $student['last_name'], 0);
        $this->Ln();
        $this->SetX(15);

        $this->SetFont('Arial', 'B', 11);
        $this->Cell(40, 7, 'Class:', 0);
        $this->SetFont('Arial', '', 11);
        $this->Cell(100, 7, $student['class'], 0);
        $this->Ln();
        $this->SetX(15);

        $this->SetFont('Arial', 'B', 11);
        $this->Cell(40, 7, 'Reg. Number:', 0);
        $this->SetFont('Arial', '', 11);
        $this->Cell(100, 7, $student['registration_number'], 0);
        
        $this->Ln(20);
    }

    function ExamHeader() {
        $this->SetFillColor(51, 122, 183);
        $this->SetTextColor(255);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(60, 8, 'Subject', 1, 0, 'C', true);
        $this->Cell(40, 8, 'Score (%)', 1, 0, 'C', true);
        $this->Cell(30, 8, 'Status', 1, 0, 'C', true);
        $this->Cell(60, 8, 'Date Taken', 1, 1, 'C', true);
        $this->SetTextColor(0);
    }

    function ExamRow($exam) {
        $this->SetFont('Arial', '', 10);
        $this->SetFillColor(245, 245, 245);
        
        // Calculate status and set colors
        $status = ($exam['score'] >= $exam['passing_score']) ? 'Passed' : 'Failed';
        if ($status == 'Passed') {
            $this->SetTextColor(0, 128, 0); // Dark green for pass
        } else {
            $this->SetTextColor(200, 0, 0); // Dark red for fail
        }

        // Add zebra striping
        static $fill = false;
        $this->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);

        $this->Cell(60, 7, $exam['subject'], 1, 0, 'L', $fill);
        $this->Cell(40, 7, number_format($exam['score'], 1) . '%', 1, 0, 'C', $fill);
        $this->Cell(30, 7, $status, 1, 0, 'C', $fill);
        $this->Cell(60, 7, date('d/m/Y', strtotime($exam['attempt_date'])), 1, 1, 'C', $fill);
        
        $this->SetTextColor(0); // Reset text color
        $fill = !$fill; // Toggle fill for zebra effect
    }

    function Summary($stats) {
        $this->Ln(10);
        $this->SetFont('Arial', 'B', 14);
        $this->SetFillColor(220, 220, 220);
        $this->Cell(0, 8, 'Performance Summary', 0, 1, 'L', true);
        $this->Ln(5);

        // Create two columns for summary
        $this->SetFont('Arial', '', 11);
        $col_width = 95;
        
        // Left column
        $x = $this->GetX();
        $y = $this->GetY();
        
        $this->RoundedRect($x, $y, $col_width - 5, 40, 3.5, 'DF');
        $this->SetXY($x + 5, $y + 5);
        
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(60, 7, 'Total Exams Taken:', 0);
        $this->SetFont('Arial', '', 10);
        $this->Cell(30, 7, $stats['total_exams'], 0, 1);
        $this->SetX($x + 5);

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(60, 7, 'Average Score:', 0);
        $this->SetFont('Arial', '', 10);
        $this->Cell(30, 7, number_format($stats['avg_score'], 1) . '%', 0, 1);
        $this->SetX($x + 5);

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(60, 7, 'Highest Score:', 0);
        $this->SetFont('Arial', '', 10);
        $this->Cell(30, 7, number_format($stats['highest_score'], 1) . '%', 0, 1);

        // Right column
        $this->SetXY($x + $col_width, $y);
        $this->RoundedRect($x + $col_width, $y, $col_width - 5, 40, 3.5, 'DF');
        $this->SetXY($x + $col_width + 5, $y + 5);

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(60, 7, 'Lowest Score:', 0);
        $this->SetFont('Arial', '', 10);
        $this->Cell(30, 7, number_format($stats['lowest_score'], 1) . '%', 0, 1);
        $this->SetX($x + $col_width + 5);

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(60, 7, 'Passed Exams:', 0);
        $this->SetFont('Arial', '', 10);
        $this->Cell(30, 7, $stats['passed_exams'], 0, 1);
        $this->SetX($x + $col_width + 5);

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(60, 7, 'Failed Exams:', 0);
        $this->SetFont('Arial', '', 10);
        $this->Cell(30, 7, $stats['failed_exams'], 0, 1);
    }

    function RoundedRect($x, $y, $w, $h, $r, $style = '') {
        $k = $this->k;
        $hp = $this->h;
        if($style=='F')
            $op='f';
        elseif($style=='FD' || $style=='DF')
            $op='B';
        else
            $op='S';
        $MyArc = 4/3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m',($x+$r)*$k,($hp-$y)*$k ));
        $xc = $x+$w-$r ;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l', $xc*$k,($hp-$y)*$k ));

        $this->_Arc($xc + $r*$MyArc, $yc - $r, $xc + $r, $yc - $r*$MyArc, $xc + $r, $yc);
        $xc = $x+$w-$r ;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-$yc)*$k));
        $this->_Arc($xc + $r, $yc + $r*$MyArc, $xc + $r*$MyArc, $yc + $r, $xc, $yc + $r);
        $xc = $x+$r ;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',$xc*$k,($hp-($y+$h))*$k));
        $this->_Arc($xc - $r*$MyArc, $yc + $r, $xc - $r, $yc + $r*$MyArc, $xc - $r, $yc);
        $xc = $x+$r ;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-$yc)*$k ));
        $this->_Arc($xc - $r, $yc - $r*$MyArc, $xc - $r*$MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    function _Arc($x1, $y1, $x2, $y2, $x3, $y3) {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', 
            $x1*$this->k, ($h-$y1)*$this->k,
            $x2*$this->k, ($h-$y2)*$this->k,
            $x3*$this->k, ($h-$y3)*$this->k));
    }
}

// Check if student ID is provided
if (!isset($_GET['student_id'])) {
    die('Student ID is required');
}

$student_id = intval($_GET['student_id']);
$db = Database::getInstance()->getConnection();

// Get student information
$stmt = $db->prepare("SELECT s.*, 
                            COUNT(DISTINCT ea.id) as total_exams,
                            AVG(ea.score) as avg_score,
                            MAX(ea.score) as highest_score,
                            MIN(ea.score) as lowest_score,
                            COUNT(DISTINCT CASE WHEN ea.score >= e.passing_score THEN ea.id END) as passed_exams,
                            COUNT(DISTINCT CASE WHEN ea.score < e.passing_score THEN ea.id END) as failed_exams
                     FROM ace_school_system.students s
                     LEFT JOIN ace_school_system.exam_attempts ea ON s.id = ea.student_id
                     LEFT JOIN ace_school_system.exams e ON ea.exam_id = e.id
                     WHERE s.id = :student_id
                     GROUP BY s.id");
$stmt->execute([':student_id' => $student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die('Student not found');
}

// Get student's exam attempts
$stmt = $db->prepare("SELECT e.subject, ea.score, e.passing_score, ea.start_time as attempt_date
                     FROM ace_school_system.exam_attempts ea
                     JOIN ace_school_system.exams e ON ea.exam_id = e.id
                     WHERE ea.student_id = :student_id
                     AND ea.status = 'completed'
                     ORDER BY ea.start_time DESC");
$stmt->execute([':student_id' => $student_id]);
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create PDF
$pdf = new ResultPDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// Add student information
$pdf->StudentInfo($student);

// Add exam results
if (!empty($exams)) {
    $pdf->ExamHeader();
    foreach ($exams as $exam) {
        $pdf->ExamRow($exam);
    }
    
    // Add summary
    $pdf->Summary($student);
} else {
    $pdf->SetFont('Arial', 'I', 11);
    $pdf->Cell(0, 10, 'No exam attempts found', 0, 1, 'C');
}

// Output PDF
$filename = 'Result_' . str_replace(' ', '_', $student['first_name'] . '_' . $student['last_name']) . '.pdf';
$pdf->Output('D', $filename); 