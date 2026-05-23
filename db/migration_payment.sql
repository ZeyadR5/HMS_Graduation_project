-- =====================================================
-- Payment System Migration (Safe - skips existing columns)
-- =====================================================

-- Add deposit tracking columns to appointment table (one by one to skip duplicates)
SET @dbname = DATABASE();

-- deposit_amount
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'appointment' AND COLUMN_NAME = 'deposit_amount');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `appointment` ADD COLUMN `deposit_amount` INT(11) DEFAULT 0 AFTER `paid`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- deposit_status
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'appointment' AND COLUMN_NAME = 'deposit_status');
SET @sql = IF(@col_exists = 0, "ALTER TABLE `appointment` ADD COLUMN `deposit_status` ENUM('none','pending','paid','failed') DEFAULT 'none' AFTER `deposit_amount`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- deposit_transaction_id
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'appointment' AND COLUMN_NAME = 'deposit_transaction_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `appointment` ADD COLUMN `deposit_transaction_id` VARCHAR(255) DEFAULT NULL AFTER `deposit_status`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- deposit_channel
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'appointment' AND COLUMN_NAME = 'deposit_channel');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `appointment` ADD COLUMN `deposit_channel` VARCHAR(50) DEFAULT NULL AFTER `deposit_transaction_id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- deposit_paid_at
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'appointment' AND COLUMN_NAME = 'deposit_paid_at');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `appointment` ADD COLUMN `deposit_paid_at` TIMESTAMP NULL DEFAULT NULL AFTER `deposit_channel`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
