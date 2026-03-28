<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_item_media', function (Blueprint $table) {
            if (! Schema::hasColumn('news_item_media', 'ai_attempts')) {
                $table->unsignedSmallInteger('ai_attempts')
                    ->default(0)
                    ->after('ai_status');
            }

            if (! Schema::hasColumn('news_item_media', 'ai_last_error')) {
                $table->text('ai_last_error')
                    ->nullable()
                    ->after('ai_error');
            }
        });
    }

    public function down(): void
    {
        Schema::table('news_item_media', function (Blueprint $table) {
            if (Schema::hasColumn('news_item_media', 'ai_attempts')) {
                $table->dropColumn('ai_attempts');
            }
            if (Schema::hasColumn('news_item_media', 'ai_last_error')) {
                $table->dropColumn('ai_last_error');
            }
        });
    }
};
