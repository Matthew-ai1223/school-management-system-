<?php
require_once 'config.php';
require_once 'fpdf_temp/fpdf.php';

class PDFGenerator extends FPDF {
    function Header() {
        // Define colors for header
        $primaryColor = [0, 51, 102]; // Dark blue
        $secondaryColor = [220, 57, 18]; // Red
        $accentColor = [16, 150, 24]; // Green
        
        // Add a colored banner at the top
        $this->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $this->Rect(0, 0, 210, 25, 'F');
        
        // Check for logo file in common locations
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
        
        // Position at 10mm from left
        $this->SetX(10);
        
        // If logo exists, add it
        if ($logoPath) {
            $this->Image($logoPath, 10, 5, 15, 15);
            $this->SetX(30); // Adjust X position to make room for logo
        }
        
        // School name in white text
        $this->SetY(5);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 7, SCHOOL_NAME, 0, 1, 'C');
        
        // Address in slightly smaller white text
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, SCHOOL_ADDRESS, 0, 1, 'C');
        
        // Contact info
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, 'Phone: ' . SCHOOL_PHONE . ' | Email: ' . SCHOOL_EMAIL, 0, 1, 'C');
        
        // Reset text color and add some space
        $this->SetTextColor(0, 0, 0);
        
        // Draw a decorative line
        $this->SetLineWidth(0.5);
        $this->SetDrawColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
        $this->Line(10, 25, 200, 25);
        
        // Add some space before content begins
        $this->Ln(10);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function generateApplicationForm($studentData) {
        $this->AddPage();
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'Application Form', 0, 1, 'C');
        $this->Ln(10);

        $this->SetFont('Arial', '', 12);
        $this->Cell(60, 10, 'Registration Number:', 0);
        $this->Cell(0, 10, $studentData['registration_number'], 0, 1);

        $this->Cell(60, 10, 'Full Name:', 0);
        $this->Cell(0, 10, $studentData['first_name'] . ' ' . $studentData['last_name'], 0, 1);

        $this->Cell(60, 10, 'Date of Birth:', 0);
        $this->Cell(0, 10, $studentData['date_of_birth'], 0, 1);

        $this->Cell(60, 10, 'Gender:', 0);
        $this->Cell(0, 10, $studentData['gender'], 0, 1);

        $this->Cell(60, 10, 'Parent Name:', 0);
        $this->Cell(0, 10, $studentData['parent_name'], 0, 1);

        $this->Cell(60, 10, 'Parent Phone:', 0);
        $this->Cell(0, 10, $studentData['parent_phone'], 0, 1);

        $this->Cell(60, 10, 'Parent Email:', 0);
        $this->Cell(0, 10, $studentData['parent_email'], 0, 1);

        $this->Cell(60, 10, 'Application Date:', 0);
        $this->Cell(0, 10, $studentData['application_date'], 0, 1);
    }

    function generatePaymentReceipt($paymentData) {
        $this->AddPage();
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'Payment Receipt', 0, 1, 'C');
        $this->Ln(10);

        $this->SetFont('Arial', '', 12);
        $this->Cell(60, 10, 'Receipt Number:', 0);
        $this->Cell(0, 10, $paymentData['reference_number'], 0, 1);

        $this->Cell(60, 10, 'Student Name:', 0);
        $this->Cell(0, 10, $paymentData['student_name'], 0, 1);

        $this->Cell(60, 10, 'Payment Type:', 0);
        $this->Cell(0, 10, $paymentData['payment_type'], 0, 1);

        $this->Cell(60, 10, 'Amount:', 0);
        $this->Cell(0, 10, '₦' . number_format($paymentData['amount'], 2), 0, 1);

        $this->Cell(60, 10, 'Payment Method:', 0);
        $this->Cell(0, 10, $paymentData['payment_method'], 0, 1);

        $this->Cell(60, 10, 'Payment Date:', 0);
        $this->Cell(0, 10, $paymentData['payment_date'], 0, 1);

        $this->Cell(60, 10, 'Status:', 0);
        $this->Cell(0, 10, $paymentData['status'], 0, 1);
    }

    function generateExamResult($resultData) {
        $this->AddPage();
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'Examination Result', 0, 1, 'C');
        $this->Ln(10);

        $this->SetFont('Arial', '', 12);
        $this->Cell(60, 10, 'Student Name:', 0);
        $this->Cell(0, 10, $resultData['student_name'], 0, 1);

        $this->Cell(60, 10, 'Exam Type:', 0);
        $this->Cell(0, 10, $resultData['exam_type'], 0, 1);

        $this->Cell(60, 10, 'Score:', 0);
        $this->Cell(0, 10, $resultData['score'] . '/' . $resultData['total_score'], 0, 1);

        $this->Cell(60, 10, 'Percentage:', 0);
        $this->Cell(0, 10, number_format(($resultData['score'] / $resultData['total_score']) * 100, 2) . '%', 0, 1);

        $this->Cell(60, 10, 'Status:', 0);
        $this->Cell(0, 10, $resultData['status'], 0, 1);

        $this->Cell(60, 10, 'Exam Date:', 0);
        $this->Cell(0, 10, $resultData['exam_date'], 0, 1);
    }

    function generateStudentProfile($studentData) {
        $this->AddPage();
        
        // Define colors
        $primaryColor = [0, 51, 102]; // Dark blue
        $secondaryColor = [220, 57, 18]; // Red
        $accentColor = [16, 150, 24]; // Green
        $lightBgColor = [245, 245, 245]; // Light gray
        $mediumBgColor = [220, 220, 220]; // Medium gray
        $headerBgColor = [0, 71, 122]; // Deep blue
        
        // Title with colored background
        $this->SetFillColor($headerBgColor[0], $headerBgColor[1], $headerBgColor[2]);
        $this->SetTextColor(255, 255, 255); // White text
        $this->SetFont('Arial', 'B', 18);
        $this->Cell(0, 15, 'COMPLETE STUDENT PROFILE', 0, 1, 'C', true);
        
        // Reset text color
        $this->SetTextColor(0, 0, 0);
        $this->Ln(5);
        
        // Student Information Section with styled header
        $this->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'STUDENT INFORMATION', 1, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        
        // Add a passport photo if available
        $profile_image = $studentData['file_path'] ?? null;
        if (!empty($profile_image)) {
            $img_path = str_replace('../../', $_SERVER['DOCUMENT_ROOT'] . '/backends/', $profile_image);
            if (file_exists($img_path)) {
                $this->Image($img_path, 160, 40, 30, 30);
                
                // Add a border around the image
                $this->SetDrawColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
                $this->Rect(160, 40, 30, 30);
            }
        }
        
        // Registration Information with colored subheading
        $this->SetFillColor($lightBgColor[0], $lightBgColor[1], $lightBgColor[2]);
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $this->Cell(0, 10, 'Registration Information', 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        
        // Table-like layout for better readability
        $this->SetFillColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(60, 8, 'Registration Number:', 1, 0, 'L', true);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 8, $studentData['registration_number'] ?? 'N/A', 1, 1, 'L', true);
        
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(60, 8, 'Registration Type:', 1, 0, 'L', true);
        $this->SetFont('Arial', '', 10);
        $regType = $studentData['application_type'] ?? 'N/A';
        if (strtolower($regType) == 'kiddies') {
            $regType = 'Ace Kiddies';
        } elseif (strtolower($regType) == 'college') {
            $regType = 'Ace College';
        }
        $this->Cell(0, 8, $regType, 1, 1, 'L', true);
        
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(60, 8, 'Registration Date:', 1, 0, 'L', true);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 8, ($studentData['created_at'] ? date('F j, Y', strtotime($studentData['created_at'])) : 'N/A'), 1, 1, 'L', true);
        
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(60, 8, 'Status:', 1, 0, 'L', true);
        $this->SetFont('Arial', '', 10);
        
        // Color-coded status
        $status = ucfirst((string)$studentData['status'] ?? 'N/A');
        if (strtolower($status) == 'registered') {
            $this->SetTextColor($accentColor[0], $accentColor[1], $accentColor[2]);
        } elseif (strtolower($status) == 'pending') {
            $this->SetTextColor(255, 153, 0); // Orange for pending
        } elseif (strtolower($status) == 'rejected') {
            $this->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
        }
        $this->Cell(0, 8, $status, 1, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        
        $this->Ln(5);
        
        // Personal Information with styled subheading
        $this->SetFillColor($lightBgColor[0], $lightBgColor[1], $lightBgColor[2]);
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $this->Cell(0, 10, 'Personal Information', 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        
        // Reset fill color for the data rows
        $this->SetFillColor(255, 255, 255);
        
        // Create alternating row colors for better readability
        $altRow = true;
        
        // Personal Information fields with alternating row colors
        $rowFields = [
            ['Full Name:', ($studentData['first_name'] ?? '') . ' ' . ($studentData['last_name'] ?? '')],
            ['Date of Birth:', $studentData['date_of_birth'] ?? 'N/A'],
            ['Gender:', $studentData['gender'] ?? 'N/A'],
            ['Nationality:', $studentData['nationality'] ?? 'N/A'],
            ['State:', $studentData['state'] ?? 'N/A'],
            ['Email:', $studentData['email'] ?? 'N/A'],
        ];
        
        foreach ($rowFields as $field) {
            $this->SetFillColor($altRow ? 255 : 245, $altRow ? 255 : 245, $altRow ? 255 : 245);
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(60, 8, $field[0], 1, 0, 'L', true);
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 8, $field[1], 1, 1, 'L', true);
            $altRow = !$altRow;
        }
        
        // Contact Address which might be multiline
        $this->SetFillColor($altRow ? 255 : 245, $altRow ? 255 : 245, $altRow ? 255 : 245);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(60, 8, 'Contact Address:', 1, 0, 'L', true);
        $this->SetFont('Arial', '', 10);
        
        $address = $studentData['contact_address'] ?? 'N/A';
        if (strlen($address) > 60) {
            $this->Cell(0, 8, '', 1, 1, 'L', true);
            $this->SetX(70);
            $this->MultiCell(0, 8, $address, 1, 'L', true);
        } else {
            $this->Cell(0, 8, $address, 1, 1, 'L', true);
        }
        
        $this->Ln(5);
        
        // Add parents information if available
        if (!empty($studentData['father_s_name']) || !empty($studentData['mother_s_name'])) {
            $this->AddPage();
            
            // Parents/Guardian section header
            $this->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Arial', 'B', 14);
            $this->Cell(0, 10, 'PARENT/GUARDIAN INFORMATION', 1, 1, 'L', true);
            $this->SetTextColor(0, 0, 0);
            $this->Ln(5);
            
            // Father's Information with styled subheading
            if (!empty($studentData['father_s_name'])) {
                $this->SetFillColor(0, 102, 204, 0.2); // Light blue background
                $this->SetFont('Arial', 'B', 12);
                $this->SetTextColor(0, 51, 153); // Dark blue text
                $this->Cell(0, 10, 'Father\'s Information', 0, 1, 'L', true);
                $this->SetTextColor(0, 0, 0);
                
                // Reset row alternation
                $altRow = true;
                
                // Father's information fields
                $fatherFields = [
                    ['Name:', $studentData['father_s_name'] ?? 'N/A'],
                    ['Occupation:', $studentData['father_s_occupation'] ?? 'N/A'],
                    ['Contact Number:', $studentData['father_s_contact_phone_number_s_'] ?? 'N/A'],
                ];
                
                foreach ($fatherFields as $field) {
                    $this->SetFillColor($altRow ? 255 : 245, $altRow ? 255 : 245, $altRow ? 255 : 245);
                    $this->SetFont('Arial', 'B', 10);
                    $this->Cell(60, 8, $field[0], 1, 0, 'L', true);
                    $this->SetFont('Arial', '', 10);
                    $this->Cell(0, 8, $field[1], 1, 1, 'L', true);
                    $altRow = !$altRow;
                }
                
                // Office Address (potentially multiline)
                $this->SetFillColor($altRow ? 255 : 245, $altRow ? 255 : 245, $altRow ? 255 : 245);
                $this->SetFont('Arial', 'B', 10);
                $this->Cell(60, 8, 'Office Address:', 1, 0, 'L', true);
                $this->SetFont('Arial', '', 10);
                
                $fatherOfficeAddress = $studentData['father_s_office_address'] ?? 'N/A';
                if (strlen($fatherOfficeAddress) > 60) {
                    $this->Cell(0, 8, '', 1, 1, 'L', true);
                    $this->SetX(70);
                    $this->MultiCell(0, 8, $fatherOfficeAddress, 1, 'L', true);
                } else {
                    $this->Cell(0, 8, $fatherOfficeAddress, 1, 1, 'L', true);
                }
                
                $this->Ln(5);
            }
            
            // Mother's Information with styled subheading
            if (!empty($studentData['mother_s_name'])) {
                $this->SetFillColor(255, 200, 200, 0.2); // Light pink background
                $this->SetFont('Arial', 'B', 12);
                $this->SetTextColor(204, 0, 102); // Pink/purple text
                $this->Cell(0, 10, 'Mother\'s Information', 0, 1, 'L', true);
                $this->SetTextColor(0, 0, 0);
                
                // Reset row alternation
                $altRow = true;
                
                // Mother's information fields
                $motherFields = [
                    ['Name:', $studentData['mother_s_name'] ?? 'N/A'],
                    ['Occupation:', $studentData['mother_s_occupation'] ?? 'N/A'],
                    ['Contact Number:', $studentData['mother_s_contact_phone_number_s_'] ?? 'N/A'],
                ];
                
                foreach ($motherFields as $field) {
                    $this->SetFillColor($altRow ? 255 : 245, $altRow ? 255 : 245, $altRow ? 255 : 245);
                    $this->SetFont('Arial', 'B', 10);
                    $this->Cell(60, 8, $field[0], 1, 0, 'L', true);
                    $this->SetFont('Arial', '', 10);
                    $this->Cell(0, 8, $field[1], 1, 1, 'L', true);
                    $altRow = !$altRow;
                }
                
                // Office Address (potentially multiline)
                $this->SetFillColor($altRow ? 255 : 245, $altRow ? 255 : 245, $altRow ? 255 : 245);
                $this->SetFont('Arial', 'B', 10);
                $this->Cell(60, 8, 'Office Address:', 1, 0, 'L', true);
                $this->SetFont('Arial', '', 10);
                
                $motherOfficeAddress = $studentData['mother_s_office_address'] ?? 'N/A';
                if (strlen($motherOfficeAddress) > 60) {
                    $this->Cell(0, 8, '', 1, 1, 'L', true);
                    $this->SetX(70);
                    $this->MultiCell(0, 8, $motherOfficeAddress, 1, 'L', true);
                } else {
                    $this->Cell(0, 8, $motherOfficeAddress, 1, 1, 'L', true);
                }
                
                $this->Ln(5);
            }
            
            // Guardian Information with styled subheading
            if (!empty($studentData['guardian_name'])) {
                $this->SetFillColor(210, 230, 210, 0.2); // Light green background
                $this->SetFont('Arial', 'B', 12);
                $this->SetTextColor(0, 102, 0); // Dark green text
                $this->Cell(0, 10, 'Guardian Information', 0, 1, 'L', true);
                $this->SetTextColor(0, 0, 0);
                
                // Reset row alternation
                $altRow = true;
                
                // Guardian information fields
                $guardianFields = [
                    ['Name:', $studentData['guardian_name'] ?? 'N/A'],
                    ['Occupation:', $studentData['guardian_occupation'] ?? 'N/A'],
                    ['Contact Number:', $studentData['guardian_contact_phone_number'] ?? 'N/A'],
                    ['Child Lives With:', $studentData['child_lives_with'] ?? 'N/A'],
                ];
                
                foreach ($guardianFields as $field) {
                    $this->SetFillColor($altRow ? 255 : 245, $altRow ? 255 : 245, $altRow ? 255 : 245);
                    $this->SetFont('Arial', 'B', 10);
                    $this->Cell(60, 8, $field[0], 1, 0, 'L', true);
                    $this->SetFont('Arial', '', 10);
                    $this->Cell(0, 8, $field[1], 1, 1, 'L', true);
                    $altRow = !$altRow;
                }
                
                // Office Address (potentially multiline)
                $this->SetFillColor($altRow ? 255 : 245, $altRow ? 255 : 245, $altRow ? 255 : 245);
                $this->SetFont('Arial', 'B', 10);
                $this->Cell(60, 8, 'Office Address:', 1, 0, 'L', true);
                $this->SetFont('Arial', '', 10);
                
                $guardianOfficeAddress = $studentData['guardian_office_address'] ?? 'N/A';
                if (strlen($guardianOfficeAddress) > 60) {
                    $this->Cell(0, 8, '', 1, 1, 'L', true);
                    $this->SetX(70);
                    $this->MultiCell(0, 8, $guardianOfficeAddress, 1, 'L', true);
                } else {
                    $this->Cell(0, 8, $guardianOfficeAddress, 1, 1, 'L', true);
                }
                
                $this->Ln(5);
            }
        }
        
        // Medical Information with colored header
        if (!empty($studentData['blood_group']) || !empty($studentData['genotype']) || !empty($studentData['allergies'])) {
            $this->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Arial', 'B', 14);
            $this->Cell(0, 10, 'MEDICAL INFORMATION', 1, 1, 'L', true);
            $this->SetTextColor(0, 0, 0);
            $this->Ln(5);
            
            // Reset row alternation
            $altRow = true;
            
            // Medical information fields with alternating row colors
            $medicalFields = [
                ['Blood Group:', $studentData['blood_group'] ?? 'N/A'],
                ['Genotype:', $studentData['genotype'] ?? 'N/A'],
            ];
            
            foreach ($medicalFields as $field) {
                $this->SetFillColor($altRow ? 255 : 245, $altRow ? 255 : 245, $altRow ? 255 : 245);
                $this->SetFont('Arial', 'B', 10);
                $this->Cell(60, 8, $field[0], 1, 0, 'L', true);
                $this->SetFont('Arial', '', 10);
                $this->Cell(0, 8, $field[1], 1, 1, 'L', true);
                $altRow = !$altRow;
            }
            
            // Allergies (potentially multiline)
            $this->SetFillColor($altRow ? 255 : 245, $altRow ? 255 : 245, $altRow ? 255 : 245);
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(60, 8, 'Allergies:', 1, 0, 'L', true);
            $this->SetFont('Arial', '', 10);
            
            $allergies = $studentData['allergies'] ?? 'N/A';
            if (strlen($allergies) > 60) {
                $this->Cell(0, 8, '', 1, 1, 'L', true);
                $this->SetX(70);
                $this->MultiCell(0, 8, $allergies, 1, 'L', true);
            } else {
                $this->Cell(0, 8, $allergies, 1, 1, 'L', true);
            }
            
            $this->Ln(5);
        }
        
        // Include any other categorized fields
        if (!empty($studentData['categorized_fields']) && !empty($studentData['categorized_fields']['other'])) {
            $this->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Arial', 'B', 14);
            $this->Cell(0, 10, 'ADDITIONAL INFORMATION', 1, 1, 'L', true);
            $this->SetTextColor(0, 0, 0);
            $this->Ln(5);
            
            // Reset row alternation
            $altRow = true;
            
            $this->SetFont('Arial', '', 10);
            foreach ($studentData['categorized_fields']['other'] as $field) {
                $this->SetFillColor($altRow ? 255 : 245, $altRow ? 255 : 245, $altRow ? 255 : 245);
                $this->SetFont('Arial', 'B', 10);
                $this->Cell(60, 8, $field['label'] . ':', 1, 0, 'L', true);
                $this->SetFont('Arial', '', 10);
                $this->Cell(0, 8, $field['value'] ?? 'N/A', 1, 1, 'L', true);
                $altRow = !$altRow;
            }
            
            $this->Ln(5);
        }
        
        // Payment History with colored header and styled table
        if (!empty($studentData['payments'])) {
            $this->AddPage();
            $this->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Arial', 'B', 14);
            $this->Cell(0, 10, 'PAYMENT HISTORY', 1, 1, 'L', true);
            $this->SetTextColor(0, 0, 0);
            $this->Ln(5);
            
            // Column headers with colored background
            $this->SetFillColor(100, 100, 100);
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(40, 10, 'Date', 1, 0, 'C', true);
            $this->Cell(40, 10, 'Type', 1, 0, 'C', true);
            $this->Cell(40, 10, 'Amount', 1, 0, 'C', true);
            $this->Cell(40, 10, 'Method', 1, 0, 'C', true);
            $this->Cell(30, 10, 'Status', 1, 1, 'C', true);
            
            // Reset text color for data
            $this->SetTextColor(0, 0, 0);
            
            // Data rows with alternating background
            $altRow = true;
            $this->SetFont('Arial', '', 9);
            
            foreach ($studentData['payments'] as $payment) {
                $this->SetFillColor($altRow ? 255 : 245, $altRow ? 255 : 245, $altRow ? 255 : 245);
                
                $this->Cell(40, 8, date('Y-m-d', strtotime($payment['payment_date'])), 1, 0, 'C', true);
                $this->Cell(40, 8, ucfirst((string)$payment['payment_type']), 1, 0, 'L', true);
                $this->Cell(40, 8, '₦' . number_format($payment['amount'], 2), 1, 0, 'R', true);
                $this->Cell(40, 8, ucfirst((string)$payment['payment_method']), 1, 0, 'C', true);
                
                // Color-coded status
                $status = strtolower((string)$payment['status']);
                if ($status == 'completed' || $status == 'success') {
                    $this->SetTextColor($accentColor[0], $accentColor[1], $accentColor[2]); // Green
                } elseif ($status == 'pending') {
                    $this->SetTextColor(255, 153, 0); // Orange
                } elseif ($status == 'failed') {
                    $this->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]); // Red
                }
                
                $this->Cell(30, 8, ucfirst($status), 1, 1, 'C', true);
                $this->SetTextColor(0, 0, 0); // Reset text color
                $altRow = !$altRow;
            }
            
            // Payment summary if there are multiple payments
            if (count($studentData['payments']) > 1) {
                $this->Ln(5);
                $this->SetFont('Arial', 'B', 10);
                $this->Cell(0, 10, 'Payment Summary', 0, 1, 'L');
                
                // Calculate total
                $total = 0;
                foreach ($studentData['payments'] as $payment) {
                    if (strtolower((string)$payment['status']) == 'completed' || 
                        strtolower((string)$payment['status']) == 'success') {
                        $total += $payment['amount'];
                    }
                }
                
                // Show summary with colored background
                $this->SetFillColor(245, 245, 245);
                $this->SetFont('Arial', 'B', 10);
                $this->Cell(120, 8, 'Total Payments Completed:', 1, 0, 'R', true);
                $this->SetTextColor($accentColor[0], $accentColor[1], $accentColor[2]);
                $this->Cell(70, 8, '₦' . number_format($total, 2), 1, 1, 'R', true);
                $this->SetTextColor(0, 0, 0);
            }
            
            $this->Ln(5);
        }
        
        // Exam Results with colored header and styled table
        if (!empty($studentData['exam_results'])) {
            $this->AddPage();
            $this->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Arial', 'B', 14);
            $this->Cell(0, 10, 'EXAMINATION RESULTS', 1, 1, 'L', true);
            $this->SetTextColor(0, 0, 0);
            $this->Ln(5);
            
            // Column headers with colored background
            $this->SetFillColor(100, 100, 100);
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(30, 10, 'Date', 1, 0, 'C', true);
            $this->Cell(70, 10, 'Subject', 1, 0, 'C', true);
            $this->Cell(30, 10, 'Score', 1, 0, 'C', true);
            $this->Cell(30, 10, 'Grade', 1, 0, 'C', true);
            $this->Cell(30, 10, 'Remarks', 1, 1, 'C', true);
            
            // Reset text color for data
            $this->SetTextColor(0, 0, 0);
            
            // Data rows with alternating background
            $altRow = true;
            $this->SetFont('Arial', '', 9);
            
            foreach ($studentData['exam_results'] as $result) {
                $this->SetFillColor($altRow ? 255 : 245, $altRow ? 255 : 245, $altRow ? 255 : 245);
                
                $this->Cell(30, 8, date('Y-m-d', strtotime($result['exam_date'])), 1, 0, 'C', true);
                $this->Cell(70, 8, $result['subject'], 1, 0, 'L', true);
                
                // Color code scores based on performance
                $score = intval($result['score']);
                if ($score >= 70) {
                    $this->SetTextColor($accentColor[0], $accentColor[1], $accentColor[2]); // Green for excellent
                } elseif ($score >= 50) {
                    $this->SetTextColor(0, 0, 255); // Blue for average
                } else {
                    $this->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]); // Red for poor
                }
                
                $this->Cell(30, 8, $result['score'], 1, 0, 'C', true);
                $this->Cell(30, 8, $result['grade'], 1, 0, 'C', true);
                $this->SetTextColor(0, 0, 0); // Reset text color
                $this->Cell(30, 8, $result['remarks'], 1, 1, 'L', true);
                $altRow = !$altRow;
            }
            
            // Add a performance summary if there are multiple results
            if (count($studentData['exam_results']) > 1) {
                $this->Ln(5);
                $this->SetFont('Arial', 'B', 10);
                $this->Cell(0, 10, 'Performance Summary', 0, 1, 'L');
                
                // Calculate average score
                $totalScore = 0;
                $count = 0;
                foreach ($studentData['exam_results'] as $result) {
                    $totalScore += intval($result['score']);
                    $count++;
                }
                $averageScore = $count > 0 ? $totalScore / $count : 0;
                
                // Show summary with colored background
                $this->SetFillColor(245, 245, 245);
                $this->SetFont('Arial', 'B', 10);
                $this->Cell(120, 8, 'Average Score:', 1, 0, 'R', true);
                
                // Color code average score
                if ($averageScore >= 70) {
                    $this->SetTextColor($accentColor[0], $accentColor[1], $accentColor[2]); // Green for excellent
                } elseif ($averageScore >= 50) {
                    $this->SetTextColor(0, 0, 255); // Blue for average
                } else {
                    $this->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]); // Red for poor
                }
                
                $this->Cell(70, 8, number_format($averageScore, 1) . '%', 1, 1, 'R', true);
                $this->SetTextColor(0, 0, 0);
            }
        }
        
        // Add footer with document generation info
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128); // Gray text
        $this->Cell(0, 10, 'Document generated on ' . date('F j, Y, g:i a') . ' by ' . SCHOOL_NAME . ' Admin Portal', 0, 0, 'C');
    }
}

