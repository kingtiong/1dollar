-- ================================================================
-- Demo seed data
-- Default admin: admin / admin123
-- Demo user:     demo  / demo123
-- ================================================================

INSERT INTO admins (username, password_hash, role) VALUES
('admin', '$2y$10$5ub4iEZBLzOEJOzKCIqvmeQJG5wD4KEdg2obcWJ6D2xmnER391gte', 'super');
-- password_hash above verifies against the password 'admin123'

INSERT INTO categories (slug, name_zh, name_en, sort_order) VALUES
('phone',   '手机数码', 'Phones',      1),
('home',    '家用电器', 'Appliances',  2),
('vehicle', '交通工具', 'Vehicles',    3),
('food',    '食品饮料', 'Food',        4),
('luxury',  '奢侈品',   'Luxury',      5),
('crypto',  '加密货币', 'Crypto',      6);

INSERT INTO users (username, password_hash, display_name, referral_code, balance, points) VALUES
('demo', '$2y$10$71tCt/4lEP1Ty5il6s3LsO8NGMzbnCV1TZvofEsZvMWzTbhpFLpCy', 'Demo User', 'DEMO0001', 500.00, 100);
-- password_hash above verifies against the password 'demo123'

-- Products
INSERT INTO products (category_id, name_zh, name_en, description_zh, description_en, cover_image, value_amount, slot_price, default_total_slots, sort_order) VALUES
(1, 'Apple iPhone 17 Pro Max 1TB', 'Apple iPhone 17 Pro Max 1TB',
   '最新一代旗舰手机，配备 1TB 大存储。', 'Latest flagship phone with 1TB storage.',
   '/h5/img/product-iphone.svg', 1888.00, 1.00, 1888, 10),
(1, 'Samsung Galaxy S24 Ultra', 'Samsung Galaxy S24 Ultra',
   '三星旗舰超大屏拍照手机。', 'Samsung flagship with large screen and pro camera.',
   '/h5/img/product-samsung.svg', 1288.00, 1.00, 1288, 9),
(1, '华为 Mate XTs 三折屏', 'Huawei Mate XTs Foldable',
   '三折屏旗舰，超薄超轻。', 'Triple-fold flagship, ultra light.',
   '/h5/img/product-huawei.svg', 2088.00, 1.00, 2088, 8),
(5, 'Franck Muller 男士腕表', 'Franck Muller Mens Watch',
   '高端瑞士机械腕表。', 'High-end Swiss mechanical watch.',
   '/h5/img/product-watch.svg', 2000.00, 1.00, 2000, 7),
(5, 'IWC 飞行员系列自动腕表', 'IWC Pilots Watch Automatic',
   '经典飞行员腕表，自动机械。', 'Classic pilots watch, automatic.',
   '/h5/img/product-watch.svg', 1000.00, 1.00, 1000, 6),
(6, '1000 USDT 奖金', '1000 USDT Bonus',
   '直接到账 1000 USDT。', 'Receive 1000 USDT directly.',
   '/h5/img/product-usdt.svg', 8888.00, 1.00, 8888, 5),
(3, 'Porsche 911 Carrera', 'Porsche 911 Carrera',
   '经典跑车 911。', 'Iconic sports car 911.',
   '/h5/img/product-porsche.svg', 911000.00, 1.00, 91100, 4);

-- Open periods for each product
INSERT INTO periods (product_id, period_no, total_slots, sold_slots, status) VALUES
(1, 138, 1888, 1537, 1),
(2, 138, 1288, 1050, 1),
(3, 173, 2088,  120, 1),
(4, 133, 2000, 1876, 1),
(5,  72, 1000,  938, 1),
(6, 121, 8888, 1560, 1),
(7,  78, 91100, 12000, 1);

-- A couple of already-drawn periods (for "Latest Reveals")
INSERT INTO periods (product_id, period_no, total_slots, sold_slots, status, winner_user_id, winner_code, drawn_at) VALUES
(1, 137, 1888, 1888, 3, 1, '10001859', NOW() - INTERVAL 1 DAY),
(6, 120, 8888, 8888, 3, 1, '10001049', NOW() - INTERVAL 2 DAY),
(2, 139, 1288, 1288, 3, 1, '10000412', NOW() - INTERVAL 3 DAY),
(3, 176, 2088, 2088, 3, 1, '10000891', NOW() - INTERVAL 4 DAY);

INSERT INTO winners (period_id, user_id, product_id, code, status, drawn_at)
SELECT id, winner_user_id, product_id, winner_code, 'pending', drawn_at
FROM periods WHERE status = 3;

INSERT INTO banners (image, title_zh, title_en, sort_order) VALUES
('/h5/img/banner1.svg', '快来围观，一起见证幸运儿', 'Watch our latest lucky winners', 1),
('/h5/img/banner2.svg', '低成本博取大奖', 'Big prizes for low entry', 2);

INSERT INTO settings (`key`, `value`) VALUES
('site_name',           'JackOne'),
('site_name_zh',        '一夺'),
('site_slogan',         'One shot. One jackpot.'),
('site_slogan_zh',      '一注一夺'),
('currency',            'USD'),
('commission_rate',     '0.10'),
('group_bonus_depth',   '7'),
('min_withdraw',        '100'),
('usdt_address',        'TXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'),
('usdt_rate',           '300'),
('signup_bonus',        '10'),
('points_per_rs',       '1');

-- ---------- ranks: V0..V5 thresholds + bonus rates ----------
INSERT INTO ranks (id, code, name_zh, name_en, min_direct, min_team_volume, bonus_rate, sub_lines, sort_order) VALUES
(0, 'V0', '普通会员',     'Member',            0,      0.00, 0.0000, NULL,    0),
(1, 'V1', '青铜会员',     'Bronze',            5,   1000.00, 0.0100, NULL,    1),
(2, 'V2', '白银合伙人',   'Silver Partner',   10,   5000.00, 0.0200, NULL,    2),
(3, 'V3', '黄金合伙人',   'Gold Partner',      0,  20000.00, 0.0300, '2x V2', 3),
(4, 'V4', '铂金合伙人',   'Platinum Partner',  0,  50000.00, 0.0400, '2x V3', 4),
(5, 'V5', '钻石合伙人',   'Diamond Partner',   0, 200000.00, 0.0500, '3x V4', 5);
