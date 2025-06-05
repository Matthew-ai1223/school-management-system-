<?php
require_once 'db_config.php';

class PaymentRecords {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getAllPayments() {
        $sql = "SELECT p.*, pt.name as payment_type_name,
                CONCAT(s.first_name, ' ', s.last_name) as student_name 
                FROM school_payments p 
                JOIN school_payment_types pt ON p.payment_type_id = pt.id 
                LEFT JOIN students s ON p.student_id = s.registration_number 
                ORDER BY p.payment_date DESC";
        $result = $this->conn->query($sql);
        $payments = [];
        
        while($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
        return $payments;
    }

    public function getStudentPayments($student_id) {
        $sql = "SELECT p.*, pt.name as payment_type_name,
                CONCAT(s.first_name, ' ', s.last_name) as student_name 
                FROM school_payments p 
                JOIN school_payment_types pt ON p.payment_type_id = pt.id 
                LEFT JOIN students s ON p.student_id = s.registration_number 
                WHERE p.student_id = ?
                ORDER BY p.payment_date DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $payments = [];
        
        while($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
        return $payments;
    }

    public function getPaymentsByDate($start_date, $end_date) {
        $sql = "SELECT p.*, pt.name as payment_type_name,
                CONCAT(s.first_name, ' ', s.last_name) as student_name 
                FROM school_payments p 
                JOIN school_payment_types pt ON p.payment_type_id = pt.id 
                LEFT JOIN students s ON p.student_id = s.registration_number 
                WHERE DATE(p.payment_date) BETWEEN ? AND ?
                ORDER BY p.payment_date DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $payments = [];
        
        while($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
        return $payments;
    }

    public function getPaymentDetails($reference_code) {
        $sql = "SELECT p.*, pt.name as payment_type_name,
                CONCAT(s.first_name, ' ', s.last_name) as student_name 
                FROM school_payments p 
                JOIN school_payment_types pt ON p.payment_type_id = pt.id 
                LEFT JOIN students s ON p.student_id = s.registration_number 
                WHERE p.reference_code = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $reference_code);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
}
?> 