/**
 * Generate a registration number based on the configured format in registration_number_settings
 * 
 * @param string $registrationType The type of registration ('kiddies' or 'college')
 * @return string The generated registration number
 */
function generateRegistrationNumber($registrationType) {
    $db = Database::getInstance();
    $mysqli = $db->getConnection();
    
    // Load settings
    $settings = [];
    $query = "SELECT setting_name, setting_value FROM registration_number_settings";
    $result = $mysqli->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_name']] = $row['setting_value'];
        }
    }
    
    // Default settings if not found
    if (empty($settings)) {
        $settings = [
            'year_format' => 'Y',
            'kiddies_prefix' => 'KID',
            'college_prefix' => 'COL',
            'number_padding' => 4,
            'separator' => '',
            'custom_prefix' => ''
        ];
    }
    
    // Generate year portion
    $year = '';
    if ($settings['year_format'] === 'Y') {
        $year = date('Y');
    } elseif ($settings['year_format'] === 'y') {
        $year = date('y');
    } elseif ($settings['year_format'] === 'manual' && isset($settings['manual_year'])) {
        $year = $settings['manual_year'];
    }
    
    // Select prefix based on registration type
    $type_prefix = ($registrationType === 'kiddies') ? $settings['kiddies_prefix'] : $settings['college_prefix'];
    $next_number_key = ($registrationType === 'kiddies') ? 'next_kid_number' : 'next_col_number';
    
    // Get the separator
    $separator = $settings['separator'];
    
    // Add custom prefix if exists
    $custom_prefix = empty($settings['custom_prefix']) ? '' : $settings['custom_prefix'] . $separator;
    
    // Generate the search pattern for finding last registration number
    $type_search = ($registrationType === 'kiddies') ? $settings['kiddies_prefix'] : $settings['college_prefix'];
    
    // Get the last registration number
    $query = "SELECT registration_number FROM students WHERE registration_number LIKE '%$type_search%' ORDER BY id DESC LIMIT 1";
    $result = $mysqli->query($query);
    
    if ($result && $result->num_rows > 0) {
        $last_reg = $result->fetch_assoc()['registration_number'];
        // Extract the number portion
        preg_match('/(\d+)$/', $last_reg, $matches);
        if (isset($matches[1])) {
            $last_number = intval($matches[1]);
            $new_number = $last_number + 1;
        } else {
            // If pattern not found, use the stored next number or default to 1
            $new_number = isset($settings[$next_number_key]) ? intval($settings[$next_number_key]) : 1;
        }
    } else {
        // If no previous registration, use the stored next number or default to 1
        $new_number = isset($settings[$next_number_key]) ? intval($settings[$next_number_key]) : 1;
    }
    
    // Pad the number portion
    $padded_number = str_pad($new_number, intval($settings['number_padding']), '0', STR_PAD_LEFT);
    
    // Build the registration number
    $parts = [];
    if (!empty($custom_prefix)) $parts[] = rtrim($custom_prefix, $separator);
    if (!empty($year)) $parts[] = $year;
    $parts[] = $type_prefix;
    $parts[] = $padded_number;
    
    // Join with separator
    $registration_number = empty($separator) 
        ? implode('', $parts) 
        : implode($separator, $parts);
    
    // Update the next number in settings
    $new_next_number = $new_number + 1;
    $query = "UPDATE registration_number_settings SET setting_value = ? WHERE setting_name = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('ss', $new_next_number, $next_number_key);
    $stmt->execute();
    
    return $registration_number;
}

