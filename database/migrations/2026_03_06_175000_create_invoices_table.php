<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices')) {
            return;
        }

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lexware_invoice_id')->nullable();
            $table->string('voucher_number')->nullable();
            $table->string('status')->nullable();
            $table->decimal('total_net', 12, 2)->nullable();
            $table->decimal('total_vat', 12, 2)->nullable();
            $table->decimal('total_gross', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->date('voucher_date')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
