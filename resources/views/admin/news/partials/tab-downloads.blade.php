{{-- Tab „Downloads“: Kunden-Downloads zur News-ID (wer hat was heruntergeladen) --}}
<div class="bg-white shadow-sm rounded-2xl border border-gray-200 overflow-hidden min-w-0 max-w-full">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <h2 class="text-base font-semibold text-gray-900">Kunden-Downloads zur Meldung @if(isset($newsItem) && $newsItem)#{{ $newsItem->id }}@endif</h2>
        <p class="mt-1 text-sm text-gray-600">Welcher Kunde hat welches Medium zu dieser Meldung heruntergeladen. Steht keine Redaktion fest (Link noch nicht bestätigt), wird die <span class="font-medium text-gray-800">E-Mail des Versandlinks</span> angezeigt.</p>
    </div>
    <div class="px-4 sm:px-6 py-4">
        @if (!isset($newsItem) || !$newsItem)
            <p class="text-sm text-gray-500">Nach dem Speichern der Meldung erscheinen hier die Kunden-Downloads zu dieser News-ID.</p>
        @else
            @php
                $customerDownloads = \App\Models\DeliveryEvent::query()
                    ->where('event_type', 'download')
                    ->whereHas('delivery', fn ($q) => $q->where('news_item_id', $newsItem->id))
                    ->with([
                        'media',
                        'organization',
                        'product',
                        'delivery.organization',
                        'delivery.product',
                    ])
                    ->orderByDesc('created_at')
                    ->limit(200)
                    ->get();
            @endphp
            @if ($customerDownloads->isEmpty())
                <p class="text-sm text-gray-500">Bisher keine Kunden-Downloads zu dieser Meldung.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Datum / Uhrzeit</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kunde / Organisation</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produkt</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Medium (Datei)</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($customerDownloads as $ev)
                                @php
                                    $orgResolved = $ev->organization?->name
                                        ?? (filled($ev->self_reported_organization_name) ? $ev->self_reported_organization_name : null)
                                        ?? $ev->delivery?->organization?->name
                                        ?? (filled($ev->delivery?->self_reported_organization_name) ? $ev->delivery->self_reported_organization_name : null);
                                    $recipientEmail = $ev->delivery?->recipient_email;
                                    $orgShowsEmailOnly = ($orgResolved === null || $orgResolved === '') && filled($recipientEmail);
                                    $orgName = $orgResolved !== null && $orgResolved !== ''
                                        ? $orgResolved
                                        : ($recipientEmail ?: '–');

                                    $productResolved = $ev->product?->name
                                        ?? (filled($ev->self_reported_product_name) ? $ev->self_reported_product_name : null)
                                        ?? $ev->delivery?->product?->name
                                        ?? (filled($ev->delivery?->self_reported_product_name) ? $ev->delivery->self_reported_product_name : null);
                                    $productName = ($productResolved !== null && $productResolved !== '')
                                        ? $productResolved
                                        : ($orgShowsEmailOnly ? '— (nicht bestätigt)' : '–');
                                    $mediaName = $ev->media ? ($ev->media->original_name ?: basename($ev->media->path)) : '–';
                                @endphp
                                <tr class="hover:bg-gray-50/50">
                                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $ev->created_at?->format('d.m.Y H:i') ?? '–' }}</td>
                                    <td class="px-4 py-3 text-gray-900">
                                        @if ($orgShowsEmailOnly)
                                            <span class="font-mono text-[13px]">{{ $recipientEmail }}</span>
                                            <span class="block text-xs text-gray-500 mt-0.5">Versand-E-Mail (kein Medienhaus bestätigt)</span>
                                        @else
                                            {{ $orgName }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ $productName }}</td>
                                    <td class="px-4 py-3 text-gray-700 truncate max-w-xs" title="{{ $mediaName }}">{{ $mediaName }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($customerDownloads->count() >= 200)
                    <p class="mt-3 text-xs text-gray-500">Es werden die letzten 200 Download-Einträge angezeigt.</p>
                @endif
            @endif
        @endif
    </div>
</div>