/**
 * Format payment type for display
 * 
 * @param string $paymentType The raw payment type from database
 * @return string Formatted payment type for display
 */
function formatPaymentType($paymentType) {
    // Handle null or empty payment type
    if (empty($paymentType)) {
        return 'Unknown';
    }
    
    $types = [
        'tuition_fee' => 'Tuition Fee',
        'registration_fee' => 'Registration Fee',
        'development_levy' => 'Development Levy',
        'book_fee' => 'Book Fee',
        'uniform_fee' => 'Uniform Fee',
        'exam_fee' => 'Examination Fee',
        'transportation_fee' => 'Transportation Fee',
        'other' => 'Other'
    ];
    
    return $types[$paymentType] ?? ucfirst(str_replace('_', ' ', $paymentType));
}

/**
 * Format payment status for display with appropriate styling
 * 
 * @param string $status The raw payment status from database
 * @param bool $withBadge Whether to include HTML badge styling
 * @return string Formatted payment status for display
 */
function formatPaymentStatus($status, $withBadge = true) {
    // Handle null or empty status
    if (empty($status)) {
        return $withBadge ? '<span class="badge badge-secondary">Unknown</span>' : 'Unknown';
    }
    
    $badgeClass = '';
    
    switch ($status) {
        case 'completed':
        case 'success':
            $formattedStatus = 'Completed';
            $badgeClass = 'badge-success';
            break;
        case 'pending':
            $formattedStatus = 'Pending';
            $badgeClass = 'badge-warning';
            break;
        case 'failed':
            $formattedStatus = 'Failed';
            $badgeClass = 'badge-danger';
            break;
        default:
            $formattedStatus = ucfirst($status);
            $badgeClass = 'badge-secondary';
    }
    
    if ($withBadge) {
        return '<span class="badge ' . $badgeClass . '">' . $formattedStatus . '</span>';
    }
    
    return $formattedStatus;
}

