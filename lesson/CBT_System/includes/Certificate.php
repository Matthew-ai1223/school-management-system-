<?php
require_once 'vendor/autoload.php';

class Certificate extends TCPDF {
    private $title;
    private $student_name;
    private $exam_title;
    private $certificate_number;
    private $issue_date;
    private $verification_url;

    public function __construct($title, $student_name, $exam_title, $certificate_number, $issue_date) {
        parent::__construct(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        $this->title = $title;
        $this->student_name = $student_name;
        $this->exam_title = $exam_title;
        $this->certificate_number = $certificate_number;
        $this->issue_date = $issue_date;
        $this->verification_url = SITE_URL . '/verify-certificate.php?code=' . $certificate_number;

        $this->setupPDF();
    }

    private function setupPDF() {
        // Set document information
        $this->SetCreator(SITE_NAME);
        $this->SetAuthor(SITE_NAME);
        $this->SetTitle($this->title);

        // Set margins
        $this->SetMargins(15, 15, 15);
        
        // Remove header/footer
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);

        // Set auto page breaks
        $this->SetAutoPageBreak(true, 15);

        // Set image scale factor
        $this->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // Set default font
        $this->SetFont('helvetica', '', 12);
    }

    private function addWatermark() {
        // Save current state
        $this->StartTransform();
        
        // Rotate and make transparent
        $this->SetAlpha(0.1);
        $this->Rotate(45, $this->getPageWidth()/2, $this->getPageHeight()/2);
        
        // Add watermark text
        $this->SetFont('helvetica', 'B', 50);
        $this->SetTextColor(128, 128, 128);
        $this->Text(50, 120, SITE_NAME);
        
        // Restore state
        $this->StopTransform();
    }

    private function addQRCode() {
        // Generate QR code
        $style = array(
            'border' => false,
            'padding' => 0,
            'fgcolor' => array(0, 0, 0),
            'bgcolor' => false
        );
        
        // Position QR code in bottom right
        $this->write2DBarcode(
            $this->verification_url,
            'QRCODE,H',
            $this->getPageWidth()-70,
            $this->getPageHeight()-70,
            50,
            50,
            $style
        );

        // Add verification text
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(0, 0, 0);
        $this->SetXY($this->getPageWidth()-90, $this->getPageHeight()-15);
        $this->Cell(70, 0, 'Scan to verify certificate', 0, 0, 'C');
    }

    public function generateCertificate() {
        // Add a page in landscape format
        $this->AddPage('L', 'A4');

        // Add watermark
        $this->addWatermark();

        // Add border
        $this->SetLineWidth(1);
        $this->SetDrawColor(0, 0, 0);
        $this->Rect(10, 10, $this->getPageWidth()-20, $this->getPageHeight()-20);

        // Add decorative inner border
        $this->SetLineWidth(0.5);
        $this->SetDrawColor(64, 64, 64);
        $this->Rect(15, 15, $this->getPageWidth()-30, $this->getPageHeight()-30);

        // Add logo if exists
        if (file_exists(UPLOAD_DIR . 'logo.png')) {
            $this->Image(UPLOAD_DIR . 'logo.png', 30, 25, 40);
        }

        // Set font for title
        $this->SetFont('helvetica', 'B', 24);
        $this->SetTextColor(0, 102, 204);
        $this->SetY(50);
        $this->Cell(0, 0, $this->title, 0, 1, 'C');

        // Add main text
        $this->SetFont('helvetica', '', 12);
        $this->SetTextColor(0, 0, 0);
        $this->SetY(80);
        $this->Cell(0, 0, 'This is to certify that', 0, 1, 'C');

        // Add student name
        $this->SetFont('helvetica', 'B', 20);
        $this->SetY(100);
        $this->Cell(0, 0, $this->student_name, 0, 1, 'C');

        // Add completion text
        $this->SetFont('helvetica', '', 12);
        $this->SetY(120);
        $this->Cell(0, 0, 'has successfully completed', 0, 1, 'C');

        // Add exam title
        $this->SetFont('helvetica', 'B', 16);
        $this->SetY(140);
        $this->Cell(0, 0, $this->exam_title, 0, 1, 'C');

        // Add certificate details
        $this->SetFont('helvetica', '', 10);
        $this->SetY(170);
        $this->Cell(0, 0, 'Certificate Number: ' . $this->certificate_number, 0, 1, 'C');
        $this->SetY(180);
        $this->Cell(0, 0, 'Issue Date: ' . date('F d, Y', strtotime($this->issue_date)), 0, 1, 'C');

        // Add signature lines
        $this->SetY(-60);
        $this->Line(50, $this->GetY(), 120, $this->GetY());
        $this->Line($this->getPageWidth()-120, $this->GetY(), $this->getPageWidth()-50, $this->GetY());
        
        $this->SetY(-50);
        $this->Cell(($this->getPageWidth()/2), 0, 'Examiner', 0, 0, 'C');
        $this->Cell(($this->getPageWidth()/2), 0, 'Administrator', 0, 1, 'C');

        // Add QR code
        $this->addQRCode();
    }
} 