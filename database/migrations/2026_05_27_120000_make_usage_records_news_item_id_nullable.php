<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('usage_records') || ! Schema::hasColumn('usage_records', 'news_item_id')) {
            return;
        }

        Schema::table('usage_records', function (Blueprint $table) {
            $table->dropForeign(['news_item_id']);
            $table->unsignedBigInteger('news_item_id')->nullable()->change();
            $table->foreign('news_item_id')->references('id')->on('news_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('usage_records') || ! Schema::hasColumn('usage_records', 'news_item_id')) {
            return;
        }

        Schema::table('usage_records', function (Blueprint $table) {
            $table->dropForeign(['news_item_id']);
            $table->unsignedBigInteger('news_item_id')->nullable(false)->change();
            $table->foreign('news_item_id')->references('id')->on('news_items')->nullOnDelete();
        });
    }
};
