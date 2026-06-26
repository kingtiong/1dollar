<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Helpers;
use App\Core\Request;
use App\Core\Response;

class AuthController
{
    public function register(Request $req): void
    {
        $u  = trim((string)$req->input('username'));
        $p  = (string)$req->input('password');
        $em = trim((string)$req->input('email'));
        $rc = trim((string)$req->input('referral_code'));

        if (!preg_match('/^[a-zA-Z0-9_]{3,32}$/', $u))
            Response::fail('Username must be 3-32 chars (letters/digits/underscore)');
        if (strlen($p) < 6) Response::fail('Password must be at least 6 chars');

        if (Database::one("SELECT id FROM users WHERE username = ?", [$u]))
            Response::fail('Username taken');

        $referrer = null;
        if ($rc !== '') {
            $referrer = Database::one("SELECT id FROM users WHERE referral_code = ?", [$rc]);
            if (!$referrer) Response::fail('Invalid referral code');
        }

        $hash = password_hash($p, PASSWORD_BCRYPT);
        $refCode = Helpers::refCode();
        $bonus = (float)(Database::val("SELECT `value` FROM settings WHERE `key`='signup_bonus'") ?: 0);

        Database::q(
            "INSERT INTO users (username, email, password_hash, display_name,
                referral_code, referrer_id, balance) VALUES (?,?,?,?,?,?,?)",
            [$u, $em ?: null, $hash, $u, $refCode, $referrer['id'] ?? null, $bonus]
        );
        $uid = (int)Database::insertId();

        if ($bonus > 0) {
            Database::q(
                "INSERT INTO wallet_txns (user_id, kind, amount, balance_after, note)
                 VALUES (?, 'adjust', ?, ?, 'Signup bonus')",
                [$uid, $bonus, $bonus]
            );
        }

        $token = Auth::issue($uid);
        Helpers::setCookie(Config::get('session.cookie'), $token, Config::get('session.lifetime'));
        Response::ok(['token' => $token, 'user_id' => $uid]);
    }

    public function login(Request $req): void
    {
        $u = trim((string)$req->input('username'));
        $p = (string)$req->input('password');
        $row = Database::one("SELECT * FROM users WHERE username = ? OR email = ?", [$u, $u]);
        if (!$row || !password_verify($p, $row['password_hash']))
            Response::fail('Invalid credentials', 401);
        if ((int)$row['status'] !== 1) Response::fail('Account suspended', 403);

        $token = Auth::issue((int)$row['id']);
        Helpers::setCookie(Config::get('session.cookie'), $token, Config::get('session.lifetime'));
        Response::ok(['token' => $token, 'user_id' => (int)$row['id']]);
    }

    public function logout(Request $req): void
    {
        $tok = $req->bearer();
        if ($tok) Auth::revoke($tok);
        Helpers::clearCookie(Config::get('session.cookie'));
        Response::ok();
    }

    public function me(Request $req): void
    {
        $u = Auth::require($req);
        $u = Database::one(
            "SELECT id, username, display_name, email, phone, avatar, balance, points, free_draws,
                referral_code, referrer_id, locale, created_at
             FROM users WHERE id = ?",
            [$u['user_id']]
        );
        Response::ok($u);
    }

    public function updateProfile(Request $req): void
    {
        $u = Auth::require($req);
        $name = trim((string)$req->input('display_name'));
        $loc  = (string)$req->input('locale');
        $av   = (string)$req->input('avatar');
        if ($loc && !in_array($loc, ['zh','en'])) $loc = 'zh';

        Database::q(
            "UPDATE users SET display_name = COALESCE(NULLIF(?, ''), display_name),
                locale = COALESCE(NULLIF(?, ''), locale),
                avatar = COALESCE(NULLIF(?, ''), avatar) WHERE id = ?",
            [$name, $loc, $av, $u['user_id']]
        );
        Response::ok();
    }
}
