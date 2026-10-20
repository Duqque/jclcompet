<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Services\BoutService;
use App\Services\RegistrationService;
use Tests\TestCase;

final class AuthPermissionTest extends TestCase
{
    private function request(): Request
    {
        return new Request('POST', '/connexion', [], [], [], ['REMOTE_ADDR' => '10.0.0.' . random_int(1, 250)]);
    }

    public function testLoginSuccessAndLogout(): void
    {
        $id = $this->user('admin', null, 'BonMotDePasse2026');
        $email = Database::value('SELECT email FROM users WHERE id = ?', [$id]);
        $res = Auth::attempt($email, 'BonMotDePasse2026', $this->request());
        $this->assertTrue($res['ok']);
        $this->assertSame($id, Auth::id());
        $this->assertTrue(Auth::can('results.correct'));
        Auth::logout();
        $this->assertFalse(Auth::check());
    }

    public function testWrongPasswordAndRateLimit(): void
    {
        $id = $this->user('coach', $this->club());
        $email = Database::value('SELECT email FROM users WHERE id = ?', [$id]);
        $req = $this->request();
        for ($i = 0; $i < 5; $i++) {
            $this->assertFalse(Auth::attempt($email, 'mauvais', $req)['ok']);
        }
        $blocked = Auth::attempt($email, 'MotDePasse2026!', $req);
        $this->assertFalse($blocked['ok'], 'Bon mot de passe refusé après 5 échecs');
        $this->assertStringContainsString('Trop de tentatives', $blocked['error']);
    }

    public function testInactiveAndDemoAccountsInProduction(): void
    {
        $id = $this->user('admin');
        Database::run('UPDATE users SET is_active = 0 WHERE id = ?', [$id]);
        $email = Database::value('SELECT email FROM users WHERE id = ?', [$id]);
        $this->assertFalse(Auth::attempt($email, 'MotDePasse2026!', $this->request())['ok']);
        $demo = $this->user('admin');
        Database::run('UPDATE users SET is_demo = 1 WHERE id = ?', [$demo]);
        Config::set('app.env', 'production');
        try {
            $res = Auth::attempt(Database::value('SELECT email FROM users WHERE id = ?', [$demo]), 'MotDePasse2026!', $this->request());
            $this->assertFalse($res['ok']);
            $this->assertStringContainsString('démonstration', $res['error']);
        } finally {
            Config::set('app.env', 'local');
        }
    }

    public function testRolePermissionsAreSeparated(): void
    {
        $club = $this->club();
        Auth::actAs($this->user('coach', $club));
        $this->assertTrue(Auth::can('registrations.own'));
        $this->assertFalse(Auth::can('registrations.manage'));
        $this->assertFalse(Auth::can('bouts.score'));
        Auth::actAs($this->user('commissioner'));
        $this->assertTrue(Auth::can('bouts.score'));
        $this->assertFalse(Auth::can('results.correct'));
        $this->assertFalse(Auth::can('pools.manage'));
        Auth::actAs($this->user('reception'));
        $this->assertTrue(Auth::can('attendance.manage'));
        $this->assertFalse(Auth::can('competitions.manage'));
    }

    public function testClubDataIsolation(): void
    {
        $this->admin();
        $cid = $this->competition();
        $clubA = $this->club('A');
        $clubB = $this->club('B');
        [$regB] = $this->athletes($cid, 1, 'M', 10, 30, [$clubB], false);
        Auth::actAs($this->user('coach', $clubA));
        $this->assertSame([], RegistrationService::list($cid, ['status' => 'all'], $clubA));
        $this->assertCount(1, RegistrationService::list($cid, ['status' => 'all'], $clubB));
        $this->assertDomainError(fn () => RegistrationService::update($regB, ['first_name' => 'Pirate'], ['actor' => 'club', 'club_id' => $clubA]), null, 403);
        $this->assertDomainError(fn () => RegistrationService::withdraw($regB, ['actor' => 'club', 'club_id' => $clubA]), null, 403);
        $lines = array_filter(explode("\n", trim(RegistrationService::exportCsv($cid, $clubA))));
        $this->assertCount(1, $lines, "L'export du club A ne contient que l'en-tête");
    }

    public function testCommissionerLimitedToActiveAssignedTatami(): void
    {
        $this->admin();
        $cid = $this->competition();
        $this->athletes($cid, 3);
        $g = \App\Services\PoolService::generate($cid);
        \App\Services\PoolService::validate($g);
        $bout = Database::one('SELECT * FROM bouts WHERE competition_id = ? ORDER BY id LIMIT 1', [$cid]);
        $other = (int) Database::value('SELECT id FROM tatamis WHERE competition_id = ? AND id <> ? LIMIT 1', [$cid, $bout['tatami_id']]);
        $u = $this->user('commissioner');
        Auth::actAs($u);
        $this->assertDomainError(fn () => BoutService::act((int) $bout['id'], ['action' => 'start', 'version' => $bout['version']]), "n'est pas sur un tapis", 403);
        Database::insert('commissioner_tatamis', ['user_id' => $u, 'tatami_id' => $other, 'is_active' => 1]);
        Database::insert('commissioner_tatamis', ['user_id' => $u, 'tatami_id' => $bout['tatami_id'], 'is_active' => 0]);
        $this->assertDomainError(fn () => BoutService::act((int) $bout['id'], ['action' => 'start', 'version' => $bout['version']]), 'tapis actif', 403);
        Database::run('UPDATE commissioner_tatamis SET is_active = (tatami_id = ?) WHERE user_id = ?', [$bout['tatami_id'], $u]);
        $state = BoutService::act((int) $bout['id'], ['action' => 'start', 'version' => $bout['version']]);
        $this->assertTrue($state['clock']['running']);
    }
}
