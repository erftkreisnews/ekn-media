<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_items', function (Blueprint $table) {
            if (! Schema::hasColumn('news_items', 'media_ai_context')) {
                $table->text('media_ai_context')->nullable()->after('author_credit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('news_items', function (Blueprint $table) {
            if (Schema::hasColumn('news_items', 'media_ai_context')) {
                $table->dropColumn('media_ai_context');
            }
        });
    }
};
