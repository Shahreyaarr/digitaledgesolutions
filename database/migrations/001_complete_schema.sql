-- ============================================================
-- DIGITALEDGESOLUTIONS - COMPLETE DATABASE SCHEMA
-- Version: 1.0.0
-- Description: Production-ready schema for EdTech + Software Dev ecosystem
-- ============================================================

-- Drop existing tables if they exist (for clean setup)
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- CORE USER MANAGEMENT TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS `users` (
    `user_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('super_admin', 'admin', 'sub_admin', 'employee', 'student', 'corporate_client', 'guest') DEFAULT 'guest',
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20),
    `profile_image` VARCHAR(500),
    `country` VARCHAR(100),
    `city` VARCHAR(100),
    `timezone` VARCHAR(50) DEFAULT 'UTC',
    `language` VARCHAR(10) DEFAULT 'en',
    `date_of_birth` DATE,
    `gender` ENUM('male', 'female', 'other', 'prefer_not_to_say'),
    `bio` TEXT,
    `is_active` TINYINT(1) DEFAULT 1,
    `email_verified` TINYINT(1) DEFAULT 0,
    `phone_verified` TINYINT(1) DEFAULT 0,
    `two_factor_enabled` TINYINT(1) DEFAULT 0,
    `two_factor_secret` VARCHAR(32),
    `blockchain_wallet` VARCHAR(255),
    `last_login` DATETIME,
    `login_attempts` INT DEFAULT 0,
    `locked_until` DATETIME,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME,
    INDEX `idx_email` (`email`),
    INDEX `idx_role` (`role`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_social_accounts` (
    `social_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `provider` ENUM('google', 'linkedin', 'github', 'facebook') NOT NULL,
    `provider_user_id` VARCHAR(255) NOT NULL,
    `access_token` TEXT,
    `refresh_token` TEXT,
    `email` VARCHAR(255),
    `profile_data` JSON,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_provider_user` (`provider`, `provider_user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    INDEX `idx_user_provider` (`user_id`, `provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_sessions` (
    `session_id` VARCHAR(255) PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `token` TEXT NOT NULL,
    `refresh_token` TEXT,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `device_info` JSON,
    `location_data` JSON,
    `is_valid` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NOT NULL,
    `last_activity` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_expires` (`expires_at`),
    INDEX `idx_valid` (`is_valid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_devices` (
    `device_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `device_name` VARCHAR(255),
    `device_type` ENUM('mobile', 'tablet', 'desktop', 'other'),
    `os` VARCHAR(100),
    `browser` VARCHAR(100),
    `fingerprint` VARCHAR(255),
    `is_trusted` TINYINT(1) DEFAULT 0,
    `last_used` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    INDEX `idx_user_device` (`user_id`, `fingerprint`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `log_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(100) NOT NULL,
    `entity_id` BIGINT UNSIGNED,
    `old_values` JSON,
    `new_values` JSON,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ROLE & PERMISSION SYSTEM
-- ============================================================

CREATE TABLE IF NOT EXISTS `roles` (
    `role_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `role_name` VARCHAR(50) NOT NULL UNIQUE,
    `role_slug` VARCHAR(50) NOT NULL UNIQUE,
    `description` TEXT,
    `hierarchy_level` INT DEFAULT 0,
    `is_system` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
    `permission_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `permission_name` VARCHAR(100) NOT NULL UNIQUE,
    `permission_slug` VARCHAR(100) NOT NULL UNIQUE,
    `module` VARCHAR(50) NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `description` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_id` INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    `granted_by` BIGINT UNSIGNED,
    `granted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`role_id`, `permission_id`),
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`role_id`) ON DELETE CASCADE,
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`permission_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_roles` (
    `user_id` BIGINT UNSIGNED NOT NULL,
    `role_id` INT UNSIGNED NOT NULL,
    `assigned_by` BIGINT UNSIGNED,
    `assigned_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME,
    PRIMARY KEY (`user_id`, `role_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`role_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- LMS - COURSE MANAGEMENT TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS `course_categories` (
    `category_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `parent_id` INT UNSIGNED,
    `name` VARCHAR(200) NOT NULL,
    `slug` VARCHAR(200) NOT NULL UNIQUE,
    `description` TEXT,
    `icon` VARCHAR(255),
    `thumbnail` VARCHAR(500),
    `color` VARCHAR(20),
    `sort_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`parent_id`) REFERENCES `course_categories`(`category_id`) ON DELETE SET NULL,
    INDEX `idx_parent` (`parent_id`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `courses` (
    `course_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT UNSIGNED,
    `course_code` VARCHAR(50) NOT NULL UNIQUE,
    `title` VARCHAR(300) NOT NULL,
    `slug` VARCHAR(300) NOT NULL UNIQUE,
    `short_description` TEXT,
    `full_description` LONGTEXT,
    `thumbnail` VARCHAR(500),
    `trailer_video` VARCHAR(500),
    `level` ENUM('beginner', 'intermediate', 'advanced', 'expert') DEFAULT 'beginner',
    `language` VARCHAR(10) DEFAULT 'en',
    `duration_hours` DECIMAL(8,2) DEFAULT 0,
    `price` DECIMAL(10,2) DEFAULT 0.00,
    `compare_price` DECIMAL(10,2),
    `currency` VARCHAR(3) DEFAULT 'USD',
    `is_published` TINYINT(1) DEFAULT 0,
    `is_featured` TINYINT(1) DEFAULT 0,
    `is_free` TINYINT(1) DEFAULT 0,
    `enrollment_limit` INT,
    `prerequisites` JSON,
    `learning_outcomes` JSON,
    `tags` JSON,
    `seo_title` VARCHAR(200),
    `seo_description` TEXT,
    `seo_keywords` VARCHAR(500),
    `rating_average` DECIMAL(2,1) DEFAULT 0.0,
    `rating_count` INT DEFAULT 0,
    `enrollment_count` INT DEFAULT 0,
    `completion_count` INT DEFAULT 0,
    `created_by` BIGINT UNSIGNED,
    `published_at` DATETIME,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `course_categories`(`category_id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
    INDEX `idx_category` (`category_id`),
    INDEX `idx_level` (`level`),
    INDEX `idx_published` (`is_published`),
    INDEX `idx_featured` (`is_featured`),
    INDEX `idx_price` (`price`),
    INDEX `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `course_instructors` (
    `course_id` BIGINT UNSIGNED NOT NULL,
    `instructor_id` BIGINT UNSIGNED NOT NULL,
    `is_primary` TINYINT(1) DEFAULT 0,
    `revenue_share` DECIMAL(5,2) DEFAULT 0.00,
    `assigned_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`course_id`, `instructor_id`),
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`course_id`) ON DELETE CASCADE,
    FOREIGN KEY (`instructor_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `course_modules` (
    `module_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `course_id` BIGINT UNSIGNED NOT NULL,
    `module_name` VARCHAR(200) NOT NULL,
    `description` TEXT,
    `sequence_order` INT DEFAULT 0,
    `is_published` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`course_id`) ON DELETE CASCADE,
    INDEX `idx_course_order` (`course_id`, `sequence_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lessons` (
    `lesson_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `module_id` BIGINT UNSIGNED NOT NULL,
    `lesson_type` ENUM('video', 'text', 'quiz', 'assignment', 'live_session', 'downloadable') DEFAULT 'video',
    `title` VARCHAR(300) NOT NULL,
    `description` TEXT,
    `content_url` VARCHAR(500),
    `content_text` LONGTEXT,
    `duration_minutes` INT DEFAULT 0,
    `is_free` TINYINT(1) DEFAULT 0,
    `is_published` TINYINT(1) DEFAULT 1,
    `sequence_order` INT DEFAULT 0,
    `downloadable_files` JSON,
    `transcript` LONGTEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`module_id`) REFERENCES `course_modules`(`module_id`) ON DELETE CASCADE,
    INDEX `idx_module_order` (`module_id`, `sequence_order`),
    INDEX `idx_type` (`lesson_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `enrollments` (
    `enrollment_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `course_id` BIGINT UNSIGNED NOT NULL,
    `enrolled_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME,
    `progress_percent` DECIMAL(5,2) DEFAULT 0.00,
    `total_time_spent` INT DEFAULT 0,
    `status` ENUM('active', 'completed', 'dropped', 'paused', 'expired') DEFAULT 'active',
    `certificate_issued` TINYINT(1) DEFAULT 0,
    `certificate_id` VARCHAR(100),
    `payment_status` ENUM('pending', 'completed', 'refunded', 'free') DEFAULT 'pending',
    `payment_amount` DECIMAL(10,2),
    `transaction_id` VARCHAR(255),
    `expires_at` DATETIME,
    `last_accessed_at` DATETIME,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`course_id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user_course` (`user_id`, `course_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_progress` (`progress_percent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lesson_progress` (
    `progress_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `enrollment_id` BIGINT UNSIGNED NOT NULL,
    `lesson_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `is_completed` TINYINT(1) DEFAULT 0,
    `completion_date` DATETIME,
    `time_spent_seconds` INT DEFAULT 0,
    `last_position_seconds` INT DEFAULT 0,
    `notes` TEXT,
    `bookmarks` JSON,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments`(`enrollment_id`) ON DELETE CASCADE,
    FOREIGN KEY (`lesson_id`) REFERENCES `lessons`(`lesson_id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_enrollment_lesson` (`enrollment_id`, `lesson_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quizzes` (
    `quiz_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `lesson_id` BIGINT UNSIGNED,
    `course_id` BIGINT UNSIGNED,
    `quiz_title` VARCHAR(300) NOT NULL,
    `description` TEXT,
    `time_limit_minutes` INT,
    `passing_score` DECIMAL(5,2) DEFAULT 60.00,
    `max_attempts` INT DEFAULT 3,
    `shuffle_questions` TINYINT(1) DEFAULT 1,
    `show_correct_answers` TINYINT(1) DEFAULT 1,
    `is_published` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`lesson_id`) REFERENCES `lessons`(`lesson_id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`course_id`) ON DELETE CASCADE,
    INDEX `idx_course` (`course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quiz_questions` (
    `question_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `quiz_id` BIGINT UNSIGNED NOT NULL,
    `question_text` TEXT NOT NULL,
    `question_type` ENUM('mcq_single', 'mcq_multiple', 'true_false', 'short_answer', 'coding', 'fill_blank') DEFAULT 'mcq_single',
    `options` JSON,
    `correct_answer` JSON,
    `explanation` TEXT,
    `marks` INT DEFAULT 1,
    `sequence_order` INT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`quiz_id`) REFERENCES `quizzes`(`quiz_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quiz_attempts` (
    `attempt_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `quiz_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `enrollment_id` BIGINT UNSIGNED,
    `started_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `submitted_at` DATETIME,
    `score` DECIMAL(5,2),
    `total_marks` INT,
    `status` ENUM('in_progress', 'completed', 'abandoned', 'timed_out') DEFAULT 'in_progress',
    `answers` JSON,
    `ip_address` VARCHAR(45),
    `tab_switch_count` INT DEFAULT 0,
    `proctoring_data` JSON,
    `graded_by` BIGINT UNSIGNED,
    `graded_at` DATETIME,
    FOREIGN KEY (`quiz_id`) REFERENCES `quizzes`(`quiz_id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    INDEX `idx_user_quiz` (`user_id`, `quiz_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `course_reviews` (
    `review_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `course_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `rating` INT NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
    `review_text` TEXT,
    `is_approved` TINYINT(1) DEFAULT 0,
    `helpful_count` INT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`course_id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user_course_review` (`course_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CERTIFICATION SYSTEM
-- ============================================================

CREATE TABLE IF NOT EXISTS `certificates` (
    `certificate_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `certificate_number` VARCHAR(100) NOT NULL UNIQUE,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `course_id` BIGINT UNSIGNED,
    `certificate_type` ENUM('course_completion', 'specialization', 'internship', 'skill_assessment', 'achievement') DEFAULT 'course_completion',
    `title` VARCHAR(300) NOT NULL,
    `description` TEXT,
    `grade` VARCHAR(20),
    `score` DECIMAL(5,2),
    `issue_date` DATE NOT NULL,
    `expiry_date` DATE,
    `status` ENUM('active', 'revoked', 'expired') DEFAULT 'active',
    `pdf_url` VARCHAR(500),
    `blockchain_tx_hash` VARCHAR(255),
    `blockchain_network` VARCHAR(50),
    `ipfs_hash` VARCHAR(255),
    `verification_url` VARCHAR(500),
    `revoked_reason` TEXT,
    `revoked_at` DATETIME,
    `revoked_by` BIGINT UNSIGNED,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`course_id`) ON DELETE SET NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_number` (`certificate_number`),
    INDEX `idx_blockchain` (`blockchain_tx_hash`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `certificate_verifications` (
    `verification_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `certificate_id` BIGINT UNSIGNED NOT NULL,
    `verified_by` VARCHAR(255),
    `verification_method` ENUM('qr_scan', 'url', 'api'),
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `verified_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`certificate_id`) REFERENCES `certificates`(`certificate_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- INTERNSHIP MANAGEMENT SYSTEM
-- ============================================================

CREATE TABLE IF NOT EXISTS `internship_positions` (
    `position_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(300) NOT NULL,
    `slug` VARCHAR(300) NOT NULL UNIQUE,
    `department` VARCHAR(100),
    `description` LONGTEXT,
    `requirements` JSON,
    `responsibilities` JSON,
    `skills_required` JSON,
    `location_type` ENUM('remote', 'onsite', 'hybrid') DEFAULT 'remote',
    `city` VARCHAR(100),
    `country` VARCHAR(100),
    `stipend_amount` DECIMAL(10,2),
    `stipend_currency` VARCHAR(3) DEFAULT 'USD',
    `stipend_period` ENUM('hourly', 'daily', 'weekly', 'monthly') DEFAULT 'monthly',
    `duration_months` INT,
    `positions_available` INT DEFAULT 1,
    `positions_filled` INT DEFAULT 0,
    `posted_by` BIGINT UNSIGNED,
    `posted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `deadline` DATETIME,
    `start_date` DATE,
    `status` ENUM('draft', 'published', 'closed', 'filled', 'cancelled') DEFAULT 'draft',
    `is_featured` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`posted_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_location` (`location_type`),
    INDEX `idx_deadline` (`deadline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `internship_applications` (
    `application_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `position_id` BIGINT UNSIGNED NOT NULL,
    `student_id` BIGINT UNSIGNED NOT NULL,
    `applied_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `resume_url` VARCHAR(500),
    `cover_letter` TEXT,
    `portfolio_url` VARCHAR(500),
    `linkedin_url` VARCHAR(500),
    `github_url` VARCHAR(500),
    `answers` JSON,
    `status` ENUM('pending', 'screening', 'shortlisted', 'interview_scheduled', 'interview_completed', 'selected', 'rejected', 'withdrawn', 'on_hold') DEFAULT 'pending',
    `status_updated_at` DATETIME,
    `status_updated_by` BIGINT UNSIGNED,
    `interview_date` DATETIME,
    `feedback` TEXT,
    `rejection_reason` TEXT,
    `notes` TEXT,
    `match_score` DECIMAL(5,2),
    FOREIGN KEY (`position_id`) REFERENCES `internship_positions`(`position_id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    INDEX `idx_student` (`student_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `interviews` (
    `interview_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `application_id` BIGINT UNSIGNED NOT NULL,
    `scheduled_at` DATETIME NOT NULL,
    `duration_minutes` INT DEFAULT 30,
    `meeting_link` VARCHAR(500),
    `meeting_platform` ENUM('zoom', 'google_meet', 'teams', 'internal') DEFAULT 'internal',
    `interviewer_id` BIGINT UNSIGNED,
    `interview_type` ENUM('screening', 'technical', 'hr', 'final') DEFAULT 'screening',
    `status` ENUM('scheduled', 'completed', 'cancelled', 'no_show', 'rescheduled') DEFAULT 'scheduled',
    `feedback` TEXT,
    `rating` INT,
    `recording_url` VARCHAR(500),
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`application_id`) REFERENCES `internship_applications`(`application_id`) ON DELETE CASCADE,
    FOREIGN KEY (`interviewer_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `internship_offers` (
    `offer_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `application_id` BIGINT UNSIGNED NOT NULL,
    `letter_url` VARCHAR(500),
    `stipend_amount` DECIMAL(10,2),
    `stipend_currency` VARCHAR(3),
    `start_date` DATE,
    `end_date` DATE,
    `status` ENUM('pending', 'accepted', 'declined', 'expired') DEFAULT 'pending',
    `expiry_date` DATE,
    `accepted_at` DATETIME,
    `declined_at` DATETIME,
    `decline_reason` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`application_id`) REFERENCES `internship_applications`(`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `intern_projects` (
    `project_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `application_id` BIGINT UNSIGNED NOT NULL,
    `project_name` VARCHAR(300),
    `description` TEXT,
    `mentor_id` BIGINT UNSIGNED,
    `start_date` DATE,
    `end_date` DATE,
    `status` ENUM('active', 'completed', 'on_hold', 'cancelled') DEFAULT 'active',
    `progress_percent` DECIMAL(5,2) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`application_id`) REFERENCES `internship_applications`(`application_id`) ON DELETE CASCADE,
    FOREIGN KEY (`mentor_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `intern_time_logs` (
    `log_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `project_id` BIGINT UNSIGNED NOT NULL,
    `intern_id` BIGINT UNSIGNED NOT NULL,
    `log_date` DATE NOT NULL,
    `hours_worked` DECIMAL(4,2) NOT NULL,
    `description` TEXT,
    `screenshot_url` VARCHAR(500),
    `activity_data` JSON,
    `is_approved` TINYINT(1) DEFAULT 0,
    `approved_by` BIGINT UNSIGNED,
    `approved_at` DATETIME,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`) REFERENCES `intern_projects`(`project_id`) ON DELETE CASCADE,
    FOREIGN KEY (`intern_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- EMPLOYEE MANAGEMENT (HRMS)
-- ============================================================

CREATE TABLE IF NOT EXISTS `employees` (
    `employee_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `employee_code` VARCHAR(50) NOT NULL UNIQUE,
    `department` VARCHAR(100),
    `designation` VARCHAR(100),
    `employment_type` ENUM('full_time', 'part_time', 'contract', 'intern') DEFAULT 'full_time',
    `joining_date` DATE,
    `exit_date` DATE,
    `salary` DECIMAL(12,2),
    `currency` VARCHAR(3) DEFAULT 'USD',
    `manager_id` BIGINT UNSIGNED,
    `work_location` ENUM('office', 'remote', 'hybrid') DEFAULT 'office',
    `office_address` TEXT,
    `probation_end_date` DATE,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`manager_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
    INDEX `idx_department` (`department`),
    INDEX `idx_manager` (`manager_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_documents` (
    `document_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id` BIGINT UNSIGNED NOT NULL,
    `document_type` ENUM('resume', 'id_proof', 'address_proof', 'education', 'experience_letter', 'payslip', 'contract', 'nda', 'other') NOT NULL,
    `document_name` VARCHAR(255),
    `file_url` VARCHAR(500),
    `file_size` INT,
    `mime_type` VARCHAR(100),
    `uploaded_by` BIGINT UNSIGNED,
    `uploaded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `is_verified` TINYINT(1) DEFAULT 0,
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attendance` (
    `attendance_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `date` DATE NOT NULL,
    `check_in` DATETIME,
    `check_out` DATETIME,
    `status` ENUM('present', 'absent', 'late', 'half_day', 'on_leave', 'wfh', 'holiday', 'weekend') DEFAULT 'absent',
    `work_hours` DECIMAL(4,2),
    `location_lat` DECIMAL(10,8),
    `location_long` DECIMAL(11,8),
    `ip_address` VARCHAR(45),
    `device_info` JSON,
    `verification_method` ENUM('manual', 'face_recognition', 'qr_code', 'geofencing', 'auto'),
    `verification_photo` VARCHAR(500),
    `notes` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user_date` (`user_id`, `date`),
    INDEX `idx_date` (`date`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `leave_types` (
    `leave_type_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `type_name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `days_per_year` INT DEFAULT 0,
    `is_carry_forward` TINYINT(1) DEFAULT 0,
    `max_carry_forward_days` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `leaves` (
    `leave_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id` BIGINT UNSIGNED NOT NULL,
    `leave_type_id` INT UNSIGNED NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `days_count` DECIMAL(4,1) NOT NULL,
    `reason` TEXT,
    `status` ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    `applied_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `approved_by` BIGINT UNSIGNED,
    `approved_at` DATETIME,
    `rejection_reason` TEXT,
    `attachment_url` VARCHAR(500),
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`employee_id`) ON DELETE CASCADE,
    FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`leave_type_id`) ON DELETE CASCADE,
    INDEX `idx_status` (`status`),
    INDEX `idx_dates` (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payroll` (
    `payroll_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id` BIGINT UNSIGNED NOT NULL,
    `month_year` VARCHAR(7) NOT NULL,
    `basic_salary` DECIMAL(12,2) NOT NULL,
    `hra` DECIMAL(12,2) DEFAULT 0,
    `da` DECIMAL(12,2) DEFAULT 0,
    `conveyance` DECIMAL(12,2) DEFAULT 0,
    `medical` DECIMAL(12,2) DEFAULT 0,
    `special_allowance` DECIMAL(12,2) DEFAULT 0,
    `other_allowances` DECIMAL(12,2) DEFAULT 0,
    `gross_salary` DECIMAL(12,2) DEFAULT 0,
    `pf_deduction` DECIMAL(12,2) DEFAULT 0,
    `esi_deduction` DECIMAL(12,2) DEFAULT 0,
    `tds_deduction` DECIMAL(12,2) DEFAULT 0,
    `professional_tax` DECIMAL(12,2) DEFAULT 0,
    `other_deductions` DECIMAL(12,2) DEFAULT 0,
    `total_deductions` DECIMAL(12,2) DEFAULT 0,
    `net_salary` DECIMAL(12,2) DEFAULT 0,
    `payment_status` ENUM('pending', 'processed', 'paid', 'failed') DEFAULT 'pending',
    `payment_date` DATE,
    `transaction_id` VARCHAR(255),
    `payslip_url` VARCHAR(500),
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`employee_id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_employee_month` (`employee_id`, `month_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- COMMUNICATION SYSTEM
-- ============================================================

CREATE TABLE IF NOT EXISTS `chat_rooms` (
    `room_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(200),
    `type` ENUM('direct', 'group', 'channel') DEFAULT 'direct',
    `description` TEXT,
    `avatar` VARCHAR(500),
    `created_by` BIGINT UNSIGNED,
    `is_archived` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
    INDEX `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_room_members` (
    `room_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `role` ENUM('admin', 'member', 'viewer') DEFAULT 'member',
    `is_muted` TINYINT(1) DEFAULT 0,
    `joined_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `last_read_at` DATETIME,
    PRIMARY KEY (`room_id`, `user_id`),
    FOREIGN KEY (`room_id`) REFERENCES `chat_rooms`(`room_id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_messages` (
    `message_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `room_id` BIGINT UNSIGNED NOT NULL,
    `sender_id` BIGINT UNSIGNED NOT NULL,
    `message_type` ENUM('text', 'image', 'file', 'voice', 'code', 'system') DEFAULT 'text',
    `content` TEXT,
    `file_url` VARCHAR(500),
    `file_name` VARCHAR(255),
    `file_size` INT,
    `reply_to` BIGINT UNSIGNED,
    `reactions` JSON,
    `is_edited` TINYINT(1) DEFAULT 0,
    `edited_at` DATETIME,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`room_id`) REFERENCES `chat_rooms`(`room_id`) ON DELETE CASCADE,
    FOREIGN KEY (`sender_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`reply_to`) REFERENCES `chat_messages`(`message_id`) ON DELETE SET NULL,
    INDEX `idx_room_created` (`room_id`, `created_at`),
    INDEX `idx_sender` (`sender_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `video_calls` (
    `call_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `room_id` BIGINT UNSIGNED,
    `started_by` BIGINT UNSIGNED NOT NULL,
    `started_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `ended_at` DATETIME,
    `duration_seconds` INT,
    `participants` JSON,
    `recording_url` VARCHAR(500),
    `call_type` ENUM('one_on_one', 'group', 'webinar') DEFAULT 'one_on_one',
    `status` ENUM('ongoing', 'ended', 'recording') DEFAULT 'ongoing',
    FOREIGN KEY (`room_id`) REFERENCES `chat_rooms`(`room_id`) ON DELETE SET NULL,
    FOREIGN KEY (`started_by`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
    `notification_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(300),
    `message` TEXT,
    `type` ENUM('info', 'success', 'warning', 'error', 'system') DEFAULT 'info',
    `category` VARCHAR(50),
    `link` VARCHAR(500),
    `image` VARCHAR(500),
    `data` JSON,
    `is_read` TINYINT(1) DEFAULT 0,
    `read_at` DATETIME,
    `sent_via` JSON,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    INDEX `idx_user_read` (`user_id`, `is_read`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PORTFOLIO & CONTENT MANAGEMENT
-- ============================================================

CREATE TABLE IF NOT EXISTS `portfolio_projects` (
    `project_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(300) NOT NULL,
    `slug` VARCHAR(300) NOT NULL UNIQUE,
    `client_name` VARCHAR(200),
    `industry` VARCHAR(100),
    `service_type` VARCHAR(100),
    `description` LONGTEXT,
    `challenge` TEXT,
    `solution` TEXT,
    `results` JSON,
    `technologies` JSON,
    `main_image` VARCHAR(500),
    `gallery` JSON,
    `testimonial` TEXT,
    `testimonial_author` VARCHAR(200),
    `testimonial_author_title` VARCHAR(200),
    `client_logo` VARCHAR(500),
    `project_url` VARCHAR(500),
    `case_study_url` VARCHAR(500),
    `is_featured` TINYINT(1) DEFAULT 0,
    `completion_date` DATE,
    `status` ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    `created_by` BIGINT UNSIGNED,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
    INDEX `idx_industry` (`industry`),
    INDEX `idx_service` (`service_type`),
    INDEX `idx_featured` (`is_featured`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `team_members` (
    `member_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `role` VARCHAR(100),
    `department` VARCHAR(100),
    `bio` TEXT,
    `skills` JSON,
    `social_links` JSON,
    `display_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `joined_date` DATE,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    INDEX `idx_department` (`department`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `blog_categories` (
    `category_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(200) NOT NULL,
    `slug` VARCHAR(200) NOT NULL UNIQUE,
    `description` TEXT,
    `parent_id` INT UNSIGNED,
    `seo_meta_title` VARCHAR(200),
    `seo_meta_description` TEXT,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`parent_id`) REFERENCES `blog_categories`(`category_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `blog_posts` (
    `post_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(300) NOT NULL,
    `slug` VARCHAR(300) NOT NULL UNIQUE,
    `content` LONGTEXT,
    `excerpt` TEXT,
    `featured_image` VARCHAR(500),
    `category_id` INT UNSIGNED,
    `author_id` BIGINT UNSIGNED,
    `tags` JSON,
    `seo_title` VARCHAR(200),
    `seo_description` TEXT,
    `seo_keywords` VARCHAR(500),
    `seo_score` INT,
    `status` ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    `published_at` DATETIME,
    `views` INT DEFAULT 0,
    `likes` INT DEFAULT 0,
    `comments_count` INT DEFAULT 0,
    `reading_time_minutes` INT,
    `is_featured` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `blog_categories`(`category_id`) ON DELETE SET NULL,
    FOREIGN KEY (`author_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_published` (`published_at`),
    INDEX `idx_category` (`category_id`),
    INDEX `idx_author` (`author_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `blog_comments` (
    `comment_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `post_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED,
    `parent_id` BIGINT UNSIGNED,
    `author_name` VARCHAR(200),
    `author_email` VARCHAR(255),
    `content` TEXT NOT NULL,
    `is_approved` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`post_id`) REFERENCES `blog_posts`(`post_id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`parent_id`) REFERENCES `blog_comments`(`comment_id`) ON DELETE CASCADE,
    INDEX `idx_post` (`post_id`),
    INDEX `idx_approved` (`is_approved`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CLIENT & PROJECT MANAGEMENT
-- ============================================================

CREATE TABLE IF NOT EXISTS `clients` (
    `client_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED,
    `company_name` VARCHAR(200) NOT NULL,
    `industry` VARCHAR(100),
    `website` VARCHAR(500),
    `description` TEXT,
    `contract_value` DECIMAL(15,2),
    `onboarding_date` DATE,
    `account_manager_id` BIGINT UNSIGNED,
    `status` ENUM('active', 'inactive', 'prospect') DEFAULT 'prospect',
    `billing_address` JSON,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
    FOREIGN KEY (`account_manager_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `projects` (
    `project_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `client_id` BIGINT UNSIGNED NOT NULL,
    `project_name` VARCHAR(300) NOT NULL,
    `description` TEXT,
    `tech_stack` JSON,
    `start_date` DATE,
    `deadline` DATE,
    `budget` DECIMAL(15,2),
    `status` ENUM('planning', 'in_progress', 'on_hold', 'completed', 'cancelled') DEFAULT 'planning',
    `project_manager_id` BIGINT UNSIGNED,
    `progress_percent` DECIMAL(5,2) DEFAULT 0,
    `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`client_id`) ON DELETE CASCADE,
    FOREIGN KEY (`project_manager_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `project_tasks` (
    `task_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `project_id` BIGINT UNSIGNED NOT NULL,
    `assigned_to` BIGINT UNSIGNED,
    `task_title` VARCHAR(300) NOT NULL,
    `description` TEXT,
    `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    `status` ENUM('todo', 'in_progress', 'review', 'done', 'cancelled') DEFAULT 'todo',
    `estimated_hours` DECIMAL(6,2),
    `actual_hours` DECIMAL(6,2) DEFAULT 0,
    `due_date` DATE,
    `completed_at` DATETIME,
    `created_by` BIGINT UNSIGNED,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`project_id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_to`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
    INDEX `idx_project` (`project_id`),
    INDEX `idx_assigned` (`assigned_to`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `time_logs` (
    `log_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `task_id` BIGINT UNSIGNED,
    `project_id` BIGINT UNSIGNED,
    `employee_id` BIGINT UNSIGNED NOT NULL,
    `log_date` DATE NOT NULL,
    `hours_worked` DECIMAL(4,2) NOT NULL,
    `description` TEXT,
    `screenshot_url` VARCHAR(500),
    `is_billable` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`task_id`) REFERENCES `project_tasks`(`task_id`) ON DELETE SET NULL,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`project_id`) ON DELETE SET NULL,
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- FINANCE & PAYMENTS
-- ============================================================

CREATE TABLE IF NOT EXISTS `payment_transactions` (
    `transaction_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED,
    `amount` DECIMAL(12,2) NOT NULL,
    `currency` VARCHAR(3) DEFAULT 'USD',
    `payment_for` ENUM('course', 'internship_stipend', 'salary', 'project', 'subscription', 'other') NOT NULL,
    `reference_id` BIGINT UNSIGNED,
    `reference_type` VARCHAR(50),
    `payment_method` ENUM('card', 'upi', 'netbanking', 'wallet', 'crypto', 'bank_transfer') NOT NULL,
    `transaction_status` ENUM('pending', 'completed', 'failed', 'refunded', 'cancelled') DEFAULT 'pending',
    `gateway_name` VARCHAR(50),
    `gateway_transaction_id` VARCHAR(255),
    `gateway_response` JSON,
    `paid_at` DATETIME,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
    INDEX `idx_status` (`transaction_status`),
    INDEX `idx_gateway` (`gateway_transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoices` (
    `invoice_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `client_id` BIGINT UNSIGNED,
    `invoice_number` VARCHAR(100) NOT NULL UNIQUE,
    `amount` DECIMAL(12,2) NOT NULL,
    `tax_amount` DECIMAL(12,2) DEFAULT 0,
    `total_amount` DECIMAL(12,2) NOT NULL,
    `items` JSON,
    `status` ENUM('draft', 'sent', 'paid', 'overdue', 'cancelled') DEFAULT 'draft',
    `due_date` DATE,
    `paid_at` DATETIME,
    `transaction_id` BIGINT UNSIGNED,
    `pdf_url` VARCHAR(500),
    `sent_at` DATETIME,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`client_id`) ON DELETE SET NULL,
    FOREIGN KEY (`transaction_id`) REFERENCES `payment_transactions`(`transaction_id`) ON DELETE SET NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_due_date` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SYSTEM CONFIGURATION
-- ============================================================

CREATE TABLE IF NOT EXISTS `system_settings` (
    `setting_key` VARCHAR(100) PRIMARY KEY,
    `setting_value` TEXT,
    `category` VARCHAR(50) DEFAULT 'general',
    `data_type` ENUM('string', 'integer', 'boolean', 'json', 'array') DEFAULT 'string',
    `is_editable` TINYINT(1) DEFAULT 1,
    `description` TEXT,
    `last_updated` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `feature_flags` (
    `flag_name` VARCHAR(100) PRIMARY KEY,
    `is_enabled` TINYINT(1) DEFAULT 0,
    `description` TEXT,
    `allowed_roles` JSON,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `api_keys` (
    `key_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED,
    `api_key` VARCHAR(255) NOT NULL UNIQUE,
    `api_secret` VARCHAR(255) NOT NULL,
    `name` VARCHAR(200),
    `permissions` JSON,
    `rate_limit` INT DEFAULT 100,
    `usage_count` INT DEFAULT 0,
    `last_used` DATETIME,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    INDEX `idx_api_key` (`api_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `api_logs` (
    `log_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `api_key_id` BIGINT UNSIGNED,
    `endpoint` VARCHAR(500) NOT NULL,
    `method` VARCHAR(10) NOT NULL,
    `ip_address` VARCHAR(45),
    `request_body` JSON,
    `response_code` INT,
    `response_time_ms` INT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`api_key_id`) REFERENCES `api_keys`(`key_id`) ON DELETE SET NULL,
    INDEX `idx_endpoint` (`endpoint`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_templates` (
    `template_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `template_key` VARCHAR(100) NOT NULL UNIQUE,
    `name` VARCHAR(200),
    `subject` VARCHAR(300),
    `body_html` LONGTEXT,
    `body_text` LONGTEXT,
    `variables` JSON,
    `category` VARCHAR(50),
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- GAMIFICATION & ENGAGEMENT
-- ============================================================

CREATE TABLE IF NOT EXISTS `user_points` (
    `point_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `points` INT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `reference_type` VARCHAR(50),
    `reference_id` BIGINT UNSIGNED,
    `description` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `badges` (
    `badge_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(200) NOT NULL,
    `description` TEXT,
    `icon` VARCHAR(500),
    `criteria_type` VARCHAR(50),
    `criteria_value` INT,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_badges` (
    `user_id` BIGINT UNSIGNED NOT NULL,
    `badge_id` INT UNSIGNED NOT NULL,
    `earned_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `badge_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`badge_id`) REFERENCES `badges`(`badge_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `learning_streaks` (
    `streak_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `current_streak` INT DEFAULT 0,
    `longest_streak` INT DEFAULT 0,
    `last_activity_date` DATE,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- INSERT DEFAULT DATA
-- ============================================================

-- Default Roles
INSERT INTO `roles` (`role_name`, `role_slug`, `description`, `hierarchy_level`, `is_system`) VALUES
('Super Admin', 'super_admin', 'Full system access and control', 1, 1),
('Admin', 'admin', 'Department level administration', 2, 1),
('Sub Admin', 'sub_admin', 'Branch/project level administration', 3, 1),
('Employee', 'employee', 'Internal team member', 4, 1),
('Student', 'student', 'Learning platform user', 5, 1),
('Corporate Client', 'corporate_client', 'External business client', 6, 1),
('Guest', 'guest', 'Public visitor', 7, 1);

-- Default Permissions
INSERT INTO `permissions` (`permission_name`, `permission_slug`, `module`, `action`, `description`) VALUES
('User Management', 'user_management', 'users', 'all', 'Full user management access'),
('Course Management', 'course_management', 'courses', 'all', 'Full course management access'),
('Internship Management', 'internship_management', 'internships', 'all', 'Full internship management access'),
('Certificate Management', 'certificate_management', 'certificates', 'all', 'Full certificate management access'),
('Employee Management', 'employee_management', 'employees', 'all', 'Full employee management access'),
('Payroll Management', 'payroll_management', 'payroll', 'all', 'Full payroll management access'),
('Project Management', 'project_management', 'projects', 'all', 'Full project management access'),
('Client Management', 'client_management', 'clients', 'all', 'Full client management access'),
('Content Management', 'content_management', 'content', 'all', 'Full content management access'),
('System Settings', 'system_settings', 'system', 'all', 'Full system configuration access'),
('View Reports', 'view_reports', 'reports', 'view', 'View analytics and reports'),
('Manage API Keys', 'manage_api_keys', 'api', 'all', 'Manage API keys and access');

-- Assign all permissions to Super Admin
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, permission_id FROM permissions;

-- Default System Settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `category`, `data_type`, `is_editable`, `description`) VALUES
('site_name', 'DigitalEdgeSolutions', 'general', 'string', 1, 'Website name'),
('site_tagline', 'Where Learning Meets Opportunity', 'general', 'string', 1, 'Website tagline'),
('site_logo', '/assets/images/logo.png', 'general', 'string', 1, 'Website logo URL'),
('contact_email', 'contact@digitaledgesolutions.com', 'general', 'string', 1, 'Primary contact email'),
('support_phone', '+1-800-123-4567', 'general', 'string', 1, 'Support phone number'),
('default_timezone', 'UTC', 'general', 'string', 1, 'Default timezone'),
('default_currency', 'USD', 'general', 'string', 1, 'Default currency'),
('maintenance_mode', '0', 'system', 'boolean', 1, 'Enable maintenance mode'),
('user_registration', '1', 'system', 'boolean', 1, 'Allow new user registrations'),
('email_verification_required', '1', 'system', 'boolean', 1, 'Require email verification'),
('session_timeout', '1800', 'security', 'integer', 1, 'Session timeout in seconds'),
('max_login_attempts', '5', 'security', 'integer', 1, 'Maximum failed login attempts'),
('password_min_length', '8', 'security', 'integer', 1, 'Minimum password length'),
('jwt_expiry', '86400', 'security', 'integer', 1, 'JWT token expiry in seconds'),
('course_completion_threshold', '80', 'lms', 'integer', 1, 'Minimum progress % for completion'),
('quiz_passing_score', '60', 'lms', 'integer', 1, 'Minimum quiz passing score %'),
('certificate_enabled', '1', 'certificates', 'boolean', 1, 'Enable certificate generation'),
('blockchain_certificates', '0', 'certificates', 'boolean', 1, 'Enable blockchain verification'),
('attendance_auto_mark', '1', 'hrms', 'boolean', 1, 'Auto-mark attendance'),
('payroll_auto_process', '1', 'hrms', 'boolean', 1, 'Auto-process payroll');

-- Default Leave Types
INSERT INTO `leave_types` (`type_name`, `description`, `days_per_year`, `is_carry_forward`, `max_carry_forward_days`) VALUES
('Casual Leave', 'Regular casual leave', 12, 0, 0),
('Sick Leave', 'Medical leave', 10, 0, 0),
('Earned Leave', 'Paid time off based on service', 15, 1, 30),
('Maternity Leave', 'Maternity leave for female employees', 180, 0, 0),
('Paternity Leave', 'Paternity leave for male employees', 15, 0, 0),
('Compensatory Off', 'Compensatory leave for extra work', 0, 1, 10);

-- Default Badges
INSERT INTO `badges` (`name`, `description`, `icon`, `criteria_type`, `criteria_value`) VALUES
('First Steps', 'Complete your first course', 'badge-first-steps.png', 'courses_completed', 1),
('Quick Learner', 'Complete 5 courses', 'badge-quick-learner.png', 'courses_completed', 5),
('Course Master', 'Complete 10 courses', 'badge-course-master.png', 'courses_completed', 10),
('Perfect Score', 'Get 100% in any quiz', 'badge-perfect-score.png', 'perfect_quiz', 1),
('7-Day Streak', 'Maintain 7-day learning streak', 'badge-7-streak.png', 'streak_days', 7),
('30-Day Streak', 'Maintain 30-day learning streak', 'badge-30-streak.png', 'streak_days', 30),
('Helper', 'Help 10 other students', 'badge-helper.png', 'help_others', 10),
('Top Performer', 'Rank in top 10% of leaderboard', 'badge-top-performer.png', 'leaderboard_rank', 10);

-- Default Email Templates
INSERT INTO `email_templates` (`template_key`, `name`, `subject`, `body_html`, `body_text`, `variables`, `category`) VALUES
('welcome_email', 'Welcome Email', 'Welcome to DigitalEdgeSolutions!', 
'<h1>Welcome {{first_name}}!</h1><p>Thank you for joining DigitalEdgeSolutions. We are excited to have you on board.</p><p>Get started by exploring our courses and internships.</p>', 
'Welcome {{first_name}}! Thank you for joining DigitalEdgeSolutions. Get started by exploring our courses and internships.', 
'["first_name", "login_url"]', 'auth'),

('password_reset', 'Password Reset', 'Password Reset Request', 
'<h1>Password Reset</h1><p>Hello {{first_name}},</p><p>Click the link below to reset your password:</p><p><a href="{{reset_url}}">Reset Password</a></p><p>This link expires in 1 hour.</p>', 
'Hello {{first_name}}, Click the link to reset your password: {{reset_url}}. This link expires in 1 hour.', 
'["first_name", "reset_url"]', 'auth'),

('course_enrollment', 'Course Enrollment', 'You have enrolled in {{course_title}}', 
'<h1>Enrollment Confirmed</h1><p>Hello {{first_name}},</p><p>You have successfully enrolled in <strong>{{course_title}}</strong>.</p><p><a href="{{course_url}}">Start Learning</a></p>', 
'Hello {{first_name}}, You have successfully enrolled in {{course_title}}. Start learning: {{course_url}}', 
'["first_name", "course_title", "course_url"]', 'lms'),

('certificate_issued', 'Certificate Issued', 'Congratulations! Your certificate is ready', 
'<h1>Congratulations {{first_name}}!</h1><p>You have successfully completed <strong>{{course_title}}</strong>.</p><p>Your certificate is ready for download.</p><p><a href="{{certificate_url}}">Download Certificate</a></p>', 
'Congratulations {{first_name}}! You have completed {{course_title}}. Download your certificate: {{certificate_url}}', 
'["first_name", "course_title", "certificate_url"]', 'certificates'),

('interview_scheduled', 'Interview Scheduled', 'Your interview has been scheduled', 
'<h1>Interview Scheduled</h1><p>Hello {{first_name}},</p><p>Your interview for <strong>{{position_title}}</strong> is scheduled for {{interview_date}}.</p><p>Meeting Link: <a href="{{meeting_link}}">Join Interview</a></p>', 
'Hello {{first_name}}, Your interview for {{position_title}} is scheduled for {{interview_date}}. Join: {{meeting_link}}', 
'["first_name", "position_title", "interview_date", "meeting_link"]', 'internships'),

('offer_letter', 'Offer Letter', 'Congratulations! You have received an offer', 
'<h1>Congratulations!</h1><p>Hello {{first_name}},</p><p>We are pleased to offer you the position of <strong>{{position_title}}</strong>.</p><p>Please review and accept your offer letter.</p><p><a href="{{offer_url}}">View Offer</a></p>', 
'Hello {{first_name}}, We are pleased to offer you the position of {{position_title}}. View offer: {{offer_url}}', 
'["first_name", "position_title", "offer_url"]', 'internships'),

('payslip_generated', 'Payslip Generated', 'Your payslip for {{month_year}} is ready', 
'<h1>Payslip Ready</h1><p>Hello {{first_name}},</p><p>Your payslip for {{month_year}} has been generated.</p><p>Net Salary: {{currency}} {{net_salary}}</p><p><a href="{{payslip_url}}">Download Payslip</a></p>', 
'Hello {{first_name}}, Your payslip for {{month_year}} is ready. Net Salary: {{currency}} {{net_salary}}. Download: {{payslip_url}}', 
'["first_name", "month_year", "currency", "net_salary", "payslip_url"]', 'payroll');

SET FOREIGN_KEY_CHECKS = 1;
