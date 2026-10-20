<?php
declare(strict_types=1);

namespace App\Controllers\Commissioner;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLogger;
use App\Services\BoutService;
use App\Services\CompetitionService;
use App\Services\DomainError;
use App\Services\PoolService;
use App\Services\SettingsService;

/**
 * Espace commissaire sportif : paramètres (choix du tapis actif), saisie des combats, historique et corrections.
 * Les droits sont vérifiés par BoutService sur chaque action (tapis affecté et actif, ou administrateur).
 */
final class CommissionerController extends Controller
{
    private function guard(): void
    {
        if (!Auth::can('bouts.score') && !Auth::can('results.correct')) {
            throw new HttpException(403);
        }
    }

    /** Tapis accessibles : tapis affectés (commissaire) ou tous les tapis des compétitions en cours (administrateur). */
    private function myTatamis(): array
    {
        if (Auth::can('results.correct')) {
            return Database::all(
                "SELECT t.*, c.name AS competition_name, c.status AS competition_status, 1 AS is_active_for_me
                 FROM tatamis t JOIN competitions c ON c.id = t.competition_id
                 WHERE t.is_active = 1 AND c.status IN ('pool_preparation','in_progress','attendance','registrations_closed')
                 ORDER BY c.event_date, t.number"
            );
        }
        return Database::all(
            "SELECT t.*, c.name AS competition_name, c.status AS competition_status, ct.is_active AS is_active_for_me
             FROM commissioner_tatamis ct JOIN tatamis t ON t.id = ct.tatami_id JOIN competitions c ON c.id = t.competition_id
             WHERE ct.user_id = ? AND c.status <> 'archived' ORDER BY c.event_date, t.number",
            [Auth::id()]
        );
    }

    public function home(Request $r): Response
    {
        $this->guard();
        $tatamis = $this->myTatamis();
        foreach ($tatamis as &$t) {
            $t['remaining'] = (int) Database::value("SELECT COUNT(*) FROM bouts WHERE tatami_id = ? AND status IN ('scheduled','in_progress','finished')", [$t['id']]);
            $t['done'] = (int) Database::value("SELECT COUNT(*) FROM bouts WHERE tatami_id = ? AND status = 'validated'", [$t['id']]);
        }
        unset($t);
        return $this->view('commissioner/home', ['title' => 'Commissaire sportif', 'tatamis' => $tatamis], 'commissioner');
    }

    public function activate(Request $r): Response
    {
        $this->guard();
        $tid = (int) $r->param('tid');
        if (!Auth::can('results.correct')) {
            $row = Database::one('SELECT ct.*, t.competition_id FROM commissioner_tatamis ct JOIN tatamis t ON t.id = ct.tatami_id WHERE ct.user_id = ? AND ct.tatami_id = ?', [Auth::id(), $tid]);
            if (!$row) {
                throw new HttpException(403, "Ce tapis ne vous est pas affecté.");
            }
            // Un seul tapis actif à la fois dans l'interface de saisie (par compétition).
            Database::run('UPDATE commissioner_tatamis ct JOIN tatamis t ON t.id = ct.tatami_id SET ct.is_active = (ct.tatami_id = ?) WHERE ct.user_id = ? AND t.competition_id = ?', [$tid, Auth::id(), $row['competition_id']]);
            AuditLogger::log('commissioner.activate', 'tatami', $tid, null, null, null, (int) $row['competition_id']);
        }
        return $this->redirect('/commissaire/tapis/' . $tid);
    }

