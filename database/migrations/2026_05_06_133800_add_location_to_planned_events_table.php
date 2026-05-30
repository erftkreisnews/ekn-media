<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('planned_events') && ! Schema::hasColumn('planned_events', 'location')) {
            Schema::table('planned_events', function (Blueprint $table): void {
                $table->string('location', 255)->nullable()->after('date_label');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('planned_events') && Schema::hasColumn('planned_events', 'location')) {
            Schema::table('planned_events', function (Blueprint $table): void {
                $table->dropColumn('location');
            });
        }
    }
};
