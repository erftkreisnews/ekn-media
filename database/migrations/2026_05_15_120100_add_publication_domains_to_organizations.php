<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            if (! Schema::hasColumn('organizations', 'publication_domains')) {
                $table->text('publication_domains')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('organizations', 'publication_domains')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropColumn('publication_domains');
            });
        }
    }
};
