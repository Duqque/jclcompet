<?php
declare(strict_types=1);

namespace App\Controllers\Commissioner;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
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

    /** Statuts où l'on peut saisir des combats (jour J). */
    private const DAY_STATUSES = ['registrations_closed', 'attendance', 'pool_preparation', 'in_progress'];

    /** Compétitions du jour J qui ont au moins un tapis actif. */
    private function competitions(): array
    {
        $in = implode(',', array_fill(0, count(self::DAY_STATUSES), '?'));
        return Database::all(
            "SELECT c.id, c.name, c.status, c.event_date, c.city,
                    (SELECT COUNT(*) FROM tatamis t WHERE t.competition_id = c.id AND t.is_active = 1) AS mats,
                    (SELECT COUNT(*) FROM bouts b WHERE b.competition_id = c.id AND b.status IN ('scheduled','in_progress','finished')) AS remaining,
                    (SELECT COUNT(*) FROM commissioner_tatamis ct JOIN tatamis t ON t.id = ct.tatami_id WHERE t.competition_id = c.id AND ct.user_id = ?) AS mine
             FROM competitions c
             WHERE c.status IN ($in) AND EXISTS (SELECT 1 FROM tatamis t WHERE t.competition_id = c.id AND t.is_active = 1)
             ORDER BY c.status = 'in_progress' DESC, c.event_date, c.name",
            array_merge([Auth::id()], self::DAY_STATUSES)
        );
    }

    /** Tapis d'une compétition, avec l'état « affecté / actif » du commissaire et les autres commissaires présents. */
    private function tatamisOf(int $competitionId): array
    {
        $rows = Database::all(
            "SELECT t.*, COALESCE(ct.is_active, 0) AS is_active_for_me, ct.user_id IS NOT NULL AS is_mine,
                    (SELECT GROUP_CONCAT(u.display_name SEPARATOR ', ')
                       FROM commissioner_tatamis o JOIN users u ON u.id = o.user_id
                      WHERE o.tatami_id = t.id AND o.is_active = 1 AND o.user_id <> ?) AS others
             FROM tatamis t LEFT JOIN commissioner_tatamis ct ON ct.tatami_id = t.id AND ct.user_id = ?
             WHERE t.competition_id = ? AND t.is_active = 1 ORDER BY t.number",
            [Auth::id(), Auth::id(), $competitionId]
        );
        foreach ($rows as &$t) {
            $t['remaining'] = (int) Database::value("SELECT COUNT(*) FROM bouts WHERE tatami_id = ? AND status IN ('scheduled','in_progress','finished')", [$t['id']]);
            $t['done'] = (int) Database::value("SELECT COUNT(*) FROM bouts WHERE tatami_id = ? AND status = 'validated'", [$t['id']]);
        }
        return $rows;
    }

    /**
     * Paramètres : choix de la compétition (mémorisé pour la session), puis du tapis.
     * ?competition=0 revient au choix de la compétition.
     */
    public function home(Request $r): Response
    {
        $this->guard();
        $competitions = $this->competitions();
        $ids = array_map(fn ($c) => (int) $c['id'], $competitions);
        if (array_key_exists('competition', $r->query)) {
            Session::put('commissioner_competition', (int) $r->query['competition']);
        }
        $chosen = (int) Session::get('commissioner_competition', 0);
        if (!in_array($chosen, $ids, true)) {
            $chosen = 0;
            // Une seule compétition possible : choisie d'office (sauf retour explicite au choix)
            if (count($ids) === 1 && !array_key_exists('competition', $r->query)) {
                $chosen = $ids[0];
            }
        }
        $competition = null;
        foreach ($competitions as $c) {
            if ((int) $c['id'] === $chosen) {
                $competition = $c;
            }
        }
        return $this->view('commissioner/home', [
            'title' => 'Commissaire sportif', 'competitions' => $competitions, 'competition' => $competition,
            'tatamis' => $competition ? $this->tatamisOf((int) $competition['id']) : [],
        ], 'commissioner');
    }

    public function activate(Request $r): Response
    {
        $this->guard();
        $tid = (int) $r->param('tid');
        $tatami = Database::one('SELECT t.*, c.status AS competition_status FROM tatamis t JOIN competitions c ON c.id = t.competition_id WHERE t.id = ?', [$tid]);
        if (!$tatami) {
            throw new HttpException(404, 'Tapis introuvable.');
        }
        if (!Auth::can('results.correct')) {
            if (!Auth::can('bouts.score')) {
                throw new HttpException(403);
            }
            $row = Database::one('SELECT * FROM commissioner_tatamis WHERE user_id = ? AND tatami_id = ?', [Auth::id(), $tid]);
            if (!$row) {
                // Le commissaire choisit lui-même son tapis, uniquement sur une compétition du jour J et un tapis en service
                if (!in_array($tatami['competition_status'], self::DAY_STATUSES, true) || (int) $tatami['is_active'] !== 1) {
                    throw new HttpException(403, "Ce tapis n'est pas ouvert à la saisie.");
                }
                Database::insert('commissioner_tatamis', ['user_id' => Auth::id(), 'tatami_id' => $tid, 'is_active' => 0]);
                AuditLogger::log('commissioner.self_assign', 'tatami', $tid, null, null, null, (int) $tatami['competition_id']);
            }
            // Un seul tapis actif à la fois dans l'interface de saisie (par compétition).
            Database::run('UPDATE commissioner_tatamis ct JOIN tatamis t ON t.id = ct.tatami_id SET ct.is_active = (ct.tatami_id = ?) WHERE ct.user_id = ? AND t.competition_id = ?', [$tid, Auth::id(), $tatami['competition_id']]);
            AuditLogger::log('commissioner.activate', 'tatami', $tid, null, null, null, (int) $tatami['competition_id']);
        }
        Session::put('commissioner_competition', (int) $tatami['competition_id']);
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
            'q' => $q = trim((string) ($r->query['q'] ?? '')),
            'pools' => $g ? PoolService::sheets(PoolService::matching(PoolService::pools((int) $g['id'], (int) $t['id']), $q)) : [],
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
