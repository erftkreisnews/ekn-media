<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_items', function (Blueprint $table) {
            $table->timestamp('event_at')->nullable()->after('published_at');
            $table->string('source_type', 64)->nullable()->after('event_at');
            $table->string('source_name', 255)->nullable()->after('source_type');
            $table->string('verification_status', 32)->nullable()->after('source_name');
            $table->string('update_type', 32)->nullable()->after('verification_status');
        });
    }

    public function down(): void
    {
        Schema::table('news_items', function (Blueprint $table) {
            $table->dropColumn([
                'event_at',
                'source_type',
                'source_name',
                'verification_status',
                'update_type',
            ]);
        });
    }
};
