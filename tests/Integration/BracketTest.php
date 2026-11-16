<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Services\AttendanceService;
use App\Services\BracketService;
use App\Services\CompetitionService;
use App\Services\PoolService;
use App\Services\RankingService;
use App\Services\RegistrationService;
use Tests\TestCase;

/** Formule « tableaux officiels » : poules jusqu'à 8, tableau à double repêchage à partir de 9. */
final class BracketTest extends TestCase
{
    private function officialCompetition(): int
    {
        $year = (int) date('Y');
        $id = CompetitionService::create([
            'name' => 'Tableaux ' . random_int(1, 999999), 'event_date' => date('Y-m-d', strtotime('+10 days')), 'start_time' => '09:00:00',
            'address' => '1 rue du Test', 'city' => 'Testville', 'mat_count' => 2, 'pool_sizes' => '3,4,5',
            'registration_opens_at' => date('Y-m-d H:i:s', strtotime('-5 days')), 'registration_closes_at' => date('Y-m-d H:i:s', strtotime('+5 days')),
            'age_reference_date' => $year . '-12-31',
        ], ['U11', 'U15'], true, 'official');
        Database::run("UPDATE competitions SET status = 'registrations_open' WHERE id = ?", [$id]);
        return $id;
    }

    /** Inscrit et pointe $n minimes garçons dans la catégorie -46 kg, clubs alternés. */
    private function fighters(int $cid, int $n, int $clubs = 3): array
    {
        $clubIds = [];
        for ($i = 0; $i < $clubs; $i++) {
            $clubIds[] = $this->club();
        }
        $ids = [];
        for ($i = 0; $i < $n; $i++) {
            $ids[] = RegistrationService::register($cid, [
                'last_name' => 'Tableau' . $i . random_int(1, 99999), 'first_name' => 'Lou', 'sex' => 'M', 'birth_date' => $this->birthFor(13),
                'declared_weight_kg' => 42.2 + ($i % 10) * 0.3,
            ], ['actor' => 'admin', 'club_id' => $clubIds[$i % $clubs]]);
            AttendanceService::mark(end($ids), 'present');
        }
        return $ids;
    }

    /** Joue tous les combats programmés (le premier appelé gagne par ippon), validation par validation. */
    private function playAll(int $cid, int $max = 200): int
    {
        $played = 0;
        while ($played < $max) {
            $b = Database::one("SELECT * FROM bouts WHERE competition_id = ? AND status = 'scheduled' ORDER BY tatami_id, order_on_tatami LIMIT 1", [$cid]);
            if (!$b) {
                break;
            }
            Database::run("UPDATE bouts SET status = 'validated', winner_side = 'a', win_method = 'ippon', a_ippon = 1 WHERE id = ?", [$b['id']]);
            RankingService::recomputePool((int) $b['pool_id']);
            $played++;
        }
        return $played;
    }

    private function generateAndValidate(int $cid): int
    {
        $gen = PoolService::generate($cid);
        PoolService::validate($gen, true);
        return $gen;
    }

    public function testPlanThresholds(): void
    {
        $this->assertSame('single', BracketService::plan(1)['type']);
        foreach ([2, 3, 4, 5] as $n) {
            $this->assertSame('pool', BracketService::plan($n)['type'], "$n combattants");
        }
        $this->assertSame([3, 3], BracketService::plan(6)['pools']);
        $this->assertSame([4, 3], BracketService::plan(7)['pools']);
        $this->assertSame([4, 4], BracketService::plan(8)['pools']);
        $this->assertSame(['type' => 'bracket', 'size' => 16], BracketService::plan(9));
        $this->assertSame(32, BracketService::plan(17)['size']);
        $this->assertSame(64, BracketService::plan(33)['size']);
    }

