<?php
// Prevent any output before PDF generation
ob_start();

require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';
require_once '../fpdf_temp/fpdf.php';

// Error handling
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 0);

try {
    $auth = new Auth();
    $auth->requireRole('admin');

    $db = Database::getInstance();
    $mysqli = $db->getConnection();

    // Get application ID
    $id = $_GET['id'] ?? 0;

    // Get application details
    $stmt = $mysqli->prepare("SELECT * FROM applications WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $application = $result->fetch_assoc();

    if (!$application) {
        throw new Exception("Application not found");
    }

    // Get payment details
    $payment_data = null;
    $applicant_data = json_decode($application['applicant_data'], true);
    if (isset($applicant_data['payment_reference'])) {
        $stmt = $mysqli->prepare("SELECT * FROM application_payments WHERE reference = ?");
        $stmt->bind_param("s", $applicant_data['payment_reference']);
        $stmt->execute();
        $payment_result = $stmt->get_result();
        $payment_data = $payment_result->fetch_assoc();
    }

    // Create PDF
    class PDF extends FPDF {
        function Header() {
            // Logo
            $possibleLogoPaths = [
                __DIR__ . "/../../assets/images/logo.png",
                __DIR__ . "/../../assets/img/logo.png",
                __DIR__ . "/../assets/images/logo.png",
                __DIR__ . "/../assets/img/logo.png",
                "C:/xampp/htdocs/ACE MODEL COLLEGE/assets/images/logo.png",
                "C:/xampp/htdocs/ACE MODEL COLLEGE/images/logo.png"
            ];
            
            $logoPath = null;
            foreach ($possibleLogoPaths as $path) {
                if (file_exists($path)) {
                    $logoPath = $path;
                    break;
                }
            }
            
            if ($logoPath) {
                $this->Image($logoPath, 10, 10, 30);
                $this->Ln(35);
            } else {
                $this->Ln(10);
            }

            // School name
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 10, SCHOOL_NAME, 0, 1, 'C');
            $this->SetFont('Arial', 'B', 14);
            $this->Cell(0, 10, 'Application Details', 0, 1, 'C');
            $this->Ln(10);
        }

        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }

        function SectionTitle($title) {
            $this->SetFont('Arial', 'B', 12);
            $this->SetFillColor(200, 220, 255);
            $this->Cell(0, 10, $title, 0, 1, 'L', true);
            $this->SetFont('Arial', '', 12);
            $this->Ln(5);
        }

        function Field($label, $value) {
            $this->SetFont('Arial', 'B', 11);
            $this->Cell(60, 8, $label . ':', 0);
            $this->SetFont('Arial', '', 11);
            // Convert null to empty string to avoid deprecation warning
            $displayValue = ($value !== null) ? (string)$value : '';
            $this->MultiCell(0, 8, $displayValue ?: 'N/A');
        }
    }

    // Initialize PDF
    $pdf = new PDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();

    // Application Overview
    $pdf->SectionTitle('Application Overview');
    $pdf->Field('Application ID', '#' . $application['id']);
    $pdf->Field('Application Type', $application['application_type'] ? ucfirst((string)$application['application_type']) : 'N/A');
    $pdf->Field('Status', $application['status'] ? ucfirst((string)$application['status']) : 'N/A');
    $pdf->Field('Submission Date', date('F j, Y H:i', strtotime($application['submission_date'])));
    $pdf->Ln(10);

    // Applicant Information
    $pdf->SectionTitle('Applicant Information');
    $pdf->Field('Full Name', $applicant_data['field_full_name'] ?? '');
    $pdf->Field('Class Seeking Admission', $applicant_data['field_class_admission'] ?? '');
    $pdf->Field('Date of Birth', $applicant_data['field_dob'] ?? '');
    $pdf->Field('Gender', $applicant_data['field_gender'] ?? '');
    $pdf->Field('Previous School', $applicant_data['field_previous_school'] ?? '');
    $pdf->Ln(10);

    // Parent/Guardian Information
    $pdf->SectionTitle('Parent/Guardian Information');
    $pdf->Field('Parent/Guardian Name', $applicant_data['field_parent_name'] ?? '');
    $pdf->Field('Phone Number', $applicant_data['field_parent_phone'] ?? '');
    $pdf->Field('Email', $applicant_data['field_parent_email'] ?? '');
    $pdf->Field('Home Address', $applicant_data['field_home_address'] ?? '');
    $pdf->Ln(10);

    // Payment Information
    if ($payment_data) {
        $pdf->SectionTitle('Payment Information');
        $pdf->Field('Payment Reference', $payment_data['reference'] ?? '');
        $pdf->Field('Amount Paid', $payment_data['amount'] ? '₦' . number_format($payment_data['amount'], 2) : '');
        $pdf->Field('Payment Status', $payment_data['status'] ? ucfirst((string)$payment_data['status']) : '');
        $pdf->Field('Payment Method', $payment_data['payment_method'] ? ucfirst((string)$payment_data['payment_method']) : '');
        $pdf->Field('Payment Date', $payment_data['payment_date'] ? 
            date('F j, Y H:i', strtotime($payment_data['payment_date'])) : 'Not completed');
        $pdf->Ln(10);
    }

    // Review Information
    if ($application['reviewed_by']) {
        $pdf->SectionTitle('Review Information');
        $pdf->Field('Review Date', date('F j, Y H:i', strtotime($application['review_date'])));
        if ($application['comments']) {
            $pdf->Field('Review Comments', $application['comments']);
        }
    }

    // Generate filename
    $filename = sprintf('application_%s_%s.pdf', 
        $application['id'],
        date('Y-m-d')
    );

    // Clear any output buffers
    ob_end_clean();

    // Output PDF
    $pdf->Output('D', $filename);
} catch (Exception $e) {
    // Clear output buffer
    ob_end_clean();
    
    // Log error
    error_log("PDF Generation Error: " . $e->getMessage());
    
    // Redirect with error
    header("Location: view_application.php?id=" . $id . "&error=pdf_generation");
    exit();
} 