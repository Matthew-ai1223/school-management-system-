<?php
/**
 * Test PDF Receipt Generator
 * This file can be accessed directly to test PDF generation
 */

// Include FPDF library
require_once __DIR__ . '/../../../fpdf_temp/fpdf.php';

// Sample student data for testing
$studentData = array(
    'first_name' => 'John',
    'last_name' => 'Doe',
    'email' => 'john.doe@example.com',
    'date_of_birth' => '2000-01-01',
    'gender' => 'Male',
    'phone' => '1234567890',
    'address' => '123 Main St, City',
    'class_name' => 'JSS 1',
    'previous_school' => 'Primary School',
    'parent_name' => 'Jane Doe',
    'parent_phone' => '0987654321',
    'parent_email' => 'jane.doe@example.com',
    'parent_address' => '123 Main St, City',
    'username' => 'john.doe',
    'password' => 'password123',
    'registration_number' => 'REG12345'
);

try {
    // Create PDF instance - A4 portrait
    $pdf = new FPDF('P', 'mm', 'A4');
    
    // Add a page
    $pdf->AddPage();
    
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
    $pdf->Cell(130, 8, $studentData['class_name'], 1, 1, 'L', true);
    
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
    $pdf->Cell(0, 6, 'Please keep this receipt for your records.', 0, 1, 'C');
    $pdf->Cell(0, 6, 'The login credentials provided above will be needed to access your student account.', 0, 1, 'C');
    $pdf->Cell(0, 6, 'Welcome to ACE MODEL COLLEGE!', 0, 1, 'C');
    
    // Output PDF - D to force download, I to show in browser
    $pdf->Output('I', 'test_receipt.pdf');
    
} catch (Exception $e) {
    // Display error
    echo "<h1>Error Generating PDF</h1>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
} 