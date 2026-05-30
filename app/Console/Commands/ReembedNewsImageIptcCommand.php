<?php

namespace App\Console\Commands;

use App\Models\NewsItemMedia;
use App\Services\ImageMetadataWriter;
use App\Services\MediaStorage;
use Illuminate\Console\Command;

class ReembedNewsImageIptcCommand extends Command
{
    protected $signature = 'media:reembed-iptc
                            {--dry-run : Nur zählen/auflisten, keine Dateien ändern}
                            {--id= : Nur diese news_item_media-ID (Bild)}
                            {--chunk=100 : Anzahl Datensätze pro Chunk}
                            {--force : Ohne Sicherheitsabfrage starten}';

    protected $description = 'Schreibt IPTC + XMP für vorhandene Nachrichten-Bilder neu (Master-Datei wie Medienpaket), z. B. nach UTF-8-/XMP-Anpassungen.';

    public function handle(MediaStorage $mediaStorage): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $singleId = $this->option('id');
        $chunk = max(1, (int) $this->option('chunk'));

        $query = NewsItemMedia::query()
            ->where('type', 'image')
            ->whereNotNull('path')
            ->where('path', '!=', '')
            ->where('path', '!=', 'news-media/.pending')
            ->orderBy('id');

        if ($singleId !== null && $singleId !== '' && $singleId !== '0') {
            $query->whereKey((int) $singleId);
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->warn('Keine passenden Bild-Medien gefunden.');

            return self::SUCCESS;
        }

        $this->info('Gefundene Bild-Medien: '.$total.($dryRun ? ' (Dry-Run)' : '').'.');

        if (! $dryRun && ! $this->option('force') && ! $this->input->isInteractive()) {
            $this->error('Nicht-interaktiv: bitte --force setzen oder --dry-run nutzen.');

            return self::FAILURE;
        }

        if (! $dryRun && ! $this->option('force')) {
            if (! $this->confirm('IPTC/XMP in den Dateien überschreiben? (Master-JPEGs werden angepasst.)', false)) {
                $this->comment('Abgebrochen.');

                return self::SUCCESS;
            }
        }

        $ok = 0;
        $listed = 0;
        $skipped = 0;
        $failed = 0;

        $query->chunkById($chunk, function ($medias) use ($mediaStorage, $dryRun, &$ok, &$listed, &$skipped, &$failed) {
            foreach ($medias as $media) {
                /** @var NewsItemMedia $media */
                $media->loadMissing('newsItem');
                $rel = $media->resolveIptcMasterRelativePath();
                if ($rel === null || ! $mediaStorage->exists($rel)) {
                    $skipped++;
                    if ($this->output->isVerbose()) {
                        $this->line('Übersprungen #'.$media->id.' (kein Master-Pfad oder Datei fehlt).');
                    }

                    continue;
                }

                if ($dryRun) {
                    $this->line('Würde #'.$media->id.' → '.$rel);
                    $listed++;

                    continue;
                }

                $resolved = $mediaStorage->resolveReadableLocalPath($rel);
                $fullPath = $resolved['path'] ?? null;
                if (! is_string($fullPath) || ! is_file($fullPath) || ! is_writable($fullPath)) {
                    $failed++;
                    $this->warn('Nicht beschreibbar / nicht lesbar: #'.$media->id.' → '.$rel);

                    continue;
                }

                $written = ImageMetadataWriter::write($fullPath, $media->resolvedIptcForEmbed());
                if ($written && ($resolved['temporary'] ?? false) === true) {
                    $mediaStorage->putFromLocalFile($rel, $fullPath, ['visibility' => 'public']);
                }
                $mediaStorage->cleanupResolvedPath($resolved);

                if ($written) {
                    $ok++;
                } else {
                    $failed++;
                    $this->warn('Schreiben fehlgeschlagen: #'.$media->id);
                }
            }
        });

        $this->newLine();
        $this->info($dryRun ? 'Dry-Run:' : 'Fertig:');
        if ($dryRun) {
            $this->table(
                ['Kennzahl', 'Anzahl'],
                [
                    ['Würde neu schreiben', (string) $listed],
                    ['Übersprungen', (string) $skipped],
                ]
            );
        } else {
            $this->table(
                ['Kennzahl', 'Anzahl'],
                [
                    ['Erfolgreich', (string) $ok],
                    ['Übersprungen', (string) $skipped],
                    ['Fehler', (string) $failed],
                ]
            );
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
