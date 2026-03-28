<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_run_items', function (Blueprint $table) {
            $table->dropForeign(['news_item_media_id']);
        });
        DB::statement('ALTER TABLE delivery_run_items MODIFY news_item_media_id BIGINT UNSIGNED NULL');
        Schema::table('delivery_run_items', function (Blueprint $table) {
            $table->foreign('news_item_media_id')->references('id')->on('news_item_media')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_run_items', function (Blueprint $table) {
            $table->dropForeign(['news_item_media_id']);
        });
        DB::statement('ALTER TABLE delivery_run_items MODIFY news_item_media_id BIGINT UNSIGNED NOT NULL');
        Schema::table('delivery_run_items', function (Blueprint $table) {
            $table->foreign('news_item_media_id')->references('id')->on('news_item_media')->cascadeOnDelete();
        });
    }
};
