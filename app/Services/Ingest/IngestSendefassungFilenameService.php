<?php

namespace App\Services\Ingest;

use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Redaktions-Dateinamen für Ingest-Sendefassungen (Download, FTP, Medienpaket).
 *
 * Schema: YYYY-MM-DD_ort_ereignis[_teilN]_AF(autor).mp4
 */
class IngestSendefassungFilenameService
{
    /** @var array<string, string> Einsatzkategorie (Schlagwort) → Kurzform */
    private const EINSATZ_KATEGORIE_SLUGS = [
        'Gefahrgutunfall' => 'gefahrgut',
        'Verkehrsunfall' => 'unfall',
        'Polizeieinsatz' => 'polizei',
        'Hilfeleistung' => 'hilfeleistung',
        'Sucheinsatz' => 'sucheinsatz',
        'Wasserrettung' => 'wasserrettung',
        'Brand' => 'brand',
        'Unwetter' => 'unwetter',
        'Event' => 'event',
        'Luftbild' => 'luftbild',
    ];

    /** Priorität bei Erkennung aus Titel/Schlagwörtern (spezifisch zuerst). */
    private const EREIGNIS_INFERENCE_ORDER = [
        'einsturzgefahr' => 'einsturz',
        'einsturz' => 'einsturz',
        'gefahrgut' => 'gefahrgut',
        'verkehrsunfall' => 'unfall',
        'unfall' => 'unfall',
        'polizeieinsatz' => 'polizei',
        'polizei' => 'polizei',
        'brand' => 'brand',
        'wasserrettung' => 'wasserrettung',
        'hochwasser' => 'unwetter',
        'sturm' => 'unwetter',
        'unwetter' => 'unwetter',
        'hilfeleistung' => 'hilfeleistung',
        'sucheinsatz' => 'sucheinsatz',
        'luftbild' => 'luftbild',
        'event' => 'event',
    ];

    public function resolveEventDate(NewsItem $newsItem): CarbonInterface
    {
        $date = $newsItem->event_at ?? $newsItem->published_at ?? $newsItem->created_at ?? now();

        return $date instanceof CarbonInterface ? $date : now();
    }

    public function resolveOrtSlug(NewsItem $newsItem): string
    {
        $title = (string) ($newsItem->title ?? '');
        $city = trim((string) ($newsItem->city ?? ''));

        if ($city !== '' && preg_match('/\b'.preg_quote($city, '/').'-([\p{L}\p{M}]+)/u', $title, $m)) {
            $slug = $this->editorialSlug($city.'-'.$m[1]);

            return $slug !== '' ? $slug : 'ort';
        }

        if ($city !== '') {
            $slug = $this->editorialSlug($city);

            return $slug !== '' ? $slug : 'ort';
        }

        $region = $this->editorialSlug((string) ($newsItem->region ?? ''));

        return $region !== '' ? $region : 'ort';
    }

    public function resolveEreignisSlug(NewsItem $newsItem): string
    {
        $haystack = mb_strtolower(
            (string) $newsItem->title.' '.(string) $newsItem->keywords,
            'UTF-8'
        );

        foreach (self::EREIGNIS_INFERENCE_ORDER as $needle => $slug) {
            if (str_contains($haystack, $needle)) {
                return $slug;
            }
        }

        $terms = array_map(static fn (string $t) => trim($t), explode(',', (string) ($newsItem->keywords ?? '')));
        foreach (self::EINSATZ_KATEGORIE_SLUGS as $term => $slug) {
            if (in_array($term, $terms, true)) {
                return $slug;
            }
        }

        return 'einsatz';
    }

    public function resolveAuthorAfSegment(NewsItem $newsItem): string
    {
        $credit = trim((string) ($newsItem->author_credit ?? ''));
        if ($credit === '' && $newsItem->relationLoaded('author') && $newsItem->author) {
            $credit = trim((string) ($newsItem->author->name ?? ''));
        }

        $slug = $this->editorialSlug($credit);

        return 'AF('.($slug !== '' ? $slug : 'unbekannt').')';
    }

    /** Umlaute für Redaktions-Pfade lesbar (köln → koeln, nicht koln). */
    private function editorialSlug(string $value): string
    {
        $value = str_replace(
            ['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'],
            ['ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue', 'ss'],
            $value
        );

        return Str::slug($value, '-');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<NewsItemMedia>
     */
    public function sendefassungVideosQuery(NewsItem $newsItem)
    {
        return $newsItem->media()
            ->where('type', 'video')
            ->where('path', 'like', '%-sendefassung-%');
    }

    public function countExistingSendefassungVideos(NewsItem $newsItem): int
    {
        return (int) $this->sendefassungVideosQuery($newsItem)->count();
    }

    /**
     * @return list<NewsItemMedia>
     */
    public function existingSendefassungVideosOrdered(NewsItem $newsItem): array
    {
        return $this->sendefassungVideosQuery($newsItem)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function buildOriginalName(NewsItem $newsItem, int $partNumber, bool $usePartSuffix): string
    {
        $date = $this->resolveEventDate($newsItem)->format('Y-m-d');
        $ort = $this->resolveOrtSlug($newsItem);
        $ereignis = $this->resolveEreignisSlug($newsItem);
        $author = $this->resolveAuthorAfSegment($newsItem);
        $partNumber = max(1, $partNumber);

        $segments = [$date, $ort, $ereignis];
        if ($usePartSuffix) {
            $segments[] = 'teil'.$partNumber;
        }
        $segments[] = $author;

        return implode('_', $segments).'.mp4';
    }

    /**
     * Lokaler Render-Pfad: ASCII-slug des Redaktionsnamens.
     */
    public function buildLocalRenderBasename(NewsItem $newsItem, int $partNumber, bool $usePartSuffix): string
    {
        $original = pathinfo($this->buildOriginalName($newsItem, $partNumber, $usePartSuffix), PATHINFO_FILENAME);
        $slug = Str::slug($original, '_');
        $slug = str_replace('-', '_', $slug);

        return ($slug !== '' ? $slug : 'sendefassung').'.mp4';
    }

    /**
     * Alle Sendefassungen einer Meldung auf Redaktions-Schema umbenennen.
     */
    public function relabelExistingSendefassungVideosAsParts(NewsItem $newsItem, ?bool $useParts = null): void
    {
        $videos = $this->existingSendefassungVideosOrdered($newsItem);
        $useParts = $useParts ?? count($videos) > 1;

        foreach ($videos as $index => $video) {
            $expected = $this->buildOriginalName($newsItem, $index + 1, $useParts);
            if ((string) ($video->original_name ?? '') !== $expected) {
                $video->update(['original_name' => $expected]);
            }
        }
    }

    /**
     * @return array{original_name: string, local_basename: string, part_number: int, use_part_suffix: bool}
     */
    public function assignForNewSendefassung(NewsItem $newsItem): array
    {
        $existingCount = $this->countExistingSendefassungVideos($newsItem);
        $usePartSuffix = ($existingCount + 1) > 1;

        if ($existingCount >= 1) {
            $this->relabelExistingSendefassungVideosAsParts($newsItem, $usePartSuffix);
        }

        $partNumber = $existingCount + 1;
        $originalName = $this->buildOriginalName($newsItem, $partNumber, $usePartSuffix);

        return [
            'original_name' => $originalName,
            'local_basename' => $this->buildLocalRenderBasename($newsItem, $partNumber, $usePartSuffix),
            'part_number' => $partNumber,
            'use_part_suffix' => $usePartSuffix,
        ];
    }
}
