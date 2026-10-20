<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Répartition des poules sur les tapis (sans accès base de données).
 * 1. charge de chaque poule = nombre de combats × (durée + battement) ;
 * 2. poules triées par charge décroissante, affectées une à une au tapis le moins chargé (LPT) ;
 * 3. améliorations locales (déplacements puis échanges) tant que l'écart entre tapis le plus et le moins chargé diminue.
 * Les poules verrouillées gardent leur tapis. L'égalité parfaite n'est pas toujours possible : l'écart résiduel est affiché.
 */
final class MatDistributor
{
    /**
     * @param list<array{id:int, load:int, tatami_id:?int, locked:bool}> $pools
     * @param int[] $tatamiIds tapis disponibles (actifs)
     * @return array<int, int> poolId => tatamiId
     */
    public static function distribute(array $pools, array $tatamiIds): array
    {
        if (!$tatamiIds) {
            return [];
        }
        $base = array_fill_keys($tatamiIds, 0);
        $fixed = [];
        foreach ($pools as $p) {
            if ($p['locked'] && $p['tatami_id'] !== null && isset($base[$p['tatami_id']])) {
                $fixed[$p['id']] = $p['tatami_id'];
                $base[$p['tatami_id']] += $p['load'];
            }
        }
        $free = array_values(array_filter($pools, fn ($p) => !isset($fixed[$p['id']])));
        $byId = array_column($pools, null, 'id');

        // 1. Plus grande charge d'abord (LPT), puis améliorations locales.
        usort($free, fn ($a, $b) => [$b['load'], $a['id']] <=> [$a['load'], $b['id']]);
        $best = self::improve($byId, self::greedy($free, $base, $fixed));
        $bestScore = self::score(self::loadsOf($byId, $best, $tatamiIds));
        // 2. Essais supplémentaires (ordres aléatoires à graine fixe, résultat reproductible) : l'équilibre parfait
        //    n'est pas toujours atteignable par le glouton seul.
        $tries = count($free) > 1 ? min(200, intdiv(30000, max(1, count($free)))) : 0;
        mt_srand(20260926);
        for ($t = 0; $t < $tries && $bestScore[0] > 0; $t++) {
            $order = $free;
            for ($i = count($order) - 1; $i > 0; $i--) {
                $j = mt_rand(0, $i);
                [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
            }
            $cand = self::improve($byId, self::greedy($order, $base, $fixed));
            $score = self::score(self::loadsOf($byId, $cand, $tatamiIds));
            if ($score < $bestScore) {
                $best = $cand;
                $bestScore = $score;
            }
        }
        mt_srand();
        return $best;
    }

    private static function greedy(array $order, array $loads, array $assign): array
    {
        foreach ($order as $p) {
            $target = self::leastLoaded($loads);
            $assign[$p['id']] = $target;
            $loads[$target] += $p['load'];
        }
        return $assign;
    }

    /** Critère : écart max − min, puis somme des carrés (répartition la plus homogène). */
    private static function score(array $loads): array
    {
        return [self::spread($loads), array_sum(array_map(fn ($l) => $l * $l, $loads))];
    }

    private static function loadsOf(array $byId, array $assign, array $tatamiIds): array
    {
        $loads = array_fill_keys($tatamiIds, 0);
        foreach ($assign as $pid => $t) {
            $loads[$t] += $byId[$pid]['load'];
        }
        return $loads;
    }

    private static function leastLoaded(array $loads): int
    {
        $min = min($loads);
        foreach ($loads as $id => $l) {
            if ($l === $min) {
                return (int) $id;
            }
        }
        return (int) array_key_first($loads);
    }

    public static function spread(array $loads): int
    {
        return $loads ? max($loads) - min($loads) : 0;
    }

    /** Déplacements et échanges entre deux tapis quelconques tant que le critère s'améliore strictement. */
    private static function improve(array $byId, array $assign): array
    {
        $tatamis = array_values(array_unique($assign));
        $loads = [];
        foreach ($assign as $pid => $t) {
            $loads[$t] = ($loads[$t] ?? 0) + $byId[$pid]['load'];
        }
        $movable = array_values(array_filter(array_keys($assign), fn ($pid) => !$byId[$pid]['locked']));
        for ($iter = 0; $iter < 500; $iter++) {
            $current = self::score($loads);
            $bestMove = null;
            foreach ($movable as $pid) {
                $from = $assign[$pid];
                $l = $byId[$pid]['load'];
                foreach (array_keys($loads) as $to) {
                    if ($to === $from) {
                        continue;
                    }
                    $trial = $loads;
                    $trial[$from] -= $l;
                    $trial[$to] += $l;
                    $sc = self::score($trial);
                    if ($sc < $current && ($bestMove === null || $sc < $bestMove['score'])) {
                        $bestMove = ['score' => $sc, 'moves' => [[$pid, $to]], 'loads' => $trial];
                    }
                }
                foreach ($movable as $qid) {
                    $u = $assign[$qid];
                    if ($u === $from || $qid <= $pid) {
                        continue;
                    }
                    $m = $byId[$qid]['load'];
                    $trial = $loads;
                    $trial[$from] += $m - $l;
                    $trial[$u] += $l - $m;
                    $sc = self::score($trial);
                    if ($sc < $current && ($bestMove === null || $sc < $bestMove['score'])) {
                        $bestMove = ['score' => $sc, 'moves' => [[$pid, $u], [$qid, $from]], 'loads' => $trial];
                    }
                }
            }
            if ($bestMove === null) {
                break;
            }
            foreach ($bestMove['moves'] as [$pid, $t]) {
                $assign[$pid] = $t;
            }
            $loads = $bestMove['loads'];
        }
        return $assign;
    }

    /** Charges résultantes par tapis. */
    public static function loads(array $pools, array $assign, array $tatamiIds): array
    {
        $loads = array_fill_keys($tatamiIds, 0);
        foreach ($pools as $p) {
            if (isset($assign[$p['id']], $loads[$assign[$p['id']]])) {
                $loads[$assign[$p['id']]] += $p['load'];
            }
        }
        return $loads;
    }
}
