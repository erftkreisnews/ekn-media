<?php

use App\Support\FcKoelnHerrenSquadSnapshot;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        FcKoelnHerrenSquadSnapshot::apply(onlyIfEmpty: true);
    }

    public function down(): void
    {
        // Kein automatisches Löschen: Kader kann inzwischen redaktionell geändert sein.
    }
};
