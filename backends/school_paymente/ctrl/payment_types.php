<?php
require_once 'db_config.php';

class PaymentTypes {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
        // Create or alter table to ensure min_payment_amount column exists
        $this->initializeTable();
    }

    private function initializeTable() {
        // First, check if the column exists
        $check_column = "SHOW COLUMNS FROM school_payment_types LIKE 'min_payment_amount'";
        $result = $this->conn->query($check_column);
        
        if ($result->num_rows == 0) {
            // Add the column if it doesn't exist
            $alter_table = "ALTER TABLE school_payment_types 
                           ADD COLUMN min_payment_amount DECIMAL(10,2) NOT NULL 
                           AFTER amount";
            $this->conn->query($alter_table);
        }
    }

    public function addPaymentType($name, $amount, $description, $academic_term, $min_amount) {
        $sql = "INSERT INTO school_payment_types (name, amount, min_payment_amount, description, academic_term) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sddss", $name, $amount, $min_amount, $description, $academic_term);
        return $stmt->execute();
    }

    public function getPaymentTypes() {
        $sql = "SELECT * FROM school_payment_types WHERE is_active = 1";
        $result = $this->conn->query($sql);
        $types = [];
        
        while($row = $result->fetch_assoc()) {
            $types[] = $row;
        }
        return $types;
    }

    public function updatePaymentType($id, $name, $amount, $description, $academic_term, $min_amount) {
        $sql = "UPDATE school_payment_types 
                SET name = ?, amount = ?, min_payment_amount = ?, description = ?, academic_term = ? 
                WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sdsssi", $name, $amount, $min_amount, $description, $academic_term, $id);
        return $stmt->execute();
    }

    public function deactivatePaymentType($id) {
        $sql = "UPDATE school_payment_types SET is_active = 0 WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
}

// Create tables if they don't exist
$sql = "CREATE TABLE IF NOT EXISTS school_payment_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    min_payment_amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    academic_term VARCHAR(50) NOT NULL,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$conn->query($sql);

// Create payments table
$sql = "CREATE TABLE IF NOT EXISTS school_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    payment_type_id INT,
    amount DECIMAL(10,2) NOT NULL,
    reference_code VARCHAR(100) UNIQUE,
    payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_type_id) REFERENCES school_payment_types(id)
)";

$conn->query($sql);
?> 