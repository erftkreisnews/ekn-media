<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\Lexware\LexwareContactService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncProductToLexwareJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(
        protected Product $product
    ) {}

    public function handle(LexwareContactService $lexwareContactService): void
    {
        if (! config('lexware.api_key')) {
            return;
        }

        $product = $this->product->fresh(['organization']);
        if (! $product) {
            return;
        }

        try {
            $lexwareContactService->ensureContact($product);
        } catch (\Throwable $e) {
            Log::warning('SyncProductToLexwareJob: Sync fehlgeschlagen', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
