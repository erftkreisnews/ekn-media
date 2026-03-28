<?php

namespace Tests\Unit;

use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsItemMediaRedactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    protected function createNewsItem(): NewsItem
    {
        $user = User::factory()->create();

        return NewsItem::create([
            'title' => 'Test',
            'slug' => 'test-'.uniqid(),
            'status' => 'published',
            'author_id' => $user->id,
        ]);
    }

    /** Public darf bei pending/failed niemals Original ausliefern. */
    public function test_public_path_is_null_when_redaction_pending(): void
    {
        $newsItem = $this->createNewsItem();
        $media = $newsItem->media()->create([
            'type' => 'image',
            'path' => 'news-media/1/image/000001.jpg',
            'original_name' => 'test.jpg',
            'redaction_status' => NewsItemMedia::REDACTION_PENDING,
        ]);
        Storage::disk('public')->put($media->path, 'fake-image');

        $this->assertNull($media->public_path);
        $this->assertNull($media->public_url);
        $this->assertFalse($media->isSafeForPublic());
    }

    /** Public liefert redacted_path nur bei status done und existierender Datei. */
    public function test_public_path_uses_redacted_when_done(): void
    {
        $newsItem = $this->createNewsItem();
        $media = $newsItem->media()->create([
            'type' => 'image',
            'path' => 'news-media/1/image/000001.jpg',
            'original_name' => 'test.jpg',
            'redaction_status' => NewsItemMedia::REDACTION_DONE,
            'redacted_path' => 'media/redacted/1/1.jpg',
        ]);
        Storage::disk('public')->put($media->path, 'original');
        Storage::disk('public')->put($media->redacted_path, 'redacted');

        $this->assertSame('media/redacted/1/1.jpg', $media->public_path);
        $this->assertNotNull($media->public_url);
        $this->assertTrue($media->isSafeForPublic());
    }

    /** Bei failed ist public_path null. */
    public function test_public_path_is_null_when_redaction_failed(): void
    {
        $newsItem = $this->createNewsItem();
        $media = $newsItem->media()->create([
            'type' => 'image',
            'path' => 'news-media/1/image/000001.jpg',
            'original_name' => 'test.jpg',
            'redaction_status' => NewsItemMedia::REDACTION_FAILED,
        ]);
        Storage::disk('public')->put($media->path, 'fake');

        $this->assertNull($media->public_path);
        $this->assertFalse($media->isSafeForPublic());
    }

    /** Legacy (redaction_status null) liefert path für Rückwärtskompatibilität. */
    public function test_public_path_uses_path_when_legacy(): void
    {
        $newsItem = $this->createNewsItem();
        $media = $newsItem->media()->create([
            'type' => 'image',
            'path' => 'news-media/1/image/000001.jpg',
            'original_name' => 'test.jpg',
            'redaction_status' => null,
        ]);
        Storage::disk('public')->put($media->path, 'legacy');

        $this->assertSame($media->path, $media->public_path);
        $this->assertTrue($media->isSafeForPublic());
    }

    /** Video ignoriert Redaction, public_path = path. */
    public function test_video_public_path_is_always_path(): void
    {
        $newsItem = $this->createNewsItem();
        $media = $newsItem->media()->create([
            'type' => 'video',
            'path' => 'news-media/1/video/000001.mp4',
            'original_name' => 'test.mp4',
            'redaction_status' => NewsItemMedia::REDACTION_PENDING,
        ]);
        Storage::disk('public')->put($media->path, 'video');

        $this->assertSame($media->path, $media->public_path);
    }
}
