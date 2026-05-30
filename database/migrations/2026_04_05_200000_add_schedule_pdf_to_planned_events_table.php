<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('planned_events')) {
            return;
        }

        Schema::table('planned_events', function (Blueprint $table) {
            if (! Schema::hasColumn('planned_events', 'schedule_pdf_path')) {
                $table->string('schedule_pdf_path', 512)->nullable()->after('sort_order');
            }
            if (! Schema::hasColumn('planned_events', 'schedule_pdf_original_name')) {
                $table->string('schedule_pdf_original_name', 255)->nullable()->after('schedule_pdf_path');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('planned_events')) {
            return;
        }

        Schema::table('planned_events', function (Blueprint $table) {
            if (Schema::hasColumn('planned_events', 'schedule_pdf_original_name')) {
                $table->dropColumn('schedule_pdf_original_name');
            }
            if (Schema::hasColumn('planned_events', 'schedule_pdf_path')) {
                $table->dropColumn('schedule_pdf_path');
            }
        });
    }
};
