@php
    use App\Models\NewsItemFieldAudit;
@endphp

@if ($fieldAuditsForEdit instanceof \Illuminate\Support\Collection && $fieldAuditsForEdit->isNotEmpty())
    <div id="section-news-field-audit" class="mt-8 border-t border-gray-200 pt-8">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">
            Änderungshistorie (Publikation &amp; Kennzeichnung)
        </h3>
        <p class="text-xs text-gray-500 mb-4">
            Protokolliert: Status, Veröffentlichungsdatum, Sperrfrist, Titel und URL-Slug bei Anlage und Speichern.
            Hintergrund-Jobs ohne angemeldeten Benutzer erscheinen ohne Namen.
        </p>
        <div class="overflow-x-auto rounded-xl border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">
                    <tr>
                        <th class="px-3 py-2 whitespace-nowrap">Zeit</th>
                        <th class="px-3 py-2 whitespace-nowrap">Benutzer</th>
                        <th class="px-3 py-2 whitespace-nowrap">Ereignis</th>
                        <th class="px-3 py-2 min-w-[14rem]">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($fieldAuditsForEdit as $audit)
                        <tr class="align-top">
                            <td class="px-3 py-2 whitespace-nowrap text-gray-700">
                                {{ $audit->created_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') }}
                            </td>
                            <td class="px-3 py-2 text-gray-700">
                                @if ($audit->user)
                                    {{ $audit->user->name }}
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                @if ($audit->event === NewsItemFieldAudit::EVENT_CREATED)
                                    <span class="inline-flex rounded-full bg-emerald-50 text-emerald-900 px-2 py-0.5 text-xs font-medium">Angelegt</span>
                                @else
                                    <span class="inline-flex rounded-full bg-sky-50 text-sky-900 px-2 py-0.5 text-xs font-medium">Geändert</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-gray-800">
                                @if ($audit->event === NewsItemFieldAudit::EVENT_CREATED && is_array($audit->new_values))
                                    <ul class="space-y-1 list-disc list-inside text-xs sm:text-sm">
                                        @foreach ($audit->new_values as $key => $val)
                                            <li>
                                                <span class="font-medium text-gray-600">{{ NewsItemFieldAudit::labelForField((string) $key) }}:</span>
                                                {{ NewsItemFieldAudit::formatValueForDisplay((string) $key, $val) }}
                                            </li>
                                        @endforeach
                                    </ul>
                                @elseif ($audit->event === NewsItemFieldAudit::EVENT_UPDATED && is_array($audit->old_values) && is_array($audit->new_values))
                                    <ul class="space-y-2 text-xs sm:text-sm">
                                        @foreach ($audit->new_values as $key => $newVal)
                                            @php $k = (string) $key; @endphp
                                            <li class="border-l-2 border-sky-200 pl-2">
                                                <span class="font-medium text-gray-600">{{ NewsItemFieldAudit::labelForField($k) }}</span>
                                                <div class="mt-0.5 text-gray-600">
                                                    <span class="text-gray-400">Alt:</span>
                                                    {{ NewsItemFieldAudit::formatValueForDisplay($k, $audit->old_values[$k] ?? null) }}
                                                </div>
                                                <div class="text-gray-900">
                                                    <span class="text-gray-400">Neu:</span>
                                                    {{ NewsItemFieldAudit::formatValueForDisplay($k, $newVal) }}
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <span class="text-gray-400 text-xs">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
