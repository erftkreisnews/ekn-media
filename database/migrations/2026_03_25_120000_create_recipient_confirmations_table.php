<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('recipient_confirmations')) {
            return;
        }

        Schema::create('recipient_confirmations', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();

            // Für "known customers": org/product IDs (bevorzugt).
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();

            // Für "self reported" Fallback (wenn keine org/product IDs genutzt werden können).
            $table->string('self_reported_organization_name')->nullable();
            $table->string('self_reported_product_name')->nullable();

            $table->dateTime('last_confirmed_at');
            $table->dateTime('confirmed_until')->index();
            $table->timestamps();

            $table->index(['email', 'organization_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipient_confirmations');
    }
};