    public function testDrawPlacesSeedsByesAndSeparatesClubs(): void
    {
        $athletes = [];
        for ($i = 1; $i <= 12; $i++) {
            $athletes[] = ['id' => $i, 'club_id' => $i % 3];
        }
        $seeds = [5 => 1, 6 => 2, 7 => 3, 8 => 4, 9 => 5, 10 => 6, 11 => 7, 12 => 8];
        $slots = BracketService::draw($athletes, $seeds, 16, 42);
        $this->assertCount(16, $slots);
        $this->assertSame(5, $slots[0], 'tête de série 1 en haut');
        $this->assertSame(6, $slots[15], 'tête de série 2 en bas');
        // En quart (cases 0-3 contre 4-7…) : 1 contre 8, 4 contre 5, 3 contre 6, 7 contre 2
        $quarterOf = fn ($reg) => intdiv(array_search($reg, $slots, true), 4);
        $this->assertSame($quarterOf(5), intdiv(array_search(12, $slots, true), 4) === $quarterOf(5) ? $quarterOf(5) : -1, '1 et 8 dans le même quart');
        $this->assertSame($quarterOf(8), $quarterOf(9), '4 et 5 dans le même quart');
        $this->assertSame($quarterOf(7), $quarterOf(10), '3 et 6 dans le même quart');
        $this->assertSame($quarterOf(11), $quarterOf(6), '7 et 2 dans le même quart');
        // 4 exemptions, jamais deux dans le même premier combat, et en face de têtes de série
        $this->assertSame(4, count(array_filter($slots, fn ($v) => $v === null)));
        for ($p = 0; $p < 16; $p += 2) {
            $this->assertFalse($slots[$p] === null && $slots[$p + 1] === null);
        }
        $this->assertNull($slots[1], 'exemption en face de la tête de série 1');
        // Clubs : jamais deux combattants du même club au premier combat
        $club = array_column($athletes, 'club_id', 'id');
        for ($p = 0; $p < 16; $p += 2) {
            if ($slots[$p] !== null && $slots[$p + 1] !== null) {
                $this->assertNotSame($club[$slots[$p]], $club[$slots[$p + 1]], 'même club au premier tour');
            }
        }
    }

    public function testDoubleRepechageBracketPlayedToTheEnd(): void
    {
        $this->admin();
        $cid = $this->officialCompetition();
        $this->fighters($cid, 12);
        $gen = $this->generateAndValidate($cid);
        $pool = Database::one("SELECT * FROM pools WHERE generation_id = ? AND kind = 'bracket'", [$gen]);
        $this->assertNotNull($pool, 'tableau créé');
        $this->assertSame(16, (int) $pool['bracket_size']);
        $this->assertStringContainsString('-46', $pool['label']);
        // Tour 1 : 12 combattants, 4 exemptés → 4 combats
        $this->assertSame(4, (int) Database::value("SELECT COUNT(*) FROM bouts WHERE pool_id = ? AND stage_label LIKE '8e de finale%'", [$pool['id']]));

        $played = $this->playAll($cid);
        $this->assertGreaterThan(10, $played);
        $st = BracketService::structure(PoolService::pool((int) $pool['id']));
        $this->assertTrue($st['complete'], 'tableau terminé');
        $places = array_count_values($st['places']);
        ksort($places);
        $this->assertSame([1 => 1, 2 => 1, 3 => 2, 5 => 2, 7 => 2], $places, 'classement 1, 2, 3, 3, 5, 5, 7, 7');
        // Places de 3e avant la finale, repêchages croisés
        $final = Database::one("SELECT * FROM bouts WHERE pool_id = ? AND stage_label = 'Finale'", [$pool['id']]);
        foreach (Database::all("SELECT * FROM bouts WHERE pool_id = ? AND bracket_key LIKE 'B%'", [$pool['id']]) as $b) {
            $this->assertLessThan((int) $final['order_in_pool'], (int) $b['order_in_pool'], 'place de 3e avant la finale');
        }
        $this->assertSame(8, (int) Database::value('SELECT COUNT(*) FROM athlete_rankings WHERE pool_id = ?', [$pool['id']]));
        $this->assertSame('finished', Database::value('SELECT status FROM pools WHERE id = ?', [$pool['id']]));
        $view = BracketService::view(PoolService::pool((int) $pool['id']));
        $this->assertCount(12, $view['ranking'], 'toute la catégorie, y compris les non classés');
        $this->assertSame(4, count(array_filter($view['ranking'], fn ($r) => $r['place'] === null)), '4 non classés');
    }

