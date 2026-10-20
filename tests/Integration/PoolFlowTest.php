<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Services\PoolService;
use App\Services\SettingsService;
use Tests\TestCase;

final class PoolFlowTest extends TestCase
{
    public function testNoPresentAthleteMeansNoGeneration(): void
    {
        $this->admin();
        $cid = $this->competition();
        $this->athletes($cid, 4, 'M', 10, 30, null, false);
        $this->assertDomainError(fn () => PoolService::generate($cid), 'Aucun athlète présent');
    }

    public function testGenerationUsesOnlyPresentAndSeparatesSexesAndAges(): void
    {
        $this->admin();
        $cid = $this->competition();
        $this->athletes($cid, 7, 'M', 10, 28);
        $this->athletes($cid, 5, 'F', 10, 27);
        $this->athletes($cid, 4, 'M', 12, 38);
        $absent = $this->athletes($cid, 2, 'M', 10, 50, null, false);
        $g = PoolService::generate($cid);
        $pools = PoolService::pools($g);
        $placed = [];
        foreach ($pools as $p) {
            $this->assertContains($p['size'], [3, 4, 5]);
            $sexes = array_unique(array_column($p['athletes'], 'sex'));
            $this->assertCount(1, $sexes, 'Jamais de poule mixte');
            $ages = Database::column('SELECT DISTINCT age_category_id FROM registrations WHERE id IN (' . implode(',', array_map('intval', array_column($p['athletes'], 'registration_id'))) . ')');
            $this->assertCount(1, $ages, 'Jamais deux catégories d\'âge');
            $placed = array_merge($placed, array_column($p['athletes'], 'registration_id'));
        }
        $this->assertCount(16, $placed);
        foreach ($absent as $a) {
            $this->assertNotContains($a, array_map('intval', $placed));
        }
        $this->assertNotNull(Database::value('SELECT tatami_id FROM pools WHERE generation_id = ? LIMIT 1', [$g]));
    }

    public function testValidationCreatesRoundRobinBoutsWithDurationSnapshot(): void
    {
        $this->admin();
        $cid = $this->competition();
        $this->athletes($cid, 5, 'M', 10, 28);
        SettingsService::set($cid, 'preferred_pool_size', 5);
        $g = PoolService::generate($cid);
        PoolService::validate($g);
        $pools = PoolService::pools($g);
        $this->assertCount(1, $pools);
        $this->assertSame(10, (int) Database::value('SELECT COUNT(*) FROM bouts WHERE pool_id = ?', [$pools[0]['id']]), '5 × 4 / 2 = 10 combats');
        $rule = Database::one('SELECT duration_seconds FROM bouts WHERE pool_id = ? LIMIT 1', [$pools[0]['id']]);
        Database::run('UPDATE fight_duration_rules SET duration_seconds = 999 WHERE competition_id = ?', [$cid]);
        $this->assertSame($rule['duration_seconds'], Database::value('SELECT duration_seconds FROM bouts WHERE pool_id = ? LIMIT 1', [$pools[0]['id']]), 'Pas de modification rétroactive');
        $this->assertSame('validated', Database::value('SELECT status FROM pool_generations WHERE id = ?', [$g]));
    }

    public function testImpossibleGroupNeedsAnExplicitDecision(): void
    {
        $this->admin();
        $cid = $this->competition();
        [$a, $b] = $this->athletes($cid, 2, 'F', 12, 40);
        $g = PoolService::generate($cid);
        $report = json_decode((string) Database::value('SELECT report_json FROM pool_generations WHERE id = ?', [$g]), true);
        $this->assertSame('needs_decision', $report['groups'][0]['status']);
        $this->assertCount(2, PoolService::unplaced($cid), 'Les 2 athlètes restent visibles, non placés');
        $this->assertDomainError(fn () => PoolService::validate($g), 'Aucune poule');
        PoolService::decide($cid, $report['groups'][0]['key'], ['type' => 'sizes', 'sizes' => [2, 3, 4, 5]]);
        $this->assertCount(0, PoolService::unplaced($cid));
        PoolService::validate((int) PoolService::activeGeneration($cid)['id']);
        $this->assertSame(1, (int) Database::value('SELECT COUNT(*) FROM bouts WHERE competition_id = ?', [$cid]));
    }

