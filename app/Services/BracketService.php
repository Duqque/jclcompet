<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;

/**
 * Formule « tableaux officiels » (catégories de poids officielles France Judo), par catégorie de poids :
 *  - 1 combattant : vainqueur sans combat ;
 *  - 2 à 5 : une poule (toutes rondes), classement 1 à 5 ;
 *  - 6, 7, 8 : deux poules (3+3, 4+3, 4+4) puis tableau final croisé : 1er A – 2e B, 1er B – 2e A, finale ;
 *  - 9 et plus : tableau à double repêchage (16, 32, 64 ou 128 cases), jusqu'à 8 têtes de série.
 *
 * Double repêchage : chaque demi-finaliste « repêche » les combattants qu'il a battus dans son quart de tableau.
 * Ils combattent dans l'ordre des tours (perdant du 1er tour contre perdant du 2e, le vainqueur contre le perdant
 * du tour suivant…). Les vainqueurs des repêchages des deux quarts d'une même moitié se rencontrent ; le vainqueur
 * rencontre pour la 3e place le perdant de la demi-finale de l'AUTRE moitié. Les combats pour la 3e place se
 * disputent avant la finale. Classement : 1, 2, 3, 3, 5, 5, 7, 7, puis non classés (NC).
 *
 * Les combats sont créés au fur et à mesure (dès que les deux adversaires sont connus) : le reste du tableau est
 * calculé à partir du tirage et des résultats validés, à chaque validation ou correction (refresh()).
 */
final class BracketService
{
    /** Huitièmes de tableau dans l'ordre du tirage, par tête de série : en quart 1–8, 4–5, 3–6, 7–2 ; 1 en haut, 2 en bas. */
    public const EIGHTHS = [1, 8, 4, 5, 3, 6, 7, 2];

    // ------------------------------------------------------------------ Formule

    /** @return array{type:string, pools?:list<int>, size?:int} */
    public static function plan(int $n): array
    {
        return match (true) {
            $n <= 1 => ['type' => 'single', 'pools' => [max(0, $n)]],
            $n <= 5 => ['type' => 'pool', 'pools' => [$n]],
            $n === 6 => ['type' => 'pools_final', 'pools' => [3, 3]],
            $n === 7 => ['type' => 'pools_final', 'pools' => [4, 3]],
            $n === 8 => ['type' => 'pools_final', 'pools' => [4, 4]],
            default => ['type' => 'bracket', 'size' => self::sizeFor($n)],
        };
    }

    public static function sizeFor(int $n): int
    {
        $size = 16;
        while ($size < $n) {
            $size *= 2;
        }
        return $size;
    }

    public static function planLabel(int $n): string
    {
        $p = self::plan($n);
        return match ($p['type']) {
            'single' => 'Seul(e) dans la catégorie',
            'pool' => 'Poule de ' . $n,
            'pools_final' => '2 poules (' . implode(' + ', $p['pools']) . ') puis tableau final croisé',
            default => 'Tableau à double repêchage (' . $p['size'] . ' cases)',
        };
    }

    public static function roundLabel(int $round, int $rounds): string
    {
        return match ($rounds - $round) {
            0 => 'Finale',
            1 => 'Demi-finale',
            2 => 'Quart de finale',
            default => (2 ** ($rounds - $round)) . 'e de finale',
        };
    }

    // ------------------------------------------------------------------ Génération (appelée par PoolService::generate)

    /**
     * Compose une catégorie de poids en formule officielle et enregistre ses poules / tableau. $number est incrémenté.
     * @param array{key:string,label:string,age_category_id:int,sex:string,weight_category_id:?int,athletes:list<array>} $group
     */
    public static function generateGroup(array $competition, int $genId, array $group, int &$number, int $rngSeed): array
    {
        $n = count($group['athletes']);
        $plan = self::plan($n);
        $entry = ['key' => $group['key'], 'label' => $group['label'], 'count' => $n, 'status' => 'ok', 'pools' => [], 'issue' => null,
            'decision' => null, 'formula' => self::planLabel($n)];
        $seed = crc32($group['key']) ^ $rngSeed;
        if ($plan['type'] === 'pool' || $plan['type'] === 'single') {
            $number++;
            $entry['pools'][] = self::insert($competition, $genId, $group, $number, $plan['type'] === 'single' ? 'bracket' : 'pool',
                $group['label'] . ($plan['type'] === 'single' ? ' · Tableau' : ' · Poule ' . $number), self::slotsInOrder($group['athletes']), $plan['type'] === 'single' ? 1 : null);
            return $entry;
        }
        if ($plan['type'] === 'pools_final') {
            [$a, $b] = self::splitTwoPools($group['athletes'], $plan['pools'], $seed);
            foreach ([$a, $b] as $members) {
                $number++;
                $entry['pools'][] = self::insert($competition, $genId, $group, $number, 'pool', $group['label'] . ' · Poule ' . $number, self::slotsInOrder($members), null);
            }
            $number++;
            $entry['pools'][] = self::insert($competition, $genId, $group, $number, 'final_block', $group['label'] . ' · Tableau final', [], null);
            return $entry;
        }
        $seeds = self::seedsFor((int) $competition['id'], $group['key']);
        $slots = self::draw($group['athletes'], $seeds, $plan['size'], $seed);
        $number++;
        $entry['pools'][] = self::insert($competition, $genId, $group, $number, 'bracket', $group['label'] . ' · Tableau', $slots, $plan['size'], $seeds);
        return $entry;
    }

    /** @return array<int, ?int> */
    private static function slotsInOrder(array $athletes): array
    {
        $out = [];
        foreach (array_values($athletes) as $i => $a) {
            $out[$i] = (int) $a['id'];
        }
        return $out;
    }

    /** Deux poules de tailles données, en séparant au mieux les clubs. @return array{0:list<array>,1:list<array>} */
    private static function splitTwoPools(array $athletes, array $sizes, int $rngSeed): array
    {
        mt_srand($rngSeed);
        $athletes = self::shuffled($athletes);
        $clubCount = array_count_values(array_map(fn ($a) => (string) ($a['club_id'] ?? 'x' . $a['id']), $athletes));
        usort($athletes, fn ($x, $y) => ($clubCount[(string) ($y['club_id'] ?? 'x' . $y['id'])] ?? 0) <=> ($clubCount[(string) ($x['club_id'] ?? 'x' . $x['id'])] ?? 0));
        $pools = [[], []];
        foreach ($athletes as $a) {
            $best = null;
            foreach ([0, 1] as $p) {
                if (count($pools[$p]) >= $sizes[$p]) {
                    continue;
                }
                $same = count(array_filter($pools[$p], fn ($m) => $a['club_id'] !== null && $m['club_id'] === $a['club_id']));
                $score = $same * 10 + count($pools[$p]);
                if ($best === null || $score < $best[1]) {
                    $best = [$p, $score];
                }
            }
            $pools[$best[0]][] = $a;
        }
        return [$pools[0], $pools[1]];
    }

    private static function shuffled(array $list): array
    {
        $list = array_values($list);
        for ($i = count($list) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
        }
        return $list;
    }

