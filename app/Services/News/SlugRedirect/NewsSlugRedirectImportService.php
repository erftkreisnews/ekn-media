<?php

namespace App\Services\News\SlugRedirect;

use App\Models\NewsItem;
use App\Models\NewsSlugRedirect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NewsSlugRedirectImportService
{
    /**
     * Importiert Slug-Redirects in die Tabelle `news_slug_redirects`.
     *
     * @param  array<int, array{from_slug:string,to_slug:string}>  $rows
     * @param  array{dryRun:bool, allowNonPublic:bool, upsert:bool}  $options
     * @return array<string, int|float|string>
     */
    public function importRows(array $rows, array $options): array
    {
        $dryRun = (bool) ($options['dryRun'] ?? false);
        $allowNonPublic = (bool) ($options['allowNonPublic'] ?? false);
        $upsert = (bool) ($options['upsert'] ?? false);

        $stats = [
            'read_rows' => count($rows),
            'parsed' => 0,
            'normalized' => 0,
            'input_from_slug_conflicts' => 0,
            'skipped_self' => 0,
            'skipped_empty' => 0,
            'checked' => 0,
            'imported' => 0,
            'skipped' => 0,
            'conflicts' => 0,
            'skipped_target_missing_or_not_public' => 0,
            'skipped_from_slug_is_public' => 0,
            'skipped_duplicate' => 0,
            'skipped_chain' => 0,
            'skipped_loop' => 0,
        ];

        // Normalisieren + Eingabekonflikte (from_slug kommt mehrfach vor, aber mapped auf anderes to_slug) erkennen
        $inputMap = []; // from_slug => to_slug
        $inputConflictFromSlugs = [];

        foreach ($rows as $row) {
            $from = $this->normalizeSlug((string) ($row['from_slug'] ?? ''));
            $to = $this->normalizeSlug((string) ($row['to_slug'] ?? ''));

            $stats['parsed']++;
            $stats['normalized']++;

            if ($from === '' || $to === '') {
                $stats['skipped_empty']++;

                continue;
            }
            if ($from === $to) {
                $stats['skipped_self']++;

                continue;
            }

            if (! isset($inputMap[$from])) {
                $inputMap[$from] = $to;

                continue;
            }

            if ($inputMap[$from] !== $to) {
                $inputConflictFromSlugs[$from] = true;
                $stats['input_from_slug_conflicts']++;
            }
        }

        // Bestehende Redirects als Graph
        $existing = NewsSlugRedirect::query()
            ->where('is_gone', false)
            ->whereNotNull('to_slug')
            ->get(['from_slug', 'to_slug'])
            ->pluck('to_slug', 'from_slug')
            ->toArray();

        $inputMapClean = [];
        foreach ($inputMap as $from => $to) {
            if (isset($inputConflictFromSlugs[$from])) {
                continue;
            }
            $inputMapClean[$from] = $to;
        }

        // Kandidaten prüfen
        $candidatesToInsert = [];

        // Für Ketten-/Loop-Checks verwenden wir einen Graph aus bestehenden + (noch zu importierenden) Kanten.
        $graphBase = $existing;
        foreach ($inputMapClean as $from => $to) {
            $graphBase[$from] = $to;
        }

        foreach ($inputMapClean as $fromSlug => $toSlug) {
            $stats['checked']++;

            // 1) Loop- & Chain-Schutz anhand Graph (defensiv: Kette/Loop -> skip)
            if (isset($graphBase[$toSlug])) {
                // toSlug ist selbst ein from_slug => min. 2-step chain möglich
                $stats['skipped_chain']++;
                $stats['skipped']++;

                continue;
            }

            // Loop detection: Kandidat überschreibt temporär den Graph
            $tempGraph = $graphBase;
            $tempGraph[$fromSlug] = $toSlug;
            if ($this->graphHasLoop($fromSlug, $tempGraph)) {
                $stats['skipped_loop']++;
                $stats['skipped']++;

                continue;
            }

            // 2) Existierende Redirects prüfen
            $existingRedirect = NewsSlugRedirect::query()
                ->where('from_slug', $fromSlug)
                ->first();

            if ($existingRedirect) {
                // Wenn identisch, kein neuer Import
                if (! $existingRedirect->is_gone && $existingRedirect->to_slug === $toSlug) {
                    $stats['skipped_duplicate']++;
                    $stats['skipped']++;

                    continue;
                }

                if (! $upsert) {
                    $stats['conflicts']++;
                    $stats['skipped']++;

                    continue;
                }
                // upsert möglich -> wird in insertList landen
            }

            // 3) Zielartikel muss existieren und (default) publicVisible sein
            $target = NewsItem::query()
                ->publicVisible()
                ->where('slug', $toSlug)
                ->first();

            if (! $target && ! $allowNonPublic) {
                $stats['skipped_target_missing_or_not_public']++;
                $stats['skipped']++;

                continue;
            }

            // allowNonPublic: wir müssen dann trotzdem irgendwas finden, sonst ist to_slug wertlos.
            if (! $target && $allowNonPublic) {
                $target = NewsItem::query()->where('slug', $toSlug)->first();
                if (! $target) {
                    $stats['skipped_target_missing_or_not_public']++;
                    $stats['skipped']++;

                    continue;
                }
            }

            // 4) fromSlug darf (default) nicht bereits publicVisible sein
            if (! $allowNonPublic) {
                $fromIsPublic = NewsItem::query()
                    ->publicVisible()
                    ->where('slug', $fromSlug)
                    ->exists();

                if ($fromIsPublic) {
                    $stats['skipped_from_slug_is_public']++;
                    $stats['skipped']++;

                    continue;
                }
            }

            $candidatesToInsert[] = [
                'from_slug' => $fromSlug,
                'to_slug' => $toSlug,
                'is_gone' => false,
            ];
        }

        if ($dryRun) {
            // Kein DB-Schreiben
            $stats['imported'] = 0;
            $stats['dry_run_candidates'] = count($candidatesToInsert);

            return $stats;
        }

        DB::transaction(function () use (&$stats, $candidatesToInsert, $upsert) {
            foreach ($candidatesToInsert as $row) {
                $fromSlug = $row['from_slug'];

                try {
                    if ($upsert) {
                        NewsSlugRedirect::query()->updateOrCreate(
                            ['from_slug' => $fromSlug],
                            [
                                'to_slug' => $row['to_slug'],
                                'is_gone' => $row['is_gone'],
                            ]
                        );
                    } else {
                        // Nur wenn nicht vorhanden
                        $exists = NewsSlugRedirect::query()
                            ->where('from_slug', $fromSlug)
                            ->exists();
                        if ($exists) {
                            $stats['skipped_duplicate']++;
                            $stats['skipped']++;

                            continue;
                        }
                        NewsSlugRedirect::query()->create($row);
                    }

                    $stats['imported']++;
                } catch (\Throwable) {
                    $stats['conflicts']++;
                    $stats['skipped']++;
                }
            }
        });

        return $stats;
    }

    /**
     * @param  array<string,string>  $graph
     */
    private function graphHasLoop(string $startFromSlug, array $graph): bool
    {
        $seen = [];
        $current = $startFromSlug;

        while (isset($graph[$current])) {
            if (isset($seen[$current])) {
                return true;
            }
            $seen[$current] = true;

            $current = $graph[$current];
            if ($current === $startFromSlug) {
                return true;
            }
        }

        return false;
    }

    private function normalizeSlug(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // Query/Fragment entfernen
        $value = preg_split('/[?#]/', $value)[0] ?? '';
        $value = trim($value);

        // Voll-URL/Path auf Slug reduzieren
        if (str_contains($value, '://')) {
            $path = parse_url($value, PHP_URL_PATH) ?: '';
            $value = $path;
        }

        $value = trim($value, '/');

        // Optional: /news/{slug} abziehen
        if (str_starts_with($value, 'news/')) {
            $value = substr($value, strlen('news/'));
        }

        // Letztes Segment verwenden, falls noch Pfad übrig ist
        $parts = explode('/', $value);
        $last = (string) array_pop($parts);

        return Str::lower(trim($last));
    }
}
