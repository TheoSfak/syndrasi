<?php
/**
 * SynDrasi - Minimal router with {param} placeholders (numeric IDs).
 */
class Router
{
    private $routes = [];

    public function get($pattern, $handler)
    {
        $this->routes[] = ['GET', $pattern, $handler];
    }

    public function post($pattern, $handler)
    {
        $this->routes[] = ['POST', $pattern, $handler];
    }

    public function dispatch($method, $uri)
    {
        $path = parse_url($uri, PHP_URL_PATH);
        $base = base_uri();
        if ($base !== '' && strpos($path, $base) === 0) {
            $path = substr($path, strlen($base));
        } else {
            // Accessed through the root-folder rewrite (request URI has no
            // /public segment): strip the parent prefix instead.
            $parent = preg_replace('#/public$#', '', $base);
            if ($parent !== $base && $parent !== '' && strpos($path, $parent) === 0) {
                $path = substr($path, strlen($parent));
            }
        }
        $path = '/' . trim($path, '/');
        if ($path === '/index.php') {
            $path = '/';
        }

        foreach ($this->routes as $route) {
            list($m, $pattern, $handler) = $route;
            if ($m !== $method) {
                continue;
            }
            $regex = '#^' . preg_replace('/\{[a-zA-Z_]+\}/', '(\d+)', $pattern) . '$#';
            if (preg_match($regex, $path, $matches)) {
                array_shift($matches);
                list($class, $action) = explode('@', $handler);
                $controller = new $class();
                return call_user_func_array([$controller, $action], $matches);
            }
        }

        abort(404, 'Η σελίδα δεν βρέθηκε.');
    }
}
