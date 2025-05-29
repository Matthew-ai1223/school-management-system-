<?php
require_once '../config/config.php';
require_once '../includes/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Read and execute the SQL file
    $sql = file_get_contents(__DIR__ . '/teacher_subjects.sql');
    $db->exec($sql);
    
    echo "Teacher subjects table and exam subject column created successfully!\n";
    
    // Add some default subjects if needed
    $default_subjects = [
        'Mathematics',
        'English Language',
        'Physics',
        'Chemistry',
        'Biology',
        'Computer Science',
        'Literature',
        'History',
        'Geography',
        'Economics'
    ];
    
    // Prepare the insert statement
    $stmt = $db->prepare("INSERT IGNORE INTO teacher_subjects (teacher_id, subject) VALUES (:teacher_id, :subject)");
    
    // Get all teachers
    $teachers = $db->query("SELECT id FROM teachers")->fetchAll(PDO::FETCH_ASSOC);
    
    // Assign random subjects to teachers (for testing purposes)
    foreach ($teachers as $teacher) {
        // Randomly select 2-4 subjects for each teacher
        $num_subjects = rand(2, 4);
        $teacher_subjects = array_rand(array_flip($default_subjects), $num_subjects);
        
        foreach ((array)$teacher_subjects as $subject) {
            $stmt->execute([
                ':teacher_id' => $teacher['id'],
                ':subject' => $subject
            ]);
        }
    }
    
    echo "Default subjects assigned to teachers successfully!\n";
    
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
} 