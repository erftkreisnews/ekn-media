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
            if (! Schema::hasColumn('planned_events', 'schedule_pdf_extracted_text')) {
                $table->longText('schedule_pdf_extracted_text')->nullable()->after('schedule_pdf_original_name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('planned_events')) {
            return;
        }

        Schema::table('planned_events', function (Blueprint $table) {
            if (Schema::hasColumn('planned_events', 'schedule_pdf_extracted_text')) {
                $table->dropColumn('schedule_pdf_extracted_text');
            }
        });
    }
};
