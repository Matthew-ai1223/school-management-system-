-- Add school_name column to report_cards table
ALTER TABLE report_cards ADD COLUMN school_name VARCHAR(50) NOT NULL DEFAULT 'ACE COLLEGE' AFTER class;

-- Add allow_download column to report_cards table
ALTER TABLE report_cards ADD COLUMN allow_download BOOLEAN NOT NULL DEFAULT FALSE AFTER school_name; 