-- Add trust_score column to users table
ALTER TABLE users ADD COLUMN trust_score DECIMAL(5,2) DEFAULT 50.00;

-- Create trust_score_logs table
CREATE TABLE trust_score_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    old_score DECIMAL(5,2) NOT NULL,
    new_score DECIMAL(5,2) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    adjusted_by INT NULL, -- NULL for automatic adjustments, user_id for manual adjustments
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (adjusted_by) REFERENCES users(id)
);

-- Initialize existing users with trust score of 50
UPDATE users SET trust_score = 50.00 WHERE trust_score IS NULL;
