<?php
namespace App\Services;

use App\Core\Database;

class GroupBonusService
{
    /**
     * Award multi-level group bonus up the referrer chain.
     *
     * For each ancestor at depth 1..maxDepth:
     *   - effective_rate = max(0, ancestor_rate - max_rate_seen_below)
     *   - credit ancestor: amount = base * effective_rate
     *
     * The differential rule mirrors marketing-plan.html §3:
     * "若上下级同级别, 上级仅在下级所在分支获得 0% 差额奖" — keeps
     * total payout per branch <= max rank rate.
     */
    public static function award(int $buyerId, int $participationId, int $periodId, float $cost): void
    {
        if ($cost <= 0) return;

        $maxDepth = self::maxDepth();
        $ym = date('Y-m');

        $currentId = $buyerId;
        $maxBelowRate = 0.0;

        for ($depth = 1; $depth <= $maxDepth; $depth++) {
            $row = Database::one("SELECT referrer_id FROM users WHERE id = ?", [$currentId]);
            if (!$row || !$row['referrer_id']) break;
            $ancestorId = (int)$row['referrer_id'];

            $rank = Database::one(
                "SELECT r.id AS rank_id, r.bonus_rate
                 FROM user_ranks ur
                 JOIN ranks r ON r.id = ur.rank_id
                 WHERE ur.user_id = ? AND ur.year_month = ?",
                [$ancestorId, $ym]
            );
            $ancestorRate = $rank ? (float)$rank['bonus_rate'] : 0.0;
            $ancestorRankId = $rank ? (int)$rank['rank_id'] : 0;

            $effectiveRate = max(0.0, $ancestorRate - $maxBelowRate);

            if ($effectiveRate > 0.0) {
                $amount = round($cost * $effectiveRate, 2);
                if ($amount > 0) {
                    $u = Database::one("SELECT balance FROM users WHERE id = ? FOR UPDATE", [$ancestorId]);
                    if ($u) {
                        $newBal = round((float)$u['balance'] + $amount, 2);
                        Database::q("UPDATE users SET balance = ? WHERE id = ?", [$newBal, $ancestorId]);

                        Database::q(
                            "INSERT INTO group_bonuses
                             (user_id, from_user_id, period_id, participation_id, depth, rank_id, base_amount, rate, amount)
                             VALUES (?,?,?,?,?,?,?,?,?)",
                            [$ancestorId, $buyerId, $periodId, $participationId, $depth,
                             $ancestorRankId, $cost, $effectiveRate, $amount]
                        );

                        Database::q(
                            "INSERT INTO wallet_txns (user_id, kind, amount, balance_after, reference, note)
                             VALUES (?,?,?,?,?,?)",
                            [$ancestorId, 'group_bonus', $amount, $newBal, "part:$participationId",
                             "Team L{$depth} bonus from user #{$buyerId}"]
                        );
                    }
                }
            }

            if ($ancestorRate > $maxBelowRate) $maxBelowRate = $ancestorRate;
            $currentId = $ancestorId;
        }
    }

    private static function maxDepth(): int
    {
        $row = Database::one("SELECT `value` FROM settings WHERE `key` = 'group_bonus_depth'");
        $val = $row ? (int)$row['value'] : 7;
        return $val > 0 ? min($val, 15) : 7;
    }
}
