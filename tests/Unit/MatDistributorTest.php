<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MatDistributor;
use PHPUnit\Framework\TestCase;

final class MatDistributorTest extends TestCase
{
    private function pools(array $loads, array $locked = []): array
    {
        return array_map(fn ($l, $i) => ['id' => $i + 1, 'load' => $l, 'tatami_id' => $locked[$i + 1] ?? null, 'locked' => isset($locked[$i + 1])], $loads, array_keys($loads));
    }

    public function testLoadsAreBalanced(): void
    {
        $pools = $this->pools([600, 300, 300, 200, 200, 400, 100]);
        $assign = MatDistributor::distribute($pools, [10, 20, 30]);
        $loads = MatDistributor::loads($pools, $assign, [10, 20, 30]);
        $this->assertSame(2100, array_sum($loads));
        $this->assertLessThanOrEqual(100, MatDistributor::spread($loads), 'Écart minimal attendu entre tapis');
    }

    public function testLockedPoolsKeepTheirTatami(): void
    {
        $pools = $this->pools([600, 100, 100, 100], [1 => 20]);
        $assign = MatDistributor::distribute($pools, [10, 20]);
        $this->assertSame(20, $assign[1]);
    }

    public function testEveryPoolIsAssigned(): void
    {
        $pools = $this->pools(array_fill(0, 9, 360));
        $assign = MatDistributor::distribute($pools, [1, 2, 3, 4]);
        $this->assertCount(9, $assign);
    }
}
