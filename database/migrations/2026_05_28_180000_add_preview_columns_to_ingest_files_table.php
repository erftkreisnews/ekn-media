<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ingest_files')) {
            return;
        }

        Schema::table('ingest_files', function (Blueprint $table) {
            if (! Schema::hasColumn('ingest_files', 'preview_path')) {
                $table->string('preview_path', 500)->nullable()->after('thumb_path');
            }
            if (! Schema::hasColumn('ingest_files', 'preview_status')) {
                $table->string('preview_status', 32)->nullable()->after('preview_path');
            }
            if (! Schema::hasColumn('ingest_files', 'preview_error_message')) {
                $table->text('preview_error_message')->nullable()->after('preview_status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ingest_files')) {
            return;
        }

        Schema::table('ingest_files', function (Blueprint $table) {
            $cols = [];
            foreach (['preview_error_message', 'preview_status', 'preview_path'] as $col) {
                if (Schema::hasColumn('ingest_files', $col)) {
                    $cols[] = $col;
                }
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
