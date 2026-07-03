<?php

use PHPUnit\Framework\TestCase;

/**
 * HTTP-level integration tests against a locally running install
 * (Apache + MySQL, e.g. XAMPP serving http://localhost/syndrasi).
 *
 * These are the highest-risk surfaces — the no-login token endpoints and the
 * auth flow — exercised through the real front controller, router, CSRF
 * layer, and database. The whole class SKIPS cleanly when no local server or
 * database is reachable (so CI, which has neither, stays green).
 */
final class LocalHttpTest extends TestCase
{
    private const BASE = 'http://localhost/syndrasi/public';
    private const TEST_EMAIL = 'httptest_admin@test.local';
    private const TEST_PASS  = 'HttpTest#12345';

    private static ?PDO $pdo = null;
    private static string $cookieJar = '';
    private static string $fieldToken = '';
    private static int $eventId = 0;
    private static int $teamId = 0;
    private static int $rrAcceptId = 0;
    private static int $rrDoubleId = 0;
    private static int $userId = 0;

    public static function setUpBeforeClass(): void
    {
        if (!function_exists('curl_init')) {
            self::markTestSkipped('curl extension not available');
        }
        // Server reachable?
        $ch = curl_init(self::BASE . '/login');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3, CURLOPT_NOBODY => true]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($code !== 200) {
            self::markTestSkipped('Local server not reachable at ' . self::BASE);
        }
        // DB reachable? (same defaults as config/database.php for local XAMPP)
        try {
            $cfg = require BASE_PATH . '/config/database.php';
            self::$pdo = new PDO(
                "mysql:host={$cfg['host']};dbname={$cfg['database']};charset=utf8mb4",
                $cfg['username'], $cfg['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (Throwable $e) {
            self::markTestSkipped('Local DB not reachable: ' . $e->getMessage());
        }
        self::$cookieJar = tempnam(sys_get_temp_dir(), 'httptest_cookies_');
        self::fixturesUp();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$pdo) {
            self::fixturesDown();
        }
        if (self::$cookieJar) {
            @unlink(self::$cookieJar);
        }
    }

    private static function fixturesUp(): void
    {
        $pdo = self::$pdo;
        $row = $pdo->query("SELECT e.id, e.municipality_id FROM events e WHERE e.status = 'active' LIMIT 1")->fetch();
        if (!$row) {
            self::markTestSkipped('No active event in local DB to test against');
        }
        self::$eventId = (int) $row['id'];
        $mid = (int) $row['municipality_id'];

        $team = $pdo->query("SELECT id FROM volunteer_teams WHERE municipality_id = $mid AND status = 'active' LIMIT 1")->fetch();
        if (!$team) {
            self::markTestSkipped('No active team in local DB to test against');
        }
        self::$teamId = (int) $team['id'];

        self::$fieldToken = bin2hex(random_bytes(32));
        $pdo->prepare(
            "INSERT INTO event_applications (municipality_id, event_id, team_id, offered_people, offered_vehicle,
                                             offered_medical_equipment, status, submitted_at, field_token)
             VALUES (?, ?, ?, 3, 0, 0, 'approved', NOW(), ?)"
        )->execute([$mid, self::$eventId, self::$teamId, self::$fieldToken]);

        $ins = $pdo->prepare(
            "INSERT INTO resource_requests (municipality_id, event_id, from_team_id, item_label) VALUES (?, ?, ?, ?)"
        );
        $ins->execute([$mid, self::$eventId, self::$teamId, 'HTTP test item A']);
        self::$rrAcceptId = (int) $pdo->lastInsertId();
        $ins->execute([$mid, self::$eventId, self::$teamId, 'HTTP test item B']);
        self::$rrDoubleId = (int) $pdo->lastInsertId();
        // Pre-answer B so responding to it must yield 409.
        $pdo->exec("UPDATE resource_requests SET status = 'declined', responded_at = NOW() WHERE id = " . self::$rrDoubleId);

