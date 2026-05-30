<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            if (! Schema::hasColumn('deliveries', 'is_update_delivery')) {
                $table->boolean('is_update_delivery')->default(false)->after('allowed_organization_id');
            }
            if (! Schema::hasColumn('deliveries', 'update_baseline_delivery_at')) {
                $table->dateTime('update_baseline_delivery_at')->nullable()->after('is_update_delivery');
            }
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            if (Schema::hasColumn('deliveries', 'update_baseline_delivery_at')) {
                $table->dropColumn('update_baseline_delivery_at');
            }
            if (Schema::hasColumn('deliveries', 'is_update_delivery')) {
                $table->dropColumn('is_update_delivery');
            }
        });
    }
};
