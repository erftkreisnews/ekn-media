<?php

namespace App\Console\Commands;

use App\Services\News\SlugRedirect\NewsSlugRedirectImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use SplFileObject;

class ImportSlugRedirects extends Command
{
    protected $signature = 'news:import-slug-redirects
        {--file= : CSV-Datei (Header optional): from_slug,to_slug}
        {--delimiter=, : CSV-Delimiter}
        {--dry-run : Kein DB-Write, nur Validierung & Statistik}
        {--upsert : Existierende Redirects bei Konflikten aktualisieren}
        {--allow-nonpublic : Auch Zielartikel importieren, die aktuell nicht publicVisible sind (Default: Skip)}
    ';

    protected $description = 'Importiert alte News-Slugs in news_slug_redirects (301) mit defensiver Validierung';

    public function handle(NewsSlugRedirectImportService $service): int
    {
        $file = (string) $this->option('file');
        $delimiter = (string) $this->option('delimiter');
        if ($delimiter === '') {
            $delimiter = ',';
        }

        $dryRun = (bool) $this->option('dry-run');
        $upsert = (bool) $this->option('upsert');
        $allowNonPublic = (bool) $this->option('allow-nonpublic');

        $rows = [];

        if ($file !== '') {
            $rows = $this->readCsvRows($file, $delimiter);
        } else {
            $rows = $this->autoDetectRowsFromNewsItems();
        }

        if ($rows === []) {
            $this->error('Keine Redirect-Quellen gefunden. Nutze bitte --file=... (from_slug,to_slug).');

            return self::FAILURE;
        }

        $this->info('Starte Import: '.($file !== '' ? $file : 'Auto-Quelle'));
        $this->info('Rows (nach Read): '.count($rows));
        $this->newLine();

        $stats = $service->importRows($rows, [
            'dryRun' => $dryRun,
            'upsert' => $upsert,
            'allowNonPublic' => $allowNonPublic,
        ]);

        $this->line('--- Ergebnis ---');
        foreach ($stats as $k => $v) {
            if (is_float($v)) {
                $v = (string) $v;
            }
            $this->line(sprintf('%s: %s', $k, (string) $v));
        }

        if (! $dryRun) {
            $this->newLine();
            $this->info('Import abgeschlossen. Bitte danach Caches leeren, falls vorhanden.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{from_slug:string,to_slug:string}>
     */
    private function readCsvRows(string $file, string $delimiter): array
    {
        $path = $file;

        // Wenn user relative Pfade übergibt, erweitern wir auf storage/app
        if (! str_starts_with($path, '/') && str_starts_with($path, 'storage/')) {
            // ok
        } elseif (! str_starts_with($path, '/') && ! str_starts_with($path, 'storage/')) {
            $path = 'storage/app/'.ltrim($path, '/');
        }

        if (! is_file($path)) {
            // fallback: project_root + file
            $alt = base_path($file);
            if (is_file($alt)) {
                $path = $alt;
            }
        }

        if (! is_file($path)) {
            throw new \InvalidArgumentException('CSV-Datei nicht gefunden: '.$file);
        }

        $f = new SplFileObject($path);
        $f->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        $rows = [];
        $headerChecked = false;

        while (! $f->eof()) {
            $data = $f->fgetcsv($delimiter);
            if (! is_array($data)) {
                continue;
            }
            if ($data === [null] || $data === []) {
                continue;
            }

            $data = array_map(static fn ($v) => is_string($v) ? trim($v) : $v, $data);

            if (! $headerChecked) {
                $headerChecked = true;
                // Header optional
                $lower = array_map(static fn ($v) => is_string($v) ? strtolower($v) : '', $data);
                if (in_array('from_slug', $lower, true) && in_array('to_slug', $lower, true)) {
                    continue;
                }
            }

            $from = (string) ($data[0] ?? '');
            $to = (string) ($data[1] ?? '');

            if ($from === '' && $to === '') {
                continue;
            }

            $rows[] = [
                'from_slug' => $from,
                'to_slug' => $to,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{from_slug:string,to_slug:string}>
     */
    private function autoDetectRowsFromNewsItems(): array
    {
        // Es gibt aktuell in euren Migrations keine Slug-Historie-Felder.
        // Dieser Block ist defensiv für künftige/abweichende Setups gedacht.
        $candidateColumns = [
            'old_slugs',
            'previous_slugs',
            'slug_history',
            'redirect_from_slugs',
        ];

        $available = [];
        foreach ($candidateColumns as $col) {
            if (Schema::hasColumn('news_items', $col)) {
                $available[] = $col;
            }
        }

        if ($available === []) {
            return [];
        }

        $this->warn('Auto-Quelle aktiv: '.implode(', ', $available));

        // Lazy-load, um nicht komplett in Memory zu gehen
        $rows = [];

        $items = \App\Models\NewsItem::query()->select(['id', 'slug', ...$available])->get();
        foreach ($items as $item) {
            foreach ($available as $col) {
                $raw = $item->{$col};
                if ($raw === null) {
                    continue;
                }

                $list = [];
                if (is_array($raw)) {
                    $list = $raw;
                } elseif (is_string($raw)) {
                    $trim = trim($raw);
                    if ($trim !== '' && (str_starts_with($trim, '[') || str_starts_with($trim, '{'))) {
                        $decoded = json_decode($trim, true);
                        if (is_array($decoded)) {
                            $list = $decoded;
                        }
                    }
                    if ($list === []) {
                        $list = preg_split('/[\r\n,;]+/', $raw) ?: [];
                    }
                }

                foreach ($list as $oldSlug) {
                    $oldSlug = trim((string) $oldSlug);
                    if ($oldSlug === '') {
                        continue;
                    }
                    $rows[] = [
                        'from_slug' => $oldSlug,
                        'to_slug' => $item->slug,
                    ];
                }
            }
        }

        return $rows;
    }
}
