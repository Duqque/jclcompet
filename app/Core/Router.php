<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int, array{method:string, pattern:string, regex:string, handler:array|callable, middleware:array}> */
    private array $routes = [];
    private array $groupMiddleware = [];
    private string $groupPrefix = '';

    public function get(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function group(string $prefix, array $middleware, callable $fn): void
    {
        $prevPrefix = $this->groupPrefix;
        $prevMw = $this->groupMiddleware;
        $this->groupPrefix = $prevPrefix . $prefix;
        $this->groupMiddleware = array_merge($prevMw, $middleware);
        $fn($this);
        $this->groupPrefix = $prevPrefix;
        $this->groupMiddleware = $prevMw;
    }

    public function add(string $method, string $path, array|callable $handler, array $middleware = []): void
    {
        $pattern = '/' . trim($this->groupPrefix . $path, '/');
        $regex = preg_replace_callback(
            '#\{(\w+)(?::([^}]+))?\}#',
            fn ($m) => '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')',
            $pattern
        );
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'regex' => '#^' . $regex . '$#u',
            'handler' => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];
    }

    /** @return array{route: array, params: array}|null */
    public function match(string $method, string $path): ?array
    {
        $allowed = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            if ($route['method'] !== $method && !($method === 'HEAD' && $route['method'] === 'GET')) {
                $allowed = true;
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            return ['route' => $route, 'params' => $params];
        }
        if ($allowed) {
            throw new HttpException(405);
        }
        return null;
    }

    public function routes(): array
    {
        return $this->routes;
    }
}
