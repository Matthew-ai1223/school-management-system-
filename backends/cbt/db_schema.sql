-- Table structure for CBT exams
CREATE TABLE IF NOT EXISTS `cbt_exams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `subject` varchar(100) NOT NULL,
  `class` varchar(50) NOT NULL,
  `duration` int(11) NOT NULL COMMENT 'in minutes',
  `instructions` text DEFAULT NULL,
  `passing_score` int(11) DEFAULT 50,
  `random_questions` tinyint(1) DEFAULT 1,
  `show_result` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for CBT questions
CREATE TABLE IF NOT EXISTS `cbt_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice','true_false','short_answer') NOT NULL DEFAULT 'multiple_choice',
  `marks` int(11) NOT NULL DEFAULT 1,
  `correct_answer` text NOT NULL,
  `option_a` text DEFAULT NULL,
  `option_b` text DEFAULT NULL,
  `option_c` text DEFAULT NULL,
  `option_d` text DEFAULT NULL,
  `explanation` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `exam_id` (`exam_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for CBT exam attempts
CREATE TABLE IF NOT EXISTS `cbt_exam_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `start_time` datetime NOT NULL,
  `submit_time` datetime DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `total_marks` int(11) DEFAULT NULL,
  `marks_obtained` decimal(5,2) DEFAULT NULL,
  `status` enum('in_progress','completed','timed_out') NOT NULL DEFAULT 'in_progress',
  PRIMARY KEY (`id`),
  KEY `exam_id` (`exam_id`),
  KEY `student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for CBT student answers
CREATE TABLE IF NOT EXISTS `cbt_student_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `student_answer` text DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `marks_awarded` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `attempt_id` (`attempt_id`),
  KEY `question_id` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Foreign key constraints
ALTER TABLE `cbt_questions`
  ADD CONSTRAINT `cbt_questions_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `cbt_exams` (`id`) ON DELETE CASCADE;

ALTER TABLE `cbt_exam_attempts`
  ADD CONSTRAINT `cbt_exam_attempts_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `cbt_exams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cbt_exam_attempts_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

ALTER TABLE `cbt_student_answers`
  ADD CONSTRAINT `cbt_student_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `cbt_exam_attempts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cbt_student_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `cbt_questions` (`id`) ON DELETE CASCADE; 