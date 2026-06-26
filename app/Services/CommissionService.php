<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

class CommissionService
{
    /**
     * If the buyer has a referrer, credit them 10% of cost as commission.
     * Single-level only.
     */
    public static function award(int $buyerId, int $participationId, int $periodId, float $cost): void
    {
        $buyer = Database::one("SELECT referrer_id FROM users WHERE id = ?", [$buyerId]);
        if (!$buyer || !$buyer['referrer_id']) return;

        $referrerId = (int)$buyer['referrer_id'];
        $rate = (float)Config::get('commission.rate', 0.10);
        $amount = round($cost * $rate, 2);
        if ($amount <= 0) return;

        $ref = Database::one("SELECT balance FROM users WHERE id = ? FOR UPDATE", [$referrerId]);
        if (!$ref) return;

        $newBal = round((float)$ref['balance'] + $amount, 2);
        Database::q("UPDATE users SET balance = ? WHERE id = ?", [$newBal, $referrerId]);

        Database::q(
            "INSERT INTO commissions (user_id, from_user_id, period_id, participation_id,
                base_amount, rate, amount) VALUES (?,?,?,?,?,?,?)",
            [$referrerId, $buyerId, $periodId, $participationId, $cost, $rate, $amount]
        );

        Database::q(
            "INSERT INTO wallet_txns (user_id, kind, amount, balance_after, reference, note)
             VALUES (?,?,?,?,?,?)",
            [$referrerId, 'commission', $amount, $newBal, "part:$participationId",
             "Commission from user #$buyerId"]
        );
    }
}
