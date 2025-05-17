<?php
require_once 'config.php';
require_once 'fpdf_temp/fpdf.php';

class PDFGenerator extends FPDF {
    function Header() {
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 10, SCHOOL_NAME, 0, 1, 'C');
        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 10, SCHOOL_ADDRESS, 0, 1, 'C');
        $this->Cell(0, 10, 'Phone: ' . SCHOOL_PHONE . ' | Email: ' . SCHOOL_EMAIL, 0, 1, 'C');
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
