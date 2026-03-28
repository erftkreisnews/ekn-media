<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_item_media', function (Blueprint $table) {
            if (! Schema::hasColumn('news_item_media', 'ai_status')) {
                $table->string('ai_status', 20)->nullable()->after('duration_s');
            }

            if (! Schema::hasColumn('news_item_media', 'ai_error')) {
                // Falls ai_error aus älterer Migration noch nicht existiert
                $table->text('ai_error')->nullable()->after('ai_payload');
            }

            if (! Schema::hasColumn('news_item_media', 'ai_started_at')) {
                $table->timestamp('ai_started_at')->nullable()->after('ai_status');
            }

            if (! Schema::hasColumn('news_item_media', 'ai_finished_at')) {
                $table->timestamp('ai_finished_at')->nullable()->after('ai_started_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('news_item_media', function (Blueprint $table) {
            $columns = ['ai_started_at', 'ai_finished_at', 'ai_status', 'ai_error'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('news_item_media', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
