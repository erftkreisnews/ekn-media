@extends('layouts.admin')

@section('content')
    <div class="space-y-6 max-w-7xl mx-auto">
        <div>
            <a href="{{ route('admin.backoffice.index') }}" class="text-sm text-gray-600 hover:text-[#092E48] mb-2 inline-block">← Backoffice</a>
            <h1 class="text-2xl font-semibold text-gray-900">Nutzung &amp; Auswertungen</h1>
            <p class="mt-1 text-sm text-gray-600">
                Übersicht erfasster Nutzungs-Einträge für das gewählte Zeitfenster.
                @if(!($isAdmin ?? false))
                    Es werden nur Ihre eigenen Einträge und Summen angezeigt.
                @else
                    Die Summen helfen bei Monats-, Quartals- und Jahresauswertungen.
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
        @if(!empty($focusRecord))
            <div class="rounded-md bg-sky-50 p-4 border border-sky-200">
                <p class="text-sm font-medium text-sky-900">
                    Nachverfolgung #{{ $focusRecord->id }} wurde erfasst.
                </p>
                <p class="mt-1 text-sm text-sky-800">
                    Status:
                    @if($focusRecord->invoice_id)
                        im Rechnungsentwurf/Rechnung gebunden
                    @else
                        offen (kann bearbeitet oder gelöscht werden)
                    @endif
                    · Betrag: {{ number_format((float) $focusRecord->total_amount, 2, ',', '.') }} €
                </p>
            </div>
        @endif

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <p class="text-sm text-gray-700">
                    Zeitraum: <span class="font-medium">{{ $periodLabel }}</span>
                    <span class="text-gray-500">({{ $start->format('d.m.Y') }}–{{ $end->format('d.m.Y') }})</span>
                </p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <a
                        href="{{ route('admin.backoffice.usage.index', ['period' => $period, 'month' => $prevMonth]) }}"
                        class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                        title="Vormonat"
                    >← Monat</a>
                    <form method="GET" action="{{ route('admin.backoffice.usage.index') }}" class="inline-flex items-center gap-2">
                        <input type="hidden" name="period" value="{{ $period }}">
                        <input
                            type="month"
                            name="month"
                            value="{{ $month }}"
                            class="rounded-md border-gray-300 text-sm focus:border-[#092E48] focus:ring-[#092E48]"
                        >
                        <button type="submit" class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-md border border-[#092E48] text-[#092E48] bg-white hover:bg-[#092E48]/5">
                            Springen
                        </button>
                    </form>
                    <a
                        href="{{ route('admin.backoffice.usage.index', ['period' => $period, 'month' => $nextMonth]) }}"
                        class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                        title="Nächster Monat"
                    >Monat →</a>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                @php
                    $periods = [
                        'month' => 'Monat',
                        'quarter' => 'Quartal',
                        'halfyear' => 'Halbjahr',
                        'year' => 'Jahr',
                    ];
                @endphp
                @foreach($periods as $key => $label)
                    <a
                        href="{{ route('admin.backoffice.usage.index', ['period' => $key, 'month' => $month]) }}"
                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full border {{ $period === $key ? 'border-[#092E48] bg-[#092E48] text-white' : 'border-gray-300 text-gray-700 bg-white hover:bg-gray-50' }}"
                    >
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Bilder</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ (int) ($totals['images'] ?? 0) }}</p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Videominuten</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">
                    {{ number_format((float) ($totals['video_minutes'] ?? 0), 1, ',', '.') }}
                </p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Radio-Minuten</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">
                    {{ number_format((float) ($totals['radio_minutes'] ?? 0), 1, ',', '.') }}
                </p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Netto-Betrag</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">
                    {{ number_format((float) ($totals['amount'] ?? 0), 2, ',', '.') }} €
                </p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Brutto (inkl. 7% USt)</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">
                    {{ number_format((float) ($totals['gross'] ?? 0), 2, ',', '.') }} €
                </p>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                <h2 class="text-base font-medium text-gray-900">Nutzungs-Einträge (max. 100 im Zeitraum)</h2>
                <a href="{{ route('admin.backoffice.usage.create') }}" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">
                    Nachverfolgung erfassen
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Datum</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">News</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Medienhaus / Redaktion</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Mengen</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Netto (€)</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Rechnung</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($records as $record)
                            <tr id="usage-record-{{ $record->id }}" class="{{ !empty($focusRecord) && (int) $focusRecord->id === (int) $record->id ? 'bg-sky-50/60' : '' }}">
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ optional($record->used_at)->format('d.m.Y') ?? '–' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    @if($record->newsItem)
                                        <span class="font-medium">#{{ $record->newsItem->id }}</span>
                                        <span class="text-gray-700">
                                            – {{ \Illuminate\Support\Str::limit($record->newsItem->title, 60) }}
                                        </span>
                                    @else
                                        <span class="text-gray-500 italic">Freies Angebot</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ $record->organization?->name ?? '–' }}
                                    @if($record->product)
                                        <span class="text-gray-500"> · {{ $record->product->name }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-right text-gray-700">
                                    @if((int) $record->images_count > 0)
                                        {{ (int) $record->images_count }} Bilder
                                    @endif
                                    @if((int) $record->images_count > 0 && ((float) $record->video_minutes > 0 || (float) ($record->radio_minutes ?? 0) > 0))
                                        <br>
                                    @endif
                                    @if((float) $record->video_minutes > 0)
                                        {{ number_format((float) $record->video_minutes, 1, ',', '.') }} Min. Video
                                    @endif
                                    @if((float) $record->video_minutes > 0 && (float) ($record->radio_minutes ?? 0) > 0)
                                        <br>
                                    @endif
                                    @if((float) ($record->radio_minutes ?? 0) > 0)
                                        {{ number_format((float) $record->radio_minutes, 1, ',', '.') }} Min. Radio
                                    @endif
                                    @if((int) $record->images_count === 0 && (float) $record->video_minutes === 0.0 && (float) ($record->radio_minutes ?? 0) === 0.0)
                                        <span class="text-gray-400">–</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-right text-gray-700">
                                    {{ number_format((float) $record->total_amount, 2, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    @if($record->invoice_id)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                            In Rechnung
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                            Offen
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    @if($record->invoice)
                                        <span class="font-medium">Rechnung #{{ $record->invoice->id }}</span>
                                        @if($record->invoice->status)
                                            <span class="text-gray-500"> · {{ $record->invoice->status }}</span>
                                        @endif
                                    @else
                                        <span class="text-gray-400">–</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-right">
                                    <a href="{{ route('admin.backoffice.usage.edit', $record) }}" class="text-[#092E48] hover:underline">
                                        Bearbeiten
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-6 text-center text-sm text-gray-500">
                                    Für den gewählten Zeitraum sind noch keine Nutzungs-Einträge erfasst.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
