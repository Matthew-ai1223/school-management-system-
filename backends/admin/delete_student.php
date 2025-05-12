<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();
$mysqli = $db->getConnection();

$response = ['success' => false, 'message' => ''];

try {
    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get student ID
    $student_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($student_id <= 0) {
        throw new Exception('Invalid student ID');
    }

    // Start transaction
    $mysqli->begin_transaction();

    try {
        // First, delete associated files
        $query = "SELECT id, file_path FROM files WHERE entity_type = 'student' AND entity_id = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param('i', $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($file = $result->fetch_assoc()) {
            // Delete physical file if it exists
            if (!empty($file['file_path']) && file_exists($file['file_path'])) {
                unlink($file['file_path']);
            }
            
            // Delete file record
            $delete_file = "DELETE FROM files WHERE id = ?";
            $stmt = $mysqli->prepare($delete_file);
            $stmt->bind_param('i', $file['id']);
            $stmt->execute();
        }

        // Delete student record
        $query = "DELETE FROM students WHERE id = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param('i', $student_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $mysqli->commit();
                $response['success'] = true;
                $response['message'] = 'Student deleted successfully';
            } else {
                throw new Exception('Student not found');
            }
        } else {
            throw new Exception('Failed to delete student');
        }
    } catch (Exception $e) {
        $mysqli->rollback();
        throw $e;
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response); 