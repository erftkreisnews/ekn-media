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

        if (! Schema::hasColumn('media_publication_findings', 'authority_access_file_reference')) {
            Schema::table('media_publication_findings', function (Blueprint $table) {
                $table->string('authority_access_file_reference', 128)->nullable()->after('authority_access_recipient');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('media_publication_findings', 'authority_access_file_reference')) {
            Schema::table('media_publication_findings', function (Blueprint $table) {
                $table->dropColumn('authority_access_file_reference');
            });
        }
    }
};
