<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Services\BoutService;
use App\Services\PoolService;
use App\Services\RankingService;
use App\Services\SettingsService;
use App\Services\TvTokenService;
use Tests\TestCase;

final class BoutRankingTest extends TestCase
{
    private int $cid;
    private int $g;

    private function setupPool(int $n = 3): array
    {
        $this->admin();
        $this->cid = $this->competition(['mat_count' => 1]);
        $clubs = [$this->club('A'), $this->club('B'), $this->club('C')];
        $this->athletes($this->cid, $n, 'M', 10, 28, $clubs);
        SettingsService::set($this->cid, 'preferred_pool_size', $n);
        $this->g = PoolService::generate($this->cid);
        PoolService::validate($this->g);
        return Database::column("SELECT id FROM bouts WHERE competition_id = ? ORDER BY order_on_tatami", [$this->cid]);
    }

    private function do(int $boutId, string $action, array $extra = []): array
    {
        $s = BoutService::state($boutId);
        return BoutService::act($boutId, ['action' => $action, 'version' => $s['version']] + $extra);
    }

    private function win(int $boutId, string $side, string $kind = 'ippon'): array
    {
        $this->do($boutId, 'start');
        $this->do($boutId, 'score', ['side' => $side, 'kind' => $kind, 'delta' => 1]);
        $s = BoutService::state($boutId);
        if ($s['status'] === 'in_progress') {
            $this->do($boutId, 'pause');
            $this->do($boutId, 'finish');
        }
        return $this->do($boutId, 'validate');
    }

    public function testClockIsServerSideAndSurvivesReload(): void
    {
        [$b] = $this->setupPool();
        $s = $this->do((int) $b, 'start');
        $this->assertTrue($s['clock']['running']);
        Database::run('UPDATE bouts SET clock_started_at_ms = clock_started_at_ms - 5000 WHERE id = ?', [$b]);
        $s = $this->do((int) $b, 'pause');
        $this->assertFalse($s['clock']['running']);
        $this->assertGreaterThanOrEqual(5000, $s['clock']['elapsed_ms']);
        $reloaded = BoutService::state((int) $b);
        $this->assertSame($s['clock']['elapsed_ms'], $reloaded['clock']['elapsed_ms'], 'Un rechargement ne réinitialise pas le temps');
    }

    public function testTwoWazaariWinAndScoreStaysDisplayedAs020(): void
    {
        [$b] = $this->setupPool();
        $this->do((int) $b, 'start');
        $this->do((int) $b, 'score', ['side' => 'b', 'kind' => 'wazaari', 'delta' => 1]);
        $s = $this->do((int) $b, 'score', ['side' => 'b', 'kind' => 'wazaari', 'delta' => 1]);
        $this->assertSame('finished', $s['status']);
        $this->assertSame('b', $s['winner_side']);
        $this->assertSame([0, 2, 0], [$s['b']['ippon'], $s['b']['wazaari'], $s['b']['yuko']]);
        $s = $this->do((int) $b, 'score', ['side' => 'b', 'kind' => 'wazaari', 'delta' => -1]);
        $this->assertSame('in_progress', $s['status'], 'Annuler le 2e waza-ari annule la victoire');
    }

    public function testThirdShidoGivesHansokuMakeToOpponent(): void
    {
        [$b] = $this->setupPool();
        $this->do((int) $b, 'start');
        for ($i = 0; $i < 3; $i++) {
            $s = $this->do((int) $b, 'score', ['side' => 'a', 'kind' => 'shido', 'delta' => 1]);
        }
        $this->assertSame('finished', $s['status']);
        $this->assertSame('b', $s['winner_side']);
        $this->assertSame('hansoku_make', $s['win_method']);
    }

    public function testOsaekomiThresholdsAwardScores(): void
    {
        [$b] = $this->setupPool();
        $this->do((int) $b, 'start');
        $this->do((int) $b, 'osaekomi', ['side' => 'a']);
        $s = $this->do((int) $b, 'toketa', ['held_ms' => 0]);
        Database::run('UPDATE bouts SET osaekomi_started_at_ms = NULL WHERE id = ?', [$b]);
        $this->assertSame(0, $s['a']['yuko'], 'Moins de 5 s : rien');
        $this->do((int) $b, 'osaekomi', ['side' => 'a']);
        Database::run('UPDATE bouts SET osaekomi_started_at_ms = osaekomi_started_at_ms - 6000 WHERE id = ?', [$b]);
        $s = $this->do((int) $b, 'toketa');
        $this->assertSame(1, $s['a']['yuko'], '5 s : yuko');
        $this->do((int) $b, 'osaekomi', ['side' => 'a']);
        Database::run('UPDATE bouts SET osaekomi_started_at_ms = osaekomi_started_at_ms - 11000 WHERE id = ?', [$b]);
        $s = $this->do((int) $b, 'toketa');
        $this->assertSame(1, $s['a']['wazaari'], '10 s : waza-ari');
        $this->do((int) $b, 'osaekomi', ['side' => 'a']);
        Database::run('UPDATE bouts SET osaekomi_started_at_ms = osaekomi_started_at_ms - 21000 WHERE id = ?', [$b]);
        $s = $this->do((int) $b, 'toketa');
        $this->assertSame('finished', $s['status'], '20 s : ippon (ou 2e waza-ari)');
        $this->assertSame('a', $s['winner_side']);
    }

