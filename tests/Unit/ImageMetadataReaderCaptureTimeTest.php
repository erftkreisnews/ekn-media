<?php

namespace Tests\Unit;

use App\Services\ImageMetadataReader;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ImageMetadataReaderCaptureTimeTest extends TestCase
{
    public function test_capture_time_carbon_from_datetime_original_string(): void
    {
        $c = ImageMetadataReader::captureTimeCarbonFromMetadata([
            'datetime_original' => '2026-05-09T18:51',
        ]);

        $this->assertInstanceOf(Carbon::class, $c);
        $this->assertSame('2026-05-09 18:51:00', $c->format('Y-m-d H:i:s'));
    }

    public function test_capture_time_carbon_returns_null_when_missing(): void
    {
        $this->assertNull(ImageMetadataReader::captureTimeCarbonFromMetadata([]));
        $this->assertNull(ImageMetadataReader::captureTimeCarbonFromMetadata(['datetime_original' => '']));
    }
}
