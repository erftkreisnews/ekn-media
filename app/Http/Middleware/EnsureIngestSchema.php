<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verhindert 500er, wenn Ingest-Migrationen noch nicht ausgeführt wurden.
 * Läuft vor SubstituteBindings (prepend auf „web“), damit {ingestFile}-Routen nicht an fehlender Tabelle scheitern.
 */
class EnsureIngestSchema
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('admin/ingest') && ! $request->is('admin/ingest/*')) {
            return $next($request);
        }

        if (Schema::hasTable('ingest_files')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Video-Ingest ist noch nicht eingerichtet. Bitte auf dem Server `php artisan migrate` ausführen.',
            ], 503);
        }

        return response()
            ->view('admin.ingest.schema-missing', [], 503);
    }
}
