-- ============================================================
-- Migration: Doctor Scheduling & Notifications System
-- Date: 2026-04-17
-- ============================================================

-- 1. Doctor weekly schedule
CREATE TABLE IF NOT EXISTS `doctor_schedules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `doctor_id` INT NOT NULL,
    `day_of_week` TINYINT NOT NULL COMMENT '0=Saturday,1=Sunday,2=Monday,3=Tuesday,4=Wednesday,5=Thursday,6=Friday',
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `slot_duration` INT NOT NULL DEFAULT 30 COMMENT 'Minutes per appointment',
    `status` ENUM('available','off','emergency') DEFAULT 'available',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_doctor_day` (`doctor_id`, `day_of_week`),
    FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Day-specific overrides (vacations, emergencies, custom hours)
CREATE TABLE IF NOT EXISTS `doctor_day_overrides` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `doctor_id` INT NOT NULL,
    `override_date` DATE NOT NULL,
    `status` ENUM('off','emergency','custom') DEFAULT 'off',
    `start_time` TIME NULL,
    `end_time` TIME NULL,
    `slot_duration` INT NULL,
    `reason` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_doctor_date` (`doctor_id`, `override_date`),
    FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Notifications
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `recipient_type` ENUM('patient','employee','admin','doctor') NOT NULL,
    `recipient_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('schedule_change','appointment','system','cancellation') DEFAULT 'system',
    `related_doctor_id` INT NULL,
    `related_appointment_id` INT NULL,
    `is_read` TINYINT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_recipient` (`recipient_type`, `recipient_id`),
    INDEX `idx_unread` (`recipient_type`, `recipient_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Add patient_email to appointment table
ALTER TABLE `appointment` ADD COLUMN IF NOT EXISTS `patient_email` VARCHAR(255) NULL AFTER `patient_Num`;
