-- ================================================================
-- JackOne · 一夺 — Sinhala / Bengali product localization
-- Adds name_si, name_bn, description_si, description_bn to products.
-- Idempotent: re-running is safe. No DELIMITER directives, so this
-- works from phpMyAdmin / Adminer / PDO as well as the mysql CLI.
-- ================================================================

SET @db := DATABASE();

-- name_si --------------------------------------------------------
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'name_si');
SET @sql := IF(@exists = 0,
    'ALTER TABLE products ADD COLUMN name_si VARCHAR(200) NULL AFTER name_en',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- name_bn --------------------------------------------------------
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'name_bn');
SET @sql := IF(@exists = 0,
    'ALTER TABLE products ADD COLUMN name_bn VARCHAR(200) NULL AFTER name_si',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- description_si -------------------------------------------------
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'description_si');
SET @sql := IF(@exists = 0,
    'ALTER TABLE products ADD COLUMN description_si TEXT NULL AFTER description_en',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- description_bn -------------------------------------------------
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'description_bn');
SET @sql := IF(@exists = 0,
    'ALTER TABLE products ADD COLUMN description_bn TEXT NULL AFTER description_si',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
