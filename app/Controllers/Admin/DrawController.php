<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLogger;
use App\Services\BracketService;
use App\Services\DomainError;
use App\Services\PoolService;

/**
 * Tirage au sort en direct (écran du PC de l'administrateur, projeté) : choix de la catégorie, liste des combattants
 * (têtes de série d'abord, puis ordre alphabétique), tirage animé dans le tableau, validation, catégorie suivante.
 */
final class DrawController extends AdminController
{
    public function index(Request $r): Response
    {
        $c = $this->competition($r);
        $g = PoolService::activeGeneration((int) $c['id']);
        return $this->view('admin/draw', ['title' => 'Tirage au sort · ' . $c['name'], 'c' => $c, 'generation' => $g,
            'categories' => $g ? $this->categories((int) $g['id']) : [], 'scripts' => ['draw.js']], 'attendance');
    }

    private function categories(int $generationId): array
    {
        return array_map(fn ($p) => [
            'id' => (int) $p['id'], 'label' => preg_replace('/ · Tableau$/u', '', $p['label']), 'count' => (int) $p['n'], 'size' => (int) $p['bracket_size'],
            'state' => (int) $p['started'] > 0 ? 'started' : ((int) $p['is_locked'] === 1 ? 'validated' : 'todo'),
        ], Database::all(
            "SELECT p.id, p.label, p.is_locked, p.bracket_size, (SELECT COUNT(*) FROM pool_athletes pa WHERE pa.pool_id = p.id) AS n,
                    (SELECT COUNT(*) FROM bouts b WHERE b.pool_id = p.id AND b.status NOT IN ('scheduled','cancelled')) AS started
             FROM pools p JOIN age_categories ac ON ac.id = p.age_category_id LEFT JOIN weight_categories wc ON wc.id = p.weight_category_id
             WHERE p.generation_id = ? AND p.kind = 'bracket' AND p.bracket_size >= 2
             ORDER BY ac.sort_order, p.sex, wc.sort_order, p.number",
            [$generationId]
        ));
    }

    private function pool(Request $r, array $c): array
    {
        $pool = PoolService::pool((int) $r->param('pid'));
        if ((int) $pool['competition_id'] !== (int) $c['id'] || $pool['kind'] !== 'bracket') {
            throw new HttpException(404, 'Tableau introuvable.');
        }
        return $pool;
    }

    /** Combattants de la catégorie : têtes de série dans l'ordre, puis ordre alphabétique du nom. */
    private function athletes(array $pool): array
    {
        $seeds = BracketService::seedsFor((int) $pool['competition_id'], $pool['group_key']);
        $people = BracketService::people(array_map('intval', Database::column('SELECT registration_id FROM pool_athletes WHERE pool_id = ?', [$pool['id']])));
        $list = [];
        foreach ($people as $id => $p) {
            $list[] = $p + ['seed' => $seeds[$id] ?? null];
        }
        usort($list, fn ($a, $b) => [$a['seed'] ?? 99, $a['last_name'], $a['first_name']] <=> [$b['seed'] ?? 99, $b['last_name'], $b['first_name']]);
        return $list;
    }

    public function show(Request $r): Response
    {
        $c = $this->competition($r);
        $pool = $this->pool($r, $c);
        return $this->json(['id' => (int) $pool['id'], 'label' => preg_replace('/ · Tableau$/u', '', $pool['label']), 'size' => (int) $pool['bracket_size'],
            'locked' => (int) $pool['is_locked'] === 1, 'played' => count($this->played($pool)), 'athletes' => $this->athletes($pool)]);
    }

    /** Combats déjà disputés du tableau. */
    private function played(array $pool): array
    {
        return Database::all("SELECT a_registration_id, b_registration_id FROM bouts WHERE pool_id = ? AND status IN ('finished','validated')", [$pool['id']]);
    }

    public function seeds(Request $r): Response
    {
        $c = $this->competition($r);
        $pool = $this->pool($r, $c);
        BracketService::saveSeeds((int) $pool['id'], (array) $r->input('seeds', []));
        return $this->json(['ok' => true, 'athletes' => $this->athletes(PoolService::pool((int) $pool['id']))]);
    }

    /**
     * Lance le tirage : enregistré tout de suite ; l'écran l'anime ensuite (têtes de série d'abord).
     * mode « reset » ou « keep » pour refaire un tirage validé ou commencé (garder ou non les combats disputés).
     */
    public function run(Request $r): Response
    {
        $c = $this->competition($r);
        $pool = $this->pool($r, $c);
        $mode = $r->input('mode');
        $mode = in_array($mode, ['reset', 'keep'], true) ? $mode : null;
        $kept = [];
        if ($mode === 'keep') {
            foreach ($this->played($pool) as $b) {
                $kept[] = (int) $b['a_registration_id'];
                $kept[] = (int) $b['b_registration_id'];
            }
        }
        BracketService::redraw((int) $pool['id'], BracketService::seedsFor((int) $c['id'], $pool['group_key']), $mode);
        $slots = [];
        foreach (Database::all('SELECT registration_id, seed, draw_seed FROM pool_athletes WHERE pool_id = ?', [$pool['id']]) as $pa) {
            $slots[] = ['slot' => (int) $pa['seed'] - 1, 'registration_id' => (int) $pa['registration_id'], 'seed' => $pa['draw_seed'] === null ? null : (int) $pa['draw_seed']];
        }
        return $this->json(['ok' => true, 'size' => (int) $pool['bracket_size'], 'slots' => $slots, 'kept' => array_values(array_unique($kept))]);
    }

    public function confirm(Request $r): Response
    {
        $c = $this->competition($r);
        $pool = $this->pool($r, $c);
        if (Database::value("SELECT 1 FROM bouts WHERE pool_id = ? AND status = 'in_progress' LIMIT 1", [$pool['id']])) {
            throw new DomainError('Un combat de ce tableau est en cours.');
        }
        Database::run('UPDATE pools SET is_locked = 1 WHERE id = ?', [$pool['id']]);
        AuditLogger::log('bracket.draw_validated', 'pool', (int) $pool['id'], null, null, null, (int) $c['id']);
        return $this->json(['ok' => true, 'message' => 'Tirage validé : ' . preg_replace('/ · Tableau$/u', '', $pool['label']) . '.']);
    }
}
