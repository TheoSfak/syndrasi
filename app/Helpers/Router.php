<?php
/**
 * SynDrasi - Minimal router with {param} placeholders (numeric IDs).
 *
 * Every route is deny-by-default: dispatch() requires a logged-in session
 * before invoking the controller, unless the route is registered with
 * ['public' => true]. Pass ['roles' => [...]] to further restrict to
 * specific roles (checked via requireRole()). A route with neither option
 * still requires login, but allows any authenticated role through.
 */
class Router
{
    private $routes = [];

    public function get($pattern, $handler, array $options = [])
    {
        $this->routes[] = ['GET', $pattern, $handler, $options];
    }

    public function post($pattern, $handler, array $options = [])
    {
        $this->routes[] = ['POST', $pattern, $handler, $options];
    }

    public function dispatch($method, $uri)
    {
        // HEAD is GET without a body (the SAPI discards the output) — needed
        // by health checks and monitoring probes.
        if ($method === 'HEAD') {
            $method = 'GET';
        }
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
            list($m, $pattern, $handler, $options) = $route;
            if ($m !== $method) {
                continue;
            }
            // {token} matches an alphanumeric string (used by public/share links);
            // every other {param} matches a numeric id.
            $regex = '#^' . preg_replace_callback(
                '/\{([a-zA-Z_]+)\}/',
                function ($m) {
                    return $m[1] === 'token' ? '([A-Za-z0-9]+)' : '(\d+)';
                },
                $pattern
            ) . '$#';
            if (preg_match($regex, $path, $matches)) {
                array_shift($matches);
                $this->enforceAccess($options);
                list($class, $action) = explode('@', $handler);
                if (!class_exists($class)) {
                    abort(500, 'Άγνωστος ελεγκτής δρομολόγησης.');
                }
                $controller = new $class();
                return call_user_func_array([$controller, $action], $matches);
            }
        }

        abort(404, 'Η σελίδα δεν βρέθηκε.');
    }

    /** Deny-by-default access gate, run before the controller is instantiated. */
    private function enforceAccess(array $options)
    {
        if (!empty($options['public'])) {
            return;
        }
        if (!empty($options['roles'])) {
            requireRole($options['roles']);
            return;
        }
        requireLogin();
    }
}
