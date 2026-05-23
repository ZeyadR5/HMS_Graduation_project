ALTER TABLE `tblmedicalhistory` ADD COLUMN `prescription` TEXT NULL AFTER `treatment`;
ALTER TABLE `tblmedicalhistory` ADD COLUMN `description` TEXT NULL AFTER `prescription`;
ALTER TABLE `tblmedicalhistory` ADD COLUMN `userId` INT NULL AFTER `UserID`;
UPDATE `tblmedicalhistory` SET `userId` = `UserID` WHERE `userId` IS NULL;
