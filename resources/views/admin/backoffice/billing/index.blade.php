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
                            <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Videominuten</th>
                            <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Fotos</th>
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
                                <td class="px-4 py-3 text-sm text-right text-gray-700">{{ (int) ($product->open_photos ?? 0) }}</td>
                                <td class="px-4 py-3 text-sm text-right text-gray-700">{{ number_format((float) ($product->open_net ?? 0), 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.backoffice.billing.create', $product) }}" class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">Rechnung anlegen</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">Keine Rechnungseinheiten mit offenen Nachverfolgungs-Einträgen.</td>
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
                            <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Positionen</th>
                            <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
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
                                <td class="px-4 py-3 text-sm text-right text-gray-700">{{ $invoice->usage_records_count ?? 0 }}</td>
                                <td class="px-4 py-3 text-right">
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800">
                                        {{ $statusLabels[$invoice->status] ?? $invoice->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.backoffice.billing.show', $invoice) }}" class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Anzeigen</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">Keine Rechnungsentwürfe in Bearbeitung.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