    public function scoring(Request $r): Response
    {
        $this->guard();
        $t = BoutService::assertCanUseTatami((int) $r->param('tid'));
        $c = CompetitionService::find((int) $t['competition_id']);
        $cid = (int) $c['id'];
        return $this->view('commissioner/scoring', [
            'title' => 'Tapis ' . $t['number'], 't' => $t, 'c' => $c, 'fullApp' => true, 'scripts' => ['commissioner.js'],
            'config' => [
                'tatamiId' => (int) $t['id'], 'tatamiNumber' => (int) $t['number'],
                'stateUrl' => url('/api/commissaire/tapis/' . $t['id']), 'selectUrl' => url('/api/commissaire/tapis/' . $t['id'] . '/selection'),
                'actionUrl' => url('/api/combats/__ID__/action'), 'homeUrl' => url('/commissaire'), 'historyUrl' => url('/commissaire/tapis/' . $t['id'] . '/historique'),
                'correctUrl' => url('/commissaire/combats/__ID__/corriger'),
                'model' => SettingsService::get($cid, 'score_model'), 'color' => SettingsService::get($cid, 'fighter_color'),
                'methods' => BoutService::METHODS,
            ],
        ], 'commissioner');
    }

    /** État complet du tapis pour l'interface (combat courant, tous les combats restants, derniers résultats). */
    public function tatamiState(Request $r): Response
    {
        $this->guard();
        $t = BoutService::assertCanUseTatami((int) $r->param('tid'));
        $current = $t['current_bout_id'] ? BoutService::state((int) $t['current_bout_id']) : null;
        $remaining = Database::all(
            "SELECT id FROM bouts WHERE tatami_id = ? AND status IN ('scheduled','in_progress','finished') ORDER BY order_on_tatami, id",
            [$t['id']]
        );
        return $this->json([
            'tatami' => ['id' => (int) $t['id'], 'number' => (int) $t['number'], 'current_bout_id' => $t['current_bout_id'] ? (int) $t['current_bout_id'] : null],
            'current' => $current,
            'remaining' => array_map(fn ($b) => BoutService::state((int) $b['id'], false), $remaining),
            'done_count' => (int) Database::value("SELECT COUNT(*) FROM bouts WHERE tatami_id = ? AND status = 'validated'", [$t['id']]),
            'server_now_ms' => BoutService::nowMs(),
        ]);
    }

    public function select(Request $r): Response
    {
        $this->guard();
        $t = BoutService::assertCanUseTatami((int) $r->param('tid'));
        $boutId = $r->int('bout_id');
        return $this->json(['ok' => true, 'current' => BoutService::selectCurrent((int) $t['id'], $boutId)]);
    }

    public function act(Request $r): Response
    {
        $this->guard();
        return $this->json(['ok' => true, 'state' => BoutService::act((int) $r->param('bid'), $r->json())]);
    }

    public function correctApi(Request $r): Response
    {
        $this->guard();
        return $this->json(['ok' => true, 'state' => BoutService::correct((int) $r->param('bid'), $r->json())]);
    }

    public function history(Request $r): Response
    {
        $this->guard();
        $t = BoutService::assertCanUseTatami((int) $r->param('tid'));
        $c = CompetitionService::find((int) $t['competition_id']);
        $g = PoolService::activeGeneration((int) $c['id']);
        return $this->view('commissioner/history', [
            'title' => 'Historique du tapis ' . $t['number'], 't' => $t, 'c' => $c,
            'bouts' => BoutService::recent((int) $t['id'], 200),
            'pools' => $g ? PoolService::pools((int) $g['id'], (int) $t['id']) : [],
        ], 'commissioner');
    }

    public function correctForm(Request $r): Response
    {
        $this->guard();
        $b = BoutService::find((int) $r->param('bid'));
        BoutService::assertCanUseTatami((int) $b['tatami_id']);
        return $this->view('commissioner/correct', ['title' => 'Corriger un résultat', 's' => BoutService::state((int) $b['id'])], 'commissioner');
    }

    public function correct(Request $r): Response
    {
        $this->guard();
        $b = BoutService::find((int) $r->param('bid'));
        BoutService::correct((int) $b['id'], [
            'version' => $r->int('version'), 'reason' => $r->str('reason'), 'confirm' => $r->bool('confirm'),
            'winner_side' => $r->str('winner_side'), 'method' => $r->str('method'), 'scores' => (array) ($r->post['scores'] ?? []),
        ]);
        return $this->redirect('/commissaire/tapis/' . $b['tatami_id'] . '/historique', 'Résultat corrigé ; classements et écran TV mis à jour.');
    }
}
