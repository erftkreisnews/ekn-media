<?php

namespace App\Console\Commands;

use App\Jobs\GenerateIngestPreviewJob;
use App\Models\IngestFile;
use Illuminate\Console\Command;

class RetryIngestPreviewsCommand extends Command
{
    protected $signature = 'ingest:retry-failed-previews {--all : Auch Clips ohne Fehlermeldung neu anstoßen, wenn keine lesbare Vorschau existiert}';

    protected $description = 'Stellt fehlgeschlagene Ingest-Browser-Vorschauen erneut in die Queue.';

    public function handle(): int
    {
        $query = IngestFile::query()
            ->whereIn('status', [
                IngestFile::STATUS_VALIDATED,
                IngestFile::STATUS_ASSIGNED,
                IngestFile::STATUS_PREVIEW_READY,
            ]);

        if (! $this->option('all')) {
            $query->whereNotNull('preview_error_message')
                ->where('preview_error_message', '!=', '');
        }

        $files = $query->orderBy('id')->get();
        $dispatched = 0;

        foreach ($files as $file) {
            if (! $file->isIngestVideoCandidate()) {
                continue;
            }

            GenerateIngestPreviewJob::dispatch($file->id);
            $dispatched++;
        }

        $this->info("{$dispatched} Vorschau-Job(s) eingereiht.");

        return self::SUCCESS;
    }
}
