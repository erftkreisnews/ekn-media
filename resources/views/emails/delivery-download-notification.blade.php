<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Download</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, Helvetica, sans-serif; font-size:14px; line-height:1.5; color:#111827; background-color:#f6f7f9;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px; background:#ffffff; border-radius:8px; border:1px solid #e5e7eb;">
                <tr>
                    <td style="padding:20px 24px;">
                        <p style="margin:0 0 12px 0; font-size:12px; text-transform:uppercase; letter-spacing:0.05em; color:#6b7280;">Erftkreis News · Download-Hinweis</p>
                        <p style="margin:0 0 8px 0; font-size:12px; color:#6b7280;">Empfänger-Link</p>
                        <p style="margin:0 0 16px 0; font-weight:600;">{{ $delivery->recipient_email }}</p>

                        <p style="margin:0 0 8px 0; font-size:12px; color:#6b7280;">Nachricht</p>
                        <p style="margin:0 0 8px 0; font-weight:600;">{{ $newsItem->title ?? '—' }}</p>
                        <p style="margin:0 0 16px 0; font-size:12px; color:#6b7280;">NewsID {{ $newsItem->id ?? '—' }}</p>

                        <p style="margin:0 0 8px 0; font-size:12px; color:#6b7280;">Heruntergeladen</p>
                        <p style="margin:0 0 4px 0;">
                            <strong>{{ $media->delivery_activity_type_label }}</strong>
                            · {{ $label }}
                        </p>
                        @if($fileName)
                            <p style="margin:0 0 16px 0; font-size:12px; color:#6b7280;">Datei: {{ $fileName }}</p>
                        @endif

                        <p style="margin:0 0 12px 0;">
                            <a href="{{ $adminActivityUrl }}" style="color:#092E48; font-weight:600;">Versand-Aktivität in der Verwaltung öffnen</a>
                        </p>
                        <p style="margin:0; font-size:11px; color:#9ca3af;">Automatische Benachrichtigung – ein Download pro Vorgang (Doppelklicks werden zusammengefasst).</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
