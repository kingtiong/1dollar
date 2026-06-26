<?php
namespace App\Services;

use App\Core\Database;

/**
 * "砍一刀" bargain sessions.
 *
 * Owner starts a session on a bargain-eligible product; friends ("helpers") each
 * cut a chunk off the remaining amount. Cuts follow an ideal-progress curve so
 * progress slows visibly as helpers pile on — Pinduoduo-style "almost there".
 * The Nth helper (max_helpers) always completes the session by design, so users
 * always see a real path to the reward.
 *
 * On completion: owner + the final helper get +N free_draws (config-driven).
 * Free draws share the same wallet counter as check-in / social rewards.
 */
class BargainService
{
    public const DEFAULT_MAX_HELPERS = 15;
    public const DEFAULT_HOURS       = 48;

    /** Owner starts a new active session on a product. */
    public static function start(int $userId, int $productId): array
    {
        return Database::tx(function () use ($userId, $productId) {
            $product = Database::one(
                "SELECT id, bargain_eligible, bargain_target_cents, slot_price, status
                 FROM products WHERE id = ?",
                [$productId]
            );
            if (!$product) throw new \RuntimeException('Product not found');
            if ((int)$product['status'] !== 1) throw new \RuntimeException('Product not on sale');
            if ((int)$product['bargain_eligible'] !== 1) throw new \RuntimeException('Bargain not enabled for this product');

            // One active session per user per product — keeps share-links unambiguous.
            $existing = Database::one(
                "SELECT id, share_token FROM bargain_sessions
                 WHERE user_id = ? AND product_id = ? AND status = 'active' AND expires_at > NOW()",
                [$userId, $productId]
            );
            if ($existing) {
                return ['id' => (int)$existing['id'], 'share_token' => $existing['share_token'], 'reused' => true];
            }

            $target = (int)($product['bargain_target_cents'] ?: round((float)$product['slot_price'] * 100));
            if ($target < 10) throw new \RuntimeException('Target too small to bargain');

            $maxHelpers = (int)(Database::val("SELECT `value` FROM settings WHERE `key` = 'bargain_max_helpers'")
                ?: self::DEFAULT_MAX_HELPERS);
            $hours      = (int)(Database::val("SELECT `value` FROM settings WHERE `key` = 'bargain_session_hours'")
                ?: self::DEFAULT_HOURS);

            $token = bin2hex(random_bytes(12));
            $expires = date('Y-m-d H:i:s', time() + $hours * 3600);

            Database::q(
                "INSERT INTO bargain_sessions
                    (user_id, product_id, share_token, target_cents, current_cents,
                     max_helpers, helper_count, status, expires_at)
                 VALUES (?,?,?,?,0,?,0,'active',?)",
                [$userId, $productId, $token, $target, $maxHelpers, $expires]
            );
            return [
                'id'          => (int)Database::insertId(),
                'share_token' => $token,
                'reused'      => false,
            ];
        });
    }

    /**
     * Helper cuts the remaining amount.
     * Returns ['cut_cents', 'current_cents', 'target_cents', 'completed'].
     */
    public static function help(int $helperUserId, string $shareToken): array
    {
        return Database::tx(function () use ($helperUserId, $shareToken) {
            $s = Database::one(
                "SELECT * FROM bargain_sessions WHERE share_token = ? FOR UPDATE",
                [$shareToken]
            );
            if (!$s) throw new \RuntimeException('Bargain not found');
            self::expireIfNeeded($s);
            if ($s['status'] !== 'active') throw new \RuntimeException('Bargain is ' . $s['status']);
            if ((int)$s['user_id'] === $helperUserId) {
                throw new \RuntimeException("Can't cut your own bargain — share it to friends");
            }

            $dupe = Database::one(
                "SELECT id FROM bargain_helps WHERE session_id = ? AND helper_user_id = ?",
                [(int)$s['id'], $helperUserId]
            );
            if ($dupe) throw new \RuntimeException('You already cut for this one');

            $helperIndex = (int)$s['helper_count'] + 1;
            $cut = self::computeCutCents(
                (int)$s['target_cents'],
                (int)$s['current_cents'],
                $helperIndex,
                (int)$s['max_helpers']
            );

            $newCurrent  = (int)$s['current_cents'] + $cut;
            $completed   = $newCurrent >= (int)$s['target_cents'];

            Database::q(
                "INSERT INTO bargain_helps (session_id, helper_user_id, amount_cents)
                 VALUES (?,?,?)",
                [(int)$s['id'], $helperUserId, $cut]
            );

            if ($completed) {
                Database::q(
                    "UPDATE bargain_sessions
                     SET current_cents = target_cents, helper_count = ?,
                         status = 'completed', completed_at = NOW()
                     WHERE id = ?",
                    [$helperIndex, (int)$s['id']]
                );
                self::grantOwnerReward((int)$s['user_id']);
            } else {
                Database::q(
                    "UPDATE bargain_sessions
                     SET current_cents = ?, helper_count = ?
                     WHERE id = ?",
                    [$newCurrent, $helperIndex, (int)$s['id']]
                );
            }

            // Helper always gets a small reward — capped per day to discourage alt-farming.
            $helperReward = self::grantHelperReward($helperUserId);

            return [
                'cut_cents'      => $cut,
                'current_cents'  => $completed ? (int)$s['target_cents'] : $newCurrent,
                'target_cents'   => (int)$s['target_cents'],
                'completed'      => $completed,
                'helper_reward'  => $helperReward,
            ];
        });
    }

