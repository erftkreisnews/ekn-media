<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_items', function (Blueprint $table) {
            $table->string('subheadline', 512)->nullable()->after('teaser');
            $table->dateTime('embargo_at')->nullable()->after('published_at');
            $table->string('author_credit', 255)->nullable()->after('author_id');
        });
    }

    public function down(): void
    {
        Schema::table('news_items', function (Blueprint $table) {
            $table->dropColumn(['subheadline', 'embargo_at', 'author_credit']);
        });
    }
};
