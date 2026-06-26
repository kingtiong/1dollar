<?php
namespace App\Core;

class Request
{
    public string $method;
    public string $path;
    public array  $query;
    public array  $body;
    public array  $headers;

    public function __construct()
    {
        $this->method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->path    = '/' . trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
        $this->query   = $_GET;
        $this->headers = self::collectHeaders();

        $ct = $this->headers['content-type'] ?? '';
        if (str_contains($ct, 'application/json')) {
            $raw = file_get_contents('php://input') ?: '';
            $this->body = json_decode($raw, true) ?: [];
        } else {
            $this->body = $_POST;
        }
    }

    private static function collectHeaders(): array
    {
        $h = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($k, 5)));
                $h[$name] = $v;
            }
        }
        if (!empty($_SERVER['CONTENT_TYPE'])) $h['content-type'] = $_SERVER['CONTENT_TYPE'];
        return $h;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function bearer(): ?string
    {
        $h = $this->headers['authorization'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $h, $m)) return $m[1];
        return $_COOKIE[Config::get('session.cookie')] ?? null;
    }

    public function ip(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
