<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

/**
 * User-facing endpoints for the engagement loop:
 *   - winner proof uploads (photo file or video URL)
 *   - daily check-in
 *   - social-share reward claims
 *
 * Admin-side moderation lives in AdminController.
 */
class EngagementController
{
    /* =====================================================
       Winner proof uploads
       ===================================================== */

    /**
     * POST /api/me/wins/:id/upload-proof
     *   kind=photo  + multipart file=<image>      (saved to /uploads/<id>.<ext>)
     *   kind=video  + media_url=<https://...>     (external URL — YouTube/Douyin/TikTok/etc.)
     *   note optional, <=255 chars
     *
     * Only allowed on winners whose owner = current user AND whose status = 'claimed'.
     * The user can have multiple proofs per win; admin moderates each.
     */
    public function uploadProof(Request $req, array $params): void
    {
        $u = Auth::require($req);
        $winId = (int)($params['id'] ?? 0);
        if ($winId <= 0) Response::fail('id required');

        $w = Database::one(
            "SELECT id, status FROM winners WHERE id = ? AND user_id = ?",
            [$winId, $u['user_id']]
        );
        if (!$w) Response::fail('Winner not found', 404);
        if ($w['status'] !== 'claimed') {
            Response::fail('Can only upload proof after receipt is confirmed (current: ' . $w['status'] . ')');
        }

        $kind = (string)$req->input('kind', 'photo');
        if (!in_array($kind, ['photo', 'video'], true)) Response::fail('kind must be photo or video');
        $note = trim((string)$req->input('note', ''));
        if (mb_strlen($note) > 255) $note = mb_substr($note, 0, 255);

        $mediaUrl = '';
        if ($kind === 'photo') {
            if (empty($_FILES['file']['tmp_name'])) Response::fail('Photo file required');
            $f = $_FILES['file'];
            $mime = mime_content_type($f['tmp_name']) ?: '';
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($allowed[$mime])) Response::fail('Unsupported image type: ' . $mime);
            if ($f['size'] > 5 * 1024 * 1024) Response::fail('File too large (max 5MB)');

            $ext = $allowed[$mime];
            $name = 'proof_' . date('Ymd') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $dir = __DIR__ . '/../../public/uploads';
            @mkdir($dir, 0775, true);
            $dest = $dir . '/' . $name;
            if (!move_uploaded_file($f['tmp_name'], $dest)) Response::fail('Failed to save file', 500);
            @chmod($dest, 0644);
            $mediaUrl = '/uploads/' . $name;
        } else {
            $mediaUrl = trim((string)$req->input('media_url'));
            if ($mediaUrl === '' || !preg_match('~^https?://~i', $mediaUrl)) {
                Response::fail('media_url must be a valid http(s) URL for video proofs');
            }
            if (mb_strlen($mediaUrl) > 512) Response::fail('media_url too long');
        }

        Database::q(
            "INSERT INTO winner_proofs (winner_id, user_id, kind, media_url, note)
             VALUES (?, ?, ?, ?, ?)",
            [$winId, $u['user_id'], $kind, $mediaUrl, $note ?: null]
        );
        $id = (int)Database::insertId();

