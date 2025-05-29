<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/Certificate.php';

session_start();

$auth = new Auth();

// if (!$auth->isLoggedIn() || !in_array($_SESSION['user_role'], ['admin', 'super_admin'])) {
//     header('Location: login.php');
//     exit();
// }

$certificate_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$certificate_id) {
    header('Location: students.php');
    exit();
}

$db = Database::getInstance()->getConnection();

// Get certificate details
$query = "SELECT c.*, u.name as student_name, e.title as exam_title
          FROM certificates c
          JOIN users u ON c.user_id = u.id
          JOIN exams e ON c.exam_id = e.id
          WHERE c.id = :certificate_id";
$stmt = $db->prepare($query);
$stmt->execute([':certificate_id' => $certificate_id]);
$cert = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cert) {
    header('Location: students.php');
    exit();
}

// Create certificate
$certificate = new Certificate(
    'Certificate of Completion',
    $cert['student_name'],
    $cert['exam_title'],
    $cert['certificate_number'],
    $cert['issue_date']
);

// Generate the certificate
$certificate->generateCertificate();

// Output the PDF
$filename = 'certificate_' . $cert['certificate_number'] . '.pdf';
$certificate->Output($filename, 'D'); 