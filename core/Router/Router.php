<?php

declare(strict_types=1);

namespace App\Router;

class Router
{
    private array $routes = [];
    private array $middleware = [];
    private string $prefix = '';

    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->middleware;

        $this->prefix .= $prefix;
        $this->middleware = array_merge($this->middleware, $middleware);

        $callback($this);

        $this->prefix = $previousPrefix;
        $this->middleware = $previousMiddleware;
    }

    public function get(string $path, array $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, array $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, array $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    public function patch(string $path, array $handler): void
    {
        $this->addRoute('PATCH', $path, $handler);
    }

    private function addRoute(string $method, string $path, array $handler): void
    {
        $fullPath = $this->prefix . $path;
        $this->routes[] = [
            'method' => $method,
            'path' => $fullPath,
            'handler' => $handler,
            'middleware' => $this->middleware,
            'pattern' => $this->compilePattern($fullPath),
        ];
    }

    private function compilePattern(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    public function dispatch(string $method, string $uri): mixed
    {
        $uri = parse_url($uri, PHP_URL_PATH) ?? '/';
        $uri = rtrim($uri, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                foreach ($route['middleware'] as $mw) {
                    $result = $this->runMiddleware($mw, $params);
                    if ($result !== true) {
                        return $result;
                    }
                }

                [$controllerClass, $action] = $route['handler'];
                $controller = new $controllerClass();

                return $controller->$action($params);
            }
        }

        http_response_code(404);
        return json(['error' => 'Not Found', 'message' => "路由未找到: {$method} {$uri}"]);
    }

    private function runMiddleware(string $mw, array $params): mixed
    {
        $parts = explode('@', $mw);
        $class = $parts[0];
        $method = $parts[1] ?? 'handle';

        if (class_exists($class)) {
            $instance = new $class();
            return $instance->$method($params);
        }

        return true;
    }
}
