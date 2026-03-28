<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_events', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_events', 'download_media_type')) {
                $table->string('download_media_type', 16)->nullable()->after('media_id');
            }
            if (! Schema::hasColumn('delivery_events', 'download_media_label')) {
                $table->string('download_media_label', 512)->nullable()->after('download_media_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_events', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_events', 'download_media_label')) {
                $table->dropColumn('download_media_label');
            }
            if (Schema::hasColumn('delivery_events', 'download_media_type')) {
                $table->dropColumn('download_media_type');
            }
        });
    }
};
