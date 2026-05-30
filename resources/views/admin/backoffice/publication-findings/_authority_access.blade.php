@php
    $hasZip = (string) ($finding->evidence_dossier_path ?? '') !== '';
@endphp

<div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6 space-y-4">
    <div>
        <h2 class="text-lg font-semibold text-gray-900">Behördenzugang (Polizei / STA)</h2>
        <p class="mt-1 text-sm text-gray-600">
            Link kann sofort erstellt werden – <strong>Aktenzeichen ist optional</strong> und kann später ergänzt werden, ohne neuen Link.
        </p>
    </div>

    @if(session('authority_access_plain_password'))
        <div class="rounded-md bg-amber-50 border border-amber-200 p-4 text-sm text-amber-900 space-y-1">
            <p class="font-medium">Zugangscode (nur jetzt sichtbar – bitte mitteilen und nicht erneut abrufbar):</p>
            <p class="font-mono text-lg">{{ session('authority_access_plain_password') }}</p>
        </div>
    @endif

    @if($finding->hasActiveAuthorityAccess())
        <div class="rounded-md bg-emerald-50 border border-emerald-200 p-4 text-sm space-y-3">
            <p class="font-medium text-emerald-900">Aktiver Behördenlink</p>
            <p class="text-emerald-800 break-all font-mono text-xs">{{ $finding->authorityPortalUrl() }}</p>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-emerald-900">
                <div class="sm:col-span-2">
                    <dt class="text-emerald-700 text-xs">Anzeige für Behörde</dt>
                    <dd>{{ $finding->authorityRecipientDisplay() }}</dd>
                </div>
                <div>
                    <dt class="text-emerald-700 text-xs">Gültig bis</dt>
                    <dd>{{ $finding->authority_access_expires_at?->format('d.m.Y H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-emerald-700 text-xs">Downloads protokolliert</dt>
                    <dd>{{ $finding->authorityDownloads->count() }}</dd>
                </div>
            </dl>
            <form method="post" action="{{ route('admin.backoffice.publication-findings.authority-access.revoke', $finding) }}"
                  onsubmit="return confirm('Behördenzugang wirklich widerrufen?');">
                @csrf
                <button type="submit" class="text-sm text-red-700 hover:underline">Zugang widerrufen</button>
            </form>
        </div>

        <form method="post" action="{{ route('admin.backoffice.publication-findings.authority-access.update', $finding) }}"
              class="space-y-3 rounded-md border border-gray-200 bg-gray-50 p-4">
            @csrf
            @method('PUT')
            <p class="text-sm font-medium text-gray-900">Behörde / Aktenzeichen nachtragen</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label for="authority_access_recipient_edit" class="block text-xs text-gray-600">Behörde (optional)</label>
                    <input type="text" name="authority_access_recipient" id="authority_access_recipient_edit"
                           placeholder="z. B. Polizei Köln, STA Köln"
                           value="{{ old('authority_access_recipient', $finding->authority_access_recipient) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                </div>
                <div>
                    <label for="authority_access_file_reference_edit" class="block text-xs text-gray-600">Aktenzeichen (optional)</label>
                    <input type="text" name="authority_access_file_reference" id="authority_access_file_reference_edit"
                           placeholder="z. B. 123 Js 456/26"
                           value="{{ old('authority_access_file_reference', $finding->authority_access_file_reference) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                </div>
            </div>
            <button type="submit" class="text-sm text-[#092E48] hover:underline">Angaben speichern (Link bleibt gleich)</button>
        </form>

        @if($finding->authorityDownloads->isNotEmpty())
            <div>
                <p class="text-sm font-medium text-gray-900 mb-2">Download-Protokoll</p>
                <ul class="text-xs text-gray-600 space-y-1 max-h-40 overflow-y-auto">
                    @foreach($finding->authorityDownloads->sortByDesc('created_at') as $log)
                        <li>{{ $log->created_at?->format('d.m.Y H:i:s') }} – {{ $log->recipient_label ?? 'Behörde' }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif

    @if(! $hasZip)
        <p class="text-sm text-amber-800">Zuerst eine Beweismittelmappe (ZIP) erstellen, dann Behördenzugang freigeben.</p>
    @else
        <form method="post" action="{{ route('admin.backoffice.publication-findings.authority-access.create', $finding) }}" class="space-y-4 border-t border-gray-100 pt-4">
            @csrf
            <p class="text-sm font-medium text-gray-900">{{ $finding->hasActiveAuthorityAccess() ? 'Neuen Link erzeugen' : 'Link erstellen' }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="authority_access_recipient" class="block text-sm font-medium text-gray-700">Behörde (optional)</label>
                    <input type="text" name="authority_access_recipient" id="authority_access_recipient"
                           placeholder="z. B. Polizei Köln, STA Köln"
                           value="{{ old('authority_access_recipient', $finding->authority_access_recipient) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                </div>
                <div>
                    <label for="authority_access_file_reference" class="block text-sm font-medium text-gray-700">Aktenzeichen (optional)</label>
                    <input type="text" name="authority_access_file_reference" id="authority_access_file_reference"
                           placeholder="kann später ergänzt werden"
                           value="{{ old('authority_access_file_reference', $finding->authority_access_file_reference) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="authority_link_days" class="block text-sm font-medium text-gray-700">Link gültig (Tage)</label>
                    <input type="number" name="authority_link_days" id="authority_link_days" min="1" max="365"
                           value="{{ old('authority_link_days', config('publication_evidence.authority_link_days', 30)) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                </div>
                <div>
                    <label for="authority_access_password" class="block text-sm font-medium text-gray-700">Zugangscode (optional)</label>
                    <input type="text" name="authority_access_password" id="authority_access_password" minlength="8" maxlength="64"
                           autocomplete="new-password"
                           placeholder="mind. 8 Zeichen"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    <p class="mt-1 text-xs text-gray-500">Getrennt vom Link übermitteln (Telefon/Aktenvermerk).</p>
                </div>
            </div>
            <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">
                {{ $finding->hasActiveAuthorityAccess() ? 'Behördenlink neu erzeugen' : 'Behördenlink erstellen' }}
            </button>
        </form>
    @endif
</div>
