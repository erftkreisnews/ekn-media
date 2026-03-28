<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_item_media', function (Blueprint $table) {
            $table->string('original_path', 500)->nullable()->after('preview_path');
            $table->string('redacted_path', 500)->nullable()->after('original_path');
            $table->string('redaction_status', 20)->nullable()->after('redacted_path'); // null = legacy (path ausliefern), pending, done, failed, disabled
            $table->string('redaction_method', 30)->nullable()->after('redaction_status');
            $table->json('redaction_boxes')->nullable()->after('redaction_method');
            $table->timestamp('redacted_at')->nullable()->after('redaction_boxes');
        });
    }

    public function down(): void
    {
        Schema::table('news_item_media', function (Blueprint $table) {
            $table->dropColumn([
                'original_path',
                'redacted_path',
                'redaction_status',
                'redaction_method',
                'redaction_boxes',
                'redacted_at',
            ]);
        });
    }
};
