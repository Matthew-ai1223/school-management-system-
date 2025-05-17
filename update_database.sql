-- Add field_category column to registration_form_fields table if it doesn't exist
ALTER TABLE `registration_form_fields` 
ADD COLUMN IF NOT EXISTS `field_category` VARCHAR(50) NOT NULL DEFAULT 'student_info' AFTER `options`;

-- Update existing fields to categorize them properly
UPDATE `registration_form_fields` SET `field_category` = 'student_info' 
WHERE `field_label` IN ('First Name', 'Last Name', 'Date of Birth', 'Gender', 'Nationality', 'State', 'Contact Address', 'Email')
  OR `field_label` LIKE '%name%' 
  OR `field_label` LIKE '%birth%'
  OR `field_label` LIKE '%gender%'
  OR `field_label` LIKE '%nationality%'
  OR `field_label` LIKE '%state%'
  OR `field_label` LIKE '%address%'
  OR `field_label` LIKE '%email%';

UPDATE `registration_form_fields` SET `field_category` = 'parent_info' 
WHERE `field_label` IN ('Father\'s Name', 'Father\'s Occupation', 'Father\'s Office Address', 'Father\'s Contact Phone Number(s)',
                       'Mother\'s Name', 'Mother\'s Occupation', 'Mother\'s Office Address', 'Mother\'s Contact Phone Number(s)')
  OR `field_label` LIKE '%father%'
  OR `field_label` LIKE '%mother%'
  OR `field_label` LIKE '%parent%';

UPDATE `registration_form_fields` SET `field_category` = 'guardian_info' 
WHERE `field_label` IN ('Guardian\'s Name', 'Guardian\'s Occupation', 'Guardian\'s Office Address', 'Guardian\'s Contact Phone Number')
  OR `field_label` LIKE '%guardian%';

UPDATE `registration_form_fields` SET `field_category` = 'medical_info' 
WHERE `field_label` IN ('Allergies', 'Blood Group', 'Genotype')
  OR `field_label` LIKE '%allergies%'
  OR `field_label` LIKE '%blood%'
  OR `field_label` LIKE '%genotype%'
  OR `field_label` LIKE '%medical%';

-- Remove all existing fields to start fresh
-- DELETE FROM `registration_form_fields` WHERE 1=1;

-- Insert the new fields for Student Information
INSERT IGNORE INTO `registration_form_fields` 
(`field_label`, `field_type`, `field_order`, `required`, `options`, `registration_type`, `field_category`, `is_active`) VALUES
('First Name', 'text', 1, 1, '', 'kiddies', 'student_info', 1),
('Last Name', 'text', 2, 1, '', 'kiddies', 'student_info', 1),
('Date of Birth', 'date', 3, 1, '', 'kiddies', 'student_info', 1),
('Gender', 'select', 4, 1, 'Male,Female', 'kiddies', 'student_info', 1),
('Nationality', 'text', 5, 1, '', 'kiddies', 'student_info', 1),
('State', 'text', 6, 1, '', 'kiddies', 'student_info', 1),
('Contact Address', 'textarea', 7, 1, '', 'kiddies', 'student_info', 1),
('Email', 'email', 8, 0, '', 'kiddies', 'student_info', 1),

-- Parent/Guardian Information
('Father\'s Name', 'text', 9, 1, '', 'kiddies', 'parent_info', 1),
('Father\'s Occupation', 'text', 10, 1, '', 'kiddies', 'parent_info', 1),
('Father\'s Office Address', 'textarea', 11, 1, '', 'kiddies', 'parent_info', 1),
('Father\'s Contact Phone Number(s)', 'text', 12, 1, '', 'kiddies', 'parent_info', 1),
('Mother\'s Name', 'text', 13, 1, '', 'kiddies', 'parent_info', 1),
('Mother\'s Occupation', 'text', 14, 1, '', 'kiddies', 'parent_info', 1),
('Mother\'s Office Address', 'textarea', 15, 1, '', 'kiddies', 'parent_info', 1),
('Mother\'s Contact Phone Number(s)', 'text', 16, 1, '', 'kiddies', 'parent_info', 1),

-- Guardian Info (Optional)
('Guardian Name', 'text', 17, 0, '', 'kiddies', 'guardian_info', 1),
('Guardian Occupation', 'text', 18, 0, '', 'kiddies', 'guardian_info', 1),
('Guardian Office Address', 'textarea', 19, 0, '', 'kiddies', 'guardian_info', 1),
('Guardian Contact Phone Number', 'text', 20, 0, '', 'kiddies', 'guardian_info', 1),
('Child Lives With', 'checkbox', 21, 1, 'Both Parents,Mother,Father,Guardian', 'kiddies', 'guardian_info', 1),

