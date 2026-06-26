<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Helpers;
use App\Core\Request;
use App\Core\Response;
use App\Services\DrawService;
use App\Services\PaymentService;

class AdminController
{
    public function login(Request $req): void
    {
        $u = (string)$req->input('username');
        $p = (string)$req->input('password');
        $row = Database::one("SELECT * FROM admins WHERE username = ?", [$u]);
        if (!$row || !password_verify($p, $row['password_hash']))
            Response::fail('Invalid admin credentials', 401);
        $token = Auth::issue((int)$row['id'], 'admin');
        Helpers::setCookie(Config::get('session.cookie') . '_admin', $token, Config::get('session.lifetime'));
        Response::ok(['token' => $token]);
    }

    public function dashboard(Request $req): void
    {
        Auth::requireAdmin($req);
        $s = [
            'users'         => (int)Database::val("SELECT COUNT(*) FROM users"),
            'open_periods'  => (int)Database::val("SELECT COUNT(*) FROM periods WHERE status = 1"),
            'drawn_periods' => (int)Database::val("SELECT COUNT(*) FROM periods WHERE status = 3"),
            'pending_pay'   => (int)Database::val("SELECT COUNT(*) FROM payments WHERE status = 'pending'"),
            'pending_wd'    => (int)Database::val("SELECT COUNT(*) FROM withdrawals WHERE status = 'pending'"),
            'recharge_total'=> (float)Database::val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid'"),
            'sold_today'    => (int)Database::val("SELECT COALESCE(SUM(slots_count),0) FROM participations WHERE DATE(created_at) = CURDATE()"),
        ];
        Response::ok($s);
    }

    /* ---------- products ---------- */
    public function listProducts(Request $req): void
    {
        Auth::requireAdmin($req);
        Response::ok(Database::all("SELECT p.*, c.name_en AS category FROM products p LEFT JOIN categories c ON c.id = p.category_id ORDER BY p.id DESC"));
    }

    public function saveProduct(Request $req): void
    {
        Auth::requireAdmin($req);
        $id = (int)$req->input('id', 0);
        $data = [
            'category_id'         => (int)$req->input('category_id') ?: null,
            'name_zh'             => trim((string)$req->input('name_zh')),
            'name_en'             => trim((string)$req->input('name_en')),
            'name_si'             => trim((string)$req->input('name_si')) ?: null,
            'name_bn'             => trim((string)$req->input('name_bn')) ?: null,
            'description_zh'      => (string)$req->input('description_zh'),
            'description_en'      => (string)$req->input('description_en'),
            'description_si'      => (string)$req->input('description_si') ?: null,
            'description_bn'      => (string)$req->input('description_bn') ?: null,
            'cover_image'         => (string)$req->input('cover_image'),
            'value_amount'        => (float)$req->input('value_amount'),
            'slot_price'          => (float)$req->input('slot_price', 1),
            'default_total_slots' => (int)$req->input('default_total_slots'),
            'sort_order'          => (int)$req->input('sort_order', 0),
            'status'              => (int)!!$req->input('status', 1),
            'bargain_eligible'    => (int)!!$req->input('bargain_eligible', 0),
            'bargain_target_cents' => (int)$req->input('bargain_target_cents', 0) ?: null,
        ];
        if (!$data['name_en'] || !$data['value_amount']) Response::fail('name_en + value_amount required');

        if ($id > 0) {
            $set = []; $vals = [];
            foreach ($data as $k => $v) { $set[] = "$k = ?"; $vals[] = $v; }
            $vals[] = $id;
            Database::q("UPDATE products SET " . implode(', ', $set) . " WHERE id = ?", $vals);
        } else {
            $cols = implode(',', array_keys($data));
            $marks = implode(',', array_fill(0, count($data), '?'));
            Database::q("INSERT INTO products ($cols) VALUES ($marks)", array_values($data));
            $id = (int)Database::insertId();

            // Auto-create period #1
            Database::q(
                "INSERT INTO periods (product_id, period_no, total_slots, sold_slots, status)
                 VALUES (?, 1, ?, 0, 1)",
                [$id, $data['default_total_slots']]
            );
        }
        Response::ok(['id' => $id]);
    }

    public function deleteProduct(Request $req, array $params): void
    {
        Auth::requireAdmin($req);
        Database::q("UPDATE products SET status = 0 WHERE id = ?", [(int)$params['id']]);
        Response::ok();
    }

    /* ---------- periods ---------- */
    public function listPeriods(Request $req): void
    {
        Auth::requireAdmin($req);
        $rows = Database::all(
            "SELECT p.*, pr.name_en, pr.cover_image
             FROM periods p JOIN products pr ON pr.id = p.product_id
             ORDER BY p.id DESC LIMIT 200"
        );
        Response::ok($rows);
    }

