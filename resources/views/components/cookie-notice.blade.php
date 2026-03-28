{{-- Cookie-Hinweis: sichtbar bis "Verstanden" (localStorage). 
     Auf Mobil nicht über dem Inhalt fixieren, sondern unten auf der Seite anzeigen. --}}
<div
    x-data="{
        accepted: false,
        init() {
            this.accepted = localStorage.getItem('erftkreis_media_cookie_info') === '1';
        },
        accept() {
            localStorage.setItem('erftkreis_media_cookie_info', '1');
            this.accepted = true;
        }
    }"
    x-show="!accepted"
    class="bg-white border-t border-gray-200 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)] mt-4"
    role="dialog"
    aria-label="Cookie-Hinweis"
>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 sm:py-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <p class="text-sm text-gray-700">
                Wir setzen nur technisch notwendige Cookies (z.&nbsp;B. für Anmeldung und Sicherheit). Es werden keine Analyse- oder Werbe-Cookies verwendet.
                <a href="https://www.erftkreis-news.de/datenschutzerklaerung" target="_blank" rel="noopener noreferrer" class="text-[#092E48] font-medium hover:underline">Datenschutzerklärung</a>
            </p>
            <button
                type="button"
                @click="accept()"
                class="shrink-0 px-4 py-2 text-sm font-medium rounded-lg bg-[#092E48] text-white hover:bg-[#0b3858] transition whitespace-nowrap"
            >
                Verstanden
            </button>
        </div>
    </div>
</div>
