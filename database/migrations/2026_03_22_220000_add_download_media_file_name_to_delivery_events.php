<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_events', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_events', 'download_media_file_name')) {
                $table->string('download_media_file_name', 512)->nullable()->after('download_media_label');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_events', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_events', 'download_media_file_name')) {
                $table->dropColumn('download_media_file_name');
            }
        });
    }
};
