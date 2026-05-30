<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sofortversand Bild</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <p style="font-size:18px;line-height:24px;font-weight:700;color:#092E48;margin:0 0 10px 0;">
        Medienangebot von Alexander Franz
    </p>
    <p style="font-size:14px;line-height:20px;color:#6b7280;margin:0 0 16px 0;">
        Freier Journalist | Erftkreis News
    </p>

    <p>Hallo,</p>

    <p>hier kommt ein Bild im Sofortversand.</p>

    <p>
        @if(!empty($newsItem))
            <strong>Meldung:</strong> {{ $newsItem->title }}<br>
        @else
            <strong>Quelle:</strong> Bild-Mediathek (unabhängiger Versand)<br>
        @endif
        <strong>Bild-ID:</strong> {{ $medium->id }}<br>
        <strong>Datei:</strong> {{ $medium->original_name ?: ('medium-'.$medium->id) }}
    </p>

    @if (!empty($editorNote))
        <p><strong>Info aus der Redaktion:</strong><br>{!! nl2br(e($editorNote)) !!}</p>
    @endif

    <p>
        Das Bild ist im Original direkt als Anhang beigefügt.
    </p>

    <p>Viele Grüße<br>{{ config('mail.from.name', 'Redaktion') }}</p>
</body>
</html>
