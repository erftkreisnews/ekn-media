<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingest_files', function (Blueprint $table): void {
            if (! Schema::hasColumn('ingest_files', 'thumb_path')) {
                $table->string('thumb_path', 2048)->nullable()->after('absolute_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ingest_files', function (Blueprint $table): void {
            if (Schema::hasColumn('ingest_files', 'thumb_path')) {
                $table->dropColumn('thumb_path');
            }
        });
    }
};
