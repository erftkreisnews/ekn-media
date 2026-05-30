<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Zeigt ein kurzes Zitat (Laravel Standard).');

Schedule::command('event-discovery:fetch')
    ->dailyAt((string) config('event_discovery.run_at', '06:00'))
    ->timezone((string) config('event_discovery.timezone', 'Europe/Berlin'))
    ->withoutOverlapping()
    ->skip(fn () => ! config('event_discovery.enabled', false));
