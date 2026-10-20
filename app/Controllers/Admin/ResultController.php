<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Services\CategoryService;
use App\Services\PoolService;
use App\Services\RankingService;

/** Résultats par catégorie : podium de chaque poule, avancement, égalités à départager. */
final class ResultController extends AdminController
{
    public function index(Request $r): Response
    {
        $c = $this->competition($r);
        $cid = (int) $c['id'];
        $g = PoolService::activeGeneration($cid);
        $pools = $g ? PoolService::pools((int) $g['id']) : [];
        $photos = [];
        foreach (\App\Core\Database::all('SELECT r.id, a.photo_document_id FROM registrations r JOIN athletes a ON a.id = r.athlete_id WHERE r.competition_id = ?', [$cid]) as $x) {
            $photos[(int) $x['id']] = $x['photo_document_id'] ? (int) $x['photo_document_id'] : null;
        }
        $filter = (string) ($r->query['etat'] ?? '');
        $groups = [];
        foreach ($pools as $p) {
            if ($filter === 'terminees' && !$p['ranking_complete']) {
                continue;
            }
            if ($filter === 'en-cours' && $p['ranking_complete']) {
                continue;
            }
            $label = CategoryService::label(['name' => $p['age_name'], 'name_m' => $p['name_m'], 'name_f' => $p['name_f']], $p['sex']);
            $groups[$label][] = $p;
        }
        return $this->admin('results', [
            'title' => 'Résultats', 'competition' => $c, 'c' => $c, 'groups' => $groups, 'photos' => $photos, 'filter' => $filter,
            'done' => count(array_filter($pools, fn ($p) => $p['ranking_complete'])), 'total' => count($pools),
            'totals' => RankingService::medalTotals($cid), 'tiebreaks' => count(array_filter($pools, fn ($p) => (int) $p['needs_tiebreak'] === 1)),
        ]);
    }
}
