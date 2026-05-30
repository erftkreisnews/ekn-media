<?php

namespace Tests\Unit;

use App\Models\Squad;
use App\Models\SquadPlayer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SquadMediaAiFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_format_for_media_ai_lists_players_with_number_and_role(): void
    {
        $squad = Squad::query()->create([
            'slug' => 'unit-test-squad-format',
            'name' => '1. FC Köln – Herren',
            'is_active' => true,
            'include_in_media_ai' => true,
        ]);
        SquadPlayer::query()->create([
            'squad_id' => $squad->id,
            'shirt_number' => '7',
            'full_name' => 'Luca Waldschmidt',
            'position_label' => 'Sturm',
            'sort_order' => 0,
        ]);

        $squad->load('players');
        $text = $squad->formatForMediaAiContext();

        $this->assertStringContainsString('1. FC Köln', $text);
        $this->assertStringContainsString('#7', $text);
        $this->assertStringContainsString('Luca Waldschmidt', $text);
        $this->assertStringContainsString('Sturm', $text);
    }

    public function test_format_returns_empty_when_include_in_media_ai_off(): void
    {
        $squad = Squad::query()->create([
            'slug' => 'unit-test-squad-format-off',
            'name' => 'Test',
            'is_active' => true,
            'include_in_media_ai' => false,
        ]);
        SquadPlayer::query()->create([
            'squad_id' => $squad->id,
            'shirt_number' => '1',
            'full_name' => 'X',
            'sort_order' => 0,
        ]);
        $squad->load('players');

        $this->assertSame('', $squad->formatForMediaAiContext());
    }
}
