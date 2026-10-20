<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLogger;
use App\Services\CompetitionService;
use App\Services\DomainError;
use App\Services\PoolService;
use App\Services\RankingService;

final class RankingController extends AdminController
{
    public function index(Request $r): Response
    {
        $c = $this->competition($r);
        $cid = (int) $c['id'];
        $g = PoolService::activeGeneration($cid);
        $pools = $g ? PoolService::pools((int) $g['id']) : [];
        $byId = array_column($pools, null, 'id');
        $tiebreaks = [];
        foreach ($pools as $p) {
            if ((int) $p['needs_tiebreak'] === 1) {
                $p['calc'] = RankingService::computePool((int) $p['id']);
                $p['members'] = array_column($byId[$p['id']]['athletes'], null, 'registration_id');
                $tiebreaks[] = $p;
            }
        }
        return $this->admin('rankings', [
            'title' => 'Classements', 'competition' => $c, 'c' => $c,
            'byPoints' => RankingService::clubTable($cid, 'points'), 'byMedals' => RankingService::clubTable($cid, 'medals'),
            'individual' => RankingService::individual($cid), 'tiebreaks' => $tiebreaks, 'pools' => $pools,
            'incomplete' => count(array_filter($pools, fn ($p) => !$p['ranking_complete'])), 'lastUpdate' => RankingService::lastUpdate($cid),
        ]);
    }

    public function action(Request $r): Response
    {
        $c = $this->competition($r);
        CompetitionService::assertWritable($c);
        $cid = (int) $c['id'];
        switch ($r->str('op')) {
            case 'recompute':
                RankingService::recomputeCompetition($cid);
                return $this->back($r, 'Classements recalculés depuis les résultats validés.');
            case 'tiebreak':
                $poolId = (int) $r->int('pool_id');
                $reason = $r->str('reason');
                if (mb_strlen($reason) < 5) {
                    throw new DomainError('Indiquez le motif du départage (5 caractères minimum).');
                }
                RankingService::setManualRanks($poolId, (array) ($r->post['rank'] ?? []), $reason);
                return $this->back($r, 'Départage enregistré.');
            case 'barrage':
                PoolService::addTiebreakBout((int) $r->int('pool_id'), (int) $r->int('a'), (int) $r->int('b'));
                return $this->back($r, 'Combat de barrage ajouté en fin de file du tapis de la poule.');
            case 'exclude':
            case 'include':
                $reason = $r->str('reason');
                if (mb_strlen($reason) < 5) {
                    throw new DomainError('Indiquez le motif (5 caractères minimum).');
                }
                RankingService::setExcluded((int) $r->int('pool_id'), (int) $r->int('registration_id'), $r->str('op') === 'exclude', $reason);
                return $this->back($r, $r->str('op') === 'exclude' ? 'Résultat exclu du classement.' : 'Résultat réintégré.');
        }
        throw new DomainError('Action inconnue.');
    }

    public function rules(Request $r): Response
    {
        $c = $this->competition($r);
        return $this->admin('ranking_rules', ['title' => 'Barème de points', 'competition' => $c, 'c' => $c, 'rules' => RankingService::rules((int) $c['id'])]);
    }

    public function saveRules(Request $r): Response
    {
        $c = $this->competition($r);
        CompetitionService::assertWritable($c);
        $cid = (int) $c['id'];
        $old = RankingService::rules($cid);
        Database::transaction(function () use ($r, $cid) {
            Database::run('DELETE FROM ranking_rules WHERE competition_id = ?', [$cid]);
            foreach ((array) ($r->post['rules'] ?? []) as $row) {
                $place = (int) ($row['place'] ?? 0);
                if ($place < 1 || $place > 50 || ($row['points'] ?? '') === '' && empty($row['medal'])) {
                    continue;
                }
                Database::run(
                    'INSERT INTO ranking_rules (competition_id, place, points, medal, counts_in_table) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE points = VALUES(points), medal = VALUES(medal)',
                    [$cid, $place, max(0, (int) $row['points']), in_array($row['medal'] ?? '', ['gold', 'silver', 'bronze'], true) ? $row['medal'] : null, empty($row['excluded']) ? 1 : 0]
                );
            }
            RankingService::recomputeCompetition($cid);
        });
        AuditLogger::log('rankings.rules', 'competition', $cid, array_values($old), array_values(RankingService::rules($cid)), null, $cid);
        return $this->back($r, 'Barème enregistré et classements recalculés.');
    }
}
