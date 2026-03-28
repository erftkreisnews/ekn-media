<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_item_media', function (Blueprint $table) {
            $table->string('ai_status', 20)->nullable()->after('duration_s');
            $table->timestamp('ai_suggested_at')->nullable()->after('ai_status');
            $table->string('ai_model', 64)->nullable()->after('ai_suggested_at');
            $table->json('ai_payload')->nullable()->after('ai_model');
            $table->text('ai_error')->nullable()->after('ai_payload');
        });
    }

    public function down(): void
    {
        Schema::table('news_item_media', function (Blueprint $table) {
            $table->dropColumn(['ai_status', 'ai_suggested_at', 'ai_model', 'ai_payload', 'ai_error']);
        });
    }
};
