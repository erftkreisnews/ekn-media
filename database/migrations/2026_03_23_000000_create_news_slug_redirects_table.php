<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_slug')->unique();
            $table->string('to_slug')->nullable();

            // Wenn true: Ziel existiert nicht mehr oder wird nicht mehr ersetzt -> 410 Gone.
            $table->boolean('is_gone')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_slug_redirects');
    }
};
