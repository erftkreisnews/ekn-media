<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class XRobotsTag
{
    /**
     * Setzt den X-Robots-Tag HTTP-Header und teilt den Wert mit Views (meta robots).
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        $isNoIndexNoFollow =
            $request->is('admin*')
            || $request->is('login')
            || $request->is('register')
            || $request->is('forgot-password')
            || $request->is('reset-password/*')
            || $request->is('verify-email*')
            || $request->is('confirm-password')
            || $request->is('password/*')
            || $request->is('profile*')
            || ($request->is('kunden*') && ! $request->routeIs('koelnimage.customer.downloads'))
            || $request->is('d/*')
            || $request->is('d/*/*')
            || $request->is('d/*/*/*')
            || $request->is('auth/*')
            || $request->is('witness/*')
            || $request->routeIs('witness.upload.show', 'witness.upload.store', 'witness.portal.show', 'witness.portal.store');

        $page = (int) $request->query('page', 1);
        $q = trim((string) $request->query('q', ''));
        $queryKeys = array_keys($request->query());
        $hasOtherQueryParams = array_diff($queryKeys, ['page', 'q']) !== [];
        $path = trim((string) $request->path(), '/');
        $isFrontendHome = $request->routeIs('home') || $path === '';

        $isNoIndexFollowForQuery =
            $isFrontendHome && (
                $q !== ''
                || $page > 1
                || $hasOtherQueryParams
            );

        $koelnimageGalleryQuery = $request->routeIs('koelnimage.gallery.photos') && count($request->query()) > 0;
        $koelnimageEventsQuery = $request->routeIs('koelnimage.events.index') && count($request->query()) > 0;
        $koelnimageGalleriesQuery = $request->routeIs('koelnimage.galleries.index') && count($request->query()) > 0;
        $koelnimageGalleriesPaginationOnly = $request->routeIs('koelnimage.galleries.index')
            && count($request->query()) === 1
            && $request->has('page')
            && (int) $request->query('page', 1) >= 2;
        $isKoelnimageNoIndexFollow = $koelnimageGalleryQuery
            || $koelnimageEventsQuery
            || ($koelnimageGalleriesQuery && ! $koelnimageGalleriesPaginationOnly);

        $robotsValue = 'index, follow';
        if ($isNoIndexNoFollow) {
            $robotsValue = 'noindex, nofollow';
        } elseif ($isNoIndexFollowForQuery || $isKoelnimageNoIndexFollow) {
            $robotsValue = 'noindex, follow';
        }

        $response->headers->set('X-Robots-Tag', $robotsValue);
        View::share('robotsMeta', $robotsValue);

        return $response;
    }
}
