<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Services\AccountRequestService;
use App\Services\ApiTokenService;
use App\Services\RegistrationService;
use Tests\TestCase;

/** Création de compte coach (site et application), validation par l'organisation, jetons de l'application, direct. */
final class CoachAccountTest extends TestCase
{
    private function request(): Request
    {
        return new Request('POST', '/api/v1/compte/connexion', [], [], [], ['REMOTE_ADDR' => '10.9.' . random_int(0, 250) . '.' . random_int(1, 250)]);
    }

    private function ask(array $over = []): int
    {
        return AccountRequestService::create(array_merge([
            'first_name' => 'camille', 'last_name' => 'test', 'email' => 'camille.' . random_int(1000, 999999) . '@test.local', 'phone' => '06 12 34 56 78',
            'club_id' => $this->club(), 'password' => 'Tatami-Ippon-42', 'password_confirmation' => 'Tatami-Ippon-42',
        ], $over), 'app', '10.1.' . random_int(0, 250) . '.' . random_int(1, 250));
    }

    public function testRequestCreatesInactiveCoachAndNotifiesAdmins(): void
    {
        $admin = $this->user('admin');
        $id = $this->ask();
        $req = Database::one('SELECT ar.*, u.is_active, u.display_name, r.code FROM account_requests ar JOIN users u ON u.id = ar.user_id JOIN roles r ON r.id = u.role_id WHERE ar.id = ?', [$id]);
        $this->assertSame('pending', $req['status']);
        $this->assertSame(0, (int) $req['is_active']);
        $this->assertSame('coach', $req['code']);
        $this->assertSame('Camille TEST', $req['display_name']);
        $this->assertFalse((bool) Database::value('SELECT 1 FROM club_users WHERE user_id = ?', [$req['user_id']]), 'aucun accès au club avant validation');
        $this->assertTrue((bool) Database::value("SELECT 1 FROM notifications WHERE user_id = ? AND type = 'account_request'", [$admin]));
    }

    public function testPendingAccountCannotLogInButIsToldWhy(): void
    {
        $id = $this->ask(['email' => 'attente@test.local']);
        $res = Auth::verify('attente@test.local', 'Tatami-Ippon-42', $this->request());
        $this->assertFalse($res['ok']);
        $this->assertTrue($res['pending'] ?? false);
        $this->assertStringContainsString('validation', $res['error']);
        // Mauvais mot de passe : message générique (ne révèle pas l'état du compte).
        $bad = Auth::verify('attente@test.local', 'Mauvais-Mot-99', $this->request());
        $this->assertSame('Identifiant ou mot de passe incorrect.', $bad['error']);
        $this->assertNotNull($id);
    }

    public function testApprovalActivatesAndLinksClub(): void
    {
        $this->admin();
        $club = $this->club();
        $id = $this->ask(['email' => 'ok@test.local', 'club_id' => $club]);
        $res = AccountRequestService::approve($id);
        $this->assertSame($club, $res['club_id']);
        $this->assertSame(1, (int) Database::value('SELECT is_active FROM users WHERE id = ?', [$res['user_id']]));
        $this->assertTrue(Auth::verify('ok@test.local', 'Tatami-Ippon-42', $this->request())['ok']);
        $this->assertDomainError(fn () => AccountRequestService::approve($id), 'déjà traitée');
    }

    public function testApprovalOfUnknownClubCreatesIt(): void
    {
        $this->admin();
        $id = $this->ask(['club_id' => null, 'club_name' => 'Judo Club Nouveau ' . random_int(1, 99999), 'club_city' => 'Arras']);
        $res = AccountRequestService::approve($id);
        $this->assertSame('Arras', Database::value('SELECT city FROM clubs WHERE id = ?', [$res['club_id']]));
    }

    public function testRejectionKeepsAccountClosed(): void
    {
        $this->admin();
        $id = $this->ask(['email' => 'non@test.local']);
        AccountRequestService::reject($id, 'Inconnu du club');
        $res = Auth::verify('non@test.local', 'Tatami-Ippon-42', $this->request());
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString("n'a pas été acceptée", $res['error']);
    }

