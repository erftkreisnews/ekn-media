<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('external_reference_label')->nullable()->after('active');
            $table->string('external_author_id')->nullable()->after('external_reference_label');
            $table->string('external_supplier_id')->nullable()->after('external_author_id');
            $table->string('external_vendor_code')->nullable()->after('external_supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'external_reference_label',
                'external_author_id',
                'external_supplier_id',
                'external_vendor_code',
            ]);
        });
    }
};