    /**
     * Tirage d'un tableau : têtes de série dans leur huitième, exemptions (tours offerts) d'abord en face des têtes de
     * série puis réparties dans les huitièmes, puis les autres combattants au hasard en séparant les clubs
     * (même premier combat, même quart, même moitié).
     * @param list<array{id:int,club_id:?int}> $athletes
     * @param array<int,int> $seeds registration_id => tête de série (1..8)
     * @return array<int, ?int> case (0..size-1) => registration_id, ou null (exempt)
     */
    public static function draw(array $athletes, array $seeds, int $size, int $rngSeed): array
    {
        mt_srand($rngSeed);
        $club = [];
        foreach ($athletes as $a) {
            $club[(int) $a['id']] = $a['club_id'] === null ? null : (int) $a['club_id'];
        }
        $free = -1; // case libre
        $slots = array_fill(0, $size, $free);
        $block = intdiv($size, 8);
        $seedSlot = [];
        $blockOfSeed = [];
        foreach (self::EIGHTHS as $b => $s) {
            $seedSlot[$s] = $b < 4 ? $b * $block : ($b + 1) * $block - 1;
            $blockOfSeed[$s] = $b;
        }
        // Têtes de série présentes, renumérotées 1..k dans l'ordre choisi
        $seeded = array_filter($seeds, fn ($s, $id) => isset($club[(int) $id]) && $s >= 1 && $s <= 8, ARRAY_FILTER_USE_BOTH);
        asort($seeded);
        $seededIds = array_map('intval', array_keys($seeded));
        foreach ($seededIds as $k => $id) {
            $slots[$seedSlot[$k + 1]] = $id;
        }
        $byes = $size - count($athletes);
        foreach (array_keys($seededIds) as $k) {
            if ($byes <= 0) {
                break;
            }
            $slots[$seedSlot[$k + 1] ^ 1] = null;
            $byes--;
        }
        // Exemptions restantes réparties huitième par huitième (ordre des têtes de série)
        while ($byes > 0) {
            $placed = false;
            for ($s = 1; $s <= 8 && $byes > 0; $s++) {
                $b = $blockOfSeed[$s];
                $pairs = [];
                for ($p = $b * $block; $p < ($b + 1) * $block; $p += 2) {
                    $pairs[] = $p;
                }
                if ($b >= 4) {
                    $pairs = array_reverse($pairs);
                }
                foreach ($pairs as $p) {
                    if ($slots[$p] === $free && $slots[$p + 1] === $free) {
                        $slots[$b < 4 ? $p + 1 : $p] = null;
                        $byes--;
                        $placed = true;
                        break;
                    }
                }
            }
            if (!$placed) {
                break;
            }
        }
        // Autres combattants : clubs les plus représentés d'abord, case qui sépare le mieux les clubs
        $others = self::shuffled(array_values(array_filter($athletes, fn ($a) => !in_array((int) $a['id'], $seededIds, true))));
        $count = array_count_values(array_map(fn ($a) => (string) ($a['club_id'] ?? 'x'), $others));
        $weight = fn ($a) => $a['club_id'] === null ? 0 : ($count[(string) $a['club_id']] ?? 0);
        usort($others, fn ($x, $y) => $weight($y) <=> $weight($x));
        $quarter = intdiv($size, 4);
        $half = intdiv($size, 2);
        foreach ($others as $a) {
            $id = (int) $a['id'];
            $c = $club[$id];
            $best = [];
            $bestScore = PHP_INT_MAX;
            foreach ($slots as $i => $v) {
                if ($v !== $free) {
                    continue;
                }
                $score = 0;
                if ($c !== null) {
                    $partner = $slots[$i ^ 1];
                    if (is_int($partner) && $partner > 0 && ($club[$partner] ?? null) === $c) {
                        $score += 1000;
                    }
                    for ($j = intdiv($i, $quarter) * $quarter, $end = $j + $quarter; $j < $end; $j++) {
                        if (is_int($slots[$j]) && $slots[$j] > 0 && ($club[$slots[$j]] ?? null) === $c) {
                            $score += 100;
                        }
                    }
                    for ($j = intdiv($i, $half) * $half, $end = $j + $half; $j < $end; $j++) {
                        if (is_int($slots[$j]) && $slots[$j] > 0 && ($club[$slots[$j]] ?? null) === $c) {
                            $score += 10;
                        }
                    }
                }
                if ($score < $bestScore) {
                    $bestScore = $score;
                    $best = [$i];
                } elseif ($score === $bestScore) {
                    $best[] = $i;
                }
            }
            $slots[$best[mt_rand(0, count($best) - 1)]] = $id;
        }
        foreach ($slots as $i => $v) {
            if ($v === $free) {
                $slots[$i] = null;
            }
        }
        return self::separateClubs($slots, $club, $seededIds);
    }

    /**
     * Coût « clubs » d'un placement : même club au premier combat réel (premier tour, ou adversaire direct après un
     * tour offert) très pénalisé, puis même club dans le même quart, puis dans la même moitié.
     * @param array<int, ?int> $slots @param array<int, ?int> $club
     */
    public static function clubCost(array $slots, array $club): int
    {
        $size = count($slots);
        $same = fn ($a, $b) => $a !== null && $b !== null && ($club[$a] ?? null) !== null && ($club[$a] ?? null) === ($club[$b] ?? null);
        $cost = 0;
        for ($p = 0; $p < $size; $p += 2) {
            [$a, $b] = [$slots[$p], $slots[$p + 1]];
            if ($same($a, $b)) {
                $cost += 10000;
            }
            // Tour offert : le premier adversaire vient de la paire voisine
            $alone = $a ?? $b;
            if ($size >= 4 && ($a === null) !== ($b === null) && $p % 4 === 0) {
                [$c, $d] = [$slots[$p + 2], $slots[$p + 3]];
                if (($c === null) !== ($d === null)) {
                    $cost += $same($alone, $c ?? $d) ? 10000 : 0; // deux exemptés face à face
                } else {
                    $cost += ($same($alone, $c) ? 3000 : 0) + ($same($alone, $d) ? 3000 : 0);
                }
            } elseif ($size >= 4 && ($a === null) === ($b === null) && $a !== null && $p % 4 === 2) {
                // paire complète dont la voisine (avant) a un exempté
                [$c, $d] = [$slots[$p - 2], $slots[$p - 1]];
                if (($c === null) !== ($d === null)) {
                    $cost += ($same($c ?? $d, $a) ? 3000 : 0) + ($same($c ?? $d, $b) ? 3000 : 0);
                }
            } elseif ($size >= 4 && ($a === null) !== ($b === null) && $p % 4 === 2) {
                [$c, $d] = [$slots[$p - 2], $slots[$p - 1]];
                if (($c === null) === ($d === null) && $c !== null) {
                    $cost += ($same($alone, $c) ? 3000 : 0) + ($same($alone, $d) ? 3000 : 0);
                }
            }
        }
        foreach ([[intdiv($size, 4), 100], [intdiv($size, 2), 10]] as [$len, $w]) {
            if ($len < 4) {
                continue;
            }
            for ($s = 0; $s < $size; $s += $len) {
                $ids = array_values(array_filter(array_slice($slots, $s, $len), fn ($v) => $v !== null));
                $n = count($ids);
                for ($i = 0; $i < $n; $i++) {
                    for ($j = $i + 1; $j < $n; $j++) {
                        $cost += $same($ids[$i], $ids[$j]) ? $w : 0;
                    }
                }
            }
        }
        return $cost;
    }

