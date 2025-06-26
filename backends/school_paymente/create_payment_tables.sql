-- Payment System Database Tables Creation Script
-- Database: ace_school_system

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- Create payment types table
CREATE TABLE IF NOT EXISTS school_payment_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    min_payment_amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    academic_term VARCHAR(50) NOT NULL,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create payments table with base_amount and service_charge
CREATE TABLE IF NOT EXISTS school_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    payment_type_id INT,
    amount DECIMAL(10,2) NOT NULL,
    base_amount DECIMAL(10,2) NOT NULL,
    service_charge DECIMAL(10,2) NOT NULL,
    reference_code VARCHAR(100) UNIQUE,
    payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_type_id) REFERENCES school_payment_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create payment receipts table
CREATE TABLE IF NOT EXISTS school_payment_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT,
    receipt_number VARCHAR(50) UNIQUE NOT NULL,
    generated_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES school_payments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create students table if it doesn't exist (for foreign key reference)
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    registration_number VARCHAR(50) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    class VARCHAR(50),
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample payment types
INSERT IGNORE INTO school_payment_types (name, amount, min_payment_amount, description, academic_term) VALUES
('School Fees', 50000.00, 10000.00, 'Complete school fees for the academic term', '2024/2025'),
('Library Fee', 5000.00, 5000.00, 'Library access and book borrowing fee', '2024/2025'),
('Laboratory Fee', 3000.00, 3000.00, 'Science laboratory usage fee', '2024/2025'),
('Sports Fee', 2000.00, 2000.00, 'Sports facilities and equipment fee', '2024/2025'),
('Examination Fee', 1500.00, 1500.00, 'End of term examination fee', '2024/2025');

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Display success message
SELECT 'Payment system tables created successfully!' as status; 