        Response::ok(['id' => $id, 'media_url' => $mediaUrl, 'status' => 'pending']);
    }

    /**
     * GET /api/me/proofs — list this user's submissions (most recent first).
     */
    public function myProofs(Request $req): void
    {
        $u = Auth::require($req);
        $rows = Database::all(
            "SELECT p.*, pr.name_zh, pr.name_en, pr.name_si, pr.name_bn
             FROM winner_proofs p
             JOIN winners w ON w.id = p.winner_id
             JOIN products pr ON pr.id = w.product_id
             WHERE p.user_id = ?
             ORDER BY p.id DESC",
            [$u['user_id']]
        );
        Response::ok($rows);
    }

    /* =====================================================
       Daily check-in
       ===================================================== */

    /**
     * Reward amount for a given streak day, using settings:
     *   - day % 30 == 0  → checkin_reward_day30
     *   - day % 7  == 0  → checkin_reward_day7
     *   - else           → checkin_reward_day1
     */
    private static function checkinReward(int $streakDay): float
    {
        $key = $streakDay > 0 && $streakDay % 30 === 0 ? 'checkin_reward_day30'
             : ($streakDay > 0 && $streakDay % 7 === 0 ? 'checkin_reward_day7'
             :  'checkin_reward_day1');
        return (float)(Database::val("SELECT `value` FROM settings WHERE `key` = ?", [$key]) ?? 0);
    }

    /**
     * GET /api/me/checkin/state — { today_done, today_date, current_streak, next_streak, next_reward, today_reward_if_claimed }
     */
    public function checkinState(Request $req): void
    {
        $u = Auth::require($req);
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $last = Database::one(
            "SELECT checkin_date, streak_day FROM checkins WHERE user_id = ? ORDER BY checkin_date DESC LIMIT 1",
            [$u['user_id']]
        );

        $todayDone = $last && $last['checkin_date'] === $today;
        $currentStreak = (int)($last['streak_day'] ?? 0);
        if (!$todayDone) {
            $continues = $last && $last['checkin_date'] === $yesterday;
            $nextStreak = $continues ? $currentStreak + 1 : 1;
        } else {
            $nextStreak = $currentStreak;  // already done today
        }
        $reward = self::checkinReward($nextStreak);

        Response::ok([
            'today_done'     => $todayDone,
            'today_date'     => $today,
            'current_streak' => $todayDone ? $currentStreak : ($last && $last['checkin_date'] === $yesterday ? $currentStreak : 0),
            'next_streak'    => $nextStreak,
            'next_reward'    => $reward,
        ]);
    }

    /**
     * POST /api/me/checkin — record today; credit reward to balance.
     */
    public function checkin(Request $req): void
    {
        $u = Auth::require($req);
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $result = Database::tx(function () use ($u, $today, $yesterday) {
            // Lock user row to serialize check-ins.
            $user = Database::one("SELECT id, balance FROM users WHERE id = ? FOR UPDATE", [$u['user_id']]);

            $already = Database::one(
                "SELECT id FROM checkins WHERE user_id = ? AND checkin_date = ?",
                [$user['id'], $today]
            );
            if ($already) return ['already' => true];

            $last = Database::one(
                "SELECT streak_day FROM checkins WHERE user_id = ? AND checkin_date = ?",
                [$user['id'], $yesterday]
            );
            $streak = $last ? (int)$last['streak_day'] + 1 : 1;
            $reward = self::checkinReward($streak);

            Database::q(
                "INSERT INTO checkins (user_id, checkin_date, streak_day, reward_amount)
                 VALUES (?, ?, ?, ?)",
                [$user['id'], $today, $streak, $reward]
            );

            if ($reward > 0) {
                $newBal = round((float)$user['balance'] + $reward, 2);
                Database::q("UPDATE users SET balance = ? WHERE id = ?", [$newBal, $user['id']]);
                Database::q(
                    "INSERT INTO wallet_txns (user_id, kind, amount, balance_after, reference, note)
                     VALUES (?, 'checkin', ?, ?, ?, ?)",
                    [$user['id'], $reward, $newBal, "checkin:$today", "Daily check-in day $streak"]
                );
            }

            return ['streak' => $streak, 'reward' => $reward];
        });

        Response::ok($result);
    }

    /* =====================================================
       Social shares
       ===================================================== */

    /**
     * POST /api/me/social-share — submit screenshot of a social-media post.
     *   multipart file=<screenshot>
     *   platform: wechat / weibo / douyin / tiktok / twitter / facebook / instagram / other
     *   post_url optional (link to the original post)
     *   winner_id optional (tied to a specific win)
     */
    public function submitShare(Request $req): void
    {
        $u = Auth::require($req);
        if (empty($_FILES['file']['tmp_name'])) Response::fail('Screenshot file required');

        $platform = strtolower(trim((string)$req->input('platform')));
        $allowedPlatforms = ['wechat','weibo','douyin','tiktok','twitter','facebook','instagram','other'];
        if (!in_array($platform, $allowedPlatforms, true)) {
            Response::fail('platform must be one of: ' . implode(', ', $allowedPlatforms));
        }

        $postUrl = trim((string)$req->input('post_url'));
        if ($postUrl !== '' && !preg_match('~^https?://~i', $postUrl)) {
            Response::fail('post_url must be a valid http(s) URL');
        }
        if (mb_strlen($postUrl) > 512) Response::fail('post_url too long');

        $winId = (int)$req->input('winner_id', 0);
        if ($winId > 0) {
            $owns = Database::val("SELECT 1 FROM winners WHERE id = ? AND user_id = ?", [$winId, $u['user_id']]);
            if (!$owns) Response::fail('Winner not found for this user');
        } else {
            $winId = null;
        }

        $f = $_FILES['file'];
        $mime = mime_content_type($f['tmp_name']) ?: '';
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) Response::fail('Unsupported image type: ' . $mime);
        if ($f['size'] > 5 * 1024 * 1024) Response::fail('File too large (max 5MB)');

        $ext = $allowed[$mime];
        $name = 'share_' . date('Ymd') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $dir = __DIR__ . '/../../public/uploads';
        @mkdir($dir, 0775, true);
        $dest = $dir . '/' . $name;
        if (!move_uploaded_file($f['tmp_name'], $dest)) Response::fail('Failed to save file', 500);
        @chmod($dest, 0644);
        $proofUrl = '/uploads/' . $name;

        Database::q(
            "INSERT INTO social_shares (user_id, winner_id, platform, proof_url, post_url)
             VALUES (?, ?, ?, ?, ?)",
            [$u['user_id'], $winId, $platform, $proofUrl, $postUrl ?: null]
        );

        Response::ok([
            'id' => (int)Database::insertId(),
            'proof_url' => $proofUrl,
            'status' => 'pending',
        ]);
    }

    /**
     * GET /api/me/social-shares — list this user's submissions.
     */
    public function mySocialShares(Request $req): void
    {
        $u = Auth::require($req);
        $rows = Database::all(
            "SELECT * FROM social_shares WHERE user_id = ? ORDER BY id DESC",
            [$u['user_id']]
        );
        Response::ok($rows);
    }
}
