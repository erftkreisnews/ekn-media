<?php

namespace Tests\Unit;

use App\Models\Delivery;
use App\Models\NewsItem;
use App\Models\NewsItemUpdate;
use App\Models\User;
use App\Services\NewsDeliveryUpdateSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsDeliveryUpdateSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_summary_note_from_new_update_and_media(): void
    {
        $user = User::factory()->create();
        $news = NewsItem::create([
            'title' => 'Einsatz',
            'author_id' => $user->id,
            'update_type' => 'update',
            'status' => 'draft',
            'is_wdr_job' => false,
        ]);

        $prior = Delivery::create([
            'news_item_id' => $news->id,
            'recipient_email' => 'a@example.com',
            'expires_at' => now()->addDay(),
            'created_by' => $user->id,
        ]);
        $prior->created_at = now()->subHour();
        $prior->saveQuietly();

        NewsItemUpdate::create([
            'news_item_id' => $news->id,
            'type' => NewsItemUpdate::TYPE_SITUATION,
            'body' => 'Neuer Stand',
            'show_in_mail' => true,
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $service = app(NewsDeliveryUpdateSummaryService::class);
        $note = $service->buildSummaryNote($news, $service->baselineAt($news), true);

        $this->assertStringContainsString('1 neuer Einsatz-Stand', $note);
    }
}
