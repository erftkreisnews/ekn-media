<?php

namespace Tests\Unit;

use App\Models\NewsItem;
use App\Models\NewsItemUpdate;
use App\Models\User;
use App\Services\DeliveryTimelineBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryTimelineBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_chronological_timeline_with_first_report_and_updates(): void
    {
        $user = User::factory()->create();
        $news = NewsItem::create([
            'title' => 'Einsatz Köln',
            'subheadline' => 'Risse an Fassade',
            'body' => '<p>Erster Text</p>',
            'author_id' => $user->id,
            'published_at' => now()->setTime(22, 30),
            'status' => 'published',
            'is_wdr_job' => false,
        ]);

        NewsItemUpdate::create([
            'news_item_id' => $news->id,
            'type' => NewsItemUpdate::TYPE_SITUATION,
            'title' => 'Einsatz beendet',
            'body' => 'Feuerwehr meldet Ende des Einsatzes.',
            'happened_at' => now()->setTime(23, 30),
            'show_in_mail' => false,
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $timeline = app(DeliveryTimelineBuilder::class)->build($news->fresh(['updates']));

        $this->assertCount(2, $timeline);
        $this->assertSame('Update zur Lage', $timeline[0]['label']);
        $this->assertStringContainsString('Feuerwehr meldet', $timeline[0]['body']);
        $this->assertSame('Erstmeldung', $timeline[1]['label']);
    }
}
