<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;

/**
 * Cycle de vie des poules : génération (brouillon) → ajustements → validation (création des combats) → compétition.
 * Seuls les athlètes présents, inscription confirmée, dans une catégorie « poules » sont utilisés.
 */
final class PoolService
{
    public static function activeGeneration(int $competitionId): ?array
    {
        return Database::one(
            "SELECT * FROM pool_generations WHERE competition_id = ? AND status IN ('draft','validated') ORDER BY id DESC LIMIT 1",
            [$competitionId]
        );
    }

    public static function generation(int $id): array
    {
        $g = Database::one('SELECT * FROM pool_generations WHERE id = ?', [$id]);
        if (!$g) {
            throw new HttpException(404, 'Génération introuvable.');
        }
        return $g;
    }

    /** Athlètes présents et autorisés à combattre. */
    public static function participants(int $competitionId): array
    {
        $useMeasured = (bool) SettingsService::get($competitionId, 'use_measured_weight');
        $rows = Database::all(
            "SELECT r.id, r.club_id, r.age_category_id, r.weight_category_id, r.declared_weight_kg, r.measured_weight_kg,
                    a.sex, a.last_name, a.first_name, ac.name AS age_name, ac.name_m, ac.name_f, ac.sort_order AS age_sort,
                    wc.label AS weight_label, wc.sort_order AS weight_sort, cc.merge_group
             FROM registrations r
             JOIN athletes a ON a.id = r.athlete_id
             JOIN age_categories ac ON ac.id = r.age_category_id
             LEFT JOIN weight_categories wc ON wc.id = r.weight_category_id
             LEFT JOIN competition_categories cc ON cc.competition_id = r.competition_id AND cc.age_category_id = r.age_category_id AND cc.sex = a.sex
             WHERE r.competition_id = ? AND r.status = 'confirmed' AND r.attendance_status = 'present' AND ac.format = 'pools'
             ORDER BY ac.sort_order, a.sex, wc.sort_order, r.id",
            [$competitionId]
        );
        foreach ($rows as &$r) {
            $w = ($useMeasured && $r['measured_weight_kg'] !== null) ? $r['measured_weight_kg'] : $r['declared_weight_kg'];
            $r['ref_weight'] = $w === null ? null : (float) $w;
        }
        return $rows;
    }

    public static function groupKey(array $r, string $mode): string
    {
        $base = $r['merge_group'] ? 'm:' . $r['merge_group'] : 'a' . $r['age_category_id'];
        $key = $base . '|' . $r['sex'];
        if ($mode === 'weight_category') {
            $key .= '|w' . ($r['weight_category_id'] ?? '0');
        }
        return $key;
    }

    public static function groupLabel(array $r, string $mode): string
    {
        $name = CategoryService::label(['name' => $r['age_name'], 'name_m' => $r['name_m'], 'name_f' => $r['name_f']], $r['sex']);
        if ($r['merge_group']) {
            $name = 'Groupe ' . $r['merge_group'] . ' ' . sex_label($r['sex'], true);
        }
        if ($mode === 'weight_category' && $r['weight_label']) {
            $name .= ' ' . $r['weight_label'] . ' kg';
        }
        return $name;
    }

