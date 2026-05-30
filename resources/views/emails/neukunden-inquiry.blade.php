<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Neukunden-Anfrage</title>
</head>
<body style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#111827;">
    <p style="font-weight:bold;color:#092E48;">Neue Anfrage: Medienportal (Neukunde)</p>
    <table cellpadding="6" cellspacing="0" border="0" style="border-collapse:collapse;">
        <tr><td><strong>Medienhaus</strong></td><td>{{ $payload['medienhaus'] }}</td></tr>
        <tr><td><strong>Redaktion / Format</strong></td><td>{{ $payload['redaktion'] }}</td></tr>
        @if(!empty($payload['name']))
            <tr><td><strong>Ansprechperson</strong></td><td>{{ $payload['name'] }}</td></tr>
        @endif
        <tr><td><strong>E-Mail</strong></td><td><a href="mailto:{{ $payload['email'] }}">{{ $payload['email'] }}</a></td></tr>
        @if(!empty($payload['phone']))
            <tr><td><strong>Telefon</strong></td><td>{{ $payload['phone'] }}</td></tr>
        @endif
    </table>
    @if(!empty($payload['message']))
        <p style="margin-top:16px;"><strong>Nachricht</strong></p>
        <p style="white-space:pre-wrap;">{{ $payload['message'] }}</p>
    @endif
    <p style="margin-top:20px;font-size:12px;color:#6b7280;">
        Technisch: IP {{ $requestIp }}@if($userAgent)<br>UA {{ \Illuminate\Support\Str::limit($userAgent, 500) }}@endif
    </p>
</body>
</html>
