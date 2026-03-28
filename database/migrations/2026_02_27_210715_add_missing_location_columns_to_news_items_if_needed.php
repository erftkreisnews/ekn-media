<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Fügt die Standort-Spalten hinzu, falls sie fehlen (z. B. wenn DB anders aufgesetzt wurde).
     */
    public function up(): void
    {
        Schema::table('news_items', function (Blueprint $table) {
            if (! Schema::hasColumn('news_items', 'country')) {
                $table->string('country', 255)->nullable();
            }
            if (! Schema::hasColumn('news_items', 'federal_state')) {
                $table->string('federal_state', 255)->nullable();
            }
            if (! Schema::hasColumn('news_items', 'city')) {
                $table->string('city', 255)->nullable();
            }
            if (! Schema::hasColumn('news_items', 'street')) {
                $table->string('street', 255)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_items', function (Blueprint $table) {
            $columns = ['country', 'federal_state', 'city', 'street'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('news_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
