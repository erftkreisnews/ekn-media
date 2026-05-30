<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Willkommen bei EKN Media</title>
</head>
<body style="font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background-color: #f3f4f6; padding: 16px;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 640px; background: #ffffff; border-radius: 12px; padding: 24px; border: 1px solid #e5e7eb;">
                <tr>
                    <td>
                        <h1 style="font-size: 20px; margin: 0 0 12px 0; color: #111827;">
                            Willkommen bei EKN Media
                        </h1>

                        <p style="font-size: 14px; color: #4b5563; margin: 0 0 16px 0;">
                            Hallo {{ $user->name }},
                        </p>

                        <p style="font-size: 14px; color: #4b5563; margin: 0 0 16px 0;">
                            Ihr Benutzerkonto wurde erfolgreich angelegt.
                            Bitte setzen Sie jetzt aus Sicherheitsgründen Ihr Passwort neu.
                        </p>

                        <table role="presentation" cellspacing="0" cellpadding="0" style="margin: 0 0 16px 0;">
                            <tr>
                                <td style="background-color:#092E48; border-radius:6px;">
                                    <a href="{{ $resetUrl }}" style="display:inline-block; color:#ffffff; text-decoration:none; font-size:14px; font-weight:600; padding:10px 16px;">
                                        Passwort jetzt zurücksetzen
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="font-size: 13px; color: #6b7280; margin: 0 0 8px 0;">
                            Falls der Button nicht funktioniert, verwenden Sie bitte diesen Link:
                        </p>
                        <p style="font-size: 13px; margin: 0 0 16px 0;">
                            <a href="{{ $resetUrl }}" style="color:#092E48; word-break: break-all;">{{ $resetUrl }}</a>
                        </p>

                        <p style="font-size: 14px; color: #4b5563; margin: 0;">
                            Viele Grüße<br>
                            Erftkreis News
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
