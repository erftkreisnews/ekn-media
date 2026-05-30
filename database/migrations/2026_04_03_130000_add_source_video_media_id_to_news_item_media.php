<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_item_media', function (Blueprint $table) {
            if (! Schema::hasColumn('news_item_media', 'source_video_media_id')) {
                $table->foreignId('source_video_media_id')
                    ->nullable()
                    ->after('news_item_id')
                    ->constrained('news_item_media')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('news_item_media', function (Blueprint $table) {
            if (Schema::hasColumn('news_item_media', 'source_video_media_id')) {
                $table->dropConstrainedForeignId('source_video_media_id');
            }
        });
    }
};
