<?php

namespace Tests\Feature\Admin;

use App\Models\NewsItem;
use App\Models\PlannedEvent;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PlannedEventsAdminTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    protected function requiredVenueAddressPayload(): array
    {
        return [
            'venue_street' => 'Otto-Flimm-Straße 1',
            'venue_postal_code' => '53520',
            'venue_city' => 'Nürburg',
            'venue_state' => 'Rheinland-Pfalz',
            'venue_country' => 'Deutschland',
            'venue_country_code' => 'DE',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('access_admin');
    }

    public function test_guest_cannot_list_planned_events(): void
    {
        $this->get(route('admin.settings.planned-events.index'))
            ->assertRedirect();
    }

    public function test_admin_can_create_planned_event_with_teams(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $this->actingAs($user)
            ->post(route('admin.settings.planned-events.store'), array_merge([
                'name' => 'Test Event 2026',
                'date_label' => '14.–17.05.2026',
                'starts_at' => '2026-05-14',
                'ends_at' => '2026-05-17',
                'ai_context' => 'Hinweis für KI',
                'is_active' => '1',
                'sort_order' => 2,
                'teams' => [
                    ['name' => 'Rowe Racing', 'notes' => 'BMW M4 GT3'],
                    ['name' => '', 'notes' => ''],
                ],
            ], $this->requiredVenueAddressPayload()))
            ->assertRedirect();

        $event = PlannedEvent::query()->where('name', 'Test Event 2026')->first();
        $this->assertNotNull($event);
        $this->assertSame('14.–17.05.2026', $event->date_label);
        $this->assertTrue($event->is_active);
        $this->assertSame(1, $event->teams()->count());
        $this->assertSame('Rowe Racing', $event->teams()->first()->name);
        $this->assertSame('53520', $event->venue_postal_code);
        $this->assertSame('Nürburg', $event->venue_city);
    }

    public function test_store_requires_complete_venue_address(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $this->actingAs($user)
            ->post(route('admin.settings.planned-events.store'), [
                'name' => 'Ohne Adresse',
                'is_active' => '1',
                'sort_order' => 0,
            ])
            ->assertSessionHasErrors(['venue_street', 'venue_postal_code', 'venue_city', 'venue_state']);
    }

    public function test_cannot_delete_planned_event_when_news_assigned(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $event = PlannedEvent::query()->create([
            'name' => 'Bound Event',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $news = NewsItem::query()->create([
            'title' => 'Testmeldung',
            'planned_event_id' => $event->id,
            'author_id' => $user->id,
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->delete(route('admin.settings.planned-events.destroy', $event), [
                'confirmation' => 'ja',
            ])
            ->assertRedirect(route('admin.settings.planned-events.index'))
            ->assertSessionHas('error');

        $this->assertTrue(PlannedEvent::query()->whereKey($event->id)->exists());
        $this->assertSame($event->id, $news->fresh()->planned_event_id);
    }

    public function test_admin_can_upload_schedule_pdf(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $event = PlannedEvent::query()->create([
            'name' => 'Mit Zeitplan',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $pdf = UploadedFile::fake()->create('zeitplan.pdf', 120, 'application/pdf');

        $this->actingAs($user)
            ->put(route('admin.settings.planned-events.update', $event), array_merge([
                'name' => 'Mit Zeitplan',
                'is_active' => '1',
                'sort_order' => '0',
                'schedule_pdf' => $pdf,
            ], $this->requiredVenueAddressPayload()))
            ->assertRedirect(route('admin.settings.planned-events.edit', $event));

        $event->refresh();
        $this->assertTrue($event->hasSchedulePdf());
        $this->assertSame('zeitplan.pdf', $event->schedule_pdf_original_name);
        Storage::disk('local')->assertExists((string) $event->schedule_pdf_path);
    }

    public function test_admin_can_remove_schedule_pdf(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $event = PlannedEvent::query()->create([
            'name' => 'Mit Programm',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $path = 'planned-event-schedules/'.$event->id.'/test.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4');
        $event->forceFill([
            'schedule_pdf_path' => $path,
            'schedule_pdf_original_name' => 'ablauf.pdf',
            'schedule_pdf_extracted_text' => 'Extrakt bleibt nicht stehen',
        ])->save();

        $this->actingAs($user)
            ->put(route('admin.settings.planned-events.update', $event), array_merge([
                'name' => 'Mit Programm',
                'is_active' => '1',
                'sort_order' => '0',
                'remove_schedule_pdf' => '1',
            ], $this->requiredVenueAddressPayload()))
            ->assertRedirect(route('admin.settings.planned-events.edit', $event));

        $event->refresh();
        $this->assertFalse($event->hasSchedulePdf());
        $this->assertNull($event->schedule_pdf_extracted_text);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_schedule_pdf_download_works_when_file_only_on_legacy_app_root_disk(): void
    {
        Storage::fake('local');
        Storage::fake('planned_schedule_legacy');

        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $event = PlannedEvent::query()->create([
            'name' => 'Legacy PDF',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $path = 'planned-event-schedules/'.$event->id.'/only-legacy.pdf';
        Storage::disk('planned_schedule_legacy')->put($path, '%PDF-1.4 test');
        $event->forceFill([
            'schedule_pdf_path' => $path,
            'schedule_pdf_original_name' => 'zeitplan.pdf',
        ])->save();

        $this->actingAs($user)
            ->get(route('admin.settings.planned-events.schedule', $event))
            ->assertOk();
    }

    public function test_admin_can_reextract_schedule_pdf_text(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $event = PlannedEvent::query()->create([
            'name' => 'Reextract Event',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $binary = Pdf::loadHTML('<html><body><p>StageOne 20:00 Uhr Gala</p></body></html>')->output();
        $path = 'planned-event-schedules/'.$event->id.'/prog.pdf';
        Storage::disk('local')->put($path, $binary);
        $event->forceFill([
            'schedule_pdf_path' => $path,
            'schedule_pdf_original_name' => 'prog.pdf',
            'schedule_pdf_extracted_text' => 'ALT UND FALSCH',
        ])->save();

        $this->actingAs($user)
            ->put(route('admin.settings.planned-events.update', $event), array_merge([
                'name' => 'Reextract Event',
                'is_active' => '1',
                'sort_order' => '0',
                'schedule_pdf_extracted_text' => 'ALT UND FALSCH',
                'reextract_schedule_pdf' => '1',
            ], $this->requiredVenueAddressPayload()))
            ->assertRedirect(route('admin.settings.planned-events.edit', $event));

        $event->refresh();
        $this->assertStringContainsString('StageOne', (string) $event->schedule_pdf_extracted_text);
        $this->assertStringNotContainsString('ALT UND FALSCH', (string) $event->schedule_pdf_extracted_text);
    }

    public function test_update_preserves_schedule_extract_when_textarea_empty_in_post(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $event = PlannedEvent::query()->create([
            'name' => 'Extract Preserve',
            'is_active' => true,
            'sort_order' => 0,
            'schedule_pdf_extracted_text' => 'Donnerstag 08:30 RCN — Text aus PDF',
        ]);

        $this->actingAs($user)
            ->put(route('admin.settings.planned-events.update', $event), array_merge([
                'name' => 'Extract Preserve',
                'is_active' => '1',
                'sort_order' => '0',
                'schedule_pdf_extracted_text' => '',
            ], $this->requiredVenueAddressPayload()))
            ->assertRedirect(route('admin.settings.planned-events.edit', $event));

        $event->refresh();
        $this->assertSame('Donnerstag 08:30 RCN — Text aus PDF', $event->schedule_pdf_extracted_text);
    }

    public function test_admin_can_open_teams_overview_page(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $event = PlannedEvent::query()->create([
            'name' => 'Overview Event',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $event->teams()->create([
            'name' => 'Box 1 | Team Alpha',
            'notes' => "Fahrer: A\nFahrer: B",
            'sort_order' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('admin.settings.planned-events.teams', $event))
            ->assertOk()
            ->assertSee('Teilnehmende / Acts / Teams', false)
            ->assertSee('Box 1 | Team Alpha', false)
            ->assertSee('Fahrer: A', false);
    }

    public function test_admin_can_import_teams_via_bulk_textarea_on_update(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $event = PlannedEvent::query()->create([
            'name' => 'Bulk Test',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $event->teams()->create([
            'name' => 'Alt',
            'notes' => 'wird ersetzt',
            'sort_order' => 0,
        ]);

        $bulk = "Box 1 | Startnr. 1 | Team Alpha SP9\nFahrer: A\n\nBox 2 | Startnr. 2 | Team Beta SP9\nFahrer: B";

        $this->actingAs($user)
            ->put(route('admin.settings.planned-events.update', $event), array_merge([
                'name' => 'Bulk Test',
                'is_active' => '1',
                'sort_order' => '0',
                'teams_bulk' => $bulk,
                'teams' => [
                    ['name' => 'Ignoriert', 'notes' => 'wenn Bulk gesetzt'],
                ],
            ], $this->requiredVenueAddressPayload()))
            ->assertRedirect(route('admin.settings.planned-events.edit', $event));

        $event->refresh();
        $teams = $event->teams()->orderBy('sort_order')->get();
        $this->assertCount(2, $teams);
        $this->assertSame('Box 1 | Startnr. 1 | Team Alpha SP9', $teams[0]->name);
        $this->assertStringContainsString('Fahrer: A', (string) $teams[0]->notes);
        $this->assertSame('Box 2 | Startnr. 2 | Team Beta SP9', $teams[1]->name);
    }
}
