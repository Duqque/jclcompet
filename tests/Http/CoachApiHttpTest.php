<?php
declare(strict_types=1);

namespace Tests\Http;

use PHPUnit\Framework\TestCase;

/**
 * API coach de l'application (jeton Bearer) contre une instance en fonctionnement, données de démonstration chargées.
 * TEST_BASE_URL=http://localhost:8095 vendor/bin/phpunit --testsuite Http
 */
final class CoachApiHttpTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $base = getenv('TEST_BASE_URL');
        if (!$base) {
            $this->markTestSkipped('TEST_BASE_URL non défini.');
        }
        $this->base = rtrim($base, '/');
    }

    /** @return array{0:int,1:array} */
    private function api(string $method, string $path, ?array $body = null, ?string $token = null, ?string $cookieJar = null): array
    {
        $ch = curl_init($this->base . '/api/v1' . $path);
        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers]);
        if ($cookieJar) {
            curl_setopt_array($ch, [CURLOPT_COOKIEJAR => $cookieJar, CURLOPT_COOKIEFILE => $cookieJar]);
        }
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $raw = (string) curl_exec($ch);
        return [(int) curl_getinfo($ch, CURLINFO_HTTP_CODE), json_decode($raw, true) ?? []];
    }

    private function login(string $email, string $password): string
    {
        [$code, $r] = $this->api('POST', '/compte/connexion', ['email' => $email, 'password' => $password, 'device' => 'phpunit']);
        $this->assertSame(200, $code, json_encode($r));
        return $r['token'];
    }

    public function testTokenIsRequiredAndCookiesAreNotEnough(): void
    {
        $this->assertSame(401, $this->api('GET', '/club')[0]);
        $this->assertSame(401, $this->api('GET', '/club', null, str_repeat('0', 64))[0]);
        // Session du site (cookie) : ne donne pas accès à l'API de l'application.
        $jar = tempnam(sys_get_temp_dir(), 'jcl');
        $ch = curl_init($this->base . '/connexion');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
        preg_match('/name="_csrf" value="([^"]+)"/', (string) curl_exec($ch), $m);
        $ch = curl_init($this->base . '/connexion');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['_csrf' => $m[1] ?? '', 'email' => 'coach@demo-judo.test', 'password' => 'CoachDemo2026!'])]);
        curl_exec($ch);
        $this->assertSame(401, $this->api('GET', '/club', null, null, $jar)[0]);
        unlink($jar);
    }

    public function testCoachReadsOwnClubAndCannotTouchAnotherClub(): void
    {
        $t1 = $this->login('coach@demo-judo.test', 'CoachDemo2026!');
        $t2 = $this->login('coach2@demo-judo.test', 'CoachDemo2026!');
        [$code, $home] = $this->api('GET', '/club', null, $t1);
        $this->assertSame(200, $code);
        $this->assertNotNull($home['account']['club']);
        $open = array_values(array_filter($home['competitions'], fn ($c) => $c['can_edit']));
        $this->assertNotEmpty($open, 'une compétition aux inscriptions ouvertes dans la démo');
        $cid = $open[0]['id'];

        [, $other] = $this->api('GET', "/club/competitions/$cid", null, $t2);
        $foreign = $other['fighters'][0]['id'] ?? null;
        $this->assertNotNull($foreign);
        $this->assertSame(403, $this->api('POST', "/club/competitions/$cid/inscriptions/$foreign", ['grade' => 'Noire'], $t1)[0]);
        $this->assertSame(403, $this->api('POST', "/club/competitions/$cid/inscriptions/$foreign/retirer", [], $t1)[0]);

        [, $mine] = $this->api('GET', "/club/competitions/$cid", null, $t1);
        $ids = array_column($mine['fighters'], 'id');
        $this->assertNotContains($foreign, $ids, 'les judokas des autres clubs ne sont jamais renvoyés');

        $this->api('POST', '/compte/deconnexion', [], $t1);
        $this->api('POST', '/compte/deconnexion', [], $t2);
        $this->assertSame(401, $this->api('GET', '/club', null, $t1)[0]);
    }

    public function testNonCoachRolesAreRefusedInTheApp(): void
    {
        [$code, $r] = $this->api('POST', '/compte/connexion', ['email' => 'commissaire1@demo-judo.test', 'password' => 'CommissaireDemo2026!']);
        $this->assertSame(403, $code);
        $this->assertStringContainsString('coachs', $r['error']);
    }
}
