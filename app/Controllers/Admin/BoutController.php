<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\BoutService;
use App\Services\CategoryService;
use App\Services\CompetitionService;
use App\Services\DomainError;
use App\Services\PoolService;

final class BoutController extends AdminController
{
    public function index(Request $r): Response
    {
        $c = $this->competition($r);
        $cid = (int) $c['id'];
        $f = array_intersect_key($r->query, array_flip(['tatami_id', 'age_category_id', 'sex', 'weight_category_id', 'pool_id', 'status', 'q']));
        $where = ['b.competition_id = :cid'];
        $p = ['cid' => $cid];
        foreach (['tatami_id' => 'b.tatami_id', 'age_category_id' => 'pl.age_category_id', 'weight_category_id' => 'pl.weight_category_id', 'pool_id' => 'b.pool_id'] as $k => $col) {
            if (!empty($f[$k])) {
                $where[] = "$col = :$k";
                $p[$k] = (int) $f[$k];
            }
        }
        if (!empty($f['sex']) && in_array($f['sex'], ['M', 'F'], true)) {
            $where[] = 'pl.sex = :sex';
            $p['sex'] = $f['sex'];
        }
        if (!empty($f['status']) && isset(BoutService::STATUS[$f['status']])) {
            $where[] = 'b.status = :st';
            $p['st'] = $f['status'];
        }
        if (!empty($f['q'])) {
            $where[] = '(CONCAT(aa.first_name, " ", aa.last_name) LIKE :q1 OR CONCAT(ab.first_name, " ", ab.last_name) LIKE :q2)';
            $p['q1'] = $p['q2'] = '%' . $f['q'] . '%';
        }
        [$page, $per, $offset] = $this->page($r, 60);
        $sqlFrom = 'FROM bouts b JOIN pools pl ON pl.id = b.pool_id
            JOIN registrations ra ON ra.id = b.a_registration_id JOIN athletes aa ON aa.id = ra.athlete_id
            JOIN registrations rb ON rb.id = b.b_registration_id JOIN athletes ab ON ab.id = rb.athlete_id
            LEFT JOIN tatamis t ON t.id = b.tatami_id LEFT JOIN users u ON u.id = b.validated_by
            WHERE ' . implode(' AND ', $where);
        $total = (int) Database::value("SELECT COUNT(*) $sqlFrom", $p);
        $rows = Database::all(
            "SELECT b.*, pl.label AS pool_label, pl.number AS pool_number, t.number AS tatami_number, u.display_name AS validator,
                    CONCAT(aa.first_name, ' ', aa.last_name) AS a_name, CONCAT(ab.first_name, ' ', ab.last_name) AS b_name
             $sqlFrom ORDER BY t.number, b.order_on_tatami LIMIT $per OFFSET $offset",
            $p
        );
        $g = PoolService::activeGeneration($cid);
        return $this->admin('bouts/index', [
            'title' => 'Combats et résultats', 'competition' => $c, 'c' => $c, 'rows' => $rows, 'f' => $f, 'total' => $total, 'page' => $page, 'pages' => (int) ceil($total / $per),
            'tatamis' => CompetitionService::tatamis($cid, false), 'ages' => CategoryService::ageCategories($cid),
            'weights' => CategoryService::weightCategories($cid), 'poolsList' => $g ? Database::all('SELECT id, number, label FROM pools WHERE generation_id = ? ORDER BY number', [$g['id']]) : [],
        ]);
    }

    private function bout(Request $r, array $c): array
    {
        $b = BoutService::find((int) $r->param('bid'));
        if ((int) $b['competition_id'] !== (int) $c['id']) {
            throw new HttpException(404, 'Combat introuvable.');
        }
        return $b;
    }

    public function show(Request $r): Response
    {
        $c = $this->competition($r);
        $b = $this->bout($r, $c);
        return $this->admin('bouts/show', [
            'title' => 'Combat n° ' . $b['id'], 'competition' => $c, 'c' => $c, 'state' => BoutService::state((int) $b['id']),
            'results' => Database::all('SELECT br.*, u.display_name FROM bout_results br LEFT JOIN users u ON u.id = br.recorded_by WHERE bout_id = ? ORDER BY revision DESC', [$b['id']]),
            'events' => Database::all('SELECT se.*, u.display_name FROM score_events se LEFT JOIN users u ON u.id = se.user_id WHERE bout_id = ? ORDER BY se.id DESC LIMIT 100', [$b['id']]),
        ]);
    }

    public function action(Request $r): Response
    {
        $c = $this->competition($r);
        $b = $this->bout($r, $c);
        switch ($r->str('op')) {
            case 'correct':
                BoutService::correct((int) $b['id'], [
                    'version' => $r->int('version'), 'reason' => $r->str('reason'), 'confirm' => $r->bool('confirm'),
                    'winner_side' => $r->str('winner_side'), 'method' => $r->str('method'), 'scores' => (array) ($r->post['scores'] ?? []),
                ]);
                return $this->back($r, 'Résultat corrigé ; classements recalculés et affichages mis à jour.');
            case 'cancel':
                BoutService::cancel((int) $b['id'], $r->str('reason'));
                return $this->back($r, 'Combat annulé.');
            case 'forfeit':
                $s = BoutService::state((int) $b['id']);
                BoutService::act((int) $b['id'], ['action' => 'forfeit', 'version' => $s['version'], 'winner_side' => $r->str('winner_side'), 'method' => $r->str('method')]);
                $s = BoutService::state((int) $b['id']);
                BoutService::act((int) $b['id'], ['action' => 'validate', 'version' => $s['version']]);
                return $this->back($r, 'Forfait enregistré et validé.');
        }
        throw new DomainError('Action inconnue.');
    }
}
