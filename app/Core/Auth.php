<?php
namespace App\Core;

class Auth
{
    private static ?array $cachedUser = null;
    private static ?array $cachedAdmin = null;

    public static function issue(int $userId, string $kind = 'user'): string
    {
        $token = bin2hex(random_bytes(24));
        $exp = date('Y-m-d H:i:s', time() + Config::get('session.lifetime'));
        Database::q(
            "INSERT INTO sessions (token, user_id, kind, expires_at) VALUES (?,?,?,?)",
            [$token, $userId, $kind, $exp]
        );
        return $token;
    }

    public static function revoke(string $token): void
    {
        Database::q("DELETE FROM sessions WHERE token = ?", [$token]);
    }

    public static function userFromRequest(Request $req): ?array
    {
        if (self::$cachedUser !== null) return self::$cachedUser ?: null;
        $tok = $req->bearer();
        if (!$tok) return self::$cachedUser = null;
        $sess = Database::one(
            "SELECT s.*, u.* FROM sessions s
             JOIN users u ON u.id = s.user_id
             WHERE s.token = ? AND s.kind = 'user' AND s.expires_at > NOW() AND u.status = 1",
            [$tok]
        );
        if (!$sess) return self::$cachedUser = null;
        unset($sess['password_hash']);
        return self::$cachedUser = $sess;
    }

    public static function require(Request $req): array
    {
        $u = self::userFromRequest($req);
        if (!$u) Response::fail('Login required', 401);
        return $u;
    }

    public static function adminFromRequest(Request $req): ?array
    {
        if (self::$cachedAdmin !== null) return self::$cachedAdmin ?: null;
        $tok = $req->bearer();
        if (!$tok) return self::$cachedAdmin = null;
        $sess = Database::one(
            "SELECT s.*, a.username, a.role
             FROM sessions s JOIN admins a ON a.id = s.user_id
             WHERE s.token = ? AND s.kind = 'admin' AND s.expires_at > NOW()",
            [$tok]
        );
        return self::$cachedAdmin = $sess ?: null;
    }

    public static function requireAdmin(Request $req): array
    {
        $a = self::adminFromRequest($req);
        if (!$a) Response::fail('Admin login required', 401);
        return $a;
    }
}
