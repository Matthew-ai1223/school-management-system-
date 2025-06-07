<?php
session_start();
require_once('../../confg.php');
require('fpdf/fpdf.php');

if (!isset($_POST['selected_students']) || empty($_POST['selected_students'])) {
    header('Location: index.php');
    exit;
}

class ReceiptPDF extends FPDF {
    function Header() {
        // Empty header
    }

    function Footer() {
        // Page number
        $this->SetY(-10);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page '.$this->PageNo().'/{nb}', 0, 0, 'C');
    }

    function StudentReceipt($data, $x, $y) {
        // Set position
        $this->SetXY($x, $y);
        
        // Receipt box
        $receipt_width = 95;
        $receipt_height = 125;
        $this->Rect($x, $y, $receipt_width, $receipt_height);
        
        // School header
        $this->SetFont('Arial', 'B', 12);
        $this->SetXY($x, $y + 5);
        $this->Cell($receipt_width, 6, 'ACE TUTORING CENTER', 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->SetX($x);
        $this->Cell($receipt_width, 6, 'Student Receipt', 0, 1, 'C');
        
        // Line under header
        $this->Line($x, $y + 18, $x + $receipt_width, $y + 18);
        
        // Student details
        $this->SetFont('Arial', '', 9);
        $start_y = $y + 20;
        $detail_x = $x + 5;
        
        $details = array(
            'Name' => $data['fullname'],
            'Department' => $data['department'],
            'Session' => ucfirst($data['session']),
            'Payment Ref' => $data['payment_reference'],
            'Payment Type' => $data['payment_type'],
            'Amount' => $data['payment_amount'],
            'Reg. Date' => date('d/m/Y', strtotime($data['registration_date'])),
            'Exp. Date' => date('d/m/Y', strtotime($data['expiration_date'])),
            'Status' => (!$data['is_active'] ? 'Inactive' : 
                        (strtotime($data['expiration_date']) < time() ? 'Expired' : 'Active'))
        );
        
        foreach ($details as $label => $value) {
            $this->SetXY($detail_x, $start_y);
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(25, 5, $label.':', 0);
            $this->SetFont('Arial', '', 9);
            $this->Cell(35, 5, $value, 0);
            $start_y += 6;
        }
        
        // Footer text
        $this->SetFont('Arial', 'I', 7);
        $this->SetXY($x, $y + $receipt_height - 12);
        $this->Cell($receipt_width, 4, 'This receipt serves as proof of payment and registration', 0, 1, 'C');
        $this->SetX($x);
        $this->Cell($receipt_width, 4, 'at ACE TUTORING CENTER.', 0, 1, 'C');
        
        // Generation date
        $this->SetFont('Arial', 'I', 6);
        $this->SetX($x);
        $this->Cell($receipt_width, 4, 'Generated: '.date('d/m/Y'), 0, 1, 'C');
    }
}

// Collect student data
$selected_students = $_POST['selected_students'];
$students_data = array();

foreach ($selected_students as $student_info) {
    list($id, $session) = explode('_', $student_info);
    
    $table = $session . '_students';
    $query = "SELECT * FROM $table WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($student = $result->fetch_assoc()) {
        $student['session'] = $session;
        $student['photo'] = '../../student/uploads/' . $student['photo'];
        $students_data[] = $student;
    }
}

// Create PDF
$pdf = new ReceiptPDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();

// Calculate positions for 4 receipts per page
$positions = array(
    array(10, 10),    // Top left
    array(105, 10),   // Top right
    array(10, 150),   // Bottom left
    array(105, 150)   // Bottom right
);

$student_count = 0;
$total_students = count($students_data);

foreach ($students_data as $index => $student) {
    $position = $student_count % 4;
    
    // Add new page if needed
    if ($position == 0 && $student_count > 0) {
        $pdf->AddPage();
    }
    
    // Add receipt at the correct position
    $pdf->StudentReceipt($student, $positions[$position][0], $positions[$position][1]);
    
    $student_count++;
}

// Output PDF
$pdf->Output('I', 'Student_Receipts.pdf');
