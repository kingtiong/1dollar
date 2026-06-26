<?php
namespace App\Core;

class Response
{
    public static function json(mixed $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function ok(mixed $data = null): void
    {
        self::json(['ok' => true, 'data' => $data]);
    }

    public static function fail(string $msg, int $code = 400, mixed $extra = null): void
    {
        self::json(['ok' => false, 'error' => $msg, 'detail' => $extra], $code);
    }

    public static function redirect(string $url, int $code = 302): void
    {
        http_response_code($code);
        header('Location: ' . $url);
        exit;
    }

    public static function html(string $html, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }
}
