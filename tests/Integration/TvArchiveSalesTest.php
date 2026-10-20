<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\HttpException;
use App\Services\AuditLogger;
use App\Services\BoutService;
use App\Services\CompetitionService;
use App\Services\PoolService;
use App\Services\RegistrationService;
use App\Services\SalesService;
use App\Services\TvTokenService;
use Tests\TestCase;

final class TvArchiveSalesTest extends TestCase
{
    public function testTvTokenIsReadOnlyRevocableAndLimitedToItsTatami(): void
    {
        $this->admin();
        $cid = $this->competition(['mat_count' => 2]);
        [$t1, $t2] = CompetitionService::tatamis($cid);
        $link = TvTokenService::create((int) $t1['id'], null, 1);
        $this->assertStringContainsString('/tv/tapis/1?jeton=', $link['url']);
        $this->assertNotSame($link['token'], Database::value('SELECT token_hash FROM tv_tokens WHERE id = ?', [$link['id']]), 'Jeton stocké haché');
        $this->assertSame((int) $t1['id'], (int) TvTokenService::verify($link['token'], 1)['id']);
        $this->assertDomainError(fn () => TvTokenService::verify($link['token'], 2), 'invalide', 403);
        $this->assertDomainError(fn () => TvTokenService::verify('faux', 1), 'invalide', 403);
        TvTokenService::setActive($link['id'], false);
        $this->assertDomainError(fn () => TvTokenService::verify($link['token'], 1), 'désactivé', 403);
        TvTokenService::setActive($link['id'], true);
        Database::run('UPDATE tv_tokens SET expires_at = NOW() - INTERVAL 1 MINUTE WHERE id = ?', [$link['id']]);
        $this->assertDomainError(fn () => TvTokenService::verify($link['token'], 1), 'expiré', 403);
        $new = TvTokenService::reset((int) $t1['id']);
        $this->assertNotNull(Database::value('SELECT revoked_at FROM tv_tokens WHERE id = ?', [$link['id']]));
        $this->assertSame((int) $t1['id'], (int) TvTokenService::verify($new['token'], 1)['id']);
    }

    public function testTvStateFollowsTheBout(): void
    {
        $this->admin();
        $cid = $this->competition(['mat_count' => 1]);
        $this->athletes($cid, 3);
        $g = PoolService::generate($cid);
        PoolService::validate($g);
        $t = CompetitionService::tatamis($cid)[0];
        $this->assertSame('waiting', TvTokenService::tvState($t)['mode']);
        BoutService::selectCurrent((int) $t['id']);
        $t = CompetitionService::tatamis($cid)[0];
        $st = TvTokenService::tvState($t);
        $this->assertSame('next', $st['mode']);
        $this->assertCount(2, $st['next']);
        $b = $st['current'];
        BoutService::act($b['id'], ['action' => 'start', 'version' => $b['version']]);
        $this->assertSame('running', TvTokenService::tvState($t)['mode']);
        $s = BoutService::state($b['id']);
        BoutService::act($b['id'], ['action' => 'score', 'side' => 'a', 'kind' => 'wazaari', 'delta' => 1, 'version' => $s['version']]);
        $tv = TvTokenService::tvState($t);
        $this->assertSame(1, $tv['current']['a']['wazaari'], 'La saisie du commissaire apparaît à l\'écran sans double saisie');
    }

    public function testArchivedCompetitionIsReadOnlyUntilExplicitReopening(): void
    {
        $this->admin();
        $cid = $this->competition();
        [$rid] = $this->athletes($cid, 1, 'M', 10, 30, null, false);
        CompetitionService::archive($cid, 'Fin de saison');
        $this->assertSame('archived', CompetitionService::find($cid)['status']);
        $this->assertDomainError(fn () => RegistrationService::withdraw($rid, ['actor' => 'admin']), null, 423);
        $this->assertDomainError(fn () => CompetitionService::update($cid, ['name' => 'Modifié']), null, 423);
        $this->assertDomainError(fn () => PoolService::generate($cid), null, 423);
        $this->assertDomainError(fn () => CompetitionService::reopen($cid, ''), 'motif');
        CompetitionService::reopen($cid, 'Correction d\'un résultat');
        $this->assertSame('registrations_open', CompetitionService::find($cid)['status']);
    }

    public function testSalesRegisterRefundsAndNetRevenue(): void
    {
        $this->admin();
        $cid = $this->competition();
        $p1 = Database::insert('products', ['competition_id' => $cid, 'category' => 'boissons', 'name' => 'Café', 'price_cents' => 150]);
        $p2 = Database::insert('products', ['competition_id' => $cid, 'category' => 'restauration', 'name' => 'Crêpe', 'price_cents' => 200]);
        $s1 = SalesService::record($cid, [['product_id' => $p1, 'quantity' => 2], ['product_id' => $p2, 'quantity' => 1]], 'cash', 'ref-1');
        $this->assertSame($s1, SalesService::record($cid, [['product_id' => $p1, 'quantity' => 2]], 'cash', 'ref-1'), 'Renvoi sans doublon');
        SalesService::record($cid, [['product_id' => $p2, 'quantity' => 3]], 'card');
        Database::run('UPDATE products SET price_cents = 999 WHERE id = ?', [$p1]);
        SalesService::refund($s1, 'Erreur de caisse');
        $this->assertDomainError(fn () => SalesService::refund($s1, 'Encore'), 'déjà');
        $t = SalesService::summary($cid)['totals'];
        $this->assertSame(1100, (int) $t['gross']);
        $this->assertSame(500, (int) $t['refunds']);
        $this->assertSame(600, (int) $t['net']);
        $this->assertStringContainsString('Crêpe', SalesService::exportCsv($cid));
    }

    public function testAuditChainDetectsTampering(): void
    {
        $this->admin();
        AuditLogger::log('test.one');
        AuditLogger::log('test.two', null, null, null, null, 'motif');
        $this->assertTrue(AuditLogger::verifyChain()['ok']);
        Database::run("UPDATE audit_logs SET reason = 'falsifié' WHERE action = 'test.two'");
        $res = AuditLogger::verifyChain();
        $this->assertFalse($res['ok']);
    }
}