    /**
     * Amélioration du tirage : échanges entre combattants non têtes de série tant qu'ils diminuent le coût « clubs »
     * (éviter au maximum deux combattants du même club au premier combat). Les exemptions et têtes de série restent en place.
     * @param array<int, ?int> $slots @param array<int, ?int> $club @param int[] $fixed
     */
    public static function separateClubs(array $slots, array $club, array $fixed = []): array
    {
        $movable = [];
        foreach ($slots as $i => $v) {
            if ($v !== null && !in_array($v, $fixed, true)) {
                $movable[] = $i;
            }
        }
        $cost = self::clubCost($slots, $club);
        for ($pass = 0; $pass < 30 && $cost > 0; $pass++) {
            $improved = false;
            $order = self::shuffled($movable);
            foreach ($order as $x) {
                foreach ($order as $y) {
                    if ($x >= $y || ($club[$slots[$x]] ?? null) === ($club[$slots[$y]] ?? null)) {
                        continue;
                    }
                    $try = $slots;
                    [$try[$x], $try[$y]] = [$try[$y], $try[$x]];
                    $c = self::clubCost($try, $club);
                    if ($c < $cost) {
                        $slots = $try;
                        $cost = $c;
                        $improved = true;
                    }
                }
            }
            if (!$improved) {
                break;
            }
        }
        return $slots;
    }

    /** Têtes de série choisies par l'administrateur pour une catégorie (clé de groupe). @return array<int,int> */
    public static function seedsFor(int $competitionId, string $groupKey): array
    {
        $all = (array) SettingsService::get($competitionId, 'bracket_seeds');
        $out = [];
        foreach ((array) ($all[$groupKey] ?? []) as $reg => $s) {
            $out[(int) $reg] = (int) $s;
        }
        return $out;
    }

    /** @param array<int, ?int> $slots case => inscription (null = exempt) */
    private static function insert(array $competition, int $genId, array $group, int $number, string $kind, string $label, array $slots, ?int $size, array $seeds = []): int
    {
        $weights = [];
        $byReg = [];
        foreach ($group['athletes'] as $a) {
            $byReg[(int) $a['id']] = $a;
            if ($a['weight'] !== null) {
                $weights[] = (float) $a['weight'];
            }
        }
        $poolId = Database::insert('pools', [
            'competition_id' => (int) $competition['id'], 'generation_id' => $genId, 'group_key' => $group['key'],
            'age_category_id' => $group['age_category_id'], 'sex' => $group['sex'], 'weight_category_id' => $group['weight_category_id'],
            'number' => $number, 'label' => mb_substr($label, 0, 80), 'kind' => $kind, 'bracket_size' => $size,
            'weight_min_kg' => $weights ? min($weights) : null, 'weight_max_kg' => $weights ? max($weights) : null, 'status' => 'draft',
        ]);
        foreach ($slots as $slot => $reg) {
            if ($reg === null) {
                continue;
            }
            Database::insert('pool_athletes', [
                'pool_id' => $poolId, 'registration_id' => $reg, 'generation_id' => $genId, 'seed' => $slot + 1,
                'draw_seed' => $seeds[$reg] ?? null, 'reference_weight_kg' => $byReg[$reg]['weight'] ?? null,
            ]);
        }
        PoolService::refreshEstimate($poolId);
        return $poolId;
    }

    /** Combats estimés (charge des tapis) : tableau = n - 1 + repêchages et places de 3e ; tableau final = 3. */
    public static function estimatedBouts(array $pool, int $n): int
    {
        return match ($pool['kind']) {
            'final_block' => 3,
            'bracket' => $n <= 1 ? 0 : $n - 1 + 6,
            default => PoolGenerator::boutCount($n),
        };
    }

    /**
     * Enregistre les têtes de série d'un tableau (1..8, chacune une seule fois). @return array<int,int>
     */
    public static function saveSeeds(int $poolId, array $seeds): array
    {
        $pool = PoolService::pool($poolId);
        CompetitionService::assertWritable((int) $pool['competition_id']);
        $seeds = array_filter(array_map('intval', $seeds), fn ($s) => $s >= 1 && $s <= 8);
        if (count($seeds) !== count(array_unique($seeds))) {
            throw new DomainError('Chaque numéro de tête de série ne peut être donné qu\'une fois.');
        }
        $members = array_map('intval', Database::column('SELECT registration_id FROM pool_athletes WHERE pool_id = ?', [$poolId]));
        $seeds = array_intersect_key($seeds, array_flip($members));
        $cid = (int) $pool['competition_id'];
        $all = (array) SettingsService::get($cid, 'bracket_seeds');
        $all[$pool['group_key']] = $seeds;
        SettingsService::set($cid, 'bracket_seeds', $all);
        return $seeds;
    }

