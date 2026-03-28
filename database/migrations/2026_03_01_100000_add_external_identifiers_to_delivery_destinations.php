<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_destinations', function (Blueprint $table) {
            $table->string('external_reference_label')->nullable()->after('timeout');
            $table->string('external_author_id')->nullable()->after('external_reference_label');
            $table->string('external_supplier_id')->nullable()->after('external_author_id');
            $table->string('external_vendor_code')->nullable()->after('external_supplier_id');
            $table->boolean('include_in_email')->default(true)->after('external_vendor_code');
            $table->boolean('include_in_filename')->default(false)->after('include_in_email');
            $table->boolean('generate_sidecar')->default(false)->after('include_in_filename');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_destinations', function (Blueprint $table) {
            $table->dropColumn([
                'external_reference_label',
                'external_author_id',
                'external_supplier_id',
                'external_vendor_code',
                'include_in_email',
                'include_in_filename',
                'generate_sidecar',
            ]);
        });
    }
};
