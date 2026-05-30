<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('planned_event_teams')) {
            return;
        }

        if (! Schema::hasColumn('planned_event_teams', 'reference_image_path')) {
            Schema::table('planned_event_teams', function (Blueprint $table) {
                $table->string('reference_image_path', 512)->nullable()->after('notes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('planned_event_teams') && Schema::hasColumn('planned_event_teams', 'reference_image_path')) {
            Schema::table('planned_event_teams', function (Blueprint $table) {
                $table->dropColumn('reference_image_path');
            });
        }
    }
};
