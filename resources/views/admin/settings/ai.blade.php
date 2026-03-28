@extends('layouts.admin')

@section('content')
    <div class="space-y-6" x-data="{
        testLoading: false,
        testResult: null,
        async runTest() {
            this.testLoading = true;
            this.testResult = null;
            try {
                const r = await fetch('{{ route('admin.settings.ai.test') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                });
                const data = await r.json();
                this.testResult = data.success ? { ok: true, message: data.message } : { ok: false, message: data.message };
            } catch (e) {
                this.testResult = { ok: false, message: e.message || 'Netzwerkfehler' };
            }
            this.testLoading = false;
        }
    }">
        <div>
            <a href="{{ route('admin.settings.index') }}" class="text-sm text-gray-600 hover:text-[#092E48] mb-2 inline-block">← Einstellungen</a>
            <h1 class="text-2xl font-semibold text-gray-900">KI / ChatGPT</h1>
            <p class="mt-1 text-sm text-gray-600">OpenAI-Anbindung für automatische Bild-Metadaten (Titel, Bildunterschrift, Schlagwörter). API-Key kommt aus .env.</p>
        </div>

        @if (session('ai_test_ok'))
            <div class="rounded-md bg-green-50 p-4 border border-green-200">
                <p class="text-sm text-green-800">{{ session('ai_test_ok') }}</p>
            </div>
        @endif
        @if (session('ai_test_error'))
            <div class="rounded-md bg-red-50 p-4 border border-red-200">
                <p class="text-sm text-red-800">{{ session('ai_test_error') }}</p>
            </div>
        @endif

        {{-- Status --}}
        <div class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between gap-4">
                <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase">Verbindungsstatus</h2>
                @if($keySet)
                    @if($statusOk)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 text-emerald-800 px-3 py-1 text-xs font-medium ring-1 ring-emerald-200">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                            Verbindung OK
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-red-100 text-red-800 px-3 py-1 text-xs font-medium ring-1 ring-red-200">
                            <span class="h-2 w-2 rounded-full bg-red-500"></span>
                            Fehler / nicht getestet
                        </span>
                    @endif
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-red-100 text-red-800 px-3 py-1 text-xs font-medium ring-1 ring-red-200">
                        <span class="h-2 w-2 rounded-full bg-red-500"></span>
                        Key fehlt
                    </span>
                @endif
            </div>
            <div class="px-6 py-4 space-y-2">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-gray-500">API-Key</dt>
                        <dd class="font-medium text-gray-900">{{ $keySet ? 'Key gesetzt' : 'Key fehlt' }}</dd>
                    </div>
                    @if($lastOkAt)
                        <div>
                            <dt class="text-gray-500">Letzter erfolgreicher Test</dt>
                            <dd class="text-gray-700">{{ \Carbon\Carbon::parse($lastOkAt)->format('d.m.Y H:i') }} Uhr</dd>
                        </div>
                    @endif
                    @if($lastError)
                        <div class="sm:col-span-2">
                            <dt class="text-gray-500">Letzte Fehlermeldung</dt>
                            <dd class="text-red-700 text-xs font-mono break-words" title="{{ $lastError }}">{{ \Illuminate\Support\Str::limit($lastError, 200) }}</dd>
                        </div>
                    @endif
                </dl>
                <div class="pt-2">
                    <button type="button"
                            @click="runTest()"
                            :disabled="testLoading"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-xl bg-[#092E48] text-white hover:bg-[#0b3858] disabled:opacity-50 disabled:cursor-not-allowed ring-1 ring-slate-200">
                        <span x-show="testLoading">Bitte warten…</span>
                        <span x-show="!testLoading">Verbindung testen</span>
                    </button>
                </div>
                <div x-show="testResult" x-cloak class="mt-2 p-3 rounded-lg text-sm"
                     :class="testResult?.ok ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200'">
                    <span x-text="testResult?.message"></span>
                </div>
            </div>
        </div>

        {{-- Konfiguration (aus .env / config, nur Anzeige) --}}
        <div class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase">Konfiguration</h2>
                <p class="mt-1 text-xs text-gray-500">Werte aus config/media_ai.php bzw. .env. Änderungen nur über .env möglich.</p>
            </div>
            <dl class="px-6 py-4 divide-y divide-gray-100">
                <div class="py-3 flex justify-between gap-4 items-center">
                    <dt class="text-sm text-gray-500">Vision-Modell</dt>
                    <dd class="text-sm font-medium text-gray-900 font-mono">{{ $config['vision_model'] ?? '–' }}</dd>
                </div>
                <div class="py-3 flex justify-between gap-4 items-center">
                    <dt class="text-sm text-gray-500">Auto-Analyse bei Upload</dt>
                    <dd class="text-sm text-gray-700">{{ $config['auto_analyze'] ? 'An' : 'Aus' }}</dd>
                </div>
                <div class="py-3 flex justify-between gap-4 items-center">
                    <dt class="text-sm text-gray-500">Rate Limit (pro Minute)</dt>
                    <dd class="text-sm text-gray-700">{{ $config['rate_limit_per_minute'] ?? '–' }}</dd>
                </div>
                <div class="py-3 flex justify-between gap-4 items-center">
                    <dt class="text-sm text-gray-500">Timeout (Sekunden)</dt>
                    <dd class="text-sm text-gray-700">{{ $config['timeout'] ?? '–' }}</dd>
                </div>
                <div class="py-3 flex justify-between gap-4 items-center">
                    <dt class="text-sm text-gray-500">Retries</dt>
                    <dd class="text-sm text-gray-700">{{ $config['retries'] ?? '–' }}</dd>
                </div>
            </dl>
        </div>
    </div>
@endsection
