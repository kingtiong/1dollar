<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

class ProductController
{
    public function home(Request $req): void
    {
        // Categories
        $cats = Database::all("SELECT id, slug, name_zh, name_en, name_si, name_bn FROM categories WHERE status=1 ORDER BY sort_order");

        // Banners
        $banners = Database::all("SELECT * FROM banners WHERE status=1 ORDER BY sort_order");

        // Recent winners
        $recent = Database::all(
            "SELECT w.code, w.drawn_at, p.id AS period_id, p.period_no,
                pr.id AS product_id, pr.name_zh, pr.name_en, pr.name_si, pr.name_bn,
                pr.cover_image, pr.value_amount,
                u.display_name, u.username
             FROM winners w
             JOIN periods p ON p.id = w.period_id
             JOIN products pr ON pr.id = w.product_id
             JOIN users u ON u.id = w.user_id
             ORDER BY w.drawn_at DESC LIMIT 8"
        );

        // Featured open periods grouped by category
        $where = "p.status = 1 AND pr.status = 1";
        $params = [];
        if (!empty($req->query['category_id'])) {
            $where .= " AND pr.category_id = ?";
            $params[] = (int)$req->query['category_id'];
        }
        $list = Database::all(
            "SELECT p.id AS period_id, p.period_no, p.total_slots, p.sold_slots,
                pr.id, pr.name_zh, pr.name_en, pr.name_si, pr.name_bn,
                pr.cover_image, pr.value_amount, pr.slot_price,
                pr.category_id
             FROM periods p
             JOIN products pr ON pr.id = p.product_id
             WHERE $where
             ORDER BY pr.sort_order DESC, p.id DESC
             LIMIT 30",
            $params
        );

        Response::ok([
            'categories' => $cats,
            'banners'    => $banners,
            'winners'    => $recent,
            'products'   => $list,
        ]);
    }

    public function list(Request $req): void
    {
        $page = max(1, (int)($req->query['page'] ?? 1));
        $size = min(50, max(5, (int)($req->query['size'] ?? 20)));
        $off  = ($page - 1) * $size;

        $where = "p.status = 1 AND pr.status = 1";
        $params = [];
        if (!empty($req->query['q'])) {
            $where .= " AND (pr.name_zh LIKE ? OR pr.name_en LIKE ? OR pr.name_si LIKE ? OR pr.name_bn LIKE ?)";
            $kw = '%' . $req->query['q'] . '%';
            $params[] = $kw; $params[] = $kw; $params[] = $kw; $params[] = $kw;
        }
        if (!empty($req->query['category_id'])) {
            $where .= " AND pr.category_id = ?";
            $params[] = (int)$req->query['category_id'];
        }

        $rows = Database::all(
            "SELECT p.id AS period_id, p.period_no, p.total_slots, p.sold_slots,
                pr.id, pr.name_zh, pr.name_en, pr.name_si, pr.name_bn,
                pr.cover_image, pr.value_amount, pr.slot_price
             FROM periods p JOIN products pr ON pr.id = p.product_id
             WHERE $where
             ORDER BY pr.sort_order DESC, p.id DESC
             LIMIT $size OFFSET $off",
            $params
        );

        Response::ok(['list' => $rows, 'page' => $page, 'size' => $size]);
    }

    public function detail(Request $req, array $params): void
    {
        $id = (int)$params['id'];
        $period = Database::one(
            "SELECT p.*, pr.name_zh, pr.name_en, pr.name_si, pr.name_bn,
                pr.description_zh, pr.description_en, pr.description_si, pr.description_bn,
                pr.cover_image, pr.gallery, pr.value_amount, pr.slot_price, pr.category_id,
                pr.bargain_eligible, pr.bargain_target_cents,
                u.display_name AS winner_name
             FROM periods p
             JOIN products pr ON pr.id = p.product_id
             LEFT JOIN users u ON u.id = p.winner_user_id
             WHERE p.id = ?",
            [$id]
        );
        if (!$period) Response::fail('not found', 404);

        $history = Database::all(
            "SELECT id, period_no, status, winner_code, drawn_at
             FROM periods WHERE product_id = ? ORDER BY period_no DESC LIMIT 10",
            [$period['product_id']]
        );

        $records = Database::all(
            "SELECT pa.id, pa.slots_count, pa.created_at, u.display_name, u.username, u.avatar
             FROM participations pa
             JOIN users u ON u.id = pa.user_id
             WHERE pa.period_id = ? ORDER BY pa.id DESC LIMIT 50",
            [$id]
        );

        Response::ok(['period' => $period, 'history' => $history, 'records' => $records]);
    }

    public function reveals(Request $req): void
    {
        // Upcoming (period is full but not yet drawn) + recently drawn
        $upcoming = Database::all(
            "SELECT p.id, p.period_no, p.total_slots, p.sold_slots,
                pr.name_zh, pr.name_en, pr.name_si, pr.name_bn,
                pr.cover_image, pr.value_amount
             FROM periods p JOIN products pr ON pr.id = p.product_id
             WHERE p.status IN (1,2) AND p.sold_slots >= p.total_slots * 0.9
             ORDER BY (p.sold_slots/p.total_slots) DESC LIMIT 10"
        );
        $drawn = Database::all(
            "SELECT p.id, p.period_no, p.winner_code, p.drawn_at,
                pr.id AS product_id, pr.name_zh, pr.name_en, pr.name_si, pr.name_bn,
                pr.cover_image, pr.value_amount,
                u.display_name, u.username
             FROM periods p
             JOIN products pr ON pr.id = p.product_id
             JOIN users u ON u.id = p.winner_user_id
             WHERE p.status = 3
             ORDER BY p.drawn_at DESC LIMIT 30"
        );
        Response::ok(['upcoming' => $upcoming, 'drawn' => $drawn]);
    }
}
