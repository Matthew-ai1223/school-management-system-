<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';
require_once '../payment_config.php';
require_once '../fpdf_temp/fpdf.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();
$mysqli = $db->getConnection();

class ApplicationDetailsPDF extends FPDF {
    protected $hasLogo = false;

    function __construct() {
        parent::__construct();
        // Check if logo exists in multiple possible locations
        $possiblePaths = [
            __DIR__ . '/../../../../images/logo.png',
            __DIR__ . '/../../../assets/img/logo.png',
            __DIR__ . '/../../assets/images/logo.png',
            __DIR__ . '/../../assets/img/logo.png',
            'C:/xampp/htdocs/ACE MODEL COLLEGE/assets/images/logo.png',
            'C:/xampp/htdocs/ACE MODEL COLLEGE/images/logo.png'
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

    // Helper function to add a field row
    function AddFieldRow($label, $value) {
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(60, 8, $label, 0, 0);
        $this->SetFont('Arial', '', 10);
        $this->MultiCell(0, 8, $value, 0, 'L');
        $this->SetDrawColor(200, 200, 200);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(2);
    }
    
    // Helper function to display passport photo
    function AddPassportPhoto($photoPath) {
        if (!$photoPath) return;
        
        error_log("Trying to display passport photo: " . $photoPath);
        
        // Try different ways to access the passport photo
        $possiblePaths = [
            // Direct path as provided
            $photoPath,
            // Remove any leading slash to make it relative
            ltrim($photoPath, '/'),
            // Path relative to document root
            $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($photoPath, '/'),
            // Path with various directory adjustments
            '../../../' . ltrim($photoPath, '/'),
            '../../' . ltrim($photoPath, '/'),
            '../' . ltrim($photoPath, '/'),
            // Specific to the application structure
            dirname(__DIR__, 3) . '/' . ltrim($photoPath, '/'),
            // Special case for XAMPP on Windows
            'C:/xampp/htdocs/' . ltrim($photoPath, '/'),
            // Direct paths for Windows
            'C:/xampp/htdocs/ACE MODEL COLLEGE/' . ltrim($photoPath, '/')
        ];
        
        $validPath = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $validPath = $path;
                error_log("Found valid passport photo path: " . $path);
                break;
            } else {
                error_log("Tried path but not found: " . $path);
            }
        }
        
        // Current Y position
        $startY = $this->GetY();
        $rightColumnX = 140;
        
        if ($validPath) {
            try {
                // Get image info to determine type
                $imgInfo = getimagesize($validPath);
                if ($imgInfo) {
                    // Valid image, add to PDF
                    $this->Image($validPath, $rightColumnX, $startY, 50);
                    error_log("Successfully added image to PDF");
                } else {
                    error_log("Invalid image format for passport photo: " . $validPath);
                    $this->DrawPlaceholderImage($rightColumnX, $startY, '(Invalid Format)');
                }
            } catch (Exception $e) {
                error_log("Error adding passport photo to PDF: " . $e->getMessage() . " (Path: $validPath)");
                $this->DrawPlaceholderImage($rightColumnX, $startY, '(Error Loading)');
            }
        } else {
            // Log that we couldn't find the image
            error_log("Could not find passport photo at any of the attempted paths for: $photoPath");
            $this->DrawPlaceholderImage($rightColumnX, $startY, '(Not Found)');
        }
        
        // Return to normal position and add enough space after the photo
        $this->SetY($startY + 55); // Ensure there's enough space after the photo
    }
    
    // Helper to draw placeholder when image can't be loaded
    function DrawPlaceholderImage($x, $y, $message) {
        $this->SetDrawColor(200, 200, 200);
        $this->SetFillColor(240, 240, 240);
        $this->Rect($x, $y, 50, 50, 'DF');
        $this->SetFont('Arial', '', 8);
        $this->SetXY($x, $y + 20);
        $this->Cell(50, 10, 'Passport Photo', 0, 1, 'C');
        $this->SetFont('Arial', '', 7);
        $this->SetXY($x, $y + 30);
        $this->Cell(50, 10, $message, 0, 1, 'C');
    }
}

