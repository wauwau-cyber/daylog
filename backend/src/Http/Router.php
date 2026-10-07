<?php
declare(strict_types=1);

namespace App\Http;

final class Router
{
    /** @var list<array{string, string, array{class-string, string}}> */
    private array $routes = [];

    public function get(string $pattern, array $handler): void { $this->add('GET', $pattern, $handler); }
    public function post(string $pattern, array $handler): void { $this->add('POST', $pattern, $handler); }
    public function put(string $pattern, array $handler): void { $this->add('PUT', $pattern, $handler); }
    public function delete(string $pattern, array $handler): void { $this->add('DELETE', $pattern, $handler); }

    private function add(string $method, string $pattern, array $handler): void
    {
        $this->routes[] = [$method, '#^' . $pattern . '$#', $handler];
    }

    public function dispatch(Request $request): void
    {
        $pathMatched = false;
        foreach ($this->routes as [$method, $regex, [$class, $action]]) {
            if (!preg_match($regex, $request->path, $matches)) {
                continue;
            }
            $pathMatched = true;
            if ($method !== $request->method) {
                continue;
            }
            array_shift($matches);
            (new $class())->$action($request, ...$matches);
            return;
        }
        throw new HttpException($pathMatched ? 'Method not allowed' : 'Not found', $pathMatched ? 405 : 404);
    }
}
