<?php

namespace Tests\Unit;

use App\Services\Ingest\IngestRenderChunkPlanner;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IngestRenderChunkPlannerTest extends TestCase
{
    #[Test]
    public function parse_bitrate_handles_megabit_and_kilobit_suffixes(): void
    {
        $planner = app(IngestRenderChunkPlanner::class);

        $this->assertSame(12_400_000.0, $planner->parseBitrateToBitsPerSecond('12.4M'));
        $this->assertSame(256_000.0, $planner->parseBitrateToBitsPerSecond('256k'));
    }

    #[Test]
    public function plan_chunks_splits_when_estimated_size_exceeds_limit(): void
    {
        config([
            'ingest.output.video_bitrate' => '12.4M',
            'ingest.output.audio_bitrate' => '256k',
            'ingest.output.size_estimate_overhead' => 1.0,
            'ingest.output.max_output_gigabytes' => 1.0,
        ]);

        $planner = app(IngestRenderChunkPlanner::class);
        $bytesPerSecond = $planner->estimateOutputBytesPerSecond();
        $secondsPerGb = (1024 * 1024 * 1024) / $bytesPerSecond;

        $clips = [];
        for ($i = 0; $i < 6; $i++) {
            $clips[] = [
                'id' => $i + 1,
                'path' => '/tmp/clip-'.$i.'.mp4',
                'duration_s' => $secondsPerGb / 3,
            ];
        }

        $chunks = $planner->planChunks($clips);

        $this->assertGreaterThan(1, count($chunks));
        $this->assertSame(6, array_sum(array_map(static fn (array $c): int => count($c), $chunks)));
    }

    #[Test]
    public function plan_chunks_keeps_single_chunk_when_under_limit(): void
    {
        config([
            'ingest.output.max_output_gigabytes' => 1.0,
        ]);

        $planner = app(IngestRenderChunkPlanner::class);
        $clips = [
            ['id' => 1, 'path' => '/tmp/a.mp4', 'duration_s' => 60.0],
            ['id' => 2, 'path' => '/tmp/b.mp4', 'duration_s' => 90.0],
        ];

        $chunks = $planner->planChunks($clips);

        $this->assertCount(1, $chunks);
        $this->assertCount(2, $chunks[0]);
    }
}