    public function forceDraw(Request $req, array $params): void
    {
        Auth::requireAdmin($req);
        try {
            // Need to be at least partially full
            $p = Database::one("SELECT * FROM periods WHERE id = ?", [(int)$params['id']]);
            if (!$p) Response::fail('not found', 404);
            if ((int)$p['sold_slots'] < 1) Response::fail('No participations yet');
            $r = DrawService::draw((int)$params['id']);
            Response::ok($r);
        } catch (\Throwable $e) {
            Response::fail($e->getMessage());
        }
    }

    /* ---------- users ---------- */
    public function listUsers(Request $req): void
    {
        Auth::requireAdmin($req);
        $q = trim((string)($req->query['q'] ?? ''));
        $params = [];
        $where = "1=1";
        if ($q !== '') {
            $where .= " AND (username LIKE ? OR email LIKE ? OR phone LIKE ?)";
            $kw = "%$q%"; $params = [$kw, $kw, $kw];
        }
        $rows = Database::all(
            "SELECT id, username, email, phone, display_name, balance, points,
                referral_code, referrer_id, status, created_at
             FROM users WHERE $where ORDER BY id DESC LIMIT 200",
            $params
        );
        Response::ok($rows);
    }

    public function adjustUser(Request $req): void
    {
        Auth::requireAdmin($req);
        $uid    = (int)$req->input('user_id');
        $amount = (float)$req->input('amount');
        $note   = (string)$req->input('note', 'Manual adjustment');
        Database::tx(function () use ($uid, $amount, $note) {
            $u = Database::one("SELECT balance FROM users WHERE id = ? FOR UPDATE", [$uid]);
            if (!$u) throw new \RuntimeException('user not found');
            $nb = round((float)$u['balance'] + $amount, 2);
            if ($nb < 0) throw new \RuntimeException('Would result in negative balance');
            Database::q("UPDATE users SET balance = ? WHERE id = ?", [$nb, $uid]);
            Database::q(
                "INSERT INTO wallet_txns (user_id, kind, amount, balance_after, note)
                 VALUES (?, 'adjust', ?, ?, ?)",
                [$uid, $amount, $nb, $note]
            );
        });
        Response::ok();
    }

    public function setUserStatus(Request $req): void
    {
        Auth::requireAdmin($req);
        Database::q("UPDATE users SET status = ? WHERE id = ?",
            [(int)$req->input('status'), (int)$req->input('user_id')]);
        Response::ok();
    }

    /* ---------- payments ---------- */
    public function listPayments(Request $req): void
    {
        Auth::requireAdmin($req);
        $status = $req->query['status'] ?? 'pending';
        $rows = Database::all(
            "SELECT pa.*, u.username FROM payments pa JOIN users u ON u.id = pa.user_id
             WHERE pa.status = ? ORDER BY pa.id DESC LIMIT 200",
            [$status]
        );
        Response::ok($rows);
    }

    public function approvePayment(Request $req): void
    {
        Auth::requireAdmin($req);
        try {
            PaymentService::markPaid((string)$req->input('order_no'), (string)$req->input('gateway_ref'));
            Response::ok();
        } catch (\Throwable $e) {
            Response::fail($e->getMessage());
        }
    }

    public function rejectPayment(Request $req): void
    {
        Auth::requireAdmin($req);
        Database::q("UPDATE payments SET status = 'failed' WHERE order_no = ?", [(string)$req->input('order_no')]);
        Response::ok();
    }

    /* ---------- withdrawals ---------- */
    public function listWithdrawals(Request $req): void
    {
        Auth::requireAdmin($req);
        $rows = Database::all(
            "SELECT w.*, u.username FROM withdrawals w JOIN users u ON u.id = w.user_id
             WHERE w.status = ? ORDER BY w.id DESC LIMIT 200",
            [(string)($req->query['status'] ?? 'pending')]
        );
        Response::ok($rows);
    }

    public function approveWithdrawal(Request $req): void
    {
        Auth::requireAdmin($req);
        $id = (int)$req->input('id');
        Database::q(
            "UPDATE withdrawals SET status = 'paid', processed_at = NOW(), note = ? WHERE id = ?",
            [(string)$req->input('note', ''), $id]
        );
        Response::ok();
    }