/**
 * Checks if a column exists in a table
 * 
 * @param mysqli $conn Database connection
 * @param string $table Table name
 * @param string $column Column name
 * @return bool True if column exists, false otherwise
 */
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result->num_rows > 0;
}

/**
 * Ensures both admission_number and registration_number columns exist in students table
 * 
 * @param mysqli $conn Database connection
 * @return void
 */
function ensureStudentNumberColumns($conn) {
    $hasAdmissionNumber = columnExists($conn, 'students', 'admission_number');
    $hasRegistrationNumber = columnExists($conn, 'students', 'registration_number');
    
    if ($hasAdmissionNumber && !$hasRegistrationNumber) {
        // Add registration_number as a copy of admission_number
        $conn->query("ALTER TABLE `students` ADD `registration_number` VARCHAR(50) NULL AFTER `admission_number`");
        $conn->query("UPDATE `students` SET `registration_number` = `admission_number`");
    } 
    else if (!$hasAdmissionNumber && $hasRegistrationNumber) {
        // Add admission_number as a copy of registration_number
        $conn->query("ALTER TABLE `students` ADD `admission_number` VARCHAR(50) NULL AFTER `id`");
        $conn->query("UPDATE `students` SET `admission_number` = `registration_number`");
    }
}

class Utils {
    public static function getBasePath() {
        return rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/';
    }

