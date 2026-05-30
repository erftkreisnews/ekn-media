<?php

namespace Database\Seeders;

use App\Support\FcKoelnHerrenSquadSnapshot;
use Illuminate\Database\Seeder;

/**
 * Setzt den Haus-Kader 1. FC Köln Herren auf den eingecheckten Snapshot (überschreibt vorhandene Spielerzeilen).
 *
 * php artisan db:seed --class=FcKoelnHerrenSquadSeeder
 */
class FcKoelnHerrenSquadSeeder extends Seeder
{
    public function run(): void
    {
        FcKoelnHerrenSquadSnapshot::apply(onlyIfEmpty: false);
    }
}
