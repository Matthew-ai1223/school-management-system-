<?php
require_once '../../../backends/config.php';
require_once '../../../backends/database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

// Drop existing tables
$conn->query("DROP TABLE IF EXISTS applications");
$conn->query("DROP TABLE IF EXISTS form_fields");

// Recreate form_fields table with correct structure
$conn->query("CREATE TABLE form_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    field_label VARCHAR(255) NOT NULL,
    field_type VARCHAR(50) NOT NULL,
    field_order INT DEFAULT 0,
    required BOOLEAN DEFAULT FALSE,
    options TEXT,
    application_type ENUM('kiddies', 'college') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Recreate applications table with correct structure
$conn->query("CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    applicant_data JSON NOT NULL,
    application_type ENUM('kiddies', 'college') NOT NULL,
    submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(50) DEFAULT 'pending',
    reviewed_by INT,
    review_date TIMESTAMP NULL,
    comments TEXT,
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
)");

// Insert default fields for both kiddies and college applications
$default_fields = [
    // Kiddies default fields
    ['First Name', 'text', 1, true, '', 'kiddies'],
    ['Last Name', 'text', 2, true, '', 'kiddies'],
    ['Date of Birth', 'date', 3, true, '', 'kiddies'],
    ['Gender', 'select', 4, true, 'Male,Female', 'kiddies'],
    ['Parent Name', 'text', 5, true, '', 'kiddies'],
    ['Parent Phone', 'text', 6, true, '', 'kiddies'],
    ['Parent Email', 'email', 7, false, '', 'kiddies'],
    ['Home Address', 'textarea', 8, true, '', 'kiddies'],
    ['Blood Group', 'select', 9, true, 'A+,A-,B+,B-,O+,O-,AB+,AB-', 'kiddies'],
    ['Previous School (if any)', 'text', 10, false, '', 'kiddies'],
    ['Medical Conditions', 'textarea', 11, false, '', 'kiddies'],
    ['Emergency Contact', 'text', 12, true, '', 'kiddies'],
    
    // College default fields
    ['First Name', 'text', 1, true, '', 'college'],
    ['Last Name', 'text', 2, true, '', 'college'],
    ['Date of Birth', 'date', 3, true, '', 'college'],
    ['Gender', 'select', 4, true, 'Male,Female', 'college'],
    ['Previous School', 'text', 5, true, '', 'college'],
    ['Parent Name', 'text', 6, true, '', 'college'],
    ['Parent Phone', 'text', 7, true, '', 'college'],
    ['Parent Email', 'email', 8, false, '', 'college'],
    ['Home Address', 'textarea', 9, true, '', 'college'],
    ['Last Class Completed', 'text', 10, true, '', 'college'],
    ['Academic Records', 'file', 11, true, '', 'college'],
    ['Blood Group', 'select', 12, true, 'A+,A-,B+,B-,O+,O-,AB+,AB-', 'college'],
    ['Medical Conditions', 'textarea', 13, false, '', 'college'],
    ['Emergency Contact', 'text', 14, true, '', 'college'],
    ['Extra-Curricular Activities', 'textarea', 15, false, '', 'college']
];

// Prepare and execute insert statement for default fields
$stmt = $conn->prepare("INSERT INTO form_fields (field_label, field_type, field_order, required, options, application_type) VALUES (?, ?, ?, ?, ?, ?)");

foreach ($default_fields as $field) {
    $required = $field[2] ? 1 : 0;
    $stmt->bind_param("ssisss", $field[0], $field[1], $field[2], $required, $field[4], $field[5]);
    $stmt->execute();
}

echo "Tables have been recreated successfully with default fields!";
?> 