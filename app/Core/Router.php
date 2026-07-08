<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function add(string $action, array $handler, string $method = 'GET'): void
    {
        $method = strtoupper($method);
        $this->routes[$method][$action] = $handler;
    }

    public function dispatch(string $action): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (!isset($this->routes[$method][$action])) {
            $matched = $this->matchWildcard($method, $action);

            if ($matched === null) {
                Response::json([
                    'status' => 'error',
                    'message' => 'Unknown action'
                ]);
            }

            [$controller, $controllerMethod] = $matched;
        } else {
            [$controller, $controllerMethod] = $this->routes[$method][$action];
        }

        (new $controller())->$controllerMethod();
    }

    private function matchWildcard(string $method, string $action): ?array
    {
        foreach ($this->routes[$method] ?? [] as $route => $handler) {
            if (!str_ends_with($route, '/*')) {
                continue;
            }

            $prefix = substr($route, 0, -1);

            if (str_starts_with($action, $prefix)) {
                $_GET['_route_wildcard'] = substr($action, strlen($prefix));
                return $handler;
            }
        }

        return null;
    }
}
