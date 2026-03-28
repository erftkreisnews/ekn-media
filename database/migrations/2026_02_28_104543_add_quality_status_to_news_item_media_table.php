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
            $table->string('quality_status', 20)->nullable()->after('sort_order'); // ok, warning, fail
            $table->text('quality_notes')->nullable()->after('quality_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_item_media', function (Blueprint $table) {
            $table->dropColumn(['quality_status', 'quality_notes']);
        });
    }
};