    public function testTieNeedsDecisionOrGoldenScore(): void
    {
        [$b] = $this->setupPool();
        $this->do((int) $b, 'start');
        $this->do((int) $b, 'pause');
        try {
            $this->do((int) $b, 'finish');
            $this->fail('Décision attendue');
        } catch (HttpException $e) {
            $this->assertTrue($e->data['needs_decision'] ?? false);
        }
        $this->assertDomainError(fn () => $this->do((int) $b, 'golden_score'), "n'est pas écoulé");
        Database::run('UPDATE bouts SET clock_elapsed_ms = duration_seconds * 1000 WHERE id = ?', [$b]);
        $s = $this->do((int) $b, 'golden_score');
        $this->assertTrue($s['clock']['golden_score']);
        $this->do((int) $b, 'start');
        $s = $this->do((int) $b, 'score', ['side' => 'a', 'kind' => 'yuko', 'delta' => 1]);
        $this->assertSame('finished', $s['status'], 'Golden score : premier avantage gagnant');
        $this->assertSame('yuko', $s['win_method']);
    }

    public function testValidationIsIdempotentAndUpdatesRankingsAndTv(): void
    {
        $bouts = $this->setupPool(3);
        $s = $this->win((int) $bouts[0], 'a');
        $this->assertSame('validated', $s['status']);
        $again = BoutService::act((int) $bouts[0], ['action' => 'validate', 'version' => $s['version']]);
        $this->assertTrue($again['duplicate'] ?? false, 'Résultat soumis deux fois : sans effet');
        $this->assertSame(1, (int) Database::value('SELECT COUNT(*) FROM bout_results WHERE bout_id = ?', [$bouts[0]]));
        // Poule incomplète : aucune médaille
        $this->assertSame(0, (int) Database::value('SELECT COUNT(*) FROM athlete_rankings WHERE competition_id = ?', [$this->cid]));
        $this->win((int) $bouts[1], 'a');
        $this->win((int) $bouts[2], 'b', 'wazaari');
        $pool = Database::one('SELECT * FROM pools WHERE competition_id = ?', [$this->cid]);
        $this->assertSame('finished', $pool['status']);
        $ranks = Database::all('SELECT place, points, medal FROM athlete_rankings WHERE competition_id = ? ORDER BY place', [$this->cid]);
        $this->assertCount(3, $ranks);
        $this->assertSame(['gold', 'silver', 'bronze'], array_column($ranks, 'medal'));
        $this->assertSame([10, 7, 5], array_map('intval', array_column($ranks, 'points')));
        $clubs = RankingService::clubTable($this->cid, 'points');
        $this->assertSame(22, array_sum(array_column($clubs, 'points')));
        $this->assertSame(1, (int) $clubs[0]['rank_points']);
        // TV : résultat puis combat suivant (aucun) après le délai d'affichage
        $t = Database::one('SELECT * FROM tatamis WHERE competition_id = ?', [$this->cid]);
        $this->assertSame('result', TvTokenService::tvState($t)['mode']);
        Database::run('UPDATE bouts SET validated_at = NOW() - INTERVAL 1 HOUR WHERE id = ?', [$t['current_bout_id']]);
        $this->assertSame('waiting', TvTokenService::tvState($t)['mode']);
    }

    public function testDuplicateEventIsNotAppliedTwiceAfterConnectionLoss(): void
    {
        [$b] = $this->setupPool();
        $this->do((int) $b, 'start');
        $s = BoutService::state((int) $b);
        $payload = ['action' => 'score', 'side' => 'a', 'kind' => 'wazaari', 'delta' => 1, 'version' => $s['version'], 'client_event_id' => 'evt-123'];
        BoutService::act((int) $b, $payload);
        $retry = BoutService::act((int) $b, $payload); // même identifiant, ancienne version : renvoi après coupure
        $this->assertTrue($retry['duplicate']);
        $this->assertSame(1, $retry['a']['wazaari']);
    }

