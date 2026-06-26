-- ================================================================
-- Lucky Mall — raffle shopping platform schema
-- MySQL 8.0+ / MariaDB 10.5+
-- ================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------- users ----------
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(64)  NOT NULL UNIQUE,
    email           VARCHAR(128) NULL UNIQUE,
    phone           VARCHAR(32)  NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    display_name    VARCHAR(64)  NULL,
    avatar          VARCHAR(255) NULL,
    balance         DECIMAL(14,2) NOT NULL DEFAULT 0.00,  -- lucky coin
    points          INT UNSIGNED NOT NULL DEFAULT 0,
    free_draws      INT NOT NULL DEFAULT 0,                -- reward-granted slots; consumed before balance
    referrer_id     BIGINT UNSIGNED NULL,
    referral_code   VARCHAR(16)  NOT NULL UNIQUE,
    locale          VARCHAR(8)   NOT NULL DEFAULT 'zh',
    status          TINYINT      NOT NULL DEFAULT 1,       -- 1=active 0=banned
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_referrer (referrer_id),
    CONSTRAINT fk_users_referrer FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- admins ----------
DROP TABLE IF EXISTS admins;
CREATE TABLE admins (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(64)  NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    role            VARCHAR(32)  NOT NULL DEFAULT 'admin',
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- categories ----------
DROP TABLE IF EXISTS categories;
CREATE TABLE categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug        VARCHAR(64) NOT NULL UNIQUE,
    name_zh     VARCHAR(64) NOT NULL,
    name_en     VARCHAR(64) NOT NULL,
    name_si     VARCHAR(64) NULL,
    name_bn     VARCHAR(64) NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    status      TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- products ----------
DROP TABLE IF EXISTS products;
CREATE TABLE products (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id     INT UNSIGNED NULL,
    name_zh         VARCHAR(200) NOT NULL,
    name_en         VARCHAR(200) NOT NULL,
    name_si         VARCHAR(200) NULL,
    name_bn         VARCHAR(200) NULL,
    description_zh  TEXT NULL,
    description_en  TEXT NULL,
    description_si  TEXT NULL,
    description_bn  TEXT NULL,
    cover_image     VARCHAR(255) NULL,
    gallery         TEXT NULL,            -- JSON array of image urls
    value_amount    DECIMAL(14,2) NOT NULL,     -- Rs value
    slot_price      DECIMAL(14,2) NOT NULL DEFAULT 1.00,  -- price per slot
    default_total_slots INT UNSIGNED NOT NULL DEFAULT 100,
    sort_order      INT NOT NULL DEFAULT 0,
    status          TINYINT NOT NULL DEFAULT 1,  -- 1=on-sale 0=off
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_products_category (category_id),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- periods ----------
-- Each "round" of a product (138期 etc.)
DROP TABLE IF EXISTS periods;
CREATE TABLE periods (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id      BIGINT UNSIGNED NOT NULL,
    period_no       INT UNSIGNED NOT NULL,         -- 138, 139 ...
    total_slots     INT UNSIGNED NOT NULL,
    sold_slots      INT UNSIGNED NOT NULL DEFAULT 0,
    status          TINYINT NOT NULL DEFAULT 1,     -- 1=open 2=drawing 3=drawn 4=closed
    winner_user_id  BIGINT UNSIGNED NULL,
    winner_code     VARCHAR(32) NULL,
    seed_block      VARCHAR(255) NULL,              -- audit info for the draw
    drawn_at        DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_periods (product_id, period_no),
    INDEX idx_periods_status (status),
    CONSTRAINT fk_periods_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_periods_winner FOREIGN KEY (winner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- participations ----------
DROP TABLE IF EXISTS participations;
CREATE TABLE participations (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_id    BIGINT UNSIGNED NOT NULL,
    user_id      BIGINT UNSIGNED NOT NULL,
    slots_count  INT UNSIGNED NOT NULL,
    amount_paid  DECIMAL(14,2) NOT NULL,
    ip_addr      VARCHAR(64) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_part_period (period_id),
    INDEX idx_part_user (user_id),
    CONSTRAINT fk_part_period FOREIGN KEY (period_id) REFERENCES periods(id) ON DELETE CASCADE,
    CONSTRAINT fk_part_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- lucky_codes ----------
DROP TABLE IF EXISTS lucky_codes;
CREATE TABLE lucky_codes (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_id       BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NOT NULL,
    participation_id BIGINT UNSIGNED NOT NULL,
    code            VARCHAR(16) NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_code (period_id, code),
    INDEX idx_lc_user (user_id),
    CONSTRAINT fk_lc_period FOREIGN KEY (period_id) REFERENCES periods(id) ON DELETE CASCADE,
    CONSTRAINT fk_lc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_lc_part FOREIGN KEY (participation_id) REFERENCES participations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- wallet transactions ----------
DROP TABLE IF EXISTS wallet_txns;
CREATE TABLE wallet_txns (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      BIGINT UNSIGNED NOT NULL,
    kind         VARCHAR(32) NOT NULL,    -- recharge, buy, refund, commission, win, withdraw, adjust
    amount       DECIMAL(14,2) NOT NULL,   -- signed
    balance_after DECIMAL(14,2) NOT NULL,
    reference    VARCHAR(128) NULL,        -- order id / period id / etc.
    note         VARCHAR(255) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wt_user (user_id),
    CONSTRAINT fk_wt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- payments (recharge orders) ----------
DROP TABLE IF EXISTS payments;
CREATE TABLE payments (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    order_no        VARCHAR(40) NOT NULL UNIQUE,
    gateway         VARCHAR(32) NOT NULL,        -- usdt / stripe / manual
    amount          DECIMAL(14,2) NOT NULL,
    currency        VARCHAR(8) NOT NULL DEFAULT 'Rs',
    status          VARCHAR(16) NOT NULL DEFAULT 'pending',  -- pending / paid / failed / refunded
    gateway_ref     VARCHAR(255) NULL,
    proof_image     VARCHAR(255) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at         DATETIME NULL,
    INDEX idx_pay_user (user_id),
    CONSTRAINT fk_pay_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- withdrawals ----------
DROP TABLE IF EXISTS withdrawals;
CREATE TABLE withdrawals (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    amount      DECIMAL(14,2) NOT NULL,
    method      VARCHAR(32) NOT NULL,            -- usdt / bank
    payee_info  TEXT NOT NULL,                   -- JSON
    status      VARCHAR(16) NOT NULL DEFAULT 'pending',
    note        VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    INDEX idx_wd_user (user_id),
    CONSTRAINT fk_wd_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- addresses ----------
DROP TABLE IF EXISTS addresses;
CREATE TABLE addresses (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    name        VARCHAR(64) NOT NULL,
    phone       VARCHAR(32) NOT NULL,
    country     VARCHAR(64) NOT NULL DEFAULT 'Sri Lanka',
    province    VARCHAR(64) NULL,
    city        VARCHAR(64) NULL,
    address1    VARCHAR(255) NOT NULL,
    is_default  TINYINT NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_addr_user (user_id),
    CONSTRAINT fk_addr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- winners (prize fulfilment) ----------
DROP TABLE IF EXISTS winners;
CREATE TABLE winners (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_id   BIGINT UNSIGNED NOT NULL UNIQUE,
    user_id     BIGINT UNSIGNED NOT NULL,
    product_id  BIGINT UNSIGNED NOT NULL,
    code        VARCHAR(16) NOT NULL,
    address_id  BIGINT UNSIGNED NULL,
    status      VARCHAR(16) NOT NULL DEFAULT 'pending',  -- pending / shipped / delivered / claimed
    tracking    VARCHAR(128) NULL,
    drawn_at    DATETIME NOT NULL,
    shipped_at  DATETIME NULL,
    delivered_at DATETIME NULL,
    claimed_at  DATETIME NULL,
    INDEX idx_win_user (user_id),
    CONSTRAINT fk_win_period FOREIGN KEY (period_id) REFERENCES periods(id) ON DELETE CASCADE,
    CONSTRAINT fk_win_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_win_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_win_address FOREIGN KEY (address_id) REFERENCES addresses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- commissions ----------
DROP TABLE IF EXISTS commissions;
CREATE TABLE commissions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,           -- earner (referrer)
    from_user_id    BIGINT UNSIGNED NOT NULL,           -- the referred user
    period_id       BIGINT UNSIGNED NULL,
    participation_id BIGINT UNSIGNED NULL,
    base_amount     DECIMAL(14,2) NOT NULL,
    rate            DECIMAL(6,4) NOT NULL DEFAULT 0.1000,
    amount          DECIMAL(14,2) NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_comm_user (user_id),
    CONSTRAINT fk_comm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- favorites ----------
DROP TABLE IF EXISTS favorites;
CREATE TABLE favorites (
    user_id     BIGINT UNSIGNED NOT NULL,
    product_id  BIGINT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, product_id),
    CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_fav_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- posts (晒单 / showcase) ----------
DROP TABLE IF EXISTS posts;
CREATE TABLE posts (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    period_id   BIGINT UNSIGNED NULL,
    content     TEXT NOT NULL,
    images      TEXT NULL,
    status      TINYINT NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_posts_user (user_id),
    CONSTRAINT fk_posts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_posts_period FOREIGN KEY (period_id) REFERENCES periods(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- banners ----------
DROP TABLE IF EXISTS banners;
CREATE TABLE banners (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    image       VARCHAR(255) NOT NULL,
    title_zh    VARCHAR(128) NULL,
    title_en    VARCHAR(128) NULL,
    link        VARCHAR(255) NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    status      TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- settings ----------
DROP TABLE IF EXISTS settings;
CREATE TABLE settings (
    `key`   VARCHAR(64) PRIMARY KEY,
    `value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- sessions ----------
DROP TABLE IF EXISTS sessions;
CREATE TABLE sessions (
    token       VARCHAR(64) PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    kind        VARCHAR(16) NOT NULL DEFAULT 'user',   -- user | admin
    expires_at  DATETIME NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sess_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- commission v2: ranks + monthly snapshot + group bonuses ----------
DROP TABLE IF EXISTS group_bonuses;
DROP TABLE IF EXISTS user_ranks;
DROP TABLE IF EXISTS ranks;
CREATE TABLE ranks (
    id              TINYINT UNSIGNED PRIMARY KEY,
    code            VARCHAR(8)   NOT NULL UNIQUE,
    name_zh         VARCHAR(32)  NOT NULL,
    name_en         VARCHAR(32)  NOT NULL,
    min_direct      INT UNSIGNED NOT NULL DEFAULT 0,
    min_team_volume DECIMAL(14,2) NOT NULL DEFAULT 0,
    bonus_rate      DECIMAL(6,4) NOT NULL DEFAULT 0,
    sub_lines       VARCHAR(64)  NULL,
    sort_order      TINYINT      NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_ranks (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    year_month      CHAR(7)      NOT NULL,
    rank_id         TINYINT UNSIGNED NOT NULL,
    direct_count    INT UNSIGNED NOT NULL DEFAULT 0,
    team_volume     DECIMAL(14,2) NOT NULL DEFAULT 0,
    locked_at       DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_month (user_id, year_month),
    INDEX idx_ur_rank (rank_id),
    CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ur_rank FOREIGN KEY (rank_id) REFERENCES ranks(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE group_bonuses (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    from_user_id    BIGINT UNSIGNED NOT NULL,
    period_id       BIGINT UNSIGNED NULL,
    participation_id BIGINT UNSIGNED NULL,
    depth           TINYINT UNSIGNED NOT NULL,
    rank_id         TINYINT UNSIGNED NOT NULL,
    base_amount     DECIMAL(14,2) NOT NULL,
    rate            DECIMAL(6,4) NOT NULL,
    amount          DECIMAL(14,2) NOT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_gb_user (user_id),
    INDEX idx_gb_from (from_user_id),
    CONSTRAINT fk_gb_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_gb_rank FOREIGN KEY (rank_id) REFERENCES ranks(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- engagement loop: winner proofs ----------
DROP TABLE IF EXISTS winner_proofs;
CREATE TABLE winner_proofs (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    winner_id     BIGINT UNSIGNED NOT NULL,
    user_id       BIGINT UNSIGNED NOT NULL,
    kind          VARCHAR(8) NOT NULL,                -- 'photo' | 'video'
    media_url     VARCHAR(512) NOT NULL,
    note          VARCHAR(255) NULL,
    status        VARCHAR(16) NOT NULL DEFAULT 'pending',
    reward_draws  INT NOT NULL DEFAULT 0,
    reject_reason VARCHAR(255) NULL,
    reviewer_id   BIGINT UNSIGNED NULL,
    reviewed_at   DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_proof_user (user_id),
    INDEX idx_proof_status (status),
    CONSTRAINT fk_proof_winner FOREIGN KEY (winner_id) REFERENCES winners(id) ON DELETE CASCADE,
    CONSTRAINT fk_proof_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- engagement loop: daily check-ins ----------
DROP TABLE IF EXISTS checkins;
CREATE TABLE checkins (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       BIGINT UNSIGNED NOT NULL,
    checkin_date  DATE NOT NULL,
    streak_day    INT NOT NULL,
    reward_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_day (user_id, checkin_date),
    CONSTRAINT fk_chk_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- engagement loop: social shares ----------
DROP TABLE IF EXISTS social_shares;
CREATE TABLE social_shares (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       BIGINT UNSIGNED NOT NULL,
    winner_id     BIGINT UNSIGNED NULL,
    platform      VARCHAR(32) NOT NULL,
    proof_url     VARCHAR(512) NOT NULL,
    post_url      VARCHAR(512) NULL,
    status        VARCHAR(16) NOT NULL DEFAULT 'pending',
    reward_draws  INT NOT NULL DEFAULT 0,
    reject_reason VARCHAR(255) NULL,
    reviewer_id   BIGINT UNSIGNED NULL,
    reviewed_at   DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_share_user (user_id),
    INDEX idx_share_status (status),
    CONSTRAINT fk_share_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_share_winner FOREIGN KEY (winner_id) REFERENCES winners(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