    public function testManualEditsLockAndRegenerationRules(): void
    {
        $this->admin();
        $cid = $this->competition();
        $ids = $this->athletes($cid, 8, 'M', 10, 28);
        $g = PoolService::generate($cid);
        $pools = PoolService::pools($g);
        $this->assertCount(2, $pools);
        $a = (int) $pools[0]['athletes'][0]['registration_id'];
        $b = (int) $pools[1]['athletes'][0]['registration_id'];
        PoolService::swapAthletes($a, $b);
        $this->assertSame((int) $pools[1]['id'], (int) Database::value('SELECT pool_id FROM pool_athletes WHERE registration_id = ? AND generation_id = ?', [$a, $g]));
        PoolService::setLocked((int) $pools[0]['id'], true);
        $this->assertDomainError(fn () => PoolService::moveAthlete($a, (int) $pools[0]['id']), 'verrouillée');
        $lockedMembers = array_map('intval', Database::column('SELECT registration_id FROM pool_athletes WHERE pool_id = ? ORDER BY registration_id', [$pools[0]['id']]));
        $g2 = PoolService::generate($cid);
        $kept = Database::one('SELECT * FROM pools WHERE generation_id = ? AND is_locked = 1', [$g2]);
        $this->assertNotNull($kept, 'La poule verrouillée est conservée à la régénération');
        $this->assertSame($lockedMembers, array_map('intval', Database::column('SELECT registration_id FROM pool_athletes WHERE pool_id = ? ORDER BY registration_id', [$kept['id']])));
        PoolService::validate($g2);
        $bout = Database::one('SELECT * FROM bouts WHERE competition_id = ? ORDER BY id LIMIT 1', [$cid]);
        \App\Services\BoutService::act((int) $bout['id'], ['action' => 'start', 'version' => $bout['version']]);
        $this->assertDomainError(fn () => PoolService::generate($cid), 'ont déjà commencé');
        $this->assertDomainError(fn () => PoolService::cancel($g2), 'ont déjà commencé');
    }

    public function testLateAthleteIsAddedWithoutTouchingExistingResults(): void
    {
        $this->admin();
        $cid = $this->competition();
        $this->athletes($cid, 4, 'M', 10, 28);
        $g = PoolService::generate($cid);
        PoolService::validate($g);
        $pool = PoolService::pools($g)[0];
        $before = (int) Database::value('SELECT COUNT(*) FROM bouts WHERE pool_id = ?', [$pool['id']]);
        [$late] = $this->athletes($cid, 1, 'M', 10, 29);
        $this->assertCount(1, PoolService::unplaced($cid));
        $sugg = PoolService::suggestionsFor($late);
        $this->assertSame((int) $pool['id'], (int) $sugg[0]['pool']['id']);
        PoolService::moveAthlete($late, (int) $pool['id']);
        $this->assertSame($before + 4, (int) Database::value('SELECT COUNT(*) FROM bouts WHERE pool_id = ?', [$pool['id']]), '4 nouveaux combats (poule de 5)');
    }

    public function testDistributionBalancesTatamis(): void
    {
        $this->admin();
        $cid = $this->competition(['mat_count' => 3]);
        $this->athletes($cid, 12, 'M', 10, 26);
        $this->athletes($cid, 12, 'F', 10, 26);
        $this->athletes($cid, 12, 'M', 12, 36);
        $g = PoolService::generate($cid);
        $loads = PoolService::tatamiLoads($g);
        $this->assertCount(3, $loads['tatamis']);
        foreach ($loads['tatamis'] as $t) {
            $this->assertGreaterThan(0, $t['pools'], 'Chaque tapis reçoit des poules');
        }
        $perBout = (int) Database::value('SELECT MAX(estimated_seconds / 6) FROM pools WHERE generation_id = ?', [$g]);
        $this->assertLessThanOrEqual(6 * $perBout, $loads['spread_seconds']);
    }
}
