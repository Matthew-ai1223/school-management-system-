<?php
require_once 'backends/database.php';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Add field_group column if it doesn't exist
try {
    $conn->query('ALTER TABLE form_fields ADD COLUMN field_group VARCHAR(255) DEFAULT NULL');
    echo "Added field_group column\n";
} catch (Exception $e) {
    echo "Note: " . $e->getMessage() . "\n";
}

// Prepare the insert statement
$sql = "INSERT INTO form_fields (field_label, field_type, field_order, required, options, field_group, application_type, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param('ssiisss', $field_label, $field_type, $field_order, $required, $options, $field_group, $app_type);

// Add Previous School field for kiddies
$field_label = 'Previous School';
$field_type = 'text';
$field_order = 20;
$required = 1;
$options = '';
$field_group = 'Academic Information';
$app_type = 'kiddies';

if ($stmt->execute()) {
    echo "Added Previous School field\n";
} else {
    echo "Error adding Previous School field: " . $stmt->error . "\n";
}

// Add Class Seeking Admission field for kiddies
$field_label = 'Class Seeking Admission Into';
$field_type = 'select';
$field_order = 21;
$required = 1;
$options = 'Nursery 1,Nursery 2,Primary 1,Primary 2,Primary 3,Primary 4,Primary 5,Primary 6';
$field_group = 'Academic Information';
$app_type = 'kiddies';

if ($stmt->execute()) {
    echo "Added Class Seeking Admission field\n";
} else {
    echo "Error adding Class Seeking Admission field: " . $stmt->error . "\n";
}

// Add Passport Photo field for kiddies
$field_label = 'Passport Photo';
$field_type = 'file';
$field_order = 5;
$required = 1;
$options = '';
$field_group = 'Personal Information';
$app_type = 'kiddies';

if ($stmt->execute()) {
    echo "Added Passport Photo field for kiddies\n";
} else {
    echo "Error adding Passport Photo field for kiddies: " . $stmt->error . "\n";
}

// Add Passport Photo field for college
$field_label = 'Passport Photo';
$field_type = 'file';
$field_order = 5;
$required = 1;
$options = '';
$field_group = 'Personal Information';
$app_type = 'college';

if ($stmt->execute()) {
    echo "Added Passport Photo field for college\n";
} else {
    echo "Error adding Passport Photo field for college: " . $stmt->error . "\n";
}

echo "Done!\n"; 