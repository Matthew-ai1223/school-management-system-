-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 09, 2025 at 11:19 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `school_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_sessions`
--

CREATE TABLE `academic_sessions` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `published_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `expiry_date` date DEFAULT NULL,
  `visibility` enum('Admin','Teacher','Student','Parent','All') DEFAULT 'All',
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `attendance_date` date DEFAULT NULL,
  `status` enum('Present','Absent','Late','Excused') DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `marked_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cbt_exams`
--

CREATE TABLE `cbt_exams` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `total_questions` int(11) DEFAULT NULL,
  `passing_score` float DEFAULT NULL,
  `time_limit` int(11) DEFAULT NULL,
  `start_datetime` datetime DEFAULT NULL,
  `end_datetime` datetime DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cbt_options`
--

CREATE TABLE `cbt_options` (
  `id` int(11) NOT NULL,
  `question_id` int(11) DEFAULT NULL,
  `option_text` text NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cbt_questions`
--

CREATE TABLE `cbt_questions` (
  `id` int(11) NOT NULL,
  `exam_id` int(11) DEFAULT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('Multiple Choice','True/False','Short Answer','Essay') DEFAULT NULL,
  `marks` float DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cbt_student_answers`
--

CREATE TABLE `cbt_student_answers` (
  `id` int(11) NOT NULL,
  `student_exam_id` int(11) DEFAULT NULL,
  `question_id` int(11) DEFAULT NULL,
  `selected_option_id` int(11) DEFAULT NULL,
  `text_answer` text DEFAULT NULL,
  `marks_awarded` float DEFAULT NULL,
  `evaluated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cbt_student_exams`
--

CREATE TABLE `cbt_student_exams` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `exam_id` int(11) DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `score` float DEFAULT NULL,
  `status` enum('Pending','In Progress','Completed','Evaluated') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `section` varchar(10) DEFAULT NULL,
  `room_number` varchar(20) DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `class_teacher_id` int(11) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `class_subjects`
--

CREATE TABLE `class_subjects` (
  `id` int(11) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exams`
--

CREATE TABLE `exams` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `term` enum('First','Second','Third') DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fees`
--

CREATE TABLE `fees` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `due_date` date DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `term` enum('First','Second','Third') DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fee_categories`
--

CREATE TABLE `fee_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fee_payments`
--

CREATE TABLE `fee_payments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `fee_id` int(11) DEFAULT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_method` enum('Cash','Bank Transfer','Card','Online') DEFAULT NULL,
  `transaction_id` varchar(50) DEFAULT NULL,
  `receipt_number` varchar(50) DEFAULT NULL,
  `status` enum('Pending','Completed','Failed') DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `files`
--

CREATE TABLE `files` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `filepath` varchar(255) NOT NULL,
  `filetype` varchar(50) DEFAULT NULL,
  `filesize` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `related_to` enum('Student','Teacher','Admin','Class','Subject','Assignment','CBT') DEFAULT NULL,
  `related_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `grades`
--

CREATE TABLE `grades` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `exam_id` int(11) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `marks_obtained` float DEFAULT NULL,
  `max_marks` float DEFAULT NULL,
  `grade` varchar(5) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `entered_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `subject` varchar(100) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `role` enum('admin','teacher','student','parent','all') DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parents`
--

CREATE TABLE `parents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `occupation` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `relation_with_student` enum('Father','Mother','Guardian') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parent_student`
--

CREATE TABLE `parent_student` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `admission_number` varchar(20) NOT NULL,
  `admission_date` date DEFAULT curdate(),
  `date_of_birth` date NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `roll_number` int(11) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `parent_name` varchar(100) NOT NULL,
  `parent_phone` varchar(20) NOT NULL,
  `parent_email` varchar(100) DEFAULT NULL,
  `parent_address` text DEFAULT NULL,
  `previous_school` varchar(100) DEFAULT NULL,
  `registration_number` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `class_type` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `user_id`, `first_name`, `last_name`, `admission_number`, `admission_date`, `date_of_birth`, `gender`, `blood_group`, `class_id`, `roll_number`, `phone`, `address`, `parent_name`, `parent_phone`, `parent_email`, `parent_address`, `previous_school`, `registration_number`, `created_at`, `updated_at`, `class_type`) VALUES
(4, 5, 'Adeniyi', 'Olalekan', 'ADM20250001', '2025-05-08', '2025-04-28', 'Male', '', NULL, NULL, '08185683708', 'ifedapor\r\nNew gbaje', 'majs c', '08185683708', 'olalekanmatthew081@gmail.com', 'ifedapor\r\nNew gbaje', 'loyola', 'adeniyi4846', '2025-05-08 00:40:46', '2025-05-08 00:45:07', 'creche'),
(5, 6, 'Adeniyi', 'Olalekan', 'ADM20250002', '2025-05-08', '2025-04-28', 'Male', '', NULL, NULL, '08185683708', 'ifedapor\r\nNew gbaje', 'majs c', '08185683708', 'olalekanmatthew08@gmail.com', 'ifedapor\r\nNew gbaje', 'loyola', 'adeniyi5288', '2025-05-08 00:48:10', '2025-05-08 00:48:10', 'creche'),
(6, 7, 'Adeniyi', 'Olalekan', 'ADM20250003', '2025-05-08', '2025-02-13', 'Male', '', NULL, NULL, '08185683708', 'ifedapor\r\nNew gbaje', 'majs c', '08185683708', 'olalekanmatthew081@gmail.com', 'ifedapor\r\nNew gbaje', 'loyola', 'adeniyi6341', '2025-05-08 01:05:42', '2025-05-08 01:05:42', 'playgroup'),
(7, 8, 'Adeniyi', 'Olalekan', 'ADM20250004', '2025-05-08', '2025-01-22', 'Male', '', NULL, NULL, '08185683708', 'ifedapor\r\nNew gbaje', 'majs c', '08185683708', 'olalekanmatthew081@gmail.com', 'ifedapor\r\nNew gbaje', 'loyola', 'adeniyi6858', '2025-05-08 01:14:18', '2025-05-08 01:14:18', 'nursery'),
(8, 9, 'Adeniyi', 'Olalekan', 'ADM20250005', '2025-05-08', '2025-05-05', 'Male', '', NULL, NULL, '08185683708', 'ifedapor\r\nNew gbaje', 'majs c', '08185683708', 'olalekanadeniyi081@gmail.com', 'ifedapor\r\nNew gbaje', 'loyola', 'adeniyi7133', '2025-05-08 01:18:53', '2025-05-08 01:18:53', 'nursery'),
(9, 10, 'Adeniyi', 'Olalekan', 'ADM20250006', '2025-05-08', '2025-05-06', 'Female', '', NULL, NULL, '08185683708', 'ifedapor\r\nNew gbaje', 'ascasc', '08110575847', 'olalekanmatthew081@gmail.com', 'ifedapor\r\nNew gbaje', 'loyola', 'adeniyi7351', '2025-05-08 01:22:32', '2025-05-08 01:22:32', 'primary'),
(10, 11, 'Adeniyi', 'Olalekan', 'ADM20250007', '2025-05-08', '2025-04-28', 'Male', '', NULL, NULL, '08185683708', 'ifedapor\r\nNew gbaje', 'ascasc', '08185683708', 'olalekanadeniyi081@gmail.com', 'ifedapor\r\nNew gbaje', 'loyola', 'adeniyi7570', '2025-05-08 01:26:10', '2025-05-08 01:26:10', 'creche'),
(11, 12, 'Adeniyi', 'Olalekan', 'ADM20250008', '2025-05-08', '2025-05-03', 'Male', '', NULL, NULL, '08185683708', 'ifedapor\r\nNew gbaje', 'ascasc', '08185683708', 'olalekanadeniyi081@gmail.com', 'ifedapor\r\nNew gbaje', 'loyola', 'adeniyi8640', '2025-05-08 01:44:01', '2025-05-08 01:44:01', 'nursery'),
(12, 13, 'Adeniyi', 'Olalekan', 'ADM20250009', '2025-05-08', '2025-05-04', 'Male', '', NULL, NULL, '08185683708', 'ifedapor\r\nNew gbaje', 'ascasc', '08110575847', 'olalekanmatthew081@gmail.com', 'ifedapor\r\nNew gbaje', 'loyola', 'adeniyi9032', '2025-05-08 01:50:33', '2025-05-08 01:50:33', 'primary'),
(13, 14, 'Adeniyi', 'Olalekan', 'ADM20250010', '2025-05-08', '2025-05-04', 'Female', '', NULL, NULL, '08185683708', 'ifedapor\r\nNew gbaje', 'ascasc', '08185683708', 'olalekanmatthew081@gmail.com', 'ifedapor\r\nNew gbaje', 'loyola', 'adeniyi9184', '2025-05-08 01:53:05', '2025-05-08 01:53:05', 'creche'),
(14, 15, 'Adeniyi', 'Olalekan', 'ADM20250011', '2025-05-08', '2025-04-27', 'Male', '', NULL, NULL, '08185683708', 'ifedapor\r\nNew gbaje', 'ascascs', '08185683708', 'olalekanadeniyi081@gmail.com', 'ifedapor\r\nNew gbaje', 'loyola', 'adeniyi9681', '2025-05-08 02:01:22', '2025-05-08 02:01:22', 'jss'),
(15, 16, 'Adeniyi', 'Olalekan', 'ADM20250012', '2025-05-08', '2025-04-29', 'Male', '', NULL, NULL, '08185683708', 'ifedapor\r\nNew gbaje', 'ascascs', '08110575847', 'olalekanadeniyi081@gmail.com', 'ifedapor\r\nNew gbaje', 'loyola', 'adeniyi0167', '2025-05-08 02:09:27', '2025-05-08 02:09:27', 'primary'),
(16, 17, 'Adeniyi', 'Olalekan', 'ADM20250013', '2025-05-08', '2025-05-04', 'Female', '', NULL, NULL, '08185683708', 'ifedapor\r\nNew gbaje', 'ascascs', '08110575847', 'ola@gmail.com', 'ifedapor', 'loyola', 'adeniyi0666', '2025-05-08 02:17:47', '2025-05-08 02:17:47', 'primary'),
(17, 18, 'Adeniyi', 'Olalekan', 'ADM20250014', '2025-05-08', '2025-05-04', 'Male', '', NULL, NULL, '08185683708', 'ifedapor\r\nNew gbaje', 'ascascs', '08185683708', 'olalekanadeniyi081@gmail.com', 'ifedapor\r\nNew gbaje', 'loyola', 'adeniyi1666', '2025-05-08 02:34:26', '2025-05-08 02:34:26', 'sss'),
(18, 19, 'Adeniyi', 'Olalekan', 'ADM20250015', '2025-05-08', '2025-05-04', 'Male', '', NULL, NULL, '08185683708', 'ifedapor\r\nNew gbaje', 'ascascs', '08185683708', 'olalekanadeniyi081@gmail.com', 'ifedapor\r\nNew gbaje', 'loyola', 'adeniyi1924', '2025-05-08 02:38:45', '2025-05-08 02:38:45', 'sss'),
(19, 20, 'Adeniyi', 'Olalekan', 'ADM20250016', '2025-05-08', '2025-05-04', 'Male', '', NULL, NULL, '08185683708', 'ifedapor\r\nNew gbaje', 'ascascs', '08110575847', 'olalekanadeniyi081@gmail.com', 'ifedapor\r\nNew gbaje', 'loyola', 'adeniyi2269', '2025-05-08 02:44:29', '2025-05-08 02:44:29', 'sss'),
(20, 21, 'Adeniyi', 'Olalekan', 'ADM20250017', '2025-05-08', '2025-04-29', 'Male', '', NULL, NULL, '08185683708', 'ifedapor\r\nNew gbaje', 'ascascs', '08110575847', 'olalekanadeniyi081@gmail.com', 'ifedapor\r\nNew gbaje', 'loyola', 'adeniyi2633', '2025-05-08 02:50:34', '2025-05-08 02:50:34', 'primary'),
(21, 22, 'Adeniyi', 'Olalekan', 'ADM20250018', '2025-05-08', '2025-05-06', 'Male', '', NULL, NULL, '08185683708', 'ifedapor\r\nNew gbaje', 'ascascs', '08185683708', 'olalekanmatthew081@gmail.com', 'ifedapor\r\nNew gbaje', 'loyola', 'adeniyi3094', '2025-05-08 02:58:14', '2025-05-08 02:58:14', 'playgroup'),
(22, 23, 'Adeniyi', 'Olalekan', 'ADM20250019', '2025-05-08', '2025-05-05', 'Male', '', NULL, NULL, '08185683708', 'ifedapor\r\nNew gbaje', 'ascascs', '08110575847', 'ola@gmail.com', 'ifedapor', 'loyola', 'ADM20250019', '2025-05-08 03:08:25', '2025-05-08 03:08:25', 'nursery');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `credit_hours` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `joining_date` date DEFAULT NULL,
  `qualification` varchar(100) DEFAULT NULL,
  `experience` float DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `timetable`
--

CREATE TABLE `timetable` (
  `id` int(11) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `room_number` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','teacher','student','parent') NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `profile_image`, `created_at`, `updated_at`) VALUES
(5, 'adeniyi.olalekan', '$2y$12$w9TkfR6aXy448QPEPsKF1Ov843cmxHAykxqwAo8Nrb/JfW5WqzXXW', 'olalekanadeniyi081@gmail.com', 'student', NULL, '2025-05-08 00:40:46', '2025-05-08 00:40:46'),
(6, 'adeniyi.olalekan1', '$2y$12$ersIU6zmRYu3HJQpAE7/nejkTMe0Ufn4NF6z5Xotlyb7lEVtmU.sy', 'olalekanadeniyi08@gmail.com', 'student', NULL, '2025-05-08 00:48:09', '2025-05-08 00:48:09'),
(7, 'adeniyi.olalekan2', '$2y$12$QkM/FIw/EwbtMfEkWGvEwuFoSpK7cTMn9XpSi10NaeU78QDUordUG', 'olalekanmatthew0@gmail.com', 'student', NULL, '2025-05-08 01:05:42', '2025-05-08 01:05:42'),
(8, 'adeniyi.olalekan3', '$2y$12$0zPtYjClFBMq0Drmbyrjbuxw3XF/AbIUyRjoP475ALpJ.SWocWE1y', 'olalekanmatthew01@gmail.com', 'student', NULL, '2025-05-08 01:14:18', '2025-05-08 01:14:18'),
(9, 'adeniyi.olalekan4', '$2y$12$f4T4slF9/MKquQ69/0OsDOwwqBWGa6I5a.Pj3ubfECiXHcakMobv6', 'olalekanmattw081@gmail.com', 'student', NULL, '2025-05-08 01:18:53', '2025-05-08 01:18:53'),
(10, 'adeniyi.olalekan5', '$2y$12$c1fSHG0xZInaFbEaRukOMOreLc6QVDXHRIH6.9Zv.Lu5n8myIzKzG', 'olaltthew081@gmail.com', 'student', NULL, '2025-05-08 01:22:31', '2025-05-08 01:22:31'),
(11, 'adeniyi.olalekan6', '$2y$12$p.846lFyMUTjCcyyFsw4e.C453QqzoQfK3Sy8X2NQxOa0ZPj0ZHPm', 'olalekanmatthew081@gmail.com', 'student', NULL, '2025-05-08 01:26:10', '2025-05-08 01:26:10'),
(12, 'adeniyi.olalekan7', '$2y$12$sgrMN/2c1NNfr.Jvav6rnePfiAVcaJhVomFDjd2AOJTwk9cNNJ5Wi', 'olaw081@gmail.com', 'student', NULL, '2025-05-08 01:44:01', '2025-05-08 01:44:01'),
(13, 'adeniyi.olalekan8', '$2y$12$FJWtKGAsDaY3jiM.BrcoI.pkqL5mA39e2Ee6TMQgk0y6401lif0Ju', 'olalekanmatth1@gmail.com', 'student', NULL, '2025-05-08 01:50:33', '2025-05-08 01:50:33'),
(14, 'adeniyi.olalekan9', '$2y$12$NBP5QXV6JquS6TmvVbkF/.84iiBL3AQCvEgr9NfJcQZDjN5fBkd6C', 'anmatthew081@gmail.com', 'student', NULL, '2025-05-08 01:53:05', '2025-05-08 01:53:05'),
(15, 'adeniyi.olalekan10', '$2y$12$pc5UAZLdvg7GR27ZjKtUEOsjih.OblBYNsPMkPTrdDSDNazcBMmEe', 'olal1@gmail.com', 'student', NULL, '2025-05-08 02:01:22', '2025-05-08 02:01:22'),
(16, 'adeniyi.olalekan11', '$2y$12$mj.KLFKNGE8RxZMfHLIJ5uw6NNJ/9/LS16ogYZ5j9DD3gT.dN/L1S', 'ola81@gmail.com', 'student', NULL, '2025-05-08 02:09:27', '2025-05-08 02:09:27'),
(17, 'adeniyi.olalekan12', '$2y$12$IORoUyacypCYYFolRkwZUOSVm3WR2ylEcj1Zzk8T5J.WqSeDZuWo6', 'gw081@gmail.com', 'student', NULL, '2025-05-08 02:17:47', '2025-05-08 02:17:47'),
(18, 'adeniyi.olalekan13', '$2y$12$mvEbU3mriYcGnPQr1x5b8uYzzoapRROZakW5z5sxofXOFD6dzQm5a', 'olaleka1@gmail.com', 'student', NULL, '2025-05-08 02:34:26', '2025-05-08 02:34:26'),
(19, 'adeniyi.olalekan14', '$2y$12$FRkpGJO/6N4/4.SqnDQAc.JPIfnByhZQ8eQ2fzF1yXZT03u9dvUmO', 'olalekjjjjdfa1@gmail.com', 'student', NULL, '2025-05-08 02:38:45', '2025-05-08 02:38:45'),
(20, 'adeniyi.olalekan15', '$2y$12$xkpdrtaqbyp/oJ7US9JetepHmcnZ4n7M8n4S8T1Y6menPmDV7274u', 'olalekthew081@gmail.com', 'student', NULL, '2025-05-08 02:44:29', '2025-05-08 02:44:29'),
(21, 'adeniyi.olalekan16', '$2y$12$T3mk4Y5Q5X8/b/Ob3iO5n.WjDMSeO/Z8bSoWFlf/hVNOWRvzZGFtG', 'ola1@gmail.com', 'student', NULL, '2025-05-08 02:50:34', '2025-05-08 02:50:34'),
(22, 'adeniyi.olalekan17', '$2y$12$ee7zEhRbMf0X1BlGkL2s4.t99mrSLg4jZQmdrxI6zCQgRsjvS3wlG', 'onnnnla@gmail.com', 'student', NULL, '2025-05-08 02:58:14', '2025-05-08 02:58:14'),
(23, 'adeniyi.olalekan18', '$2y$12$HcjmcBPyD.ZNSdmC.XZKtu/Rw5KsfPHw7lyNr4HmE.1Iv1zYvFptm', 'sfretse081@gmail.com', 'student', NULL, '2025-05-08 03:08:25', '2025-05-08 03:08:25');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_sessions`
--
ALTER TABLE `academic_sessions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `marked_by` (`marked_by`);

--
-- Indexes for table `cbt_exams`
--
ALTER TABLE `cbt_exams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `cbt_options`
--
ALTER TABLE `cbt_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `cbt_questions`
--
ALTER TABLE `cbt_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `exam_id` (`exam_id`);

--
-- Indexes for table `cbt_student_answers`
--
ALTER TABLE `cbt_student_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_exam_id` (`student_exam_id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `selected_option_id` (`selected_option_id`),
  ADD KEY `evaluated_by` (`evaluated_by`);

--
-- Indexes for table `cbt_student_exams`
--
ALTER TABLE `cbt_student_exams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `exam_id` (`exam_id`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_teacher_id` (`class_teacher_id`);

--
-- Indexes for table `class_subjects`
--
ALTER TABLE `class_subjects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `exams`
--
ALTER TABLE `exams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `fees`
--
ALTER TABLE `fees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `class_id` (`class_id`);

--
-- Indexes for table `fee_categories`
--
ALTER TABLE `fee_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `fee_payments`
--
ALTER TABLE `fee_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `fee_id` (`fee_id`),
  ADD KEY `received_by` (`received_by`);

--
-- Indexes for table `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `exam_id` (`exam_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `entered_by` (`entered_by`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `parents`
--
ALTER TABLE `parents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `parent_student`
--
ALTER TABLE `parent_student`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `admission_number` (`admission_number`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `class_id` (`class_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `timetable`
--
ALTER TABLE `timetable`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_sessions`
--
ALTER TABLE `academic_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cbt_exams`
--
ALTER TABLE `cbt_exams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cbt_options`
--
ALTER TABLE `cbt_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cbt_questions`
--
ALTER TABLE `cbt_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cbt_student_answers`
--
ALTER TABLE `cbt_student_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cbt_student_exams`
--
ALTER TABLE `cbt_student_exams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `class_subjects`
--
ALTER TABLE `class_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exams`
--
ALTER TABLE `exams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fees`
--
ALTER TABLE `fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fee_categories`
--
ALTER TABLE `fee_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fee_payments`
--
ALTER TABLE `fee_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `files`
--
ALTER TABLE `files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `grades`
--
ALTER TABLE `grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parents`
--
ALTER TABLE `parents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parent_student`
--
ALTER TABLE `parent_student`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `timetable`
--
ALTER TABLE `timetable`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admins`
--
ALTER TABLE `admins`
  ADD CONSTRAINT `admins_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_4` FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cbt_exams`
--
ALTER TABLE `cbt_exams`
  ADD CONSTRAINT `cbt_exams_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cbt_exams_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cbt_exams_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cbt_options`
--
ALTER TABLE `cbt_options`
  ADD CONSTRAINT `cbt_options_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `cbt_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cbt_questions`
--
ALTER TABLE `cbt_questions`
  ADD CONSTRAINT `cbt_questions_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `cbt_exams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cbt_student_answers`
--
ALTER TABLE `cbt_student_answers`
  ADD CONSTRAINT `cbt_student_answers_ibfk_1` FOREIGN KEY (`student_exam_id`) REFERENCES `cbt_student_exams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cbt_student_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `cbt_questions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cbt_student_answers_ibfk_3` FOREIGN KEY (`selected_option_id`) REFERENCES `cbt_options` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `cbt_student_answers_ibfk_4` FOREIGN KEY (`evaluated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cbt_student_exams`
--
ALTER TABLE `cbt_student_exams`
  ADD CONSTRAINT `cbt_student_exams_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cbt_student_exams_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `cbt_exams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`class_teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `class_subjects`
--
ALTER TABLE `class_subjects`
  ADD CONSTRAINT `class_subjects_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_subjects_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_subjects_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `exams`
--
ALTER TABLE `exams`
  ADD CONSTRAINT `exams_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `fees`
--
ALTER TABLE `fees`
  ADD CONSTRAINT `fees_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `fee_categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fees_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fee_payments`
--
ALTER TABLE `fee_payments`
  ADD CONSTRAINT `fee_payments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fee_payments_ibfk_2` FOREIGN KEY (`fee_id`) REFERENCES `fees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fee_payments_ibfk_3` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `files`
--
ALTER TABLE `files`
  ADD CONSTRAINT `files_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `grades`
--
ALTER TABLE `grades`
  ADD CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grades_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grades_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grades_ibfk_4` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grades_ibfk_5` FOREIGN KEY (`entered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `parents`
--
ALTER TABLE `parents`
  ADD CONSTRAINT `parents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `parent_student`
--
ALTER TABLE `parent_student`
  ADD CONSTRAINT `parent_student_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `parent_student_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `timetable`
--
ALTER TABLE `timetable`
  ADD CONSTRAINT `timetable_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `timetable_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `timetable_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
