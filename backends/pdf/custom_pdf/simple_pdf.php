<?php
/**
 * Simple PDF Generator for Student Registration Receipt
 * Uses FPDF library which is already available in the system
 */

// Prevent direct access
if (!defined('ALLOW_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Generate a PDF receipt for student registration using FPDF
 * 
 * @param array $studentData Array of student registration data
 * @return bool Whether PDF generation was successful
 */
function generateSimplePDF($studentData) {
    try {
        // Include FPDF library
        require_once __DIR__ . '/../../../fpdf_temp/fpdf.php';
        
        // Create PDF instance - A4 portrait
        $pdf = new FPDF('P', 'mm', 'A4');
        
        // Add a page
        $pdf->AddPage();
        
        // Add school logo
        $logoPath = __DIR__ . '/../../../images/logo.png';
        if (file_exists($logoPath)) {
            // Logo exists, add it to the PDF (centered at the top)
            $pdf->Image($logoPath, 85, 10, 40, 0, 'PNG'); // Adjust x, y, and width as needed
            $pdf->Ln(45); // Add space after the logo
        }
        
        // Set font
        $pdf->SetFont('Arial', 'B', 16);
        
        // School name and title
        $pdf->Cell(0, 10, 'ACE MODEL COLLEGE', 0, 1, 'C');
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, 'STUDENT REGISTRATION RECEIPT', 0, 1, 'C');
        
        // Date
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 10, 'Date: ' . date('F j, Y'), 0, 1, 'R');
        
        // Add a line
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(5);
        
        // Get the admission/registration number - handle different possible key names
        $admissionNumber = '';
        if (isset($studentData['admission_number'])) {
            $admissionNumber = $studentData['admission_number'];
        } elseif (isset($studentData['registration_number'])) {
            $admissionNumber = $studentData['registration_number'];
        } elseif (isset($studentData['password'])) {
            // Use password as a fallback since it's often used as the registration number
            $admissionNumber = $studentData['password'];
        }
        
        // Registration Number
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(0, 10, 'ADMISSION/REGISTRATION NUMBER: ' . $admissionNumber, 1, 1, 'C', true);
        $pdf->Ln(5);
        
        // Student Information header
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, 'STUDENT INFORMATION', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 10);
        
        // Create student info table
        $pdf->SetFillColor(245, 245, 245);
        
        // Student name
        $pdf->Cell(60, 8, 'Name:', 1, 0, 'L');
        $pdf->Cell(130, 8, $studentData['first_name'] . ' ' . $studentData['last_name'], 1, 1, 'L', true);
        
        // Email
        $pdf->Cell(60, 8, 'Email:', 1, 0, 'L');
        $pdf->Cell(130, 8, $studentData['email'], 1, 1, 'L');
        
        // Date of Birth
        $pdf->Cell(60, 8, 'Date of Birth:', 1, 0, 'L');
        $pdf->Cell(130, 8, $studentData['date_of_birth'], 1, 1, 'L', true);
        
        // Gender
        $pdf->Cell(60, 8, 'Gender:', 1, 0, 'L');
        $pdf->Cell(130, 8, $studentData['gender'], 1, 1, 'L');
        
        // Phone
        $pdf->Cell(60, 8, 'Phone:', 1, 0, 'L');
        $pdf->Cell(130, 8, $studentData['phone'] ?? 'N/A', 1, 1, 'L', true);
        
        // Address
        $pdf->Cell(60, 8, 'Address:', 1, 0, 'L');
        $pdf->Cell(130, 8, $studentData['address'] ?? 'N/A', 1, 1, 'L');
        
        // Class
        $pdf->Cell(60, 8, 'Class:', 1, 0, 'L');
        
        // Handle class display correctly
        $classValue = 'N/A';
        if (!empty($studentData['class_name'])) {
            $classValue = $studentData['class_name'];
        } else if (!empty($studentData['class_type'])) {
            $classValue = $studentData['class_type'];
        } else if (!empty($studentData['class_id'])) {
            $classValue = $studentData['class_id'];
        }
        
        $pdf->Cell(130, 8, $classValue, 1, 1, 'L', true);
        
        // Previous School
        $pdf->Cell(60, 8, 'Previous School:', 1, 0, 'L');
        $pdf->Cell(130, 8, $studentData['previous_school'] ?? 'N/A', 1, 1, 'L');
        
        $pdf->Ln(5);
        
        // Parent/Guardian Information header
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, 'PARENT/GUARDIAN INFORMATION', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 10);
        
        // Parent name
        $pdf->Cell(60, 8, 'Name:', 1, 0, 'L');
        $pdf->Cell(130, 8, $studentData['parent_name'] ?? 'N/A', 1, 1, 'L', true);
        
        // Parent phone
        $pdf->Cell(60, 8, 'Phone:', 1, 0, 'L');
        $pdf->Cell(130, 8, $studentData['parent_phone'] ?? 'N/A', 1, 1, 'L');
        
        // Parent email
        $pdf->Cell(60, 8, 'Email:', 1, 0, 'L');
        $pdf->Cell(130, 8, $studentData['parent_email'] ?? 'N/A', 1, 1, 'L', true);
        
        // Parent address
        $pdf->Cell(60, 8, 'Address:', 1, 0, 'L');
        $pdf->Cell(130, 8, $studentData['parent_address'] ?? 'N/A', 1, 1, 'L');
        
        $pdf->Ln(5);
        
        // Login Credentials header
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, 'LOGIN CREDENTIALS', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 10);
        
        // Username
        $pdf->Cell(60, 8, 'Username:', 1, 0, 'L');
        $pdf->Cell(130, 8, $studentData['username'], 1, 1, 'L', true);
        
        // Password
        $pdf->Cell(60, 8, 'Password:', 1, 0, 'L');
        $pdf->Cell(130, 8, $studentData['password'], 1, 1, 'L');
        
        $pdf->Ln(10);
        
        // Footer
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 6, 'Congratulations on your registration!. Note that you are to bring two copies of this receipt to the school office to complete your registration.', 0, 1, 'C');
        $pdf->Cell(0, 6, 'The login credentials provided above will be needed to access your student account.', 0, 1, 'C');
        // $pdf->Cell(0, 6, 'Welcome to ACE MODEL COLLEGE!', 0, 1, 'C');

        // $output .= "Congratulations on your registration!. Note that you are to bring two copies of this receipt to the school office to complete your registration.\n";
        // $output .= "The login credentials provided above will be needed to access your student account.\n\n";
        // $output .= "=========================================\n";
        
        // Output the PDF as download
        $pdf->Output('D', 'registration_receipt.pdf');
        return true;
        
    } catch (Exception $e) {
        // Log the error
        error_log('PDF Generation Error: ' . $e->getMessage());
        return false;
    }
} 