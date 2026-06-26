# Lucky Mall

A PHP + MySQL raffle-style shopping platform inspired by the H5 e-commerce
lottery model (低成本博取大奖 / "1-dollar shopping"). Users buy participation
slots toward a high-value product. When all slots for a period are filled, a
winner is drawn deterministically from the issued lucky codes.

## Stack

- PHP 8.3 (no framework — small home-grown router / PDO layer)
- MySQL 8 / MariaDB 10.5+
- Vanilla JS H5 mobile frontend, bilingual EN / 中文
- Admin panel built with vanilla JS

## Layout

```
config/         config.php (loaded once at boot)
database/       schema.sql, seed.sql
app/
  Core/         Config, Database, Router, Request, Response, Auth, Helpers, I18n
  Services/     DrawService, CommissionService, PaymentService
  Controllers/  Auth, Product, Purchase, Wallet, Misc, Admin
public/         web root (point apache here)
  index.php     front controller
  .htaccess     rewrite rules
  h5/           mobile frontend
  admin/        admin panel
  uploads/      user-uploaded images
storage/logs/   php error log
```

## Install

1. Web server document root: `/var/www/dollar/public`. For Apache + mod_rewrite
   the included `.htaccess` does the routing.

2. Create the database and user:
   ```sql
   CREATE DATABASE lucky_mall CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'lucky'@'localhost' IDENTIFIED BY 'strong-password';
   GRANT ALL ON lucky_mall.* TO 'lucky'@'localhost';
   ```

3. Import the schema and seed:
   ```bash
   mysql -ulucky -p lucky_mall < database/schema.sql
   mysql -ulucky -p lucky_mall < database/seed.sql
   ```

4. Configure. Either edit `config/config.php` or set environment variables in
   apache / php-fpm:
   ```
   DB_HOST=127.0.0.1  DB_NAME=lucky_mall  DB_USER=lucky  DB_PASS=...
   APP_SECRET=<long-random-string>
   APP_URL=https://your-domain.example
   APP_TZ=Asia/Colombo
   USDT_ADDRESS=Txxxxxxx...    USDT_RATE=300
   STRIPE_PK=...  STRIPE_SK=...
   ```
   Or copy to `config/config.local.php` and override.

5. Permissions:
   ```bash
   chown -R www-data:www-data storage public/uploads
   ```

## Default credentials (from seed)

- **Admin:** `admin` / `admin123`  →  https://your-domain/admin/
- **User:**  `demo`  / `demo123`   →  https://your-domain/h5/

Change both immediately on a real deployment.

## Features

### User (H5 mobile)
- Bilingual EN / 中文 toggle (top-right of every page)
- Home — winner strip, category tabs, product grid with progress bars
- All Products — search + list
- Product detail — period tabs, progress, Buy / Add-to-fav, participation log
- Latest Reveals — upcoming with countdown estimate + recently drawn winners
- My Page — wallet balance, points, participations, wins, favorites
- Wallet — recharge (Bank/USDT), withdraw, full transaction history
- Share — referral code + link, commission history (10% per referred buy)
- Address book — CRUD

### Admin
- Dashboard with KPIs
- Products CRUD (auto-creates first period)
- Periods view, force-draw
- Users search, balance adjust, ban/unban
- Payments — approve / reject pending recharges
- Withdrawals — approve / reject (auto-refunds on reject)
- Winners — mark as shipped with tracking
- Settings (commission_rate, min_withdraw, usdt_address, etc.)

### Draw algorithm
`DrawService::draw()` selects a winner once all slots are sold:

1. Gather all `lucky_codes` in insertion order.
2. Concatenate them with a random server salt.
3. Take SHA-256, use first 48 bits modulo the count as the index.
4. Record salt + hash + index in `periods.seed_block` for auditability.

Then a fresh period of the same product is auto-opened.

### Commissions
On each purchase the buyer's `referrer_id` (if any) is credited
`commission_rate` (default 10%) of the spend, written to `commissions` and
`wallet_txns`.

## Payments

Three gateway stubs ship out of the box:

- **manual** — bank transfer, user uploads proof, admin approves.
- **usdt**   — show address + USDT rate, user submits tx-hash, admin approves.
- **stripe** — wired into config but checkout/webhook integration is left as
  the real client's commercial setup. To enable, plug Stripe SDK into
  `PaymentService::createRecharge` and forward the webhook to
  `/api/admin/payments/approve` (or implement a public `/api/payments/webhook/stripe` endpoint).

All payments are routed through the same `wallet_txns` ledger so balances are
always consistent.

## Quick local run (no Apache)

```bash
cd public
php -S 127.0.0.1:8000
```

then open http://127.0.0.1:8000/h5/index.html

## License & images

The seeded SVG product images are generic placeholder art. Replace them with
your own licensed product photos before going live. The platform does not
ship with any third-party product imagery.
