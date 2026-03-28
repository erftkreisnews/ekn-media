<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Erforderlich vor add_billing_and_lexware_fields_to_products_table (dort wird aus organizations gelesen).
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            if (! Schema::hasColumn('organizations', 'buyer_reference')) {
                $table->string('buyer_reference')->nullable()->after('active');
            }
            if (! Schema::hasColumn('organizations', 'lexware_contact_id')) {
                $table->string('lexware_contact_id')->nullable()->after('buyer_reference');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('organizations', 'buyer_reference')) {
                $cols[] = 'buyer_reference';
            }
            if (Schema::hasColumn('organizations', 'lexware_contact_id')) {
                $cols[] = 'lexware_contact_id';
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
