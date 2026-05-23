SET @dbname = DATABASE();
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'appointment' AND COLUMN_NAME = 'cancel_reason');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `appointment` ADD COLUMN `cancel_reason` TEXT DEFAULT NULL AFTER `cancelledBy`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
