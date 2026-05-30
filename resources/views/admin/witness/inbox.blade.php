@extends('layouts.admin')

@section('title', 'Zeugen-Eingang')

@section('content')
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Zeugen-Eingang (ohne Meldung)</h1>
            <p class="mt-1 text-sm text-gray-600">
                Hier erstellen Sie allgemeine Upload-Links ohne feste News-Zuordnung. Eingänge können später einer Meldung zugewiesen werden.
            </p>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 border border-green-200">
                <p class="text-sm text-green-800">{{ session('status') }}</p>
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-md bg-red-50 p-4 border border-red-200">
                <p class="text-sm text-red-800">{{ session('error') }}</p>
            </div>
        @endif
        @if ($errors->any())
            <div class="rounded-md bg-red-50 p-4 border border-red-200" role="alert">
                <p class="text-sm font-medium text-red-900">Bitte Eingaben prüfen:</p>
                <ul class="mt-1 list-disc list-inside text-sm text-red-700 space-y-0.5">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('witness_plain_token'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 space-y-2">
                <p class="text-sm font-semibold text-emerald-900">Allgemeiner Link erzeugt - bitte jetzt kopieren (wird nicht erneut angezeigt)</p>
                <label class="block text-xs font-medium text-emerald-900">Vollständige URL</label>
                <input type="text" readonly
                    class="w-full text-sm font-mono rounded border border-emerald-300 bg-white px-2 py-2"
                    value="{{ \App\Support\WitnessPortal::uploadUrl(session('witness_plain_token')) }}"
                    onclick="this.select()">
            </div>
        @endif

        <div class="rounded-lg border border-gray-200 bg-white p-4 space-y-3">
            <h2 class="text-sm font-semibold text-gray-900">Allgemeinen Upload-Link erzeugen</h2>
            <form method="post" action="{{ route('admin.witness.global-links.store') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 sm:items-end">
                @csrf
                <div class="sm:col-span-2">
                    <label for="witness-label" class="block text-xs font-medium text-gray-500">Interne Bezeichnung (optional)</label>
                    <input type="text" name="label" id="witness-label" maxlength="191" placeholder="z. B. Sammellink Wochenende"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                </div>
                <div>
                    <label for="witness-expires" class="block text-xs font-medium text-gray-500">Gültigkeit</label>
                    <select name="expires_in_days" id="witness-expires" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        <option value="">unbegrenzt</option>
                        <option value="7">7 Tage</option>
                        <option value="14" selected>14 Tage</option>
                        <option value="30">30 Tage</option>
                        <option value="90">90 Tage</option>
                    </select>
                </div>
                <div>
                    <label for="witness-max" class="block text-xs font-medium text-gray-500">Max. Uploads</label>
                    <input type="number" name="max_uploads" id="witness-max" min="1" max="200" value="50"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                </div>
                <div class="sm:col-span-2 lg:col-span-4">
                    <button type="submit" class="inline-flex min-h-[2.75rem] items-center rounded-md bg-[#092E48] px-4 py-2 text-sm font-medium text-white hover:bg-[#0b3858]">
                        Link erzeugen
                    </button>
                </div>
            </form>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Allgemeine Links</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                    <tr>
                        <th class="px-2 py-2">Erstellt</th>
                        <th class="px-2 py-2">Label</th>
                        <th class="px-2 py-2">Uploads</th>
                        <th class="px-2 py-2">Status</th>
                        <th class="px-2 py-2 text-right">Aktion</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse ($globalLinks as $wl)
                        @php
                            $wlExpired = $wl->expires_at && $wl->expires_at->isPast();
                            $wlRevoked = $wl->revoked_at !== null;
                            $wlFull = ($wl->submissions_count ?? 0) >= max(1, (int) $wl->max_uploads);
                        @endphp
                        <tr>
                            <td class="px-2 py-2 whitespace-nowrap text-gray-700">{{ $wl->created_at?->format('d.m.Y H:i') }}</td>
                            <td class="px-2 py-2 text-gray-800">{{ $wl->label ?: '—' }}</td>
                            <td class="px-2 py-2">{{ $wl->submissions_count ?? 0 }} / {{ $wl->max_uploads }}</td>
                            <td class="px-2 py-2">
                                @if ($wlRevoked)
                                    <span class="text-red-700">zurückgezogen</span>
                                @elseif ($wlExpired)
                                    <span class="text-amber-800">abgelaufen</span>
                                @elseif ($wlFull)
                                    <span class="text-amber-800">Limit</span>
                                @else
                                    <span class="text-emerald-800">aktiv</span>
                                @endif
                            </td>
                            <td class="px-2 py-2 text-right">
                                @if (! $wlRevoked)
                                    <form method="POST" action="{{ route('admin.witness.global-links.destroy', $wl) }}" class="inline"
                                          onsubmit="return window.adminConfirmDelete(this)"
                                          data-delete-prompt="Upload-Link wirklich zurückziehen? Geben Sie zur Bestätigung „ja“ ein.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-700 hover:underline text-xs font-medium">Zurückziehen</button>
                                    </form>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-2 py-4 text-gray-500">Noch keine allgemeinen Links.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Unzugeordnete Einreichungen</h2>
            <div class="space-y-4">
                @forelse ($unassignedSubmissions as $sub)
                    <div class="rounded-lg border border-gray-200 p-4 space-y-3">
                        <div class="text-sm text-gray-700">
                            <p class="text-xs text-gray-500">
                                {{ $sub->created_at?->format('d.m.Y H:i') }} · <span class="font-mono">#{{ $sub->id }}</span> · Status: <strong>{{ $sub->status }}</strong>
                            </p>
                            <p class="font-medium text-gray-900">{{ $sub->submitter_name }} · {{ $sub->submitter_email }}</p>
                            <p class="text-xs text-gray-600">Datei: {{ $sub->original_filename }}</p>
                            @if ($sub->link?->label)
                                <p class="text-xs text-gray-600">Link: {{ $sub->link->label }}</p>
                            @endif
                        </div>

                        <div class="flex flex-wrap gap-3 text-sm">
                            @if ($sub->stored_path)
                                <a href="{{ route('admin.witness.submissions.preview', $sub) }}" class="text-[#092E48] hover:underline" target="_blank" rel="noopener">Vorschau</a>
                                <a href="{{ route('admin.witness.submissions.download', $sub) }}" class="text-[#092E48] hover:underline">Download</a>
                            @endif
                        </div>

                        @if ($sub->status === \App\Models\NewsItemWitnessSubmission::STATUS_PENDING && $sub->stored_path)
                            <form method="post" action="{{ route('admin.witness.submissions.assign', $sub) }}" class="grid gap-2 sm:grid-cols-4 sm:items-end">
                                @csrf
                                <div class="sm:col-span-3">
                                    <label for="assign-news-{{ $sub->id }}" class="block text-xs font-medium text-gray-500">Meldung zuordnen</label>
                                    <select name="news_item_id" id="assign-news-{{ $sub->id }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                                        <option value="">Bitte Meldung wählen…</option>
                                        @foreach ($newsChoices as $choice)
                                            <option value="{{ $choice->id }}">#{{ $choice->id }} · {{ \Illuminate\Support\Str::limit((string) $choice->title, 120) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <button type="submit" class="inline-flex min-h-[2.75rem] items-center rounded-md bg-[#092E48] px-4 py-2 text-sm font-medium text-white hover:bg-[#0b3858]">
                                        Zuordnen
                                    </button>
                                </div>
                            </form>

                            <form method="post" action="{{ route('admin.witness.submissions.reject', $sub) }}" class="inline" onsubmit="return confirm('Einreichung wirklich ablehnen? Die Datei wird gelöscht.');">
                                @csrf
                                <button type="submit" class="text-sm text-red-700 hover:underline font-medium">Ablehnen & Datei löschen</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Aktuell keine unzugeordneten Einreichungen.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
