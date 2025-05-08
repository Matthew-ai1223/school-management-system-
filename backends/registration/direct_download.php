<?php
// Direct PDF Download Script
// This is a simplified script that directly generates a PDF without session or token handling

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once '../database.php';
require_once '../config.php';
require_once '../utils.php';

// Include FPDF directly
require_once '../../fpdf_temp/fpdf.php';

// Sample student data for testing
$studentData = array(
    'first_name' => 'Test',
    'last_name' => 'Student',
    'email' => 'test@example.com',
    'date_of_birth' => date('Y-m-d'),
    'gender' => 'Male',
    'phone' => '1234567890',
    'address' => '123 Test Street',
    'class_name' => 'Test Class',
    'previous_school' => 'Previous School',
    'parent_name' => 'Parent Name',
    'parent_phone' => '0987654321',
    'parent_email' => 'parent@example.com',
    'parent_address' => 'Parent Address',
    'username' => 'test.student',
    'password' => 'password123',
    'registration_number' => 'REG12345'
);

// Use session data if available
session_start();
if (isset($_SESSION['student_data']) && !empty($_SESSION['student_data'])) {
    $studentData = $_SESSION['student_data'];
    error_log('Using student data from session');
} else {
    error_log('Using sample student data (session data not available)');
}

try {
    // Create PDF instance
    $pdf = new FPDF('P', 'mm', 'A4');
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('Arial', 'B', 16);
    
    // Title
    $pdf->Cell(0, 10, APP_NAME, 0, 1, 'C');
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'STUDENT REGISTRATION RECEIPT', 0, 1, 'C');
    
    // Date
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 10, 'Date: ' . date('F j, Y'), 0, 1, 'R');
    
    // Add a line
    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
    $pdf->Ln(5);
    
    // Registration Number
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(0, 10, 'ADMISSION/REGISTRATION NUMBER: ' . $studentData['registration_number'], 1, 1, 'C', true);
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
    
    // Other details
    $pdf->Cell(60, 8, 'Date of Birth:', 1, 0, 'L');
    $pdf->Cell(130, 8, $studentData['date_of_birth'], 1, 1, 'L', true);
    
    $pdf->Cell(60, 8, 'Gender:', 1, 0, 'L');
    $pdf->Cell(130, 8, $studentData['gender'], 1, 1, 'L');
    
    // Login information
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'LOGIN CREDENTIALS', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 10);
    
    $pdf->Cell(60, 8, 'Username:', 1, 0, 'L');
    $pdf->Cell(130, 8, $studentData['username'], 1, 1, 'L', true);
    
    $pdf->Cell(60, 8, 'Password:', 1, 0, 'L');
    $pdf->Cell(130, 8, $studentData['password'], 1, 1, 'L');
    
    // Footer
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 6, 'Please keep this receipt for your records.', 0, 1, 'C');
    
    // Output PDF
    $pdf->Output('D', 'registration_receipt.pdf');
    
} catch (Exception $e) {
    // Log error
    error_log('Direct PDF Generation Error: ' . $e->getMessage());
    
    // Show error in browser
    echo '<h1>Error Generating PDF</h1>';
    echo '<p>An error occurred while generating the PDF receipt: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p>Please try again or contact support.</p>';
} 