    /** Public view of a session (no login required). */
    public static function getByToken(string $token): ?array
    {
        $s = Database::one(
            "SELECT s.*, p.cover_image, p.value_amount,
                    p.name_zh, p.name_en, p.name_si, p.name_bn,
                    u.display_name AS owner_name, u.username AS owner_username
             FROM bargain_sessions s
             JOIN products p ON p.id = s.product_id
             JOIN users u ON u.id = s.user_id
             WHERE s.share_token = ?",
            [$token]
        );
        if (!$s) return null;
        self::expireIfNeeded($s);
        if ($s['status'] === 'active' && strtotime($s['expires_at']) <= time()) {
            $s['status'] = 'expired';
        }
        $helps = Database::all(
            "SELECT h.amount_cents, h.created_at, u.username, u.display_name, u.avatar
             FROM bargain_helps h
             JOIN users u ON u.id = h.helper_user_id
             WHERE h.session_id = ?
             ORDER BY h.id DESC LIMIT 30",
            [(int)$s['id']]
        );
        $s['helps'] = $helps;
        return $s;
    }

    /** List the caller's active + recent bargains. */
    public static function listMine(int $userId, int $limit = 20): array
    {
        return Database::all(
            "SELECT s.id, s.share_token, s.product_id, s.target_cents, s.current_cents,
                    s.helper_count, s.max_helpers, s.status, s.expires_at, s.created_at,
                    p.cover_image, p.name_zh, p.name_en, p.name_si, p.name_bn
             FROM bargain_sessions s
             JOIN products p ON p.id = s.product_id
             WHERE s.user_id = ?
             ORDER BY s.id DESC LIMIT $limit",
            [$userId]
        );
    }

    /* ---------- internals ---------- */

    /**
     * Ideal-progress curve: P(i) = target * (1 - (1 - i/N)^k).
     * Helper i contributes the gap between the curve at i and current progress,
     * with ±25% jitter so it doesn't look machine-perfect. The Nth helper always
     * closes the gap (guaranteed completion).
     *
     * Tuned so progress feels generous early (~16% on first cut), stalls hard in
     * the 80–99% band (the "差一两分" hook), and resolves on the final helper.
     */
    public static function computeCutCents(int $target, int $current, int $helperIndex, int $maxHelpers): int
    {
        if ($helperIndex >= $maxHelpers) {
            return max(0, $target - $current);
        }
        $remaining = $target - $current;
        if ($remaining <= 1) return 0;

        $k = 2.5;
        $rate = ($maxHelpers - $helperIndex) / $maxHelpers;
        $idealProgress = $target * (1 - pow($rate, $k));
        $base = (int)floor($idealProgress - $current);
        if ($base < 1) $base = 1;

        // ±25% jitter
        $jitter = 0.75 + (mt_rand(0, 1000) / 1000.0) * 0.50;
        $cut = (int)floor($base * $jitter);

        // Always leave at least 1 cent so the user sees the "near-miss" until the final helper.
        $cut = max(1, min($cut, $remaining - 1));
        return $cut;
    }

    private static function expireIfNeeded(array $s): void
    {
        if ($s['status'] === 'active' && strtotime($s['expires_at']) <= time()) {
            Database::q(
                "UPDATE bargain_sessions SET status = 'expired' WHERE id = ? AND status = 'active'",
                [(int)$s['id']]
            );
        }
    }

    private static function grantOwnerReward(int $ownerId): void
    {
        $draws = (int)(Database::val(
            "SELECT `value` FROM settings WHERE `key` = 'bargain_owner_reward_draws'"
        ) ?? 1);
        if ($draws > 0) {
            Database::q("UPDATE users SET free_draws = free_draws + ? WHERE id = ?", [$draws, $ownerId]);
        }
    }

    /**
     * Reward the helper for cutting, with a daily cap so alt accounts can't farm draws by
     * spamming helps. Returns the number of free_draws actually granted (0 if capped).
     */
    private static function grantHelperReward(int $helperId): int
    {
        $perHelp = (int)(Database::val(
            "SELECT `value` FROM settings WHERE `key` = 'bargain_helper_reward_draws'"
        ) ?? 1);
        if ($perHelp <= 0) return 0;

        $dailyCap = 5;  // hard-coded ceiling — tune later if abuse appears
        $helpsToday = (int)Database::val(
            "SELECT COUNT(*) FROM bargain_helps
             WHERE helper_user_id = ? AND created_at >= ?",
            [$helperId, date('Y-m-d 00:00:00')]
        );
        // This help is already inserted before we get here, so count includes it.
        if ($helpsToday > $dailyCap) return 0;

        Database::q("UPDATE users SET free_draws = free_draws + ? WHERE id = ?", [$perHelp, $helperId]);
        return $perHelp;
    }
}
