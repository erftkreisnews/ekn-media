<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_item_media', function (Blueprint $table) {
            $table->string('caption', 512)->nullable()->after('original_name');
        });
    }

    public function down(): void
    {
        Schema::table('news_item_media', function (Blueprint $table) {
            $table->dropColumn('caption');
        });
    }
};
