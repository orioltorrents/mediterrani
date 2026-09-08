<?php

declare(strict_types=1);

class Router
{
    /** @var array<int, array{methods: array<int, string>, pattern: string, regex: string, handler: callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler, array $constraints = []): void
    {
        $this->add(['GET'], $pattern, $handler, $constraints);
    }

    public function post(string $pattern, callable $handler, array $constraints = []): void
    {
        $this->add(['POST'], $pattern, $handler, $constraints);
    }

    public function any(string $pattern, callable $handler, array $constraints = []): void
    {
        $this->add(['ANY'], $pattern, $handler, $constraints);
    }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);
        $method = $method === 'HEAD' ? 'GET' : $method;
        $methodNotAllowed = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            if (!in_array('ANY', $route['methods'], true) && !in_array($method, $route['methods'], true)) {
                $methodNotAllowed = true;
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            $response = ($route['handler'])($params);
            if ($response !== null) {
                echo $response;
            }

            return;
        }

        if ($methodNotAllowed) {
            http_response_code(405);
            echo 'Metode no permes';
            return;
        }

        http_response_code(404);
        echo 'Pagina no trobada';
    }

    private function add(array $methods, string $pattern, callable $handler, array $constraints = []): void
    {
        $this->routes[] = [
            'methods' => array_map('strtoupper', $methods),
            'pattern' => $pattern,
            'regex' => $this->compile($pattern, $constraints),
            'handler' => $handler,
        ];
    }

    private function compile(string $pattern, array $constraints): string
    {
        $tokens = preg_split('/(\{[a-zA-Z_][a-zA-Z0-9_]*\})/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if ($tokens === false) {
            throw new RuntimeException('No s\'ha pogut compilar la ruta: ' . $pattern);
        }

        $regex = '';
        foreach ($tokens as $token) {
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $token, $matches) === 1) {
                $name = $matches[1];
                $constraint = isset($constraints[$name]) ? (string) $constraints[$name] : '[^/]+';
                $regex .= '(?P<' . $name . '>' . $constraint . ')';
                continue;
            }

            $regex .= preg_quote($token, '#');
        }

        return '#^' . $regex . '$#';
    }
}
