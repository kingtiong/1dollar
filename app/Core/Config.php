<?php
namespace App\Core;

class Config
{
    private static array $data = [];

    public static function load(): void
    {
        $base = require __DIR__ . '/../../config/config.php';
        $local = __DIR__ . '/../../config/config.local.php';
        if (file_exists($local)) {
            $base = array_replace_recursive($base, require $local);
        }
        self::$data = $base;
        date_default_timezone_set($base['app']['timezone']);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $v = self::$data;
        foreach ($parts as $p) {
            if (!is_array($v) || !array_key_exists($p, $v)) return $default;
            $v = $v[$p];
        }
        return $v;
    }
}