    public function rejectWithdrawal(Request $req): void
    {
        Auth::requireAdmin($req);
        $id = (int)$req->input('id');
        Database::tx(function () use ($id, $req) {
            $w = Database::one("SELECT * FROM withdrawals WHERE id = ? FOR UPDATE", [$id]);
            if (!$w || $w['status'] !== 'pending') throw new \RuntimeException('not pending');
            Database::q("UPDATE withdrawals SET status = 'rejected', processed_at = NOW(), note = ? WHERE id = ?",
                [(string)$req->input('note', 'Rejected'), $id]);
            // refund
            $u = Database::one("SELECT balance FROM users WHERE id = ? FOR UPDATE", [$w['user_id']]);
            $nb = round((float)$u['balance'] + (float)$w['amount'], 2);
            Database::q("UPDATE users SET balance = ? WHERE id = ?", [$nb, $w['user_id']]);
            Database::q(
                "INSERT INTO wallet_txns (user_id, kind, amount, balance_after, note)
                 VALUES (?, 'refund', ?, ?, 'Withdrawal rejected #" . $id . "')",
                [$w['user_id'], (float)$w['amount'], $nb]
            );
        });
        Response::ok();
    }

    /* ---------- winners ---------- */
    public function listWinners(Request $req): void
    {
        Auth::requireAdmin($req);
        $rows = Database::all(
            "SELECT w.*, u.username, pr.name_en, p.period_no,
                    a.name AS addr_name, a.phone AS addr_phone,
                    a.address1 AS addr_line, a.city AS addr_city,
                    a.province AS addr_province, a.country AS addr_country
             FROM winners w
             JOIN users u ON u.id = w.user_id
             JOIN products pr ON pr.id = w.product_id
             JOIN periods p ON p.id = w.period_id
             LEFT JOIN addresses a ON a.id = w.address_id
             ORDER BY w.id DESC LIMIT 200"
        );
        Response::ok($rows);
    }

    public function shipWinner(Request $req): void
    {
        Auth::requireAdmin($req);
        $id = (int)$req->input('id');
        $tracking = trim((string)$req->input('tracking'));
        if ($id <= 0 || $tracking === '') Response::fail('id and tracking are required');

        $w = Database::one("SELECT id, status FROM winners WHERE id = ?", [$id]);
        if (!$w) Response::fail('Winner not found', 404);
        if ($w['status'] === 'claimed') Response::fail('Already claimed — cannot edit');

        // pending → shipped (stamp shipped_at), or just edit tracking on an already-shipped row.
        if ($w['status'] === 'pending') {
            Database::q(
                "UPDATE winners SET status = 'shipped', tracking = ?, shipped_at = NOW() WHERE id = ?",
                [$tracking, $id]
            );
        } else {
            Database::q(
                "UPDATE winners SET tracking = ? WHERE id = ?",
                [$tracking, $id]
            );
        }
        Response::ok();
    }

    public function deliverWinner(Request $req): void
    {
        Auth::requireAdmin($req);
        $id = (int)$req->input('id');
        if ($id <= 0) Response::fail('id is required');

        $w = Database::one("SELECT id, status FROM winners WHERE id = ?", [$id]);
        if (!$w) Response::fail('Winner not found', 404);
        if ($w['status'] !== 'shipped') Response::fail('Can only mark delivered after shipped (current: ' . $w['status'] . ')');

        Database::q("UPDATE winners SET status = 'delivered', delivered_at = NOW() WHERE id = ?", [$id]);
        Response::ok();
    }

    /* ---------- commission v2: rank thresholds ---------- */
    public function listRanks(Request $req): void
    {
        Auth::requireAdmin($req);
        Response::ok(Database::all("SELECT * FROM ranks ORDER BY sort_order ASC"));
    }

    public function saveRank(Request $req): void
    {
        Auth::requireAdmin($req);
        $id = (int)$req->input('id', -1);
        if ($id < 0) Response::fail('id required');

        $row = Database::one("SELECT id FROM ranks WHERE id = ?", [$id]);
        if (!$row) Response::fail('rank not found', 404);

        $rate = (float)$req->input('bonus_rate', 0);
        if ($rate < 0 || $rate > 0.5) Response::fail('bonus_rate must be 0..0.5');

        Database::q(
            "UPDATE ranks SET name_zh=?, name_en=?, min_direct=?, min_team_volume=?, bonus_rate=?, sub_lines=?
             WHERE id = ?",
            [
                (string)$req->input('name_zh', ''),
                (string)$req->input('name_en', ''),
                max(0, (int)$req->input('min_direct', 0)),
                max(0, (float)$req->input('min_team_volume', 0)),
                $rate,
                $req->input('sub_lines') ? (string)$req->input('sub_lines') : null,
                $id,
            ]
        );
        Response::ok();
    }

