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
                $table->string('ai_status', 32)->nullable()->after('duration_s');
            }

            if (! Schema::hasColumn('news_item_media', 'ai_error')) {
                $table->text('ai_error')->nullable()->after('ai_payload');
            }

            if (! Schema::hasColumn('news_item_media', 'ai_started_at')) {
                $table->timestamp('ai_started_at')->nullable()->after('ai_status');
            }

            if (! Schema::hasColumn('news_item_media', 'ai_finished_at')) {
                $table->timestamp('ai_finished_at')->nullable()->after('ai_started_at');
            }

            if (! Schema::hasColumn('news_item_media', 'ai_payload')) {
                $table->json('ai_payload')->nullable()->after('ai_model');
            }

            $table->index('ai_status', 'news_item_media_ai_status_index');
            $table->index('ai_finished_at', 'news_item_media_ai_finished_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('news_item_media', function (Blueprint $table) {
            if (Schema::hasColumn('news_item_media', 'ai_status')) {
                $table->dropIndex('news_item_media_ai_status_index');
            }
            if (Schema::hasColumn('news_item_media', 'ai_finished_at')) {
                $table->dropIndex('news_item_media_ai_finished_at_index');
            }

            if (Schema::hasColumn('news_item_media', 'ai_started_at')) {
                $table->dropColumn('ai_started_at');
            }
            if (Schema::hasColumn('news_item_media', 'ai_finished_at')) {
                $table->dropColumn('ai_finished_at');
            }
            if (Schema::hasColumn('news_item_media', 'ai_error')) {
                $table->dropColumn('ai_error');
            }
            if (Schema::hasColumn('news_item_media', 'ai_payload')) {
                $table->dropColumn('ai_payload');
            }
            if (Schema::hasColumn('news_item_media', 'ai_status')) {
                $table->dropColumn('ai_status');
            }
        });
    }
};