    /**
     * Nouveau tirage d'un tableau (têtes de série mises à jour), tant qu'aucun de ses combats n'a commencé.
     * @param array<int,int> $seeds registration_id => tête de série (1..8), les autres sont ignorés
     */
    /**
     * Refait le tirage d'un tableau.
     * $mode null : premier tirage (refusé si le tirage est validé ou si des combats ont commencé) ;
     * 'reset' : tout recommencer (les combats déjà disputés sont supprimés, avec trace dans le journal) ;
     * 'keep'  : les combattants des combats déjà disputés et les têtes de série gardent leur case, les autres sont retirés.
     * Dans les deux cas le tableau redevient « à valider ».
     */
    public static function redraw(int $poolId, array $seeds, ?string $mode = null): void
    {
        $pool = PoolService::pool($poolId);
        CompetitionService::assertWritable((int) $pool['competition_id']);
        if ($pool['kind'] !== 'bracket') {
            throw new DomainError('Seul un tableau à double repêchage se tire au sort.');
        }
        if (Database::value("SELECT 1 FROM bouts WHERE pool_id = ? AND status = 'in_progress' LIMIT 1", [$poolId])) {
            throw new DomainError('Un combat de ce tableau est en cours sur un tapis : attendez sa fin pour refaire le tirage.');
        }
        $played = Database::all("SELECT id, bracket_key, a_registration_id, b_registration_id, status FROM bouts WHERE pool_id = ? AND status IN ('finished','validated')", [$poolId]);
        if ($mode === null) {
            if ($played) {
                throw new DomainError('Des combats de ce tableau ont été disputés : choisissez de les garder ou de tout recommencer.');
            }
            if ((int) $pool['is_locked'] === 1) {
                throw new DomainError('Ce tirage a été validé : utilisez « Refaire le tirage ».');
            }
        } elseif (!in_array($mode, ['reset', 'keep'], true)) {
            throw new DomainError('Choix de tirage inconnu.');
        }
        $keep = $mode === 'keep' && $played;
        $seeds = $keep ? self::seedsFor((int) $pool['competition_id'], $pool['group_key']) : self::saveSeeds($poolId, $seeds);
        $members = Database::all(
            'SELECT pa.registration_id AS id, pa.seed AS slot, pa.reference_weight_kg AS weight, r.club_id FROM pool_athletes pa JOIN registrations r ON r.id = pa.registration_id WHERE pa.pool_id = ?',
            [$poolId]
        );
        $athletes = array_map(fn ($m) => ['id' => (int) $m['id'], 'club_id' => $m['club_id'] === null ? null : (int) $m['club_id'], 'weight' => $m['weight']], $members);
        $size = (int) $pool['bracket_size'];
        mt_srand(random_int(1, 2_000_000_000));
        if ($keep) {
            // Combattants déjà engagés (et têtes de série) à leur place ; les exemptions restent où elles sont
            $fixed = [];
            foreach ($played as $b) {
                $fixed[(int) $b['a_registration_id']] = true;
                $fixed[(int) $b['b_registration_id']] = true;
            }
            foreach (array_keys($seeds) as $id) {
                $fixed[(int) $id] = true;
            }
            $slots = array_fill(0, $size, null);
            $free = [];
            $movers = [];
            foreach ($members as $m) {
                $slot = (int) $m['slot'] - 1;
                if (isset($fixed[(int) $m['id']])) {
                    $slots[$slot] = (int) $m['id'];
                } else {
                    $free[] = $slot;
                    $movers[] = (int) $m['id'];
                }
            }
            foreach (self::shuffled($movers) as $k => $id) {
                $slots[$free[$k]] = $id;
            }
            $club = array_column($athletes, 'club_id', 'id');
            $slots = self::separateClubs($slots, $club, array_map('intval', array_keys($fixed)));
        } else {
            $slots = self::draw($athletes, $seeds, $size, mt_rand());
        }
        Database::transaction(function () use ($pool, $slots, $seeds, $poolId, $mode, $keep, $played) {
            if ($mode === 'reset' && $played) {
                Database::run('DELETE FROM bouts WHERE pool_id = ?', [$poolId]);
            } else {
                Database::run("DELETE FROM bouts WHERE pool_id = ? AND status = 'scheduled'", [$poolId]);
            }
            if (!$keep && in_array($pool['status'], ['in_progress', 'finished'], true)) {
                Database::run("UPDATE pools SET status = 'validated' WHERE id = ?", [$poolId]);
            }
            Database::run('UPDATE pools SET is_locked = 0 WHERE id = ?', [$poolId]);
            Database::run('UPDATE pool_athletes SET seed = seed + 1000 WHERE pool_id = ?', [$poolId]);
            foreach ($slots as $slot => $reg) {
                if ($reg !== null) {
                    Database::run('UPDATE pool_athletes SET seed = ?, draw_seed = ? WHERE pool_id = ? AND registration_id = ?', [$slot + 1, $seeds[$reg] ?? null, $poolId, $reg]);
                }
            }
            self::refresh(PoolService::pool($poolId));
            if ($played) {
                RankingService::recomputeClubs((int) $pool['competition_id']);
            }
            AuditLogger::log('bracket.redraw', 'pool', $poolId, $played ? ['played_bouts' => $played] : null,
                ['seeds' => $seeds, 'mode' => $mode ?? 'first', 'kept_bouts' => $keep ? count($played) : 0], null, (int) $pool['competition_id']);
        });
    }

    // ------------------------------------------------------------------ Tableau calculé

    private static function reg(int $id): array
    {
        return ['reg' => $id];
    }

    private static function bye(): array
    {
        return ['bye' => true];
    }

    private static function pending(string $text): array
    {
        return ['pending' => $text];
    }

    private static function isReg(array $p): bool
    {
        return isset($p['reg']);
    }

    /** Résout un combat : vainqueur et perdant (inscription), exemption ou « en attente ». */
    private static function node(string $key, string $stage, int $round, string $label, array $a, array $b, array $bouts): array
    {
        $node = ['key' => $key, 'stage' => $stage, 'round' => $round, 'label' => $label, 'a' => $a, 'b' => $b, 'bout' => null,
            'winner' => self::pending('Vainqueur · ' . $label), 'loser' => self::pending('Perdant · ' . $label), 'fight' => false];
        if (self::isReg($a) && self::isReg($b)) {
            $node['fight'] = true;
            $bout = $bouts[$key] ?? null;
            if ($bout) {
                $node['bout'] = $bout;
                $same = [(int) $bout['a_registration_id'], (int) $bout['b_registration_id']];
                sort($same);
                $want = [$a['reg'], $b['reg']];
                sort($want);
                if ($same === $want && $bout['status'] === 'validated' && in_array($bout['winner_side'], ['a', 'b'], true)) {
                    $w = (int) $bout[$bout['winner_side'] . '_registration_id'];
                    $node['winner'] = self::reg($w);
                    $node['loser'] = self::reg($w === $a['reg'] ? $b['reg'] : $a['reg']);
                }
            }
        } elseif (self::isReg($a) && isset($b['bye'])) {
            $node['winner'] = $a;
            $node['loser'] = self::bye();
        } elseif (self::isReg($b) && isset($a['bye'])) {
            $node['winner'] = $b;
            $node['loser'] = self::bye();
        } elseif (isset($a['bye']) && isset($b['bye'])) {
            $node['winner'] = self::bye();
            $node['loser'] = self::bye();
        }
        return $node;
    }

    /** Combats existants du tableau, par clé de position. */
    private static function boutsByKey(int $poolId): array
    {
        $out = [];
        foreach (Database::all("SELECT * FROM bouts WHERE pool_id = ? AND status <> 'cancelled' AND bracket_key IS NOT NULL", [$poolId]) as $b) {
            $out[$b['bracket_key']] = $b;
        }
        return $out;
    }

    /**
     * Tableau complet calculé : sections (tableau principal par tour, repêchages, places de 3e, finale), places.
     * @return array{kind:string, sections:list<array>, places:array<int,int>, complete:bool, nodes:list<array>}
     */
    public static function structure(array $pool): array
    {
        return $pool['kind'] === 'final_block' ? self::finalBlockStructure($pool) : self::bracketStructure($pool);
    }

