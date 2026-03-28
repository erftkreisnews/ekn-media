<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Einige Server hatten eine leere/fehlerhafte Migration ohne gültige Klasse
 * (Fehler: Class "CreateSettingsTable" not found). Diese Datei ersetzt das
 * Standard-Muster mit anonymem Migration-Objekt (Laravel 10+).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('settings')) {
            return;
        }

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
