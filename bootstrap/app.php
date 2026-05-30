<?php

use App\Http\Middleware\EnsureIngestSchema;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\XRobotsTag;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Hinter Reverse-Proxy / TLS-Termination: sonst oft $request->isSecure() === false,
        // Session-Cookie ohne „Secure“ oder falsches Schema → Login wirkt erfolgreich, nächster Request wieder Gast.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB
        );

        $middleware->prependToGroup('web', [
            EnsureIngestSchema::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'logout',
        ]);
        $middleware->appendToGroup('web', [
            XRobotsTag::class,
            SecurityHeaders::class,
        ]);
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'delete.confirm' => \App\Http\Middleware\EnsureDeleteConfirmation::class,
            'witness.honeypot' => \App\Http\Middleware\WitnessHoneypotMiddleware::class,
            'brand.resolve' => \App\Http\Middleware\ResolveBrandByHost::class,
        ]);
        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.dashboard', absolute: false);
            }

            return route('login', absolute: false);
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            // Laravel-Standard: nicht als generischen 500er behandeln und in Production als 404 maskieren.
            if ($e instanceof AuthenticationException || $e instanceof ValidationException) {
                return null;
            }

            $statusCode = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            if ($statusCode === 404) {
                return response()->view('errors.404', [
                    'homeUrl' => url('/'),
                ], 404);
            }

            $isForbidden = $e instanceof AuthorizationException
                || $statusCode === 403;

            if (! $isForbidden || ! $request->user()) {
                if (! app()->environment(['local', 'testing']) && $statusCode >= 500) {
                    return response()->view('errors.404', [
                        'homeUrl' => url('/'),
                    ], 404);
                }

                return null;
            }

            $routeName = (string) ($request->route()?->getName() ?? '');
            if ($request->is('admin*') || str_starts_with($routeName, 'admin.')) {
                return redirect()
                    ->route('admin.dashboard')
                    ->with('error', 'Sie haben keine Berechtigung für diese Seite.');
            }

            return null;
        });
    })
    ->create();
