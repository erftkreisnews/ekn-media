<?php

namespace Tests\Unit;

use App\Jobs\GenerateVideoStills;
use App\Models\NewsItemMedia;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class GenerateVideoStillsOomTest extends TestCase
{
    private function invokeIsFfmpegMemoryFailure(?string $err): bool
    {
        $ref = new ReflectionClass(new GenerateVideoStills(new NewsItemMedia(['type' => 'video'])));
        $method = $ref->getMethod('isFfmpegMemoryFailure');
        $method->setAccessible(true);

        return $method->invoke(new GenerateVideoStills(new NewsItemMedia(['type' => 'video'])), $err);
    }

    #[Test]
    public function detects_signal_9_as_memory_failure(): void
    {
        $this->assertTrue($this->invokeIsFfmpegMemoryFailure('Prozess beendet (Signal 9, oft OOM oder Timeout).'));
    }

    #[Test]
    public function detects_oom_keywords(): void
    {
        $this->assertTrue($this->invokeIsFfmpegMemoryFailure('Process killed'));
        $this->assertTrue($this->invokeIsFfmpegMemoryFailure('Cannot allocate memory'));
    }

    #[Test]
    public function ignores_codec_errors(): void
    {
        $this->assertFalse($this->invokeIsFfmpegMemoryFailure('Invalid data found when processing input'));
        $this->assertFalse($this->invokeIsFfmpegMemoryFailure(null));
    }
}
