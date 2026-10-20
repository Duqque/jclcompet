<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\CompetitionService;
use App\Services\DomainError;
use App\Services\PoolService;
use App\Services\SettingsService;

final class PoolController extends AdminController
{
    public function index(Request $r): Response
    {
        $c = $this->competition($r);
        $cid = (int) $c['id'];
        $g = PoolService::activeGeneration($cid);
        $pools = $g ? PoolService::pools((int) $g['id']) : [];
        $q = trim((string) ($r->query['q'] ?? ''));
        $vue = in_array($r->query['vue'] ?? '', ['feuilles', 'categories', 'liste', 'tapis'], true) ? $r->query['vue'] : 'feuilles';
        $shown = PoolService::matching($pools, $q);
        if ($vue === 'feuilles') {
            $shown = PoolService::sheets($shown);
        }
        $byTatami = [];
        foreach ($pools as $p) {
            $byTatami[$p['tatami_id'] ?? 0][] = $p;
        }
        $unplaced = PoolService::unplaced($cid);
        foreach ($unplaced as &$u) {
            $u['suggestions'] = $g ? array_slice(PoolService::suggestionsFor((int) $u['id']), 0, 3) : [];
        }
        unset($u);
        $groups = [];
        foreach (PoolService::buildGroups($cid) as $k => $grp) {
            $groups[$k] = $grp['label'] . ' (' . count($grp['athletes']) . ')';
        }
        return $this->admin('pools', [
            'title' => 'Poules et tapis', 'competition' => $c, 'c' => $c, 'g' => $g,
            'report' => $g ? (json_decode((string) $g['report_json'], true) ?: []) : [],
            'pools' => $pools, 'shown' => $shown, 'q' => $q, 'vue' => $vue, 'byTatami' => $byTatami, 'tatamis' => CompetitionService::tatamis($cid),
            'loads' => $g ? PoolService::tatamiLoads((int) $g['id']) : null,
            'warnings' => $g ? PoolService::warnings($cid, (int) $g['id']) : [],
            'participants' => count(PoolService::participants($cid)), 'unplaced' => $unplaced, 'groups' => $groups,
            'grouping' => SettingsService::get($cid, 'pool_grouping'), 'scripts' => ['pools.js'],
        ]);
    }

    public function action(Request $r): Response
    {
        $c = $this->competition($r);
        $msg = $this->dispatch((int) $c['id'], $r->all());
        return $this->back($r, $msg);
    }

    /** Variante JSON pour le glisser-déposer. */
    public function api(Request $r): Response
    {
        $c = $this->competition($r);
        try {
            return $this->json(['ok' => true, 'message' => $this->dispatch((int) $c['id'], $r->json())]);
        } catch (DomainError $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()] + $e->data, 422);
        }
    }

    private function dispatch(int $cid, array $in): string
    {
        $g = PoolService::activeGeneration($cid);
        $int = fn ($k) => isset($in[$k]) && $in[$k] !== '' ? (int) $in[$k] : null;
        $assertPool = function (?int $poolId) use ($cid): int {
            if (!$poolId || !Database::value('SELECT 1 FROM pools WHERE id = ? AND competition_id = ?', [$poolId, $cid])) {
                throw new DomainError('Poule introuvable.');
            }
            return $poolId;
        };
        $assertReg = function (?int $regId) use ($cid): int {
            if (!$regId || !Database::value('SELECT 1 FROM registrations WHERE id = ? AND competition_id = ?', [$regId, $cid])) {
                throw new DomainError('Athlète introuvable.');
            }
            return $regId;
        };
        $exception = !empty($in['allow_exception']);
        switch ($in['op'] ?? '') {
            case 'generate':
                $id = PoolService::generate($cid);
                $n = (int) Database::value('SELECT COUNT(*) FROM pools WHERE generation_id = ?', [$id]);
                return "Proposition générée : $n poules. Vérifiez l'aperçu, ajustez puis validez.";
            case 'validate':
                if (!$g) {
                    throw new DomainError('Aucune proposition à valider.');
                }
                PoolService::validate((int) $g['id'], !empty($in['acknowledge_unplaced']));
                return 'Composition validée : les combats sont créés et visibles par les commissaires.';
            case 'cancel':
                if (!$g) {
                    throw new DomainError('Aucune génération en cours.');
                }
                PoolService::cancel((int) $g['id']);
                return 'Génération annulée.';
            case 'distribute':
                if (!$g) {
                    throw new DomainError('Aucune génération en cours.');
                }
                PoolService::autoDistribute((int) $g['id']);
                return 'Poules réparties automatiquement sur les tapis (poules verrouillées et commencées conservées).';
            case 'move_athlete':
                PoolService::moveAthlete($assertReg($int('registration_id')), $assertPool($int('pool_id')), $exception);
                return 'Athlète déplacé.';
            case 'swap':
                PoolService::swapAthletes($assertReg($int('a')), $assertReg($int('b')), $exception);
                return 'Athlètes échangés.';
            case 'remove_athlete':
                PoolService::removeAthlete($assertReg($int('registration_id')), $assertPool($int('pool_id')));
                return 'Athlète retiré de la poule (non placé).';
            case 'move_pool':
                $tatami = $int('tatami_id');
                if ($tatami && !Database::value('SELECT 1 FROM tatamis WHERE id = ? AND competition_id = ?', [$tatami, $cid])) {
                    throw new DomainError('Tapis invalide.');
                }
                PoolService::setTatami($assertPool($int('pool_id')), $tatami ?: null, $int('position'));
                return 'Poule déplacée.';
            case 'lock':
            case 'unlock':
                PoolService::setLocked($assertPool($int('pool_id')), $in['op'] === 'lock');
                return $in['op'] === 'lock' ? 'Poule verrouillée.' : 'Poule déverrouillée.';
            case 'new_pool':
                PoolService::createPoolLike($assertPool($int('pool_id')));
                return 'Poule vide créée dans le même groupe.';
            case 'delete_pool':
                PoolService::deleteEmptyPool($assertPool($int('pool_id')));
                return 'Poule supprimée.';
            case 'regroup':
                PoolService::regenerateGroup($cid, (string) ($in['group_key'] ?? ''));
                return 'Groupe recalculé (les poules dont les combats ont commencé ne sont pas modifiées).';
            case 'decide':
                $type = (string) ($in['type'] ?? '');
                $decision = ['type' => $type];
                if ($type === 'sizes') {
                    $decision['sizes'] = array_map('intval', explode(',', (string) ($in['sizes'] ?? '')));
                }
                if ($type === 'merge') {
                    $decision['into'] = (string) ($in['into'] ?? '');
                }
                PoolService::decide($cid, (string) ($in['group_key'] ?? ''), $decision);
                return 'Décision enregistrée et appliquée au groupe.';
        }
        throw new DomainError('Action inconnue.');
    }
}
