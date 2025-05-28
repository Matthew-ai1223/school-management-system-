<?php
require_once 'config/config.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';
require 'vendor/composer/autoload_static.php'; // You'll need to install TCPDF via composer

session_start();


$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}
$attempt_id = filter_input(INPUT_GET, 'attempt', FILTER_VALIDATE_INT);
if (!$attempt_id) {
    header('Location: dashboard.php');
    exit();
}

$db = Database::getInstance()->getConnection();

// Get certificate details
$query = "SELECT c.*, u.name as student_name, e.title as exam_title
          FROM certificates c
          JOIN users u ON c.user_id = u.id
          JOIN exams e ON c.exam_id = e.id
          WHERE c.user_id = :user_id AND e.id = (
              SELECT exam_id FROM exam_attempts WHERE id = :attempt_id
          )";
$stmt = $db->prepare($query);
$stmt->execute([
    ':user_id' => $_SESSION['user_id'],
    ':attempt_id' => $attempt_id
]);
$cert = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cert) {
    header('Location: dashboard.php');
    exit();
}

// Generate PDF certificate
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(SITE_NAME);
$pdf->SetAuthor(SITE_NAME);
$pdf->SetTitle('Certificate of Completion');

$pdf->AddPage('L', 'A4');
$pdf->SetFont('helvetica', '', 12);

// Add certificate content
$html = <<<EOD
<h1 style="text-align:center;">Certificate of Completion</h1>
<h2 style="text-align:center;">This is to certify that</h2>
<h2 style="text-align:center;">{$cert['student_name']}</h2>
<p style="text-align:center;">has successfully completed</p>
<h3 style="text-align:center;">{$cert['exam_title']}</h3>
<p style="text-align:center;">Certificate Number: {$cert['certificate_number']}</p>
<p style="text-align:center;">Issue Date: {$cert['issue_date']}</p>
EOD;

$pdf->writeHTML($html, true, false, true, false, '');

// Output PDF
$pdf->Output('certificate.pdf', 'D'); 