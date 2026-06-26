<?php
namespace App\Core;

class Helpers
{
    public static function uuid(): string
    {
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }

    public static function orderNo(string $prefix = 'P'): string
    {
        return $prefix . date('YmdHis') . substr(bin2hex(random_bytes(3)), 0, 5);
    }

    public static function refCode(): string
    {
        return strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }

    /** Generate next lucky code for a period — global counter starting at 10000001 */
    public static function nextLuckyCode(int $periodId): string
    {
        $count = (int)Database::val("SELECT COUNT(*) FROM lucky_codes WHERE period_id = ?", [$periodId]);
        return (string)(10000001 + $count);
    }

    public static function setCookie(string $name, string $value, int $ttl): void
    {
        setcookie($name, $value, [
            'expires'  => time() + $ttl,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function clearCookie(string $name): void
    {
        setcookie($name, '', ['expires' => time() - 3600, 'path' => '/']);
    }
}
