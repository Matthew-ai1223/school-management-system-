<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

session_start();

// Check teacher authentication
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}

$db = Database::getInstance()->getConnection();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['subjects']) && is_array($_POST['subjects'])) {
        // Validate number of subjects
        if (count($_POST['subjects']) > 5) {
            $_SESSION['error_message'] = "Error: Maximum 5 subjects can be assigned to a teacher";
            header('Location: dashboard.php');
            exit();
        } else if (empty($_POST['subjects'])) {
            $_SESSION['error_message'] = "Error: Please select at least one subject";
            header('Location: dashboard.php');
            exit();
        }

        try {
            $db->beginTransaction();
            
            // Clean and validate subjects array
            $cleanSubjects = array_map('trim', $_POST['subjects']);
            $cleanSubjects = array_filter($cleanSubjects);

            // Verify all subjects exist in the all_subjects table
            $subjectPlaceholders = str_repeat('?,', count($cleanSubjects) - 1) . '?';
            $subjectVerifyStmt = $db->prepare("
                SELECT subject_name 
                FROM ace_school_system.all_subjects 
                WHERE subject_name IN ($subjectPlaceholders)
            ");
            
            if (!$subjectVerifyStmt->execute($cleanSubjects)) {
                throw new PDOException("Failed to verify subjects");
            }
            
            $validSubjects = $subjectVerifyStmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($validSubjects) !== count($cleanSubjects)) {
                $invalidSubjects = array_diff($cleanSubjects, $validSubjects);
                throw new PDOException("Invalid subjects selected: " . implode(", ", $invalidSubjects));
            }

            // Delete existing assignments
            $deleteStmt = $db->prepare("DELETE FROM ace_school_system.teacher_subjects WHERE teacher_id = ?");
            if (!$deleteStmt->execute([$_SESSION['teacher_id']])) {
                throw new PDOException("Failed to remove existing subject assignments");
            }
            
            // Batch insert new assignments
            if (!empty($validSubjects)) {
                $insertValues = array_fill(0, count($validSubjects), "(?, ?)");
                $insertQuery = "INSERT INTO ace_school_system.teacher_subjects (teacher_id, subject) VALUES " . implode(", ", $insertValues);
                $insertParams = [];
                foreach ($validSubjects as $subject) {
                    $insertParams[] = $_SESSION['teacher_id'];
                    $insertParams[] = $subject;
                }
                
                $insertStmt = $db->prepare($insertQuery);
                if (!$insertStmt->execute($insertParams)) {
                    throw new PDOException("Failed to assign new subjects");
                }
            }
            
            $db->commit();
            $_SESSION['success_message'] = "Subject assignments updated successfully!";
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error in assign-subjects.php: " . $e->getMessage());
            $_SESSION['error_message'] = "Error: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "Error: No subjects were selected";
    }
    
    header('Location: dashboard.php');
    exit();
}

// If not a POST request, redirect back to dashboard
header('Location: dashboard.php');
exit(); 