    private static function bracketStructure(array $pool): array
    {
        $poolId = (int) $pool['id'];
        $size = (int) ($pool['bracket_size'] ?: 1);
        $bouts = self::boutsByKey($poolId);
        $slots = array_fill(0, max(2, $size), null);
        foreach (Database::all('SELECT registration_id, seed FROM pool_athletes WHERE pool_id = ?', [$poolId]) as $pa) {
            $slots[(int) $pa['seed'] - 1] = (int) $pa['registration_id'];
        }
        if ($size < 2) {
            // Seul dans la catégorie : vainqueur sans combat
            $only = array_values(array_filter($slots));
            return ['kind' => 'bracket', 'sections' => [], 'nodes' => [], 'complete' => (bool) $only, 'places' => $only ? [$only[0] => 1] : []];
        }
        $rounds = (int) round(log($size, 2));
        $main = [];
        $nodes = [];
        for ($r = 1; $r <= $rounds; $r++) {
            $count = intdiv($size, 2 ** $r);
            for ($p = 0; $p < $count; $p++) {
                if ($r === 1) {
                    $a = $slots[2 * $p] === null ? self::bye() : self::reg($slots[2 * $p]);
                    $b = $slots[2 * $p + 1] === null ? self::bye() : self::reg($slots[2 * $p + 1]);
                } else {
                    $a = $main[$r - 1][2 * $p]['winner'];
                    $b = $main[$r - 1][2 * $p + 1]['winner'];
                }
                $label = self::roundLabel($r, $rounds) . ($count > 1 ? ' ' . ($p + 1) : '');
                $main[$r][$p] = self::node("M$r-$p", $r === $rounds ? 'final' : 'main', $r, $label, $a, $b, $bouts)
                    + ['src' => $r === 1 ? ['a' => null, 'b' => null] : ['a' => 'M' . ($r - 1) . '-' . (2 * $p), 'b' => 'M' . ($r - 1) . '-' . (2 * $p + 1)]];
            }
        }
        // Repêchages : un par quart de tableau
        $qfRound = $rounds - 2;
        $ladderWinner = [];
        $ladderSrc = [0 => null, 1 => null, 2 => null, 3 => null];
        $repechage = [];
        for ($q = 0; $q < 4; $q++) {
            $qf = $main[$qfRound][$q];
            $w = $qf['winner'];
            if (!self::isReg($w)) {
                $ladderWinner[$q] = isset($w['bye']) ? self::bye() : self::pending('Repêchage du quart ' . ($q + 1));
                continue;
            }
            $victims = [];
            for ($r = 1; $r <= $qfRound; $r++) {
                $span = 2 ** ($qfRound - $r);
                for ($p = $q * $span; $p < ($q + 1) * $span; $p++) {
                    $n = $main[$r][$p];
                    if ($n['fight'] && self::isReg($n['winner']) && $n['winner']['reg'] === $w['reg']) {
                        $victims[] = $n['loser'];
                    }
                }
            }
            if (!$victims) {
                $ladderWinner[$q] = self::bye();
                continue;
            }
            $current = $victims[0];
            $currentSrc = null;
            for ($j = 1; $j < count($victims); $j++) {
                $n = self::node("R$q-$j", 'repechage', $j, 'Repêchage quart ' . ($q + 1) . ($j > 1 ? ' · ' . $j : ''), $current, $victims[$j], $bouts)
                    + ['src' => ['a' => $currentSrc, 'b' => null]];
                $repechage[] = $n;
                $current = $n['winner'];
                $currentSrc = "R$q-$j";
            }
            $ladderWinner[$q] = $current;
            $ladderSrc[$q] = $currentSrc;
        }
        $repFinal = [];
        $bronze = [];
        for ($h = 0; $h < 2; $h++) {
            $repFinal[$h] = self::node("RF$h", 'repechage_final', 0, 'Finale de repêchage ' . ($h + 1), $ladderWinner[2 * $h], $ladderWinner[2 * $h + 1], $bouts)
                + ['src' => ['a' => $ladderSrc[2 * $h], 'b' => $ladderSrc[2 * $h + 1]]];
        }
        for ($h = 0; $h < 2; $h++) {
            // Croisement : perdant de la demi-finale de la moitié h contre le vainqueur du repêchage de l'autre moitié
            $sf = $main[$rounds - 1][$h];
            $bronze[$h] = self::node("B$h", 'bronze', 0, 'Place de 3e ' . ($h + 1), $sf['loser'], $repFinal[1 - $h]['winner'], $bouts)
                + ['src' => ['a' => null, 'b' => 'RF' . (1 - $h)]];
        }
        $final = $main[$rounds][0];

        // Places
        $places = [];
        $complete = true;
        foreach ([$final] as $f) {
            if (self::isReg($f['winner'])) {
                $places[$f['winner']['reg']] = 1;
                if (self::isReg($f['loser'])) {
                    $places[$f['loser']['reg']] = 2;
                }
            } else {
                $complete = false;
            }
        }
        foreach ($bronze as $b) {
            if (self::isReg($b['winner'])) {
                $places[$b['winner']['reg']] = 3;
                if (self::isReg($b['loser'])) {
                    $places[$b['loser']['reg']] = 5;
                }
            } elseif (!isset($b['winner']['bye'])) {
                $complete = false;
            }
        }
        foreach ($repFinal as $rf) {
            if (self::isReg($rf['loser']) && $rf['fight']) {
                $places[$rf['loser']['reg']] = 7;
            } elseif (isset($rf['winner']['pending'])) {
                $complete = false;
            }
        }
        foreach ($main as $list) {
            array_push($nodes, ...$list);
        }
        array_push($nodes, ...$repechage, ...$repFinal, ...$bronze);

        $sections = [];
        for ($r = 1; $r < $rounds; $r++) {
            $sections[] = ['key' => "M$r", 'title' => self::sectionTitle($r, $rounds), 'nodes' => array_values($main[$r])];
        }
        $sections[] = ['key' => 'R', 'title' => 'Repêchages', 'nodes' => array_merge($repechage, $repFinal), 'note' => 'Les combattants battus par un demi-finaliste dans son quart de tableau sont repêchés.'];
        $sections[] = ['key' => 'B', 'title' => 'Places de 3e', 'nodes' => array_values($bronze)];
        $sections[] = ['key' => 'F', 'title' => 'Finale', 'nodes' => [$final]];
        return ['kind' => 'bracket', 'sections' => $sections, 'nodes' => $nodes, 'places' => $places, 'complete' => $complete, 'size' => $size, 'rounds' => $rounds];
    }

    private static function sectionTitle(int $r, int $rounds): string
    {
        return match ($rounds - $r) {
            1 => 'Demi-finales',
            2 => 'Quarts de finale',
            default => (2 ** ($rounds - $r)) . 'es de finale',
        };
    }

    /** Poules A et B d'une catégorie « 2 poules + tableau final ». @return list<array> */
    public static function siblingPools(array $block): array
    {
        return Database::all(
            "SELECT * FROM pools WHERE generation_id = ? AND group_key = ? AND kind = 'pool' ORDER BY number",
            [$block['generation_id'], $block['group_key']]
        );
    }

    /** Tableau final croisé d'une poule de la catégorie, s'il existe. */
    public static function finalBlockFor(array $pool): ?array
    {
        return Database::one("SELECT * FROM pools WHERE generation_id = ? AND group_key = ? AND kind = 'final_block' LIMIT 1", [$pool['generation_id'], $pool['group_key']]);
    }

    private static function finalBlockStructure(array $block): array
    {
        $bouts = self::boutsByKey((int) $block['id']);
        $pools = self::siblingPools($block);
        $ranks = [];
        $done = [];
        foreach ($pools as $i => $p) {
            $done[$i] = (int) $p['ranking_complete'] === 1;
            $ranks[$i] = [];
            foreach (Database::all('SELECT registration_id, final_rank FROM pool_athletes WHERE pool_id = ? AND final_rank IS NOT NULL', [$p['id']]) as $pa) {
                $ranks[$i][(int) $pa['final_rank']] = (int) $pa['registration_id'];
            }
        }
        $who = function (int $i, int $rank) use ($ranks, $done, $pools): array {
            if (!isset($pools[$i])) {
                return self::bye();
            }
            if (!$done[$i]) {
                return self::pending(($rank === 1 ? '1er' : $rank . 'e') . ' de la poule ' . $pools[$i]['number']);
            }
            return isset($ranks[$i][$rank]) ? self::reg($ranks[$i][$rank]) : self::bye();
        };
        $s0 = self::node('S0', 'semi', 1, 'Demi-finale 1', $who(0, 1), $who(1, 2), $bouts) + ['src' => ['a' => null, 'b' => null]];
        $s1 = self::node('S1', 'semi', 1, 'Demi-finale 2', $who(1, 1), $who(0, 2), $bouts) + ['src' => ['a' => null, 'b' => null]];
        $final = self::node('F', 'final', 2, 'Finale', $s0['winner'], $s1['winner'], $bouts) + ['src' => ['a' => 'S0', 'b' => 'S1']];
        $places = [];
        $complete = self::isReg($final['winner']);
        if ($complete) {
            $places[$final['winner']['reg']] = 1;
            if (self::isReg($final['loser'])) {
                $places[$final['loser']['reg']] = 2;
            }
            foreach ([$s0, $s1] as $s) {
                if (self::isReg($s['loser'])) {
                    $places[$s['loser']['reg']] = 3;
                }
            }
            foreach ($ranks as $r) {
                foreach ([3 => 5, 4 => 7] as $poolRank => $place) {
                    if (isset($r[$poolRank])) {
                        $places[$r[$poolRank]] = $place;
                    }
                }
            }
        }
        return ['kind' => 'final_block', 'nodes' => [$s0, $s1, $final], 'places' => $places, 'complete' => $complete,
            'sections' => [['key' => 'S', 'title' => 'Demi-finales', 'nodes' => [$s0, $s1]], ['key' => 'F', 'title' => 'Finale', 'nodes' => [$final]]],
            'pools' => array_map(fn ($p) => (int) $p['id'], $pools)];
    }

