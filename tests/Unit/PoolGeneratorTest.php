<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PoolGenerator;
use PHPUnit\Framework\TestCase;

final class PoolGeneratorTest extends TestCase
{
    private function athletes(array $weights, ?array $clubs = null): array
    {
        return array_map(fn ($w, $i) => ['id' => $i + 1, 'weight' => $w, 'club_id' => $clubs[$i] ?? null], $weights, array_keys($weights));
    }

    public function testElevenAthletesWithAllSizesGiveBalancedCompletePools(): void
    {
        $r = PoolGenerator::partition($this->athletes([25, 26, 26.5, 27, 28, 28.2, 29, 30, 31, 31.5, 33]), [3, 4, 5]);
        $this->assertTrue($r['ok']);
        $sizes = array_map('count', $r['pools']);
        sort($sizes);
        $this->assertSame([3, 4, 4], $sizes, 'Répartition équilibrée attendue (4 + 4 + 3)');
        $this->assertSame(11, array_sum($sizes), 'Aucun athlète ne doit être écarté');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('sizesProvider')]
    public function testOnlyAllowedSizesAreUsed(int $n, array $sizes): void
    {
        $w = array_map(fn ($i) => 20 + $i, range(1, $n));
        $r = PoolGenerator::partition($this->athletes($w), $sizes);
        $this->assertTrue($r['ok']);
        foreach ($r['pools'] as $p) {
            $this->assertContains(count($p), $sizes);
        }
        $this->assertSame($n, array_sum(array_map('count', $r['pools'])));
    }

    public static function sizesProvider(): array
    {
        return [
            'poules de 3' => [9, [3]], 'poules de 4' => [12, [4]], 'poules de 5' => [10, [5]],
            '3 et 4' => [10, [3, 4]], 'effectif impair 3-4-5' => [13, [3, 4, 5]], '7 en 3-4' => [7, [3, 4]],
        ];
    }

    public function testPoolsGroupClosestWeights(): void
    {
        $r = PoolGenerator::partition($this->athletes([40, 25, 41, 26, 42, 27, 43, 28]), [4]);
        $weights = array_map(fn ($p) => array_column($p, 'weight'), $r['pools']);
        $this->assertSame([[25, 26, 27, 28], [40, 41, 42, 43]], $weights);
    }

    public function testImpossibleCompositionProposesAlternativesWithoutDroppingAnyone(): void
    {
        $r = PoolGenerator::partition($this->athletes([30, 31]), [3, 4, 5]);
        $this->assertFalse($r['ok']);
        $this->assertSame([], $r['pools']);
        $this->assertNotEmpty($r['alternatives']);
        $this->assertContains('sizes', array_column($r['alternatives'], 'type'));
        $r1 = PoolGenerator::partition($this->athletes([30]), [3, 4, 5]);
        $this->assertFalse($r1['ok']);
        $this->assertSame(['merge', 'unplaced'], array_column($r1['alternatives'], 'type'));
        $r7 = PoolGenerator::partition($this->athletes(range(20, 26)), [3]);
        $this->assertFalse($r7['ok'], '7 athlètes ne se répartissent pas en poules de 3 uniquement');
    }

    public function testEmptyGroupIsValid(): void
    {
        $this->assertTrue(PoolGenerator::partition([], [3, 4])['ok']);
    }

    public function testSameClubAthletesAreSeparatedWhenWeightsAreClose(): void
    {
        // Deux poules de 3 ; athlètes 1 et 2 (même club) de poids quasi identiques à l'athlète 4 d'un autre club.
        $a = $this->athletes([30.0, 30.2, 30.4, 30.6, 30.8, 31.0], [1, 1, 2, 3, 4, 5]);
        $r = PoolGenerator::partition($a, [3]);
        $this->assertSame(0, PoolGenerator::clubConflicts($r['pools']));
    }

    public function testBoutCountFormula(): void
    {
        foreach ([2 => 1, 3 => 3, 4 => 6, 5 => 10, 6 => 15] as $n => $expected) {
            $this->assertSame($expected, PoolGenerator::boutCount($n));
            $order = PoolGenerator::roundRobinOrder($n);
            $this->assertCount($expected, $order);
            $pairs = array_map(fn ($p) => min($p) . '-' . max($p), $order);
            $this->assertCount($expected, array_unique($pairs), 'Chaque paire une seule fois');
        }
    }

    public function testRoundRobinGivesRestBetweenBouts(): void
    {
        // Avec 4 combattants, l'enchaînement sans combat consécutif est impossible (ordre fédéral 1-2, 3-4, 1-3…) ;
        // la poule de 5 l'autorise.
        foreach ([5] as $n) {
            $order = PoolGenerator::roundRobinOrder($n);
            for ($i = 1; $i < count($order); $i++) {
                $this->assertEmpty(array_intersect($order[$i - 1], $order[$i]), "Poule de $n : un athlète combat deux fois de suite");
            }
        }
    }

    public function testAthletesWithoutWeightAreDrawnReproducibly(): void
    {
        $a = $this->athletes([null, null, null, null, null, null]);
        $x = PoolGenerator::sortByWeight($a, 42);
        $y = PoolGenerator::sortByWeight($a, 42);
        $this->assertSame(array_column($x, 'id'), array_column($y, 'id'));
        $this->assertCount(6, $x);
    }

    public function testRoundRobinFollowsOfficialPoolSheetOrder(): void
    {
        $this->assertSame([[1, 2], [2, 3], [1, 3]], PoolGenerator::roundRobinOrder(3));
        $this->assertSame([[1, 2], [3, 4], [1, 3], [2, 4], [1, 4], [2, 3]], PoolGenerator::roundRobinOrder(4));
        $this->assertSame([[1, 2], [3, 4], [1, 5], [2, 4], [3, 5], [1, 4], [2, 3], [4, 5], [1, 3], [2, 5]], PoolGenerator::roundRobinOrder(5));
    }
}
