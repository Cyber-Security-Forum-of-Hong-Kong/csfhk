-- Create users table for CSFHK Portal
-- Run this SQL in your database to create the users table

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_name  VARCHAR(50)  NOT NULL,
    account_email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    user_level    ENUM('admin', 'dev', 'guest') NOT NULL DEFAULT 'guest',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (account_email),
    INDEX idx_name (account_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create CTF progress table
CREATE TABLE IF NOT EXISTS ctf_progress (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id           INT UNSIGNED NOT NULL,
    ctf_total_points  INT UNSIGNED NOT NULL DEFAULT 0,
    challenges_done   INT UNSIGNED NOT NULL DEFAULT 0,
    last_updated      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                       ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ctf_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    UNIQUE KEY uniq_user_progress (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create CTF user challenges table (tracks which challenges each user completed)
CREATE TABLE IF NOT EXISTS ctf_user_challenges (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    challenge_id  INT UNSIGNED NOT NULL,
    points       INT UNSIGNED NOT NULL,
    completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_challenge (user_id, challenge_id),
    CONSTRAINT fk_cuc_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_challenge_id (challenge_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

