-- Add class column to exams table
ALTER TABLE exams
ADD COLUMN class VARCHAR(50) NOT NULL AFTER created_by; 