    /**
     * Arbres à dessiner (vue « tableau » façon feuille officielle) : tableau principal, puis les deux repêchages qui mènent
     * aux places de 3e. Chaque nœud : vainqueur (s'il est connu), score « vainqueur\perdant », état, et ses deux branches
     * (sous-arbre ou combattant d'entrée).
     * @return list<array{title:string, root:array}>
     */
    public static function trees(array $pool): array
    {
        $st = self::structure($pool);
        if (!$st['nodes']) {
            return [];
        }
        $byKey = [];
        foreach ($st['nodes'] as $n) {
            $byKey[$n['key']] = $n;
        }
        $regs = [];
        foreach ($st['nodes'] as $n) {
            foreach (['a', 'b'] as $s) {
                if (self::isReg($n[$s])) {
                    $regs[] = $n[$s]['reg'];
                }
            }
        }
        $people = self::people(array_values(array_unique($regs)));
        $seedOf = [];
        foreach (Database::all('SELECT registration_id, draw_seed FROM pool_athletes WHERE pool_id = ? AND draw_seed IS NOT NULL', [$pool['id']]) as $x) {
            $seedOf[(int) $x['registration_id']] = (int) $x['draw_seed'];
        }
        $tatami = $pool['tatami_id'] ? (int) Database::value('SELECT number FROM tatamis WHERE id = ?', [$pool['tatami_id']]) : null;
        $leaf = function (array $p) use ($people, $seedOf): array {
            if (self::isReg($p)) {
                return ['leaf' => true, 'type' => 'athlete', 'athlete' => $people[$p['reg']] ?? null, 'seed' => $seedOf[$p['reg']] ?? null, 'text' => null];
            }
            return ['leaf' => true, 'type' => isset($p['bye']) ? 'bye' : 'pending', 'athlete' => null, 'seed' => null, 'text' => isset($p['bye']) ? null : $p['pending']];
        };
        $build = function (string $key) use (&$build, $byKey, $leaf, $people, $tatami): array {
            $n = $byKey[$key];
            $b = $n['bout'];
            $kids = [];
            foreach (['a', 'b'] as $s) {
                $src = $n['src'][$s] ?? null;
                $kids[] = $src && isset($byKey[$src]) ? $build($src) : $leaf($n[$s]);
            }
            $winner = self::isReg($n['winner']) ? ($people[$n['winner']['reg']] ?? null) : null;
            $score = null;
            if ($b && $b['status'] === 'validated' && $n['fight'] && $winner) {
                $ws = $b['winner_side'];
                $ls = $ws === 'a' ? 'b' : 'a';
                $fmt = fn ($x) => (int) $b[$x . '_ippon'] . (int) $b[$x . '_wazaari'] . (int) $b[$x . '_yuko'] . ((int) $b[$x . '_shido'] > 0 ? '-' . (int) $b[$x . '_shido'] : '');
                $score = $fmt($ws) . '\\' . $fmt($ls);
            }
            return [
                'leaf' => false, 'key' => $key, 'label' => $n['label'], 'stage' => $n['stage'], 'fight' => $n['fight'],
                'winner' => $winner, 'score' => $score,
                'status' => $b['status'] ?? null, 'bout_id' => $b ? (int) $b['id'] : null, 'mat' => $tatami,
                'children' => $kids,
            ];
        };
        if ($pool['kind'] === 'final_block') {
            return [['title' => 'Tableau final', 'root' => $build('F')]];
        }
        $rounds = (int) $st['rounds'];
        return [
            ['title' => 'Tableau principal', 'root' => $build("M$rounds-0")],
            ['title' => 'Repêchage · place de 3e (1)', 'root' => $build('B0')],
            ['title' => 'Repêchage · place de 3e (2)', 'root' => $build('B1')],
        ];
    }

    /**
     * Mise en page d'un arbre (commune au site, au PDF et à l'application) : chaque combattant d'entrée sur une ligne
     * (colonne 0), chaque combat sur la ligne de son vainqueur (colonne = tour), à mi-hauteur de ses deux branches.
     * @return array{cols:int, rows:int, items:list<array>, joins:list<array{col:int,y1:float,y2:float}>}
     */
    public static function layout(array $root): array
    {
        $items = [];
        $joins = [];
        $row = 0;
        $walk = function (array $n) use (&$walk, &$items, &$joins, &$row): array {
            if ($n['leaf']) {
                $items[] = $n + ['y' => (float) $row, 'col' => 0, 'span' => 1];
                $row++;
                return [(float) ($row - 1), 0, count($items) - 1];
            }
            [$ya, $ca, $ia] = $walk($n['children'][0]);
            [$yb, $cb, $ib] = $walk($n['children'][1]);
            $col = max($ca, $cb) + 1;
            $items[$ia]['span'] = $col - $ca;
            $items[$ib]['span'] = $col - $cb;
            $joins[] = ['col' => $col, 'y1' => min($ya, $yb), 'y2' => max($ya, $yb)];
            $node = $n;
            unset($node['children']);
            $items[] = $node + ['y' => ($ya + $yb) / 2, 'col' => $col, 'span' => 1];
            return [($ya + $yb) / 2, $col, count($items) - 1];
        };
        [, $col] = $walk($root);
        return ['cols' => $col + 1, 'rows' => $row, 'items' => $items, 'joins' => $joins];
    }

    /** Arbres mis en page, prêts à dessiner. */
    public static function drawings(array $pool): array
    {
        return array_map(fn ($t) => ['title' => $t['title']] + self::layout($t['root']), self::trees($pool));
    }

    // ------------------------------------------------------------------ Avancement (après chaque validation / correction)

