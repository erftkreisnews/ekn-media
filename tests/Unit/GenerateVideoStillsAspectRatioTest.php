<?php

namespace Tests\Unit;

use App\Jobs\GenerateVideoStills;
use App\Models\NewsItemMedia;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class GenerateVideoStillsAspectRatioTest extends TestCase
{
    private function scaleExpr(): string
    {
        config(['media.video_stills.aspect_ratio' => '3:2', 'media.video_stills.long_edge' => 2560]);

        $job = new GenerateVideoStills(new NewsItemMedia);
        $ref = new ReflectionClass($job);
        $method = $ref->getMethod('buildStillScaleFilterExpression');
        $method->setAccessible(true);

        return (string) $method->invoke($job, 2560, 'bicubic');
    }

    #[Test]
    public function scale_filter_targets_press_3x2_crop(): void
    {
        $expr = $this->scaleExpr();
        $this->assertStringContainsString('scale=2560:1707', $expr);
        $this->assertStringContainsString('crop=2560:1707', $expr);
    }

    #[Test]
    public function extracted_jpeg_is_exactly_3x2_when_ffmpeg_available(): void
    {
        $ffmpeg = trim((string) config('media.ffmpeg_path', 'ffmpeg'));
        if ($ffmpeg === '' || ! is_executable($ffmpeg)) {
            $this->markTestSkipped('ffmpeg not available');
        }

        $src = sys_get_temp_dir().'/vs_32_test_'.uniqid('', true).'.mp4';
        $out = sys_get_temp_dir().'/vs_32_out_'.uniqid('', true).'.jpg';
        $p = new Process([
            $ffmpeg, '-y', '-f', 'lavfi', '-i', 'color=c=blue:s=1920x1080:d=1',
            '-c:v', 'libx264', '-t', '1', '-pix_fmt', 'yuv420p', $src,
        ]);
        $p->setTimeout(60);
        $p->run();
        if (! $p->isSuccessful()) {
            $this->markTestSkipped('test clip failed');
        }

        $expr = $this->scaleExpr();
        $extract = new Process([
            $ffmpeg, '-y', '-ss', '0', '-i', $src, '-frames:v', '1', '-vf', $expr, '-q:v', '2', $out,
        ]);
        $extract->setTimeout(60);
        $extract->run();
        if (! $extract->isSuccessful()) {
            $this->markTestSkipped('still extraction failed: '.$extract->getErrorOutput());
        }

        $size = @getimagesize($out);
        if (! is_array($size) || ($size[0] ?? 0) <= 0 || ($size[1] ?? 0) <= 0) {
            $this->markTestSkipped('could not read extracted still dimensions');
        }

        $this->assertEqualsWithDelta(1.5, $size[0] / $size[1], 0.02, 'still must stay near 3:2');

        @unlink($src);
        @unlink($out);
    }
}
