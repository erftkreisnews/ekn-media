<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('delivery_events')) {
            return;
        }
        Schema::create('delivery_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('delivery_id');
            $table->string('event_type', 32); // opened, confirmed, download, revoked
            $table->foreignId('media_id')->nullable()->constrained('news_item_media')->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('ip_hash')->nullable();
            $table->string('ua_hash')->nullable();
            $table->timestamp('created_at');

            $table->foreign('delivery_id')->references('id')->on('deliveries')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_events');
    }
};
