<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('planned_events') && ! Schema::hasColumn('planned_events', 'assigned_user_ids')) {
            Schema::table('planned_events', function (Blueprint $table): void {
                $table->json('assigned_user_ids')->nullable()->after('sort_order');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('planned_events') && Schema::hasColumn('planned_events', 'assigned_user_ids')) {
            Schema::table('planned_events', function (Blueprint $table): void {
                $table->dropColumn('assigned_user_ids');
            });
        }
    }
};
