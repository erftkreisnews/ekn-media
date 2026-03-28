<?php

namespace Tests\Unit;

use App\Models\NewsItemMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsItemMediaFileSizeKbTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('s3');

        config([
            'media_storage.disk' => 's3',
            'media_storage.fallback_disk' => 'public',
        ]);
    }

    public function test_file_size_kb_reads_from_active_disk(): void
    {
        Storage::disk('s3')->put('imgs/test.jpg', str_repeat('a', 2048));

        $m = new NewsItemMedia([
            'type' => 'image',
            'path' => 'imgs/test.jpg',
            'original_path' => null,
        ]);

        $this->assertSame(2, $m->file_size_kb);
    }

    public function test_file_size_kb_falls_back_to_original_path(): void
    {
        Storage::disk('public')->put('imgs/orig.jpg', str_repeat('b', 4096));

        $m = new NewsItemMedia([
            'type' => 'image',
            'path' => 'missing/on/s3.jpg',
            'original_path' => 'imgs/orig.jpg',
        ]);

        $this->assertSame(4, $m->file_size_kb);
    }

    public function test_file_size_kb_returns_zero_when_missing(): void
    {
        $m = new NewsItemMedia([
            'type' => 'image',
            'path' => 'does/not/exist.jpg',
            'original_path' => null,
        ]);

        $this->assertSame(0, $m->file_size_kb);
    }
}
