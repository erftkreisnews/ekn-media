<?php

namespace Tests\Unit;

use App\Jobs\GenerateVideoStills;
use App\Models\NewsItemMedia;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class GenerateVideoStillsDurationProbeTest extends TestCase
{
    private static function ffmpegAvailable(): bool
    {
        $p = new Process(['which', 'ffmpeg']);
        $p->run();

        return $p->isSuccessful();
    }

    private static function makeTestMp4(string $path, int $seconds): void
    {
        $p = new Process([
            'ffmpeg', '-y',
            '-f', 'lavfi',
            '-i', 'color=c=green:s=320x240:d='.$seconds,
            '-c:v', 'libx264',
            '-t', (string) $seconds,
            '-pix_fmt', 'yuv420p',
            $path,
        ]);
        $p->setTimeout(120);
        $p->run();
        if (! $p->isSuccessful() || ! is_file($path)) {
            throw new \RuntimeException('ffmpeg test clip failed: '.$p->getErrorOutput());
        }
    }

    #[Test]
    public function ffprobe_json_and_ffmpeg_stderr_match_clip_duration(): void
    {
        if (! self::ffmpegAvailable()) {
            $this->markTestSkipped('ffmpeg not in PATH');
        }

        $path = sys_get_temp_dir().'/gen_vs_duration_test_'.uniqid('', true).'.mp4';
        $expectedSeconds = 127;
        try {
            self::makeTestMp4($path, $expectedSeconds);
        } catch (\RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $media = new NewsItemMedia;
        $media->duration_s = 82.0;

        $job = new GenerateVideoStills($media);
        $ref = new ReflectionClass($job);

        $ffprobeJson = $ref->getMethod('probeDurationSecondsWithFfprobeJson');
        $ffprobeJson->setAccessible(true);
        $fromProbe = $ffprobeJson->invoke($job, $path);
        $this->assertNotNull($fromProbe);
        $this->assertEqualsWithDelta((float) $expectedSeconds, (float) $fromProbe, 2.5, 'ffprobe JSON duration should match generated clip');

        $ffmpegPath = (string) config('media.ffmpeg_path', 'ffmpeg');
        $ffmpegStderr = $ref->getMethod('probeDurationSecondsWithFfmpegStderr');
        $ffmpegStderr->setAccessible(true);
        $fromFfmpeg = $ffmpegStderr->invoke($job, $path, $ffmpegPath);
        $this->assertNotNull($fromFfmpeg);
        $this->assertEqualsWithDelta((float) $expectedSeconds, (float) $fromFfmpeg, 2.5, 'ffmpeg stderr duration should match');

        $resolve = $ref->getMethod('resolveVideoDurationSeconds');
        $resolve->setAccessible(true);
        $resolved = $resolve->invoke($job, $path, $ffmpegPath);
        $this->assertNotNull($resolved);
        $this->assertEqualsWithDelta((float) $expectedSeconds, (float) $resolved, 2.5, 'resolve should prefer file probe over wrong duration_s (82)');

        @unlink($path);
    }
}
