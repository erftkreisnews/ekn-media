@php
    $deliveryTimeline = $deliveryTimeline ?? collect();
@endphp
@if($deliveryTimeline->isNotEmpty())
    <section id="updates" class="mb-6">
        <h2 class="text-lg font-semibold text-ekn-900 mb-1">Lage im Verlauf</h2>
        <p class="text-sm text-slate-500 mb-4">Neuester Stand oben – darunter die vorherigen Stände bis zur Erstmeldung.</p>
        <ol class="space-y-4 list-none pl-0">
            @foreach($deliveryTimeline as $entry)
                <li>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 sm:p-5">
                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1 mb-2">
                            <time class="text-sm font-bold text-ekn-900 tabular-nums" datetime="{{ $entry['at']->toIso8601String() }}">
                                {{ $entry['at']->format('d.m.Y, H:i') }} Uhr
                            </time>
                            <span class="text-sm font-semibold text-indigo-800">{{ $entry['label'] }}</span>
                        </div>
                        @if(!empty($entry['title']))
                            <p class="text-sm font-semibold text-gray-900 mb-1 leading-snug">{{ $entry['title'] }}</p>
                        @endif
                        @if(!empty($entry['source']))
                            <p class="text-xs text-slate-500 mb-1">Quelle: {{ $entry['source'] }}</p>
                        @endif
                        @php
                            $timelineBodyRaw = trim((string) ($entry['body'] ?? ''));
                            if ($timelineBodyRaw !== '' && ! ($entry['is_html'] ?? false)) {
                                $timelineBodyRaw = preg_replace("/\n{3,}/", "\n\n", $timelineBodyRaw) ?? $timelineBodyRaw;
                                $timelineBodyRaw = preg_replace('/^[ \t]+/m', '', $timelineBodyRaw) ?? $timelineBodyRaw;
                            }
                            $timelineBody = $timelineBodyRaw;
                        @endphp
                        @if($timelineBody !== '')
                            @if($entry['is_html'] ?? false)
                                <div class="text-sm sm:text-base text-gray-800 leading-relaxed prose prose-gray max-w-none">{!! $timelineBody !!}</div>
                            @else
                                <div class="text-sm sm:text-base text-gray-800 leading-relaxed break-words">{!! nl2br(e($timelineBody)) !!}</div>
                            @endif
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    </section>
@endif
