<?php

namespace App\Http\Middleware;

use App\Support\WitnessPortal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stiller Schutz vor einfachen Bots: gefülltes Honeypot-Feld → sofort Redirect ohne Validierung.
 */
class WitnessHoneypotMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale(config('witness.locale', 'de'));

        if ($request->isMethod('POST') && $request->filled('website')) {
            session()->forget('witness_form_started_at');
            $token = (string) $request->route('token', '');

            return redirect()
                ->route(WitnessPortal::routeNameForShow($request), ['token' => $token])
                ->with('status', Lang::get('witness.honeypot_ok', [], 'de'));
        }

        return $next($request);
    }
}
