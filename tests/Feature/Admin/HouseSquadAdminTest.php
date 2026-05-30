<?php

namespace Tests\Feature\Admin;

use App\Models\Squad;
use App\Models\SquadPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class HouseSquadAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('access_admin');
    }

    public function test_guest_cannot_open_house_squad(): void
    {
        $this->get(route('admin.settings.house-squad.edit'))
            ->assertRedirect();
    }

    public function test_admin_can_save_house_squad_players(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $this->actingAs($user)
            ->put(route('admin.settings.house-squad.update'), [
                'name' => '1. FC Köln – Herren',
                'include_in_media_ai' => '1',
                'players' => [
                    ['shirt_number' => '1', 'full_name' => 'Marvin Schwäbe', 'position_label' => 'Torwart'],
                    ['shirt_number' => '', 'full_name' => 'René Wagner', 'position_label' => 'Cheftrainer'],
                ],
            ])
            ->assertRedirect(route('admin.settings.house-squad.edit'));

        $squad = Squad::query()->where('slug', Squad::FC_KOELN_HERREN_SLUG)->first();
        $this->assertNotNull($squad);
        $this->assertSame('1. FC Köln – Herren', $squad->name);
        $this->assertCount(2, $squad->players);
        $this->assertSame('Marvin Schwäbe', $squad->players->first()->full_name);
        $this->assertSame('René Wagner', $squad->players->last()->full_name);
    }

    public function test_update_replaces_players(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        Squad::fcKoelnHerren();
        SquadPlayer::query()->create([
            'squad_id' => Squad::fcKoelnHerren()->id,
            'shirt_number' => '99',
            'full_name' => 'Alt',
            'sort_order' => 0,
        ]);

        $this->actingAs($user)
            ->put(route('admin.settings.house-squad.update'), [
                'name' => '1. FC Köln – Herren',
                'include_in_media_ai' => '1',
                'players' => [
                    ['shirt_number' => '7', 'full_name' => 'Neu', 'position_label' => 'Sturm'],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(1, SquadPlayer::query()->count());
        $this->assertSame('Neu', SquadPlayer::query()->value('full_name'));
    }
}
