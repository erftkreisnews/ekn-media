<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veranstaltungs-Vorschläge</title>
</head>
<body style="margin: 0; padding: 16px; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, sans-serif; font-size: 14px; line-height: 1.45; color: #111827;">
    <p style="margin: 0 0 12px;">Es gibt neue automatisch gesammelte Veranstaltungs-Vorschläge (sportlich, Konzerte, Shows u. Ä.). Im Admin-Bereich kannst du sie prüfen und bei Bedarf in die Eventplanung übernehmen.</p>
    <p style="margin: 0 0 20px;">
        <a href="{{ $reviewUrl }}" style="color: #092E48; font-weight: 600;">Zur Eventplanung (Übersicht)</a>
    </p>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">
        <thead>
            <tr>
                <th align="left" style="border-bottom: 1px solid #e5e7eb; padding: 8px 6px;">Datum</th>
                <th align="left" style="border-bottom: 1px solid #e5e7eb; padding: 8px 6px;">Titel</th>
                <th align="left" style="border-bottom: 1px solid #e5e7eb; padding: 8px 6px;">Ort</th>
                <th align="left" style="border-bottom: 1px solid #e5e7eb; padding: 8px 6px;">Kategorie</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($suggestions as $s)
                <tr>
                    <td style="border-bottom: 1px solid #f3f4f6; padding: 8px 6px; white-space: nowrap; vertical-align: top;">
                        @if ($s->starts_at)
                            {{ $s->starts_at->format('d.m.Y') }}
                            @if ($s->ends_at && $s->ends_at->ne($s->starts_at))
                                – {{ $s->ends_at->format('d.m.Y') }}
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    <td style="border-bottom: 1px solid #f3f4f6; padding: 8px 6px; vertical-align: top;">
                        @if ($s->info_url)
                            <a href="{{ $s->info_url }}" style="color: #092E48;">{{ $s->title }}</a>
                        @else
                            {{ $s->title }}
                        @endif
                    </td>
                    <td style="border-bottom: 1px solid #f3f4f6; padding: 8px 6px; vertical-align: top;">
                        {{ $s->venue_name ?: '—' }}
                        @if ($s->venue_city)
                            <div style="color: #6b7280; font-size: 12px;">{{ $s->venue_city }}</div>
                        @endif
                    </td>
                    <td style="border-bottom: 1px solid #f3f4f6; padding: 8px 6px; vertical-align: top;">{{ $s->category ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p style="margin: 0; font-size: 12px; color: #6b7280;">Hinweis: Quellen und Filter sind in der Konfiguration <code>config/event_discovery.php</code> bzw. per Umgebungsvariablen steuerbar.</p>
</body>
</html>
