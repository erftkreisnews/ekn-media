<?php

namespace Tests\Unit;

use App\Models\NewsItemMedia;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsItemMediaDeliveryDownloadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('s3');
        config([
            'media_storage.disk' => 'public',
            'media_storage.fallback_disk' => 'public',
        ]);
    }

    public function test_image_download_uses_original_path_not_preview(): void
    {
        Storage::disk('public')->put('orig/full.jpg', 'original-bytes');
        Storage::disk('public')->put('derived/preview.webp', 'preview-bytes');

        $media = new NewsItemMedia([
            'type' => 'image',
            'path' => 'orig/full.jpg',
            'preview_path' => 'derived/preview.webp',
        ]);

        $this->assertSame('orig/full.jpg', $media->resolveDeliveryDownloadRelativePath());
    }

    public function test_image_download_uses_original_not_redacted_path(): void
    {
        Storage::disk('public')->put('orig/upload.jpg', 'original');
        Storage::disk('public')->put('redacted/out.jpg', 'redacted');

        $media = new NewsItemMedia([
            'type' => 'image',
            'path' => 'orig/upload.jpg',
            'redacted_path' => 'redacted/out.jpg',
            'redaction_status' => NewsItemMedia::REDACTION_DONE,
        ]);

        $this->assertSame('orig/upload.jpg', $media->resolveDeliveryDownloadRelativePath());
    }

    public function test_video_download_uses_original_not_poster_preview(): void
    {
        Storage::disk('public')->put('video/clip.mp4', 'video');
        Storage::disk('public')->put('video/derived/poster.webp', 'poster');

        $media = new NewsItemMedia([
            'type' => 'video',
            'path' => 'video/clip.mp4',
            'preview_path' => 'video/derived/poster.webp',
        ]);

        $this->assertSame('video/clip.mp4', $media->resolveDeliveryDownloadRelativePath());
    }

    public function test_audio_download_uses_original_path(): void
    {
        Storage::disk('public')->put('audio/track.m4a', 'audio');

        $media = new NewsItemMedia([
            'type' => 'audio',
            'path' => 'audio/track.m4a',
        ]);

        $this->assertSame('audio/track.m4a', $media->resolveDeliveryDownloadRelativePath());
    }

    public function test_falls_back_to_original_path_column_when_path_missing(): void
    {
        Storage::disk('public')->put('backup/orig.jpg', 'backup');

        $media = new NewsItemMedia([
            'type' => 'image',
            'path' => 'missing/on/disk.jpg',
            'original_path' => 'backup/orig.jpg',
        ]);

        $this->assertSame('backup/orig.jpg', $media->resolveDeliveryDownloadRelativePath());
    }

    public function test_returns_null_when_only_preview_exists(): void
    {
        Storage::disk('public')->put('only/preview.webp', 'x');

        $media = new NewsItemMedia([
            'type' => 'image',
            'path' => 'gone/original.jpg',
            'preview_path' => 'only/preview.webp',
        ]);

        $this->assertNull($media->resolveDeliveryDownloadRelativePath());
    }
}
