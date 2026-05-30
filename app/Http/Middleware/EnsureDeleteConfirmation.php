<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Erzwingt für Admin-Löschaktionen (Routenname *.destroy, HTTP DELETE) die aktive
 * Bestätigung per Formularfeld „confirmation“ mit dem Wert „ja“ (vom Admin-Frontend nach OK im Dialog).
 */
class EnsureDeleteConfirmation
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('DELETE')) {
            return $next($request);
        }

        $name = $request->route()?->getName();
        if (! is_string($name) || ! str_ends_with($name, '.destroy')) {
            return $next($request);
        }

        $confirmation = trim((string) $request->input('confirmation', ''));
        if (mb_strtolower($confirmation, 'UTF-8') !== 'ja') {
            return back()
                ->withErrors(['confirmation' => 'Löschen nicht bestätigt. Bitte erneut versuchen.'])
                ->withInput();
        }

        return $next($request);
    }
}
