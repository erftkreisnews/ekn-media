<?php

namespace Tests\Unit;

use App\Jobs\GenerateVideoStills;
use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Models\User;
use App\Services\MediaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class GenerateVideoStillsDuplicateRefsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config([
            'media_storage.disk' => 'public',
            'media_storage.fallback_disk' => 'public',
        ]);
    }

    private function jpegBytes(int $r, int $g, int $b): string
    {
        $img = imagecreatetruecolor(32, 32);
        imagefill($img, 0, 0, imagecolorallocate($img, $r, $g, $b));
        ob_start();
        imagejpeg($img, null, 90);
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    private function createNewsItem(): NewsItem
    {
        $user = User::factory()->create();

        return NewsItem::create([
            'title' => 'Test',
            'slug' => 'test-'.uniqid(),
            'status' => 'published',
            'author_id' => $user->id,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function invokeBuildRefs(NewsItem $newsItem): array
    {
        $videoMedia = new NewsItemMedia(['type' => 'video']);
        $job = new GenerateVideoStills($videoMedia);
        $ref = new ReflectionClass($job);
        $m = $ref->getMethod('buildExistingImageDuplicateRefs');
        $m->setAccessible(true);

        return $m->invoke($job, $newsItem->fresh(), app(MediaStorage::class));
    }

    #[Test]
    public function build_existing_duplicate_refs_skips_standbild_aus_video_but_keeps_manual_captions(): void
    {
        if (! function_exists('imagejpeg')) {
            $this->markTestSkipped('GD JPEG support required');
        }

        $newsItem = $this->createNewsItem();
        $pathVideo = 'news-media/'.$newsItem->id.'/image/still_video.jpg';
        $pathManual = 'news-media/'.$newsItem->id.'/image/manual.jpg';

        Storage::disk('public')->put($pathVideo, $this->jpegBytes(10, 20, 30));
        Storage::disk('public')->put($pathManual, $this->jpegBytes(200, 40, 50));

        $newsItem->media()->createMany([
            [
                'type' => 'image',
                'path' => $pathVideo,
                'original_name' => 'still.jpg',
                'caption' => 'Standbild aus Video',
            ],
            [
                'type' => 'image',
                'path' => $pathManual,
                'original_name' => 'manual.jpg',
                'caption' => 'Manuell hochgeladen',
            ],
        ]);

        $refs = $this->invokeBuildRefs($newsItem);

        $this->assertCount(1, $refs);
        $this->assertSame('manual.jpg', basename((string) ($refs[0]['localPath'] ?? '')));
    }

    #[Test]
    public function build_existing_duplicate_refs_is_empty_when_only_standbild_aus_video_images_exist(): void
    {
        if (! function_exists('imagejpeg')) {
            $this->markTestSkipped('GD JPEG support required');
        }

        $newsItem = $this->createNewsItem();
        $path = 'news-media/'.$newsItem->id.'/image/still_only.jpg';
        Storage::disk('public')->put($path, $this->jpegBytes(1, 2, 3));

        $newsItem->media()->create([
            'type' => 'image',
            'path' => $path,
            'original_name' => 's.jpg',
            'caption' => 'Standbild aus Video',
        ]);

        $refs = $this->invokeBuildRefs($newsItem);

        $this->assertCount(0, $refs);
    }

    #[Test]
    public function build_existing_duplicate_refs_skips_images_linked_to_source_video_even_without_legacy_caption(): void
    {
        if (! function_exists('imagejpeg')) {
            $this->markTestSkipped('GD JPEG support required');
        }

        $newsItem = $this->createNewsItem();
        $pathVideo = 'news-media/'.$newsItem->id.'/image/from_video.jpg';
        $pathManual = 'news-media/'.$newsItem->id.'/image/manual2.jpg';

        Storage::disk('public')->put($pathVideo, $this->jpegBytes(11, 22, 33));
        Storage::disk('public')->put($pathManual, $this->jpegBytes(201, 41, 51));

        $videoRow = $newsItem->media()->create([
            'type' => 'video',
            'path' => 'news-media/'.$newsItem->id.'/video/v.mp4',
            'original_name' => 'v.mp4',
        ]);

        $newsItem->media()->createMany([
            [
                'type' => 'image',
                'path' => $pathVideo,
                'original_name' => 'auto.jpg',
                'caption' => 'Nur Meldungstitel',
                'source_video_media_id' => $videoRow->id,
            ],
            [
                'type' => 'image',
                'path' => $pathManual,
                'original_name' => 'manual2.jpg',
                'caption' => 'Redaktion',
            ],
        ]);

        $refs = $this->invokeBuildRefs($newsItem);

        $this->assertCount(1, $refs);
        $this->assertSame('manual2.jpg', basename((string) ($refs[0]['localPath'] ?? '')));
    }
}
