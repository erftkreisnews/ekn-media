<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_slug_redirects', function (Blueprint $table) {
            $table->index('to_slug', 'news_slug_redirects_to_slug_index');
            $table->index('is_gone', 'news_slug_redirects_is_gone_index');
        });
    }

    public function down(): void
    {
        Schema::table('news_slug_redirects', function (Blueprint $table) {
            $table->dropIndex('news_slug_redirects_to_slug_index');
            $table->dropIndex('news_slug_redirects_is_gone_index');
        });
    }
};
