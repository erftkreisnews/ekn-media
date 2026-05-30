@extends('layouts.admin')

@section('content')
    <div class="space-y-6 min-w-0 max-w-2xl">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Neue Foto-Galerie</h1>
            <p class="mt-1 text-sm text-gray-600">
                Galerie anlegen — danach Fotos einzeln und sofort hochladen.
            </p>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 border border-green-200">
                <p class="text-sm text-green-800">{{ session('status') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-md bg-red-50 p-4 border border-red-200" role="alert">
                <ul class="list-disc list-inside text-sm text-red-700 space-y-0.5">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('admin.koelnimage.foto.store') }}"
            class="bg-white shadow-sm rounded-xl border border-gray-200 overflow-hidden"
            x-data="{
                events: @js($plannedEvents->map(fn ($ev) => ['id' => (string) $ev->id, 'label' => trim($ev->name . (filled($ev->date_label) ? ' (' . $ev->date_label . ')' : ''))])->values()),
                selectedEventId: @js((string) old('planned_event_id', '')),
                title: @js(old('title', '')),
                applyTitleFromEvent() {
                    if (this.title.trim() !== '') return;
                    const ev = this.events.find(e => e.id === this.selectedEventId);
                    if (ev) this.title = ev.label;
                }
            }"
            @submit="applyTitleFromEvent()"
        >
            @csrf
            <input type="hidden" name="author_credit_user_id" value="{{ old('author_credit_user_id', $currentUserId) }}">

            <div class="px-6 py-5 space-y-5">
                <div id="section-planned-event" class="rounded-xl border border-[#092E48]/20 bg-[#092E48]/[0.04] p-4 space-y-3">
                    <h3 class="text-sm font-semibold text-[#092E48]">Geplante Veranstaltung</h3>
                    @if ($plannedEvents->isEmpty())
                        <p class="text-sm text-gray-600">
                            <a href="{{ route('admin.settings.planned-events.create') }}" class="text-[#092E48] font-medium hover:underline">Veranstaltung anlegen</a>
                        </p>
                    @else
                        <select
                            name="planned_event_id"
                            id="planned_event_id"
                            x-model="selectedEventId"
                            @change="applyTitleFromEvent()"
                            class="block w-full max-w-2xl rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm"
                        >
                            <option value="">— Keine Zuordnung —</option>
                            @foreach ($plannedEvents as $ev)
                                <option value="{{ $ev->id }}" @selected((string) old('planned_event_id') === (string) $ev->id)>
                                    {{ $ev->name }}@if(filled($ev->date_label)) ({{ $ev->date_label }})@endif
                                </option>
                            @endforeach
                        </select>
                    @endif
                </div>

                <div class="space-y-2">
                    <label for="title" class="block text-sm font-medium text-gray-700">
                        Galerie-Titel *
                    </label>
                    <input
                        type="text"
                        name="title"
                        id="title"
                        x-model="title"
                        value="{{ old('title') }}"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                        maxlength="265"
                        required
                        placeholder="Wird aus der Veranstaltung übernommen, wenn leer"
                    />
                    @error('title')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                @if($koelnimageBrand)
                    <p class="text-sm text-blue-800 bg-blue-50 border border-blue-200 rounded-lg px-3 py-2">
                        Marke: <strong>{{ $koelnimageBrand->name }}</strong> (fest)
                    </p>
                @endif
            </div>

            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex flex-wrap gap-2">
                <button
                    type="submit"
                    class="inline-flex items-center px-4 py-2.5 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]"
                >
                    Galerie anlegen &amp; Fotos hochladen
                </button>
                <a
                    href="{{ route('admin.news.index') }}"
                    class="inline-flex items-center px-4 py-2.5 text-sm font-medium rounded-md border border-gray-300 text-gray-700 hover:bg-white"
                >
                    Abbrechen
                </a>
            </div>
        </form>
    </div>
@endsection
