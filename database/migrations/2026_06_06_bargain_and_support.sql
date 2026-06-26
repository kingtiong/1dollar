-- ================================================================
-- JackOne · 一夺 — Bargain ("砍一刀") + customer-service URL
-- Idempotent. Safe to re-run.
-- ================================================================

SET @db := DATABASE();

-- ----- products.bargain_eligible + bargain_target_cents -----
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'bargain_eligible');
SET @sql := IF(@exists = 0,
    'ALTER TABLE products ADD COLUMN bargain_eligible TINYINT(1) NOT NULL DEFAULT 0 AFTER status',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'bargain_target_cents');
SET @sql := IF(@exists = 0,
    'ALTER TABLE products ADD COLUMN bargain_target_cents INT UNSIGNED NULL AFTER bargain_eligible',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----- bargain_sessions -----
CREATE TABLE IF NOT EXISTS bargain_sessions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    product_id      BIGINT UNSIGNED NOT NULL,
    share_token     VARCHAR(40) NOT NULL UNIQUE,
    target_cents    INT UNSIGNED NOT NULL,
    current_cents   INT UNSIGNED NOT NULL DEFAULT 0,
    max_helpers     SMALLINT UNSIGNED NOT NULL DEFAULT 15,
    helper_count    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status          ENUM('active','completed','expired') NOT NULL DEFAULT 'active',
    completed_at    DATETIME NULL,
    expires_at      DATETIME NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bargain_user (user_id, status),
    INDEX idx_bargain_product (product_id),
    CONSTRAINT fk_bargain_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_bargain_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----- bargain_helps -----
CREATE TABLE IF NOT EXISTS bargain_helps (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id      BIGINT UNSIGNED NOT NULL,
    helper_user_id  BIGINT UNSIGNED NOT NULL,
    amount_cents    INT UNSIGNED NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_help (session_id, helper_user_id),
    INDEX idx_help_session (session_id),
    CONSTRAINT fk_help_session FOREIGN KEY (session_id) REFERENCES bargain_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_help_user FOREIGN KEY (helper_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----- seed settings (idempotent) -----
INSERT IGNORE INTO settings (`key`, `value`) VALUES
    ('support_url',                 ''),
    ('bargain_max_helpers',         '15'),
    ('bargain_session_hours',       '48'),
    ('bargain_helper_reward_draws', '1'),
    ('bargain_owner_reward_draws',  '1');
