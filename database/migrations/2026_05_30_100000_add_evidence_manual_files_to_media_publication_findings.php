<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('media_publication_findings')) {
            return;
        }

        if (! Schema::hasColumn('media_publication_findings', 'evidence_manual_files')) {
            Schema::table('media_publication_findings', function (Blueprint $table) {
                $table->json('evidence_manual_files')->nullable()->after('evidence_dossier_built_at');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('media_publication_findings')) {
            return;
        }

        if (Schema::hasColumn('media_publication_findings', 'evidence_manual_files')) {
            Schema::table('media_publication_findings', function (Blueprint $table) {
                $table->dropColumn('evidence_manual_files');
            });
        }
    }
};
