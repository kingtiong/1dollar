<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

class MiscController
{
    public function addresses(Request $req): void
    {
        $u = Auth::require($req);
        $rows = Database::all(
            "SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC",
            [$u['user_id']]
        );
        Response::ok($rows);
    }

    public function addressSave(Request $req): void
    {
        $u = Auth::require($req);
        $id = (int)$req->input('id', 0);
        $name = trim((string)$req->input('name'));
        $phone = trim((string)$req->input('phone'));
        $addr1 = trim((string)$req->input('address1'));
        $city  = trim((string)$req->input('city'));
        $prov  = trim((string)$req->input('province'));
        $country = trim((string)$req->input('country', 'Sri Lanka'));
        $isDef = (int)!!$req->input('is_default');
        if (!$name || !$phone || !$addr1) Response::fail('name/phone/address required');

        Database::tx(function () use ($u, $id, $name, $phone, $addr1, $city, $prov, $country, $isDef) {
            if ($isDef) Database::q("UPDATE addresses SET is_default = 0 WHERE user_id = ?", [$u['user_id']]);
            if ($id > 0) {
                Database::q(
                    "UPDATE addresses SET name=?, phone=?, address1=?, city=?, province=?, country=?, is_default=?
                     WHERE id = ? AND user_id = ?",
                    [$name, $phone, $addr1, $city, $prov, $country, $isDef, $id, $u['user_id']]
                );
            } else {
                Database::q(
                    "INSERT INTO addresses (user_id, name, phone, address1, city, province, country, is_default)
                     VALUES (?,?,?,?,?,?,?,?)",
                    [$u['user_id'], $name, $phone, $addr1, $city, $prov, $country, $isDef]
                );
            }
        });
        Response::ok();
    }

    public function addressDelete(Request $req, array $params): void
    {
        $u = Auth::require($req);
        Database::q("DELETE FROM addresses WHERE id = ? AND user_id = ?", [(int)$params['id'], $u['user_id']]);
        Response::ok();
    }

    public function favorites(Request $req): void
    {
        $u = Auth::require($req);
        $rows = Database::all(
            "SELECT pr.*, p.id AS period_id, p.period_no, p.total_slots, p.sold_slots
             FROM favorites f
             JOIN products pr ON pr.id = f.product_id
             LEFT JOIN periods p ON p.product_id = pr.id AND p.status = 1
             WHERE f.user_id = ?
             ORDER BY f.created_at DESC",
            [$u['user_id']]
        );
        Response::ok($rows);
    }

    public function favoriteToggle(Request $req): void
    {
        $u = Auth::require($req);
        $pid = (int)$req->input('product_id');
        $exists = Database::one(
            "SELECT product_id FROM favorites WHERE user_id = ? AND product_id = ?",
            [$u['user_id'], $pid]
        );
        if ($exists) {
            Database::q("DELETE FROM favorites WHERE user_id = ? AND product_id = ?", [$u['user_id'], $pid]);
            Response::ok(['favored' => false]);
        } else {
            Database::q("INSERT INTO favorites (user_id, product_id) VALUES (?, ?)", [$u['user_id'], $pid]);
            Response::ok(['favored' => true]);
        }
    }

    public function commissions(Request $req): void
    {
        $u = Auth::require($req);
        $rows = Database::all(
            "SELECT c.*, fu.username AS from_username
             FROM commissions c
             JOIN users fu ON fu.id = c.from_user_id
             WHERE c.user_id = ? ORDER BY c.id DESC LIMIT 100",
            [$u['user_id']]
        );
        $count = (int)Database::val("SELECT COUNT(*) FROM users WHERE referrer_id = ?", [$u['user_id']]);
        $total = (float)Database::val("SELECT COALESCE(SUM(amount),0) FROM commissions WHERE user_id = ?", [$u['user_id']]);
        Response::ok(['list' => $rows, 'referrals_count' => $count, 'total_amount' => $total]);
    }

    public function team(Request $req): void
    {
        $u = Auth::require($req);
        $userId = (int)$u['user_id'];
        $ym = date('Y-m');

        $ranks = Database::all("SELECT * FROM ranks ORDER BY sort_order ASC");

        $snap = Database::one(
            "SELECT rank_id, direct_count, team_volume
             FROM user_ranks WHERE user_id = ? AND year_month = ?",
            [$userId, $ym]
        ) ?: ['rank_id' => 0, 'direct_count' => 0, 'team_volume' => 0.00];

        $directs = Database::all(
            "SELECT u.id, u.username, u.display_name, u.avatar, u.created_at,
                    COALESCE(SUM(CASE WHEN p.created_at >= ? AND p.created_at < ?
                                      THEN p.cost ELSE 0 END), 0) AS volume_month
             FROM users u
             LEFT JOIN participations p ON p.user_id = u.id
             WHERE u.referrer_id = ?
             GROUP BY u.id
             ORDER BY volume_month DESC, u.id DESC
             LIMIT 100",
            [$ym . '-01 00:00:00', date('Y-m-01 00:00:00', strtotime("$ym-01 +1 month")), $userId]
        );

        $totalTeam = (int)Database::val(
            "WITH RECURSIVE downline AS (
                SELECT id FROM users WHERE referrer_id = ?
                UNION ALL
                SELECT u.id FROM downline d JOIN users u ON u.referrer_id = d.id
            )
            SELECT COUNT(*) FROM downline",
            [$userId]
        );

        $bonuses = Database::all(
            "SELECT gb.id, gb.from_user_id, gb.depth, gb.amount, gb.rate, gb.created_at,
                    fu.username AS from_username, fu.display_name AS from_display
             FROM group_bonuses gb
             JOIN users fu ON fu.id = gb.from_user_id
             WHERE gb.user_id = ?
             ORDER BY gb.id DESC LIMIT 20",
            [$userId]
        );

        $earnedMonth = (float)Database::val(
            "SELECT COALESCE(SUM(amount),0) FROM group_bonuses
             WHERE user_id = ? AND created_at >= ? AND created_at < ?",
            [$userId, $ym . '-01 00:00:00', date('Y-m-01 00:00:00', strtotime("$ym-01 +1 month"))]
        );

        Response::ok([
            'year_month'    => $ym,
            'snapshot'      => $snap,
            'ranks'         => $ranks,
            'directs'       => $directs,
            'total_team'    => $totalTeam,
            'bonuses'       => $bonuses,
            'earned_month'  => $earnedMonth,
        ]);
    }

    public function settings(Request $req): void
    {
        $rows = Database::all("SELECT `key`, `value` FROM settings");
        $kv = [];
        foreach ($rows as $r) $kv[$r['key']] = $r['value'];
        unset($kv['usdt_address']);  // don't expose unfiltered
        Response::ok($kv);
    }

    public function upload(Request $req): void
    {
        Auth::require($req);
        if (empty($_FILES['file']['tmp_name'])) Response::fail('No file');
        $f = $_FILES['file'];
        $mime = mime_content_type($f['tmp_name']);
        $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
        if (!in_array($mime, $allowed)) Response::fail('Unsupported file type');
        if ($f['size'] > 5 * 1024 * 1024) Response::fail('File too large');

        $ext = match($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png',
            'image/webp' => 'webp', 'image/gif' => 'gif',
        };
        $name = date('Ymd') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $dest = __DIR__ . '/../../public/uploads/' . $name;
        @mkdir(dirname($dest), 0775, true);
        move_uploaded_file($f['tmp_name'], $dest);
        Response::ok(['url' => '/uploads/' . $name]);
    }
}