    public static function getBaseUrl() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        return $protocol . $_SERVER['HTTP_HOST'] . '/';
    }

    public static function fileExists($path) {
        // Convert relative path to absolute path within document root
        $absolutePath = self::getBasePath() . ltrim($path, '/');
        
        // Normalize the path to remove any ../ references
        $normalizedPath = realpath($absolutePath);
        
        // Check if the normalized path is within allowed directories
        if ($normalizedPath === false) {
            error_log("Invalid path attempted: " . $absolutePath);
            return false;
        }
        
        return file_exists($normalizedPath);
    }

    public static function getLogoPath() {
        $possiblePaths = [
            'assets/img/logo.png',
            'assets/images/logo.png',
            'backends/assets/images/logo.png',
            'images/logo.png'
        ];

        foreach ($possiblePaths as $path) {
            if (self::fileExists($path)) {
                return self::getBasePath() . $path;
            }
        }

        // Return a default path if no logo is found
        error_log("Logo not found in any of the expected locations");
        return self::getBasePath() . 'assets/images/default-logo.png';
    }

    public static function sanitizePath($path) {
        // Remove any parent directory references
        $path = str_replace('..', '', $path);
        // Remove any double slashes
        $path = preg_replace('#/+#', '/', $path);
        // Remove leading slash
        $path = ltrim($path, '/');
        return $path;
    }
}
