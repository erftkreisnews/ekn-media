<div class="w-full sm:w-auto shrink-0 flex flex-col items-stretch sm:items-end gap-1">
    <button
        type="button"
        data-news-split-blocktext
        onclick="if (typeof window.eknSplitNewsBlocktext === 'function') { window.eknSplitNewsBlocktext(); } return false;"
        class="inline-flex items-center justify-center gap-2 min-h-[2.25rem] px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-200 bg-white text-slate-800 hover:bg-slate-50 w-full sm:w-auto"
    >
        Nachricht aufteilen
    </button>
    <p class="text-[11px] text-gray-500 max-w-xs sm:text-right leading-snug">
        Nach KI oder manuell eingefügter <strong class="font-medium text-gray-700">Vorlage</strong> (Zeilen
        <span class="font-mono text-[10px]">Titel:</span> /
        <span class="font-mono text-[10px]">Webtext:</span>, auch
        <span class="font-mono text-[10px]">**Titel:**</span>): automatisch beim Einfügen / Verlassen des Feldes oder per Klick hier (Server).
        <strong class="font-medium text-gray-700">Nur Fließtext:</strong> bei leerem Titel → Aufteilen schlägt den ersten Satz vor.
    </p>
</div>
