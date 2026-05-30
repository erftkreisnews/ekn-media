@php
    $destination = $destination ?? null;
    $run = $run ?? null;
    $wdrTransferNotice = $wdrTransferNotice ?? null;
@endphp

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>FTP-Upload {{ strtoupper($run->status ?? '') }}</title>
</head>
<body style="font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background-color: #f3f4f6; padding: 16px;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 640px; background: #ffffff; border-radius: 12px; padding: 24px; border: 1px solid #e5e7eb;">
                <tr>
                    <td>
                        <h1 style="font-size: 18px; margin: 0 0 12px 0; color: #111827;">
                            FTP-Upload {{ strtoupper($run->status ?? '') }}
                        </h1>
                        <p style="font-size: 14px; color: #4b5563; margin: 0 0 16px 0;">
                            Für das Versandziel <strong>{{ $destination->label }}</strong> wurde ein FTP-/SFTP-Upload abgeschlossen.
                        </p>

                        <table role="presentation" cellspacing="0" cellpadding="0" style="width: 100%; font-size: 13px; color: #374151; margin-bottom: 16px;">
                            <tr>
                                <td style="padding: 4px 0; width: 150px; color: #6b7280;">Run-ID</td>
                                <td style="padding: 4px 0;">#{{ $run->id }}</td>
                            </tr>
                            <tr>
                                <td style="padding: 4px 0; color: #6b7280;">Ziel</td>
                                <td style="padding: 4px 0;">{{ $destination->label }} ({{ $destination->type }})</td>
                            </tr>
                            <tr>
                                <td style="padding: 4px 0; color: #6b7280;">Host</td>
                                <td style="padding: 4px 0;">{{ $destination->getHostOrConfig() }}</td>
                            </tr>
                            <tr>
                                <td style="padding: 4px 0; color: #6b7280;">Status</td>
                                <td style="padding: 4px 0;">
                                    {{ $run->status ?? 'unbekannt' }}
                                    @if($run->message)
                                        – {{ $run->message }}
                                    @endif
                                </td>
                            </tr>
                            @if($run->started_at)
                                <tr>
                                    <td style="padding: 4px 0; color: #6b7280;">Start</td>
                                    <td style="padding: 4px 0;">{{ $run->started_at->format('d.m.Y H:i:s') }}</td>
                                </tr>
                            @endif
                            @if($run->finished_at)
                                <tr>
                                    <td style="padding: 4px 0; color: #6b7280;">Ende</td>
                                    <td style="padding: 4px 0;">{{ $run->finished_at->format('d.m.Y H:i:s') }}</td>
                                </tr>
                            @endif
                        </table>

                        @php
                            // Nur relevante Dateien zählen (z. B. keine übersprungenen Bilder bei WDR-FTP)
                            $total = $run->items()->where('status', '!=', 'skipped')->count();
                            $success = $run->items()->where('status', 'success')->count();
                            $failed = $run->items()->where('status', 'failed')->count();
                        @endphp

                        <p style="font-size: 13px; color: #374151; margin: 0 0 12px 0;">
                            Dateien gesamt: <strong>{{ $total }}</strong>,
                            erfolgreich: <strong style="color: #059669;">{{ $success }}</strong>,
                            fehlgeschlagen: <strong style="color: #b91c1c;">{{ $failed }}</strong>
                        </p>

                        @if(is_array($wdrTransferNotice) && !empty($wdrTransferNotice['news_ids']) && !empty($wdrTransferNotice['paths']))
                            <div style="margin: 12px 0 0 0; padding: 12px; border: 1px solid #bfdbfe; border-radius: 8px; background: #eff6ff; font-size: 13px; color: #1e3a8a;">
                                <p style="margin: 0 0 8px 0; font-weight: 600;">
                                    Hinweis an den WDR-Newsroom:
                                </p>
                                <p style="margin: 0 0 8px 0;">
                                    Das Material zur NewsID {{ implode(', ', $wdrTransferNotice['news_ids']) }}
                                    wurde zusätzlich auf dem externen FTP-Cluster bereitgestellt.
                                </p>
                                <p style="margin: 0 0 6px 0;">
                                    <strong>Server:</strong> {{ $wdrTransferNotice['host'] }}
                                </p>
                                @if(!empty($wdrTransferNotice['moids']))
                                    <p style="margin: 0 0 6px 0;">
                                        <strong>MoID:</strong> {{ implode(', ', $wdrTransferNotice['moids']) }}
                                    </p>
                                @endif
                                <p style="margin: 0 0 6px 0; font-weight: 600;">Ablagepfad:</p>
                                @foreach($wdrTransferNotice['paths'] as $remotePath)
                                    <p style="margin: 0 0 4px 0; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;">
                                        {{ $remotePath }}
                                    </p>
                                @endforeach
                                <p style="margin: 8px 0 0 0;">
                                    Bitte den Pfad an die zuständige TV-Planung weiterleiten.
                                </p>
                            </div>
                        @endif

                        @if($failed > 0)
                            <p style="font-size: 12px; color: #b91c1c; margin: 8px 0 0 0;">
                                Details zu einzelnen Dateien findest du im Admin-Bereich unter dem entsprechenden Versandziel (Upload-Läufe).
                            </p>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

