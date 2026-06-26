<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\DrawService;

class PurchaseController
{
    public function buy(Request $req, array $params): void
    {
        $u = Auth::require($req);
        $periodId = (int)$params['id'];
        $slots = max(1, (int)$req->input('slots', 1));
        if ($slots > 1000) Response::fail('Too many slots in one purchase');

        try {
            $res = DrawService::purchase((int)$u['user_id'], $periodId, $slots, $req->ip());
        } catch (\Throwable $e) {
            Response::fail($e->getMessage());
        }
        Response::ok($res);
    }

    public function myOrders(Request $req): void
    {
        $u = Auth::require($req);
        $rows = Database::all(
            "SELECT pa.id, pa.slots_count, pa.amount_paid, pa.created_at,
                p.id AS period_id, p.period_no, p.status, p.winner_code,
                pr.id AS product_id, pr.name_zh, pr.name_en, pr.name_si, pr.name_bn,
                pr.cover_image, pr.value_amount
             FROM participations pa
             JOIN periods p ON p.id = pa.period_id
             JOIN products pr ON pr.id = p.product_id
             WHERE pa.user_id = ?
             ORDER BY pa.id DESC LIMIT 100",
            [$u['user_id']]
        );
        Response::ok($rows);
    }

    public function myWins(Request $req): void
    {
        $u = Auth::require($req);
        $rows = Database::all(
            "SELECT w.*, pr.name_zh, pr.name_en, pr.name_si, pr.name_bn, pr.cover_image, pr.value_amount,
                p.period_no,
                a.name AS addr_name, a.phone AS addr_phone,
                a.address1 AS addr_line, a.city AS addr_city,
                a.province AS addr_province, a.country AS addr_country
             FROM winners w
             JOIN products pr ON pr.id = w.product_id
             JOIN periods p ON p.id = w.period_id
             LEFT JOIN addresses a ON a.id = w.address_id
             WHERE w.user_id = ?
             ORDER BY w.id DESC",
            [$u['user_id']]
        );
        Response::ok($rows);
    }

    public function claimWin(Request $req, array $params): void
    {
        $u = Auth::require($req);
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) Response::fail('id is required');

        $w = Database::one(
            "SELECT id, status FROM winners WHERE id = ? AND user_id = ?",
            [$id, $u['user_id']]
        );
        if (!$w) Response::fail('Winner not found', 404);
        if ($w['status'] === 'claimed') Response::ok(['already' => true]);
        if ($w['status'] !== 'delivered') {
            Response::fail('Can only confirm receipt after delivery (current: ' . $w['status'] . ')');
        }

        Database::q("UPDATE winners SET status = 'claimed', claimed_at = NOW() WHERE id = ?", [$id]);
        Response::ok();
    }

    public function myCodes(Request $req, array $params): void
    {
        $u = Auth::require($req);
        $pid = (int)$params['id'];
        $rows = Database::all(
            "SELECT code, created_at FROM lucky_codes WHERE period_id = ? AND user_id = ?",
            [$pid, $u['user_id']]
        );
        Response::ok($rows);
    }
}
