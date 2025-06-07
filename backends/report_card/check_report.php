<?php
require_once '../config.php';
require_once '../database.php';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // Check report_cards table
    $sql = "SELECT rc.*, 
            CONCAT(s.first_name, ' ', s.last_name) as student_name,
            CONCAT(t.first_name, ' ', t.last_name) as teacher_name
            FROM report_cards rc
            LEFT JOIN students s ON rc.student_id = s.id
            LEFT JOIN teachers t ON rc.created_by = t.id
            ORDER BY rc.id DESC";
    
    $result = $conn->query($sql);
    
    echo "<h2>Report Cards in Database:</h2>";
    if ($result && $result->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Student</th><th>Class</th><th>Term</th><th>Year</th><th>Teacher</th><th>Created At</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . ($row['student_name'] ?? 'N/A') . "</td>";
            echo "<td>" . $row['class'] . "</td>";
            echo "<td>" . $row['term'] . "</td>";
            echo "<td>" . $row['academic_year'] . "</td>";
            echo "<td>" . ($row['teacher_name'] ?? 'N/A') . "</td>";
            echo "<td>" . $row['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No report cards found in the database.</p>";
    }
    
    // Check report_card_details
    echo "<h2>Report Card Details:</h2>";
    $sql = "SELECT rcd.*, rs.subject_name 
            FROM report_card_details rcd
            LEFT JOIN report_subjects rs ON rcd.subject_id = rs.id
            ORDER BY rcd.report_card_id, rs.subject_name";
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Report Card ID</th><th>Subject</th><th>Test Score</th><th>Exam Score</th><th>Total</th><th>Grade</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['report_card_id'] . "</td>";
            echo "<td>" . ($row['subject_name'] ?? 'N/A') . "</td>";
            echo "<td>" . $row['test_score'] . "</td>";
            echo "<td>" . $row['exam_score'] . "</td>";
            echo "<td>" . $row['total_score'] . "</td>";
            echo "<td>" . ($row['grade'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No report card details found in the database.</p>";
    }
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 