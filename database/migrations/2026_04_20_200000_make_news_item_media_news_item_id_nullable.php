<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('news_item_media') || ! Schema::hasColumn('news_item_media', 'news_item_id')) {
            return;
        }

        Schema::table('news_item_media', function (Blueprint $table) {
            try {
                $table->dropForeign(['news_item_id']);
            } catch (\Throwable $e) {
                // FK kann je nach Bestand bereits fehlen oder anders heißen.
            }
        });

        Schema::table('news_item_media', function (Blueprint $table) {
            $table->unsignedBigInteger('news_item_id')->nullable()->change();
            $table->foreign('news_item_id')->references('id')->on('news_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('news_item_media') || ! Schema::hasColumn('news_item_media', 'news_item_id')) {
            return;
        }

        Schema::table('news_item_media', function (Blueprint $table) {
            try {
                $table->dropForeign(['news_item_id']);
            } catch (\Throwable $e) {
                // ignore
            }
        });

        Schema::table('news_item_media', function (Blueprint $table) {
            $table->unsignedBigInteger('news_item_id')->nullable(false)->change();
            $table->foreign('news_item_id')->references('id')->on('news_items')->cascadeOnDelete();
        });
    }
};
