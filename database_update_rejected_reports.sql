-- Add status column to reports table for tracking rejected reports
ALTER TABLE reports ADD COLUMN status ENUM('pending','verified','rejected') DEFAULT 'pending';

-- Create index for status column
ALTER TABLE reports ADD INDEX idx_status (status);

-- Update existing reports to have appropriate status
UPDATE reports SET status = 'verified' WHERE is_verified = 1;
UPDATE reports SET status = 'pending' WHERE is_verified = 0;
