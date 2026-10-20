<?php
declare(strict_types=1);

namespace Tests\Http;

use PHPUnit\Framework\TestCase;

/**
 * Tests HTTP contre une instance Apache en fonctionnement (données de démonstration chargées).
 * Activés avec TEST_BASE_URL, par exemple : TEST_BASE_URL=http://localhost:8095 vendor/bin/phpunit --testsuite Http
 */
final class SecurityHttpTest extends TestCase
{
    private string $base;
    private string $jar;

    protected function setUp(): void
    {
        $base = getenv('TEST_BASE_URL');
        if (!$base) {
            $this->markTestSkipped('TEST_BASE_URL non défini.');
        }
        $this->base = rtrim($base, '/');
        $this->jar = tempnam(sys_get_temp_dir(), 'jcl');
    }

    protected function tearDown(): void
    {
        if (isset($this->jar) && is_file($this->jar)) {
            unlink($this->jar);
        }
    }

    /** @return array{status:int, headers:array<string,string>, body:string} */
    private function req(string $method, string $path, array $data = [], array $headers = []): array
    {
        $ch = curl_init($this->base . $path);
        $respHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_HTTPHEADER => $headers, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADERFUNCTION => function ($c, $h) use (&$respHeaders) {
                if (str_contains($h, ':')) {
                    [$k, $v] = explode(':', $h, 2);
                    $respHeaders[strtolower(trim($k))] = trim($v);
                }
                return strlen($h);
            },
        ]);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        $body = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return ['status' => $status, 'headers' => $respHeaders, 'body' => $body];
    }

    private function csrf(string $path = '/connexion'): string
    {
        preg_match('/name="_csrf" value="([^"]+)"/', $this->req('GET', $path)['body'], $m);
        return $m[1] ?? '';
    }

    private function login(string $email, string $password): void
    {
        $r = $this->req('POST', '/connexion', ['_csrf' => $this->csrf(), 'email' => $email, 'password' => $password]);
        $this->assertSame(302, $r['status']);
        $this->assertStringNotContainsString('/connexion', $r['headers']['location'] ?? '');
    }

    public function testSecurityHeadersAndNoTechnicalLeaks(): void
    {
        $r = $this->req('GET', '/');
        $this->assertSame(200, $r['status']);
        $this->assertSame('nosniff', $r['headers']['x-content-type-options'] ?? null);
        $this->assertStringContainsString("script-src 'self'", $r['headers']['content-security-policy'] ?? '');
        $this->assertStringContainsString('HttpOnly', $r['headers']['set-cookie'] ?? '');
        $nf = $this->req('GET', '/page-inexistante');
        $this->assertSame(404, $nf['status']);
        $this->assertStringNotContainsString('Stack trace', $nf['body']);
    }

    public function testPrivateFilesAreNotServed(): void
    {
        foreach (['/.env', '/../.env', '/app/bootstrap.php', '/config/app.php', '/storage/logs/', '/database/migrations/001_initial_schema.sql'] as $p) {
            $r = $this->req('GET', $p);
            $this->assertNotSame(200, $r['status'], "$p ne doit pas être servi");
            $this->assertStringNotContainsString('DB_PASSWORD', $r['body']);
        }
    }

    public function testCsrfIsRequiredForWrites(): void
    {
        $r = $this->req('POST', '/connexion', ['email' => 'admin@demo-judo.test', 'password' => 'AdminDemo2026!']);
        $this->assertSame(302, $r['status']);
        $this->assertStringContainsString('/connexion', $r['headers']['location'] ?? '');
        $api = $this->req('POST', '/api/combats/1/action', [], ['Accept: application/json']);
        $this->assertContains($api['status'], [401, 419]);
    }

    public function testApiRequiresAuthenticationAndTvRequiresToken(): void
    {
        $this->assertSame(401, $this->req('GET', '/api/commissaire/tapis/1', [], ['Accept: application/json'])['status']);
        $this->assertSame(403, $this->req('GET', '/tv/tapis/1')['status']);
        $this->assertSame(403, $this->req('GET', '/api/tv/etat?tapis=1&jeton=faux', [], ['Accept: application/json'])['status']);
        $this->assertSame(302, $this->req('GET', '/admin')['status']);
    }

    public function testCoachCannotReachAdministrationOrScoring(): void
    {
        $this->login('coach@demo-judo.test', 'CoachDemo2026!');
        $this->assertSame(403, $this->req('GET', '/admin')['status']);
        $this->assertSame(403, $this->req('GET', '/commissaire')['status']);
        $this->assertSame(403, $this->req('GET', '/api/commissaire/tapis/1', [], ['Accept: application/json'])['status']);
        $this->assertSame(200, $this->req('GET', '/espace-club')['status']);
    }

    public function testPublicRankingApiIsLiveJson(): void
    {
        $home = $this->req('GET', '/competitions')['body'];
        preg_match('#/competitions/(tournoi-de-demonstration[^"]+)"#', $home, $m);
        $this->assertNotEmpty($m, 'Compétition de démonstration attendue');
        $r = $this->req('GET', '/api/public/competitions/' . $m[1] . '/classement');
        $this->assertSame(200, $r['status']);
        $d = json_decode($r['body'], true);
        $this->assertArrayHasKey('points', $d);
        $this->assertArrayHasKey('medals', $d);
        $this->assertArrayNotHasKey('email', $d['points'][0] ?? []);
    }
}
