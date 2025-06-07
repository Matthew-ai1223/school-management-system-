<?php
require_once '../config.php';
require_once '../database.php';
require_once '../auth.php';
require_once 'fpdf/fpdf.php';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

if (isset($_GET['id'])) {
    try {
        $report_id = $conn->real_escape_string($_GET['id']);
        
        // Fetch report card
        $sql = "SELECT rc.*, 
                CONCAT(s.first_name, ' ', s.last_name) as student_name,
                s.registration_number,
                CONCAT(t.first_name, ' ', t.last_name) as teacher_name
                FROM report_cards rc
                LEFT JOIN students s ON rc.student_id = s.id
                LEFT JOIN teachers t ON rc.created_by = t.id
                WHERE rc.id = '$report_id'";
        
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $report_card = $result->fetch_assoc();

            // Fetch report details
            $sql = "SELECT rcd.*, rs.subject_name
                    FROM report_card_details rcd
                    LEFT JOIN report_subjects rs ON rcd.subject_id = rs.id
                    WHERE rcd.report_card_id = '$report_id'
                    ORDER BY rs.subject_name";
            
            $result = $conn->query($sql);
            $report_details = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $report_details[] = $row;
                }
            }

            // Create new PDF document
            $pdf = new FPDF();
            $pdf->AddPage();
            
            // Set document properties
            $pdf->SetTitle('Student Report Card');
            $pdf->SetAuthor('ACE COLLEGE');
            
            // Define colors
            $pdf->SetFillColor(41, 128, 185); // Blue header
            $pdf->SetTextColor(255, 255, 255); // White text for header
            $pdf->SetDrawColor(41, 128, 185); // Blue border
            
            // School Information Header - Reduced size
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(0, 8, $report_card['school_name'], 0, 1, 'C', true);
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 8, 'Student Report Card', 0, 1, 'C', true);
            $pdf->Ln(2);
            
            // Reset text color for content
            $pdf->SetTextColor(0, 0, 0);
            
            // Student Information Box - More compact
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 6, 'Student Information', 1, 1, 'C', true);
            
            // Create a box for student info
            $pdf->SetDrawColor(200, 200, 200);
            $pdf->SetFillColor(255, 255, 255);
            
            // Left column
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(35, 6, 'Student Name:', 0);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(55, 6, $report_card['student_name'] ?? 'N/A', 0);
            
            // Right column - Same line
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(35, 6, 'Academic Year:', 0);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 6, $report_card['academic_year'], 0, 1);

            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(35, 6, 'Registration No:', 0);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(55, 6, $report_card['registration_number'] ?? 'N/A', 0);
            
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(35, 6, 'Term:', 0);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 6, $report_card['term'], 0, 1);

            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(35, 6, 'Class:', 0);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(55, 6, $report_card['class'], 0);
            
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(35, 6, 'Date:', 0);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 6, date('F d, Y', strtotime($report_card['created_at'])), 0, 1);
            $pdf->Ln(2);

            // Academic Performance Table - More compact
            $pdf->SetFillColor(41, 128, 185);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 6, 'Academic Performance', 1, 1, 'C', true);
            
            // Table Header
            $pdf->SetFont('Arial', 'B', 9);
            $header = array('Subject', 'Test (30%)', 'Exam (70%)', 'Total', 'Grade', 'Remark');
            $w = array(55, 22, 22, 22, 22, 35);

            // Header
            for($i = 0; $i < count($header); $i++) {
                $pdf->Cell($w[$i], 5, $header[$i], 1, 0, 'C', true);
            }
            $pdf->Ln();

            // Table Data
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('Arial', '', 8);
            $fill = false;
            foreach($report_details as $detail) {
                $pdf->Cell($w[0], 5, $detail['subject_name'] ?? 'N/A', 1, 0, 'L', $fill);
                $pdf->Cell($w[1], 5, number_format($detail['test_score'], 1), 1, 0, 'C', $fill);
                $pdf->Cell($w[2], 5, number_format($detail['exam_score'], 1), 1, 0, 'C', $fill);
                $pdf->Cell($w[3], 5, number_format($detail['total_score'], 1), 1, 0, 'C', $fill);
                $pdf->Cell($w[4], 5, $detail['grade'] ?? 'N/A', 1, 0, 'C', $fill);
                $pdf->Cell($w[5], 5, $detail['remark'] ?? 'N/A', 1, 0, 'L', $fill);
                $pdf->Ln();
                $fill = !$fill;
            }

            // Summary Box - More compact
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 6, 'Performance Summary', 1, 1, 'C', true);
            
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(110, 5, 'Total Score:', 1);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 5, number_format($report_card['total_score'], 1), 1, 1);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(110, 5, 'Average Score:', 1);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 5, number_format($report_card['average_score'], 1), 1, 1);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(110, 5, 'Position in Class:', 1);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 5, $report_card['position_in_class'] . ' out of ' . $report_card['total_students'], 1, 1);
            $pdf->Ln(2);

            // Comments Section - More compact
            $pdf->SetFillColor(41, 128, 185);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 6, 'Comments', 1, 1, 'C', true);
            
            $pdf->SetTextColor(0, 0, 0);
            
            // Teacher's Comment Box
            $pdf->SetDrawColor(200, 200, 200);
            $pdf->SetFillColor(245, 245, 245);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(0, 5, 'Teacher\'s Comment', 1, 1, 'L', true);
            
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->MultiCell(0, 4, $report_card['teacher_comment'] ?? 'No comment', 1, 'L', true);
            $pdf->Ln(2);

            // Principal's Comment Box
            $pdf->SetDrawColor(200, 200, 200);
            $pdf->SetFillColor(245, 245, 245);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(0, 5, 'Principal\'s Comment', 1, 1, 'L', true);
            
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->MultiCell(0, 4, $report_card['principal_comment'] ?? 'No comment', 1, 'L', true);
            $pdf->Ln(2);

            // Signatures Section - More compact
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 6, 'Signatures', 1, 1, 'C', true);
            
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell(60, 5, 'Class Teacher\'s Signature', 0, 0, 'C');
            $pdf->Cell(60, 5, 'Principal\'s Signature', 0, 0, 'C');
            $pdf->Cell(60, 5, 'Parent\'s Signature', 0, 1, 'C');
            $pdf->Ln(2);

            $pdf->Cell(60, 5, $report_card['teacher_name'] ?? 'N/A', 0, 0, 'C');
            $pdf->Cell(60, 5, '', 0, 0, 'C');
            $pdf->Cell(60, 5, '', 0, 1, 'C');

            // Output the PDF
            $pdf->Output('D', 'Report_Card_' . $report_card['student_name'] . '.pdf');
            exit;
        }
    } catch(Exception $e) {
        die("Error generating PDF: " . $e->getMessage());
    }
} else {
    die("No report card ID provided.");
}
?> 