    /**
     * Crée les combats dont les deux adversaires sont connus, met à jour ou supprime les combats programmés devenus
     * faux (correction d'un résultat), enregistre les places quand le tableau est terminé.
     * Les combats pour la 3e place passent avant la finale : la finale n'est créée qu'une fois ces combats créés.
     */
    public static function refresh(array $pool): array
    {
        $pool = Database::one('SELECT * FROM pools WHERE id = ?', [$pool['id']]);
        $st = self::structure($pool);
        $cid = (int) $pool['competition_id'];
        $live = in_array($pool['status'], ['validated', 'in_progress', 'finished'], true);
        if ($live) {
            $existing = self::boutsByKey((int) $pool['id']);
            $byKey = [];
            foreach ($st['nodes'] as $n) {
                $byKey[$n['key']] = $n;
            }
            $changed = false;
            $rule = null;
            // La finale en dernier : elle n'est créée qu'une fois les combats pour la 3e place créés (ou sans objet).
            $ordered = $st['nodes'];
            usort($ordered, fn ($x, $y) => ($x['stage'] === 'final') <=> ($y['stage'] === 'final'));
            foreach ($ordered as $n) {
                if (!$n['fight']) {
                    continue;
                }
                if ($pool['kind'] === 'bracket' && $n['stage'] === 'final') {
                    $created = self::boutsByKey((int) $pool['id']);
                    $bronzeReady = true;
                    foreach ($st['nodes'] as $x) {
                        if ($x['stage'] === 'bronze' && (($x['fight'] && !isset($created[$x['key']])) || (!$x['fight'] && isset($x['winner']['pending'])))) {
                            $bronzeReady = false;
                        }
                    }
                    if (!$bronzeReady) {
                        continue;
                    }
                }
                $bout = $existing[$n['key']] ?? null;
                if (!$bout) {
                    $rule ??= DurationRuleService::resolve($cid, (int) $pool['age_category_id'], $pool['sex'], 'final')
                        ?? DurationRuleService::resolve($cid, (int) $pool['age_category_id'], $pool['sex'], 'pool');
                    if (!$rule) {
                        \App\Core\Logger::info("Tableau {$pool['id']} : aucune durée de combat applicable, combat {$n['key']} non créé.");
                        continue;
                    }
                    Database::insert('bouts', [
                        'competition_id' => $cid, 'pool_id' => $pool['id'], 'tatami_id' => $pool['tatami_id'],
                        'order_in_pool' => (int) Database::value('SELECT COALESCE(MAX(order_in_pool), 0) + 1 FROM bouts WHERE pool_id = ?', [$pool['id']]),
                        'phase' => 'final', 'bracket_key' => $n['key'], 'stage_label' => mb_substr($n['label'], 0, 60),
                        'a_registration_id' => $n['a']['reg'], 'b_registration_id' => $n['b']['reg'],
                        'duration_rule_id' => $rule['id'], 'duration_seconds' => $rule['duration_seconds'],
                        'golden_score_mode' => $rule['golden_score_mode'], 'golden_score_seconds' => $rule['golden_score_seconds'],
                        'recovery_seconds' => $rule['recovery_seconds'], 'rules_json' => DurationRuleService::snapshot($rule),
                    ]);
                    $changed = true;
                } elseif ($bout['status'] === 'scheduled'
                    && ((int) $bout['a_registration_id'] !== $n['a']['reg'] || (int) $bout['b_registration_id'] !== $n['b']['reg'])) {
                    Database::run('UPDATE bouts SET a_registration_id = ?, b_registration_id = ?, version = version + 1 WHERE id = ?', [$n['a']['reg'], $n['b']['reg'], $bout['id']]);
                    $changed = true;
                }
            }
            // Combats programmés devenus inutiles (résultat corrigé en amont)
            foreach ($existing as $key => $bout) {
                if ($bout['status'] === 'scheduled' && (!isset($byKey[$key]) || !$byKey[$key]['fight'])) {
                    Database::run('DELETE FROM bouts WHERE id = ?', [$bout['id']]);
                    $changed = true;
                }
            }
            if ($changed && $pool['tatami_id']) {
                PoolService::resequenceTatami((int) $pool['tatami_id']);
            }
            if ($changed) {
                PoolService::refreshEstimate((int) $pool['id']);
            }
        }
        // Places : seulement quand le tableau est terminé (jamais à partir d'un résultat incomplet)
        Database::run('DELETE FROM athlete_rankings WHERE pool_id = ?', [$pool['id']]);
        if ($live && $st['complete']) {
            RankingService::writePlaces($cid, (int) $pool['id'], $st['places']);
        }
        $status = $pool['status'];
        if ($live) {
            if ($st['complete']) {
                $status = 'finished';
            } elseif ($status === 'finished') {
                $status = 'in_progress';
            }
        }
        Database::run('UPDATE pools SET ranking_complete = ?, status = ? WHERE id = ?', [$st['complete'] && $live ? 1 : 0, $status, $pool['id']]);
        return $st;
    }

    /**
     * Correction d'un résultat de tableau : refusée si le vainqueur change alors qu'un combat qui en dépend a déjà commencé.
     */
    public static function assertCorrectable(array $bout, string $newWinnerSide): void
    {
        if (!$bout['bracket_key'] || $bout['winner_side'] === $newWinnerSide) {
            return;
        }
        $pool = Database::one('SELECT * FROM pools WHERE id = ?', [$bout['pool_id']]);
        $regs = [(int) $bout['a_registration_id'], (int) $bout['b_registration_id']];
        $later = Database::value(
            "SELECT COUNT(*) FROM bouts WHERE pool_id = ? AND id <> ? AND status IN ('in_progress','finished','validated') AND order_in_pool > ?
               AND (a_registration_id IN (?, ?) OR b_registration_id IN (?, ?))",
            [$pool['id'], $bout['id'], $bout['order_in_pool'], $regs[0], $regs[1], $regs[0], $regs[1]]
        );
        if ($later) {
            throw new DomainError('Le vainqueur de ce combat a déjà disputé la suite du tableau : corrigez d\'abord les combats suivants.');
        }
    }

    // ------------------------------------------------------------------ Lecture (site, application, PDF)

