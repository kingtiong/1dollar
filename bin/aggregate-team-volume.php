<?php
/**
 * JackOne · 一夺 — Monthly team-volume + rank aggregation.
 *
 * Run nightly via cron. For each user, recompute:
 *   - effective_direct: direct referrals who made >= 1 participation this month
 *   - team_volume:      sum of participation cost across the whole downline (excl. self)
 *
 * Then assign the highest rank whose (min_direct, min_team_volume) thresholds are met
 * and UPSERT into user_ranks for the current YYYY-MM.
 *
 * Usage:
 *   php bin/aggregate-team-volume.php [YYYY-MM]
 *
 * If no month is given, current month is used.
 * Cron:
 *   5 1 * * *  /usr/bin/php /var/www/dollar/bin/aggregate-team-volume.php >> /var/www/dollar/storage/logs/aggregate.log 2>&1
 */
declare(strict_types=1);

require __DIR__ . '/../app/Core/Config.php';
require __DIR__ . '/../app/Core/Database.php';

use App\Core\Config;
use App\Core\Database;

Config::load();

$ym = $argv[1] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
    fwrite(STDERR, "Invalid YYYY-MM: $ym\n");
    exit(2);
}

$monthStart = $ym . '-01 00:00:00';
$nextMonth  = date('Y-m-01 00:00:00', strtotime("$monthStart +1 month"));

$started = microtime(true);
echo "[" . date('c') . "] aggregating $ym ($monthStart .. $nextMonth)\n";

// 1) Effective direct count: direct refs who participated this month.
$direct = Database::all(
    "SELECT u.id AS user_id, COUNT(DISTINCT p.user_id) AS cnt
     FROM users u
     LEFT JOIN users c ON c.referrer_id = u.id
     LEFT JOIN participations p
            ON p.user_id = c.id
           AND p.created_at >= ?
           AND p.created_at <  ?
     GROUP BY u.id",
    [$monthStart, $nextMonth]
);
$directMap = [];
foreach ($direct as $r) $directMap[(int)$r['user_id']] = (int)$r['cnt'];
echo "  direct counts: " . count($directMap) . " users\n";

// 2) Team volume via recursive CTE — participations of the whole downline (excl. root).
$team = Database::all(
    "WITH RECURSIVE downline AS (
        SELECT id AS root, id AS descendant
        FROM users
        UNION ALL
        SELECT d.root, u.id
        FROM downline d
        JOIN users u ON u.referrer_id = d.descendant
    )
    SELECT d.root AS user_id, COALESCE(SUM(p.cost), 0) AS vol
    FROM downline d
    LEFT JOIN participations p
           ON p.user_id = d.descendant
          AND d.descendant <> d.root
          AND p.created_at >= ?
          AND p.created_at <  ?
    GROUP BY d.root",
    [$monthStart, $nextMonth]
);
$teamMap = [];
foreach ($team as $r) $teamMap[(int)$r['user_id']] = (float)$r['vol'];
echo "  team volumes: " . count($teamMap) . " users\n";

// 3) Load rank thresholds (descending by sort_order to pick highest qualifying).
$ranks = Database::all(
    "SELECT id, min_direct, min_team_volume FROM ranks ORDER BY sort_order DESC"
);
if (!$ranks) {
    fwrite(STDERR, "No ranks defined. Apply database/migrations/2026_05_22_commission_v2.sql first.\n");
    exit(3);
}

// 4) UPSERT each user's snapshot.
$upserted = 0;
foreach ($directMap as $userId => $dCnt) {
    $vol = $teamMap[$userId] ?? 0.0;

    $rankId = 0;
    foreach ($ranks as $rk) {
        if ($dCnt >= (int)$rk['min_direct'] && $vol >= (float)$rk['min_team_volume']) {
            $rankId = (int)$rk['id'];
            break;
        }
    }

    Database::q(
        "INSERT INTO user_ranks (user_id, year_month, rank_id, direct_count, team_volume)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            rank_id = VALUES(rank_id),
            direct_count = VALUES(direct_count),
            team_volume = VALUES(team_volume)",
        [$userId, $ym, $rankId, $dCnt, $vol]
    );
    $upserted++;
}

$elapsed = round(microtime(true) - $started, 2);
echo "[" . date('c') . "] done. upserted=$upserted elapsed={$elapsed}s\n";
