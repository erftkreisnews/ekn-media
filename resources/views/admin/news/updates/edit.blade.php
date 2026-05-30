@extends('layouts.admin')

@section('content')
    <x-admin.page
        title="Update bearbeiten"
        subtitle="Nachträgliche Informationen bearbeiten und Anzeige steuern."
    >
        <div class="space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs text-gray-500">News #{{ $newsItem->id }}</p>
                <h1 class="mt-1 text-lg font-semibold text-gray-900">{{ $newsItem->title }}</h1>
                <p class="mt-2 text-sm">
                    <a href="{{ route('admin.news.edit', ['newsItem' => $newsItem, 'tab' => 'ot']) }}" class="text-[#092E48] hover:underline">← zurück zum O-Töne &amp; Updates‑Reiter</a>
                </p>
            </div>

            <div class="rounded-xl border border-indigo-200 bg-white p-5 shadow-sm space-y-4">
            <form id="updateEditForm" method="POST" action="{{ route('admin.news.updates.update', [$newsItem, $update]) }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-gray-700">Typ</label>
                        <select name="type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                            @foreach(($updateTypeOptions ?? []) as $key => $label)
                                <option value="{{ $key }}" @selected(old('type', $update->type) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-gray-700">Titel</label>
                        <input type="text" name="title" value="{{ old('title', $update->title) }}" maxlength="255" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-gray-700">Quelle (optional)</label>
                        <select name="source_type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                            <option value="">—</option>
                            @foreach(($updateSourceOptions ?? []) as $key => $label)
                                <option value="{{ $key }}" @selected(old('source_type', $update->source_type) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-gray-700">Zeitpunkt</label>
                        <input type="datetime-local" name="happened_at"
                               value="{{ old('happened_at', $update->happened_at?->format('Y-m-d\\TH:i')) }}"
                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-gray-700">Genaue Quellenbezeichnung</label>
                    <input type="text" name="source_label" value="{{ old('source_label', $update->source_label) }}" maxlength="255" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-gray-700" for="update_presseportal_url">Presseportal-URL (optional)</label>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <input id="update_presseportal_url" type="url" name="presseportal_url" value="{{ old('presseportal_url', $update->presseportal_url) }}" maxlength="2000"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm"
                            placeholder="https://www.presseportal.de/…/pm/…/…">
                        <button type="button" id="btnPresseportalLoadUpdateEdit"
                            class="inline-flex justify-center items-center shrink-0 px-3 py-2 text-sm font-medium rounded-md border border-indigo-300 text-indigo-900 bg-white hover:bg-indigo-50 disabled:opacity-50"
                            @if(empty($presseportalKeySet)) disabled title="PRESSEPORTAL_API_KEY fehlt" @endif>Text laden</button>
                    </div>
                    <input type="hidden" name="presseportal_story_id" id="edit_presseportal_story_id" value="{{ old('presseportal_story_id', $update->presseportal_story_id) }}">
                    <input type="hidden" name="presseportal_office_id" id="edit_presseportal_office_id" value="{{ old('presseportal_office_id', $update->presseportal_office_id) }}">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-gray-700">Text</label>
                    <textarea name="body" rows="10" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">{{ old('body', $update->body) }}</textarea>
                </div>

                <div class="flex flex-wrap gap-4 text-sm">
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="show_in_mail" value="1" class="rounded border-gray-300 text-[#092E48]" @checked(old('show_in_mail', $update->show_in_mail))> In Mail anzeigen</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="show_in_article" value="1" class="rounded border-gray-300 text-[#092E48]" @checked(old('show_in_article', $update->show_in_article))> Im Artikel anzeigen</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-[#092E48]" @checked(old('is_active', $update->is_active))> Aktiv</label>
                </div>

            </form>
            <div class="flex flex-wrap gap-3 pt-2 border-t border-indigo-100">
                <button type="submit" form="updateEditForm" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg bg-[#092E48] text-white hover:bg-[#0b3858]">Speichern</button>
                <form method="POST" action="{{ route('admin.news.updates.destroy', [$newsItem, $update]) }}" class="inline-flex items-center" onsubmit="return confirm('Dieses Update wirklich löschen?');">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="confirmation" value="ja">
                    <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg border border-red-300 text-red-700 bg-white hover:bg-red-50">Löschen</button>
                </form>
            </div>
            </div>
        </div>
    </x-admin.page>

    <script>
    (function () {
        var btn = document.getElementById('btnPresseportalLoadUpdateEdit');
        if (!btn || btn.disabled) return;
        var csrf = document.querySelector('meta[name="csrf-token"]');
        if (!csrf) return;
        btn.addEventListener('click', function () {
            var urlInput = document.getElementById('update_presseportal_url');
            var url = urlInput && urlInput.value ? String(urlInput.value).trim() : '';
            if (!url) {
                window.alert('Bitte eine Presseportal-URL einfügen.');
                return;
            }
            btn.disabled = true;
            fetch('{{ route('admin.news.presseportal.fetch-story', $newsItem) }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf.content,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ url: url }),
            })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (!res.ok || !res.j.success) {
                        window.alert((res.j && res.j.message) ? res.j.message : 'Fehler beim Laden.');
                        return;
                    }
                    var d = res.j.data;
                    var bodyEl = document.querySelector('textarea[name="body"]');
                    var titleEl = document.querySelector('input[name="title"]');
                    var typeEl = document.querySelector('select[name="type"]');
                    var srcType = document.querySelector('select[name="source_type"]');
                    var srcLabel = document.querySelector('input[name="source_label"]');
                    var ha = document.querySelector('input[name="happened_at"]');
                    var sid = document.getElementById('edit_presseportal_story_id');
                    var oid = document.getElementById('edit_presseportal_office_id');
                    if (bodyEl) bodyEl.value = d.body || '';
                    if (titleEl) titleEl.value = d.title || '';
                    if (typeEl) typeEl.value = 'press_release';
                    if (srcType && d.source_type) srcType.value = d.source_type;
                    if (srcLabel) srcLabel.value = d.source_label || '';
                    if (ha && d.happened_at) ha.value = d.happened_at;
                    if (sid) sid.value = d.presseportal_story_id != null ? String(d.presseportal_story_id) : '';
                    if (oid) oid.value = d.presseportal_office_id != null ? String(d.presseportal_office_id) : '';
                })
                .catch(function (e) {
                    window.alert(e.message || 'Netzwerkfehler');
                })
                .then(function () {
                    btn.disabled = false;
                });
        });
    })();
    </script>
@endsection

