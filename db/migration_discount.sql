SET @dbname = DATABASE();
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'appointment' AND COLUMN_NAME = 'discount');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `appointment` ADD COLUMN `discount` INT(11) DEFAULT 0 AFTER `paid`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'appointment' AND COLUMN_NAME = 'discount_reason');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `appointment` ADD COLUMN `discount_reason` VARCHAR(255) DEFAULT NULL AFTER `discount`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
