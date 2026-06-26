<?php
namespace App\Services;

use App\Core\Database;
use App\Core\Helpers;

class DrawService
{
    /**
     * Buy N slots in a period for a user. Wraps wallet debit, participation record,
     * lucky codes and triggers draw if full. Returns ['lucky_codes' => [...], 'drawn' => bool].
     */
    public static function purchase(int $userId, int $periodId, int $slots, string $ip = ''): array
    {
        if ($slots < 1) throw new \InvalidArgumentException('slots must be >= 1');

        return Database::tx(function () use ($userId, $periodId, $slots, $ip) {
            // Lock period + user rows
            $period = Database::one(
                "SELECT p.*, pr.slot_price FROM periods p
                 JOIN products pr ON pr.id = p.product_id
                 WHERE p.id = ? FOR UPDATE",
                [$periodId]
            );
            if (!$period) throw new \RuntimeException('Period not found');
            if ((int)$period['status'] !== 1) throw new \RuntimeException('Period closed');

            $remaining = (int)$period['total_slots'] - (int)$period['sold_slots'];
            if ($slots > $remaining) throw new \RuntimeException('Not enough slots left');

            $price = (float)$period['slot_price'];

            $user = Database::one("SELECT * FROM users WHERE id = ? FOR UPDATE", [$userId]);
            if (!$user) throw new \RuntimeException('User not found');

            // Consume free_draws first (granted as engagement rewards), then charge balance.
            $freeAvail = (int)($user['free_draws'] ?? 0);
            $freeUsed  = max(0, min($slots, $freeAvail));
            $paidSlots = $slots - $freeUsed;
            $cost      = round($price * $paidSlots, 2);  // commissions & points scale with paid only

            if ((float)$user['balance'] < $cost) throw new \RuntimeException('Insufficient balance');

            $newBal = round((float)$user['balance'] - $cost, 2);
            if ($freeUsed > 0) {
                Database::q(
                    "UPDATE users SET balance = ?, free_draws = free_draws - ? WHERE id = ?",
                    [$newBal, $freeUsed, $userId]
                );
                Database::q(
                    "INSERT INTO wallet_txns (user_id, kind, amount, balance_after, reference, note)
                     VALUES (?,?,?,?,?,?)",
                    [$userId, 'free_draw', 0, $newBal, "period:$periodId",
                     "Used $freeUsed free draw(s)"]
                );
            } else {
                Database::q("UPDATE users SET balance = ? WHERE id = ?", [$newBal, $userId]);
            }
            if ($cost > 0) {
                Database::q(
                    "INSERT INTO wallet_txns (user_id, kind, amount, balance_after, reference, note)
                     VALUES (?,?,?,?,?,?)",
                    [$userId, 'buy', -$cost, $newBal, "period:$periodId", "Buy $paidSlots slot(s)"]
                );
            }

            // Participation — amount_paid reflects actual cash spent (free draws = 0 cash)
            Database::q(
                "INSERT INTO participations (period_id, user_id, slots_count, amount_paid, ip_addr)
                 VALUES (?,?,?,?,?)",
                [$periodId, $userId, $slots, $cost, $ip]
            );
            $partId = (int)Database::insertId();

            // Lucky codes
            $codes = [];
            $start = 10000001 + (int)$period['sold_slots'];
            for ($i = 0; $i < $slots; $i++) {
                $code = (string)($start + $i);
                Database::q(
                    "INSERT INTO lucky_codes (period_id, user_id, participation_id, code)
                     VALUES (?,?,?,?)",
                    [$periodId, $userId, $partId, $code]
                );
                $codes[] = $code;
            }

            // Update period
            $newSold = (int)$period['sold_slots'] + $slots;
            Database::q("UPDATE periods SET sold_slots = ? WHERE id = ?", [$newSold, $periodId]);

            // Award points
            $points = (int)$cost;
            Database::q("UPDATE users SET points = points + ? WHERE id = ?", [$points, $userId]);

            // Referral commission — direct (10%) + group bonus (V1..V5 differential)
            CommissionService::award($userId, $partId, $periodId, $cost);
            GroupBonusService::award($userId, $partId, $periodId, $cost);

            $drawn = false;
            if ($newSold >= (int)$period['total_slots']) {
                self::draw($periodId);
                $drawn = true;
            }

            return [
                'lucky_codes' => $codes,
                'drawn'       => $drawn,
                'cost'        => $cost,
                'balance'     => $newBal,
                'free_used'   => $freeUsed,
                'paid_slots'  => $paidSlots,
            ];
        });
    }

    /**
     * Pick the winning lucky code for a closed period.
     * Method: take the SHA256 of all lucky codes (in order) concatenated with a server salt,
     * then modulo the count to get the index. Deterministic + auditable via seed_block.
     */
    public static function draw(int $periodId): array
    {
        $period = Database::one("SELECT * FROM periods WHERE id = ? FOR UPDATE", [$periodId]);
        if (!$period) throw new \RuntimeException('Period not found');
        if ((int)$period['status'] >= 3) {
            return ['winner_code' => $period['winner_code'], 'winner_user_id' => $period['winner_user_id']];
        }

        Database::q("UPDATE periods SET status = 2 WHERE id = ?", [$periodId]);

        $codes = Database::all(
            "SELECT id, user_id, code FROM lucky_codes WHERE period_id = ? ORDER BY id ASC",
            [$periodId]
        );
        if (!$codes) throw new \RuntimeException('No codes to draw from');

        $salt = bin2hex(random_bytes(8));
        $joined = implode('|', array_column($codes, 'code')) . '|' . $salt;
        $hash   = hash('sha256', $joined);
        $idx    = hexdec(substr($hash, 0, 12)) % count($codes);
        $winner = $codes[$idx];

        $seedBlock = json_encode([
            'salt' => $salt, 'hash' => $hash, 'index' => $idx, 'total' => count($codes),
        ]);

        Database::q(
            "UPDATE periods SET status = 3, winner_user_id = ?, winner_code = ?,
                seed_block = ?, drawn_at = NOW() WHERE id = ?",
            [$winner['user_id'], $winner['code'], $seedBlock, $periodId]
        );

        // Snapshot the winner's default address (fallback to most-recent) so admins
        // have a concrete shipping target. NULL is OK — admin UI surfaces missing addresses.
        $addr = Database::one(
            "SELECT id FROM addresses WHERE user_id = ?
             ORDER BY is_default DESC, id DESC LIMIT 1",
            [$winner['user_id']]
        );
        $addressId = $addr ? (int)$addr['id'] : null;

        Database::q(
            "INSERT INTO winners (period_id, user_id, product_id, code, address_id, status, drawn_at)
             VALUES (?,?,?,?,?, 'pending', NOW())",
            [$periodId, $winner['user_id'], $period['product_id'], $winner['code'], $addressId]
        );

        // Auto-open next period of same product
        $next = (int)$period['period_no'] + 1;
        $product = Database::one("SELECT * FROM products WHERE id = ?", [$period['product_id']]);
        if ($product) {
            try {
                Database::q(
                    "INSERT INTO periods (product_id, period_no, total_slots, sold_slots, status)
                     VALUES (?,?,?,0,1)",
                    [$product['id'], $next, $product['default_total_slots']]
                );
            } catch (\Throwable $e) {
                // unique key collision is fine
            }
        }

        return ['winner_code' => $winner['code'], 'winner_user_id' => $winner['user_id']];
    }
}
