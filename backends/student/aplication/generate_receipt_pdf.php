<?php
require_once '../../config.php';
require_once '../../database.php';
require_once '../../payment_config.php';
require_once '../../../backends/fpdf_temp/fpdf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class ApplicationPDF extends FPDF {
    protected $hasLogo = false;

    function __construct() {
        parent::__construct();
        // Check if logo exists in multiple possible locations
        $possiblePaths = [
            __DIR__ . '/../../../assets/images/logo.png',
            __DIR__ . '/../../../assets/img/logo.png',
            __DIR__ . '/../../assets/images/logo.png',
            __DIR__ . '/../../assets/img/logo.png',
            'C:/xampp/htdocs/ACE MODEL COLLEGE/assets/images/logo.png'
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $this->hasLogo = $path;
                break;
            }
        }
    }

    function Header() {
        // Set background color for header
        $this->SetFillColor(245, 245, 245);
        $this->Rect(0, 0, 210, 40, 'F');
        
        if ($this->hasLogo) {
            // Logo
            $this->Image($this->hasLogo, 10, 6, 30);
            // Move to the right of logo
            $this->Cell(35);
        }

        // School name with larger font
        $this->SetFont('Arial', 'B', 20);
        $this->SetTextColor(0, 51, 102); // Navy blue color
        $this->Cell(($this->hasLogo ? 130 : 190), 15, SCHOOL_NAME, 0, 1, 'C');
        
        // Add slogan
        $this->SetFont('Arial', 'I', 12);
        $this->SetTextColor(128, 128, 128); // Gray color
        $this->Cell(($this->hasLogo ? 165 : 190), 8, '"Excellent with Integrity"', 0, 1, 'C');
        
        // Add decorative line
        $this->SetDrawColor(0, 51, 102); // Navy blue
        $this->SetLineWidth(0.5);
        $this->Line(10, 40, 200, 40);
        
        // Reset text color
        $this->SetTextColor(0, 0, 0);
        $this->Ln(15);
    }

    function Footer() {
        // Position at 1.5 cm from bottom
        $this->SetY(-25);
        
        // Draw line
        $this->SetDrawColor(0, 51, 102);
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        
        // Add footer text
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, 'This is a computer-generated document. No signature is required.', 0, 1, 'C');
        $this->Cell(0, 5, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    // Helper function to add section headers
    function AddSectionHeader($title) {
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor(0, 51, 102);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 10, '  ' . $title, 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(2);
    }
}

try {
    // Create PDF instance
    $pdf = new ApplicationPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();

    // Title
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->Cell(0, 15, 'Application Receipt', 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(5);

    // Get application details
    $applicationType = isset($_GET['type']) ? $_GET['type'] : '';
    $applicationRef = isset($_GET['app_ref']) ? $_GET['app_ref'] : '';
    $paymentRef = isset($_GET['pay_ref']) ? $_GET['pay_ref'] : '';
    $firstName = isset($_GET['first_name']) ? $_GET['first_name'] : '';
    $lastName = isset($_GET['last_name']) ? $_GET['last_name'] : '';
    $applicantName = trim($firstName . ' ' . $lastName);
    $date = date('F d, Y');

    // Function to add a row with consistent styling
    function addRow($pdf, $label, $value) {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(60, 8, $label, 0, 0);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 8, $value, 0, 1);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(2);
    }

    // Application Information Section
    $pdf->AddSectionHeader('Application Information');
    $pdf->SetFont('Arial', '', 10);
    
    addRow($pdf, 'Date:', $date);
    addRow($pdf, 'First Name:', ucwords($firstName));
    addRow($pdf, 'Last Name:', ucwords($lastName));
    addRow($pdf, 'Application Type:', ucfirst($applicationType) . ' Application');
    addRow($pdf, 'Application Reference:', $applicationRef);
    addRow($pdf, 'Payment Reference:', $paymentRef);
    $pdf->Ln(5);

    // Payment Information Section
    $pdf->AddSectionHeader('Payment Information');
    $pdf->SetFont('Arial', '', 10);
    
    // Calculate amount
    $amount = (strtolower($applicationType) === 'kiddies') ? KIDDIES_APPLICATION_FEE : COLLEGE_APPLICATION_FEE;
    addRow($pdf, 'Amount Paid:', '₦' . number_format($amount, 2));
    addRow($pdf, 'Payment Status:', PAYMENT_STATUS_COMPLETED);
    addRow($pdf, 'Currency:', 'Nigerian Naira (NGN)');
    $pdf->Ln(5);

    // Important Notice Section
    $pdf->AddSectionHeader('Important Notice');
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 6, 'Please keep this receipt safe. You are required to print this receipt and bring it with you to the school admission office within 5-7 working days.', 0, 'L');
    $pdf->Ln(5);

    // Contact Information Section
    $pdf->AddSectionHeader('Contact Information');
    $pdf->SetFont('Arial', '', 10);
    addRow($pdf, 'Email:', SCHOOL_EMAIL);
    addRow($pdf, 'Phone:', SCHOOL_PHONE);
    addRow($pdf, 'Address:', SCHOOL_ADDRESS);
    addRow($pdf, 'Phone :', SCHOOL_PHONE);
        
    // Add QR Code if needed
    // $pdf->Image('qr_code.png', 160, 230, 30);

    // Output PDF
    $pdf->Output('D', 'Application_Receipt_' . $applicationRef . '.pdf');
} catch (Exception $e) {
    error_log('PDF Generation Error: ' . $e->getMessage());
    header('Location: application_successful.php?error=pdf_generation');
    exit();
} 