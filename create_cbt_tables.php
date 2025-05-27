<?php
require_once 'backends/config.php';
require_once 'backends/database.php';

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create the tables if they don't exist
$queries = [
    // cbt_exams table
    "CREATE TABLE IF NOT EXISTS `cbt_exams` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `title` varchar(255) NOT NULL,
        `subject_id` int(11) NOT NULL,
        `class_id` varchar(50) NOT NULL,
        `description` text DEFAULT NULL,
        `total_questions` int(11) NOT NULL DEFAULT 0,
        `time_limit` int(11) NOT NULL DEFAULT 60,
        `passing_score` int(11) NOT NULL DEFAULT 50,
        `is_active` tinyint(1) NOT NULL DEFAULT 1,
        `show_results` tinyint(1) NOT NULL DEFAULT 1,
        `randomize_questions` tinyint(1) NOT NULL DEFAULT 0,
        `start_datetime` datetime NOT NULL,
        `end_datetime` datetime NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `created_by` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `subject_id` (`subject_id`),
        KEY `class_id` (`class_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // cbt_questions table
    "CREATE TABLE IF NOT EXISTS `cbt_questions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `exam_id` int(11) NOT NULL,
        `question_text` text NOT NULL,
        `question_type` enum('multiple_choice','true_false','short_answer') NOT NULL DEFAULT 'multiple_choice',
        `difficulty` enum('easy','medium','hard') DEFAULT 'medium',
        `marks` int(11) NOT NULL DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `exam_id` (`exam_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // cbt_options table
    "CREATE TABLE IF NOT EXISTS `cbt_options` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `question_id` int(11) NOT NULL,
        `option_text` text NOT NULL,
        `is_correct` tinyint(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `question_id` (`question_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // cbt_student_exams table
    "CREATE TABLE IF NOT EXISTS `cbt_student_exams` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` int(11) NOT NULL,
        `exam_id` int(11) NOT NULL,
        `started_at` datetime NOT NULL,
        `submitted_at` datetime DEFAULT NULL,
        `time_spent` int(11) DEFAULT NULL COMMENT 'Time spent in seconds',
        `score` float DEFAULT NULL COMMENT 'Percentage score',
        `status` enum('In Progress','Completed','Abandoned') NOT NULL DEFAULT 'In Progress',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `student_exam_unique` (`student_id`,`exam_id`),
        KEY `student_id` (`student_id`),
        KEY `exam_id` (`exam_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // cbt_student_answers table
    "CREATE TABLE IF NOT EXISTS `cbt_student_answers` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_exam_id` int(11) NOT NULL,
        `question_id` int(11) NOT NULL,
        `selected_option_id` int(11) DEFAULT NULL,
        `text_answer` text DEFAULT NULL,
        `is_correct` tinyint(1) DEFAULT NULL,
        `marks_awarded` float DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_student_answer` (`student_exam_id`,`question_id`),
        KEY `student_exam_id` (`student_exam_id`),
        KEY `question_id` (`question_id`),
        KEY `selected_option_id` (`selected_option_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

$results = ['success' => 0, 'errors' => 0];

foreach ($queries as $query) {
    if ($conn->query($query) === TRUE) {
        $results['success']++;
    } else {
        $results['errors']++;
        echo "Error creating table: " . $conn->error . "<br>";
    }
}

// Add foreign key constraints
$constraints = [
    "ALTER TABLE `cbt_questions` 
     ADD CONSTRAINT `cbt_questions_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `cbt_exams` (`id`) ON DELETE CASCADE;",
    
    "ALTER TABLE `cbt_options` 
     ADD CONSTRAINT `cbt_options_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `cbt_questions` (`id`) ON DELETE CASCADE;",
    
    "ALTER TABLE `cbt_student_exams` 
     ADD CONSTRAINT `cbt_student_exams_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
     ADD CONSTRAINT `cbt_student_exams_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `cbt_exams` (`id`) ON DELETE CASCADE;",
    
    "ALTER TABLE `cbt_student_answers` 
     ADD CONSTRAINT `cbt_student_answers_ibfk_1` FOREIGN KEY (`student_exam_id`) REFERENCES `cbt_student_exams` (`id`) ON DELETE CASCADE,
     ADD CONSTRAINT `cbt_student_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `cbt_questions` (`id`) ON DELETE CASCADE,
     ADD CONSTRAINT `cbt_student_answers_ibfk_3` FOREIGN KEY (`selected_option_id`) REFERENCES `cbt_options` (`id`) ON DELETE SET NULL;"
];

// Foreign keys might fail if the tables already exist but we'll try anyway
foreach ($constraints as $constraint) {
    try {
        $conn->query($constraint);
    } catch (Exception $e) {
        // Ignore errors for constraints as they might already exist
    }
}

// Check if required tables exist for testing
$requiredTables = ['cbt_exams', 'cbt_student_exams', 'subjects', 'class_subjects'];
$missingTables = [];

foreach ($requiredTables as $table) {
    $checkTableQuery = "SHOW TABLES LIKE '$table'";
    $tableResult = $conn->query($checkTableQuery);
    if (!$tableResult || $tableResult->num_rows == 0) {
        $missingTables[] = $table;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CBT Tables Setup</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h2>CBT Database Tables Setup</h2>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <p><strong>Setup Results:</strong></p>
                    <ul>
                        <li>Tables created successfully: <?php echo $results['success']; ?></li>
                        <li>Errors encountered: <?php echo $results['errors']; ?></li>
                    </ul>
                </div>

                <?php if (empty($missingTables)): ?>
                    <div class="alert alert-success">
                        <h4>All required tables are present in the database!</h4>
                        <p>The CBT system should now work properly.</p>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <h4>Warning: Some tables are still missing</h4>
                        <p>The following tables could not be created or found:</p>
                        <ul>
                            <?php foreach ($missingTables as $table): ?>
                                <li><?php echo $table; ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <p>This might be because the table names are different in your database or because of permission issues.</p>
                    </div>
                <?php endif; ?>

                <div class="mt-4">
                    <a href="index.php" class="btn btn-primary">Return to Homepage</a>
                    <a href="backends/student/registration/student_dashboard.php" class="btn btn-secondary ml-2">Go to Student Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 