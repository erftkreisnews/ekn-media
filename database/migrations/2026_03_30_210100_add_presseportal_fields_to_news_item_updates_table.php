<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_item_updates', function (Blueprint $table) {
            $table->string('presseportal_url', 2000)->nullable()->after('body');
            $table->unsignedBigInteger('presseportal_story_id')->nullable()->after('presseportal_url');
            $table->unsignedInteger('presseportal_office_id')->nullable()->after('presseportal_story_id');
        });
    }

    public function down(): void
    {
        Schema::table('news_item_updates', function (Blueprint $table) {
            $table->dropColumn([
                'presseportal_url',
                'presseportal_story_id',
                'presseportal_office_id',
            ]);
        });
    }
};
