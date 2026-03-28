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
        Schema::table('news_items', function (Blueprint $table) {
            $table->boolean('is_breaking')->default(false)->after('region');
            $table->boolean('planned_video_upload')->default(false)->after('is_breaking');
            $table->boolean('liveu_on_site')->default(false)->after('planned_video_upload');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_items', function (Blueprint $table) {
            $table->dropColumn(['is_breaking', 'planned_video_upload', 'liveu_on_site']);
        });
    }
};
