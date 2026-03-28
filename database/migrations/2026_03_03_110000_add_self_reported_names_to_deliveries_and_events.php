<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            if (! Schema::hasColumn('deliveries', 'self_reported_organization_name')) {
                $table->string('self_reported_organization_name')->nullable()->after('allowed_organization_id');
            }
            if (! Schema::hasColumn('deliveries', 'self_reported_product_name')) {
                $table->string('self_reported_product_name')->nullable()->after('self_reported_organization_name');
            }
        });

        Schema::table('delivery_events', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_events', 'self_reported_organization_name')) {
                $table->string('self_reported_organization_name')->nullable()->after('product_id');
            }
            if (! Schema::hasColumn('delivery_events', 'self_reported_product_name')) {
                $table->string('self_reported_product_name')->nullable()->after('self_reported_organization_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['self_reported_organization_name', 'self_reported_product_name']);
        });
        Schema::table('delivery_events', function (Blueprint $table) {
            $table->dropColumn(['self_reported_organization_name', 'self_reported_product_name']);
        });
    }
};
