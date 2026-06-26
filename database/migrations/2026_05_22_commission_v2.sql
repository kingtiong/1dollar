-- ================================================================
-- JackOne · 一夺 — Commission v2 (multi-level group bonus)
-- Idempotent migration for existing installs.
-- ================================================================

CREATE TABLE IF NOT EXISTS ranks (
    id              TINYINT UNSIGNED PRIMARY KEY,        -- 0=member, 1..5=V1..V5
    code            VARCHAR(8)   NOT NULL UNIQUE,
    name_zh         VARCHAR(32)  NOT NULL,
    name_en         VARCHAR(32)  NOT NULL,
    min_direct      INT UNSIGNED NOT NULL DEFAULT 0,     -- effective direct refs in current month
    min_team_volume DECIMAL(14,2) NOT NULL DEFAULT 0,    -- team volume threshold (this month)
    bonus_rate      DECIMAL(6,4) NOT NULL DEFAULT 0,     -- 0.0100 = 1% of downline base
    sub_lines       VARCHAR(64)  NULL,                   -- e.g. "2x V2" (informational)
    sort_order      TINYINT      NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_ranks (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    year_month      CHAR(7)      NOT NULL,               -- '2026-05'
    rank_id         TINYINT UNSIGNED NOT NULL,
    direct_count    INT UNSIGNED NOT NULL DEFAULT 0,
    team_volume     DECIMAL(14,2) NOT NULL DEFAULT 0,
    locked_at       DATETIME     NULL,                   -- set at month close
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_month (user_id, year_month),
    INDEX idx_ur_rank (rank_id),
    CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ur_rank FOREIGN KEY (rank_id) REFERENCES ranks(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS group_bonuses (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,            -- earner (ancestor)
    from_user_id    BIGINT UNSIGNED NOT NULL,            -- the buyer (downline)
    period_id       BIGINT UNSIGNED NULL,
    participation_id BIGINT UNSIGNED NULL,
    depth           TINYINT UNSIGNED NOT NULL,           -- 1..N layers below earner
    rank_id         TINYINT UNSIGNED NOT NULL,           -- earner's rank at award time
    base_amount     DECIMAL(14,2) NOT NULL,
    rate            DECIMAL(6,4) NOT NULL,
    amount          DECIMAL(14,2) NOT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_gb_user (user_id),
    INDEX idx_gb_from (from_user_id),
    CONSTRAINT fk_gb_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_gb_rank FOREIGN KEY (rank_id) REFERENCES ranks(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default V0..V5 thresholds, aligned with marketing-plan.html
INSERT IGNORE INTO ranks (id, code, name_zh, name_en, min_direct, min_team_volume, bonus_rate, sub_lines, sort_order) VALUES
(0, 'V0', '普通会员',     'Member',            0,      0.00, 0.0000, NULL,    0),
(1, 'V1', '青铜会员',     'Bronze',            5,   1000.00, 0.0100, NULL,    1),
(2, 'V2', '白银合伙人',   'Silver Partner',   10,   5000.00, 0.0200, NULL,    2),
(3, 'V3', '黄金合伙人',   'Gold Partner',      0,  20000.00, 0.0300, '2x V2', 3),
(4, 'V4', '铂金合伙人',   'Platinum Partner',  0,  50000.00, 0.0400, '2x V3', 4),
(5, 'V5', '钻石合伙人',   'Diamond Partner',   0, 200000.00, 0.0500, '3x V4', 5);
