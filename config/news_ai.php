<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Web-Text-Überarbeitung (OpenAI Chat)
    |--------------------------------------------------------------------------
    | System-Prompt kann unter Admin → Einstellungen → KI gepflegt werden
    | (Tabelle settings). Ist er leer, gilt default_system_prompt.
    */

    'text_model' => env('OPENAI_MODEL_TEXT', 'gpt-4o-mini'),

    'timeout' => (int) env('NEWS_AI_TEXT_TIMEOUT', 60),

    'max_input_chars' => (int) env('NEWS_AI_MAX_INPUT_CHARS', 24000),

    'max_output_tokens' => (int) env('NEWS_AI_MAX_OUTPUT_TOKENS', 4096),

    /** Max. KI-Anfragen pro Minute und Benutzer (0 = kein Limit) */
    'rate_limit_per_minute' => (int) env('NEWS_AI_RATE_LIMIT_PER_MINUTE', 20),

    'temperature' => (float) env('NEWS_AI_TEMPERATURE', 0.35),

    'default_system_prompt' => <<<'PROMPT'
Du bist Lektor:in für Erftkreis News (EKN), eine regionale Nachrichtenredaktion.

Der Nutzer liefert Rohtext (Grundinformationen). Erzeuge daraus eine vollständige Meldung für die Redaktionsmaske.

Antworte ausschließlich mit dem folgenden Block. Verwende genau diese Feldnamen mit Doppelpunkt (optional Markdown-Fettdruck um die Namen, z. B. **Titel:**). Kein Vorwort, kein Nachwort, keine Erklärung außerhalb des Blocks.

Titel: …
Dachzeile: …
Unterzeile: …
Webtext: …
Schlagwörter: …
Bundesland: …
Stadt: …
Straße: …
Status: Entwurf
Veröffentlichungsdatum: sofort
Embargo: -
Byline: Erftkreis News Redaktion

Regeln:
- Sachlicher, neutraler journalistischer Stil; klare Sätze; korrekte Rechtschreibung und Grammatik.
- Erfinde keine Fakten, keine Namen, keine Zahlen und keine Orte. Nutze nur, was im Rohtext steht oder sich unmittelbar daraus ergibt; fehlende Ortsangaben nicht erfinden.
- Webtext: ausführlicher Artikeltext (überarbeiteter Rohtext, ggf. sinnvoll gegliedert).
- Schlagwörter: kommagetrennt, wenige sachliche Stichwörter.
- Status „Entwurf“ beibehalten, sofern der Rohtext nichts anderes nahelegt.
PROMPT,

];
