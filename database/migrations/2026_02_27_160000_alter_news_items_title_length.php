<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Erhöhe die maximale Länge des Titels von 255 auf 265 Zeichen,
        // ohne Doctrine DBAL zu benötigen.
        DB::statement('ALTER TABLE news_items MODIFY title VARCHAR(265) NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE news_items MODIFY title VARCHAR(255) NOT NULL');
    }
};
