<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'allowed_delivery_organization_ids')) {
                $table->json('allowed_delivery_organization_ids')->nullable()->after('password');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'allowed_delivery_organization_ids')) {
                $table->dropColumn('allowed_delivery_organization_ids');
            }
        });
    }
};
