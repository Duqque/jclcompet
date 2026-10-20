<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Classements, entièrement recalculables depuis les résultats validés :
 *  - classement de poule (victoires, points de valeur, confrontation directe, puis départage de l'administrateur) ;
 *  - places finales → points (barème) et médailles, uniquement pour les poules complètes ;
 *  - classement des clubs par points et, indépendamment, par médailles.
 */
final class RankingService
{
    /** Barème de démonstration (exemple modifiable, non officiel). */
    public const DEFAULT_RULES = [
        1 => [10, 'gold'], 2 => [7, 'silver'], 3 => [5, 'bronze'], 4 => [3, null], 5 => [2, null], 6 => [0, null], 7 => [1, null],
    ];

    public static function createDefaultRules(int $competitionId): void
    {
        foreach (self::DEFAULT_RULES as $place => [$points, $medal]) {
            Database::insert('ranking_rules', ['competition_id' => $competitionId, 'place' => $place, 'points' => $points, 'medal' => $medal]);
        }
    }

    public static function rules(int $competitionId): array
    {
        $rows = Database::all('SELECT * FROM ranking_rules WHERE competition_id = ? ORDER BY place', [$competitionId]);
        return array_column($rows, null, 'place');
    }

    /**
     * Calcule le classement d'une poule à partir de ses combats validés.
     * @return array{rows: list<array>, complete: bool, needs_tiebreak: bool}
     */
    public static function computePool(int $poolId): array
    {
        $pool = Database::one('SELECT * FROM pools WHERE id = ?', [$poolId]);
        $cid = (int) $pool['competition_id'];
        $cfg = (array) SettingsService::get($cid, 'pool_ranking');
        $values = (array) ($cfg['value_points'] ?? []);
        $tieHandling = (string) SettingsService::get($cid, 'tie_handling');
        $members = Database::all('SELECT registration_id, manual_rank, excluded_from_ranking FROM pool_athletes WHERE pool_id = ? ORDER BY seed', [$poolId]);
        $bouts = Database::all("SELECT * FROM bouts WHERE pool_id = ? AND status <> 'cancelled' ORDER BY order_in_pool", [$poolId]);
        $stats = [];
        foreach ($members as $m) {
            $stats[(int) $m['registration_id']] = [
                'registration_id' => (int) $m['registration_id'], 'wins' => 0, 'losses' => 0, 'value_points' => 0,
                'fought' => 0, 'forfeit_losses' => 0, 'manual_rank' => $m['manual_rank'] === null ? null : (int) $m['manual_rank'],
                'excluded' => (int) $m['excluded_from_ranking'] === 1,
            ];
        }
        $complete = count($members) >= 2 && $bouts !== [];
        $h2h = [];
        foreach ($bouts as $b) {
            if ($b['status'] !== 'validated') {
                $complete = false;
                continue;
            }
            $w = (int) $b[$b['winner_side'] . '_registration_id'];
            $l = (int) $b[($b['winner_side'] === 'a' ? 'b' : 'a') . '_registration_id'];
            if (!isset($stats[$w], $stats[$l])) {
                continue;
            }
            $stats[$w]['wins']++;
            $stats[$w]['value_points'] += (int) ($values[$b['win_method']] ?? 0);
            $stats[$l]['losses']++;
            if ($b['win_method'] === 'fusen_gachi') {
                $stats[$l]['forfeit_losses']++;
            } else {
                $stats[$w]['fought']++;
                $stats[$l]['fought']++;
            }
            // Le dernier combat validé entre deux athlètes (barrage éventuel) fait foi pour la confrontation directe.
            $h2h[min($w, $l) . '-' . max($w, $l)] = $w;
        }
        $rows = array_values($stats);
        usort($rows, fn ($x, $y) => [$y['wins'], $y['value_points']] <=> [$x['wins'], $x['value_points']]);

        // Groupes d'égalité (mêmes victoires et points), départagés par confrontation directe.
        $ordered = [];
        $needsTiebreak = false;
        $i = 0;
        while ($i < count($rows)) {
            $j = $i;
            while ($j + 1 < count($rows) && $rows[$j + 1]['wins'] === $rows[$i]['wins'] && $rows[$j + 1]['value_points'] === $rows[$i]['value_points']) {
                $j++;
            }
            $tied = array_slice($rows, $i, $j - $i + 1);
            foreach (self::breakTie($tied, $h2h) as $sub) {
                if (count($sub) > 1) {
                    // Égalité persistante : départage manuel (si saisi) ou places partagées selon le réglage.
                    $manual = array_filter(array_column($sub, 'manual_rank'), fn ($r) => $r !== null);
                    if (count($manual) === count($sub)) {
                        usort($sub, fn ($x, $y) => $x['manual_rank'] <=> $y['manual_rank']);
                        foreach ($sub as $r) {
                            $ordered[] = [$r];
                        }
                        continue;
                    }
                    if ($tieHandling !== 'shared') {
                        $needsTiebreak = true;
                    }
                }
                $ordered[] = $sub;
            }
            $i = $j + 1;
        }
        // Attribution des places (places partagées en cas d'égalité non départagée)
        $place = 1;
        $result = [];
        foreach ($ordered as $groupRows) {
            foreach ($groupRows as $r) {
                $r['rank'] = $place;
                $r['tied'] = count($groupRows) > 1;
                $result[] = $r;
            }
            $place += count($groupRows);
        }
        return ['rows' => $result, 'complete' => $complete, 'needs_tiebreak' => $complete && $needsTiebreak];
    }

    /** @return list<list<array>> sous-groupes ordonnés */
    private static function breakTie(array $tied, array $h2h): array
    {
        if (count($tied) === 1) {
            return [$tied];
        }
        if (count($tied) === 2) {
            $a = $tied[0]['registration_id'];
            $b = $tied[1]['registration_id'];
            $winner = $h2h[min($a, $b) . '-' . max($a, $b)] ?? null;
            if ($winner === null) {
                return [$tied];
            }
            return $winner === $a ? [[$tied[0]], [$tied[1]]] : [[$tied[1]], [$tied[0]]];
        }
        // Mini-classement des victoires entre athlètes à égalité
        $ids = array_column($tied, 'registration_id');
        $mini = array_fill_keys($ids, 0);
        foreach ($ids as $x) {
            foreach ($ids as $y) {
                if ($x < $y && isset($h2h[$x . '-' . $y])) {
                    $mini[$h2h[$x . '-' . $y]]++;
                }
            }
        }
        usort($tied, fn ($p, $q) => $mini[$q['registration_id']] <=> $mini[$p['registration_id']]);
        $groups = [];
        foreach ($tied as $r) {
            $k = $mini[$r['registration_id']];
            $groups[$k][] = $r;
        }
        krsort($groups);
        $out = [];
        foreach ($groups as $g) {
            if (count($g) === count($tied)) {
                $out[] = $g; // aucun départage possible par confrontation directe
            } else {
                foreach (self::breakTie($g, $h2h) as $sub) {
                    $out[] = $sub;
                }
            }
        }
        return $out;
    }

    /** Recalcule et enregistre le classement d'une poule, puis le classement des clubs. */
    public static function recomputePool(int $poolId, bool $withClubs = true): array
    {
        $pool = Database::one('SELECT * FROM pools WHERE id = ?', [$poolId]);
        if (!$pool) {
            return [];
        }
        $cid = (int) $pool['competition_id'];
        $calc = self::computePool($poolId);
        $final = $calc['complete'] && !$calc['needs_tiebreak'];
        foreach ($calc['rows'] as $r) {
            Database::run(
                'UPDATE pool_athletes SET wins = ?, losses = ?, value_points = ?, computed_rank = ?, final_rank = ? WHERE pool_id = ? AND registration_id = ?',
                [$r['wins'], $r['losses'], $r['value_points'], $r['rank'], $final ? $r['rank'] : null, $poolId, $r['registration_id']]
            );
        }
        $status = $pool['status'];
        if ($calc['complete'] && in_array($status, ['validated', 'in_progress'], true)) {
            $status = 'finished';
        } elseif (!$calc['complete'] && $status === 'finished') {
            $status = 'in_progress';
        }
        Database::run('UPDATE pools SET ranking_complete = ?, needs_tiebreak = ?, status = ? WHERE id = ?', [$final ? 1 : 0, $calc['needs_tiebreak'] ? 1 : 0, $status, $poolId]);

        // Places, points et médailles : jamais à partir d'un résultat incomplet.
        Database::run('DELETE FROM athlete_rankings WHERE pool_id = ?', [$poolId]);
        if ($final) {
            $rules = self::rules($cid);
            $policy = (string) SettingsService::get($cid, 'forfeit_policy');
            foreach ($calc['rows'] as $r) {
                if ($r['excluded']) {
                    continue;
                }
                $rule = $rules[$r['rank']] ?? null;
                $noPoints = $policy === 'no_points' && $r['fought'] === 0 && $r['forfeit_losses'] > 0;
                Database::insert('athlete_rankings', [
                    'competition_id' => $cid, 'pool_id' => $poolId, 'registration_id' => $r['registration_id'],
                    'club_id' => Database::value('SELECT club_id FROM registrations WHERE id = ?', [$r['registration_id']]),
                    'place' => $r['rank'],
                    'points' => $noPoints || !$rule || !(int) $rule['counts_in_table'] ? 0 : (int) $rule['points'],
                    'medal' => $noPoints || !$rule ? null : $rule['medal'],
                ]);
            }
        }
        if ($withClubs) {
            self::recomputeClubs($cid);
        }
        return $calc;
    }

    public static function recomputeCompetition(int $competitionId): void
    {
        $g = PoolService::activeGeneration($competitionId);
        if ($g) {
            foreach (Database::column('SELECT id FROM pools WHERE generation_id = ?', [$g['id']]) as $pid) {
                self::recomputePool((int) $pid, false);
            }
        }
        self::recomputeClubs($competitionId);
        AuditLogger::log('rankings.recompute', 'competition', $competitionId, null, null, null, $competitionId);
    }

    /** Classement des clubs : points (somme du barème) et médailles, deux classements indépendants. */
    public static function recomputeClubs(int $competitionId): void
    {
        $participants = Database::all(
            "SELECT club_id, COUNT(*) AS n FROM registrations WHERE competition_id = ? AND status = 'confirmed' AND attendance_status = 'present' AND club_id IS NOT NULL GROUP BY club_id",
            [$competitionId]
        );
        $agg = Database::all(
            "SELECT club_id, SUM(points) AS points, SUM(medal = 'gold') AS gold, SUM(medal = 'silver') AS silver, SUM(medal = 'bronze') AS bronze,
                    SUM(place = 4) AS fourth, SUM(place = 5) AS fifth, SUM(place > 5) AS other_places
             FROM athlete_rankings WHERE competition_id = ? AND club_id IS NOT NULL GROUP BY club_id",
            [$competitionId]
        );
        $clubs = [];
        foreach ($participants as $p) {
            $clubs[(int) $p['club_id']] = ['participants' => (int) $p['n'], 'points' => 0, 'gold' => 0, 'silver' => 0, 'bronze' => 0, 'fourth' => 0, 'fifth' => 0, 'other_places' => 0];
        }
        foreach ($agg as $a) {
            $id = (int) $a['club_id'];
            $clubs[$id] = array_merge($clubs[$id] ?? ['participants' => 0], [
                'points' => (int) $a['points'], 'gold' => (int) $a['gold'], 'silver' => (int) $a['silver'], 'bronze' => (int) $a['bronze'],
                'fourth' => (int) $a['fourth'], 'fifth' => (int) $a['fifth'], 'other_places' => (int) $a['other_places'],
            ]);
        }
        // Classement par points (rang partagé en cas d'égalité)
        $byPoints = $clubs;
        uasort($byPoints, fn ($x, $y) => $y['points'] <=> $x['points']);
        $rankPoints = self::competitionRanks($byPoints, fn ($c) => [$c['points']]);
        // Classement par médailles selon l'ordre configuré
        $order = (array) SettingsService::get($competitionId, 'medal_order');
        $key = fn ($c) => array_map(fn ($k) => (int) ($c[$k] ?? 0), $order);
        $byMedals = $clubs;
        uasort($byMedals, fn ($x, $y) => $key($y) <=> $key($x));
        $rankMedals = self::competitionRanks($byMedals, $key);

        Database::transaction(function () use ($competitionId, $clubs, $rankPoints, $rankMedals) {
            Database::run('DELETE FROM club_rankings WHERE competition_id = ?', [$competitionId]);
            foreach ($clubs as $clubId => $c) {
                Database::insert('club_rankings', [
                    'competition_id' => $competitionId, 'club_id' => $clubId, 'participants' => $c['participants'] ?? 0,
                    'points' => $c['points'], 'gold' => $c['gold'], 'silver' => $c['silver'], 'bronze' => $c['bronze'],
                    'fourth' => $c['fourth'], 'fifth' => $c['fifth'], 'other_places' => $c['other_places'],
                    'rank_points' => $rankPoints[$clubId], 'rank_medals' => $rankMedals[$clubId],
                ]);
            }
        });
    }

    /** Rangs « sportifs » : 1, 1, 3… */
    private static function competitionRanks(array $sorted, callable $key): array
    {
        $ranks = [];
        $i = 0;
        $prev = null;
        $rank = 0;
        foreach ($sorted as $id => $c) {
            $i++;
            $k = $key($c);
            if ($k !== $prev) {
                $rank = $i;
                $prev = $k;
            }
            $ranks[$id] = $rank;
        }
        return $ranks;
    }

    /** Départage manuel d'une égalité (administrateur). $ranks : registration_id => rang */
    public static function setManualRanks(int $poolId, array $ranks, string $reason): void
    {
        $pool = Database::one('SELECT * FROM pools WHERE id = ?', [$poolId]);
        CompetitionService::assertWritable((int) $pool['competition_id']);
        Database::transaction(function () use ($poolId, $ranks, $reason, $pool) {
            Database::run('UPDATE pool_athletes SET manual_rank = NULL WHERE pool_id = ?', [$poolId]);
            foreach ($ranks as $reg => $rank) {
                if ($rank !== null && $rank !== '') {
                    Database::run('UPDATE pool_athletes SET manual_rank = ? WHERE pool_id = ? AND registration_id = ?', [(int) $rank, $poolId, (int) $reg]);
                }
            }
            AuditLogger::log('rankings.tiebreak', 'pool', $poolId, null, $ranks, $reason, (int) $pool['competition_id']);
            self::recomputePool($poolId);
        });
    }

    public static function setExcluded(int $poolId, int $registrationId, bool $excluded, string $reason): void
    {
        $pool = Database::one('SELECT * FROM pools WHERE id = ?', [$poolId]);
        CompetitionService::assertWritable((int) $pool['competition_id']);
        Database::run('UPDATE pool_athletes SET excluded_from_ranking = ? WHERE pool_id = ? AND registration_id = ?', [$excluded ? 1 : 0, $poolId, $registrationId]);
        AuditLogger::log($excluded ? 'rankings.exclude' : 'rankings.include', 'registration', $registrationId, null, ['pool_id' => $poolId], $reason, (int) $pool['competition_id']);
        self::recomputePool($poolId);
    }

    // ------------------------------------------------------------------
    // Lecture
    // ------------------------------------------------------------------

    public static function clubTable(int $competitionId, string $by = 'points', bool $withAllClubs = false): array
    {
        $order = $by === 'medals' ? 'cr.rank_medals, c.name' : 'cr.rank_points, c.name';
        $rows = Database::all(
            "SELECT cr.*, c.name, c.short_name, c.city, c.department, c.logo_path FROM club_rankings cr JOIN clubs c ON c.id = cr.club_id
             WHERE cr.competition_id = ? ORDER BY $order",
            [$competitionId]
        );
        if (!$withAllClubs) {
            return $rows;
        }
        // Tous les clubs engagés apparaissent, même sans point : à égalité à la dernière place.
        $known = array_flip(array_map('intval', array_column($rows, 'club_id')));
        $others = Database::all(
            "SELECT c.id AS club_id, c.name, c.short_name, c.city, c.department, c.logo_path,
                    (SELECT COUNT(*) FROM registrations r WHERE r.competition_id = :c1 AND r.club_id = c.id AND r.status = 'confirmed' AND r.attendance_status = 'present') AS participants
             FROM clubs c
             WHERE c.id IN (SELECT club_id FROM registrations WHERE competition_id = :c2 AND status IN ('pending','confirmed') AND club_id IS NOT NULL
                            UNION SELECT club_id FROM competition_clubs WHERE competition_id = :c3)
             ORDER BY c.name",
            ['c1' => $competitionId, 'c2' => $competitionId, 'c3' => $competitionId]
        );
        $rank = $rows ? max(array_map(fn ($r) => (int) $r[$by === 'medals' ? 'rank_medals' : 'rank_points'], $rows)) + 1 : 1;
        if ($rows && (int) end($rows)['points'] === 0 && $by === 'points') {
            $rank = (int) end($rows)['rank_points'];
        }
        foreach ($others as $o) {
            if (isset($known[(int) $o['club_id']])) {
                continue;
            }
            $rows[] = $o + ['points' => 0, 'gold' => 0, 'silver' => 0, 'bronze' => 0, 'fourth' => 0, 'fifth' => 0, 'rank_points' => $rank, 'rank_medals' => $rank];
        }
        return $rows;
    }

    public static function individual(int $competitionId, ?int $clubId = null): array
    {
        $sql = "SELECT ar.*, a.first_name, a.last_name, a.sex, c.name AS club_name, p.label AS pool_label, p.number AS pool_number,
                       ac.name AS age_name, ac.name_m, ac.name_f, ac.sort_order AS age_sort, wc.label AS weight_label, wc.sort_order AS weight_sort
                FROM athlete_rankings ar
                JOIN registrations r ON r.id = ar.registration_id JOIN athletes a ON a.id = r.athlete_id
                LEFT JOIN clubs c ON c.id = ar.club_id
                JOIN pools p ON p.id = ar.pool_id JOIN age_categories ac ON ac.id = p.age_category_id
                LEFT JOIN weight_categories wc ON wc.id = p.weight_category_id
                WHERE ar.competition_id = ?";
        $params = [$competitionId];
        if ($clubId !== null) {
            $sql .= ' AND ar.club_id = ?';
            $params[] = $clubId;
        }
        return Database::all($sql . ' ORDER BY ac.sort_order, a.sex, wc.sort_order, p.number, ar.place', $params);
    }

    public static function medalTotals(int $competitionId): array
    {
        return Database::one(
            "SELECT COALESCE(SUM(medal = 'gold'),0) AS gold, COALESCE(SUM(medal = 'silver'),0) AS silver, COALESCE(SUM(medal = 'bronze'),0) AS bronze,
                    COUNT(*) AS ranked FROM athlete_rankings WHERE competition_id = ?",
            [$competitionId]
        ) ?? ['gold' => 0, 'silver' => 0, 'bronze' => 0, 'ranked' => 0];
    }

    public static function lastUpdate(int $competitionId): ?string
    {
        return Database::value('SELECT MAX(computed_at) FROM club_rankings WHERE competition_id = ?', [$competitionId]);
    }
}
