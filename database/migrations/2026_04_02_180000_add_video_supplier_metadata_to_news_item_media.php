<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_item_media', function (Blueprint $table) {
            $table->string('metadata_location', 512)->nullable()->after('media_keywords');
            $table->date('metadata_recorded_at')->nullable()->after('metadata_location');
        });
    }

    public function down(): void
    {
        Schema::table('news_item_media', function (Blueprint $table) {
            $table->dropColumn(['metadata_location', 'metadata_recorded_at']);
        });
    }
};
