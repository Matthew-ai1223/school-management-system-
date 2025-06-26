<?php
require_once 'db_config.php';

class PaymentRecords {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getAllPayments() {
        // First check if approval columns exist
        $check_columns = $this->conn->query("SHOW COLUMNS FROM cash_payments LIKE 'approval_status'");
        $has_approval_columns = $check_columns->num_rows > 0;
        
        // Union query to get both online and cash payments
        $sql = "SELECT 
                    'online' as payment_method,
                    p.id,
                    p.student_id,
                    p.payment_type_id,
                    p.amount,
                    p.base_amount,
                    p.service_charge,
                    p.reference_code,
                    p.payment_status,
                    p.payment_date,
                    pt.name as payment_type_name,
                    CONCAT(s.first_name, ' ', s.last_name) as student_name,
                    NULL as bursar_name,
                    NULL as receipt_number,
                    NULL as notes,
                    NULL as approval_status,
                    NULL as approver_name,
                    NULL as approval_date
                FROM school_payments p 
                JOIN school_payment_types pt ON p.payment_type_id = pt.id 
                LEFT JOIN students s ON p.student_id = s.registration_number 
                
                UNION ALL
                
                SELECT 
                    'cash' as payment_method,
                    cp.id,
                    cp.student_id,
                    cp.payment_type_id,
                    cp.amount,
                    cp.base_amount,
                    cp.service_charge,
                    cp.reference_code,
                    cp.payment_status,
                    cp.payment_date,
                    pt.name as payment_type_name,
                    CONCAT(s.first_name, ' ', s.last_name) as student_name,
                    cp.bursar_name,
                    cp.receipt_number,
                    cp.notes,";
        
        if ($has_approval_columns) {
            $sql .= "cp.approval_status,
                    cp.approver_name,
                    cp.approval_date";
        } else {
            $sql .= "'under_review' as approval_status,
                    NULL as approver_name,
                    NULL as approval_date";
        }
        
        $sql .= " FROM cash_payments cp 
                JOIN school_payment_types pt ON cp.payment_type_id = pt.id 
                LEFT JOIN students s ON cp.student_id = s.registration_number 
                
                ORDER BY payment_date DESC";
        
        $result = $this->conn->query($sql);
        $payments = [];
        
        while($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
        return $payments;
    }

