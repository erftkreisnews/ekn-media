{{-- Nur in News bearbeiten: Zuweisung Medienpaket/FTP (Organisationen aus DB) --}}
@if (isset($organizationsForMediaDelivery) && $organizationsForMediaDelivery->isNotEmpty())
    @php
        $deliveryOrgIds = $m->delivery_visible_for_organization_ids;
        $deliveryOrgIds = is_array($deliveryOrgIds) ? $deliveryOrgIds : [];
        $deliveryOrgIdsInt = array_map('intval', $deliveryOrgIds);
        $hasDeliveryOrgFilter = count($deliveryOrgIdsInt) > 0;
    @endphp
    <div class="mt-2 pt-2 border-t border-gray-100">
        <details class="group" @if ($hasDeliveryOrgFilter) open @endif>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-2 rounded-md py-0.5 text-[11px] font-medium text-gray-700 select-none hover:text-gray-900 [&::-webkit-details-marker]:hidden">
                <span class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-0.5">
                    <span>Medienpaket / FTP: nur für</span>
                    @if ($hasDeliveryOrgFilter)
                        <span class="font-normal text-[10px] text-gray-500">({{ count($deliveryOrgIdsInt) }} ausgewählt)</span>
                    @else
                        <span class="font-normal text-[10px] text-gray-500">(alle)</span>
                    @endif
                </span>
                <span class="shrink-0 text-gray-400 transition-transform group-open:rotate-180" aria-hidden="true">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </span>
            </summary>
            <div class="mt-2 space-y-1.5">
                {{-- Explizit form="newsEditForm": Im Tab „Bilder“ schließt ein verschachteltes Bulk-Lösch-Formular das äußere <form> im DOM; ohne form-Attribut würden diese Felder beim Speichern fehlen. --}}
                <input type="hidden" name="media[{{ $m->id }}][delivery_visibility_set]" value="1" form="newsEditForm">
                <div class="flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-gray-700">
                    @foreach ($organizationsForMediaDelivery as $org)
                        <label class="inline-flex cursor-pointer items-center gap-1.5">
                            <input
                                type="checkbox"
                                name="media[{{ $m->id }}][delivery_visible_for_organization_ids][]"
                                value="{{ $org->id }}"
                                form="newsEditForm"
                                class="rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                                @checked(in_array((int) $org->id, $deliveryOrgIdsInt, true))
                            >
                            <span>{{ $org->name }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="text-[10px] leading-snug text-gray-500">Keine Auswahl = alle Medienhäuser. Eine oder mehrere = nur diese (nach Bestätigung der Redaktion im Medienpaket; reine Freitext-Angabe ohne CRM-Organisation reicht nicht).</p>
            </div>
        </details>
    </div>
@endif