    public function testConcurrentCommissionersGetAConflict(): void
    {
        [$b] = $this->setupPool();
        $s = $this->do((int) $b, 'start');
        $stale = $s['version'];
        BoutService::act((int) $b, ['action' => 'score', 'side' => 'a', 'kind' => 'yuko', 'delta' => 1, 'version' => $stale]);
        try {
            BoutService::act((int) $b, ['action' => 'score', 'side' => 'b', 'kind' => 'yuko', 'delta' => 1, 'version' => $stale]);
            $this->fail('Conflit attendu');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->status);
            $this->assertSame(1, $e->data['state']['a']['yuko'], 'L\'état à jour est renvoyé');
        }
    }

    public function testCorrectionRequiresReasonKeepsHistoryAndRecomputes(): void
    {
        $bouts = $this->setupPool(3);
        foreach ($bouts as $b) {
            $this->win((int) $b, 'a');
        }
        $first = BoutService::state((int) $bouts[0]);
        $this->assertDomainError(fn () => BoutService::correct((int) $bouts[0], ['version' => $first['version'], 'confirm' => 1, 'reason' => '', 'winner_side' => 'b', 'method' => 'ippon']), 'motif');
        $this->assertDomainError(fn () => BoutService::correct((int) $bouts[0], ['version' => $first['version'], 'reason' => 'Erreur de table', 'winner_side' => 'b', 'method' => 'ippon']), 'Confirmez');
        $before = Database::all('SELECT registration_id, place FROM athlete_rankings WHERE competition_id = ? ORDER BY registration_id', [$this->cid]);
        BoutService::correct((int) $bouts[0], ['version' => $first['version'], 'confirm' => 1, 'reason' => 'Erreur de table centrale', 'winner_side' => 'b', 'method' => 'ippon', 'scores' => ['a' => ['ippon' => 0], 'b' => ['ippon' => 1]]]);
        $this->assertSame(2, (int) Database::value('SELECT COUNT(*) FROM bout_results WHERE bout_id = ?', [$bouts[0]]));
        $this->assertSame('correction', Database::value('SELECT kind FROM bout_results WHERE bout_id = ? AND is_current = 1', [$bouts[0]]));
        $log = Database::one("SELECT * FROM audit_logs WHERE action = 'bout.correct' ORDER BY id DESC LIMIT 1");
        $this->assertSame('Erreur de table centrale', $log['reason']);
        $this->assertStringContainsString('"winner_side":"a"', $log['old_values']);
        $after = Database::all('SELECT registration_id, place FROM athlete_rankings WHERE competition_id = ? ORDER BY registration_id', [$this->cid]);
        $this->assertNotSame($before, $after, 'Classements recalculés');
    }

    public function testCommissionerCannotCorrectAfterCompetitionFinished(): void
    {
        $bouts = $this->setupPool(3);
        $this->win((int) $bouts[0], 'a');
        $t = (int) Database::value('SELECT tatami_id FROM bouts WHERE id = ?', [$bouts[0]]);
        $u = $this->user('commissioner');
        Database::insert('commissioner_tatamis', ['user_id' => $u, 'tatami_id' => $t, 'is_active' => 1]);
        Database::run("UPDATE competitions SET status = 'finished' WHERE id = ?", [$this->cid]);
        Auth::actAs($u);
        $s = BoutService::state((int) $bouts[0]);
        $this->assertDomainError(fn () => BoutService::correct((int) $bouts[0], ['version' => $s['version'], 'confirm' => 1, 'reason' => 'Correction tardive', 'winner_side' => 'b', 'method' => 'ippon']), null, 403);
    }

    public function testUnresolvedTieIsFlaggedThenManuallyBroken(): void
    {
        $bouts = $this->setupPool(3);
        // Cycle : A bat B, B bat C, C bat A, même type de victoire → égalité parfaite.
        // Ordre de la poule de 3 : 1-2, 2-3, 1-3 → 1 bat 2, 2 bat 3, 3 bat 1.
        $this->win((int) $bouts[0], 'a');
        $this->win((int) $bouts[1], 'a');
        $this->win((int) $bouts[2], 'b');
        $pool = Database::one('SELECT * FROM pools WHERE competition_id = ?', [$this->cid]);
        $this->assertSame(1, (int) $pool['needs_tiebreak']);
        $this->assertSame(0, (int) Database::value('SELECT COUNT(*) FROM athlete_rankings WHERE pool_id = ?', [$pool['id']]), 'Pas de médaille sur égalité non tranchée');
        $members = Database::column('SELECT registration_id FROM pool_athletes WHERE pool_id = ? ORDER BY seed', [$pool['id']]);
        RankingService::setManualRanks((int) $pool['id'], [$members[0] => 1, $members[1] => 2, $members[2] => 3], 'Décision de la table');
        $this->assertSame(3, (int) Database::value('SELECT COUNT(*) FROM athlete_rankings WHERE pool_id = ?', [$pool['id']]));
    }
}