    public function getStudentPayments($student_id) {
        // First check if approval columns exist
        $check_columns = $this->conn->query("SHOW COLUMNS FROM cash_payments LIKE 'approval_status'");
        $has_approval_columns = $check_columns->num_rows > 0;
        
        // Union query to get both online and cash payments for a specific student
        $sql = "SELECT 
                    'online' as payment_method,
                    p.id,
                    p.student_id,
                    p.payment_type_id,
                    p.amount,
                    p.base_amount,
                    p.service_charge,
                    p.reference_code,
                    p.payment_status,
                    p.payment_date,
                    pt.name as payment_type_name,
                    CONCAT(s.first_name, ' ', s.last_name) as student_name,
                    NULL as bursar_name,
                    NULL as receipt_number,
                    NULL as notes,
                    NULL as approval_status,
                    NULL as approver_name,
                    NULL as approval_date
                FROM school_payments p 
                JOIN school_payment_types pt ON p.payment_type_id = pt.id 
                LEFT JOIN students s ON p.student_id = s.registration_number 
                WHERE p.student_id = ?
                
                UNION ALL
                
                SELECT 
                    'cash' as payment_method,
                    cp.id,
                    cp.student_id,
                    cp.payment_type_id,
                    cp.amount,
                    cp.base_amount,
                    cp.service_charge,
                    cp.reference_code,
                    cp.payment_status,
                    cp.payment_date,
                    pt.name as payment_type_name,
                    CONCAT(s.first_name, ' ', s.last_name) as student_name,
                    cp.bursar_name,
                    cp.receipt_number,
                    cp.notes,";
        
        if ($has_approval_columns) {
            $sql .= "cp.approval_status,
                    cp.approver_name,
                    cp.approval_date";
        } else {
            $sql .= "'under_review' as approval_status,
                    NULL as approver_name,
                    NULL as approval_date";
        }
        
        $sql .= " FROM cash_payments cp 
                JOIN school_payment_types pt ON cp.payment_type_id = pt.id 
                LEFT JOIN students s ON cp.student_id = s.registration_number 
                WHERE cp.student_id = ?
                
                ORDER BY payment_date DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $student_id, $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $payments = [];
        
        while($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
        return $payments;
    }

    public function getPaymentsByDate($start_date, $end_date) {
        // First check if approval columns exist
        $check_columns = $this->conn->query("SHOW COLUMNS FROM cash_payments LIKE 'approval_status'");
        $has_approval_columns = $check_columns->num_rows > 0;
        
        // Union query to get both online and cash payments within date range
        $sql = "SELECT 
                    'online' as payment_method,
                    p.id,
                    p.student_id,
                    p.payment_type_id,
                    p.amount,
                    p.base_amount,
                    p.service_charge,
                    p.reference_code,
                    p.payment_status,
                    p.payment_date,
                    pt.name as payment_type_name,
                    CONCAT(s.first_name, ' ', s.last_name) as student_name,
                    NULL as bursar_name,
                    NULL as receipt_number,
                    NULL as notes,
                    NULL as approval_status,
                    NULL as approver_name,
                    NULL as approval_date
                FROM school_payments p 
                JOIN school_payment_types pt ON p.payment_type_id = pt.id 
                LEFT JOIN students s ON p.student_id = s.registration_number 
                WHERE DATE(p.payment_date) BETWEEN ? AND ?
                
                UNION ALL
                
                SELECT 
                    'cash' as payment_method,
                    cp.id,
                    cp.student_id,
                    cp.payment_type_id,
                    cp.amount,
                    cp.base_amount,
                    cp.service_charge,
                    cp.reference_code,
                    cp.payment_status,
                    cp.payment_date,
                    pt.name as payment_type_name,
                    CONCAT(s.first_name, ' ', s.last_name) as student_name,
                    cp.bursar_name,
                    cp.receipt_number,
                    cp.notes,";
        
        if ($has_approval_columns) {
            $sql .= "cp.approval_status,
                    cp.approver_name,
                    cp.approval_date";
        } else {
            $sql .= "'under_review' as approval_status,
                    NULL as approver_name,
                    NULL as approval_date";
        }
        
        $sql .= " FROM cash_payments cp 
                JOIN school_payment_types pt ON cp.payment_type_id = pt.id 
                LEFT JOIN students s ON cp.student_id = s.registration_number 
                WHERE DATE(cp.payment_date) BETWEEN ? AND ?
                
                ORDER BY payment_date DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ssss", $start_date, $end_date, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $payments = [];
        
        while($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
        return $payments;
    }

    public function getPaymentDetails($reference_code) {
        // Try to get payment from online payments first
        $sql = "SELECT 
                    'online' as payment_method,
                    p.*,
                    pt.name as payment_type_name,
                    CONCAT(s.first_name, ' ', s.last_name) as student_name,
                    NULL as bursar_name,
                    NULL as receipt_number,
                    NULL as notes,
                    NULL as approval_status,
                    NULL as approver_name,
                    NULL as approval_date
                FROM school_payments p 
                JOIN school_payment_types pt ON p.payment_type_id = pt.id 
                LEFT JOIN students s ON p.student_id = s.registration_number 
                WHERE p.reference_code = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $reference_code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        // If not found in online payments, try cash payments
        // Check if approval columns exist
        $check_columns = $this->conn->query("SHOW COLUMNS FROM cash_payments LIKE 'approval_status'");
        $has_approval_columns = $check_columns->num_rows > 0;
        
        $sql = "SELECT 
                    'cash' as payment_method,
                    cp.*,
                    pt.name as payment_type_name,
                    CONCAT(s.first_name, ' ', s.last_name) as student_name";
        
        if ($has_approval_columns) {
            $sql .= ", cp.approval_status, cp.approver_name, cp.approval_date";
        } else {
            $sql .= ", 'under_review' as approval_status, NULL as approver_name, NULL as approval_date";
        }
        
        $sql .= " FROM cash_payments cp 
                JOIN school_payment_types pt ON cp.payment_type_id = pt.id 
                LEFT JOIN students s ON cp.student_id = s.registration_number 
                WHERE cp.reference_code = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $reference_code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        return null;
    }
}
?> 