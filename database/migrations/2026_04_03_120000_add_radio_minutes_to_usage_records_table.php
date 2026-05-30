<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage_records', function (Blueprint $table) {
            if (! Schema::hasColumn('usage_records', 'radio_minutes')) {
                $table->decimal('radio_minutes', 10, 2)->nullable()->after('video_minutes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('usage_records', function (Blueprint $table) {
            if (Schema::hasColumn('usage_records', 'radio_minutes')) {
                $table->dropColumn('radio_minutes');
            }
        });
    }
};
