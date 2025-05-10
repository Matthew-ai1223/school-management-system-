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
