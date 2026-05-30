{{-- Erwartet Textarea mit id="body". $webTextAiEnabled gesetzt in Controller. Optional: $webTextAiToolbarLayout === 'below' (unter dem Feld, volle Breite). --}}
@if (! empty($webTextAiEnabled))
    @php
        $aiToolbarBelow = ($webTextAiToolbarLayout ?? '') === 'below';
    @endphp
    <div
        class="{{ $aiToolbarBelow ? 'w-full flex flex-col items-stretch gap-1' : 'w-full sm:w-auto shrink-0' }}"
        x-data="{
            loading: false,
            error: null,
            async polish() {
                const ta = document.getElementById('body');
                if (!ta) return;
                const text = (ta.value || '').trim();
                if (!text) {
                    this.error = 'Bitte zuerst Text im Feld „Basistext“ eintragen.';
                    return;
                }
                this.error = null;
                this.loading = true;
                try {
                    const r = await fetch('{{ route('admin.news.ai.polish-web-text') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ text }),
                    });
                    const data = await r.json().catch(() => ({}));
                    if (!r.ok) {
                        this.error = data.message || 'Anfrage fehlgeschlagen.';
                        return;
                    }
                    if (typeof data.text === 'string' && data.text.length) {
                        ta.value = data.text;
                        ta.dispatchEvent(new Event('input', { bubbles: true }));
                        queueMicrotask(() => {
                            if (typeof window.eknSplitNewsBlocktext === 'function') {
                                window.eknSplitNewsBlocktext();
                            }
                        });
                    } else {
                        this.error = 'Leere Antwort von der KI.';
                    }
                } catch (e) {
                    this.error = e.message || 'Netzwerkfehler';
                } finally {
                    this.loading = false;
                }
            },
        }"
    >
        <button
            type="button"
            @click="polish()"
            :disabled="loading"
            class="inline-flex items-center justify-center gap-2 min-h-[2.25rem] px-3 py-1.5 text-xs font-medium rounded-lg border border-violet-200 bg-violet-50 text-violet-900 hover:bg-violet-100 disabled:opacity-50 disabled:cursor-not-allowed {{ $aiToolbarBelow ? 'w-full sm:w-fit' : 'w-full sm:w-auto' }}"
        >
            <span x-show="loading" x-cloak>Bitte warten…</span>
            <span x-show="!loading">Basistext mit KI überarbeiten</span>
        </button>
        <p x-show="error" x-cloak class="mt-1 text-xs text-red-600 max-w-md" x-text="error"></p>
    </div>
@endif
