@extends('layouts.admin')

@section('content')
    <div class="space-y-6 max-w-7xl mx-auto">
        <div>
            <a href="{{ route('admin.backoffice.index') }}" class="text-sm text-gray-600 hover:text-[#092E48] mb-2 inline-block">← Backoffice</a>
            <h1 class="text-2xl font-semibold text-gray-900">Abrechnung</h1>
            <p class="mt-1 text-sm text-gray-600">
                Offene Mengen pro Rechnungseinheit und Rechnungsentwürfe (Entwurf, Lexware‑vorgemerkt, in Bearbeitung).
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

        {{-- Offene Mengen pro Rechnungseinheit (Produkt) --}}
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
                <h2 class="text-base font-medium text-gray-900">Offene Mengen (noch nicht abgerechnet)</h2>
                <p class="mt-0.5 text-sm text-gray-500">Rechnungseinheiten mit offenen Nachverfolgungs-Einträgen. Klicke auf „Rechnung anlegen“, um einen Entwurf zu erstellen.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Rechnungseinheit / Medienhaus</th>
                            <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Video (Min.)</th>
                            <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Radio Lizenz (Min.)</th>
                            <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Radio Honorar (Min.)</th>
                            <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Fotos (Lizenz)</th>
                            <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Netto (€)</th>
                            <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Aktion</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($products as $product)
                            <tr>
                                <td class="px-4 py-3 text-sm">
                                    <span class="font-medium text-gray-900">{{ $product->name }}</span>
                                    @if($product->organization)
                                        <span class="text-gray-500"> · {{ $product->organization->name }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-right text-gray-700">{{ number_format((float) ($product->open_video_minutes ?? 0), 1, ',', '.') }}</td>
                                <td class="px-4 py-3 text-sm text-right text-gray-700">{{ number_format((float) ($product->open_lizenz_radio_minutes ?? 0), 1, ',', '.') }}</td>
                                <td class="px-4 py-3 text-sm text-right text-gray-700">{{ number_format((float) ($product->open_honorar_radio_minutes ?? 0), 1, ',', '.') }}</td>
                                <td class="px-4 py-3 text-sm text-right text-gray-700">{{ (int) ($product->open_photos ?? 0) }}</td>
                                <td class="px-4 py-3 text-sm text-right text-gray-700">{{ number_format((float) ($product->open_net ?? 0), 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right align-top">
                                    @php
                                        $hasPhotos = (int) ($product->open_photos ?? 0) > 0;
                                        $hasVideos = (float) ($product->open_video_minutes ?? 0) > 0;
                                        $hasLizenzRadio = (float) ($product->open_lizenz_radio_minutes ?? 0) > 0;
                                        $hasHonorarRadio = (float) ($product->open_honorar_radio_minutes ?? 0) > 0;
                                        $invoiceKindLinks = [];
                                        if ($hasPhotos) {
                                            $invoiceKindLinks[] = ['kind' => 'lizenz_bild', 'label' => 'Lizenz (Bilder)', 'style' => 'background-color:#065f46;color:#ffffff;border:1px solid #065f46;'];
                                        }
                                        if ($hasVideos) {
                                            $invoiceKindLinks[] = ['kind' => 'lizenz_video', 'label' => 'Lizenz (Video)', 'style' => 'background-color:#1d4ed8;color:#ffffff;border:1px solid #1d4ed8;'];
                                        }
                                        if ($hasLizenzRadio) {
                                            $invoiceKindLinks[] = ['kind' => 'lizenz_radio', 'label' => 'Lizenz (Radio)', 'style' => 'background-color:#0e7490;color:#ffffff;border:1px solid #0e7490;'];
                                        }
                                        if ($hasHonorarRadio) {
                                            $invoiceKindLinks[] = ['kind' => 'honorar_audio', 'label' => 'Honorar (Radio)', 'style' => 'background-color:#a16207;color:#ffffff;border:1px solid #a16207;'];
                                        }
                                    @endphp
                                    @if(count($invoiceKindLinks) > 1)
                                        <div class="flex flex-col items-end gap-2">
                                            @foreach($invoiceKindLinks as $link)
                                                <a href="{{ route('admin.backoffice.billing.create', ['product' => $product->id, 'invoice_kind' => $link['kind']]) }}" class="inline-flex items-center px-3 py-2 text-sm font-semibold rounded-md" style="{{ $link['style'] }}">{{ $link['label'] }}</a>
                                            @endforeach
                                        </div>
                                    @elseif(count($invoiceKindLinks) === 1)
                                        <a href="{{ route('admin.backoffice.billing.create', ['product' => $product->id, 'invoice_kind' => $invoiceKindLinks[0]['kind']]) }}" class="inline-flex items-center px-3 py-2 text-sm font-semibold rounded-md" style="{{ $invoiceKindLinks[0]['style'] }}">{{ $invoiceKindLinks[0]['label'] }}</a>
                                    @else
                                        <a href="{{ route('admin.backoffice.billing.create', $product) }}" class="inline-flex items-center px-3 py-2 text-sm font-semibold rounded-md" style="background-color:#092E48;color:#ffffff;border:1px solid #092E48;">Rechnung anlegen</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500">Keine Rechnungseinheiten mit offenen Nachverfolgungs-Einträgen.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($products->hasPages())
                <div class="px-4 py-3 border-t border-gray-200 bg-gray-50">
                    {{ $products->links() }}
                </div>
            @endif
        </div>

        {{-- Rechnungsentwürfe (Entwurf / Lexware vorgemerkt / in Bearbeitung) --}}
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
                <h2 class="text-base font-medium text-gray-900">Rechnungsentwürfe</h2>
                <p class="mt-0.5 text-sm text-gray-500">Lokale Entwürfe und Rechnungen in Bearbeitung (Status: Entwurf, Lexware‑vorgemerkt, ausstehend, Lexware offen).</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Rechnung</th>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Kunde / Rechnungseinheit</th>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Empfänger</th>
                            <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Eingang</th>
                            <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Positionen</th>
                            <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
                            <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Zahlung</th>
                            <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Aktion</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($draftInvoices as $invoice)
                            @php
                                $dispatch = $invoice->dispatch_status_data ?? [];
                                $statusLabels = [
                                    'draft' => 'Entwurf',
                                    'pending' => 'Ausstehend',
                                    'ready_for_lexware' => 'Für Lexware',
                                    'lexware_open' => 'Lexware offen',
                                    'lexware_voided' => 'Lexware storniert',
                                ];
                            @endphp
                            <tr>
                                <td class="px-4 py-3 text-sm">
                                    <span class="font-medium text-gray-900">#{{ $invoice->id }}</span>
                                    @if($invoice->voucher_number)
                                        <span class="text-gray-500"> · {{ $invoice->voucher_number }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ $invoice->organization?->name ?? '–' }}
                                    @if($invoice->product)
                                        <span class="text-gray-500"> · {{ $invoice->product->name }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $invoice->contact?->email ?? $invoice->contact?->name ?? '–' }}</td>
                                <td class="px-4 py-3 text-sm text-right text-gray-700 align-top">
                                    @if(!empty($hasIncomingDocumentsTable))
                                        @php
                                            $incomingCount = (int) ($invoice->incoming_documents_count ?? 0);
                                            $showWdrEingangPunkte = !empty($hasWdrBillingChecklistColumn) && $invoice->isWdrBillingContext();
                                            $lizDone = $showWdrEingangPunkte && $invoice->isWdrLizenzvertragSatisfied();
                                            $vergDone = $showWdrEingangPunkte && $invoice->isWdrVerguetungsmitteilungSatisfied();
                                        @endphp
                                        <div class="flex flex-col items-end gap-1.5">
                                            @if($showWdrEingangPunkte)
                                                <div class="flex flex-wrap items-center justify-end gap-1" title="Prüfpunkte auf der Rechnung setzen (WDR)">
                                                    <a href="{{ route('admin.backoffice.billing.show', $invoice) }}#wdr-abrechnung" class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-semibold leading-tight {{ $lizDone ? 'bg-green-100 text-green-800 ring-1 ring-green-200' : 'bg-red-100 text-red-800 ring-1 ring-red-200' }}">
                                                        Lizenzvertrag: {{ $lizDone ? 'OK' : 'offen' }}
                                                    </a>
                                                    <a href="{{ route('admin.backoffice.billing.show', $invoice) }}#wdr-abrechnung" class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-semibold leading-tight {{ $vergDone ? 'bg-green-100 text-green-800 ring-1 ring-green-200' : 'bg-red-100 text-red-800 ring-1 ring-red-200' }}">
                                                        Vergütung: {{ $vergDone ? 'OK' : 'offen' }}
                                                    </a>
                                                </div>
                                            @endif
                                            @if($incomingCount <= 0)
                                                <div class="flex flex-col items-end gap-0.5">
                                                    @if(! $showWdrEingangPunkte)
                                                        <span class="text-gray-400">–</span>
                                                    @endif
                                                    <a href="{{ route('admin.backoffice.billing.show', $invoice) }}#eingangsdokumente" class="text-xs font-medium text-[#092E48] hover:underline">Hochladen</a>
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-gray-400">–</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-right text-gray-700">{{ $invoice->usage_records_count ?? 0 }}</td>
                                <td class="px-4 py-3 text-right">
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800">
                                        {{ $statusLabels[$invoice->status] ?? $invoice->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-right text-gray-700 align-top">
                                    @php
                                        $pay = (array) (($invoice->meta ?? [])['payment'] ?? []);
                                        $payOn = $pay['received_on'] ?? null;
                                    @endphp
                                    @if($payOn)
                                        <span class="text-emerald-800 font-medium" title="Zahlung erfasst">{{ \Illuminate\Support\Carbon::parse($payOn)->format('d.m.Y') }}</span>
                                    @else
                                        <span class="text-gray-400">offen</span>
                                    @endif
                                    <a href="{{ route('admin.backoffice.billing.show', $invoice) }}#zahlungseingang" class="block text-xs font-medium text-[#092E48] hover:underline mt-0.5">Erfassen</a>
                                </td>
                                <td class="px-4 py-3 text-right align-top">
                                    <div class="flex flex-col items-end gap-1.5">
                                        <a href="{{ route('admin.backoffice.billing.show', $invoice) }}" class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md border border-gray-300 bg-white !text-gray-800 hover:bg-gray-50" style="color:#1f2937;">Anzeigen</a>
                                        @if(!empty($hasIncomingDocumentsTable))
                                            <a href="{{ route('admin.backoffice.billing.show', $invoice) }}#eingangsdokumente" class="text-xs font-medium text-[#092E48] hover:underline">Ablage / Eingang</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-6 text-center text-sm text-gray-500">Keine Rechnungsentwürfe in Bearbeitung.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
