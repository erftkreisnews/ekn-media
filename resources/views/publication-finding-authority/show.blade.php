@extends('layouts.frontend')

@section('title', 'Beweismittelübergabe | '.config('app.name'))

@section('content')
    <div class="max-w-3xl mx-auto px-4 py-10">
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6 sm:p-8 space-y-6">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Beweissicherung – Behördenzugang</p>
                <h1 class="mt-2 text-2xl font-semibold text-gray-900">Beweismittelmappe</h1>
                <p class="mt-2 text-sm text-gray-600">
                    Bereitgestellt durch {{ config('app.name', 'EKN Media') }} zur Beweissicherung im Rahmen strafrechtlicher Ermittlungen.
                </p>
            </div>

            @if (session('error'))
                <div class="rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-800">{{ session('error') }}</div>
            @endif

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-gray-500">Fundstellen-ID</dt>
                    <dd class="font-mono text-gray-900">#{{ $finding->id }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-gray-500">Behörde / Akte</dt>
                    <dd class="text-gray-900">{{ $finding->authorityRecipientDisplay() }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Feststellung</dt>
                    <dd class="text-gray-900">{{ $finding->found_at?->format('d.m.Y H:i') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Gültig bis</dt>
                    <dd class="text-gray-900">{{ $finding->authority_access_expires_at?->format('d.m.Y H:i') ?? '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-gray-500">Fund-URL</dt>
                    <dd class="text-gray-900 break-all">{{ $finding->url }}</dd>
                </div>
                @if($finding->page_title)
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500">Bezeichnung</dt>
                        <dd class="text-gray-900">{{ $finding->page_title }}</dd>
                    </div>
                @endif
            </dl>

            @if(is_array($finding->evidence_dossier_manifest['checklist'] ?? null))
                <div class="rounded-md bg-gray-50 border border-gray-200 p-4">
                    <p class="text-sm font-medium text-gray-900 mb-2">Inhalt der ZIP-Mappe (Checkliste)</p>
                    <ul class="text-sm text-gray-700 space-y-1">
                        @foreach($finding->evidenceChecklistForDisplay() as $key => $ok)
                            <li>{{ $ok ? '✅' : '❌' }} {{ str_replace('_', ' ', $key) }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($passwordRequired && ! $passwordOk)
                <form method="post" action="{{ route('publication-finding.authority.password', $finding->authority_access_token) }}" class="space-y-4 border-t border-gray-100 pt-6">
                    @csrf
                    <p class="text-sm text-gray-700">Bitte den von der Medienanstalt übermittelten <strong>Zugangscode</strong> eingeben.</p>
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Zugangscode</label>
                        <input type="password" name="password" id="password" required autocomplete="off"
                               class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">
                        Zugang prüfen
                    </button>
                </form>
            @else
                <div class="border-t border-gray-100 pt-6 space-y-4">
                    <p class="text-sm text-gray-600">
                        Der Download-Link ist <strong>{{ (int) config('publication_evidence.authority_download_url_minutes', 60) }} Minuten</strong> gültig.
                        Jeder Abruf wird protokolliert (Zeitpunkt, anonymisierte technische Kennung).
                    </p>
                    <a href="{{ $downloadUrl }}"
                       class="inline-flex items-center px-5 py-3 text-sm font-semibold rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">
                        Beweismittelmappe (ZIP) herunterladen
                    </a>
                    <p class="text-xs text-gray-500">
                        Nur für Polizei, Staatsanwaltschaft und andere berechtigte Stellen. Weitergabe des Links unterbinden.
                        Die ZIP enthält ein README mit Dateiliste und Hinweisen zur Beweisführung.
                    </p>
                </div>
            @endif
        </div>
    </div>
@endsection
