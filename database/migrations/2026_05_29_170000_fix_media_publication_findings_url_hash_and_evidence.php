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

        Schema::table('media_publication_findings', function (Blueprint $table) {
            $table->char('url_hash', 64)->nullable()->change();
        });

        if (! Schema::hasColumn('media_publication_findings', 'evidence_dossier_status')) {
            Schema::table('media_publication_findings', function (Blueprint $table) {
                $table->string('evidence_dossier_status', 32)->nullable()->after('created_by');
                $table->string('evidence_dossier_path', 512)->nullable()->after('evidence_dossier_status');
                $table->json('evidence_dossier_manifest')->nullable()->after('evidence_dossier_path');
                $table->timestamp('evidence_dossier_built_at')->nullable()->after('evidence_dossier_manifest');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('media_publication_findings')) {
            return;
        }

        if (Schema::hasColumn('media_publication_findings', 'evidence_dossier_status')) {
            Schema::table('media_publication_findings', function (Blueprint $table) {
                $table->dropColumn([
                    'evidence_dossier_status',
                    'evidence_dossier_path',
                    'evidence_dossier_manifest',
                    'evidence_dossier_built_at',
                ]);
            });
        }

        Schema::table('media_publication_findings', function (Blueprint $table) {
            $table->char('url_hash', 40)->nullable()->change();
        });
    }
};
