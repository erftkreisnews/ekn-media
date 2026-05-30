<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_item_witness_links', function (Blueprint $table) {
            $table->dropForeign(['news_item_id']);
            $table->unsignedBigInteger('news_item_id')->nullable()->change();
            $table->foreign('news_item_id')->references('id')->on('news_items')->nullOnDelete();
        });

        Schema::table('news_item_witness_submissions', function (Blueprint $table) {
            $table->dropForeign(['news_item_id']);
            $table->unsignedBigInteger('news_item_id')->nullable()->change();
            $table->foreign('news_item_id')->references('id')->on('news_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('news_item_witness_submissions', function (Blueprint $table) {
            $table->dropForeign(['news_item_id']);
            $table->unsignedBigInteger('news_item_id')->nullable(false)->change();
            $table->foreign('news_item_id')->references('id')->on('news_items')->cascadeOnDelete();
        });

        Schema::table('news_item_witness_links', function (Blueprint $table) {
            $table->dropForeign(['news_item_id']);
            $table->unsignedBigInteger('news_item_id')->nullable(false)->change();
            $table->foreign('news_item_id')->references('id')->on('news_items')->cascadeOnDelete();
        });
    }
};
