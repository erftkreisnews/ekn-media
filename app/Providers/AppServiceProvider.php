<?php

namespace App\Providers;

use App\Models\NewsItem;
use App\Observers\NewsItemObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        NewsItem::observe(NewsItemObserver::class);

        // Legacy: Recht „access_admin“ (direkt oder über Rolle) = voller Zugriff auf alle admin.*-Checks.
        // Nicht getAllPermissions() im before-Callback: teuer und in manchen Setups fehleranfällig.
        // hasPermissionTo('access_admin') prüft nur dieses eine Recht; innerer Gate-Call nutzt ability
        // „access_admin“ (ohne admin.-Präfix) → kein Rekursionsloop mit diesem before-Block.
        Gate::before(function ($user, ?string $ability) {
            if (! $user || $ability === null || ! str_starts_with($ability, 'admin.')) {
                return null;
            }

            try {
                if ($user->hasPermissionTo('access_admin')) {
                    return true;
                }
            } catch (\Throwable $e) {
                // z. B. Permission fehlt in DB → kein 500, normale Ablehnung
                report($e);
            }

            return null;
        });

        Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('microsoft', \SocialiteProviders\Microsoft\Provider::class);
        });

        RateLimiter::for('witness-show', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('witness-upload', function (Request $request) {
            return Limit::perMinute(6)->by($request->ip());
        });

        Queue::before(function () {
            Cache::put('queue_worker_heartbeat_at', now()->toIso8601String(), 300);
        });
    }
}
