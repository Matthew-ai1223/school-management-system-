<?php
require_once '../../confg.php';
require_once '../../student/generate_receipt.php';
require_once '../QR/functions.php';

class CashReceipt extends Receipt {
    public function generateCashReceipt($payment_data) {
        // Set up the PDF
        $this->AddPage();
        $this->SetFont('Arial', 'B', 16);
        
        // Header
        $this->Cell(0, 10, 'ACE MODEL COLLEGE', 0, 1, 'C');
        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 8, 'Cash Payment Receipt', 0, 1, 'C');
        $this->Cell(0, 8, 'Student Registration', 0, 1, 'C');
        $this->Ln(5);
        
        // Generate QR code with student data
        $qrData = [
            'fullname' => $payment_data['fullname'],
            'department' => $payment_data['department'],
            'reg_number' => $payment_data['reference_number'],
            'payment_reference' => $payment_data['reference_number'],
            'session_type' => $payment_data['session_type'],
            'payment_type' => $payment_data['payment_type'],
            'payment_amount' => $payment_data['payment_amount'],
            'registration_date' => $payment_data['registration_date'],
            'expiration_date' => $payment_data['expiration_date']
        ];
        
        $qrPath = generateStudentQR($qrData);
        
        // Student Information
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 8, 'STUDENT INFORMATION', 0, 1, 'L');
        $this->SetFont('Arial', '', 10);
        
        $this->Cell(40, 6, 'Full Name:', 0, 0);
        $this->Cell(0, 6, $payment_data['fullname'], 0, 1);
        
        $this->Cell(40, 6, 'Department:', 0, 0);
        $this->Cell(0, 6, ucfirst($payment_data['department']), 0, 1);
        
        $this->Cell(40, 6, 'Session:', 0, 0);
        $this->Cell(0, 6, ucfirst($payment_data['session_type']), 0, 1);
        
        if ($payment_data['session_type'] === 'afternoon') {
            $this->Cell(40, 6, 'Class:', 0, 0);
            $this->Cell(0, 6, $payment_data['class'], 0, 1);
            
            $this->Cell(40, 6, 'School:', 0, 0);
            $this->Cell(0, 6, $payment_data['school'], 0, 1);
        }
        
        $this->Ln(5);
        
        // Payment Information
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 8, 'PAYMENT INFORMATION', 0, 1, 'L');
        $this->SetFont('Arial', '', 10);
        
        $this->Cell(40, 6, 'Reference Number:', 0, 0);
        $this->Cell(0, 6, $payment_data['reference_number'], 0, 1);
        
        $this->Cell(40, 6, 'Payment Type:', 0, 0);
        $this->Cell(0, 6, ucfirst($payment_data['payment_type']) . ' Payment', 0, 1);
        
        $this->Cell(40, 6, 'Amount:', 0, 0);
        $this->Cell(0, 6, '₦' . number_format($payment_data['payment_amount'], 2), 0, 1);
        
        $this->Cell(40, 6, 'Registration Date:', 0, 0);
        $this->Cell(0, 6, date('F d, Y', strtotime($payment_data['registration_date'])), 0, 1);
        
        $this->Cell(40, 6, 'Expiration Date:', 0, 0);
        $this->Cell(0, 6, date('F d, Y', strtotime($payment_data['expiration_date'])), 0, 1);
        
        $this->Ln(10);
        
        // Add QR Code
        if ($qrPath && file_exists($qrPath)) {
            try {
                $this->SetXY(160, 80);
                $this->Image($qrPath, 160, 80, 30, 30);
                $this->SetXY(160, 115);
                $this->SetFont('Arial', 'I', 8);
                $this->Cell(30, 5, 'Scan QR Code', 0, 1, 'C');
                $this->Cell(30, 5, 'for details', 0, 1, 'C');
                
                // Clean up the temporary QR code file
                @unlink($qrPath);
            } catch (Exception $e) {
                error_log('Failed to add QR code to PDF: ' . $e->getMessage());
                $this->SetXY(160, 80);
                $this->SetFont('Arial', 'I', 8);
                $this->Cell(30, 30, 'QR Code Error', 1, 0, 'C');
            }
        } else {
            $this->SetXY(160, 80);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(30, 30, 'QR Code Error', 1, 0, 'C');
        }
        
        // Footer
        $this->SetY(-40);
        $this->SetFont('Arial', 'I', 10);
        $this->Cell(0, 6, 'This receipt serves as proof of payment for student registration.', 0, 1, 'C');
        $this->Cell(0, 6, 'Please keep this receipt for your records.', 0, 1, 'C');
        $this->Cell(0, 6, 'Generated on: ' . date('F d, Y H:i:s'), 0, 1, 'C');
    }
}

// Function to generate receipt for cash payment
function generateCashPaymentReceipt($payment_data) {
    $receipt = new CashReceipt();
    $receipt->generateCashReceipt($payment_data);
    
    // Create uploads directory if it doesn't exist
    $uploads_dir = __DIR__ . '/../../student/uploads';
    if (!file_exists($uploads_dir)) {
        mkdir($uploads_dir, 0777, true);
    }
    
    // Generate filename
    $safe_ref = str_replace(['/', '\\'], '_', $payment_data['reference_number']);
    $receipt_filename = 'cash_receipt_' . $safe_ref . '_' . time() . '.pdf';
    $receipt_path = $uploads_dir . '/' . $receipt_filename;
    
    // Save the PDF
    $receipt->Output('F', $receipt_path);
    
    return $receipt_filename;
}
?> 