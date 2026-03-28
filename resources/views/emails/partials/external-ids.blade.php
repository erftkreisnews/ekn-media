@if(isset($destination) && $destination && $destination->include_in_email)
    @php $externalIds = $destination->getExternalIdentifiers(); @endphp
    @if(count($externalIds) > 0)
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; margin-top:12px;">
            @foreach($externalIds as $row)
                @php
                    $lbl = trim((string) ($row['label'] ?? ''));
                    $val = trim((string) ($row['value'] ?? ''));
                    // Internes Feld „Externe ID“ nicht als Lesetext ausgeben
                    $hideLabel = $lbl !== '' && strcasecmp($lbl, 'Externe ID') === 0;
                @endphp
                <tr>
                    <td style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#666666; padding:2px 0;">
                        @if($hideLabel)
                            {{ $val }}
                        @else
                            {{ $lbl }}: {{ $val }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    @endif
@endif
