<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('planned_events')) {
            Schema::create('planned_events', function (Blueprint $table) {
                $table->id();
                $table->string('name', 512);
                $table->string('date_label', 255)->nullable();
                $table->date('starts_at')->nullable();
                $table->date('ends_at')->nullable();
                $table->text('ai_context')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('planned_event_teams')) {
            Schema::create('planned_event_teams', function (Blueprint $table) {
                $table->id();
                $table->foreignId('planned_event_id')->constrained('planned_events')->cascadeOnDelete();
                $table->string('name', 255);
                $table->text('notes')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('news_items') && ! Schema::hasColumn('news_items', 'planned_event_id')) {
            Schema::table('news_items', function (Blueprint $table) {
                $table->foreignId('planned_event_id')
                    ->nullable()
                    ->after('media_ai_context')
                    ->constrained('planned_events')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('news_items') && Schema::hasColumn('news_items', 'planned_event_id')) {
            Schema::table('news_items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('planned_event_id');
            });
        }

        Schema::dropIfExists('planned_event_teams');
        Schema::dropIfExists('planned_events');
    }
};
