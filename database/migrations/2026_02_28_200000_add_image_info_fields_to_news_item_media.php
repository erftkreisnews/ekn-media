<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_item_media', function (Blueprint $table) {
            $table->string('image_title', 255)->nullable()->after('caption');
            $table->string('photographer', 255)->nullable()->after('image_title');
            $table->string('media_keywords', 512)->nullable()->after('photographer');
            $table->text('description')->nullable()->after('media_keywords');
        });

        Schema::table('news_item_media', function (Blueprint $table) {
            $table->string('caption', 1800)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('news_item_media', function (Blueprint $table) {
            $table->dropColumn(['image_title', 'photographer', 'media_keywords', 'description']);
        });
        Schema::table('news_item_media', function (Blueprint $table) {
            $table->string('caption', 512)->nullable()->change();
        });
    }
};
