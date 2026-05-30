@extends('layouts.admin')

@section('content')
    <x-admin.page
        title="Statement bearbeiten"
        subtitle="O-Ton / Wortlaut bearbeiten und Anzeige steuern."
    >
        <div class="space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs text-gray-500">News #{{ $newsItem->id }}</p>
                <h1 class="mt-1 text-lg font-semibold text-gray-900">{{ $newsItem->title }}</h1>
                <p class="mt-2 text-sm">
                    <a href="{{ route('admin.news.edit', ['newsItem' => $newsItem, 'tab' => 'ot']) }}" class="text-[#092E48] hover:underline">← zurück zum O-Töne &amp; Updates‑Reiter</a>
                </p>
            </div>

            <div class="rounded-xl border border-sky-200 bg-white p-5 shadow-sm space-y-4">
            <form id="statementEditForm" method="POST" action="{{ route('admin.news.statements.update', [$newsItem, $statement]) }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-gray-700">Quelle</label>
                        <select name="source_type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                            @foreach(($statementSourceOptions ?? []) as $key => $label)
                                <option value="{{ $key }}" @selected(old('source_type', $statement->source_type) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-gray-700">Genaue Bezeichnung</label>
                        <input type="text" name="source_label" value="{{ old('source_label', $statement->source_label) }}" maxlength="255" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-gray-700">Art</label>
                        <select name="statement_type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                            @foreach(($statementTypeOptions ?? []) as $key => $label)
                                <option value="{{ $key }}" @selected(old('statement_type', $statement->statement_type) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-gray-700">Eingegangen am</label>
                        <input type="datetime-local" name="received_at"
                               value="{{ old('received_at', $statement->received_at?->format('Y-m-d\\TH:i')) }}"
                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-gray-700">Kurz-Zusammenfassung</label>
                    <textarea name="summary" rows="2" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">{{ old('summary', $statement->summary) }}</textarea>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-gray-700">Transkript / Wortlaut</label>
                    <textarea name="transcript" rows="10" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">{{ old('transcript', $statement->transcript) }}</textarea>
                </div>

                <div class="flex flex-wrap gap-4 text-sm">
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="show_in_mail" value="1" class="rounded border-gray-300 text-[#092E48]" @checked(old('show_in_mail', $statement->show_in_mail))> In Angebotsmail anzeigen</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_publishable" value="1" class="rounded border-gray-300 text-[#092E48]" @checked(old('is_publishable', $statement->is_publishable))> Für Veröffentlichung freigegeben</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-[#092E48]" @checked(old('is_active', $statement->is_active))> Aktiv</label>
                </div>

            </form>
            <div class="flex flex-wrap gap-3 pt-2 border-t border-sky-100">
                <button type="submit" form="statementEditForm" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg bg-[#092E48] text-white hover:bg-[#0b3858]">Speichern</button>
                <form method="POST" action="{{ route('admin.news.statements.destroy', [$newsItem, $statement]) }}" class="inline-flex items-center" onsubmit="return confirm('Dieses Statement wirklich löschen?');">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="confirmation" value="ja">
                    <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg border border-red-300 text-red-700 bg-white hover:bg-red-50">Löschen</button>
                </form>
            </div>
            </div>
        </div>
    </x-admin.page>
@endsection

