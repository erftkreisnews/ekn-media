<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_item_media', function (Blueprint $table) {
            if (! Schema::hasColumn('news_item_media', 'city')) {
                $table->string('city', 255)->nullable()->after('description');
            }
            if (! Schema::hasColumn('news_item_media', 'state')) {
                $table->string('state', 255)->nullable()->after('city');
            }
            if (! Schema::hasColumn('news_item_media', 'country')) {
                $table->string('country', 255)->nullable()->after('state');
            }
            if (! Schema::hasColumn('news_item_media', 'country_code')) {
                $table->string('country_code', 2)->nullable()->after('country');
            }
            if (! Schema::hasColumn('news_item_media', 'capture_time')) {
                $table->dateTime('capture_time')->nullable()->after('country_code');
            }
            if (! Schema::hasColumn('news_item_media', 'credit')) {
                $table->string('credit', 255)->nullable()->after('capture_time');
            }
            if (! Schema::hasColumn('news_item_media', 'copyright')) {
                $table->string('copyright', 512)->nullable()->after('credit');
            }
            if (! Schema::hasColumn('news_item_media', 'source')) {
                $table->string('source', 255)->nullable()->after('copyright');
            }
        });
    }

    public function down(): void
    {
        Schema::table('news_item_media', function (Blueprint $table) {
            foreach (['city', 'state', 'country', 'country_code', 'capture_time', 'credit', 'copyright', 'source'] as $col) {
                if (Schema::hasColumn('news_item_media', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
