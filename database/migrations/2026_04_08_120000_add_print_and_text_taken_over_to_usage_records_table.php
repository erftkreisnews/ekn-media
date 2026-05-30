<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage_records', function (Blueprint $table) {
            if (! Schema::hasColumn('usage_records', 'print_copies')) {
                $table->unsignedInteger('print_copies')->nullable()->after('radio_minutes');
            }
            if (! Schema::hasColumn('usage_records', 'text_taken_over')) {
                $table->boolean('text_taken_over')->default(false)->after('article_url');
            }
            if (! Schema::hasColumn('usage_records', 'text_taken_over_excerpt')) {
                $table->text('text_taken_over_excerpt')->nullable()->after('text_taken_over');
            }
        });
    }

    public function down(): void
    {
        Schema::table('usage_records', function (Blueprint $table) {
            $drops = [];
            if (Schema::hasColumn('usage_records', 'print_copies')) {
                $drops[] = 'print_copies';
            }
            if (Schema::hasColumn('usage_records', 'text_taken_over_excerpt')) {
                $drops[] = 'text_taken_over_excerpt';
            }
            if (Schema::hasColumn('usage_records', 'text_taken_over')) {
                $drops[] = 'text_taken_over';
            }
            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};
