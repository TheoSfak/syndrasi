<?php

use PHPUnit\Framework\TestCase;

/**
 * Roles are declared twice by design (defense in depth): on the route in
 * routes/web.php and via requireRole() inside the controller action. Nothing
 * in the runtime checks that the two agree — if they drift, the effective
 * policy is silently the stricter of the two. This test turns drift into a
 * red build.
 *
 * Rules enforced:
 *  - A route declaring ['roles' => [...]] whose action opens with a literal
 *    requireRole([...]) (or a resolvable class-constant one) must declare the
 *    SAME role set.
 *  - A route declaring ['public' => true] must NOT have an action that opens
 *    with requireRole()/requireLogin().
 * Actions with no literal guard (e.g. requireLogin() only, or token-based
 * checks) are skipped — the router-level gate is authoritative there.
 */
final class RouteRoleConsistencyTest extends TestCase
{
    private const CONTROLLER_DIR = BASE_PATH . '/app/Controllers/';
    private const ROUTES_FILE    = BASE_PATH . '/routes/web.php';

    public function testRouteRolesMatchControllerGuards(): void
    {
        $mismatches = [];
        foreach ($this->routes() as [$path, $class, $action, $opts]) {
            $guard = $this->controllerGuardRoles($class, $action);
            if ($guard === null) {
                continue; // no literal requireRole at the top of the action
            }
            if (!empty($opts['public'])) {
                $mismatches[] = "$path is ['public' => true] but {$class}::{$action}() calls requireRole()";
                continue;
            }
            $routeRoles = $opts['roles'] ?? [];
            sort($routeRoles);
            sort($guard);
            if ($routeRoles !== $guard) {
                $mismatches[] = sprintf(
                    '%s route roles [%s] != %s::%s() requireRole [%s]',
                    $path, implode(',', $routeRoles), $class, $action, implode(',', $guard)
                );
            }
        }
        $this->assertSame([], $mismatches, "Route/controller role drift:\n" . implode("\n", $mismatches));
    }

    /** @return array<int, array{0:string,1:string,2:string,3:array}> */
    private function routes(): array
    {
        $src = (string) file_get_contents(self::ROUTES_FILE);
        $out = [];
        $re = "/\\\$router->(?:get|post)\\(\\s*'([^']+)'\\s*,\\s*'([A-Za-z]+)@([A-Za-z]+)'\\s*(?:,\\s*(\\[[^;]*\\]))?\\s*\\);/";
        preg_match_all($re, $src, $m, PREG_SET_ORDER);
        $this->assertGreaterThan(200, count($m), 'Route parser regressed — too few routes matched');
        foreach ($m as $r) {
            $opts = [];
            $optSrc = $r[4] ?? '';
            if (str_contains($optSrc, "'public'")) {
                $opts['public'] = true;
            }
            if (preg_match("/'roles'\\s*=>\\s*\\[([^\\]]*)\\]/", $optSrc, $rm)) {
                $opts['roles'] = $this->parseRoleTokens($rm[1]);
            }
            $out[] = [$r[1], $r[2], $r[3], $opts];
        }
        return $out;
    }

    /** Literal roles from the first requireRole() in the action body, or null. */
    private function controllerGuardRoles(string $class, string $action): ?array
    {
        $file = self::CONTROLLER_DIR . $class . '.php';
        if (!is_file($file)) {
            $this->fail("Route references missing controller: $class");
        }
        $src = (string) file_get_contents($file);

        // Extract the action body (up to the next function declaration or EOF).
        if (!preg_match(
            '/function\s+' . preg_quote($action, '/') . '\s*\([^)]*\)\s*(?::\s*[\w?|]+\s*)?\{(.*?)(?:\n\s*(?:public|protected|private)\s+(?:static\s+)?function\s|\z)/s',
            $src, $m
        )) {
            $this->fail("Route references missing action: {$class}::{$action}()");
        }
        $head = substr($m[1], 0, 600); // guards sit at the top of the action

        if (preg_match('/requireRole\(\[([^\]]*)\]\)/', $head, $g)) {
            return $this->parseRoleTokens($g[1]);
        }
        // Resolve requireRole(self::CONSTANT) via reflection (handles any const shape).
        if (preg_match('/requireRole\(self::([A-Z_]+)\)/', $head, $c)) {
            $val = (new ReflectionClassConstant($class, $c[1]))->getValue();
            return is_array($val) ? array_values($val) : null;
        }
        return null;
    }

    /** Roles from a mixed token list: 'literal' strings and Role::CONST references. */
    private function parseRoleTokens(string $src): array
    {
        $roles = [];
        if (preg_match_all("/'([a-z_]+)'/", $src, $m)) {
            $roles = $m[1];
        }
        if (preg_match_all('/Role::([A-Z_]+)/', $src, $m)) {
            foreach ($m[1] as $name) {
                $roles[] = constant('Role::' . $name);
            }
        }
        return $roles;
    }
}
