<?php
namespace App\Core;

class Router
{
    /** @var array{0:string,1:string,2:callable,3:array}[] */
    private array $routes = [];

    public function add(string $method, string $path, callable|array $handler, array $middleware = []): void
    {
        $this->routes[] = [strtoupper($method), $path, $handler, $middleware];
    }

    public function get(string $p, callable|array $h, array $mw = []): void    { $this->add('GET',    $p, $h, $mw); }
    public function post(string $p, callable|array $h, array $mw = []): void   { $this->add('POST',   $p, $h, $mw); }
    public function put(string $p, callable|array $h, array $mw = []): void    { $this->add('PUT',    $p, $h, $mw); }
    public function delete(string $p, callable|array $h, array $mw = []): void { $this->add('DELETE', $p, $h, $mw); }

    public function dispatch(Request $req): void
    {
        foreach ($this->routes as [$m, $p, $h, $mw]) {
            if ($m !== $req->method) continue;
            $params = self::matchPath($p, $req->path);
            if ($params === null) continue;

            foreach ($mw as $fn) {
                $r = $fn($req);
                if ($r === false) return;
            }
            if (is_array($h)) {
                [$cls, $method] = $h;
                $controller = new $cls();
                $controller->$method($req, $params);
            } else {
                $h($req, $params);
            }
            return;
        }
        Response::fail('Not Found', 404);
    }

    private static function matchPath(string $pattern, string $path): ?array
    {
        $regex = preg_replace('#:([a-zA-Z_]+)#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';
        if (!preg_match($regex, $path, $m)) return null;
        $params = [];
        foreach ($m as $k => $v) if (!is_int($k)) $params[$k] = $v;
        return $params;
    }
}
