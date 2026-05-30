@extends('layouts.admin')

@section('content')
    <div class="max-w-5xl space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 border border-green-200">
                <p class="text-sm text-green-800">{{ session('status') }}</p>
            </div>
        @endif

        <div>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                <a href="{{ route('admin.event-planning.index') }}" class="text-gray-600 hover:text-[#092E48]">← Eventplanung</a>
                <a href="{{ route('admin.settings.index') }}" class="text-gray-600 hover:text-[#092E48]">Einstellungen</a>
            </div>
            <h1 class="mt-2 text-2xl font-semibold text-gray-900">Haus-Kader</h1>
            <p class="mt-1 text-xs font-medium text-gray-500 uppercase tracking-wide">Teil von KI-Vorgaben für Sport &amp; Events</p>
            <p class="mt-2 text-sm text-gray-600">
                Stammsquad (z. B. 1. FC Köln Herren): hier pflegen, bei Wechseln anpassen (z. B. neuer Torwart).
                Die <span class="font-medium text-gray-800">Bild-KI</span> bekommt diese Liste automatisch als Referenz – nur nutzen, was zum Foto passt.
            </p>
        </div>

        <form method="post" action="{{ route('admin.settings.house-squad.update') }}" class="bg-white shadow-sm rounded-lg border border-gray-200 p-6 space-y-6">
            @csrf
            @method('PUT')

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Bezeichnung</label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    value="{{ old('name', $squad->name) }}"
                    required
                    maxlength="512"
                    class="mt-1 block w-full max-w-xl rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm"
                />
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-2">
                <input
                    type="hidden"
                    name="include_in_media_ai"
                    value="0"
                />
                <input
                    type="checkbox"
                    name="include_in_media_ai"
                    id="include_in_media_ai"
                    value="1"
                    class="rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                    @checked(old('include_in_media_ai', $squad->include_in_media_ai ? '1' : '0') === '1')
                />
                <label for="include_in_media_ai" class="text-sm text-gray-800">In Bild-KI-Kontext einbeziehen</label>
            </div>

            <div class="border-t border-gray-200 pt-6">
                <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-1">Spieler &amp; Betreuer</h2>
                <p class="text-xs text-gray-500 mb-4">Rückennummer leer lassen bei Trainern. Zeilen ohne Namen werden ignoriert.</p>

                <div id="house-squad-player-rows" class="space-y-3">
                    @php
                        $playerRows = old('players');
                        if (! is_array($playerRows)) {
                            $playerRows = $squad->players->map(fn ($p) => [
                                'shirt_number' => $p->shirt_number ?? '',
                                'full_name' => $p->full_name,
                                'position_label' => $p->position_label ?? '',
                            ])->values()->all();
                            if ($playerRows === []) {
                                $playerRows = [['shirt_number' => '', 'full_name' => '', 'position_label' => '']];
                            }
                        }
                    @endphp
                    @foreach ($playerRows as $i => $row)
                        <div class="house-squad-player-row flex flex-wrap gap-2 items-start border border-gray-100 rounded-md p-3 bg-gray-50/50">
                            <input
                                type="text"
                                name="players[{{ $i }}][shirt_number]"
                                value="{{ $row['shirt_number'] ?? '' }}"
                                placeholder="Nr."
                                class="block w-16 rounded-md border-gray-300 shadow-sm text-sm"
                            />
                            <input
                                type="text"
                                name="players[{{ $i }}][full_name]"
                                value="{{ $row['full_name'] ?? '' }}"
                                placeholder="Name"
                                class="block flex-1 min-w-[10rem] rounded-md border-gray-300 shadow-sm text-sm"
                            />
                            <input
                                type="text"
                                name="players[{{ $i }}][position_label]"
                                value="{{ $row['position_label'] ?? '' }}"
                                placeholder="Position / Rolle (z. B. Torwart, Sturm)"
                                class="block flex-1 min-w-[12rem] rounded-md border-gray-300 shadow-sm text-sm"
                            />
                            <button
                                type="button"
                                class="remove-player text-sm text-red-600 hover:underline shrink-0"
                                @if (count($playerRows) <= 1) style="visibility:hidden" @endif
                            >Entfernen</button>
                        </div>
                    @endforeach
                </div>
                <button type="button" id="add-house-squad-player" class="mt-3 text-sm font-medium text-[#092E48] hover:underline">
                    + Zeile hinzufügen
                </button>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-2xl text-white bg-[#092E48] hover:bg-[#0b3858] min-h-[2.75rem]">
                    Speichern
                </button>
            </div>
        </form>
    </div>

    <script>
    (function () {
        const wrap = document.getElementById('house-squad-player-rows');
        if (!wrap) return;
        let idx = wrap.querySelectorAll('.house-squad-player-row').length;
        document.getElementById('add-house-squad-player')?.addEventListener('click', function () {
            const div = document.createElement('div');
            div.className = 'house-squad-player-row flex flex-wrap gap-2 items-start border border-gray-100 rounded-md p-3 bg-gray-50/50';
            div.innerHTML =
                '<input type="text" name="players[' + idx + '][shirt_number]" placeholder="Nr." class="block w-16 rounded-md border-gray-300 shadow-sm text-sm" />' +
                '<input type="text" name="players[' + idx + '][full_name]" placeholder="Name" class="block flex-1 min-w-[10rem] rounded-md border-gray-300 shadow-sm text-sm" />' +
                '<input type="text" name="players[' + idx + '][position_label]" placeholder="Position / Rolle (z. B. Torwart, Sturm)" class="block flex-1 min-w-[12rem] rounded-md border-gray-300 shadow-sm text-sm" />' +
                '<button type="button" class="remove-player text-sm text-red-600 hover:underline shrink-0">Entfernen</button>';
            wrap.appendChild(div);
            idx++;
            div.querySelector('.remove-player')?.addEventListener('click', function () {
                removeRow(div);
            });
            refreshRemoveVisibility();
        });
        function removeRow(div) {
            if (wrap.querySelectorAll('.house-squad-player-row').length <= 1) return;
            div.remove();
            refreshRemoveVisibility();
        }
        function refreshRemoveVisibility() {
            const rows = wrap.querySelectorAll('.house-squad-player-row');
            const show = rows.length > 1;
            rows.forEach(function (row) {
                const b = row.querySelector('.remove-player');
                if (b) b.style.visibility = show ? 'visible' : 'hidden';
            });
        }
        wrap.querySelectorAll('.remove-player').forEach(function (btn) {
            btn.addEventListener('click', function () {
                removeRow(btn.closest('.house-squad-player-row'));
            });
        });
    })();
    </script>
@endsection