    public function testRepechageLadderTakesVictimsOfQuarterWinnerInRoundOrder(): void
    {
        $this->admin();
        $cid = $this->officialCompetition();
        $this->fighters($cid, 16, 4);
        $gen = $this->generateAndValidate($cid);
        $pool = PoolService::pool((int) Database::value("SELECT id FROM pools WHERE generation_id = ? AND kind = 'bracket'", [$gen]));
        // Joue le tableau principal jusqu'aux quarts inclus
        for ($i = 0; $i < 12; $i++) {
            $b = Database::one("SELECT * FROM bouts WHERE pool_id = ? AND status = 'scheduled' AND bracket_key LIKE 'M%' ORDER BY order_in_pool LIMIT 1", [$pool['id']]);
            Database::run("UPDATE bouts SET status = 'validated', winner_side = 'a', win_method = 'ippon', a_ippon = 1 WHERE id = ?", [$b['id']]);
            RankingService::recomputePool((int) $pool['id']);
        }
        $st = BracketService::structure($pool);
        $ladders = array_values(array_filter($st['nodes'], fn ($n) => $n['stage'] === 'repechage'));
        $this->assertCount(4, $ladders, 'un repêchage par quart (16 combattants : perdant du 1er tour contre perdant du quart)');
        foreach ($ladders as $n) {
            $this->assertTrue($n['fight']);
            $quarterWinnerBouts = array_filter($st['nodes'], fn ($m) => $m['stage'] === 'main' && $m['fight'] && isset($m['loser']['reg']) && in_array($m['loser']['reg'], [$n['a']['reg'], $n['b']['reg']], true));
            $this->assertCount(2, $quarterWinnerBouts);
        }
    }

    public function testSixToEightFightersTwoPoolsThenCrossedFinal(): void
    {
        $this->admin();
        $cid = $this->officialCompetition();
        $this->fighters($cid, 7);
        $gen = $this->generateAndValidate($cid);
        $kinds = Database::column('SELECT kind FROM pools WHERE generation_id = ? ORDER BY number', [$gen]);
        $this->assertSame(['pool', 'pool', 'final_block'], $kinds);
        $sizes = Database::column("SELECT COUNT(*) FROM pool_athletes pa JOIN pools p ON p.id = pa.pool_id WHERE p.generation_id = ? AND p.kind = 'pool' GROUP BY p.id ORDER BY p.number", [$gen]);
        $this->assertEqualsCanonicalizing([4, 3], array_map('intval', $sizes));
        $block = Database::one("SELECT * FROM pools WHERE generation_id = ? AND kind = 'final_block'", [$gen]);
        $first = Database::one("SELECT * FROM pools WHERE generation_id = ? AND kind = 'pool' ORDER BY number LIMIT 1", [$gen]);
        $this->assertSame($first['tatami_id'], $block['tatami_id'], 'tableau final sur le tapis de sa première poule');
        $this->assertSame(0, (int) Database::value('SELECT COUNT(*) FROM bouts WHERE pool_id = ?', [$block['id']]), 'demi-finales après les poules');
        $this->playAll($cid);
        $semis = Database::all("SELECT * FROM bouts WHERE pool_id = ? AND bracket_key LIKE 'S%' ORDER BY bracket_key", [$block['id']]);
        $this->assertCount(2, $semis);
        $st = BracketService::structure(PoolService::pool((int) $block['id']));
        $this->assertTrue($st['complete']);
        $places = array_count_values($st['places']);
        ksort($places);
        $this->assertSame([1 => 1, 2 => 1, 3 => 2, 5 => 2, 7 => 1], $places, '1, 2, 3, 3, 5, 5, 7');
        // Les poules du tableau final ne donnent pas de place à elles seules
        $this->assertSame(0, (int) Database::value("SELECT COUNT(*) FROM athlete_rankings ar JOIN pools p ON p.id = ar.pool_id WHERE p.generation_id = ? AND p.kind = 'pool'", [$gen]));
        $this->assertSame(7, (int) Database::value('SELECT COUNT(*) FROM athlete_rankings WHERE pool_id = ?', [$block['id']]));
    }

