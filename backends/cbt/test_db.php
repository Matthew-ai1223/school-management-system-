<?php
require_once '../config.php';
require_once '../database.php';
require_once '../utils.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set error log path
ini_set('error_log', 'C:/xampp/htdocs/ACE MODEL COLLEGE/logs/php_errors.log');
if (!file_exists('C:/xampp/htdocs/ACE MODEL COLLEGE/logs')) {
    mkdir('C:/xampp/htdocs/ACE MODEL COLLEGE/logs', 0777, true);
}

class TestDatabase {
    private $conn;
    private static $instance = null;

    private function __construct() {
        $db = Database::getInstance();
        $this->conn = $db->getConnection();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new TestDatabase();
        }
        return self::$instance;
    }

    // Get exam information
    public function getExamById($exam_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM cbt_exams 
                WHERE id = ? AND is_active = 1
            ");
            $stmt->bind_param("i", $exam_id);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_assoc();
        } catch (Exception $e) {
            error_log("Error getting exam: " . $e->getMessage());
            return null;
        }
    }

    // Get exam questions
    public function getExamQuestions($exam_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT q.*
                FROM cbt_questions q
                WHERE q.exam_id = ?
                ORDER BY q.sort_order ASC, q.id ASC
            ");
            $stmt->bind_param("i", $exam_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $questions = [];
            while ($row = $result->fetch_assoc()) {
                // processed_options will be built in exam_interface.php
                $questions[] = $row;
            }
            
            return $questions;
        } catch (Exception $e) {
            error_log("Error getting exam questions: " . $e->getMessage());
            return [];
        }
    }

    // Create or update exam attempt
    public function createExamAttempt($student_id, $exam_id) {
        try {
            $this->conn->begin_transaction();

            // Check if there's an existing attempt
            $stmt = $this->conn->prepare("
                SELECT id, status 
                FROM cbt_student_attempts 
                WHERE student_id = ? AND exam_id = ? 
                AND (status = 'In Progress' OR (status = 'Completed' AND end_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)))
            ");
            $stmt->bind_param("ii", $student_id, $exam_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $attempt = $result->fetch_assoc();
                if ($attempt['status'] === 'Completed') {
                    throw new Exception("You have already completed this exam within the last 24 hours");
                }
                $this->conn->commit();
                return $attempt['id'];
            }

            // Get exam details
            $stmt = $this->conn->prepare("
                SELECT * FROM cbt_exams 
                WHERE id = ? AND is_active = 1
            ");
            $stmt->bind_param("i", $exam_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $exam = $result->fetch_assoc();

            if (!$exam) {
                throw new Exception("Exam not found or not active");
            }

            // Calculate timing
            $duration = $exam['time_limit'] ?? 60; // Default to 60 minutes if not set
            $start_time = date('Y-m-d H:i:s');
            $end_time = date('Y-m-d H:i:s', strtotime("+{$duration} minutes"));

            // Create new attempt
            $stmt = $this->conn->prepare("
                INSERT INTO cbt_student_attempts (
                    exam_id,
                    student_id,
                    start_time,
                    end_time,
                    status,
                    total_marks,
                    ip_address,
                    user_agent
                ) VALUES (?, ?, ?, ?, 'In Progress', ?, ?, ?)
            ");

            // Calculate total marks based on number of questions
            $total_marks = $exam['total_questions'] ?? 0;
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $stmt->bind_param("iississ", 
                $exam_id,
                $student_id,
                $start_time,
                $end_time,
                $total_marks,
                $ip_address,
                $user_agent
            );

            if (!$stmt->execute()) {
                throw new Exception("Failed to create exam attempt: " . $stmt->error);
            }

            $attempt_id = $this->conn->insert_id;
            $this->conn->commit();
            return $attempt_id;

        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Error creating exam attempt: " . $e->getMessage());
            throw $e;
        }
    }

    // Save student answer
    public function saveStudentAnswer($attempt_id, $question_id, $answer) {
        try {
            // Check if answer is correct
            $stmt = $this->conn->prepare("
                SELECT question_type, correct_answer 
                FROM cbt_questions 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $question_id);
            $stmt->execute();
            $question = $stmt->get_result()->fetch_assoc();

            $is_correct = 0;
            if ($question) {
                if ($question['question_type'] === 'True/False') {
                    $is_correct = strcasecmp(trim($answer), trim($question['correct_answer'])) === 0 ? 1 : 0;
                } else {
                    $stmt = $this->conn->prepare("
                        SELECT is_correct 
                        FROM cbt_options 
                        WHERE question_id = ? AND option_text = ?
                    ");
                    $stmt->bind_param("is", $question_id, $answer);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        $is_correct = $row['is_correct'];
                    }
                }
            }

            // Save or update the answer
            $stmt = $this->conn->prepare("
                INSERT INTO cbt_student_answers (
                    attempt_id, 
                    question_id, 
                    selected_answer, 
                    is_correct,
                    answer_time
                ) VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    selected_answer = VALUES(selected_answer),
                    is_correct = VALUES(is_correct),
                    answer_time = NOW()
            ");
            
            $stmt->bind_param("iisi", $attempt_id, $question_id, $answer, $is_correct);
            return $stmt->execute();

        } catch (Exception $e) {
            error_log("Error saving student answer: " . $e->getMessage());
            return false;
        }
    }

    private function calculateExamScore($attempt_id) {
        try {
            // First get exam details and total questions
            $stmt = $this->conn->prepare("
                SELECT 
                    e.total_questions,
                    e.passing_score,
                    COUNT(DISTINCT q.id) as actual_total_questions
                FROM cbt_student_attempts sa
                JOIN cbt_exams e ON e.id = sa.exam_id
                JOIN cbt_questions q ON q.exam_id = e.id
                WHERE sa.id = ?
                GROUP BY sa.id, e.total_questions, e.passing_score
            ");
            
            $stmt->bind_param("i", $attempt_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $exam_data = $result->fetch_assoc();
            
            if (!$exam_data) {
                throw new Exception("No exam data found for attempt");
            }
            
            // Each question is worth 1 mark
            $total_questions = $exam_data['actual_total_questions'];
            $marks_per_question = 1;
            
            // Now get student answers and compare with correct answers
            $stmt = $this->conn->prepare("
                SELECT 
                    q.id,
                    q.question_type,
                    q.correct_answer,
                    sa.selected_answer,
                    CASE 
                        WHEN q.question_type = 'Multiple Choice' THEN (
                            SELECT option_text 
                            FROM cbt_options 
                            WHERE question_id = q.id AND is_correct = 1 
                            LIMIT 1
                        )
                        ELSE q.correct_answer 
                    END as actual_correct_answer
                FROM cbt_questions q
                JOIN cbt_student_attempts att ON q.exam_id = att.exam_id
                LEFT JOIN cbt_student_answers sa ON sa.question_id = q.id AND sa.attempt_id = ?
                WHERE att.id = ?
            ");
            
            $stmt->bind_param("ii", $attempt_id, $attempt_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $correct_answers = 0;
            $wrong_answers = 0;
            $unanswered = 0;
            $total_marks_obtained = 0;
            
            while ($row = $result->fetch_assoc()) {
                if (empty($row['selected_answer'])) {
                    $unanswered++;
                    continue;
                }
                
                $is_correct = false;
                $selected_answer = trim($row['selected_answer']);
                $correct_answer = trim($row['actual_correct_answer']);
                $marks_awarded = 0;
                
                if (strtolower($row['question_type']) === 'multiple choice') {
                    // For multiple choice, compare selected answer with correct answer
                    $is_correct = (strcasecmp($selected_answer, $correct_answer) === 0);
                } else if (strtolower($row['question_type']) === 'true/false') {
                    // For true/false, case-insensitive comparison
                    $is_correct = (strcasecmp($selected_answer, $correct_answer) === 0);
                }
                
                if ($is_correct) {
                    $correct_answers++;
                    $marks_awarded = $marks_per_question;
                    $total_marks_obtained += $marks_awarded;
                } else {
                    $wrong_answers++;
                }
                
                // Update the student_answers table with marks and correctness
                $update_stmt = $this->conn->prepare("
                    UPDATE cbt_student_answers 
                    SET is_correct = ?, marks_awarded = ?
                    WHERE attempt_id = ? AND question_id = ?
                ");
                $is_correct_int = $is_correct ? 1 : 0;
                $update_stmt->bind_param("idii", $is_correct_int, $marks_awarded, $attempt_id, $row['id']);
                $update_stmt->execute();
            }
            
            // Ensure score is not negative
            $total_marks_obtained = max(0, $total_marks_obtained);
            $percentage = ($total_questions > 0) ? ($total_marks_obtained / $total_questions) * 100 : 0;
            
            return [
                'total_score' => $total_marks_obtained,
                'percentage' => round($percentage, 2),
                'correct_answers' => $correct_answers,
                'wrong_answers' => $wrong_answers,
                'unanswered' => $unanswered,
                'total_questions' => $total_questions,
                'total_possible_marks' => $total_questions, // Total possible marks equals total questions since each question is worth 1 mark
                'passing_score' => $exam_data['passing_score'] ?? 50 // Default to 50% if not set
            ];
            
        } catch (Exception $e) {
            error_log("Error calculating exam score: " . $e->getMessage());
            throw new Exception("Failed to calculate exam score: " . $e->getMessage());
        }
    }

    // Complete the submitExam method
    public function submitExam($attempt_id) {
        try {
            $this->conn->begin_transaction();
            
            // Check if exam is already submitted
            $stmt = $this->conn->prepare("
                SELECT status FROM cbt_student_attempts 
                WHERE id = ? AND status = 'In Progress'
            ");
            $stmt->bind_param("i", $attempt_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("Exam is already submitted or invalid attempt");
            }
            
            // Calculate final score
            $score_data = $this->calculateExamScore($attempt_id);
            if (!$score_data) {
                throw new Exception("Failed to calculate exam score");
            }
            
            // Update attempt with final score
            $stmt = $this->conn->prepare("
                UPDATE cbt_student_attempts 
                SET 
                    status = 'Completed',
                    score = ?,
                    marks_obtained = ?,
                    time_spent = TIMESTAMPDIFF(SECOND, start_time, NOW()),
                    end_time = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->bind_param(
                "ddi", 
                $score_data['percentage'],
                $score_data['total_score'],
                $attempt_id
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update exam attempt");
            }
            
            $this->conn->commit();
            return [
                'success' => true,
                'message' => 'Exam submitted successfully',
                'score_data' => $score_data
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Error submitting exam: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
} 