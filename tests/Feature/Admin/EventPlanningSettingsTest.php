<?php

namespace Tests\Feature\Admin;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EventPlanningSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('access_admin');
    }

    public function test_guest_cannot_open_event_planning(): void
    {
        $this->get(route('admin.settings.planned-events.global'))
            ->assertRedirect();
    }

    public function test_admin_can_save_event_planning_settings(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $this->actingAs($user)
            ->post(route('admin.settings.planned-events.global.save'), [
                'event_planning_enabled' => '1',
                'event_planning_context' => 'Test: Nordschleife, Qualifying.',
            ])
            ->assertRedirect(route('admin.settings.planned-events.global'))
            ->assertSessionHas('status');

        $this->assertSame('1', SiteSetting::get(SiteSetting::MEDIA_AI_EVENT_PLANNING_ENABLED));
        $this->assertSame('Test: Nordschleife, Qualifying.', SiteSetting::get(SiteSetting::MEDIA_AI_EVENT_PLANNING_CONTEXT));
    }

    public function test_admin_can_disable_event_planning_while_keeping_text(): void
    {
        SiteSetting::put(SiteSetting::MEDIA_AI_EVENT_PLANNING_ENABLED, '1');
        SiteSetting::put(SiteSetting::MEDIA_AI_EVENT_PLANNING_CONTEXT, 'Behalten');

        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $this->actingAs($user)
            ->post(route('admin.settings.planned-events.global.save'), [
                'event_planning_enabled' => '0',
                'event_planning_context' => 'Behalten',
            ])
            ->assertRedirect(route('admin.settings.planned-events.global'));

        $this->assertSame('0', SiteSetting::get(SiteSetting::MEDIA_AI_EVENT_PLANNING_ENABLED));
        $this->assertSame('Behalten', SiteSetting::get(SiteSetting::MEDIA_AI_EVENT_PLANNING_CONTEXT));
    }
}