    /* ---------- engagement loop: proof moderation ---------- */
    public function listProofs(Request $req): void
    {
        Auth::requireAdmin($req);
        $status = (string)($req->query['status'] ?? '');
        $where = ''; $args = [];
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $where = 'WHERE p.status = ?'; $args[] = $status;
        }
        $rows = Database::all(
            "SELECT p.*, u.username, pr.name_zh, pr.name_en, w.code, w.period_id
             FROM winner_proofs p
             JOIN users u ON u.id = p.user_id
             JOIN winners w ON w.id = p.winner_id
             JOIN products pr ON pr.id = w.product_id
             $where
             ORDER BY p.id DESC LIMIT 200",
            $args
        );
        Response::ok($rows);
    }

    public function approveProof(Request $req): void
    {
        $admin = Auth::requireAdmin($req);
        $id = (int)$req->input('id');
        if ($id <= 0) Response::fail('id required');

        Database::tx(function () use ($id, $admin) {
            $p = Database::one("SELECT * FROM winner_proofs WHERE id = ? FOR UPDATE", [$id]);
            if (!$p) Response::fail('Proof not found', 404);
            if ($p['status'] !== 'pending') Response::fail('Already ' . $p['status']);

            $rewardKey = $p['kind'] === 'video' ? 'proof_video_reward_draws' : 'proof_photo_reward_draws';
            $draws = (int)(Database::val("SELECT `value` FROM settings WHERE `key` = ?", [$rewardKey]) ?? 0);

            Database::q(
                "UPDATE winner_proofs SET status='approved', reward_draws=?, reviewer_id=?, reviewed_at=NOW()
                 WHERE id = ?",
                [$draws, (int)$admin['user_id'], $id]
            );
            if ($draws > 0) {
                Database::q("UPDATE users SET free_draws = free_draws + ? WHERE id = ?", [$draws, $p['user_id']]);
            }
        });
        Response::ok();
    }

    public function rejectProof(Request $req): void
    {
        $admin = Auth::requireAdmin($req);
        $id = (int)$req->input('id');
        $reason = trim((string)$req->input('reason'));
        if ($id <= 0) Response::fail('id required');
        if ($reason === '') Response::fail('Reason required so the user knows why');

        $p = Database::one("SELECT id, status FROM winner_proofs WHERE id = ?", [$id]);
        if (!$p) Response::fail('Proof not found', 404);
        if ($p['status'] !== 'pending') Response::fail('Already ' . $p['status']);

        Database::q(
            "UPDATE winner_proofs SET status='rejected', reject_reason=?, reviewer_id=?, reviewed_at=NOW()
             WHERE id = ?",
            [$reason, (int)$admin['user_id'], $id]
        );
        Response::ok();
    }

    /* ---------- engagement loop: social share moderation ---------- */
    public function listShares(Request $req): void
    {
        Auth::requireAdmin($req);
        $status = (string)($req->query['status'] ?? '');
        $where = ''; $args = [];
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $where = 'WHERE s.status = ?'; $args[] = $status;
        }
        $rows = Database::all(
            "SELECT s.*, u.username
             FROM social_shares s
             JOIN users u ON u.id = s.user_id
             $where
             ORDER BY s.id DESC LIMIT 200",
            $args
        );
        Response::ok($rows);
    }

    public function approveShare(Request $req): void
    {
        $admin = Auth::requireAdmin($req);
        $id = (int)$req->input('id');
        if ($id <= 0) Response::fail('id required');

        Database::tx(function () use ($id, $admin) {
            $s = Database::one("SELECT * FROM social_shares WHERE id = ? FOR UPDATE", [$id]);
            if (!$s) Response::fail('Share not found', 404);
            if ($s['status'] !== 'pending') Response::fail('Already ' . $s['status']);

            $draws = (int)(Database::val("SELECT `value` FROM settings WHERE `key` = 'share_reward_draws'") ?? 0);

            Database::q(
                "UPDATE social_shares SET status='approved', reward_draws=?, reviewer_id=?, reviewed_at=NOW()
                 WHERE id = ?",
                [$draws, (int)$admin['user_id'], $id]
            );
            if ($draws > 0) {
                Database::q("UPDATE users SET free_draws = free_draws + ? WHERE id = ?", [$draws, $s['user_id']]);
            }
        });
        Response::ok();
    }

    public function rejectShare(Request $req): void
    {
        $admin = Auth::requireAdmin($req);
        $id = (int)$req->input('id');
        $reason = trim((string)$req->input('reason'));
        if ($id <= 0) Response::fail('id required');
        if ($reason === '') Response::fail('Reason required so the user knows why');

        $s = Database::one("SELECT id, status FROM social_shares WHERE id = ?", [$id]);
        if (!$s) Response::fail('Share not found', 404);
        if ($s['status'] !== 'pending') Response::fail('Already ' . $s['status']);

        Database::q(
            "UPDATE social_shares SET status='rejected', reject_reason=?, reviewer_id=?, reviewed_at=NOW()
             WHERE id = ?",
            [$reason, (int)$admin['user_id'], $id]
        );
        Response::ok();
    }

    /* ---------- site logo upload ---------- */
    public function uploadSiteLogo(Request $req): void
    {
        Auth::requireAdmin($req);
        if (empty($_FILES['file']['tmp_name'])) Response::fail('No file');
        $f = $_FILES['file'];
        $mime = mime_content_type($f['tmp_name']) ?: '';
        $allowed = [
            'image/png'     => 'png',
            'image/jpeg'    => 'jpg',
            'image/webp'    => 'webp',
            'image/svg+xml' => 'svg',
            'image/svg'     => 'svg',
        ];
        if (!isset($allowed[$mime])) Response::fail('Unsupported file type: ' . $mime);
        if ($f['size'] > 5 * 1024 * 1024) Response::fail('File too large (max 5MB)');

        $ext = $allowed[$mime];
        $dir = __DIR__ . '/../../public/h5/img';
        @mkdir($dir, 0775, true);

        // Remove ONLY the previous primary upload (site-logo.png/jpg/webp/svg),
        // not the favicon variant (site-logo-fav.png) or the operator-managed
        // backup (site-logo.original.png).
        foreach (['png','jpg','jpeg','webp','svg'] as $oldExt) {
            $oldFile = $dir . '/site-logo.' . $oldExt;
            if (is_file($oldFile)) @unlink($oldFile);
        }

        $dest = $dir . '/site-logo.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], $dest)) Response::fail('Failed to save file', 500);
        @chmod($dest, 0644);

        $ts = time();
        $url = '/h5/img/site-logo.' . $ext . '?v=' . $ts;

        // Generate a 64x64 PNG variant for the browser favicon, if GD can read
        // the format. SVG uploads are skipped — layout.js falls back to the
        // primary URL in that case.
        $favPath = $dir . '/site-logo-fav.png';
        $favUrl  = null;
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true) && extension_loaded('gd')) {
            $loaders = ['png' => 'imagecreatefrompng', 'jpg' => 'imagecreatefromjpeg',
                        'jpeg' => 'imagecreatefromjpeg', 'webp' => 'imagecreatefromwebp'];
            $loader = $loaders[$ext];
            if (function_exists($loader)) {
                $src = @$loader($dest);
                if ($src) {
                    $sw = imagesx($src); $sh = imagesy($src);
                    $fav = imagecreatetruecolor(64, 64);
                    imagealphablending($fav, false); imagesavealpha($fav, true);
                    // Sample a background pixel near the corner so transparent-edged
                    // logos blend correctly when downsampled.
                    $bg = imagecolorat($src, 5, 5);
                    $bgR = ($bg >> 16) & 0xFF; $bgG = ($bg >> 8) & 0xFF; $bgB = $bg & 0xFF;
                    $bgC = imagecolorallocatealpha($fav, $bgR, $bgG, $bgB, 0);
                    imagefilledrectangle($fav, 0, 0, 63, 63, $bgC);
                    imagealphablending($fav, true);
                    imagecopyresampled($fav, $src, 0, 0, 0, 0, 64, 64, $sw, $sh);
                    imagealphablending($fav, false); imagesavealpha($fav, true);
                    if (@imagepng($fav, $favPath, 9)) {
                        @chmod($favPath, 0644);
                        $favUrl = '/h5/img/site-logo-fav.png?v=' . $ts;
                    }
                    imagedestroy($fav); imagedestroy($src);
                }
            }
        }

        file_put_contents(
            $dir . '/site-logo.json',
            json_encode(['url' => $url, 'fav_url' => $favUrl, 'ext' => $ext, 'ts' => $ts],
                        JSON_UNESCAPED_SLASHES)
        );

        Response::ok(['url' => $url, 'fav_url' => $favUrl, 'ext' => $ext]);
    }

    /* ---------- settings + categories + banners ---------- */
    public function settings(Request $req): void
    {
        Auth::requireAdmin($req);
        if ($req->method === 'POST') {
            foreach ((array)$req->input('items', []) as $k => $v) {
                Database::q(
                    "INSERT INTO settings (`key`, `value`) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
                    [(string)$k, (string)$v]
                );
            }
            Response::ok();
        }
        $rows = Database::all("SELECT * FROM settings ORDER BY `key`");
        Response::ok($rows);
    }
}