try {
    // Check if we're downloading multiple applications
    if (isset($_GET['ids'])) {
        $ids = array_map('intval', explode(',', $_GET['ids']));
        if (empty($ids)) {
            throw new Exception('No applications selected');
        }
        
        // Create PDF instance for multiple applications
        $pdf = new ApplicationDetailsPDF();
        $pdf->AliasNbPages();
        
        foreach ($ids as $id) {
            // Get application details
            $stmt = $mysqli->prepare("SELECT * FROM applications WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $application = $result->fetch_assoc();
            
            if (!$application) {
                continue; // Skip if application not found
            }
            
            // Add a new page for each application
            $pdf->AddPage();
            
            // Add application details (reuse existing code)
            // Title
            $pdf->SetFont('Arial', 'B', 18);
            $pdf->SetTextColor(0, 51, 102);
            $pdf->Cell(0, 15, 'Application Details', 0, 1, 'C');
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Ln(5);
            
            // Get form fields
            $fields_query = "SELECT * FROM form_fields WHERE application_type = ? AND is_active = 1 ORDER BY field_order";
            $stmt = $mysqli->prepare($fields_query);
            $stmt->bind_param("s", $application['application_type']);
            $stmt->execute();
            $fields_result = $stmt->get_result();
            $form_fields = [];
            while ($field = $fields_result->fetch_assoc()) {
                $form_fields[] = $field;
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
            
            // Get reviewer details
            $reviewer_name = '';
            if ($application['reviewed_by']) {
                $stmt = $mysqli->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
                $stmt->bind_param("i", $application['reviewed_by']);
                $stmt->execute();
                $reviewer_result = $stmt->get_result();
                if ($reviewer = $reviewer_result->fetch_assoc()) {
                    $reviewer_name = $reviewer['first_name'] . ' ' . $reviewer['last_name'];
                }
            }
            
            // Add sections (reuse existing code)
            // Application Overview Section
            $pdf->AddSectionHeader('Application Overview');
            $pdf->AddFieldRow('Application ID:', '#' . $application['id']);
            $pdf->AddFieldRow('Application Type:', ucfirst($application['application_type']));
            $pdf->AddFieldRow('Status:', ucfirst($application['status']));
            $pdf->AddFieldRow('Submission Date:', date('F d, Y H:i', strtotime($application['submission_date'])));
            if ($reviewer_name) {
                $pdf->AddFieldRow('Reviewed By:', $reviewer_name);
                $pdf->AddFieldRow('Review Date:', date('F d, Y H:i', strtotime($application['review_date'])));
            }
            if ($application['comments']) {
                $pdf->AddFieldRow('Review Comments:', $application['comments']);
            }
            $pdf->Ln(5);
            
            // Payment Information Section
            if ($payment_data) {
                $pdf->AddSectionHeader('Payment Information');
                $pdf->AddFieldRow('Payment Reference:', $payment_data['reference']);
                $pdf->AddFieldRow('Amount Paid:', '₦' . number_format($payment_data['amount'], 2));
                $pdf->AddFieldRow('Payment Status:', ucfirst($payment_data['status']));
                $pdf->AddFieldRow('Payment Method:', ucfirst($payment_data['payment_method']));
                $pdf->AddFieldRow('Payment Date:', $payment_data['payment_date'] ? 
                    date('F d, Y H:i', strtotime($payment_data['payment_date'])) : 'Not completed');
                $pdf->AddFieldRow('Email:', $payment_data['email']);
                $pdf->AddFieldRow('Phone:', $payment_data['phone']);
                if ($payment_data['transaction_reference']) {
                    $pdf->AddFieldRow('Transaction Reference:', $payment_data['transaction_reference']);
                }
                $pdf->Ln(5);
            }
            
            // Applicant Details Section
            $pdf->AddSectionHeader('Applicant Details');
            
            // Check for passport photo field and display it first
            $passportPhotoPath = null;
            foreach ($applicant_data as $key => $value) {
                if (strpos($key, 'field_passport_photo') === 0 || $key === 'field_passport_photo') {
                    $passportPhotoPath = $value;
                    break;
                }
            }
            
            // Display passport photo if found
            if ($passportPhotoPath) {
                $pdf->AddPassportPhoto($passportPhotoPath);
            }
            
            // Display class seeking admission with emphasis (if exists)
            $classAdmission = null;
            foreach ($applicant_data as $key => $value) {
                if (strpos($key, 'field_class_admission') === 0) {
                    $classAdmission = $value;
                    // Display class with special formatting
                    $pdf->SetFont('Arial', 'B', 10);
                    $pdf->Cell(60, 8, 'Class Seeking Admission:', 0, 0);
                    $pdf->SetFont('Arial', 'B', 11);
                    $pdf->SetTextColor(0, 102, 204); // Blue color for emphasis
                    $pdf->Cell(0, 8, $classAdmission, 0, 1);
                    $pdf->SetTextColor(0, 0, 0); // Reset text color
                    $pdf->SetDrawColor(200, 200, 200);
                    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
                    $pdf->Ln(2);
                    break;
                }
            }
            
            // Display other fields
            foreach ($form_fields as $field) {
                $field_value = $applicant_data['field_' . $field['id']] ?? '';
                // Skip passport photo field since we've displayed it separately
                if ($field['id'] === 'passport_photo' || $field['id'] === 'class_admission') {
                    continue;
                }
                
                // Format the field value based on type
                if ($field['field_type'] === 'file' && $field_value) {
                    $field_value = 'File uploaded: ' . basename($field_value);
                } elseif ($field['field_type'] === 'date' && $field_value) {
                    $field_value = date('Y-m-d', strtotime($field_value));
                }
                
                $pdf->AddFieldRow($field['field_label'] . ':', $field_value);
            }
        }
        
        // Output the combined PDF
        $pdf->Output('D', 'Bulk_Applications_' . date('Y-m-d') . '.pdf');
        
    } else {
        // Single application download (existing code)
        $id = $_GET['id'] ?? 0;

        // Get application details
        $stmt = $mysqli->prepare("SELECT * FROM applications WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $application = $result->fetch_assoc();

        if (!$application) {
            die("Application not found");
        }

        // Get form fields
        $fields_query = "SELECT * FROM form_fields WHERE application_type = ? AND is_active = 1 ORDER BY field_order";
        $stmt = $mysqli->prepare($fields_query);
        $stmt->bind_param("s", $application['application_type']);
        $stmt->execute();
        $fields_result = $stmt->get_result();
        $form_fields = [];
        while ($field = $fields_result->fetch_assoc()) {
            $form_fields[] = $field;
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

        // Get reviewer details
        $reviewer_name = '';
        if ($application['reviewed_by']) {
            $stmt = $mysqli->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
            $stmt->bind_param("i", $application['reviewed_by']);
            $stmt->execute();
            $reviewer_result = $stmt->get_result();
            if ($reviewer = $reviewer_result->fetch_assoc()) {
                $reviewer_name = $reviewer['first_name'] . ' ' . $reviewer['last_name'];
            }
        }

        // Create PDF instance
        $pdf = new ApplicationDetailsPDF();
        $pdf->AliasNbPages();
        $pdf->AddPage();

        // Title
        $pdf->SetFont('Arial', 'B', 18);
        $pdf->SetTextColor(0, 51, 102);
        $pdf->Cell(0, 15, 'Application Details', 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(5);

        // Application Overview Section
        $pdf->AddSectionHeader('Application Overview');
        $pdf->AddFieldRow('Application ID:', '#' . $application['id']);
        $pdf->AddFieldRow('Application Type:', ucfirst($application['application_type']));
        $pdf->AddFieldRow('Status:', ucfirst($application['status']));
        $pdf->AddFieldRow('Submission Date:', date('F d, Y H:i', strtotime($application['submission_date'])));
        if ($reviewer_name) {
            $pdf->AddFieldRow('Reviewed By:', $reviewer_name);
            $pdf->AddFieldRow('Review Date:', date('F d, Y H:i', strtotime($application['review_date'])));
        }
        if ($application['comments']) {
            $pdf->AddFieldRow('Review Comments:', $application['comments']);
        }
        $pdf->Ln(5);

        // Payment Information Section
        if ($payment_data) {
            $pdf->AddSectionHeader('Payment Information');
            $pdf->AddFieldRow('Payment Reference:', $payment_data['reference']);
            $pdf->AddFieldRow('Amount Paid:', '₦' . number_format($payment_data['amount'], 2));
            $pdf->AddFieldRow('Payment Status:', ucfirst($payment_data['status']));
            $pdf->AddFieldRow('Payment Method:', ucfirst($payment_data['payment_method']));
            $pdf->AddFieldRow('Payment Date:', $payment_data['payment_date'] ? 
                date('F d, Y H:i', strtotime($payment_data['payment_date'])) : 'Not completed');
            $pdf->AddFieldRow('Email:', $payment_data['email']);
            $pdf->AddFieldRow('Phone:', $payment_data['phone']);
            if ($payment_data['transaction_reference']) {
                $pdf->AddFieldRow('Transaction Reference:', $payment_data['transaction_reference']);
            }
            $pdf->Ln(5);
        }

        // Applicant Details Section
        $pdf->AddSectionHeader('Applicant Details');
        
        // Check for passport photo field and display it first
        $passportPhotoPath = null;
        foreach ($applicant_data as $key => $value) {
            if (strpos($key, 'field_passport_photo') === 0 || $key === 'field_passport_photo') {
                $passportPhotoPath = $value;
                break;
            }
        }
        
        // Display passport photo if found
        if ($passportPhotoPath) {
            $pdf->AddPassportPhoto($passportPhotoPath);
        }
        
        // Display class seeking admission with emphasis (if exists)
        $classAdmission = null;
        foreach ($applicant_data as $key => $value) {
            if (strpos($key, 'field_class_admission') === 0) {
                $classAdmission = $value;
                // Display class with special formatting
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->Cell(60, 8, 'Class Seeking Admission:', 0, 0);
                $pdf->SetFont('Arial', 'B', 11);
                $pdf->SetTextColor(0, 102, 204); // Blue color for emphasis
                $pdf->Cell(0, 8, $classAdmission, 0, 1);
                $pdf->SetTextColor(0, 0, 0); // Reset text color
                $pdf->SetDrawColor(200, 200, 200);
                $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
                $pdf->Ln(2);
                break;
            }
        }
        
        // Display other fields
        foreach ($form_fields as $field) {
            $field_value = $applicant_data['field_' . $field['id']] ?? '';
            
            // Skip passport photo field since we've displayed it separately
            if ($field['id'] === 'passport_photo' || $field['id'] === 'class_admission') {
                continue;
            }
            
            // Format the field value based on type
            if ($field['field_type'] === 'file' && $field_value) {
                $field_value = 'File uploaded: ' . basename($field_value);
            } elseif ($field['field_type'] === 'date' && $field_value) {
                $field_value = date('Y-m-d', strtotime($field_value));
            }
            
            $pdf->AddFieldRow($field['field_label'] . ':', $field_value);
        }

        // Output PDF
        $pdf->Output('D', 'Application_Details_' . $application['id'] . '.pdf');
    }

} catch (Exception $e) {
    error_log('PDF Generation Error: ' . $e->getMessage());
    header('Location: ' . (isset($_GET['ids']) ? 'applications.php' : 'view_application.php?id=' . $id) . '&error=pdf_generation');
    exit();
} 