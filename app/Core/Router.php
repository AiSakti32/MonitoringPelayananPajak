<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int, array{methods: string[], path: string, action: callable|array, middleware: array}> */
    private array $routes = [];

    public function get(string $path, callable|array $action, array $middleware = []): self
    {
        return $this->add(['GET'], $path, $action, $middleware);
    }

    public function post(string $path, callable|array $action, array $middleware = []): self
    {
        return $this->add(['POST'], $path, $action, $middleware);
    }

    public function match(array $methods, string $path, callable|array $action, array $middleware = []): self
    {
        return $this->add($methods, $path, $action, $middleware);
    }

    private function add(array $methods, string $path, callable|array $action, array $middleware): self
    {
        $this->routes[] = [
            'methods' => array_map('strtoupper', $methods),
            'path' => $path === '' ? '/' : $path,
            'action' => $action,
            'middleware' => $middleware,
        ];
        return $this;
    }

    public function dispatch(string $method, string $path): void
    {
        foreach ($this->routes as $route) {
            $params = $this->matchRoute($route['path'], $path);
            if ($params === null) {
                continue;
            }
            if (!in_array($method, $route['methods'], true)) {
                continue;
            }

            foreach ($route['middleware'] as $middleware) {
                $this->runMiddleware($middleware);
            }

            $this->invoke($route['action'], $params);
            return;
        }

        abort(404, 'Halaman yang Anda cari tidak ditemukan.');
    }

    private function matchRoute(string $routePath, string $requestPath): ?array
    {
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';

        if (!preg_match($pattern, $requestPath, $matches)) {
            return null;
        }

        $params = [];
        foreach ($matches as $key => $value) {
            if (!is_int($key)) {
                $params[$key] = $value;
            }
        }
        return $params;
    }

    private function runMiddleware(string|callable $middleware): void
    {
        if (is_callable($middleware)) {
            $middleware();
            return;
        }

        if (!class_exists($middleware)) {
            throw new \RuntimeException("Middleware not found: {$middleware}");
        }

        $instance = new $middleware();
        if (!method_exists($instance, 'handle')) {
            throw new \RuntimeException("Middleware {$middleware} must implement handle()");
        }
        $instance->handle();
    }

    private function invoke(callable|array $action, array $params): void
    {
        if (is_array($action)) {
            [$class, $method] = $action;
            $controller = new $class();
            $controller->{$method}(...array_values($params));
            return;
        }

        $action(...array_values($params));
    }
}
