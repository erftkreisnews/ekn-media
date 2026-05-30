<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('squads', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 128)->unique();
            $table->string('name', 512);
            $table->boolean('is_active')->default(true);
            $table->boolean('include_in_media_ai')->default(true);
            $table->timestamps();
        });

        Schema::create('squad_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('squad_id')->constrained('squads')->cascadeOnDelete();
            $table->string('shirt_number', 20)->nullable();
            $table->string('full_name', 255);
            $table->string('position_label', 120)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('squad_players');
        Schema::dropIfExists('squads');
    }
};