    public function testSmallCategoriesUseOnePoolAndAloneWinsByDefault(): void
    {
        $this->admin();
        $cid = $this->officialCompetition();
        $this->fighters($cid, 4);
        // Un combattant seul en -50 kg
        $alone = RegistrationService::register($cid, ['last_name' => 'Seul', 'first_name' => 'Max', 'sex' => 'M', 'birth_date' => $this->birthFor(13), 'declared_weight_kg' => 48],
            ['actor' => 'admin', 'club_id' => $this->club()]);
        AttendanceService::mark($alone, 'present');
        $gen = $this->generateAndValidate($cid);
        $this->assertSame(['pool', 'bracket'], Database::column('SELECT kind FROM pools WHERE generation_id = ? ORDER BY number', [$gen]));
        $this->assertSame(1, (int) Database::value('SELECT place FROM athlete_rankings WHERE registration_id = ?', [$alone]), 'seul : 1er sans combat');
        $this->assertSame(6, (int) Database::value("SELECT COUNT(*) FROM bouts b JOIN pools p ON p.id = b.pool_id WHERE p.generation_id = ? AND p.kind = 'pool'", [$gen]), 'poule de 4 : 6 combats');
    }

    public function testRedrawWithSeedsBeforeFirstBout(): void
    {
        $this->admin();
        $cid = $this->officialCompetition();
        $ids = $this->fighters($cid, 10);
        $gen = $this->generateAndValidate($cid);
        $pool = Database::one("SELECT * FROM pools WHERE generation_id = ? AND kind = 'bracket'", [$gen]);
        BracketService::redraw((int) $pool['id'], [$ids[3] => 1, $ids[7] => 2]);
        $this->assertSame(1, (int) Database::value('SELECT seed FROM pool_athletes WHERE pool_id = ? AND registration_id = ?', [$pool['id'], $ids[3]]));
        $this->assertSame(16, (int) Database::value('SELECT seed FROM pool_athletes WHERE pool_id = ? AND registration_id = ?', [$pool['id'], $ids[7]]));
        $this->assertSame(1, (int) Database::value('SELECT draw_seed FROM pool_athletes WHERE pool_id = ? AND registration_id = ?', [$pool['id'], $ids[3]]));
        $this->assertDomainError(fn () => BracketService::redraw((int) $pool['id'], [$ids[1] => 1, $ids[2] => 1]), 'une fois');
        $this->assertDomainError(fn () => PoolService::moveAthlete($ids[0], (int) $pool['id']), 'tableau');
    }

