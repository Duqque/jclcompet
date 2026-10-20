<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Algorithme de composition des poules (sans accès base de données, entièrement testable).
 *
 * Pour un groupe compatible (même catégorie d'âge, même sexe, même catégorie de poids le cas échéant) :
 *  1. tri des combattants par poids de référence (mesuré si autorisé, sinon déclaré ; tirage au sort à défaut de poids) ;
 *  2. programmation dynamique sur les découpages contigus respectant les tailles autorisées ;
 *     coût = écart de poids relatif (%) + pénalité d'éloignement de la taille préférée (équilibre des poules) ;
 *  3. échanges entre poules voisines pour séparer les combattants d'un même club, sans dégrader le rapprochement morphologique.
 * Si aucune combinaison n'est possible, aucune poule n'est créée : les solutions possibles sont proposées
 * à l'administrateur (aucun athlète n'est écarté silencieusement).
 */
final class PoolGenerator
{
    public const SIZE_PENALTY = 6.0;
    public const SWAP_TOLERANCE_PCT = 6.0;

    /**
     * @param list<array{id:int, weight:?float, club_id:?int}> $athletes
     * @param int[] $sizes tailles autorisées
     * @return array{ok: bool, pools: list<list<array>>, cost: float, alternatives: list<array>, reason: ?string}
     */
    public static function partition(array $athletes, array $sizes, int $preferred = 4, bool $avoidSameClub = true, ?int $seed = null): array
    {
        $sizes = array_values(array_unique(array_map('intval', $sizes)));
        sort($sizes);
        $n = count($athletes);
        if ($n === 0) {
            return ['ok' => true, 'pools' => [], 'cost' => 0.0, 'alternatives' => [], 'reason' => null];
        }
        $sorted = self::sortByWeight($athletes, $seed);
        $best = self::bestPartition($sorted, $sizes, $preferred);
        if ($best === null) {
            return [
                'ok' => false,
                'pools' => [],
                'cost' => INF,
                'alternatives' => self::alternatives($sorted, $sizes, $preferred),
                'reason' => $n === 1
                    ? 'Un seul combattant dans ce groupe : aucune poule possible.'
                    : "Aucune répartition de $n combattants en poules de " . implode(', ', $sizes) . ' n\'est possible.',
            ];
        }
        $pools = $best['pools'];
        if ($avoidSameClub) {
            $pools = self::separateClubs($pools);
        }
        return ['ok' => true, 'pools' => $pools, 'cost' => $best['cost'], 'alternatives' => [], 'reason' => null];
    }

    /** Tri par poids croissant ; les combattants sans poids sont placés par tirage au sort (graine reproductible). */
    public static function sortByWeight(array $athletes, ?int $seed = null): array
    {
        $withWeight = array_values(array_filter($athletes, fn ($a) => $a['weight'] !== null));
        $without = array_values(array_filter($athletes, fn ($a) => $a['weight'] === null));
        usort($withWeight, fn ($a, $b) => [$a['weight'], $a['id']] <=> [$b['weight'], $b['id']]);
        usort($without, fn ($a, $b) => $a['id'] <=> $b['id']);
        if ($without) {
            mt_srand($seed ?? random_int(1, PHP_INT_MAX));
            for ($i = count($without) - 1; $i > 0; $i--) {
                $j = mt_rand(0, $i);
                [$without[$i], $without[$j]] = [$without[$j], $without[$i]];
            }
            mt_srand();
        }
        return array_merge($withWeight, $without);
    }

    /** Écart de poids relatif d'une poule, en % du plus léger (0 si poids inconnus). */
    public static function spreadPct(array $pool): float
    {
        $w = array_values(array_filter(array_column($pool, 'weight'), fn ($x) => $x !== null));
        if (count($w) < 2) {
            return 0.0;
        }
        $min = min($w);
        return $min > 0 ? (max($w) - $min) / $min * 100 : 0.0;
    }

    /** @return array{pools: list<list<array>>, cost: float}|null */
    public static function bestPartition(array $sorted, array $sizes, int $preferred): ?array
    {
        $n = count($sorted);
        if (!$sizes) {
            return null;
        }
        $dp = array_fill(0, $n + 1, INF);
        $prev = array_fill(0, $n + 1, -1);
        $dp[0] = 0.0;
        for ($i = 1; $i <= $n; $i++) {
            foreach ($sizes as $s) {
                if ($s > $i || $dp[$i - $s] === INF) {
                    continue;
                }
                $segment = array_slice($sorted, $i - $s, $s);
                $cost = $dp[$i - $s] + self::spreadPct($segment) + self::SIZE_PENALTY * ($s - $preferred) ** 2;
                // À coût égal, préférer les poules les plus grandes en premier (déterminisme).
                if ($cost < $dp[$i] - 1e-9) {
                    $dp[$i] = $cost;
                    $prev[$i] = $i - $s;
                }
            }
        }
        if ($dp[$n] === INF) {
            return null;
        }
        $pools = [];
        for ($i = $n; $i > 0; $i = $prev[$i]) {
            array_unshift($pools, array_slice($sorted, $prev[$i], $i - $prev[$i]));
        }
        return ['pools' => $pools, 'cost' => $dp[$n]];
    }

