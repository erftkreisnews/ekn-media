<?php

namespace Tests\Unit;

use App\Jobs\GenerateVideoStills;
use App\Models\NewsItemMedia;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class GenerateVideoStillsSelectionTest extends TestCase
{
    private function job(): GenerateVideoStills
    {
        return new GenerateVideoStills(new NewsItemMedia(['type' => 'video']));
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return list<array<string, mixed>>
     */
    private function invokeFilterVisuallyDistinct(array $candidates, int $phashMax = 4, float $frameDiffMax = 2.5): array
    {
        $ref = new ReflectionClass($this->job());
        $method = $ref->getMethod('filterVisuallyDistinctCandidates');
        $method->setAccessible(true);

        return $method->invoke($this->job(), $candidates, $phashMax, $frameDiffMax);
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return list<array<string, mixed>>
     */
    private function invokeSelectWithMinGap(array $candidates, int $targetCount = 12, int $minGap = 2): array
    {
        $ref = new ReflectionClass($this->job());
        $method = $ref->getMethod('selectCandidatesWithMinGap');
        $method->setAccessible(true);

        return $method->invoke($this->job(), $candidates, $targetCount, $minGap, 4, 2.5);
    }

    #[Test]
    public function filter_visually_distinct_keeps_only_one_of_identical_hashes(): void
    {
        $candidates = [
            ['path' => '/tmp/a.jpg', 'score' => 90.0, 'second' => 1, 'hash' => str_repeat('1', 64)],
            ['path' => '/tmp/b.jpg', 'score' => 80.0, 'second' => 5, 'hash' => str_repeat('1', 64)],
            ['path' => '/tmp/c.jpg', 'score' => 70.0, 'second' => 10, 'hash' => str_repeat('0', 64)],
        ];

        $picked = $this->invokeFilterVisuallyDistinct($candidates);

        $this->assertCount(2, $picked);
        $this->assertSame(90.0, $picked[0]['score']);
        $this->assertSame(70.0, $picked[1]['score']);
    }

    #[Test]
    public function select_with_min_gap_respects_time_and_target_count(): void
    {
        $candidates = [
            ['path' => '/tmp/a.jpg', 'score' => 90.0, 'second' => 0, 'hash' => str_repeat('1', 64)],
            ['path' => '/tmp/b.jpg', 'score' => 85.0, 'second' => 1, 'hash' => str_repeat('0', 64)],
            ['path' => '/tmp/c.jpg', 'score' => 80.0, 'second' => 5, 'hash' => str_repeat('2', 64)],
            ['path' => '/tmp/d.jpg', 'score' => 75.0, 'second' => 6, 'hash' => str_repeat('3', 64)],
        ];

        $picked = $this->invokeSelectWithMinGap($candidates, targetCount: 3, minGap: 2);

        $this->assertCount(2, $picked);
        $this->assertSame(0, $picked[0]['second']);
        $this->assertSame(5, $picked[1]['second']);
    }
}
