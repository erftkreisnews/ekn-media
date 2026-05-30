@extends('layouts.admin')

@section('title', 'Ingest Render-Jobs')

@section('content')
    <div class="space-y-6 min-w-0 max-w-7xl">
        <div class="rounded-xl border border-gray-200 bg-white p-4 sm:p-5 shadow-sm min-w-0 max-w-full">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Ingest</p>
            <h1 class="mt-1 text-2xl font-semibold text-gray-900">Render-Jobs (0-100%)</h1>
            <p class="mt-2 text-sm text-gray-600">
                Zeigt offene Render-Jobs inkl. fehlgeschlagener Jobs. Progress ist eine Heuristik basierend auf Job-Zeit + Status.
            </p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden min-w-0 max-w-full">
            <div class="px-4 sm:px-6 py-4 border-b border-gray-200 flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-gray-900">Offene Jobs</h2>
                <span class="text-sm text-gray-500">{{ $jobsData->count() }} Job(s)</span>
            </div>

            <div class="px-4 sm:px-6 py-4">
                <div id="renderJobsRoot"
                     data-endpoint="{{ route('admin.ingest.render-jobs.status') }}"
                     class="space-y-3">
                    @forelse ($jobsData as $job)
                        @php
                            $isFailed = ($job['status'] ?? '') === \App\Models\IngestRenderJob::STATUS_FAILED;
                            $isUploading = ($job['stageKey'] ?? '') === 'uploading' || ($job['status'] ?? '') === \App\Models\IngestRenderJob::STATUS_UPLOADING;
                            $isRendering = ($job['status'] ?? '') === \App\Models\IngestRenderJob::STATUS_RENDERING;
                            $isQueued = ($job['status'] ?? '') === \App\Models\IngestRenderJob::STATUS_QUEUED;

                            $barColor = $isFailed ? 'bg-red-600' : ($isUploading ? 'bg-indigo-600' : ($isRendering ? 'bg-amber-500' : ($isQueued ? 'bg-gray-400' : 'bg-emerald-600')));
                            $rowBadgeColor = $isFailed ? 'bg-red-50 text-red-900 border-red-200' : ($isUploading ? 'bg-indigo-50 text-indigo-900 border-indigo-200' : ($isRendering ? 'bg-amber-50 text-amber-900 border-amber-200' : 'bg-gray-50 text-gray-800 border-gray-200'));
                        @endphp

                        <div id="render-job-{{ $job['id'] }}"
                             class="p-3 border border-gray-200 rounded-xl bg-gray-50/30 min-w-0 max-w-full">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-mono text-sm text-gray-700">Job #{{ $job['id'] }}</span>
                                        <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full border {{ $rowBadgeColor }}"
                                              data-stage="{{ $job['stage'] }}">
                                            {{ $job['stage'] }}
                                        </span>
                                        @if (! empty($job['news_item_id']))
                                            <a href="{{ route('admin.ingest.news-workspace', $job['news_item_id']) }}"
                                               class="text-xs text-[#092E48] hover:underline hover:bg-[#092E48]/5 px-2 py-1 rounded">
                                                News #{{ $job['news_item_id'] }}
                                            </a>
                                        @endif
                                    </div>
                                    @if (! empty($job['news_title']))
                                        <p class="mt-1 text-sm text-gray-600 truncate" title="{{ $job['news_title'] }}">{{ $job['news_title'] }}</p>
                                    @endif
                                </div>

                                <div class="text-right">
                                    <div class="text-sm font-semibold text-gray-900" data-progress-label> {{ (int) $job['progress'] }}% </div>
                                    <div class="h-2 w-56 max-w-full bg-gray-200 rounded-full overflow-hidden mt-2" aria-label="Progress">
                                        <div class="h-full {{ $barColor }} rounded-full transition-[width] duration-300"
                                             style="width: {{ (int) $job['progress'] }}%"
                                             data-progress-bar></div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3 {{ $isFailed ? '' : 'hidden' }} render-job-error" data-error>
                                <p class="text-xs font-semibold text-red-900 mb-1">Fehlertext</p>
                                <p class="text-sm text-red-800 whitespace-pre-wrap break-words">
                                    {{ $job['error_message'] ? \Illuminate\Support\Str::limit($job['error_message'], 1200) : 'Kein Fehlertext hinterlegt.' }}
                                </p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Keine offenen Render-Jobs.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                const root = document.getElementById('renderJobsRoot');
                if (!root) return;

                const endpoint = root.dataset.endpoint;
                if (!endpoint) return;

                const getRowIds = () => new Set(
                    Array.from(root.querySelectorAll('[id^="render-job-"]'))
                        .map(el => el.id.startsWith('render-job-') ? el.id.slice('render-job-'.length) : el.id)
                );

                async function poll() {
                    try {
                        const res = await fetch(endpoint, { headers: { 'Accept': 'application/json' } });
                        if (!res.ok) return;
                        const data = await res.json();
                        const jobs = Array.isArray(data.jobs) ? data.jobs : [];

                        const nextIds = new Set(jobs.map(j => String(j.id)));
                        const existingIds = getRowIds();

                        // Entferne Zeilen, die nicht mehr offen sind.
                        existingIds.forEach(id => {
                            if (!nextIds.has(String(id))) {
                                const el = document.getElementById('render-job-' + id);
                                if (el) el.remove();
                            }
                        });

                        for (const job of jobs) {
                            const id = String(job.id);
                            const row = document.getElementById('render-job-' + id);
                            if (!row) {
                                // Neue Job-Zeile: Einfach reload, damit die UI sicher korrekt ist.
                                window.location.reload();
                                return;
                            }

                            const progress = Number(job.progress ?? 0);
                            const label = row.querySelector('[data-progress-label]');
                            const bar = row.querySelector('[data-progress-bar]');
                            const stage = row.querySelector('[data-stage]');
                            if (label) label.textContent = progress + '%';
                            if (bar) bar.style.width = progress + '%';
                            if (stage) stage.textContent = job.stage || '';

                            const errorEl = row.querySelector('[data-error]');
                            const isFailed = job.status === 'failed';
                            if (errorEl) {
                                errorEl.classList.toggle('hidden', !isFailed);
                                const p = errorEl.querySelector('p');
                                if (p) p.textContent = job.error_message ? job.error_message : 'Kein Fehlertext hinterlegt.';
                            }
                        }
                    } catch (e) {}
                }

                setInterval(poll, 8000);
            })();
        </script>
    @endpush
@endsection

