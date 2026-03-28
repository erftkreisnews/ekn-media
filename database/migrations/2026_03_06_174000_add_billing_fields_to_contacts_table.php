<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('contacts', 'use_for_invoice')) {
                $table->boolean('use_for_invoice')->default(false)->after('role');
            }
            if (! Schema::hasColumn('contacts', 'billing_department')) {
                $table->string('billing_department')->nullable()->after('use_for_invoice');
            }
            if (! Schema::hasColumn('contacts', 'billing_type')) {
                $table->string('billing_type')->nullable()->after('billing_department');
            }
        });
    }

    public function down(): void
    {
        $drops = array_filter([
            Schema::hasColumn('contacts', 'use_for_invoice') ? 'use_for_invoice' : null,
            Schema::hasColumn('contacts', 'billing_department') ? 'billing_department' : null,
            Schema::hasColumn('contacts', 'billing_type') ? 'billing_type' : null,
        ]);
        if ($drops !== []) {
            Schema::table('contacts', function (Blueprint $table) use ($drops) {
                $table->dropColumn($drops);
            });
        }
    }
};
