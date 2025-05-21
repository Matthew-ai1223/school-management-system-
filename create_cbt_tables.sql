-- Create subjects table if it doesn't exist
CREATE TABLE IF NOT EXISTS `subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create class_subjects table if it doesn't exist
CREATE TABLE IF NOT EXISTS `class_subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `subject_id` (`subject_id`),
  KEY `teacher_id` (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create cbt_exams table if it doesn't exist
CREATE TABLE IF NOT EXISTS `cbt_exams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `subject_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `total_questions` int(11) NOT NULL,
  `passing_score` float NOT NULL DEFAULT 50,
  `time_limit` int(11) NOT NULL COMMENT 'in minutes',
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `instructions` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `show_results` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `subject_id` (`subject_id`),
  KEY `class_id` (`class_id`),
  KEY `teacher_id` (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create cbt_questions table if it doesn't exist
CREATE TABLE IF NOT EXISTS `cbt_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('Multiple Choice','True/False') NOT NULL,
  `marks` float NOT NULL DEFAULT 1,
  `correct_answer` varchar(255) DEFAULT NULL COMMENT 'For True/False questions',
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `exam_id` (`exam_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create cbt_options table if it doesn't exist
CREATE TABLE IF NOT EXISTS `cbt_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) NOT NULL,
  `option_text` varchar(255) NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `question_id` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create cbt_student_exams table if it doesn't exist
CREATE TABLE IF NOT EXISTS `cbt_student_exams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `status` enum('Pending','In Progress','Completed') NOT NULL DEFAULT 'Pending',
  `score` float DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `exam_id` (`exam_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create cbt_student_answers table if it doesn't exist
CREATE TABLE IF NOT EXISTS `cbt_student_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_exam_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer_text` text DEFAULT NULL,
  `selected_options` text DEFAULT NULL COMMENT 'Comma separated option ids',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_exam_id` (`student_exam_id`),
  KEY `question_id` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert some sample subjects if the table is empty
INSERT INTO `subjects` (`name`, `code`, `description`)
SELECT 'Mathematics', 'MATH101', 'Basic mathematics including algebra, geometry, and calculus'
WHERE NOT EXISTS (SELECT 1 FROM `subjects` WHERE `code` = 'MATH101');

INSERT INTO `subjects` (`name`, `code`, `description`)
SELECT 'English Language', 'ENG101', 'English grammar, comprehension, and literature'
WHERE NOT EXISTS (SELECT 1 FROM `subjects` WHERE `code` = 'ENG101');

INSERT INTO `subjects` (`name`, `code`, `description`)
SELECT 'Physics', 'PHY101', 'Study of matter, energy, and the interaction between them'
WHERE NOT EXISTS (SELECT 1 FROM `subjects` WHERE `code` = 'PHY101');

INSERT INTO `subjects` (`name`, `code`, `description`)
SELECT 'Chemistry', 'CHEM101', 'Study of matter, its properties, and reactions'
WHERE NOT EXISTS (SELECT 1 FROM `subjects` WHERE `code` = 'CHEM101');

INSERT INTO `subjects` (`name`, `code`, `description`)
SELECT 'Biology', 'BIO101', 'Study of living organisms and their interactions'
WHERE NOT EXISTS (SELECT 1 FROM `subjects` WHERE `code` = 'BIO101'); 