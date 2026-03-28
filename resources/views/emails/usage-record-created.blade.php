<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nutzungs-Eintrag</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, Helvetica, sans-serif; font-size:14px; line-height:1.5; color:#111827; background-color:#f6f7f9;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; background:#ffffff; border-radius:8px; border:1px solid #e5e7eb;">
                <tr>
                    <td style="padding:20px 24px;">
                        <p style="margin:0 0 12px 0; font-size:12px; text-transform:uppercase; letter-spacing:0.05em; color:#6b7280;">
                            Erftkreis News · Nutzungs-Eintrag erstellt
                        </p>

                        <p style="margin:0 0 16px 0; font-size:12px; color:#6b7280;">
                            Benachrichtigung für neue Nutzung (Admin-Backoffice).
                        </p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin:0 0 16px 0;">
                            <tr>
                                <td style="padding:6px 0; font-size:12px; color:#6b7280; width:180px;">NewsID</td>
                                <td style="padding:6px 0; font-size:12px; color:#111827; font-weight:600;">
                                    {{ $newsItem?->id ?? '–' }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:6px 0; font-size:12px; color:#6b7280;">Nachricht</td>
                                <td style="padding:6px 0; font-size:12px; color:#111827; font-weight:600;">
                                    {{ $newsItem?->title ?? '–' }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:6px 0; font-size:12px; color:#6b7280;">Medienhaus</td>
                                <td style="padding:6px 0; font-size:12px; color:#111827;">
                                    {{ $organization?->name ?? '–' }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:6px 0; font-size:12px; color:#6b7280;">Produkt</td>
                                <td style="padding:6px 0; font-size:12px; color:#111827;">
                                    {{ $product?->name ?? '–' }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:6px 0; font-size:12px; color:#6b7280;">Nutzungsdatum</td>
                                <td style="padding:6px 0; font-size:12px; color:#111827;">
                                    {{ $record?->used_at?->format('d.m.Y') ?? '–' }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:6px 0; font-size:12px; color:#6b7280;">Format</td>
                                <td style="padding:6px 0; font-size:12px; color:#111827;">
                                    {{ $record?->usage_format ?? '–' }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:6px 0; font-size:12px; color:#6b7280;">Rechte</td>
                                <td style="padding:6px 0; font-size:12px; color:#111827;">
                                    {{ $record?->usage_rights ?? '–' }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:6px 0; font-size:12px; color:#6b7280;">Gesamt</td>
                                <td style="padding:6px 0; font-size:12px; color:#111827; font-weight:600;">
                                    {{ number_format((float) ($record?->total_amount ?? 0), 2, ',', '.') }} €
                                </td>
                            </tr>
                        </table>

                        @if(!empty($record?->article_url))
                            <p style="margin:0 0 12px 0; font-size:12px; color:#6b7280;">
                                Artikel-URL:
                            </p>
                            <p style="margin:0 0 16px 0; font-size:12px; font-weight:600;">
                                <a href="{{ $record?->article_url }}" style="color:#092E48; font-weight:600; text-decoration:underline;">
                                    {{ $record?->article_url }}
                                </a>
                            </p>
                        @endif

                        <p style="margin:0 0 12px 0; font-size:12px; color:#6b7280;">
                            Admin-Bearbeitung:
                        </p>
                        <p style="margin:0 0 0 0; font-size:12px;">
                            <a href="{{ $adminEditUrl }}" style="display:inline-block; padding:12px 18px; background:#092E48; color:#ffffff; border-radius:6px; font-weight:700; text-decoration:none;">
                                Nutzungs-Eintrag öffnen
                            </a>
                        </p>

                        <p style="margin:16px 0 0 0; font-size:11px; color:#9ca3af;">
                            Automatische Benachrichtigung
                            @if($creator?->name)
                                {{ ' – erstellt von '.$creator->name }}
                            @endif
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