        $hash = password_hash(self::TEST_PASS, PASSWORD_DEFAULT);
        $pdo->prepare(
            "INSERT INTO users (municipality_id, name, email, password_hash, role, status)
             VALUES (?, 'HTTP Test Admin', ?, ?, 'municipality_admin', 'active')"
        )->execute([$mid, self::TEST_EMAIL, $hash]);
        self::$userId = (int) $pdo->lastInsertId();
    }

    private static function fixturesDown(): void
    {
        $pdo = self::$pdo;
        $pdo->exec("DELETE FROM notifications WHERE type IN ('resource_accepted','resource_declined') AND message LIKE '%HTTP test item%'");
        $pdo->exec("DELETE FROM resource_requests WHERE item_label LIKE 'HTTP test item%'");
        $pdo->prepare('DELETE FROM event_applications WHERE field_token = ?')->execute([self::$fieldToken]);
        $pdo->prepare('DELETE FROM users WHERE email = ?')->execute([self::TEST_EMAIL]);
        $pdo->prepare('DELETE FROM audit_logs WHERE user_id = ?')->execute([self::$userId]);
    }

    /** @return array{0:int,1:string,2:string} [status, body, contentType] */
    private function http(string $method, string $path, array $opts = []): array
    {
        $ch = curl_init(self::BASE . $path);
        $headers = $opts['headers'] ?? [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_COOKIEJAR      => self::$cookieJar,
            CURLOPT_COOKIEFILE     => self::$cookieJar,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (isset($opts['json'])) {
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($opts['json'], JSON_UNESCAPED_UNICODE));
            } elseif (isset($opts['form'])) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($opts['form']));
            }
        }
        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $body = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $ctype = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        return [$code, $body, $ctype];
    }

    private function csrfFrom(string $html): string
    {
        // App pages expose window.csrfToken; standalone pages (login) carry a form field.
        if (preg_match("/window\\.csrfToken = '([a-f0-9]{64})'/", $html, $m)
            || preg_match('/name="_token" value="([a-f0-9]{64})"/', $html, $m)) {
            return $m[1];
        }
        $this->fail('CSRF token missing from page');
    }

    /* ── Deny-by-default surfaces ─────────────────────────────────────── */

    public function testAnonymousProtectedRouteRedirectsToLogin(): void
    {
        [$code] = $this->http('GET', '/dashboard');
        $this->assertSame(302, $code);
    }

    public function testCronWithoutTokenIs401(): void
    {
        [$code] = $this->http('GET', '/cron/cleanup');
        $this->assertSame(401, $code);
    }

    public function testUnknownFieldTokenIs404(): void
    {
        [$code] = $this->http('GET', '/f/' . str_repeat('0', 64));
        $this->assertSame(404, $code);
    }

    /* ── Field-link flow (no login) ───────────────────────────────────── */

    public function testFieldHubRendersAndAcceptsResourceRequest(): void
    {
        [$code, $html] = $this->http('GET', '/f/' . self::$fieldToken);
        $this->assertSame(200, $code);
        $this->assertStringContainsString('HTTP test item A', $html);
        $csrf = $this->csrfFrom($html);

        [$code, $body] = $this->http('POST',
            '/f/' . self::$fieldToken . '/resource-requests/' . self::$rrAcceptId . '/respond',
            ['json' => ['action' => 'accept', 'eta_minutes' => 15],
             'headers' => ['X-CSRF-Token: ' . $csrf, 'X-Requested-With: XMLHttpRequest']]);
        $this->assertSame(200, $code, $body);
        $this->assertTrue((bool) (json_decode($body, true)['success'] ?? false), $body);

        $row = self::$pdo->query('SELECT status, eta_minutes FROM resource_requests WHERE id = ' . self::$rrAcceptId)->fetch();
        $this->assertSame('accepted', $row['status']);
        $this->assertSame(15, (int) $row['eta_minutes']);
    }

    public function testRespondingToAnsweredRequestIs409(): void
    {
        [, $html] = $this->http('GET', '/f/' . self::$fieldToken);
        $csrf = $this->csrfFrom($html);
        [$code] = $this->http('POST',
            '/f/' . self::$fieldToken . '/resource-requests/' . self::$rrDoubleId . '/respond',
            ['json' => ['action' => 'accept'],
             'headers' => ['X-CSRF-Token: ' . $csrf, 'X-Requested-With: XMLHttpRequest']]);
        $this->assertSame(409, $code);
    }

    /* ── Auth flow + change-detected ops stream ───────────────────────── */

    public function testLoginAndOpsStreamShortCircuit(): void
    {
        // Log in through the real form flow.
        [, $html] = $this->http('GET', '/login');
        $csrf = $this->csrfFrom($html);
        [$code] = $this->http('POST', '/login', [
            'form' => ['email' => self::TEST_EMAIL, 'password' => self::TEST_PASS, '_token' => $csrf],
        ]);
        $this->assertSame(302, $code, 'login should redirect on success');

        // First stream poll: full snapshot.
        [$code, $body, $ctype] = $this->http('GET', '/operations/events/' . self::$eventId . '/stream');
        $this->assertSame(200, $code);
        $this->assertStringContainsString('text/event-stream', $ctype);
        $this->assertMatchesRegularExpression('/data: (\{.*\})/', $body);
        preg_match('/data: (\{.*\})/', $body, $m);
        $first = json_decode($m[1], true);
        $this->assertTrue((bool) ($first['ok'] ?? false));
        $this->assertArrayNotHasKey('unchanged', $first, 'first poll must carry the full snapshot');
        $this->assertArrayHasKey('teams', $first);

        // Second poll with no new activity: skinny unchanged payload.
        [, $body2] = $this->http('GET', '/operations/events/' . self::$eventId . '/stream');
        preg_match('/data: (\{.*\})/', $body2, $m2);
        $second = json_decode($m2[1], true);
        $this->assertTrue((bool) ($second['unchanged'] ?? false), 'second poll should short-circuit: ' . $body2);

        // New activity (a command room message) must force a full snapshot again.
        self::$pdo->exec('UPDATE events SET last_activity_at = NOW() + INTERVAL 2 SECOND WHERE id = ' . self::$eventId);
        [, $body3] = $this->http('GET', '/operations/events/' . self::$eventId . '/stream');
        preg_match('/data: (\{.*\})/', $body3, $m3);
        $third = json_decode($m3[1], true);
        $this->assertArrayNotHasKey('unchanged', $third, 'activity bump must invalidate the signature');
        $this->assertArrayHasKey('teams', $third);
    }
}
