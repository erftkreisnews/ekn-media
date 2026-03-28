<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage_records', function (Blueprint $table) {
            $table->string('usage_format')->nullable()->after('article_url');
            $table->string('usage_rights')->nullable()->after('usage_format');
            $table->string('reference_code')->nullable()->after('usage_rights');
            $table->string('line_item_note')->nullable()->after('reference_code');
        });
    }

    public function down(): void
    {
        Schema::table('usage_records', function (Blueprint $table) {
            $table->dropColumn([
                'usage_format',
                'usage_rights',
                'reference_code',
                'line_item_note',
            ]);
        });
    }
};
