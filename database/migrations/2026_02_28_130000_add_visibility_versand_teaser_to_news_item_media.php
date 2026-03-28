<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_item_media', function (Blueprint $table) {
            $table->boolean('is_visible')->default(true)->after('sort_order');
            $table->boolean('versand')->default(false)->after('is_visible');
            $table->boolean('is_teaser')->default(false)->after('versand');
        });
    }

    public function down(): void
    {
        Schema::table('news_item_media', function (Blueprint $table) {
            $table->dropColumn(['is_visible', 'versand', 'is_teaser']);
        });
    }
};
