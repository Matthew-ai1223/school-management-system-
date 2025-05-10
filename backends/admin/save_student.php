<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Get form data
$application_type = $_POST['application_type'] ?? '';
$registration_number = $_POST['registration_number'] ?? '';
$first_name = $_POST['first_name'] ?? '';
$last_name = $_POST['last_name'] ?? '';
$date_of_birth = $_POST['date_of_birth'] ?? '';
$gender = $_POST['gender'] ?? '';
$address = $_POST['address'] ?? '';
$parent_name = $_POST['parent_name'] ?? '';
$parent_phone = $_POST['parent_phone'] ?? '';
$parent_email = $_POST['parent_email'] ?? '';

// Validate required fields
$required_fields = [
    'application_type' => 'Application Type',
    'registration_number' => 'Registration Number',
    'first_name' => 'First Name',
    'last_name' => 'Last Name',
    'date_of_birth' => 'Date of Birth',
    'gender' => 'Gender',
    'address' => 'Address',
    'parent_name' => 'Parent Name',
    'parent_phone' => 'Parent Phone'
];

$errors = [];
foreach ($required_fields as $field => $label) {
    if (empty($_POST[$field])) {
        $errors[] = "$label is required";
    }
}

// Check if registration number already exists
if (!empty($registration_number)) {
    $result = $db->query("SELECT id FROM students WHERE registration_number = '" . $db->escape($registration_number) . "'");
    if ($result && $result->num_rows > 0) {
        $errors[] = "Registration number already exists";
    }
}

if (!empty($errors)) {
    echo json_encode([
        'success' => false,
        'message' => implode(', ', $errors)
    ]);
    exit;
}

// Prepare data for insertion
$data = [
    'application_type' => $db->escape($application_type),
    'registration_number' => $db->escape($registration_number),
    'first_name' => $db->escape($first_name),
    'last_name' => $db->escape($last_name),
    'date_of_birth' => $db->escape($date_of_birth),
    'gender' => $db->escape($gender),
    'address' => $db->escape($address),
    'parent_name' => $db->escape($parent_name),
    'parent_phone' => $db->escape($parent_phone),
    'parent_email' => $db->escape($parent_email),
    'status' => 'pending'
];

// Build query
$fields = implode(', ', array_keys($data));
$values = "'" . implode("', '", $data) . "'";
$query = "INSERT INTO students ($fields) VALUES ($values)";

// Execute query
if ($db->query($query)) {
    echo json_encode([
        'success' => true,
        'message' => 'Student added successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error adding student: ' . $db->getConnection()->error
    ]);
} 