    public function testRedrawAfterStartKeepsOrResetsPlayedBouts(): void
    {
        $this->admin();
        $cid = $this->officialCompetition();
        $this->fighters($cid, 12);
        $gen = $this->generateAndValidate($cid);
        $pool = Database::one("SELECT * FROM pools WHERE generation_id = ? AND kind = 'bracket'", [$gen]);
        $pid = (int) $pool['id'];
        Database::run('UPDATE pools SET is_locked = 1 WHERE id = ?', [$pid]);
        $this->assertDomainError(fn () => BracketService::redraw($pid, []), 'Refaire');
        $this->playAll($cid, 2);
        $played = Database::all("SELECT id, a_registration_id, b_registration_id FROM bouts WHERE pool_id = ? AND status = 'validated'", [$pid]);
        $this->assertCount(2, $played);
        $slotOf = fn ($reg) => (int) Database::value('SELECT seed FROM pool_athletes WHERE pool_id = ? AND registration_id = ?', [$pid, $reg]);
        $before = [];
        foreach ($played as $b) {
            $before[(int) $b['a_registration_id']] = $slotOf($b['a_registration_id']);
            $before[(int) $b['b_registration_id']] = $slotOf($b['b_registration_id']);
        }
        $this->assertDomainError(fn () => BracketService::redraw($pid, []), 'garder');

        // Garder : les combats disputés et leurs combattants restent en place
        BracketService::redraw($pid, [], 'keep');
        foreach ($before as $reg => $slot) {
            $this->assertSame($slot, $slotOf($reg), 'combattant déjà engagé gardé à sa place');
        }
        $this->assertSame(2, (int) Database::value("SELECT COUNT(*) FROM bouts WHERE pool_id = ? AND status = 'validated'", [$pid]));
        $this->assertSame(0, (int) Database::value('SELECT is_locked FROM pools WHERE id = ?', [$pid]), 'tirage à valider de nouveau');
        $this->assertSame(12, (int) Database::value('SELECT COUNT(DISTINCT seed) FROM pool_athletes WHERE pool_id = ?', [$pid]));

        // Tout recommencer : résultats effacés, tableau reprogrammé depuis le premier tour
        BracketService::redraw($pid, [], 'reset');
        $this->assertSame(0, (int) Database::value("SELECT COUNT(*) FROM bouts WHERE pool_id = ? AND status <> 'scheduled'", [$pid]));
        $this->assertSame(4, (int) Database::value("SELECT COUNT(*) FROM bouts WHERE pool_id = ? AND stage_label LIKE '8e de finale%'", [$pid]));
        $this->assertGreaterThanOrEqual(1, (int) Database::value("SELECT COUNT(*) FROM audit_logs WHERE entity_id = ? AND action = 'bracket.redraw' AND old_values IS NOT NULL", [(string) $pid]), 'résultats effacés gardés dans le journal');
    }

    public function testClubsNeverMeetInFirstFightWhenAvoidable(): void
    {
        // 45 combattants de 4 clubs (dont un très représenté) dans un tableau de 64, 8 têtes de série
        for ($run = 1; $run <= 20; $run++) {
            $athletes = [];
            for ($i = 1; $i <= 45; $i++) {
                $athletes[] = ['id' => $i, 'club_id' => $i <= 16 ? 1 : 2 + $i % 3];
            }
            $seeds = [];
            foreach ([1, 2, 3, 4, 17, 18, 19, 20] as $k => $id) {
                $seeds[$id] = $k + 1;
            }
            $slots = BracketService::draw($athletes, $seeds, 64, $run);
            $club = array_column($athletes, 'club_id', 'id');
            // Premier combat réel : paire du premier tour, ou adversaire de la paire voisine après un tour offert
            for ($p = 0; $p < 64; $p += 2) {
                [$a, $b] = [$slots[$p], $slots[$p + 1]];
                if ($a !== null && $b !== null) {
                    $this->assertNotSame($club[$a], $club[$b], "tirage $run : même club au premier tour (cases " . ($p + 1) . ')');
                }
            }
            // Exempté au premier tour : son premier adversaire sort de la paire voisine
            for ($p = 0; $p < 64; $p += 2) {
                [$a, $b] = [$slots[$p], $slots[$p + 1]];
                if (($a === null) === ($b === null)) {
                    continue;
                }
                $alone = $a ?? $b;
                $q = $p ^ 2;
                [$c, $d] = [$slots[$q], $slots[$q + 1]];
                $next = ($c === null) !== ($d === null) ? [$c ?? $d] : array_filter([$c, $d], fn ($v) => $v !== null);
                foreach ($next as $o) {
                    $this->assertNotSame($club[$alone], $club[$o], "tirage $run : même club au premier combat après exemption (case " . ($p + 1) . ')');
                }
            }
        }
    }
}
