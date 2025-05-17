-- Add Class/Level field to the students table if it doesn't exist
ALTER TABLE students 
ADD COLUMN class VARCHAR(50) NULL AFTER email;

-- Add Class/Level field to registration form fields for kiddies
INSERT INTO registration_form_fields 
(field_label, field_type, field_order, required, options, registration_type, field_category, is_active) 
SELECT 'Class/Level', 'text', 10, 1, '', 'kiddies', 'student_info', 1
WHERE NOT EXISTS (
    SELECT 1 FROM registration_form_fields 
    WHERE is_active = 1 
    AND registration_type = 'kiddies' 
    AND (field_label LIKE '%class%' OR field_label LIKE '%level%')
);

-- Add Class/Level field to registration form fields for college
INSERT INTO registration_form_fields 
(field_label, field_type, field_order, required, options, registration_type, field_category, is_active) 
SELECT 'Class/Level', 'text', 10, 1, '', 'college', 'student_info', 1
WHERE NOT EXISTS (
    SELECT 1 FROM registration_form_fields 
    WHERE is_active = 1 
    AND registration_type = 'college' 
    AND (field_label LIKE '%class%' OR field_label LIKE '%level%')
);

-- Update an example student's class value
UPDATE students 
SET class = CONCAT('Example Class ', YEAR(CURRENT_DATE)) 
WHERE id = (SELECT id FROM (SELECT id FROM students ORDER BY id DESC LIMIT 1) as temp_table)
AND (class IS NULL OR class = ''); 