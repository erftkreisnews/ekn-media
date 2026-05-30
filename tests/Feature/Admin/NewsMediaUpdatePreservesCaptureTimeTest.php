<?php

namespace Tests\Feature\Admin;

use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NewsMediaUpdatePreservesCaptureTimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('access_admin');
    }

    public function test_patch_ohne_capture_time_feld_laesst_aufnahmezeit_in_der_db_unveraendert(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $news = NewsItem::query()->create([
            'title' => 'Meldung',
            'slug' => 'meldung-'.uniqid(),
            'author_id' => $user->id,
            'status' => 'draft',
        ]);

        $expected = Carbon::parse('2026-05-09 22:15:00', 'Europe/Berlin');

        $media = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            // Kein echtes Objekt im Speicher: sonst würde EXIF aus der Datei die DB überschreiben.
            'path' => 'news-media/'.$news->id.'/image/missing-'.uniqid('', true).'.jpg',
            'original_name' => 'foto.jpg',
            'sort_order' => 1,
            'is_visible' => true,
            'versand' => true,
            'delivery_visible_for_organization_ids' => [],
            'capture_time' => $expected,
        ]);

        $this->actingAs($user)
            ->patch(route('admin.news.media.update', [$news, $media]), [
                'image_title' => 'Nur Titel geändert',
                'is_visible' => '1',
                'versand' => '1',
            ])
            ->assertRedirect();

        $media->refresh();
        $this->assertNotNull($media->capture_time);
        $this->assertSame(
            $expected->format('Y-m-d H:i:s'),
            $media->capture_time->timezone('Europe/Berlin')->format('Y-m-d H:i:s')
        );
    }

    public function test_autosave_liefert_json_ohne_redirect(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $news = NewsItem::query()->create([
            'title' => 'Meldung',
            'slug' => 'meldung-'.uniqid(),
            'author_id' => $user->id,
            'status' => 'draft',
        ]);

        $media = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/'.$news->id.'/image/missing-'.uniqid('', true).'.jpg',
            'original_name' => 'foto.jpg',
            'sort_order' => 1,
            'is_visible' => true,
            'versand' => false,
            'delivery_visible_for_organization_ids' => [],
            'image_title' => 'Alt',
        ]);

        $this->actingAs($user)
            ->patchJson(route('admin.news.media.update', [$news, $media]), [
                'image_title' => 'Neu per Autosave',
                'is_visible' => '1',
                'versand' => '0',
                'autosave' => '1',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $media->refresh();
        $this->assertSame('Neu per Autosave', $media->image_title);
    }
}