    /**
     * Tableau prêt à afficher : combats avec noms, clubs, scores, statut, tapis ; classement complet de la catégorie
     * (1, 2, 3, 3, 5, 5, 7, 7, puis NC ; poules : 1 à 5).
     */
    public static function view(array $pool): array
    {
        $st = self::structure($pool);
        $regIds = [];
        foreach ($st['nodes'] as $n) {
            foreach (['a', 'b'] as $s) {
                if (self::isReg($n[$s])) {
                    $regIds[] = $n[$s]['reg'];
                }
            }
        }
        $members = $pool['kind'] === 'final_block'
            ? Database::column("SELECT registration_id FROM pool_athletes WHERE pool_id IN (SELECT id FROM pools WHERE generation_id = ? AND group_key = ? AND kind = 'pool')", [$pool['generation_id'], $pool['group_key']])
            : Database::column('SELECT registration_id FROM pool_athletes WHERE pool_id = ?', [$pool['id']]);
        $regIds = array_values(array_unique(array_merge($regIds, array_map('intval', $members))));
        $people = self::people($regIds);
        $seedOf = [];
        foreach (Database::all('SELECT registration_id, draw_seed FROM pool_athletes WHERE pool_id = ? AND draw_seed IS NOT NULL', [$pool['id']]) as $s) {
            $seedOf[(int) $s['registration_id']] = (int) $s['draw_seed'];
        }
        $tatami = $pool['tatami_id'] ? (int) Database::value('SELECT number FROM tatamis WHERE id = ?', [$pool['tatami_id']]) : null;
        $side = function (array $p) use ($people, $seedOf): array {
            if (self::isReg($p)) {
                return ['type' => 'athlete', 'athlete' => $people[$p['reg']] ?? null, 'seed' => $seedOf[$p['reg']] ?? null];
            }
            return isset($p['bye']) ? ['type' => 'bye', 'text' => 'Exempt'] : ['type' => 'pending', 'text' => $p['pending']];
        };
        $nodeView = function (array $n) use ($side, $tatami): array {
            $b = $n['bout'];
            $winner = null;
            if (self::isReg($n['winner']) && $n['fight']) {
                $winner = self::isReg($n['a']) && $n['a']['reg'] === $n['winner']['reg'] ? 'a' : 'b';
            } elseif (self::isReg($n['winner'])) {
                $winner = self::isReg($n['a']) ? 'a' : 'b';
            }
            return [
                'key' => $n['key'], 'stage' => $n['stage'], 'label' => $n['label'], 'fight' => $n['fight'],
                'a' => $side($n['a']), 'b' => $side($n['b']), 'winner' => $winner,
                'bout' => $b ? [
                    'id' => (int) $b['id'], 'status' => $b['status'], 'order_on_tatami' => (int) $b['order_on_tatami'], 'tatami' => $tatami,
                    'method' => $b['win_method'], 'flip' => (int) $b['a_registration_id'] !== ($n['a']['reg'] ?? 0),
                    'score' => ['a' => ['ippon' => (int) $b['a_ippon'], 'wazaari' => (int) $b['a_wazaari'], 'yuko' => (int) $b['a_yuko'], 'shido' => (int) $b['a_shido']],
                        'b' => ['ippon' => (int) $b['b_ippon'], 'wazaari' => (int) $b['b_wazaari'], 'yuko' => (int) $b['b_yuko'], 'shido' => (int) $b['b_shido']]],
                ] : null,
            ];
        };
        $sections = array_map(fn ($s) => ['nodes' => array_map($nodeView, $s['nodes'])] + $s, $st['sections']);
        // Classement de toute la catégorie
        $places = $st['places'];
        if ($pool['kind'] === 'pool') {
            $places = [];
        }
        $ranking = [];
        foreach ($regIds as $id) {
            $ranking[] = ['place' => $places[$id] ?? null, 'athlete' => $people[$id] ?? null];
        }
        usort($ranking, fn ($x, $y) => [($x['place'] ?? 99), $x['athlete']['last_name'] ?? ''] <=> [($y['place'] ?? 99), $y['athlete']['last_name'] ?? '']);
        $slug = (string) Database::value('SELECT slug FROM competitions WHERE id = ?', [$pool['competition_id']]);
        return [
            'drawings' => self::drawings($pool),
            'pdf_url' => url('/competitions/' . $slug . '/tableaux/' . $pool['id'] . '.pdf'),
            'pool_id' => (int) $pool['id'], 'kind' => $pool['kind'], 'label' => $pool['label'], 'size' => $st['size'] ?? null,
            'tatami' => $tatami, 'complete' => $st['complete'], 'sections' => $sections, 'ranking' => $ranking,
            'pools' => $st['pools'] ?? [], 'count' => count($regIds),
        ];
    }

    /** Identité publique des combattants (pas de date de naissance ni de licence). @return array<int,array> */
    public static function people(array $regIds): array
    {
        if (!$regIds) {
            return [];
        }
        $in = implode(',', array_fill(0, count($regIds), '?'));
        $out = [];
        $colors = [];
        foreach (Database::all("SELECT r.id, a.first_name, a.last_name, a.photo_document_id, r.club_id, r.club_exception_label, c.name AS club_name, c.short_name AS club_short,
                                       c.logo_path AS club_logo, c.brand_color
                                FROM registrations r JOIN athletes a ON a.id = r.athlete_id LEFT JOIN clubs c ON c.id = r.club_id WHERE r.id IN ($in)", array_values($regIds)) as $p) {
            $out[(int) $p['id']] = [
                'registration_id' => (int) $p['id'], 'club_id' => $p['club_id'] === null ? null : (int) $p['club_id'], 'first_name' => $p['first_name'], 'last_name' => mb_strtoupper($p['last_name']),
                'club' => $p['club_name'] ?? $p['club_exception_label'], 'club_short' => $p['club_short'] ?: ($p['club_name'] ?? $p['club_exception_label']),
                'club_logo' => $p['club_logo'] ? url('/' . ltrim($p['club_logo'], '/')) : null,
                'club_color' => $p['club_id'] ? ClubColorService::forClub(['id' => $p['club_id'], 'logo_path' => $p['club_logo'], 'brand_color' => $p['brand_color']]) : ClubColorService::FALLBACK,
            ];
        }
        return $out;
    }

    /** Toutes les catégories d'une génération (une entrée par catégorie : poule, 2 poules + tableau final, tableau). */
    /**
     * Tableaux dont le tirage au sort est validé (avant la publication des poules) : seuls ceux-là sont publics,
     * un tirage en cours ou refait n'est jamais montré. @return int[]
     */
    public static function drawnPools(int $competitionId): array
    {
        $g = PoolService::activeGeneration($competitionId);
        return $g ? array_map('intval', Database::column("SELECT id FROM pools WHERE generation_id = ? AND kind = 'bracket' AND is_locked = 1", [$g['id']])) : [];
    }

    public static function categories(int $generationId): array
    {
        $pools = Database::all(
            "SELECT p.*, ac.sort_order AS age_sort, ac.name AS age_name, wc.sort_order AS weight_sort, t.number AS tatami_number,
                    (SELECT COUNT(*) FROM pool_athletes pa WHERE pa.pool_id = p.id) AS n
             FROM pools p JOIN age_categories ac ON ac.id = p.age_category_id LEFT JOIN weight_categories wc ON wc.id = p.weight_category_id
             LEFT JOIN tatamis t ON t.id = p.tatami_id
             WHERE p.generation_id = ? ORDER BY ac.sort_order, p.sex, wc.sort_order, p.weight_min_kg, p.number",
            [$generationId]
        );
        $groups = [];
        foreach ($pools as $p) {
            $k = $p['group_key'];
            $groups[$k] ??= ['key' => $k, 'label' => preg_replace('/ · (Poule \d+|Tableau final|Tableau)$/u', '', $p['label']), 'pools' => [], 'count' => 0, 'kind' => 'pool', 'main_pool_id' => null, 'tatami' => null, 'finished' => true,
                'sex' => $p['sex'], 'age' => $p['age_name']];
            $groups[$k]['pools'][] = (int) $p['id'];
            $groups[$k]['count'] += (int) $p['n'];
            $groups[$k]['tatami'] ??= $p['tatami_number'] === null ? null : (int) $p['tatami_number'];
            $groups[$k]['finished'] = $groups[$k]['finished'] && $p['status'] === 'finished';
            if ($p['kind'] !== 'pool') {
                $groups[$k]['kind'] = $p['kind'] === 'bracket' ? 'bracket' : 'pools_final';
                $groups[$k]['main_pool_id'] = (int) $p['id'];
            }
        }
        foreach ($groups as &$g) {
            $g['main_pool_id'] ??= $g['pools'][0];
            if ($g['kind'] === 'pool' && count($g['pools']) > 1) {
                $g['kind'] = 'pools';
            }
        }
        unset($g);
        return array_values($groups);
    }

    /** Auteur courant (pour le journal). */
    public static function actor(): ?int
    {
        return Auth::id();
    }
}
