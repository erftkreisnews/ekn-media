{{-- Tab „Verwendungen“ – alle Verwendungen zur News-ID (UsageRecord) --}}
<div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden min-w-0 max-w-full">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <h2 class="text-base font-semibold text-gray-900">Verwendungen zur Meldung @if(isset($newsItem) && $newsItem)#{{ $newsItem->id }}@endif</h2>
    </div>
    <div class="px-4 sm:px-6 py-4 sm:py-6">
        @if(isset($newsItem) && $newsItem)
            @if(($newsItem->usageRecords ?? collect())->isEmpty())
                <p class="text-sm text-gray-500">Bisher keine Verwendungen zu dieser Meldung erfasst.</p>
                <p class="mt-2 text-xs text-gray-500">
                    <a href="{{ route('admin.backoffice.usage.index') }}" class="text-[#092E48] hover:underline">Nutzung &amp; Auswertungen</a> im Backoffice öffnen, um Verwendungen zu erfassen.
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Datum</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Organisation / Produkt</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Format / Nutzungsrecht</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Bilder</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Video‑Min.</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Radio‑Min.</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Betrag (netto)</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Aktion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @foreach($newsItem->usageRecords ?? [] as $record)
                                <tr>
                                    <td class="px-4 py-2 text-gray-900 whitespace-nowrap">
                                        {{ \Carbon\Carbon::parse($record->used_at)->format('d.m.Y') }}
                                    </td>
                                    <td class="px-4 py-2 text-gray-900">
                                        <div class="flex flex-col">
                                            <span>{{ $record->organization?->name ?? '–' }}</span>
                                            <span class="text-xs text-gray-500">{{ $record->product?->name ?? '–' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 text-gray-700">
                                        <div class="flex flex-col gap-0.5">
                                            <span class="font-medium text-gray-900">{{ $record->usage_format ?: '–' }}</span>
                                            @if($record->usage_rights)
                                                <span class="text-xs text-gray-500">{{ $record->usage_rights }}</span>
                                            @endif
                                            @if($record->article_url)
                                                <a href="{{ $record->article_url }}" target="_blank" rel="noopener" class="text-xs text-[#092E48] hover:underline">{{ \Illuminate\Support\Str::limit($record->article_url, 40) }}</a>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 text-right text-gray-900">{{ $record->images_count }}</td>
                                    <td class="px-4 py-2 text-right text-gray-900">{{ number_format((float) $record->video_minutes, 1, ',', '.') }}</td>
                                    <td class="px-4 py-2 text-right text-gray-900">{{ number_format((float) ($record->radio_minutes ?? 0), 1, ',', '.') }}</td>
                                    <td class="px-4 py-2 text-right text-gray-900 whitespace-nowrap">{{ number_format((float) $record->total_amount, 2, ',', '.') }} €</td>
                                    <td class="px-4 py-2 text-right whitespace-nowrap">
                                        @if($record->invoice_id && $record->invoice)
                                            <a href="{{ route('admin.backoffice.billing.show', $record->invoice) }}" class="inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-md bg-gray-100 text-gray-700 hover:bg-gray-200">
                                                Rechnung
                                            </a>
                                        @else
                                            <a href="{{ route('admin.backoffice.usage.edit', $record) }}" class="inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">
                                                Bearbeiten
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-gray-500">
                    <a href="{{ route('admin.backoffice.usage.index') }}" class="text-[#092E48] hover:underline">Nutzung &amp; Auswertungen</a> im Backoffice – neue Verwendungen dort erfassen.
                </p>
            @endif
        @else
            <p class="text-sm text-gray-600">Verwendungen sind nach dem Speichern der Meldung unter diesem Tab sichtbar.</p>
        @endif
    </div>
</div>
