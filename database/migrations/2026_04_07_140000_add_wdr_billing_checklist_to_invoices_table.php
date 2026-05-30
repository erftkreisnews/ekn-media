<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }
        if (Schema::hasColumn('invoices', 'wdr_billing_checklist')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->json('wdr_billing_checklist')->nullable()->after('meta');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }
        if (! Schema::hasColumn('invoices', 'wdr_billing_checklist')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('wdr_billing_checklist');
        });
    }
};
