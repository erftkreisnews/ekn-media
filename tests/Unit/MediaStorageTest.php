<?php

namespace Tests\Unit;

use App\Services\MediaStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaStorageTest extends TestCase
{
    public function test_store_uploaded_file_uses_active_disk(): void
    {
        Storage::fake('s3');
        Storage::fake('public');
        config()->set('media_storage.disk', 's3');
        config()->set('media_storage.fallback_disk', 'public');

        $service = app(MediaStorage::class);
        $path = $service->storeUploadedFileAs(
            UploadedFile::fake()->image('photo.jpg'),
            'news-media/1/image',
            '000001.jpg'
        );

        $this->assertSame('news-media/1/image/000001.jpg', $path);
        Storage::disk('s3')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_exists_uses_fallback_disk(): void
    {
        Storage::fake('s3');
        Storage::fake('public');
        config()->set('media_storage.disk', 's3');
        config()->set('media_storage.fallback_disk', 'public');

        $path = 'news-media/3/image/000003.jpg';
        Storage::disk('public')->put($path, 'legacy');

        $service = app(MediaStorage::class);

        $this->assertTrue($service->exists($path));
    }

    public function test_generate_media_path_uses_published_date_and_slug(): void
    {
        $service = app(MediaStorage::class);
        $news = (object) [
            'published_at' => Carbon::parse('2026-03-23 10:00:00'),
            'slug' => 'unfall-a61-lkw-brand',
            'title' => 'Titel wird nicht gebraucht',
        ];
        $media = (object) [
            'id' => 4821,
            'type' => 'image',
        ];
        $file = UploadedFile::fake()->image('Unfallstelle Köln.jpg');

        $path = $service->generateMediaPath($news, $media, $file, 'gallery');

        $this->assertSame(
            'news-media/2026/03/23/unfall-a61-lkw-brand/4821-gallery-unfallstelle-koln.jpg',
            $path
        );
    }

    public function test_generate_media_path_falls_back_when_published_at_missing(): void
    {
        $service = app(MediaStorage::class);
        Carbon::setTestNow(Carbon::parse('2026-04-01 08:00:00'));
        $news = (object) [
            'published_at' => null,
            'slug' => 'testartikel',
            'title' => 'Fallback',
        ];
        $media = (object) ['id' => 99, 'type' => 'video'];

        $path = $service->generateMediaPath($news, $media, 'Clip.MP4', 'video');

        $this->assertSame('news-media/2026/04/01/testartikel/99-video-clip.mp4', $path);
        Carbon::setTestNow();
    }

    public function test_generate_media_path_slug_fallbacks_are_applied(): void
    {
        $service = app(MediaStorage::class);
        $media = (object) ['id' => 11, 'type' => 'audio'];

        $fromTitle = $service->generateMediaPath(
            (object) ['published_at' => Carbon::parse('2026-03-01'), 'slug' => null, 'title' => 'Köln: Große Übung'],
            $media,
            'Tonspur.wav',
            'audio'
        );
        $this->assertStringContainsString('/koln-grosse-ubung/', $fromTitle);

        $fromDefault = $service->generateMediaPath(
            (object) ['published_at' => Carbon::parse('2026-03-01'), 'slug' => null, 'title' => '!!!'],
            $media,
            'Tonspur.wav',
            'audio'
        );
        $this->assertStringContainsString('/news-item/', $fromDefault);
    }

    public function test_generate_media_path_sanitizes_filename_and_limits_length(): void
    {
        $service = app(MediaStorage::class);
        $news = (object) [
            'published_at' => Carbon::parse('2026-03-23'),
            'slug' => 'slug',
            'title' => 'x',
        ];
        $media = (object) ['id' => 7, 'type' => 'image'];

        $path = $service->generateMediaPath(
            $news,
            $media,
            'ÄÖÜ & sehr sehr sehr sehr sehr sehr sehr sehr sehr langer Dateiname!!.jpeg',
            'gallery'
        );

        $this->assertMatchesRegularExpression(
            '#^news-media/2026/03/23/slug/7-gallery-[a-z0-9-]{1,50}\.jpeg$#',
            $path
        );
    }

    public function test_generated_path_can_be_stored_on_active_disk(): void
    {
        Storage::fake('s3');
        Storage::fake('public');
        config()->set('media_storage.disk', 's3');
        config()->set('media_storage.fallback_disk', 'public');

        $service = app(MediaStorage::class);
        $news = (object) [
            'published_at' => Carbon::parse('2026-03-23'),
            'slug' => 'einsatz',
            'title' => 'einsatz',
        ];
        $media = (object) ['id' => 123, 'type' => 'image'];
        $file = UploadedFile::fake()->image('Foto.jpg');
        $path = $service->generateMediaPath($news, $media, $file, 'gallery');

        $stored = $service->storeUploadedFileAs($file, dirname($path), basename($path));

        $this->assertSame($path, $stored);
        Storage::disk('s3')->assertExists($path);
    }

    public function test_url_prefers_local_fallback_when_fallback_exists(): void
    {
        Storage::fake('s3');
        Storage::fake('public');
        config()->set('media_storage.disk', 's3');
        config()->set('media_storage.fallback_disk', 'public');

        $path = 'news-media/3/image/000003.jpg';
        Storage::disk('public')->put($path, 'legacy');

        $service = app(MediaStorage::class);
        $this->assertSame(Storage::disk('public')->url($path), $service->url($path));
    }

    public function test_url_returns_active_url_when_fallback_missing(): void
    {
        Storage::fake('s3');
        Storage::fake('public');
        config()->set('media_storage.disk', 's3');
        config()->set('media_storage.fallback_disk', 'public');

        $path = 'news-media/4/image/000004.jpg';
        Storage::disk('s3')->put($path, 's3');

        $service = app(MediaStorage::class);
        $this->assertSame(Storage::disk('s3')->url($path), $service->url($path));
    }
}
