<?php
require('../fpdf/fpdf.php');

class Receipt extends FPDF {
    private $schoolName = 'ACE COLLEGE TUTORIAL';
    private $schoolAddress = 'Beside Agodi Baptist Church';
    private $primaryColor = [0, 71, 171];  // Deep Blue
    private $secondaryColor = [220, 220, 220];  // Light Gray
    private $accentColor = [0, 112, 192];  // Medium Blue

    function Header() {
        try {
            // School Logo - Make it optional
            $logo_path = dirname(__FILE__) . '/../assets/logo.png';
            if (file_exists($logo_path)) {
                $this->Image($logo_path, 10, 10, 25);
            }
            // If no logo, just add some spacing
            else {
                $this->Ln(5);
            }
            
            // School Name and Address
            $this->SetFont('Arial', 'B', 16);
            $this->SetTextColor(...$this->primaryColor);
            $this->Cell(25); // Move past the logo space
            $this->Cell(0, 8, $this->schoolName, 0, 1, 'C');
            $this->SetFont('Arial', '', 10);
            $this->Cell(25);
            $this->Cell(0, 6, $this->schoolAddress, 0, 1, 'C');
            
            // Receipt Title
            $this->Ln(10);
            $this->SetDrawColor(...$this->primaryColor);
            $this->SetFillColor(...$this->secondaryColor);
            $this->SetFont('Arial', 'B', 14);
            $title = 'OFFICIAL PAYMENT RECEIPT';
            $w = $this->GetStringWidth($title) + 20;
            $this->SetX((210 - $w) / 2);
            $this->Cell($w, 10, $title, 1, 1, 'C', true);
            $this->Ln(10);
        } catch (Exception $e) {
            error_log('Error in receipt header: ' . $e->getMessage());
            throw $e;
        }
    }

    function Footer() {
        $this->SetY(-25);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128);
        $this->Cell(0, 5, 'This is a computer-generated document. No signature required.', 0, 1, 'C');
        $this->Cell(0, 5, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    private function addSection($title, $data) {
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor(...$this->primaryColor);
        $this->SetTextColor(255);
        $this->Cell(0, 8, ' ' . $title, 1, 1, 'L', true);
        $this->Ln(2);

        $this->SetTextColor(0);
        $this->SetFont('Arial', '', 11);
        $this->SetFillColor(...$this->secondaryColor);

        $fill = false;
        foreach ($data as $label => $value) {
            $this->Cell(60, 7, '  ' . $label . ':', 0, 0, 'L', $fill);
            $this->Cell(0, 7, $value, 0, 1, 'L', $fill);
            $fill = !$fill;
        }
        $this->Ln(5);
    }

    function generateReceipt($user, $payment_details, $table) {
        try {
            $this->AliasNbPages();
            $this->AddPage();
            
            // Add student photo if available
            if (isset($user['photo']) && file_exists($user['photo'])) {
                $this->Image($user['photo'], 160, 40, 30, 30); // Position the photo on the right
            }
            
            // Receipt Information
            $receiptInfo = [
                'Receipt No' => $payment_details['reference'],
                'Date' => date('F j, Y h:i A')
            ];
            $this->addSection('Receipt Information', $receiptInfo);

            // Student Information
            $studentInfo = [
                'Full Name' => $user['fullname'],
                'Email' => $user['email'],
                'Phone' => $user['phone'],
                'Department' => ucfirst($user['department']),
                'Class Type' => $table === 'morning_students' ? 'Morning Class' : 'Afternoon Class'
            ];
            $this->addSection('Student Information', $studentInfo);

            // Payment Details
            $paymentInfo = [
                'Payment Type' => ucfirst($payment_details['payment_type']),
                'Amount Paid' => '₦' . number_format($payment_details['amount'], 2),
                'Payment Reference' => $payment_details['reference'],
                'Expiration Date' => date('F j, Y', strtotime($payment_details['expiration_date']))
            ];
            $this->addSection('Payment Details', $paymentInfo);

            // Terms and Conditions with styled box
            $this->SetDrawColor(...$this->accentColor);
            $this->SetFillColor(245, 245, 245);
            $this->Rect(10, $this->GetY(), 190, 25, 'DF');
            
            $this->SetFont('Arial', 'I', 10);
            $this->SetXY(15, $this->GetY() + 5);
            $this->MultiCell(180, 5, 
                "This receipt serves as proof of payment for ACE COLLEGE TUTORIAL services. " .
                "Please keep it for your records. The account will be valid until the expiration date shown above. " .
                "For inquiries, please visit our office or contact our support team."
            );
            
        } catch (Exception $e) {
            error_log('Error generating receipt: ' . $e->getMessage());
            throw $e;
        }
    }
}

function generateAndSaveReceipt($user, $payment_details, $table) {
    try {
        // Create uploads directory if it doesn't exist
        $uploads_dir = dirname(__FILE__) . '/uploads';
        if (!file_exists($uploads_dir)) {
            if (!mkdir($uploads_dir, 0777, true)) {
                throw new Exception('Failed to create uploads directory');
            }
        }

        // Generate a unique filename
        $filename = 'receipt_' . $payment_details['reference'] . '.pdf';
        $filepath = $uploads_dir . '/' . $filename;
        
        // Check if file already exists and is writable
        if (file_exists($filepath) && !is_writable($filepath)) {
            throw new Exception('Receipt file exists but is not writable');
        }

        // Generate the PDF
        $pdf = new Receipt();
        $pdf->generateReceipt($user, $payment_details, $table);
        
        // Save the PDF to file
        $pdf->Output('F', $filepath);

        // Verify the file was created
        if (!file_exists($filepath)) {
            throw new Exception('Failed to create receipt file');
        }

        // Return the relative path to the file
        return [
            'filename' => $filename,
            'filepath' => 'uploads/' . $filename
        ];
    } catch (Exception $e) {
        error_log('Error in generateAndSaveReceipt: ' . $e->getMessage());
        throw $e;
    }
}
?> 