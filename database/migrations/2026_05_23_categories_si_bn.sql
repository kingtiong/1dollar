-- ================================================================
-- JackOne · 一夺 — Sinhala / Bengali category localization
-- Idempotent. Safe to run from any client (no DELIMITER).
-- ================================================================

SET @db := DATABASE();

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'name_si');
SET @sql := IF(@exists = 0,
    'ALTER TABLE categories ADD COLUMN name_si VARCHAR(64) NULL AFTER name_en',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'name_bn');
SET @sql := IF(@exists = 0,
    'ALTER TABLE categories ADD COLUMN name_bn VARCHAR(64) NULL AFTER name_si',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
