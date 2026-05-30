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
    $locationLabelRaw = trim((string) ($newsItem->location_label ?? ''));
    $locationParts = preg_split('/\s*,\s*/u', $locationLabelRaw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $locationParts = array_values(array_filter($locationParts, function (string $part): bool {
        $normalized = mb_strtolower(trim($part));
        return ! in_array($normalized, ['nicht bekannt', 'unbekannt', 'k. a.', 'k.a.', 'n/a', 'na'], true);
    }));
    $locationLabel = trim(implode(', ', $locationParts));
    $mapsSearchUrl = $newsItem->maps_search_url;
    $infoSeparator = $publishedAtLabel !== '' ? ' | ' : '';
    $locationSuffix = $locationLabel !== '' ? ' – ' . $locationLabel : '';
    $subheadline = trim((string) ($newsItem->subheadline ?? ''));
    $teaser = trim((string) ($newsItem->teaser ?? ''));
    $authorCredit = trim((string) ($newsItem->author_credit ?? ''));
    $expiresAtLabel = $delivery->expires_at?->format('d.m.Y, H:i') ?? '';
    $eventAtLabel = $newsItem->event_at?->format('d.m.Y, H:i') ?? '';
    $sourceType = trim((string) ($newsItem->source_type ?? ''));
    $sourceName = trim((string) ($newsItem->source_name ?? ''));
    $verificationStatus = trim((string) ($newsItem->verification_status ?? ''));
    $sourceTypeLabelMap = [
        'official' => 'Offizielle Stelle',
        'reporter' => 'Reporter vor Ort',
        'agency' => 'Agentur',
        'witness' => 'Zeuge/Hinweisgeber',
        'other' => 'Sonstige',
    ];
    $verificationLabelMap = [
        'unverified' => 'Unbestätigt',
        'verified' => 'Bestätigt',
        'official' => 'Offiziell bestätigt',
    ];
    $sourceTypeLabel = $sourceTypeLabelMap[$sourceType] ?? $sourceType;
    $verificationLabel = $verificationLabelMap[$verificationStatus] ?? $verificationStatus;

    $rawBody = (string) ($newsItem->body ?? '');
    $bodyText = trim(preg_replace("/\r\n|\r/", "\n", strip_tags($rawBody)));
    $bodyText = preg_replace("/\n{3,}/", "\n\n", $bodyText);
    $paragraphs = array_values(array_filter(array_map('trim', preg_split("/\n\s*\n/", $bodyText))));
    $firstParagraph = $paragraphs[0] ?? '';
    $normalizeForCompare = static function (string $value): string {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';
        return trim($value);
    };
    $teaserNormalized = $normalizeForCompare($teaser);
    $subheadlineNormalized = $normalizeForCompare($subheadline);
    $firstParagraphNormalized = $normalizeForCompare($firstParagraph);
    $hasBodyParagraphs = count($paragraphs) > 0;
    $showTeaser = ! $hasBodyParagraphs
        && $teaser !== ''
        && $teaserNormalized !== ''
        && $teaserNormalized !== $firstParagraphNormalized
        && ! str_contains($firstParagraphNormalized, $teaserNormalized);
    $showSubheadline = ! $hasBodyParagraphs
        && $subheadline !== ''
        && $subheadlineNormalized !== ''
        && $subheadlineNormalized !== $firstParagraphNormalized
        && ! str_contains($firstParagraphNormalized, $subheadlineNormalized);
    if ($showSubheadline && $teaserNormalized !== '' && $subheadlineNormalized === $teaserNormalized) {
        $showSubheadline = false;
    }
    $headlineTimeLabel = $eventAtLabel !== '' ? 'Alarmzeit Einsatzkräfte' : 'Meldungszeit';
    $headlineTimeValue = $eventAtLabel !== '' ? $eventAtLabel : $publishedAtLabel;
    $headlineTimeSeparator = $headlineTimeValue !== '' ? ' | ' : '';
    $showEventAt = false;

    $teaserImages = $teaserImages ?? [];
    // PATCH: add statements and updates support for news items
    $mailStatements = $mailStatements ?? collect();
    $mailUpdates = $mailUpdates ?? collect();
    $isUpdateDelivery = (bool) ($isUpdateDelivery ?? false);
    $updateHints = $updateHints ?? [];
    $deliveryPhase = trim((string) ($deliveryPhase ?? ''));
    $updateNote = trim((string) ($updateNote ?? ''));
    $updateReferenceNewsId = trim((string) ($updateReferenceNewsId ?? $newsItem->display_news_id));
    $updateMailIsPrimary = (bool) ($updateMailIsPrimary ?? false);
    $updateScanLine = trim((string) ($updateScanLine ?? ''));
    $updateMediaLine = trim((string) ($updateMediaLine ?? ''));
    $newImagesCount = (int) ($newImagesCount ?? 0);
    $newVideosCount = (int) ($newVideosCount ?? 0);
    $newAudiosCount = (int) ($newAudiosCount ?? 0);
    $hasPreviousVisibleMedia = (bool) ($hasPreviousVisibleMedia ?? false);
    $totalVisibleMedia = (int) (($imagesCount ?? 0) + ($videosCount ?? 0) + ($audiosCount ?? 0));
    $plannedMediaNotice = (bool) ($newsItem->planned_video_upload ?? false) && $totalVisibleMedia === 0;

    // Breaking-News/Vertraulichkeit: eigener roter Balken im Kopf; hier nur weitere Merkmale
    $newsFeatureLabels = [];
    if ($newsItem->is_confidential) {
        $newsFeatureLabels[] = 'Vertraulich';
    }
    if ($newsItem->planned_video_upload) {
        $newsFeatureLabels[] = 'Video-Upload geplant';
    }
    if ($newsItem->liveu_on_site) {
        $newsFeatureLabels[] = 'LiveU vor Ort';
    }

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

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; background-color:#f6f7f9;">
    <tr>
        <td align="center" style="padding:24px 12px;">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; width:600px; max-width:600px; background-color:#ffffff;">
                <tr>
                    <td align="center" style="padding:20px 24px @if($newsItem->is_breaking) 8px @else 16px @endif 24px; background-color:#ffffff;">
                        <div style="font-family:Arial, Helvetica, sans-serif; font-size:22px; line-height:28px; font-weight:700; color:#092E48; Margin:0; text-align:center;">
                            Medienangebot von Alexander Franz
                        </div>
                        <div style="font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:20px; color:#6b7280; Margin:8px 0 0 0; text-align:center;">
                            Freier Journalist | Erftkreis News
                        </div>
                        @if(! $newsItem->is_breaking)
                            <table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0" style="Margin:12px auto 0 auto;">
                                <tr>
                                    <td style="width:60px; height:2px; background-color:#092E48; font-size:0; line-height:0;">&nbsp;</td>
                                </tr>
                            </table>
                        @endif
                    </td>
                </tr>
                @if($newsItem->is_breaking)
                    <tr>
                        <td style="padding:0; background-color:#ffffff;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                                <tr>
                                    <td align="center" style="padding:14px 24px; background-color:#dc2626;">
                                        <div style="font-family:Arial, Helvetica, sans-serif; font-size:17px; line-height:22px; font-weight:700; color:#ffffff; Margin:0; text-align:center;">
                                            Breaking-News
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:12px 24px 16px 24px; background-color:#ffffff;">
                            <table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0" style="Margin:0 auto 0 auto;">
                                <tr>
                                    <td style="width:60px; height:2px; background-color:#092E48; font-size:0; line-height:0;">&nbsp;</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endif
                @if($newsItem->is_confidential)
                    <tr>
                        <td style="padding:0; background-color:#ffffff;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                                <tr>
                                    <td align="center" style="padding:14px 24px; background-color:#b91c1c;">
                                        <div style="font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:22px; font-weight:700; color:#ffffff; Margin:0; text-align:center;">
                                            VERTRAULICHER HINWEIS
                                        </div>
                                        <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#fee2e2; Margin:6px 0 0 0; text-align:center;">
                                            Diese Informationen sind streng vertraulich und nur für den internen Redaktionszweck bestimmt.
                                            Eine Veröffentlichung vor Freigabe durch die zuständigen Behörden ist ausdrücklich untersagt.
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:12px 24px 16px 24px; background-color:#ffffff;">
                            <table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0" style="Margin:0 auto 0 auto;">
                                <tr>
                                    <td style="width:60px; height:2px; background-color:#092E48; font-size:0; line-height:0;">&nbsp;</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endif

                @if($updateMailIsPrimary)
                    {{-- Update-Mail: Kontext wie Erstmeldung, Inhalt ohne Meta-Wiederholungen --}}
                    <tr>
                        <td style="padding:14px 24px 0 24px;">
                            <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#6b7280; Margin:0; text-align:center;">
                                @if($headlineTimeValue !== '')
                                    {{ $headlineTimeLabel }}: {{ $headlineTimeValue }}{{ $headlineTimeSeparator }}
                                @endif
                                NewsID: {{ $newsItem->display_news_id }}
                            </div>
                            @if($locationLabel !== '')
                                <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#6b7280; Margin:8px 0 0 0; text-align:center;">
                                    @if($mapsSearchUrl)
                                        <a href="{{ $mapsSearchUrl }}" style="color:#092E48; text-decoration:underline;" target="_blank" rel="noopener noreferrer">{{ $locationLabel }}</a>
                                    @else
                                        {{ $locationLabel }}
                                    @endif
                                </div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:10px 24px 0 24px;">
                            <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#4338ca; Margin:0; text-align:center;">
                                <strong>Update zur Erstmeldung (NewsID {{ $updateReferenceNewsId }})</strong>
                                @if($updateMediaLine !== '')
                                    · {{ $updateMediaLine }}
                                @endif
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:12px 24px 0 24px;">
                            <div style="font-family:Arial, Helvetica, sans-serif; font-size:20px; line-height:26px; font-weight:bold; color:#111827; Margin:0;">
                                {{ $newsItem->title }}
                            </div>
                        </td>
                    </tr>
                    @foreach($mailUpdates as $update)
                        <tr>
                            <td style="padding:14px 24px 0 24px;">
                                <div style="font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:18px; font-weight:bold; color:#312e81; Margin:0 0 8px 0;">
                                    {{ $update->type_label ?? $update->type }}
                                    @if(!empty($update->title)) · {{ $update->title }} @endif
                                </div>
                                @if($update->happened_at || filled($update->display_source_line))
                                    <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#6b7280; Margin:0 0 4px 0;">
                                        @if($update->happened_at)
                                            {{ $update->happened_at->format('d.m.Y, H:i') }} Uhr
                                        @endif
                                        @if($update->happened_at && filled($update->display_source_line))
                                            ·
                                        @endif
                                        @if(filled($update->display_source_line))
                                            Quelle: {{ $update->display_source_line }}
                                        @endif
                                    </div>
                                @endif
                                <div style="font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:24px; color:#111827; Margin:0; padding:0;">
                                    {!! nl2br(e(trim((string) $update->body))) !!}
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    @foreach($mailStatements as $statement)
                        <tr>
                            <td style="padding:12px 24px 0 24px;">
                                <div style="font-family:Arial, Helvetica, sans-serif; font-size:11px; line-height:16px; font-weight:bold; color:#1e40af; text-transform:uppercase; Margin:0 0 6px 0;">O-Ton</div>
                                <div style="font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:23px; color:#111827; Margin:0;">
                                    @if(!empty($statement->summary))
                                        {{ $statement->summary }}
                                    @else
                                        {!! nl2br(e(\Illuminate\Support\Str::limit((string) $statement->transcript, 900, ' …'))) !!}
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    <tr>
                        <td style="padding:14px 24px 0 24px;">
                            <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#6b7280; Margin:0;">
                                <strong style="color:#374151;">Bezug zur Erstmeldung:</strong>
                                {{ $newsItem->title }} (NewsID {{ $updateReferenceNewsId }})
                                · <a href="{{ $deliveryUrl }}" style="color:#092E48; text-decoration:underline;">Erstmeldung und Archiv im Medienpaket</a>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 24px 0 24px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                                <tr>
                                    <td bgcolor="#092E48" style="border-radius:4px; background-color:#092E48;">
                                        <a href="{{ $deliveryUrl }}"
                                           style="display:inline-block; padding:12px 18px; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:18px; font-weight:bold; color:#ffffff; text-decoration:none;">
                                            @if($newImagesCount + $newVideosCount + $newAudiosCount > 0)
                                                Neues Material laden
                                            @else
                                                Medienpaket öffnen
                                            @endif
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            @if($expiresAtLabel !== '')
                                <div style="font-family:Arial, Helvetica, sans-serif; font-size:11px; line-height:16px; color:#9ca3af; Margin:8px 0 0 0;">
                                    Link gültig bis {{ $expiresAtLabel }}
                                </div>
                            @endif
                        </td>
                    </tr>
                @else
                <tr>
                    <td style="padding:14px 24px 0 24px;">
                        <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#6b7280; Margin:0; text-align:center;">
                            @if($headlineTimeValue !== '')
                                {{ $headlineTimeLabel }}: {{ $headlineTimeValue }}{{ $headlineTimeSeparator }}
                            @endif
                            NewsID: {{ $newsItem->display_news_id }}
                        </div>
                        @if($locationLabel !== '')
                            <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#6b7280; Margin:8px 0 0 0; text-align:center;">
                                @if($mapsSearchUrl)
                                    <a href="{{ $mapsSearchUrl }}" style="color:#092E48; text-decoration:underline;" target="_blank" rel="noopener noreferrer">{{ $locationLabel }}</a>
                                @else
                                    {{ $locationLabel }}
                                @endif
                            </div>
                        @endif
                    </td>
                </tr>
                @if($showEventAt)
                    <tr>
                        <td style="padding:6px 24px 0 24px;">
                            <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#4b5563; Margin:0;">
                                Ereigniszeit: {{ $eventAtLabel }}
                            </div>
                        </td>
                    </tr>
                @endif

                @if(($isUpdateDelivery || in_array($deliveryPhase, ['ABSCHLUSS', 'ABSCHLUSSMELDUNG'], true)) && $updateScanLine !== '')
                    <tr>
                        <td style="padding:8px 24px 0 24px;">
                            <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; font-weight:bold; color:#4338ca; Margin:0;">
                                {{ $updateScanLine }}
                            </div>
                        </td>
                    </tr>
                @elseif($isUpdateDelivery || in_array($deliveryPhase, ['ABSCHLUSS', 'ABSCHLUSSMELDUNG'], true))
                    <tr>
                        <td style="padding:8px 24px 0 24px;">
                            <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; font-weight:bold; color:#4338ca; Margin:0;">
                                @if(!empty($updateHints))
                                    {{ implode(' · ', $updateHints) }} · ID {{ $updateReferenceNewsId }}
                                @else
                                    UPDATE · ID {{ $updateReferenceNewsId }}
                                @endif
                            </div>
                        </td>
                    </tr>
                @endif
                @endif

                @if($plannedMediaNotice)
                    <tr>
                        <td style="padding:10px 24px 0 24px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; border:1px solid #f59e0b; border-radius:8px;">
                                <tr>
                                    <td style="padding:10px 12px; background-color:#fffbeb; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#92400e;">
                                        <div style="font-weight:bold;">Hinweis zur Materialplanung</div>
                                        <div style="margin-top:4px;">Zu dieser Meldung ist ein Video-Upload geplant. Bild-/Videomaterial folgt in einem separaten Update zu dieser Meldung (NewsID {{ $newsItem->display_news_id }}).</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endif

                <tr><td style="padding:16px 24px 0 24px; font-size:0; line-height:0;">&nbsp;</td></tr>

                @unless($updateMailIsPrimary)
                <tr>
                    <td style="padding:0 24px 0 24px;">
                        <div style="font-family:Arial, Helvetica, sans-serif; font-size:18px; line-height:24px; font-weight:bold; color:#111827; Margin:0;">
                            {{ $newsItem->title }}
                        </div>
                    </td>
                </tr>

                @if($showTeaser)
                    <tr>
                        <td style="padding:8px 24px 0 24px;">
                            <div style="font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:19px; color:#4b5563; Margin:0;">
                                {{ $teaser }}
                            </div>
                        </td>
                    </tr>
                @endif

                @if($showSubheadline)
                    <tr>
                        <td style="padding:10px 24px 0 24px;">
                            <div style="font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:20px; font-weight:bold; color:#111827; Margin:0;">
                                {{ $subheadline }}
                            </div>
                        </td>
                    </tr>
                @endif

                @if($sourceTypeLabel !== '' || $sourceName !== '' || $verificationLabel !== '')
                    <tr>
                        <td style="padding:10px 24px 0 24px;">
                            <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#4b5563; Margin:0;">
                                <strong style="color:#374151;">Quelle/Status:</strong>
                                @if($sourceTypeLabel !== '')
                                    {{ $sourceTypeLabel }}
                                @endif
                                @if($sourceName !== '')
                                    @if($sourceTypeLabel !== '')
                                        ·
                                    @endif
                                    {{ $sourceName }}
                                @endif
                                @if($verificationLabel !== '')
                                    @if($sourceTypeLabel !== '' || $sourceName !== '')
                                        ·
                                    @endif
                                    {{ $verificationLabel }}
                                @endif
                            </div>
                        </td>
                    </tr>
                @endif

                @if(count($newsFeatureLabels) > 0)
                    <tr>
                        <td style="padding:10px 24px 0 24px;">
                            <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#1e3a5f; Margin:0;">
                                <span style="font-weight:700;">Besondere Merkmale:</span>
                                {{ implode(' · ', $newsFeatureLabels) }}
                            </div>
                        </td>
                    </tr>
                @endif

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
                @endunless

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
                                                                @php
                                                                    $embed = $img['embed'] ?? null;
                                                                    $imgSrc = \is_array($embed) && isset($embed['data'], $embed['name'])
                                                                        ? $message->embedData($embed['data'], $embed['name'], $embed['mime'] ?? null)
                                                                        : $img['url'];
                                                                    $tw = (int) ($img['thumb_width'] ?? 128);
                                                                    $tw = $tw > 0 ? $tw : 128;
                                                                    $th = isset($img['thumb_height']) && (int) $img['thumb_height'] > 0 ? (int) $img['thumb_height'] : null;
                                                                @endphp
                                                                {{-- CID-Einbettung: Outlook zeigt eingebettete Bilder ohne Blockade externer Inhalte; Fallback URL wenn Einbetten scheitert --}}
                                                                <img src="{{ $imgSrc }}" alt="{{ e($img['alt'] ?? '') }}" width="{{ $tw }}" @if($th !== null) height="{{ $th }}" @endif border="0" style="display:block; width:{{ $tw }}px; max-width:100%; @if($th !== null) height:{{ $th }}px; @else height:auto; @endif border:1px solid #e5e7eb; outline:none; text-decoration:none; border-radius:8px; -ms-interpolation-mode:bicubic;">
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

                {{-- Material & Zugang (Update mit Inhalt: CTA bereits oben) --}}
                @unless($updateMailIsPrimary)
                <tr>
                    <td style="padding:14px 24px 0 24px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; border:1px solid #d1d5db; background-color:#f9fafb;">
                            <tr>
                                <td style="padding:12px 14px 10px 14px; font-family:Arial, Helvetica, sans-serif; font-size:11px; line-height:16px; color:#6b7280; text-transform:uppercase; letter-spacing:0.04em; font-weight:bold;">
                                    Material &amp; Zugang
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:0 14px 10px 14px; font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:22px; color:#111827; font-weight:bold;">
                                    @if($isUpdateDelivery)
                                        Neu: {{ $newImagesCount }} Fotos · {{ $newVideosCount }} Videos · {{ $newAudiosCount }} Audios
                                    @else
                                        Verfügbar: {{ (int) ($imagesCount ?? 0) }} Fotos · {{ (int) ($videosCount ?? 0) }} Videos · {{ (int) ($audiosCount ?? 0) }} Audios
                                    @endif
                                </td>
                            </tr>
                            @if($isUpdateDelivery && ($imagesCount ?? 0) > $newImagesCount)
                                <tr>
                                    <td style="padding:0 14px 10px 14px; font-family:Arial, Helvetica, sans-serif; font-size:11px; line-height:16px; color:#9ca3af;">
                                        Gesamt ID {{ $newsItem->display_news_id }}: {{ (int) ($imagesCount ?? 0) }} Fotos · {{ (int) ($videosCount ?? 0) }} Videos
                                    </td>
                                </tr>
                            @endif
                            @if($expiresAtLabel !== '')
                                <tr>
                                    <td style="padding:0 14px 12px 14px; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#4b5563;">
                                        Link gültig bis {{ $expiresAtLabel }}
                                    </td>
                                </tr>
                            @endif
                            <tr>
                                <td style="padding:0 14px 14px 14px;">
                                    <div style="font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:20px; color:#111827; Margin:0 0 12px 0;">
                                        <strong>So laden Sie das Material herunter:</strong><br>
                                        1. Auf <strong>Medien jetzt öffnen und herunterladen</strong> klicken.<br>
                                        2. Auf der Seite die gewünschten Dateien auswählen.<br>
                                        3. Download starten.
                                    </div>
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                                        <tr>
                                            <td bgcolor="#092E48" style="border-radius:4px; background-color:#092E48;">
                                                <a href="{{ $deliveryUrl }}"
                                                   style="display:inline-block; padding:13px 20px; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:18px; font-weight:bold; color:#ffffff; text-decoration:none;">
                                                    Medien jetzt öffnen und herunterladen
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                    <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#4b5563; Margin:10px 0 0 0;">
                                        Kein Login erforderlich. Der Link ist persönlich und direkt nutzbar.
                                    </div>
                                    <div style="font-family:Arial, Helvetica, sans-serif; font-size:11px; line-height:16px; color:#9ca3af; Margin:8px 0 0 0;">
                                        Falls der Button nicht funktioniert:
                                        <a href="{{ $deliveryUrl }}" style="color:#6b7280; text-decoration:underline;">Direktlink öffnen</a>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                @endunless

                {{-- O-Töne / Einsatz-Updates (Erstmeldung oder Update ohne primären Update-Text) --}}
                @if(! $updateMailIsPrimary && $mailStatements->count() > 0)
                    <tr>
                        <td style="padding:12px 24px 0 24px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; border:1px solid #dbeafe; border-radius:8px;">
                                <tr>
                                    <td style="padding:10px 12px; background-color:#eff6ff; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#1e3a8a; font-weight:bold;">
                                        O-Ton / Statements
                                    </td>
                                </tr>
                                @foreach($mailStatements as $statement)
                                    <tr>
                                        <td style="padding:10px 12px; border-top:1px solid #e5e7eb; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#111827;">
                                            <div style="font-weight:bold;">
                                                {{ $statement->source_type_label ?? $statement->source_type }}
                                                @if(!empty($statement->source_label)) · {{ $statement->source_label }} @endif
                                                @if($statement->received_at) · {{ $statement->received_at->format('d.m.Y H:i') }} @endif
                                            </div>
                                        <div style="margin-top:4px;">
                                                @if(!empty($statement->summary))
                                                    {{ $statement->summary }}
                                                @else
                                                {{ \Illuminate\Support\Str::limit((string) $statement->transcript, 520, ' …') }}
                                                @endif
                                            </div>
                                        <div style="margin-top:6px;">
                                            <a href="{{ $deliveryUrl }}#updates" style="color:#092E48; text-decoration:underline; font-size:12px;">Volltext im Medienpaket</a>
                                        </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                @endif

                @if(! $updateMailIsPrimary && $mailUpdates->count() > 0)
                    <tr>
                        <td style="padding:12px 24px 0 24px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; border:1px solid #e5e7eb; border-radius:8px;">
                                <tr>
                                    <td style="padding:10px 12px; background-color:#f9fafb; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#111827; font-weight:bold;">
                                        Einsatz-Updates
                                    </td>
                                </tr>
                                @foreach($mailUpdates as $update)
                                    <tr>
                                        <td style="padding:10px 12px; border-top:1px solid #e5e7eb; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#111827;">
                                            <div style="font-weight:bold;">
                                                {{ $update->type_label ?? $update->type }}
                                                @if(!empty($update->title)) · {{ $update->title }} @endif
                                                @if($update->happened_at) · {{ $update->happened_at->format('d.m.Y H:i') }} @endif
                                            </div>
                                            @if(filled($update->display_source_line))
                                                <div style="color:#4b5563; margin-top:2px;">
                                                    Quelle: {{ $update->display_source_line }}
                                                </div>
                                            @endif
                                            <div style="margin-top:4px;">{{ \Illuminate\Support\Str::limit((string) $update->body, 520, ' …') }}</div>
                                            <div style="margin-top:6px;">
                                                <a href="{{ $deliveryUrl }}#updates" style="color:#092E48; text-decoration:underline; font-size:12px;">Volltext im Medienpaket</a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                @endif

                {{-- Vertragspartner / Nutzung: sekundär, vor Kontaktmodul --}}
                @if(isset($destination) && $destination && $destination->include_in_email && $externalIdRows->count() > 0)
                    <tr>
                        <td style="padding:18px 24px 0 24px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; border-top:1px solid #e5e7eb;">
                                <tr>
                                    <td style="padding:14px 0 0 0;">
                                        <div style="font-family:Arial, Helvetica, sans-serif; font-size:11px; line-height:16px; color:#9ca3af; text-transform:uppercase; letter-spacing:0.03em; font-weight:bold; Margin:0 0 8px 0;">
                                            Rechte &amp; Vertragspartner
                                        </div>
                                        @if($isWdr && $pvNr !== '')
                                            <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#6b7280; Margin:0;">
                                                "Vertragspartner – Nutzungsrechte: Weltrechte - PV-Nr. {{ $pvNr }}"
                                            </div>
                                        @elseif(!$isWdr && $nonWdrExternalSegment !== '')
                                            <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#6b7280; Margin:0;">
                                                "Vertragspartner | Nutzungsrechte geklärt | {{ $nonWdrExternalSegment }}"
                                            </div>
                                        @else
                                            <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#6b7280; Margin:0;">
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
                                        <div style="Margin:10px 0 0 0;">
                                            @includeWhen(isset($destination) && $destination && $destination->include_in_email, 'emails.partials.external-ids', ['destination' => $destination ?? null])
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @elseif(isset($destination) && $destination && $destination->include_in_email)
                    <tr>
                        <td style="padding:18px 24px 0 24px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; border-top:1px solid #e5e7eb;">
                                <tr>
                                    <td style="padding:14px 0 0 0;">
                                        <div style="font-family:Arial, Helvetica, sans-serif; font-size:11px; line-height:16px; color:#9ca3af; text-transform:uppercase; letter-spacing:0.03em; font-weight:bold; Margin:0 0 8px 0;">
                                            Externe Kennungen
                                        </div>
                                        @include('emails.partials.external-ids', ['destination' => $destination ?? null])
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endif

                {{-- Kontakt & Abschluss --}}
                <tr>
                    <td style="padding:16px 24px 0 24px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; border-top:1px solid #e5e7eb;">
                            <tr>
                                <td style="padding:14px 0 0 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; line-height:16px; color:#9ca3af; text-transform:uppercase; letter-spacing:0.03em; font-weight:bold;">
                                    Kontakt
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:8px 0 0 0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#6b7280;">
                                    <strong style="color:#111827;">Kontakt: Alexander Franz</strong><br>
                                    Redaktionsdesk (24h): <a href="tel:+4922364809488" style="color:#092E48; text-decoration:underline;">02236 4809 488</a><br>
                                    Mobil: +49 (0) 176 59593226
                                    @if(config('mail.contact_address'))
                                        <br>E-Mail: <a href="mailto:{{ config('mail.contact_address') }}" style="color:#092E48; text-decoration:underline;">{{ config('mail.contact_address') }}</a>
                                    @endif
                                    @if($authorCredit !== '')
                                        <br><span style="color:#374151;">Quelle: {{ $authorCredit }}</span>
                                    @endif
                                    <br><span style="color:#374151;">Die Bilder sind honorarpflichtig zzgl. 7 % MwSt. Alle Rechte vorbehalten.</span>
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
