<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class XRobotsTag
{
    /**
     * Setzt den X-Robots-Tag HTTP-Header.
     *
     * Öffentliche Seiten: index, follow
     * Interne/Admin-Bereiche: noindex, nofollow
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        // robots-Support in dieser Reihenfolge (wichtig für unterschiedliche noindex Varianten):
        // 1) noindex,nofollow: Admin/Auth/Delivery/System
        // 2) noindex,follow: Suche/Filter/Parameterseiten (Query-Parameter auf Startseite)
        // 3) index,follow: Artikel-Detail + Startseite ohne Query

        // 1) noindex,nofollow (System-/Interne Seiten)
        $isNoIndexNoFollow =
            $request->is('admin*')
            || $request->is('login')
            || $request->is('register')
            // Auth-/Password- und Account-Seiten (Laravel-Built-in)
            || $request->is('forgot-password')
            || $request->is('reset-password/*')
            || $request->is('verify-email*')
            || $request->is('confirm-password')
            || $request->is('password/*')
            || $request->is('profile*')
            || $request->is('kunden*')
            // Delivery-Links (auch verschachtelt)
            // d/{token}               -> d/*
            // d/{token}/confirm       -> d/*/*
            // d/{token}/m/{media}     -> d/*/*/*
            || $request->is('d/*')
            || $request->is('d/*/*')
            || $request->is('d/*/*/*')
            // OAuth-/Auth-Callbacks
            || $request->is('auth/*');

        // 2) noindex,follow (Suche/Filter/Parameterseiten)
        $page = (int) $request->query('page', 1);
        $q = trim((string) $request->query('q', ''));
        $queryKeys = array_keys($request->query());
        $hasOtherQueryParams = array_diff($queryKeys, ['page', 'q']) !== [];

        $isNoIndexFollowForQuery =
            // Startseite-Route mit Query-Parametern
            $request->is('/') && (
                $q !== ''
                || $page > 1
                || $hasOtherQueryParams
            );

        $robotsValue = 'index, follow';
        if ($isNoIndexNoFollow) {
            $robotsValue = 'noindex, nofollow';
        } elseif ($isNoIndexFollowForQuery) {
            $robotsValue = 'noindex, follow';
        }

        $response->headers->set('X-Robots-Tag', $robotsValue);

        // Damit Meta-robots im Head konsistent mit dem Header ist.
        // (views können diesen Wert dann direkt verwenden)
        View::share('robotsMeta', $robotsValue);

        return $response;
    }
}
