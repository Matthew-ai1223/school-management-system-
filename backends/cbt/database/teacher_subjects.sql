-- Create teacher_subjects table
CREATE TABLE IF NOT EXISTS teacher_subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    subject VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_teacher_subject (teacher_id, subject)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add subject column to exams table if it doesn't exist
ALTER TABLE exams ADD COLUMN IF NOT EXISTS subject VARCHAR(100) NOT NULL AFTER title; 