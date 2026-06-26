# Server & Domain Migration Guide

How to bring **JackOne · 一夺** up on a new server and domain from this repo.

The repo contains **all code** but intentionally **excludes** three things (see
`.gitignore`): live config (`config/config.local.php`), user uploads
(`public/uploads/`), and runtime logs. You must move those separately — steps below.

---

## 0. Requirements on the new server

- PHP **8.3** with `pdo_mysql`, `mbstring`, `gd` (image uploads)
- MySQL **8** or MariaDB **10.5+**
- Apache with `mod_rewrite` + `mod_headers` (the app relies on `public/.htaccess`),
  or nginx with an equivalent `try_files ... /index.php` rule
- Timezone default is `Asia/Colombo` (override via `APP_TZ`)

---

## 1. Get the code

```bash
git clone https://github.com/kingtiong/1dollar.git /var/www/dollar
cd /var/www/dollar
```

---

## 2. Configuration

Settings come from `config/config.php`, overridden by `config/config.local.php`
(not in git) **or** environment variables. Pick one approach.

### Option A — config.local.php (matches the old server)

```bash
cp config/config.local.example.php config/config.local.php
```

Then edit `config/config.local.php`:

| Key | Set to |
|-----|--------|
| `app.base_url` | `https://<new-domain>` (no trailing slash) |
| `app.jwt_secret` | a **fresh** long random string (see note below) |
| `app.debug` | `false` in production |
| `db.host/name/user/pass` | the new database credentials |
| `payments.usdt.address` / `rate` | your TRON USDT address + rate |

### Option B — environment variables

`config.php` reads `APP_URL`, `APP_SECRET`, `APP_TZ`, `APP_DEBUG`,
`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `USDT_ADDRESS`,
`USDT_RATE`, `STRIPE_PK`, `STRIPE_SK`, `STRIPE_WEBHOOK`. Set these in your
Apache vhost / systemd / `.env` loader instead of a local config file.

> **jwt_secret note:** changing the secret invalidates all existing login
> sessions — every user (and admin) must log in again. That's fine for a
> migration; just expect it. If you want zero re-logins, copy the **exact**
> `jwt_secret` from the old server's `config.local.php`.

---

## 3. Database

The migrations are incremental on top of `schema.sql`, so for a **fresh**
install run them in date order:

```bash
mysql -u root -p -e "CREATE DATABASE lucky_mall CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

mysql -u root -p lucky_mall < database/schema.sql
mysql -u root -p lucky_mall < database/migrations/2026_05_22_commission_v2.sql
mysql -u root -p lucky_mall < database/migrations/2026_05_23_categories_si_bn.sql
mysql -u root -p lucky_mall < database/migrations/2026_05_23_products_si_bn.sql
mysql -u root -p lucky_mall < database/migrations/2026_06_06_bargain_and_support.sql

# optional demo/seed data — skip if importing real data below
mysql -u root -p lucky_mall < database/seed.sql
```

### Migrating REAL data (not a fresh install)

`schema.sql` + migrations only create empty tables. To carry over live users,
orders, balances, etc., dump the **old** database and import it instead of
running schema/migrations:

```bash
# on the OLD server
mysqldump -u lucky -p --single-transaction --routines lucky_mall > lucky_mall.sql

# copy to new server, then
mysql -u root -p lucky_mall < lucky_mall.sql
```

A real dump already contains the migrated schema, so do **not** also run the
migration files on top of it.

> ⚠️ Any PHP that selects new columns will return HTTP 500 until the matching
> migration has been applied. If you imported an older dump, re-run only the
> migrations dated **after** that dump.

---

## 4. User uploads

Not in git. Copy from the old server:

```bash
rsync -avz oldserver:/var/www/dollar/public/uploads/ /var/www/dollar/public/uploads/
```

Make the upload + storage dirs writable by the web user:

```bash
chown -R www-data:www-data public/uploads storage
chmod -R 775 public/uploads storage
```

---

## 5. Web server

Point the document root at **`public/`** (the front controller is
`public/index.php`).

**Apache vhost:**

```apache
<VirtualHost *:80>
    ServerName  <new-domain>
    DocumentRoot /var/www/dollar/public

    <Directory /var/www/dollar/public>
        AllowOverride All        # required so .htaccess rewrite rules load
        Require all granted
    </Directory>
</VirtualHost>
```

```bash
a2enmod rewrite headers && systemctl reload apache2
```

**nginx** (no `.htaccess`; replicate the rewrite):

```nginx
root /var/www/dollar/public;
index index.php;
location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
}
```

---

## 6. HTTPS

Issue a certificate for the new domain (matches `app.base_url`):

```bash
certbot --apache -d <new-domain>      # or --nginx
```

---

## 7. Verify

- [ ] `https://<new-domain>/` loads the H5 storefront
- [ ] `https://<new-domain>/admin/` loads the admin login
- [ ] Log in (users must re-auth if `jwt_secret` changed)
- [ ] Product images and uploaded proofs render (uploads copied + perms set)
- [ ] Switch languages (EN / 中文 / සිංහල / বাংলা) — i18n loads
- [ ] Place a test purchase / draw to confirm the DB is writable
- [ ] Check `storage/logs/` for PHP errors

---

## Quick checklist

| # | Step | Done |
|---|------|------|
| 1 | Clone repo | ☐ |
| 2 | Create `config.local.php` (new domain, secret, DB creds) | ☐ |
| 3 | Create DB + import schema/migrations **or** real mysqldump | ☐ |
| 4 | rsync `public/uploads/` + fix permissions | ☐ |
| 5 | Vhost → docroot `public/`, enable rewrite | ☐ |
| 6 | HTTPS cert for new domain | ☐ |
| 7 | Smoke-test storefront + admin | ☐ |