    /** Solutions proposées à l'administrateur quand les tailles autorisées ne permettent pas de répartir le groupe. */
    public static function alternatives(array $sorted, array $sizes, int $preferred): array
    {
        $n = count($sorted);
        $out = [];
        if ($n === 1) {
            $out[] = ['type' => 'merge', 'label' => 'Regrouper avec la catégorie de poids voisine (même âge, même sexe)'];
            $out[] = ['type' => 'unplaced', 'label' => 'Laisser l\'athlète hors poule (décision consignée)'];
            return $out;
        }
        foreach ([[2], [6], [2, 6]] as $extra) {
            $try = array_values(array_unique(array_merge($sizes, $extra)));
            $best = self::bestPartition($sorted, $try, $preferred);
            if ($best) {
                $counts = array_count_values(array_map('count', $best['pools']));
                krsort($counts);
                $parts = [];
                foreach ($counts as $size => $count) {
                    $parts[] = $count . ' poule' . ($count > 1 ? 's' : '') . ' de ' . $size;
                }
                $label = implode(' + ', $parts);
                if (!in_array($label, array_column($out, 'label'), true)) {
                    $out[] = ['type' => 'sizes', 'sizes' => $try, 'label' => $label];
                }
            }
        }
        $out[] = ['type' => 'merge', 'label' => 'Regrouper avec la catégorie de poids voisine (même âge, même sexe)'];
        $out[] = ['type' => 'unplaced', 'label' => 'Laisser ces athlètes hors poule (décision consignée)'];
        return $out;
    }

    /** Nombre de combattants d'un même club au-delà du premier, toutes poules confondues. */
    public static function clubConflicts(array $pools): int
    {
        $total = 0;
        foreach ($pools as $pool) {
            $clubs = array_filter(array_column($pool, 'club_id'), fn ($c) => $c !== null);
            foreach (array_count_values($clubs) as $count) {
                $total += $count - 1;
            }
        }
        return $total;
    }

    /**
     * Échanges entre poules voisines (ordre de poids) pour séparer les combattants d'un même club,
     * uniquement entre combattants de poids proches (tolérance SWAP_TOLERANCE_PCT).
     */
    public static function separateClubs(array $pools): array
    {
        $count = count($pools);
        if ($count < 2) {
            return $pools;
        }
        for ($iter = 0; $iter < 50; $iter++) {
            $improved = false;
            for ($p = 0; $p < $count - 1; $p++) {
                foreach ([[$p, $p + 1], [$p + 1, $p]] as [$a, $b]) {
                    foreach ($pools[$a] as $i => $x) {
                        if ($x['club_id'] === null || self::clubCount($pools[$a], $x['club_id']) < 2) {
                            continue;
                        }
                        foreach ($pools[$b] as $j => $y) {
                            if (!self::closeWeights($x['weight'], $y['weight']) || $y['club_id'] === $x['club_id']) {
                                continue;
                            }
                            $candidateA = $pools[$a];
                            $candidateB = $pools[$b];
                            $candidateA[$i] = $y;
                            $candidateB[$j] = $x;
                            $before = self::clubConflicts([$pools[$a], $pools[$b]]);
                            $after = self::clubConflicts([$candidateA, $candidateB]);
                            if ($after < $before) {
                                $pools[$a] = array_values($candidateA);
                                $pools[$b] = array_values($candidateB);
                                $improved = true;
                                continue 3;
                            }
                        }
                    }
                }
            }
            if (!$improved) {
                break;
            }
        }
        foreach ($pools as &$pool) {
            usort($pool, fn ($u, $v) => [$u['weight'] ?? INF, $u['id']] <=> [$v['weight'] ?? INF, $v['id']]);
        }
        return $pools;
    }

    private static function clubCount(array $pool, int $clubId): int
    {
        return count(array_filter($pool, fn ($a) => $a['club_id'] === $clubId));
    }

    private static function closeWeights(?float $a, ?float $b): bool
    {
        if ($a === null || $b === null) {
            return $a === null && $b === null;
        }
        $min = min($a, $b);
        return $min > 0 && abs($a - $b) / $min * 100 <= self::SWAP_TOLERANCE_PCT;
    }

    /** Nombre de combats d'une poule toutes rondes : n × (n − 1) / 2. */
    public static function boutCount(int $n): int
    {
        return intdiv($n * ($n - 1), 2);
    }

    /**
     * Ordre des combats d'une poule toutes rondes (indices 1..n) : ordre officiel des feuilles de poule de 3, 4 et 5.
     * @return list<array{0:int,1:int}>
     */
    public static function roundRobinOrder(int $n): array
    {
        return match ($n) {
            0, 1 => [],
            2 => [[1, 2]],
            3 => [[1, 2], [2, 3], [1, 3]],
            4 => [[1, 2], [3, 4], [1, 3], [2, 4], [1, 4], [2, 3]],
            5 => [[1, 2], [3, 4], [1, 5], [2, 4], [3, 5], [1, 4], [2, 3], [4, 5], [1, 3], [2, 5]],
            default => self::circleMethod($n),
        };
    }

    private static function circleMethod(int $n): array
    {
        $players = range(1, $n);
        if ($n % 2) {
            $players[] = 0;
        }
        $m = count($players);
        $order = [];
        for ($round = 0; $round < $m - 1; $round++) {
            for ($i = 0; $i < $m / 2; $i++) {
                $a = $players[$i];
                $b = $players[$m - 1 - $i];
                if ($a && $b) {
                    $order[] = [min($a, $b), max($a, $b)];
                }
            }
            $last = array_pop($players);
            array_splice($players, 1, 0, [$last]);
        }
        return $order;
    }
}
