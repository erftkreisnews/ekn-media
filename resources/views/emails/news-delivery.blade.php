<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Medienangebot</title>
</head>
<body style="Margin:0; padding:0; background-color:#f6f7f9;">
@php
    $publishedAt = $newsItem->published_at ?? $newsItem->updated_at;
    $publishedAtLabel = $publishedAt ? $publishedAt->format('d.m.Y, H:i') : '';
    $locationLabel = trim((string) ($newsItem->location_label ?? ''));
    $infoSeparator = $publishedAtLabel !== '' ? ' | ' : '';
    $locationSuffix = $locationLabel !== '' ? ' – ' . $locationLabel : '';
    $subheadline = trim((string) ($newsItem->subheadline ?? ''));
    $authorCredit = trim((string) ($newsItem->author_credit ?? ''));
    $expiresAtLabel = $delivery->expires_at?->format('d.m.Y, H:i') ?? '';

    $rawBody = (string) ($newsItem->body ?? '');
    $bodyText = trim(preg_replace("/\r\n|\r/", "\n", strip_tags($rawBody)));
    $bodyText = preg_replace("/\n{3,}/", "\n\n", $bodyText);
    $paragraphs = array_values(array_filter(array_map('trim', preg_split("/\n\s*\n/", $bodyText))));

    $teaserImages = $teaserImages ?? [];
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; background-color:#f6f7f9;">
    <tr>
        <td align="center" style="padding:24px 12px;">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; width:600px; max-width:600px; background-color:#ffffff;">
                <tr>
                    <td align="center" style="padding:20px 24px 16px 24px; background-color:#ffffff;">
                        <div style="font-family:Arial, Helvetica, sans-serif; font-size:22px; line-height:28px; font-weight:700; color:#092E48; Margin:0; text-align:center;">
                            Medienangebot von Alexander Franz
                        </div>
                        <div style="font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:20px; color:#6b7280; Margin:8px 0 0 0; text-align:center;">
                            Freier Journalist | Erftkreis News
                        </div>
                        <table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0" style="Margin:12px auto 0 auto;">
                            <tr>
                                <td style="width:60px; height:2px; background-color:#092E48; font-size:0; line-height:0;">&nbsp;</td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:14px 24px 0 24px;">
                        <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#6b7280; Margin:0;">
                            {{ $publishedAtLabel }}{{ $infoSeparator }}NewsID: {{ $newsItem->id }}{{ $locationSuffix }}
                        </div>
                    </td>
                </tr>

                <tr><td style="padding:8px 24px 0 24px; font-size:0; line-height:0;">&nbsp;</td></tr>

                <tr>
                    <td style="padding:0 24px 0 24px;">
                        <div style="font-family:Arial, Helvetica, sans-serif; font-size:18px; line-height:24px; font-weight:bold; color:#111827; Margin:0;">
                            {{ $newsItem->title }}
                        </div>
                    </td>
                </tr>

                @if($subheadline !== '')
                    <tr>
                        <td style="padding:10px 24px 0 24px;">
                            <div style="font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:20px; font-weight:bold; color:#111827; Margin:0;">
                                {{ $subheadline }}
                            </div>
                        </td>
                    </tr>
                @endif

                {{-- Vertragspartner: direkt nach Subheadline (klein + im Stil der Meta-Zeile) --}}
                @php
                    $externalIdentifiers = (isset($destination) && $destination && $destination->include_in_email)
                        ? $destination->getExternalIdentifiers()
                        : [];
                    $orgName = trim((string) ($destination?->organization?->name ?? ''));
                    $isWdr = $orgName !== '' && (stripos($orgName, 'WDR') !== false || stripos($orgName, 'Westdeutscher') !== false);
                    $pvNr = '';
                    foreach ($externalIdentifiers as $r) {
                        $lbl = trim((string) ($r['label'] ?? ''));
                        if ($lbl === 'PV-Nr') {
                            $pvNr = trim((string) ($r['value'] ?? ''));
                            break;
                        }
                    }
                    $externalIdRows = collect($externalIdentifiers)
                        ->filter(fn ($r) => ! empty($r['label']) && ! empty($r['value']))
                        ->values();
                    // Eine Zeile für alle außer WDR: Kennungen hintereinander (ohne Literal „Externe ID“)
                    $nonWdrExternalSegment = '';
                    if (! $isWdr && $externalIdRows->count() > 0) {
                        $segments = [];
                        foreach ($externalIdRows as $row) {
                            $lbl = trim((string) ($row['label'] ?? ''));
                            $val = trim((string) ($row['value'] ?? ''));
                            if ($lbl !== '' && strcasecmp($lbl, 'Externe ID') === 0) {
                                $segments[] = $val;
                            } elseif ($lbl === 'PV-Nr') {
                                $segments[] = 'PV-Nr. ' . $val;
                            } elseif ($lbl !== '') {
                                $sep = str_ends_with($lbl, ':') ? '' : ':';
                                $segments[] = $lbl . $sep . ' ' . $val;
                            } else {
                                $segments[] = $val;
                            }
                        }
                        $nonWdrExternalSegment = implode(' · ', array_values(array_filter($segments, fn ($s) => $s !== '')));
                    }
                @endphp
                @if(isset($destination) && $destination && $destination->include_in_email && $externalIdRows->count() > 0)
                    <tr>
                        <td style="padding:10px 24px 0 24px;">
                            @if($isWdr && $pvNr !== '')
                                <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#6b7280; Margin:0; font-weight:700;">
                                    "Vertragspartner – Nutzungsrechte: Weltrechte - PV-Nr. {{ $pvNr }}"
                                </div>
                            @elseif(!$isWdr && $nonWdrExternalSegment !== '')
                                <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#6b7280; Margin:0; font-weight:700;">
                                    "Vertragspartner | Nutzungsrechte geklärt | {{ $nonWdrExternalSegment }}"
                                </div>
                            @else
                                {{-- WDR ohne PV-Nr.: bisherige mehrzeilige Darstellung --}}
                                <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#6b7280; Margin:0; font-weight:700;">
                                    "Vertragspartner – Nutzung redaktionell möglich"
                                </div>
                                @foreach($externalIdRows as $row)
                                    <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#6b7280; Margin:4px 0 0 0;">
                                        @php
                                            $lbl = trim((string) ($row['label'] ?? ''));
                                            $val = trim((string) ($row['value'] ?? ''));
                                            if ($lbl !== '' && strcasecmp($lbl, 'Externe ID') === 0) {
                                                $lbl = '';
                                            } elseif ($lbl === 'PV-Nr') {
                                                $lbl = 'PV-Nr.';
                                            } elseif ($lbl !== '' && str_ends_with($lbl, ':') === false) {
                                                $lbl = $lbl . ':';
                                            }
                                        @endphp
                                        @if($lbl === '')
                                            {{ $val }}
                                        @else
                                            {{ $lbl }} {{ $val }}
                                        @endif
                                    </div>
                                @endforeach
                            @endif
                        </td>
                    </tr>
                @endif

                <tr><td style="padding:8px 24px 0 24px; font-size:0; line-height:0;">&nbsp;</td></tr>

                <tr>
                    <td style="padding:16px 24px 0 24px;">
                        <div style="font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:22px; color:#111827;">
                            @if(count($paragraphs) > 0)
                                @foreach($paragraphs as $p)
                                    <p style="Margin:0 0 12px 0;">{!! nl2br(e($p)) !!}</p>
                                @endforeach
                            @else
                                <p style="Margin:0 0 12px 0;">&nbsp;</p>
                            @endif
                        </div>
                    </td>
                </tr>

                @if(count($teaserImages) > 0)
                    {{-- Regel Angebotsmail: Hoch- und Querformate werden nicht gemischt. Pro Format eigene Reihen (4 Bilder pro Reihe). Reihenfolge: Quer → Quadrat → Hoch. --}}
                    @php
                        $imagesPerRow = 4;
                        $maxPerOrientation = 8;
                        $order = ['landscape', 'square', 'portrait'];
                        $byOrientation = collect($teaserImages)->groupBy('orientation');
                        $groupedRows = collect();
                        foreach ($order as $orient) {
                            $items = ($byOrientation->get($orient) ?? collect())->take($maxPerOrientation);
                            $groupedRows = $groupedRows->merge($items->chunk($imagesPerRow)->values());
                        }
                    @endphp
                    <tr>
                        <td style="padding:16px 24px 0 24px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                                <tr>
                                    <td align="center" style="padding:0;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse; margin:0 auto; max-width:560px;">
                                            @foreach($groupedRows as $row)
                                                <tr>
                                                    @foreach($row as $img)
                                                        <td width="25%" style="width:25%; padding:6px; vertical-align:top; box-sizing:border-box;">
                                                            <a href="{{ $deliveryUrl }}" style="text-decoration:none; display:block;">
                                                                <img src="{{ $img['url'] }}" alt="{{ e($img['alt'] ?? '') }}" width="128" style="width:100%; max-width:128px; height:auto; display:block; border-radius:8px; border:1px solid #e5e7eb; -ms-interpolation-mode:bicubic;">
                                                            </a>
                                                            @if(!empty($img['id']) || !empty($img['caption']))
                                                                <div style="font-family:Arial, Helvetica, sans-serif; font-size:11px; line-height:16px; color:#374151; Margin:6px 0 0 0;">
                                                                    @if(!empty($img['id']))
                                                                        <div style="color:#6b7280; font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;">
                                                                            {{ $img['id'] }}
                                                                        </div>
                                                                    @endif
                                                                    @if(!empty($img['caption']))
                                                                        <div>
                                                                            {{ $img['caption'] }}
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                    @for($i = $row->count(); $i < $imagesPerRow; $i++)
                                                        <td width="25%" style="width:25%; padding:6px;">&nbsp;</td>
                                                    @endfor
                                                </tr>
                                                <tr>
                                                    <td colspan="{{ $imagesPerRow }}" style="height:12px; font-size:0; line-height:0;">&nbsp;</td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endif

                <tr>
                    <td style="padding:16px 24px 0 24px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                            <tr>
                                <td bgcolor="#092E48" style="border-radius:4px; background-color:#092E48;">
                                    <a href="{{ $deliveryUrl }}"
                                       style="display:inline-block; padding:12px 18px; font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:16px; font-weight:bold; color:#ffffff; text-decoration:none;">
                                        Medienpaket öffnen
                                    </a>
                                </td>
                            </tr>
                        </table>
                        <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#6b7280; Margin:10px 0 0 0;">
                            Download-Link gültig bis {{ $expiresAtLabel }}
                        </div>
                        <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#6b7280; Margin:6px 0 0 0;">
                            Fallback-Link: <a href="{{ $deliveryUrl }}" style="color:#092E48; text-decoration:underline;">{{ $deliveryUrl }}</a>
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:16px 24px 0 24px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; border:1px solid #e5e7eb;">
                            <tr>
                                <td style="padding:10px 12px; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#111827; font-weight:bold; background-color:#f9fafb;">
                                    Mediencount
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:10px 12px; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#111827;">
                                    Fotos: {{ $imagesCount ?? 0 }} &nbsp;|&nbsp; Videos: {{ $videosCount ?? 0 }} &nbsp;|&nbsp; Audios: {{ $audiosCount ?? 0 }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:16px 24px 0 24px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; border-top:1px solid #e5e7eb;">
                            <tr>
                                <td style="padding:14px 0 0 0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#6b7280;">
                                    <strong style="color:#111827;">Kontakt: Alexander Franz</strong><br>
                                    Telefon: +49 (0) 176 59593226
                                    @if(config('mail.contact_address'))
                                        <br>E-Mail: <a href="mailto:{{ config('mail.contact_address') }}" style="color:#092E48; text-decoration:underline;">{{ config('mail.contact_address') }}</a>
                                    @endif
                                    @if($authorCredit !== '')
                                        <br><span style="color:#374151;">Quelle: {{ $authorCredit }}</span>
                                    @endif
                                    <br><span style="color:#374151;">Die Bilder sind honorarpflichtig zzgl. 7 % MwSt. Alle Rechte vorbehalten.</span>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:10px 0 0 0;">
                                    @includeWhen(isset($destination) && $destination && $destination->include_in_email, 'emails.partials.external-ids', ['destination' => $destination ?? null])
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:18px 24px 22px 24px; font-family:Arial, Helvetica, sans-serif; font-size:11px; line-height:16px; color:#9ca3af;">
                        Erftkreis News · Newsdesk
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
