{{-- Klarer Ablauf: Rohtext → KI-Vorlagenblock → Nachricht aufteilen ($webTextAiEnabled aus Controller) --}}
<div
    class="rounded-lg border border-sky-100 bg-sky-50/90 px-3 py-2.5 text-xs text-sky-950 leading-relaxed"
    role="note"
>
    <p class="font-semibold text-sky-900 mb-1.5">Ablauf Web-Text</p>
    <ol class="list-decimal list-inside space-y-1 text-sky-900/95">
        <li>Grundinformationen (Rohtext) ins Feld <span class="font-medium">Web-Text</span> einfügen.</li>
        @if (! empty($webTextAiEnabled))
            <li>
                <span class="font-medium">Web-Text mit KI überarbeiten</span> – die KI liefert den Vorlagenblock
                (<span class="font-mono text-[10px]">Titel:</span>,
                <span class="font-mono text-[10px]">Webtext:</span> …). Anschließend werden die Felder
                <span class="font-medium">automatisch</span> übernommen (gleicher Ablauf wie „Nachricht aufteilen“).
            </li>
        @else
            <li>
                Sobald unter Einstellungen ein KI-API-Schlüssel gesetzt ist, erscheint „Web-Text mit KI überarbeiten“. Der Leit-Prompt soll den
                <span class="font-medium">Vorlagenblock</span> mit Zeilen wie
                <span class="font-mono text-[10px]">Titel:</span> /
                <span class="font-mono text-[10px]">Webtext:</span> vorgeben, damit Schritt 3 funktioniert.
            </li>
        @endif
        <li>
            <span class="font-medium">Nachricht aufteilen</span> – nur nötig, wenn du den Vorlagenblock ohne KI einfügst (Einfügen/Verlassen des Feldes oder Klick; Server). Nach KI entfällt der Klick in der Regel.
        </li>
    </ol>
    <p class="mt-2 text-sky-900/80 border-t border-sky-100/80 pt-2">
        Ohne Vorlagenblock: Titel und Felder manuell ausfüllen, oder bei leerem Titel „Aufteilen“ klicken – dann wird der erste Satz als Titel vorgeschlagen.
    </p>
</div>
