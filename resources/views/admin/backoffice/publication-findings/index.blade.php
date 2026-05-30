@extends('layouts.admin')

@section('content')
    <div class="space-y-6 max-w-7xl mx-auto">
        <div>
            <a href="{{ route('admin.backoffice.index') }}" class="text-sm text-gray-600 hover:text-[#092E48] mb-2 inline-block">← Backoffice</a>
            <h1 class="text-2xl font-semibold text-gray-900">Fundstellen (VÖ &amp; Recht)</h1>
            <p class="mt-1 text-sm text-gray-600">
                Manuelle Dokumentation von Bild-Fundstellen im Web (nach Google Lens / News).
                @if(!($monitorEnabled ?? false))
                    Automatischer TinEye-Scan ist <strong>deaktiviert</strong>.
                @endif
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

        <div class="flex flex-wrap gap-2 items-center justify-between">
            <form method="get" class="flex flex-wrap gap-2 items-end">
                <div>
                    <label for="pf-kind" class="block text-xs text-gray-500">Art</label>
                    <select name="kind" id="pf-kind" class="mt-1 rounded-md border-gray-300 text-sm">
                        <option value="">Alle</option>
                        @foreach($kindLabels as $value => $label)
                            <option value="{{ $value }}" @selected(request('kind') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="pf-media" class="block text-xs text-gray-500">Media-ID</label>
                    <input type="number" name="media_id" id="pf-media" value="{{ request('media_id') }}" min="1" class="mt-1 rounded-md border-gray-300 text-sm w-28">
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 pb-1">
                    <input type="checkbox" name="unconfirmed" value="1" @checked(request()->boolean('unconfirmed')) class="rounded border-gray-300">
                    Nur unbestätigt
                </label>
                <button type="submit" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">Filtern</button>
            </form>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.backoffice.publication-findings.create') }}"
                   class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">
                    Fundstelle erfassen
                </a>
                <form method="post" action="{{ route('admin.backoffice.publication-findings.scan') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                        Auto-Scan (optional)
                    </button>
                </form>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Datum</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Art</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">URL</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bild / Meldung</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kunde</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Beweise</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aktionen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($findings as $f)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $f->found_at?->format('d.m.Y') ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="font-medium">{{ $f->kindLabel() }}</span>
                                @if($f->confirmed)
                                    <span class="ml-1 text-xs text-emerald-700">✓</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 max-w-xs truncate">
                                <a href="{{ $f->url }}" target="_blank" rel="noopener" class="text-[#092E48] hover:underline">{{ \Illuminate\Support\Str::limit($f->url, 60) }}</a>
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $linkedMedia = $f->mediaItems->isNotEmpty()
                                        ? $f->mediaItems
                                        : ($f->news_item_media_id ? collect([$f->media]) : collect());
                                @endphp
                                @foreach($linkedMedia as $linked)
                                    @if($linked)
                                        <a href="{{ route('admin.images.index', ['q' => $linked->id]) }}" class="text-[#092E48] hover:underline">#{{ $linked->id }}</a>@if(! $loop->last)<span class="text-gray-400">, </span>@endif
                                    @endif
                                @endforeach
                                @if($f->news_item_id)
                                    <span class="text-gray-500 {{ $linkedMedia->isNotEmpty() ? 'block' : '' }}">News {{ $f->news_item_id }}</span>
                                @endif
                                @if($linkedMedia->isEmpty() && ! $f->news_item_id)
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $f->organization?->name ?? '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-xs">
                                @if($f->evidence_dossier_status === 'ready')
                                    <span class="text-emerald-700">ZIP bereit</span>
                                @elseif($f->evidence_dossier_status === 'partial')
                                    <span class="text-amber-700">teilweise</span>
                                @elseif($f->evidence_dossier_status === 'building')
                                    <span class="text-gray-500">wird erstellt…</span>
                                @elseif($f->evidence_dossier_status === 'failed')
                                    <span class="text-red-600">Fehler</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                                @if($f->evidence_dossier_path)
                                    <a href="{{ route('admin.backoffice.publication-findings.evidence-dossier.download', $f) }}" class="block text-[#092E48] hover:underline">Download</a>
                                @endif
                                <form method="post" action="{{ route('admin.backoffice.publication-findings.evidence-dossier.build', $f) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-[#092E48] hover:underline">Mappe erstellen</button>
                                </form>
                            </td>
                            <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                                @if(! $f->confirmed)
                                    <form method="post" action="{{ route('admin.backoffice.publication-findings.confirm', $f) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-emerald-700 hover:underline text-xs">Bestätigen</button>
                                    </form>
                                @endif
                                <a href="{{ route('admin.backoffice.publication-findings.edit', $f) }}" class="text-[#092E48] hover:underline">Bearbeiten</a>
                                <form method="post" action="{{ route('admin.backoffice.publication-findings.destroy', $f) }}" class="inline"
                                      onsubmit="return confirm('Fundstelle löschen?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">Löschen</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-500">Noch keine Fundstellen erfasst.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $findings->links() }}</div>
    </div>
@endsection
