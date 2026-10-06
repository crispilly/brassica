<?php
declare(strict_types=1);

namespace Brassica\Core;

final class Router
{
    /** @var array<int,array{methods:array<int,string>,path:string,regex:string,handler:callable}> */
    private array $routes = [];

    public function add(string|array $methods, string $path, callable $handler): void
    {
        $methods = is_array($methods) ? $methods : [$methods];
        $methods = array_map('strtoupper', $methods);
        $path = $this->normalize($path);
        $regex = preg_replace_callback('/\{([A-Za-z_][A-Za-z0-9_]*)\}/',
            static fn(array $m): string => '(?P<' . $m[1] . '>[^/]+)',
            preg_quote($path, '#')
        );
        // preg_quote also quotes braces, undo placeholder quoting before replacement if needed.
        $quoted = preg_quote($path, '#');
        $regex = preg_replace_callback('/\\\{([A-Za-z_][A-Za-z0-9_]*)\\\}/',
            static fn(array $m): string => '(?P<' . $m[1] . '>[^/]+)',
            $quoted
        );
        $this->routes[] = [
            'methods' => $methods,
            'path' => $path,
            'regex' => '#^' . $regex . '$#D',
            'handler' => $handler,
        ];
    }

    public function any(string $path, callable $handler): void
    {
        $this->add(['GET','POST','PUT','PATCH','DELETE','OPTIONS','HEAD'], $path, $handler);
    }

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $path = $this->normalize((string)(parse_url($uri, PHP_URL_PATH) ?? '/'));

        foreach ($this->routes as $route) {
            $accepted = in_array($method, $route['methods'], true)
                || ($method === 'HEAD' && in_array('GET', $route['methods'], true));
            if (!$accepted || !preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = rawurldecode((string)$value);
                }
            }
            ($route['handler'])($params);
            return;
        }

        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo '404 - Route nicht gefunden';
    }

    private function normalize(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