-- Medical Background (Optional)
('Allergies', 'textarea', 22, 0, '', 'kiddies', 'medical_info', 1),
('Blood Group', 'select', 23, 0, 'A+,A-,B+,B-,AB+,AB-,O+,O-', 'kiddies', 'medical_info', 1),
('Genotype', 'select', 24, 0, 'AA,AS,SS,AC,SC', 'kiddies', 'medical_info', 1);

-- Insert the same fields for college type
INSERT IGNORE INTO `registration_form_fields` 
(`field_label`, `field_type`, `field_order`, `required`, `options`, `registration_type`, `field_category`, `is_active`) VALUES
('First Name', 'text', 1, 1, '', 'college', 'student_info', 1),
('Last Name', 'text', 2, 1, '', 'college', 'student_info', 1),
('Date of Birth', 'date', 3, 1, '', 'college', 'student_info', 1),
('Gender', 'select', 4, 1, 'Male,Female', 'college', 'student_info', 1),
('Nationality', 'text', 5, 1, '', 'college', 'student_info', 1),
('State', 'text', 6, 1, '', 'college', 'student_info', 1),
('Contact Address', 'textarea', 7, 1, '', 'college', 'student_info', 1),
('Email', 'email', 8, 0, '', 'college', 'student_info', 1),

-- Parent/Guardian Information
('Father\'s Name', 'text', 9, 1, '', 'college', 'parent_info', 1),
('Father\'s Occupation', 'text', 10, 1, '', 'college', 'parent_info', 1),
('Father\'s Office Address', 'textarea', 11, 1, '', 'college', 'parent_info', 1),
('Father\'s Contact Phone Number(s)', 'text', 12, 1, '', 'college', 'parent_info', 1),
('Mother\'s Name', 'text', 13, 1, '', 'college', 'parent_info', 1),
('Mother\'s Occupation', 'text', 14, 1, '', 'college', 'parent_info', 1),
('Mother\'s Office Address', 'textarea', 15, 1, '', 'college', 'parent_info', 1),
('Mother\'s Contact Phone Number(s)', 'text', 16, 1, '', 'college', 'parent_info', 1),

-- Guardian Info (Optional)
('Guardian Name', 'text', 17, 0, '', 'college', 'guardian_info', 1),
('Guardian Occupation', 'text', 18, 0, '', 'college', 'guardian_info', 1),
('Guardian Office Address', 'textarea', 19, 0, '', 'college', 'guardian_info', 1),
('Guardian Contact Phone Number', 'text', 20, 0, '', 'college', 'guardian_info', 1),
('Child Lives With', 'checkbox', 21, 1, 'Both Parents,Mother,Father,Guardian', 'college', 'guardian_info', 1),

-- Medical Background (Optional)
('Allergies', 'textarea', 22, 0, '', 'college', 'medical_info', 1),
('Blood Group', 'select', 23, 0, 'A+,A-,B+,B-,AB+,AB-,O+,O-', 'college', 'medical_info', 1),
('Genotype', 'select', 24, 0, 'AA,AS,SS,AC,SC', 'college', 'medical_info', 1);

-- Update students table structure to ensure all fields exist
ALTER TABLE `students`
ADD COLUMN IF NOT EXISTS `date_of_birth` DATE NULL,
ADD COLUMN IF NOT EXISTS `gender` VARCHAR(20) NULL, 
ADD COLUMN IF NOT EXISTS `nationality` VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS `state` VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS `contact_address` TEXT NULL,
ADD COLUMN IF NOT EXISTS `email` VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS `father_s_name` VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS `father_s_occupation` VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS `father_s_office_address` TEXT NULL,
ADD COLUMN IF NOT EXISTS `father_s_contact_phone_number_s_` VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS `mother_s_name` VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS `mother_s_occupation` VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS `mother_s_office_address` TEXT NULL,
ADD COLUMN IF NOT EXISTS `mother_s_contact_phone_number_s_` VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS `guardian_name` VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS `guardian_occupation` VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS `guardian_office_address` TEXT NULL,
ADD COLUMN IF NOT EXISTS `guardian_contact_phone_number` VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS `child_lives_with` VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS `allergies` TEXT NULL,
ADD COLUMN IF NOT EXISTS `blood_group` VARCHAR(10) NULL,
ADD COLUMN IF NOT EXISTS `genotype` VARCHAR(10) NULL; 