    /** @return array<string, array{key:string,label:string,age_category_id:int,sex:string,weight_category_id:?int,sort:array,athletes:list<array>}> */
    public static function buildGroups(int $competitionId, ?array $onlyRegistrationIds = null): array
    {
        $mode = (string) SettingsService::get($competitionId, 'pool_grouping');
        $overrides = (array) SettingsService::get($competitionId, 'pool_group_overrides');
        $groups = [];
        foreach (self::participants($competitionId) as $r) {
            if ($onlyRegistrationIds !== null && !in_array((int) $r['id'], $onlyRegistrationIds, true)) {
                continue;
            }
            $key = self::groupKey($r, $mode);
            // Décision explicite de l'administrateur : regroupement avec un groupe voisin
            if (($overrides[$key]['type'] ?? null) === 'merge' && !empty($overrides[$key]['into'])) {
                $key = (string) $overrides[$key]['into'];
            }
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'key' => $key,
                    'label' => self::groupLabel($r, $mode),
                    'age_category_id' => (int) $r['age_category_id'],
                    'sex' => $r['sex'],
                    'weight_category_id' => $mode === 'weight_category' ? ($r['weight_category_id'] === null ? null : (int) $r['weight_category_id']) : null,
                    'sort' => [(int) $r['age_sort'], $r['sex'], (int) ($r['weight_sort'] ?? 0)],
                    'athletes' => [],
                ];
            }
            $groups[$key]['athletes'][] = [
                'id' => (int) $r['id'],
                'weight' => $r['ref_weight'],
                'club_id' => $r['club_id'] === null ? null : (int) $r['club_id'],
                'name' => $r['last_name'] . ' ' . $r['first_name'],
            ];
        }
        uasort($groups, fn ($a, $b) => $a['sort'] <=> $b['sort']);
        return $groups;
    }

    /** Vérifie qu'une nouvelle génération est possible (aucun combat commencé dans la génération en cours). */
    private static function assertCanRegenerate(int $competitionId): void
    {
        $started = (int) Database::value(
            "SELECT COUNT(*) FROM bouts b JOIN pools p ON p.id = b.pool_id JOIN pool_generations g ON g.id = p.generation_id
             WHERE b.competition_id = ? AND g.status = 'validated' AND b.status <> 'scheduled' AND b.status <> 'cancelled'",
            [$competitionId]
        );
        if ($started) {
            throw new DomainError("Des combats ont déjà commencé : la composition ne peut plus être régénérée entièrement. Utilisez l'ajout d'athlètes ou le recalcul d'un groupe dont les combats n'ont pas commencé.");
        }
    }

    /**
     * Génère une nouvelle proposition (brouillon). Les poules verrouillées de la génération précédente sont conservées
     * telles quelles ; leurs athlètes ne sont pas redistribués.
     */
    public static function generate(int $competitionId): int
    {
        $competition = CompetitionService::find($competitionId);
        CompetitionService::assertWritable($competition);
        self::assertCanRegenerate($competitionId);
        $participants = self::participants($competitionId);
        if (!$participants) {
            throw new DomainError('Aucun athlète présent et confirmé : validez les présences avant de générer les poules.');
        }
        return Database::transaction(function () use ($competition, $competitionId) {
            $previous = self::activeGeneration($competitionId);
            $genId = Database::insert('pool_generations', ['competition_id' => $competitionId, 'status' => 'draft', 'created_by' => Auth::id()]);
            $keptRegs = [];
            $number = 0;
            if ($previous) {
                foreach (Database::all('SELECT * FROM pools WHERE generation_id = ? AND is_locked = 1 ORDER BY number', [$previous['id']]) as $locked) {
                    $number++;
                    $newPool = $locked;
                    unset($newPool['id'], $newPool['created_at'], $newPool['updated_at']);
                    $newPool['generation_id'] = $genId;
                    $newPool['number'] = $number;
                    $newPool['status'] = 'draft';
                    $newId = Database::insert('pools', $newPool);
                    foreach (Database::all('SELECT * FROM pool_athletes WHERE pool_id = ?', [$locked['id']]) as $pa) {
                        Database::insert('pool_athletes', ['pool_id' => $newId, 'registration_id' => $pa['registration_id'], 'generation_id' => $genId, 'seed' => $pa['seed'], 'reference_weight_kg' => $pa['reference_weight_kg']]);
                        $keptRegs[] = (int) $pa['registration_id'];
                    }
                }
                self::discardGeneration($previous);
            }
            $report = ['groups' => [], 'warnings' => [], 'seed' => random_int(1, 2_000_000_000)];
            foreach (self::buildGroups($competitionId) as $group) {
                $group['athletes'] = array_values(array_filter($group['athletes'], fn ($a) => !in_array($a['id'], $keptRegs, true)));
                if (!$group['athletes']) {
                    continue;
                }
                $report['groups'][] = self::generateGroup($competition, $genId, $group, $number, $report['seed']);
            }
            self::autoDistribute($genId);
            $report['warnings'] = array_merge($report['warnings'], self::warnings($competitionId, $genId));
            Database::run('UPDATE pool_generations SET report_json = ? WHERE id = ?', [json_encode($report, JSON_UNESCAPED_UNICODE), $genId]);
            if (in_array($competition['status'], ['draft', 'registrations_open', 'registrations_closed', 'attendance'], true)) {
                Database::run("UPDATE competitions SET status = 'pool_preparation' WHERE id = ?", [$competitionId]);
            }
            AuditLogger::log('pools.generate', 'pool_generation', $genId, null, [
                'pools' => (int) Database::value('SELECT COUNT(*) FROM pools WHERE generation_id = ?', [$genId]),
                'kept_locked' => count($keptRegs),
            ], null, $competitionId);
            return $genId;
        });
    }

    /** Compose les poules d'un groupe et les enregistre. $number est incrémenté. */
    private static function generateGroup(array $competition, int $genId, array $group, int &$number, int $seed): array
    {
        $cid = (int) $competition['id'];
        $overrides = (array) SettingsService::get($cid, 'pool_group_overrides');
        $decision = $overrides[$group['key']] ?? null;
        $sizes = array_map('intval', explode(',', $competition['pool_sizes']));
        if (($decision['type'] ?? null) === 'sizes' && !empty($decision['sizes'])) {
            $sizes = array_map('intval', $decision['sizes']);
        }
        $entry = [
            'key' => $group['key'], 'label' => $group['label'], 'count' => count($group['athletes']),
            'status' => 'ok', 'pools' => [], 'issue' => null, 'decision' => $decision,
        ];
        if (($decision['type'] ?? null) === 'unplaced') {
            $entry['status'] = 'unplaced';
            $entry['unplaced'] = array_column($group['athletes'], 'id');
            return $entry;
        }
        $result = PoolGenerator::partition(
            $group['athletes'],
            $sizes,
            (int) SettingsService::get($cid, 'preferred_pool_size'),
            (bool) SettingsService::get($cid, 'avoid_same_club'),
            crc32($group['key']) ^ $seed
        );
        if (!$result['ok']) {
            $entry['status'] = 'needs_decision';
            $entry['issue'] = ['reason' => $result['reason'], 'alternatives' => $result['alternatives']];
            $entry['unplaced'] = array_column($group['athletes'], 'id');
            return $entry;
        }
        $poolsInGroup = count($result['pools']);
        foreach ($result['pools'] as $i => $members) {
            $number++;
            $poolId = self::insertPool($competition, $genId, $group, $number, $members, $poolsInGroup > 1 ? $i + 1 : null);
            $entry['pools'][] = $poolId;
        }
        return $entry;
    }

    private static function insertPool(array $competition, int $genId, array $group, int $number, array $members, ?int $indexInGroup): int
    {
        $weights = array_values(array_filter(array_column($members, 'weight'), fn ($w) => $w !== null));
        $label = $group['label'] . ($indexInGroup ? ' · Poule ' . $indexInGroup : '');
        $poolId = Database::insert('pools', [
            'competition_id' => (int) $competition['id'],
            'generation_id' => $genId,
            'group_key' => $group['key'],
            'age_category_id' => $group['age_category_id'],
            'sex' => $group['sex'],
            'weight_category_id' => $group['weight_category_id'],
            'number' => $number,
            'label' => mb_substr($label, 0, 80),
            'weight_min_kg' => $weights ? min($weights) : null,
            'weight_max_kg' => $weights ? max($weights) : null,
            'status' => 'draft',
        ]);
        foreach (array_values($members) as $seed => $m) {
            Database::insert('pool_athletes', [
                'pool_id' => $poolId, 'registration_id' => $m['id'], 'generation_id' => $genId,
                'seed' => $seed + 1, 'reference_weight_kg' => $m['weight'],
            ]);
        }
        self::refreshEstimate($poolId);
        return $poolId;
    }

    /** Recalcule le temps estimé d'une poule : combats × (durée réglementaire + battement). */
    public static function refreshEstimate(int $poolId): void
    {
        $pool = Database::one('SELECT * FROM pools WHERE id = ?', [$poolId]);
        if (!$pool) {
            return;
        }
        $n = (int) Database::value('SELECT COUNT(*) FROM pool_athletes WHERE pool_id = ?', [$poolId]);
        $bouts = (int) Database::value("SELECT COUNT(*) FROM bouts WHERE pool_id = ? AND status <> 'cancelled'", [$poolId]);
        if ($bouts === 0) {
            $bouts = PoolGenerator::boutCount($n);
            $rule = DurationRuleService::resolve((int) $pool['competition_id'], (int) $pool['age_category_id'], $pool['sex']);
            $duration = (int) ($rule['duration_seconds'] ?? 180);
        } else {
            $duration = (int) Database::value("SELECT AVG(duration_seconds) FROM bouts WHERE pool_id = ? AND status <> 'cancelled'", [$poolId]);
        }
        $gap = (int) SettingsService::get((int) $pool['competition_id'], 'bout_gap_seconds');
        Database::run('UPDATE pools SET estimated_seconds = ? WHERE id = ?', [$bouts * ($duration + $gap), $poolId]);
    }

    private static function discardGeneration(array $generation): void
    {
        Database::run("DELETE FROM bouts WHERE pool_id IN (SELECT id FROM pools WHERE generation_id = ?) AND status = 'scheduled'", [$generation['id']]);
        Database::run("UPDATE pool_generations SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?", [$generation['id']]);
        Database::run('DELETE FROM pool_athletes WHERE generation_id = ?', [$generation['id']]);
        Database::run('UPDATE tatamis SET current_bout_id = NULL WHERE competition_id = ?', [$generation['competition_id']]);
        Database::run('DELETE FROM pools WHERE generation_id = ?', [$generation['id']]);
    }

    /** Annule la génération en cours (brouillon, ou validée tant qu'aucun combat n'a commencé). */
    public static function cancel(int $generationId): void
    {
        $g = self::generation($generationId);
        CompetitionService::assertWritable((int) $g['competition_id']);
        if ($g['status'] === 'cancelled') {
            return;
        }
        self::assertCanRegenerate((int) $g['competition_id']);
        Database::transaction(function () use ($g) {
            self::discardGeneration($g);
            AuditLogger::log('pools.cancel', 'pool_generation', (int) $g['id'], ['status' => $g['status']], ['status' => 'cancelled'], null, (int) $g['competition_id']);
        });
    }

    /** Pools d'une génération avec leurs athlètes (pour l'aperçu et les PDF). */
    public static function pools(int $generationId, ?int $tatamiId = null): array
    {
        $sql = 'SELECT p.*, t.number AS tatami_number, ac.name AS age_name, ac.name_m, ac.name_f, ac.code AS age_code, wc.label AS weight_label
                FROM pools p
                LEFT JOIN tatamis t ON t.id = p.tatami_id
                JOIN age_categories ac ON ac.id = p.age_category_id
                LEFT JOIN weight_categories wc ON wc.id = p.weight_category_id
                WHERE p.generation_id = ?';
        $params = [$generationId];
        if ($tatamiId !== null) {
            $sql .= ' AND p.tatami_id = ?';
            $params[] = $tatamiId;
        }
        $pools = Database::all($sql . ' ORDER BY t.number IS NULL, t.number, p.order_on_tatami, p.number', $params);
        if (!$pools) {
            return [];
        }
        $ids = array_column($pools, 'id');
        $p = [];
        $in = Database::in($ids, $p, 'pool');
        $athletes = Database::all(
            "SELECT pa.*, r.club_id, r.club_exception_label, r.declared_weight_kg, r.measured_weight_kg, a.last_name, a.first_name, a.sex, a.birth_date, a.grade,
                    c.name AS club_name, c.short_name AS club_short, c.logo_path AS club_logo
             FROM pool_athletes pa JOIN registrations r ON r.id = pa.registration_id
             JOIN athletes a ON a.id = r.athlete_id LEFT JOIN clubs c ON c.id = r.club_id
             WHERE pa.pool_id IN $in ORDER BY pa.pool_id, pa.seed",
            $p
        );
        $byPool = [];
        foreach ($athletes as $a) {
            $byPool[$a['pool_id']][] = $a;
        }
        $boutStats = Database::all(
            "SELECT pool_id, COUNT(*) AS total, SUM(status = 'validated') AS done, SUM(status IN ('in_progress','finished')) AS running
             FROM bouts WHERE pool_id IN $in AND status <> 'cancelled' GROUP BY pool_id",
            $p
        );
        $stats = array_column($boutStats, null, 'pool_id');
        foreach ($pools as &$pool) {
            $pool['athletes'] = $byPool[$pool['id']] ?? [];
            $pool['size'] = count($pool['athletes']);
            $pool['bouts_total'] = (int) ($stats[$pool['id']]['total'] ?? PoolGenerator::boutCount($pool['size']));
            $pool['bouts_done'] = (int) ($stats[$pool['id']]['done'] ?? 0);
            $pool['bouts_started'] = (int) ($stats[$pool['id']]['done'] ?? 0) + (int) ($stats[$pool['id']]['running'] ?? 0);
            $pool['spread_pct'] = PoolGenerator::spreadPct(array_map(fn ($a) => ['weight' => $a['reference_weight_kg'] === null ? null : (float) $a['reference_weight_kg']], $pool['athletes']));
            $pool['club_conflicts'] = PoolGenerator::clubConflicts([array_map(fn ($a) => ['club_id' => $a['club_id'] === null ? null : (int) $a['club_id']], $pool['athletes'])]);
        }
        return $pools;
    }

    public static function pool(int $poolId): array
    {
        $p = Database::one('SELECT * FROM pools WHERE id = ?', [$poolId]);
        if (!$p) {
            throw new HttpException(404, 'Poule introuvable.');
        }
        return $p;
    }

    private static function hasStartedBouts(int $poolId): bool
    {
        return (bool) Database::value("SELECT 1 FROM bouts WHERE pool_id = ? AND status NOT IN ('scheduled','cancelled') LIMIT 1", [$poolId]);
    }

    private static function assertEditable(array $pool, bool $allowLocked = false): void
    {
        CompetitionService::assertWritable((int) $pool['competition_id']);
        $g = self::generation((int) $pool['generation_id']);
        if ($g['status'] === 'cancelled') {
            throw new DomainError('Cette génération a été annulée.');
        }
        if (!$allowLocked && (int) $pool['is_locked'] === 1) {
            throw new DomainError("La poule « {$pool['label']} » est verrouillée : déverrouillez-la avant de la modifier.");
        }
        if (self::hasStartedBouts((int) $pool['id'])) {
            throw new DomainError("Des combats de la poule « {$pool['label']} » ont commencé : sa composition ne peut plus être modifiée.");
        }
    }

    /** Vérifie la compatibilité réglementaire (âge, sexe, poids) sauf exception explicitement confirmée. */
    private static function assertCompatible(array $pool, int $registrationId, bool $allowException): ?string
    {
        $r = Database::one('SELECT r.age_category_id, r.weight_category_id, a.sex FROM registrations r JOIN athletes a ON a.id = r.athlete_id WHERE r.id = ?', [$registrationId]);
        if ($r['sex'] !== $pool['sex']) {
            throw new DomainError('Les catégories masculines et féminines ne peuvent jamais être mélangées.');
        }
        $issues = [];
        if ((int) $r['age_category_id'] !== (int) $pool['age_category_id']) {
            $issues[] = "catégorie d'âge différente";
        }
        if ($pool['weight_category_id'] !== null && (int) $r['weight_category_id'] !== (int) $pool['weight_category_id']) {
            $issues[] = 'catégorie de poids différente';
        }
        if ($issues && !$allowException) {
            throw new DomainError('Déplacement incompatible (' . implode(', ', $issues) . '). Cochez « exception autorisée » pour le confirmer.', 422, ['needs_exception' => true]);
        }
        return $issues ? implode(', ', $issues) : null;
    }

    public static function moveAthlete(int $registrationId, int $toPoolId, bool $allowException = false): void
    {
        $to = self::pool($toPoolId);
        $current = Database::one('SELECT * FROM pool_athletes WHERE registration_id = ? AND generation_id = ?', [$registrationId, $to['generation_id']]);
        self::assertEditable($to);
        $exception = self::assertCompatible($to, $registrationId, $allowException);
        Database::transaction(function () use ($registrationId, $to, $current, $exception) {
            if ($current) {
                $from = self::pool((int) $current['pool_id']);
                if ((int) $from['id'] === (int) $to['id']) {
                    return;
                }
                self::assertEditable($from);
                Database::run('DELETE FROM pool_athletes WHERE pool_id = ? AND registration_id = ?', [$from['id'], $registrationId]);
                self::reseed((int) $from['id']);
            }
            $seed = (int) Database::value('SELECT COALESCE(MAX(seed), 0) + 1 FROM pool_athletes WHERE pool_id = ?', [$to['id']]);
            Database::insert('pool_athletes', [
                'pool_id' => $to['id'], 'registration_id' => $registrationId, 'generation_id' => $to['generation_id'], 'seed' => $seed,
                'reference_weight_kg' => self::referenceWeight($registrationId),
            ]);
            foreach (array_filter([$current['pool_id'] ?? null, $to['id']]) as $pid) {
                self::afterCompositionChange((int) $pid);
            }
            AuditLogger::log('pools.move_athlete', 'registration', $registrationId, ['pool_id' => $current['pool_id'] ?? null], ['pool_id' => (int) $to['id']], $exception ? 'Exception autorisée : ' . $exception : null, (int) $to['competition_id']);
        });
    }

    public static function swapAthletes(int $regA, int $regB, bool $allowException = false): void
    {
        $pa = Database::one("SELECT pa.* FROM pool_athletes pa JOIN pool_generations g ON g.id = pa.generation_id WHERE pa.registration_id = ? AND g.status IN ('draft','validated')", [$regA]);
        $pb = Database::one("SELECT pa.* FROM pool_athletes pa JOIN pool_generations g ON g.id = pa.generation_id WHERE pa.registration_id = ? AND g.status IN ('draft','validated')", [$regB]);
        if (!$pa || !$pb || $pa['generation_id'] !== $pb['generation_id']) {
            throw new DomainError('Les deux athlètes doivent être placés dans des poules de la même génération.');
        }
        if ($pa['pool_id'] === $pb['pool_id']) {
            return;
        }
        $poolA = self::pool((int) $pa['pool_id']);
        $poolB = self::pool((int) $pb['pool_id']);
        self::assertEditable($poolA);
        self::assertEditable($poolB);
        $e1 = self::assertCompatible($poolB, $regA, $allowException);
        $e2 = self::assertCompatible($poolA, $regB, $allowException);
        Database::transaction(function () use ($pa, $pb, $poolA, $poolB, $regA, $regB, $e1, $e2) {
            Database::run('UPDATE pool_athletes SET seed = 250 WHERE pool_id = ? AND registration_id = ?', [$pa['pool_id'], $regA]);
            Database::run('UPDATE pool_athletes SET pool_id = ?, seed = ? WHERE pool_id = ? AND registration_id = ?', [$pa['pool_id'], $pa['seed'], $pb['pool_id'], $regB]);
            Database::run('UPDATE pool_athletes SET pool_id = ?, seed = ? WHERE registration_id = ? AND seed = 250', [$pb['pool_id'], $pb['seed'], $regA]);
            self::afterCompositionChange((int) $poolA['id']);
            self::afterCompositionChange((int) $poolB['id']);
            $exc = trim(($e1 ?? '') . ' ' . ($e2 ?? ''));
            AuditLogger::log('pools.swap', 'pool', (int) $poolA['id'], ['a' => $regA, 'pool_a' => (int) $poolA['id']], ['b' => $regB, 'pool_b' => (int) $poolB['id']], $exc !== '' ? 'Exception autorisée : ' . $exc : null, (int) $poolA['competition_id']);
        });
    }

    /** Retire un athlète d'une poule (il redevient « non placé »). */
    public static function removeAthlete(int $registrationId, int $poolId): void
    {
        $pool = self::pool($poolId);
        self::assertEditable($pool);
        Database::transaction(function () use ($registrationId, $pool) {
            Database::run('DELETE FROM pool_athletes WHERE pool_id = ? AND registration_id = ?', [$pool['id'], $registrationId]);
            self::reseed((int) $pool['id']);
            self::afterCompositionChange((int) $pool['id']);
            AuditLogger::log('pools.remove_athlete', 'registration', $registrationId, ['pool_id' => (int) $pool['id']], null, null, (int) $pool['competition_id']);
        });
    }

    /** Crée une poule vide dans le groupe d'une poule existante (pour y déplacer des athlètes). */
    public static function createPoolLike(int $poolId): int
    {
        $model = self::pool($poolId);
        CompetitionService::assertWritable((int) $model['competition_id']);
        $number = (int) Database::value('SELECT MAX(number) + 1 FROM pools WHERE generation_id = ?', [$model['generation_id']]);
        $g = self::generation((int) $model['generation_id']);
        $id = Database::insert('pools', [
            'competition_id' => $model['competition_id'], 'generation_id' => $model['generation_id'], 'group_key' => $model['group_key'],
            'age_category_id' => $model['age_category_id'], 'sex' => $model['sex'], 'weight_category_id' => $model['weight_category_id'],
            'number' => $number, 'label' => mb_substr(preg_replace('/ · Poule \d+$/u', '', $model['label']) . ' · Poule ' . $number, 0, 80),
            'tatami_id' => $model['tatami_id'], 'order_on_tatami' => (int) $model['order_on_tatami'] + 1,
            'status' => $g['status'] === 'validated' ? 'validated' : 'draft',
        ]);
        AuditLogger::log('pools.create', 'pool', $id, null, ['like' => $poolId], null, (int) $model['competition_id']);
        return $id;
    }

    public static function deleteEmptyPool(int $poolId): void
    {
        $pool = self::pool($poolId);
        self::assertEditable($pool);
        if ((int) Database::value('SELECT COUNT(*) FROM pool_athletes WHERE pool_id = ?', [$poolId]) > 0) {
            throw new DomainError('Seule une poule vide peut être supprimée.');
        }
        Database::run('DELETE FROM bouts WHERE pool_id = ?', [$poolId]);
        Database::run('DELETE FROM pools WHERE id = ?', [$poolId]);
        AuditLogger::log('pools.delete', 'pool', $poolId, ['label' => $pool['label']], null, null, (int) $pool['competition_id']);
    }

    private static function reseed(int $poolId): void
    {
        $rows = Database::column('SELECT registration_id FROM pool_athletes WHERE pool_id = ? ORDER BY seed', [$poolId]);
        foreach ($rows as $i => $reg) {
            Database::run('UPDATE pool_athletes SET seed = ? WHERE pool_id = ? AND registration_id = ?', [200 + $i, $poolId, $reg]);
        }
        foreach ($rows as $i => $reg) {
            Database::run('UPDATE pool_athletes SET seed = ? WHERE pool_id = ? AND registration_id = ?', [$i + 1, $poolId, $reg]);
        }
    }

    private static function referenceWeight(int $registrationId): ?float
    {
        $r = Database::one('SELECT competition_id, declared_weight_kg, measured_weight_kg FROM registrations WHERE id = ?', [$registrationId]);
        $useMeasured = (bool) SettingsService::get((int) $r['competition_id'], 'use_measured_weight');
        $w = ($useMeasured && $r['measured_weight_kg'] !== null) ? $r['measured_weight_kg'] : $r['declared_weight_kg'];
        return $w === null ? null : (float) $w;
    }

    /** Après modification d'une poule : poids extrêmes, estimation, et combats régénérés si la poule est validée. */
    private static function afterCompositionChange(int $poolId): void
    {
        $pool = self::pool($poolId);
        $w = Database::one('SELECT MIN(reference_weight_kg) AS mn, MAX(reference_weight_kg) AS mx FROM pool_athletes WHERE pool_id = ?', [$poolId]);
        Database::run('UPDATE pools SET weight_min_kg = ?, weight_max_kg = ? WHERE id = ?', [$w['mn'], $w['mx'], $poolId]);
        if ($pool['status'] !== 'draft') {
            Database::run("DELETE FROM bouts WHERE pool_id = ? AND status = 'scheduled'", [$poolId]);
            self::createBouts($pool);
            if ($pool['tatami_id']) {
                self::resequenceTatami((int) $pool['tatami_id']);
            }
        }
        self::refreshEstimate($poolId);
    }

    public static function setTatami(int $poolId, ?int $tatamiId, ?int $position = null): void
    {
        $pool = self::pool($poolId);
        CompetitionService::assertWritable((int) $pool['competition_id']);
        if ($tatamiId !== null && !Database::value('SELECT 1 FROM tatamis WHERE id = ? AND competition_id = ? AND is_active = 1', [$tatamiId, $pool['competition_id']])) {
            throw new DomainError('Tapis invalide ou inactif.');
        }
        if (Database::value("SELECT 1 FROM bouts WHERE pool_id = ? AND status IN ('in_progress','finished') LIMIT 1", [$poolId])) {
            throw new DomainError('Un combat de cette poule est en cours : attendez sa validation avant de changer de tapis.');
        }
        Database::transaction(function () use ($pool, $tatamiId, $position) {
            $old = $pool['tatami_id'] === null ? null : (int) $pool['tatami_id'];
            $order = $position ?? ((int) Database::value('SELECT COALESCE(MAX(order_on_tatami), 0) + 1 FROM pools WHERE generation_id = ? AND tatami_id <=> ?', [$pool['generation_id'], $tatamiId]));
            Database::run('UPDATE pools SET tatami_id = ?, order_on_tatami = ? WHERE id = ?', [$tatamiId, $order, $pool['id']]);
            self::renumberTatamiPools((int) $pool['generation_id'], $tatamiId, (int) $pool['id'], $order);
            Database::run("UPDATE bouts SET tatami_id = ?, version = version + 1 WHERE pool_id = ? AND status = 'scheduled'", [$tatamiId, $pool['id']]);
            foreach (array_unique(array_filter([$old, $tatamiId])) as $t) {
                self::resequenceTatami((int) $t);
            }
            if ($old !== $tatamiId) {
                AuditLogger::log('pools.move_tatami', 'pool', (int) $pool['id'], ['tatami_id' => $old], ['tatami_id' => $tatamiId], null, (int) $pool['competition_id']);
            }
        });
    }

    private static function renumberTatamiPools(int $generationId, ?int $tatamiId, int $movedPoolId, int $position): void
    {
        $ids = Database::column(
            'SELECT id FROM pools WHERE generation_id = ? AND tatami_id <=> ? AND id <> ? ORDER BY order_on_tatami, number',
            [$generationId, $tatamiId, $movedPoolId]
        );
        array_splice($ids, max(0, $position - 1), 0, [$movedPoolId]);
        foreach ($ids as $i => $id) {
            Database::run('UPDATE pools SET order_on_tatami = ? WHERE id = ?', [$i + 1, $id]);
        }
    }

    public static function setLocked(int $poolId, bool $locked): void
    {
        $pool = self::pool($poolId);
        CompetitionService::assertWritable((int) $pool['competition_id']);
        Database::run('UPDATE pools SET is_locked = ? WHERE id = ?', [$locked ? 1 : 0, $poolId]);
        AuditLogger::log($locked ? 'pools.lock' : 'pools.unlock', 'pool', $poolId, null, null, null, (int) $pool['competition_id']);
    }

    /**
     * Répartition automatique des poules non verrouillées sur les tapis actifs (équilibrage de la charge),
     * puis ordre de passage : catégorie d'âge, sexe, poids.
     */
    public static function autoDistribute(int $generationId): void
    {
        $g = self::generation($generationId);
        $cid = (int) $g['competition_id'];
        CompetitionService::assertWritable($cid);
        $tatamis = array_map('intval', array_column(CompetitionService::tatamis($cid), 'id'));
        $pools = Database::all(
            'SELECT p.id, p.estimated_seconds, p.tatami_id, p.is_locked, ac.sort_order, p.sex, p.weight_min_kg, p.number
             FROM pools p JOIN age_categories ac ON ac.id = p.age_category_id WHERE p.generation_id = ?',
            [$generationId]
        );
        $started = [];
        foreach ($pools as $p) {
            if (self::hasStartedBouts((int) $p['id'])) {
                $started[(int) $p['id']] = true;
            }
        }
        $input = array_map(fn ($p) => [
            'id' => (int) $p['id'],
            'load' => (int) $p['estimated_seconds'],
            'tatami_id' => $p['tatami_id'] === null ? null : (int) $p['tatami_id'],
            'locked' => (int) $p['is_locked'] === 1 || isset($started[(int) $p['id']]),
        ], $pools);
        $assign = MatDistributor::distribute($input, $tatamis);
        $sortKey = [];
        foreach ($pools as $p) {
            $sortKey[(int) $p['id']] = [(int) $p['sort_order'], $p['sex'], (float) ($p['weight_min_kg'] ?? 0), (int) $p['number']];
        }
        $byTatami = [];
        foreach ($assign as $pid => $t) {
            $byTatami[$t][] = $pid;
        }
        foreach ($byTatami as $t => $pids) {
            usort($pids, fn ($a, $b) => $sortKey[$a] <=> $sortKey[$b]);
            foreach ($pids as $i => $pid) {
                Database::run('UPDATE pools SET tatami_id = ?, order_on_tatami = ? WHERE id = ?', [$t, $i + 1, $pid]);
                Database::run("UPDATE bouts SET tatami_id = ? WHERE pool_id = ? AND status = 'scheduled'", [$t, $pid]);
            }
            self::resequenceTatami((int) $t);
        }
    }

    /** Charge et avancement par tapis pour une génération. */
    public static function tatamiLoads(int $generationId): array
    {
        $g = self::generation($generationId);
        $cid = (int) $g['competition_id'];
        $gap = (int) SettingsService::get($cid, 'bout_gap_seconds');
        $rows = [];
        foreach (CompetitionService::tatamis($cid) as $t) {
            $rows[(int) $t['id']] = ['tatami' => $t, 'pools' => 0, 'bouts' => 0, 'seconds' => 0, 'done' => 0, 'remaining_seconds' => 0];
        }
        foreach (self::pools($generationId) as $p) {
            if ($p['tatami_id'] === null || !isset($rows[(int) $p['tatami_id']])) {
                continue;
            }
            $r = &$rows[(int) $p['tatami_id']];
            $r['pools']++;
            $r['bouts'] += $p['bouts_total'];
            $r['done'] += $p['bouts_done'];
            $r['seconds'] += (int) $p['estimated_seconds'];
            $per = $p['bouts_total'] > 0 ? (int) $p['estimated_seconds'] / $p['bouts_total'] : 0;
            $r['remaining_seconds'] += (int) round($per * ($p['bouts_total'] - $p['bouts_done']));
            unset($r);
        }
        $loads = array_column($rows, 'seconds');
        return [
            'tatamis' => array_values($rows),
            'spread_seconds' => $loads ? max($loads) - min($loads) : 0,
            'gap_seconds' => $gap,
        ];
    }

    /** Athlètes présents non placés dans la génération active. */
    public static function unplaced(int $competitionId): array
    {
        $g = self::activeGeneration($competitionId);
        $placed = $g ? array_map('intval', Database::column('SELECT registration_id FROM pool_athletes WHERE generation_id = ?', [$g['id']])) : [];
        $mode = (string) SettingsService::get($competitionId, 'pool_grouping');
        $out = [];
        foreach (self::participants($competitionId) as $r) {
            if (!in_array((int) $r['id'], $placed, true)) {
                $r['group_key'] = self::groupKey($r, $mode);
                $r['group_label'] = self::groupLabel($r, $mode);
                $out[] = $r;
            }
        }
        return $out;
    }

    /** Poules compatibles (même groupe, combats non commencés) triées par proximité de poids, pour un athlète non placé. */
    public static function suggestionsFor(int $registrationId): array
    {
        $reg = Database::one('SELECT competition_id FROM registrations WHERE id = ?', [$registrationId]);
        $g = self::activeGeneration((int) $reg['competition_id']);
        if (!$g) {
            return [];
        }
        $mode = (string) SettingsService::get((int) $reg['competition_id'], 'pool_grouping');
        $me = null;
        foreach (self::participants((int) $reg['competition_id']) as $r) {
            if ((int) $r['id'] === $registrationId) {
                $me = $r;
            }
        }
        if (!$me) {
            return [];
        }
        $key = self::groupKey($me, $mode);
        $max = max(array_map('intval', explode(',', (string) Database::value('SELECT pool_sizes FROM competitions WHERE id = ?', [$reg['competition_id']]))));
        $out = [];
        foreach (Database::all('SELECT * FROM pools WHERE generation_id = ? AND group_key = ?', [$g['id'], $key]) as $p) {
            if (self::hasStartedBouts((int) $p['id']) || (int) $p['is_locked'] === 1) {
                continue;
            }
            $size = (int) Database::value('SELECT COUNT(*) FROM pool_athletes WHERE pool_id = ?', [$p['id']]);
            $mid = $p['weight_min_kg'] === null ? null : ((float) $p['weight_min_kg'] + (float) $p['weight_max_kg']) / 2;
            $out[] = [
                'pool' => $p, 'size' => $size, 'full' => $size >= $max,
                'distance' => ($mid === null || $me['ref_weight'] === null) ? 0 : abs($mid - $me['ref_weight']),
            ];
        }
        usort($out, fn ($a, $b) => [$a['full'], $a['distance']] <=> [$b['full'], $b['distance']]);
        return $out;
    }

    /**
     * Recalcule les poules d'un groupe dont aucun combat n'a commencé, en intégrant les athlètes ajoutés depuis :
     * les poules ayant des combats commencés ne sont jamais touchées.
     */
    public static function regenerateGroup(int $competitionId, string $groupKey): array
    {
        $competition = CompetitionService::find($competitionId);
        CompetitionService::assertWritable($competition);
        $g = self::activeGeneration($competitionId);
        if (!$g) {
            throw new DomainError('Aucune génération en cours.');
        }
        return Database::transaction(function () use ($competition, $competitionId, $g, $groupKey) {
            $pools = Database::all('SELECT * FROM pools WHERE generation_id = ? AND group_key = ?', [$g['id'], $groupKey]);
            $keepRegs = [];
            foreach ($pools as $p) {
                if (self::hasStartedBouts((int) $p['id']) || (int) $p['is_locked'] === 1) {
                    $keepRegs = array_merge($keepRegs, array_map('intval', Database::column('SELECT registration_id FROM pool_athletes WHERE pool_id = ?', [$p['id']])));
                    continue;
                }
                Database::run('DELETE FROM bouts WHERE pool_id = ?', [$p['id']]);
                Database::run('DELETE FROM pool_athletes WHERE pool_id = ?', [$p['id']]);
                Database::run('DELETE FROM pools WHERE id = ?', [$p['id']]);
            }
            $groups = self::buildGroups($competitionId);
            if (!isset($groups[$groupKey])) {
                throw new DomainError('Groupe introuvable (aucun athlète présent).');
            }
            $group = $groups[$groupKey];
            $group['athletes'] = array_values(array_filter($group['athletes'], fn ($a) => !in_array($a['id'], $keepRegs, true)));
            $number = (int) Database::value('SELECT COALESCE(MAX(number), 0) FROM pools WHERE generation_id = ?', [$g['id']]);
            $entry = self::generateGroup($competition, (int) $g['id'], $group, $number, random_int(1, 2_000_000_000));
            if ($g['status'] === 'validated') {
                foreach ($entry['pools'] as $pid) {
                    Database::run("UPDATE pools SET status = 'validated' WHERE id = ?", [$pid]);
                    self::createBouts(self::pool((int) $pid));
                }
            }
            self::autoDistribute((int) $g['id']);
            $report = json_decode((string) $g['report_json'], true) ?: ['groups' => []];
            $found = false;
            foreach ($report['groups'] as &$grp) {
                if ($grp['key'] === $groupKey) {
                    $grp = $entry;
                    $found = true;
                }
            }
            unset($grp);
            if (!$found) {
                $report['groups'][] = $entry;
            }
            $report['warnings'] = self::warnings($competitionId, (int) $g['id']);
            Database::run('UPDATE pool_generations SET report_json = ? WHERE id = ?', [json_encode($report, JSON_UNESCAPED_UNICODE), $g['id']]);
            AuditLogger::log('pools.regenerate_group', 'pool_generation', (int) $g['id'], null, ['group' => $groupKey, 'pools' => $entry['pools']], null, $competitionId);
            return $entry;
        });
    }

    /** Enregistre la décision de l'administrateur pour un groupe sans répartition valide, puis recalcule ce groupe. */
    public static function decide(int $competitionId, string $groupKey, array $decision): array
    {
        $allowed = ['sizes', 'merge', 'unplaced', 'reset'];
        if (!in_array($decision['type'] ?? '', $allowed, true)) {
            throw new DomainError('Décision invalide.');
        }
        $overrides = (array) SettingsService::get($competitionId, 'pool_group_overrides');
        if ($decision['type'] === 'reset') {
            unset($overrides[$groupKey]);
        } else {
            if ($decision['type'] === 'sizes') {
                $decision['sizes'] = array_values(array_filter(array_map('intval', (array) ($decision['sizes'] ?? [])), fn ($s) => $s >= 2 && $s <= 8));
                if (!$decision['sizes']) {
                    throw new DomainError('Tailles de poules invalides.');
                }
            }
            if ($decision['type'] === 'merge' && empty($decision['into'])) {
                throw new DomainError('Choisissez le groupe avec lequel regrouper.');
            }
            $overrides[$groupKey] = $decision;
        }
        SettingsService::set($competitionId, 'pool_group_overrides', $overrides);
        AuditLogger::log('pools.decision', 'competition', $competitionId, null, ['group' => $groupKey, 'decision' => $decision], null, $competitionId);
        $target = $decision['type'] === 'merge' ? (string) $decision['into'] : $groupKey;
        $g = self::activeGeneration($competitionId);
        if ($g) {
            // Le groupe fusionné disparaît de la liste : on recalcule le groupe cible.
            if ($decision['type'] === 'merge') {
                $report = json_decode((string) $g['report_json'], true) ?: ['groups' => []];
                $report['groups'] = array_values(array_filter($report['groups'], fn ($x) => $x['key'] !== $groupKey));
                Database::run('UPDATE pool_generations SET report_json = ? WHERE id = ?', [json_encode($report, JSON_UNESCAPED_UNICODE), $g['id']]);
            }
            return self::regenerateGroup($competitionId, $target);
        }
        return [];
    }

    /** Valide définitivement la composition : crée les combats (règle de durée figée), publie l'ordre des tapis. */
    public static function validate(int $generationId, bool $acknowledgeUnplaced = false): void
    {
        $g = self::generation($generationId);
        $cid = (int) $g['competition_id'];
        CompetitionService::assertWritable($cid);
        if ($g['status'] !== 'draft') {
            throw new DomainError('Seule une proposition en brouillon peut être validée.');
        }
        $pools = Database::all('SELECT * FROM pools WHERE generation_id = ? ORDER BY number', [$generationId]);
        if (!$pools) {
            throw new DomainError('Aucune poule à valider.');
        }
        foreach ($pools as $p) {
            $n = (int) Database::value('SELECT COUNT(*) FROM pool_athletes WHERE pool_id = ?', [$p['id']]);
            if ($n < 2) {
                throw new DomainError("La poule « {$p['label']} » compte moins de 2 combattants : complétez-la ou supprimez-la.");
            }
            if ($p['tatami_id'] === null) {
                throw new DomainError("La poule « {$p['label']} » n'est affectée à aucun tapis.");
            }
        }
        $unplaced = self::unplaced($cid);
        if ($unplaced && !$acknowledgeUnplaced) {
            throw new DomainError(count($unplaced) . ' athlète(s) présent(s) ne sont dans aucune poule. Placez-les ou confirmez explicitement leur non-placement.', 422, ['unplaced' => count($unplaced)]);
        }
        Database::transaction(function () use ($generationId, $pools, $cid, $unplaced) {
            foreach ($pools as $p) {
                Database::run("UPDATE pools SET status = 'validated' WHERE id = ?", [$p['id']]);
                self::createBouts(self::pool((int) $p['id']));
            }
            foreach (array_unique(array_column($pools, 'tatami_id')) as $t) {
                self::resequenceTatami((int) $t);
            }
            Database::run("UPDATE pool_generations SET status = 'validated', validated_by = ?, validated_at = NOW() WHERE id = ?", [Auth::id(), $generationId]);
            AuditLogger::log('pools.validate', 'pool_generation', $generationId, null, [
                'pools' => count($pools),
                'bouts' => (int) Database::value("SELECT COUNT(*) FROM bouts b JOIN pools p ON p.id = b.pool_id WHERE p.generation_id = ?", [$generationId]),
                'unplaced' => array_map(fn ($u) => (int) $u['id'], $unplaced),
            ], $unplaced ? count($unplaced) . ' athlète(s) laissé(s) hors poule (confirmé)' : null, $cid);
        });
    }

    /** Crée les combats toutes rondes d'une poule (instantané de la règle de durée applicable). */
    public static function createBouts(array $pool): int
    {
        $athletes = Database::all('SELECT registration_id, seed FROM pool_athletes WHERE pool_id = ? ORDER BY seed', [$pool['id']]);
        $bySeed = array_column($athletes, 'registration_id', 'seed');
        $rule = DurationRuleService::resolve((int) $pool['competition_id'], (int) $pool['age_category_id'], $pool['sex'], 'pool');
        if (!$rule) {
            throw new DomainError("Aucune durée de combat applicable à « {$pool['label']} » : configurez les durées avant de valider.");
        }
        $existing = Database::all("SELECT a_registration_id, b_registration_id, order_in_pool FROM bouts WHERE pool_id = ? AND status <> 'cancelled'", [$pool['id']]);
        $pairsDone = [];
        $maxOrder = 0;
        foreach ($existing as $e) {
            $pairsDone[min($e['a_registration_id'], $e['b_registration_id']) . '-' . max($e['a_registration_id'], $e['b_registration_id'])] = true;
            $maxOrder = max($maxOrder, (int) $e['order_in_pool']);
        }
        $usedOrders = array_map('intval', Database::column('SELECT order_in_pool FROM bouts WHERE pool_id = ?', [$pool['id']]));
        $created = 0;
        foreach (PoolGenerator::roundRobinOrder(count($athletes)) as [$i, $j]) {
            $a = (int) $bySeed[$i];
            $b = (int) $bySeed[$j];
            $pairKey = min($a, $b) . '-' . max($a, $b);
            if (isset($pairsDone[$pairKey])) {
                continue;
            }
            $order = $maxOrder + 1;
            while (in_array($order, $usedOrders, true)) {
                $order++;
            }
            $usedOrders[] = $order;
            $maxOrder = $order;
            Database::insert('bouts', [
                'competition_id' => $pool['competition_id'], 'pool_id' => $pool['id'], 'tatami_id' => $pool['tatami_id'],
                'order_in_pool' => $order, 'phase' => 'pool', 'a_registration_id' => $a, 'b_registration_id' => $b,
                'duration_rule_id' => $rule['id'], 'duration_seconds' => $rule['duration_seconds'],
                'golden_score_mode' => $rule['golden_score_mode'], 'golden_score_seconds' => $rule['golden_score_seconds'],
                'recovery_seconds' => $rule['recovery_seconds'],
            ]);
            $created++;
        }
        self::refreshEstimate((int) $pool['id']);
        return $created;
    }

    /** Combat de barrage (départage) ajouté explicitement par l'administrateur entre deux athlètes d'une poule. */
    public static function addTiebreakBout(int $poolId, int $regA, int $regB): int
    {
        $pool = self::pool($poolId);
        CompetitionService::assertWritable((int) $pool['competition_id']);
        $members = array_map('intval', Database::column('SELECT registration_id FROM pool_athletes WHERE pool_id = ?', [$poolId]));
        if ($regA === $regB || !in_array($regA, $members, true) || !in_array($regB, $members, true)) {
            throw new DomainError('Choisissez deux athlètes différents de la poule.');
        }
        $rule = DurationRuleService::resolve((int) $pool['competition_id'], (int) $pool['age_category_id'], $pool['sex'], 'final');
        $order = (int) Database::value('SELECT COALESCE(MAX(order_in_pool), 0) + 1 FROM bouts WHERE pool_id = ?', [$poolId]);
        $id = Database::insert('bouts', [
            'competition_id' => $pool['competition_id'], 'pool_id' => $poolId, 'tatami_id' => $pool['tatami_id'],
            'order_in_pool' => $order, 'phase' => 'final', 'a_registration_id' => $regA, 'b_registration_id' => $regB,
            'duration_rule_id' => $rule['id'] ?? null, 'duration_seconds' => $rule['duration_seconds'] ?? 120,
            'golden_score_mode' => $rule['golden_score_mode'] ?? 'unlimited', 'golden_score_seconds' => $rule['golden_score_seconds'] ?? null,
            'recovery_seconds' => $rule['recovery_seconds'] ?? 0,
        ]);
        if ($pool['status'] === 'finished') {
            Database::run("UPDATE pools SET status = 'in_progress', ranking_complete = 0 WHERE id = ?", [$poolId]);
        }
        if ($pool['tatami_id']) {
            self::resequenceTatami((int) $pool['tatami_id']);
        }
        AuditLogger::log('bouts.add_tiebreak', 'bout', $id, null, ['pool_id' => $poolId, 'a' => $regA, 'b' => $regB], null, (int) $pool['competition_id']);
        RankingService::recomputePool($poolId);
        return $id;
    }

    /**
     * Ordre de passage des combats sur un tapis. Les poules se suivent dans leur ordre ;
     * avec le réglage « alterner les poules », les combats de deux poules consécutives sont entrelacés (récupération).
     */
    public static function resequenceTatami(int $tatamiId): void
    {
        $tatami = Database::one('SELECT competition_id FROM tatamis WHERE id = ?', [$tatamiId]);
        if (!$tatami) {
            return;
        }
        $interleave = (bool) SettingsService::get((int) $tatami['competition_id'], 'interleave_pools');
        $pools = Database::all(
            "SELECT p.id, p.age_category_id, p.sex FROM pools p JOIN pool_generations g ON g.id = p.generation_id
             WHERE p.tatami_id = ? AND g.status IN ('draft','validated') ORDER BY p.order_on_tatami, p.number",
            [$tatamiId]
        );
        $sequence = [];
        $done = Database::column("SELECT id FROM bouts WHERE tatami_id = ? AND status IN ('validated','finished','in_progress') ORDER BY order_on_tatami, id", [$tatamiId]);
        $sequence = array_map('intval', $done);
        $i = 0;
        while ($i < count($pools)) {
            $block = [$pools[$i]];
            if ($interleave && isset($pools[$i + 1]) && $pools[$i + 1]['age_category_id'] === $pools[$i]['age_category_id']) {
                $block[] = $pools[$i + 1];
            }
            $lists = [];
            foreach ($block as $p) {
                $lists[] = array_map('intval', Database::column("SELECT id FROM bouts WHERE pool_id = ? AND tatami_id = ? AND status = 'scheduled' ORDER BY phase = 'final', order_in_pool", [$p['id'], $tatamiId]));
            }
            $max = max(array_map('count', $lists) ?: [0]);
            for ($k = 0; $k < $max; $k++) {
                foreach ($lists as $l) {
                    if (isset($l[$k])) {
                        $sequence[] = $l[$k];
                    }
                }
            }
            $i += count($block);
        }
        foreach ($sequence as $pos => $boutId) {
            Database::run('UPDATE bouts SET order_on_tatami = ? WHERE id = ?', [$pos + 1, $boutId]);
        }
    }

    /** Alertes : écarts de poids, combattants d'un même club, poules hors tailles autorisées. */
    public static function warnings(int $competitionId, int $generationId): array
    {
        $out = [];
        $threshold = (float) SettingsService::get($competitionId, 'max_weight_spread_pct');
        $sizes = array_map('intval', explode(',', (string) Database::value('SELECT pool_sizes FROM competitions WHERE id = ?', [$competitionId])));
        foreach (self::pools($generationId) as $p) {
            if ($threshold > 0 && $p['spread_pct'] > $threshold) {
                $out[] = ['level' => 'warning', 'pool_id' => (int) $p['id'], 'message' => sprintf('« %s » : écart de poids de %.1f %% (seuil %.0f %%).', $p['label'], $p['spread_pct'], $threshold)];
            }
            if ($p['club_conflicts'] > 0) {
                $out[] = ['level' => 'info', 'pool_id' => (int) $p['id'], 'message' => sprintf('« %s » : %d combattant(s) du même club.', $p['label'], $p['club_conflicts'])];
            }
            if (!in_array($p['size'], $sizes, true)) {
                $out[] = ['level' => 'warning', 'pool_id' => (int) $p['id'], 'message' => sprintf('« %s » : poule de %d (tailles autorisées : %s).', $p['label'], $p['size'], implode(', ', $sizes))];
            }
        }
        foreach (DurationRuleService::configurationIssues($competitionId) as $issue) {
            $out[] = $issue;
        }
        return $out;
    }
}
