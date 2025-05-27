<?php
require_once '../config.php';
require_once '../database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Check if connection is successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Array to track created tables
$createdTables = [];
$errors = [];

// Function to execute SQL and track results
function executeSql($conn, $sql, $tableName) {
    global $createdTables, $errors;
    
    if ($conn->query($sql) === TRUE) {
        $createdTables[] = $tableName;
        return true;
    } else {
        $errors[] = "Error creating table $tableName: " . $conn->error;
        return false;
    }
}

// Create cbt_exams table
$sql = "CREATE TABLE IF NOT EXISTS `cbt_exams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `class` varchar(50) NOT NULL,
  `time_limit` int(11) NOT NULL COMMENT 'in minutes',
  `description` text DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `total_questions` int(11) DEFAULT 0,
  `passing_score` int(11) DEFAULT 50,
  `random_questions` tinyint(1) DEFAULT 1,
  `show_results` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `start_datetime` datetime DEFAULT NULL,
  `end_datetime` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `subject_id` (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

executeSql($conn, $sql, "cbt_exams");

// Create cbt_questions table
$sql = "CREATE TABLE IF NOT EXISTS `cbt_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` varchar(50) NOT NULL DEFAULT 'Multiple Choice',
  `marks` int(11) NOT NULL DEFAULT 1,
  `correct_answer` text DEFAULT NULL,
  `option_a` text DEFAULT NULL,
  `option_b` text DEFAULT NULL,
  `option_c` text DEFAULT NULL,
  `option_d` text DEFAULT NULL,
  `explanation` text DEFAULT NULL,
  `question_image` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `exam_id` (`exam_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

executeSql($conn, $sql, "cbt_questions");

// Create cbt_student_exams table
$sql = "CREATE TABLE IF NOT EXISTS `cbt_student_exams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `submitted_at` datetime DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `total_score` decimal(5,2) DEFAULT NULL,
  `percentage` decimal(5,2) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'In Progress',
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `exam_id` (`exam_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

executeSql($conn, $sql, "cbt_student_exams");

// Create cbt_student_answers table
$sql = "CREATE TABLE IF NOT EXISTS `cbt_student_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_exam_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `student_answer` text DEFAULT NULL,
  `selected_option_id` int(11) DEFAULT NULL,
  `text_answer` text DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `marks_earned` decimal(5,2) DEFAULT 0,
  `possible_marks` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `student_exam_id` (`student_exam_id`),
  KEY `question_id` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

executeSql($conn, $sql, "cbt_student_answers");

// Create cbt_options table
$sql = "CREATE TABLE IF NOT EXISTS `cbt_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) NOT NULL,
  `option_text` text NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `option_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `question_id` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

executeSql($conn, $sql, "cbt_options");

// Make sure subjects table exists
$sql = "CREATE TABLE IF NOT EXISTS `subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

executeSql($conn, $sql, "subjects");

// Make sure class_subjects table exists
$sql = "CREATE TABLE IF NOT EXISTS `class_subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` varchar(50) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `subject_id` (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

executeSql($conn, $sql, "class_subjects");

// Add a sample subject if none exists
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM subjects");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    $stmt = $conn->prepare("INSERT INTO subjects (name, code, description) VALUES ('Mathematics', 'MATH', 'General Mathematics')");
    $stmt->execute();

    $stmt = $conn->prepare("INSERT INTO subjects (name, code, description) VALUES ('English', 'ENG', 'English Language')");
    $stmt->execute();
    
    $stmt = $conn->prepare("INSERT INTO subjects (name, code, description) VALUES ('Physics', 'PHY', 'Physics')");
    $stmt->execute();
}

// Display results
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CBT Tables Installation</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4>CBT System Tables Installation</h4>
            </div>
            <div class="card-body">
                <?php if (empty($errors)): ?>
                    <div class="alert alert-success">
                        <h5>Installation Successful!</h5>
                        <p>All required tables have been created successfully.</p>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <h5>Installation Completed with Warnings</h5>
                        <p>Some tables may not have been created properly:</p>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <h5>Created Tables:</h5>
                <ul>
                    <?php foreach ($createdTables as $table): ?>
                        <li><?php echo $table; ?></li>
                    <?php endforeach; ?>
                </ul>

                <div class="mt-4">
                    <a href="../student/registration/student_dashboard.php" class="btn btn-primary">Go to Student Dashboard</a>
                    <a href="dashboard.php" class="btn btn-secondary">Go to CBT Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 