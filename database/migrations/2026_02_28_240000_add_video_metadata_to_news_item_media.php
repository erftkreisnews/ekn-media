<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('news_item_media', function (Blueprint $table) {
            $table->unsignedInteger('width')->nullable()->after('path');
            $table->unsignedInteger('height')->nullable()->after('width');
            $table->decimal('fps', 10, 4)->nullable()->after('height');
            $table->unsignedBigInteger('bitrate_bps')->nullable()->after('fps');
            $table->string('codec', 64)->nullable()->after('bitrate_bps');
            $table->string('field_order', 32)->nullable()->after('codec');
            $table->decimal('duration_s', 12, 4)->nullable()->after('field_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_item_media', function (Blueprint $table) {
            $table->dropColumn([
                'width',
                'height',
                'fps',
                'bitrate_bps',
                'codec',
                'field_order',
                'duration_s',
            ]);
        });
    }
};
