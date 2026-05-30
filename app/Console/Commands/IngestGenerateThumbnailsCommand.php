<?php

namespace App\Console\Commands;

use App\Models\IngestFile;
use App\Services\Ingest\IngestImageThumbnailService;
use App\Services\Ingest\IngestVideoPosterService;
use Illuminate\Console\Command;

class IngestGenerateThumbnailsCommand extends Command
{
    protected $signature = 'ingest:thumbnails-generate
                            {--force : Bereits vorhandene Thumbnails neu erzeugen}
                            {--limit=0 : Maximal N Bild-Einträge verarbeiten (0 = alle)}
                            {--ids= : Kommagetrennte ingest_files IDs (optional)}';

    protected $description = 'Erzeugt lokale Thumbnails/Poster für Ingest-Bilder und -Videos (thumb_path).';

    public function handle(IngestImageThumbnailService $thumbs, IngestVideoPosterService $posters): int
    {
        $force = (bool) $this->option('force');
        $limit = max(0, (int) $this->option('limit'));
        $idsOption = trim((string) ($this->option('ids') ?? ''));
        $ids = $this->parseIds($idsOption);

        $q = IngestFile::query()->orderBy('id');
        if ($ids !== []) {
            $q->whereIn('id', $ids);
        } else {
            $q->where(function ($sub): void {
                $sub->whereRaw('LOWER(original_name) LIKE ?', ['%.jpg'])
                    ->orWhereRaw('LOWER(original_name) LIKE ?', ['%.jpeg'])
                    ->orWhereRaw('LOWER(original_name) LIKE ?', ['%.jpe'])
                    ->orWhereRaw('LOWER(original_name) LIKE ?', ['%.mp4'])
                    ->orWhereRaw('LOWER(original_name) LIKE ?', ['%.mov'])
                    ->orWhereRaw('LOWER(original_name) LIKE ?', ['%.mxf'])
                    ->orWhereRaw('LOWER(original_name) LIKE ?', ['%.mkv'])
                    ->orWhereRaw('LOWER(original_name) LIKE ?', ['%.avi'])
                    ->orWhereRaw('LOWER(original_name) LIKE ?', ['%.mts'])
                    ->orWhereRaw('LOWER(original_name) LIKE ?', ['%.m2ts'])
                    ->orWhereRaw('LOWER(original_name) LIKE ?', ['%.webm']);
            });
        }
        if ($limit > 0) {
            $q->limit($limit);
        }

        $files = $q->get();
        if ($files->isEmpty()) {
            $this->info('Keine passenden Ingest-Bilder oder -Videos gefunden.');

            return self::SUCCESS;
        }

        $ok = 0;
        $failed = 0;
        $missing = 0;
        $skipped = 0;

        foreach ($files as $file) {
            if ($file->isIngestImageFile()) {
                if (! is_file((string) $file->absolute_path)) {
                    $missing++;

                    continue;
                }

                $path = $thumbs->ensureThumbnail($file, $force);
                if (is_string($path) && $path !== '') {
                    $ok++;
                } else {
                    $failed++;
                }

                continue;
            }

            if ($file->isIngestVideoFile()) {
                if (! is_file((string) $file->absolute_path)) {
                    $missing++;

                    continue;
                }

                $path = $posters->ensurePoster($file, $force);
                if (is_string($path) && $path !== '') {
                    $ok++;
                } else {
                    $failed++;
                }

                continue;
            }

            $skipped++;
        }

        $this->info('Ingest-Thumb-/Poster-Backfill abgeschlossen.');
        $this->line('  Erfolgreich: '.$ok);
        $this->line('  Fehlgeschlagen: '.$failed);
        $this->line('  Quelldatei fehlt: '.$missing);
        $this->line('  Übersprungen (kein Bild/Video): '.$skipped);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function parseIds(string $csv): array
    {
        if ($csv === '') {
            return [];
        }

        $ids = [];
        foreach (explode(',', $csv) as $part) {
            $part = trim($part);
            if ($part === '' || ! ctype_digit($part)) {
                continue;
            }
            $ids[] = (int) $part;
        }

        return array_values(array_unique($ids));
    }
}
