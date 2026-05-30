<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_suggestions')) {
            return;
        }

        Schema::create('event_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64);
            $table->string('external_id', 128);
            $table->string('title', 512);
            $table->text('description')->nullable();
            $table->string('info_url', 1024)->nullable();
            $table->string('image_url', 1024)->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('venue_name', 512)->nullable();
            $table->string('venue_street', 255)->nullable();
            $table->string('venue_postal_code', 32)->nullable();
            $table->string('venue_city', 120)->nullable();
            $table->string('venue_state', 120)->nullable();
            $table->string('venue_country', 120)->nullable()->default('Deutschland');
            $table->string('venue_country_code', 2)->nullable()->default('DE');
            $table->string('category', 255)->nullable();
            $table->string('status', 32)->default('pending');
            $table->foreignId('planned_event_id')->nullable()->constrained('planned_events')->nullOnDelete();
            $table->timestamp('fetched_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->index(['status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_suggestions');
    }
};
