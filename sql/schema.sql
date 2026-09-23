-- RePlate Database Schema Definition
CREATE DATABASE IF NOT EXISTS replate CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE replate;

-- Disable foreign key checks for clean teardown and creation
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS auth_tokens;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS donation_requests;
DROP TABLE IF EXISTS food_donations;
DROP TABLE IF EXISTS food_categories;
DROP TABLE IF EXISTS user_settings;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. Users Table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    org_name VARCHAR(255) NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    normalized_email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('recipient', 'donor', 'admin') NOT NULL DEFAULT 'recipient',
    status ENUM('Active', 'Suspended', 'Pending') NOT NULL DEFAULT 'Active',
    verification_status ENUM('Unverified', 'Pending', 'Verified', 'Rejected') NOT NULL DEFAULT 'Unverified',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role_status (role, status),
    INDEX idx_verification (verification_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. User Settings Table
CREATE TABLE user_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    discretion_mode TINYINT(1) DEFAULT 0,
    phone_number VARCHAR(50) NULL,
    max_rescue_radius_km INT DEFAULT 10,
    vehicle_type VARCHAR(50) DEFAULT 'car',
    pickup_instructions TEXT NULL,
    dietary_tags JSON NULL,
    storage_equipment JSON NULL,
    temp_logging_required TINYINT(1) DEFAULT 0,
    byoc_required TINYINT(1) DEFAULT 0,
    auto_accept_trusted TINYINT(1) DEFAULT 0,
    emergency_broadcasts TINYINT(1) DEFAULT 0,
    expiry_alert_hours INT DEFAULT 3,
    notify_inapp TINYINT(1) DEFAULT 1,
    notify_email TINYINT(1) DEFAULT 1,
    notify_sms TINYINT(1) DEFAULT 0,
    notify_whatsapp TINYINT(1) DEFAULT 0,
    tax_receipts_enabled TINYINT(1) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Food Categories Table
CREATE TABLE food_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Default Food Categories
INSERT INTO food_categories (category_id, name, description) VALUES
(1, 'General Surplus', 'General unparsed surplus food items'),
(2, 'Prepared Meals', 'Cooked and ready-to-eat meals'),
(3, 'Fresh Produce', 'Fruits, vegetables, and farm products'),
(4, 'Bakery Goods', 'Fresh bread, pastries, and baked items'),
(5, 'Packaged & Canned', 'Non-perishable shelf-stable items')
ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description);

-- 4. Food Donations Table
CREATE TABLE food_donations (
    donation_id INT AUTO_INCREMENT PRIMARY KEY,
    donor_id INT NOT NULL,
    category_id INT NULL DEFAULT 1,
    food_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    unit VARCHAR(20) DEFAULT 'kg',
    expiry_date DATETIME NOT NULL,
    pickup_location VARCHAR(255) NOT NULL,
    status ENUM('Available', 'Reserved', 'Collected', 'Cancelled') DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status_expiry (status, expiry_date),
    INDEX idx_donor (donor_id),
    FOREIGN KEY (donor_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES food_categories(category_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Donation Requests Table
CREATE TABLE donation_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    donation_id INT NOT NULL,
    recipient_id INT NOT NULL,
    quantity_requested DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    collection_date DATETIME NULL,
    status ENUM('Pending', 'Approved', 'Rejected', 'Completed') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_donation_recipient (donation_id, recipient_id),
    INDEX idx_recipient_status (recipient_id, status),
    FOREIGN KEY (donation_id) REFERENCES food_donations(donation_id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Audit Logs Table
CREATE TABLE audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    event_type VARCHAR(100) NOT NULL,
    action_details TEXT NOT NULL,
    log_type ENUM('info', 'warning', 'security', 'critical') DEFAULT 'info',
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_log (user_id),
    INDEX idx_type_created (log_type, created_at),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Notifications Table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_unread (user_id, is_read, created_at),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Auth Tokens Table
CREATE TABLE auth_tokens (
    token_id INT AUTO_INCREMENT PRIMARY KEY,
    selector VARCHAR(64) NOT NULL UNIQUE,
    hashed_validator VARCHAR(64) NOT NULL,
    user_id INT NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_tokens (user_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Password Resets Table
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_token (user_id, token),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Reports Table
CREATE TABLE reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    generated_by INT NOT NULL,
    report_type VARCHAR(100) NOT NULL,
    content TEXT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (generated_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;