<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('news_items', 'is_wdr_job')) {
            return;
        }

        Schema::table('news_items', function (Blueprint $table) {
            $table->boolean('is_wdr_job')->default(false)->change();
        });

        DB::table('news_items')
            ->where(function ($q) {
                $q->whereNull('moid')->orWhereRaw("TRIM(moid) = ''");
            })
            ->update(['is_wdr_job' => false]);

        DB::table('news_items')
            ->whereNotNull('moid')
            ->whereRaw("TRIM(moid) <> ''")
            ->update(['is_wdr_job' => true]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('news_items', 'is_wdr_job')) {
            return;
        }

        Schema::table('news_items', function (Blueprint $table) {
            $table->boolean('is_wdr_job')->default(true)->change();
        });
    }
};
