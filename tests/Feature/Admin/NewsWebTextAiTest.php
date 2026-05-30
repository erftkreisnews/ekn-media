<?php

namespace Tests\Feature\Admin;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NewsWebTextAiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('access_admin');
    }

    public function test_guest_cannot_polish_web_text(): void
    {
        $this->postJson(route('admin.news.ai.polish-web-text'), [
            'text' => 'Kurzer Rohtext für die Meldung.',
        ])->assertUnauthorized();
    }

    public function test_polish_returns_503_without_api_key(): void
    {
        config(['media_ai.api_key' => null]);

        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $this->actingAs($user)
            ->postJson(route('admin.news.ai.polish-web-text'), [
                'text' => 'Kurzer Rohtext für die Meldung.',
            ])
            ->assertStatus(503)
            ->assertJsonPath('message', 'OPENAI_API_KEY ist nicht konfiguriert.');
    }

    public function test_polish_returns_revised_text_when_openai_succeeds(): void
    {
        config(['media_ai.api_key' => 'sk-test-fake']);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => "  Überarbeiteter Text.\n"]],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $this->actingAs($user)
            ->postJson(route('admin.news.ai.polish-web-text'), [
                'text' => 'Kurzer Rohtext für die Meldung.',
            ])
            ->assertOk()
            ->assertJson(['text' => 'Überarbeiteter Text.']);
    }

    public function test_admin_can_save_web_text_system_prompt(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $this->actingAs($user)
            ->post(route('admin.settings.ai.news-web-text-prompt'), [
                'news_web_text_ai_system_prompt' => 'EKN Test-Leitlinie nur für PHPUnit.',
            ])
            ->assertRedirect(route('admin.settings.ai'));

        $this->assertDatabaseHas('settings', [
            'key' => SiteSetting::NEWS_WEB_TEXT_AI_SYSTEM_PROMPT,
            'value' => 'EKN Test-Leitlinie nur für PHPUnit.',
        ]);
    }
}
