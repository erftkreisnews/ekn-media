<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Squad;
use App\Models\SquadPlayer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class HouseSquadController extends Controller
{
    public function edit(): View|RedirectResponse
    {
        if (! Schema::hasTable('squads')) {
            return redirect()->route('admin.settings.index')
                ->with('error', 'Tabellen für Haus-Kader fehlen. Bitte Migrationen ausführen.');
        }

        $squad = Squad::fcKoelnHerren();
        $squad->load('players');

        return view('admin.settings.house-squad.edit', compact('squad'));
    }

    public function update(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('squads')) {
            return redirect()->route('admin.settings.index')->with('error', 'Tabelle fehlt.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:512'],
            'include_in_media_ai' => ['nullable', 'boolean'],
            'players' => ['nullable', 'array'],
            'players.*.shirt_number' => ['nullable', 'string', 'max:20'],
            'players.*.full_name' => ['nullable', 'string', 'max:255'],
            'players.*.position_label' => ['nullable', 'string', 'max:120'],
        ]);

        $squad = Squad::fcKoelnHerren();
        $squad->update([
            'name' => $validated['name'],
            'include_in_media_ai' => $request->boolean('include_in_media_ai', true),
        ]);

        $this->syncPlayers($squad, $request->input('players', []));

        return redirect()
            ->route('admin.settings.house-squad.edit')
            ->with('status', 'Haus-Kader gespeichert.');
    }

    /**
     * @param  array<int, mixed>  $rows
     */
    protected function syncPlayers(Squad $squad, array $rows): void
    {
        $squad->players()->delete();

        $index = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['full_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $num = trim((string) ($row['shirt_number'] ?? ''));
            $pos = trim((string) ($row['position_label'] ?? ''));

            SquadPlayer::query()->create([
                'squad_id' => $squad->id,
                'shirt_number' => $num !== '' ? $num : null,
                'full_name' => $name,
                'position_label' => $pos !== '' ? $pos : null,
                'sort_order' => $index,
            ]);
            $index++;
        }
    }
}