    public function testRequestValidation(): void
    {
        $this->assertDomainError(fn () => $this->ask(['password' => 'court1', 'password_confirmation' => 'court1']), '12 caractères');
        $this->assertDomainError(fn () => $this->ask(['password_confirmation' => 'Autre-Chose-77']), 'correspondent');
        $this->assertDomainError(fn () => $this->ask(['club_id' => null]), 'club');
        $this->ask(['email' => 'double@test.local']);
        $this->assertDomainError(fn () => $this->ask(['email' => 'double@test.local']), 'existe déjà');
    }

    public function testTooManyRequestsFromSameAddress(): void
    {
        for ($i = 0; $i < 5; $i++) {
            AccountRequestService::create(['first_name' => 'A', 'last_name' => 'B' . $i, 'email' => "flood$i." . random_int(1, 99999) . '@test.local', 'phone' => '0612345678',
                'club_id' => $this->club(), 'password' => 'Tatami-Ippon-42'], 'web', '10.250.250.250');
        }
        $this->assertDomainError(fn () => AccountRequestService::create(['first_name' => 'A', 'last_name' => 'C', 'email' => 'flood-x@test.local', 'phone' => '0612345678',
            'club_id' => $this->club(), 'password' => 'Tatami-Ippon-42'], 'web', '10.250.250.250'), 'Trop de demandes', 429);
    }

    public function testApiTokenLifecycle(): void
    {
        $coach = $this->user('coach', $this->club());
        $raw = ApiTokenService::issue($coach, 'iPhone de test');
        $this->assertSame(64, strlen($raw));
        $this->assertFalse((bool) Database::value('SELECT 1 FROM api_tokens WHERE token_hash = ?', [$raw]), 'le jeton brut n\'est jamais stocké');
        $this->assertSame($coach, ApiTokenService::resolve('Bearer ' . $raw));
        $this->assertNull(ApiTokenService::resolve('Bearer ' . str_repeat('a', 64)));
        $this->assertNull(ApiTokenService::resolve($raw), 'sans « Bearer »');
        Auth::useToken($coach);
        $this->assertTrue(Auth::hasRole('coach'));
        ApiTokenService::revoke('Bearer ' . $raw);
        $this->assertNull(ApiTokenService::resolve('Bearer ' . $raw));
    }

    public function testDeactivatedUserTokenGivesNoAccess(): void
    {
        $coach = $this->user('coach', $this->club());
        $raw = ApiTokenService::issue($coach);
        Database::run('UPDATE users SET is_active = 0 WHERE id = ?', [$coach]);
        Auth::useToken((int) ApiTokenService::resolve('Bearer ' . $raw));
        $this->assertFalse(Auth::check());
    }

    public function testStampChangesWithEveryRegistrationChange(): void
    {
        $cid = $this->competition();
        $club = $this->club();
        $s0 = RegistrationService::stamp($cid, $club);
        $rid = RegistrationService::register($cid, ['last_name' => 'Direct', 'first_name' => 'Lou', 'sex' => 'F', 'birth_date' => $this->birthFor(10), 'declared_weight_kg' => 30],
            ['actor' => 'club', 'club_id' => $club]);
        $s1 = RegistrationService::stamp($cid, $club);
        $this->assertNotSame($s0, $s1);
        \App\Services\AttendanceService::mark($rid, 'present', 30.4);
        $s2 = RegistrationService::stamp($cid, $club);
        $this->assertNotSame($s1, $s2, 'présence et pesée');
        $this->assertSame($s2, RegistrationService::stamp($cid, $club), 'stable sans changement');
        $this->assertSame(RegistrationService::stamp($cid, $this->club()), RegistrationService::stamp($cid, $this->club()), 'un autre club ne voit pas ces changements');
    }

    public function testShortIdentifierLogsInWhenUnique(): void
    {
        $u = $this->user('commissioner');
        $email = (string) Database::value('SELECT email FROM users WHERE id = ?', [$u]);
        $short = strstr($email, '@', true);
        $this->assertTrue(Auth::verify($short, 'MotDePasse2026!', $this->request())['ok'], 'identifiant court');
        $this->assertTrue(Auth::verify(strtoupper($short), 'MotDePasse2026!', $this->request())['ok'], 'sans tenir compte des majuscules');
        $this->assertFalse(Auth::verify('%', 'MotDePasse2026!', $this->request())['ok'], 'pas de joker SQL');
    }
}
