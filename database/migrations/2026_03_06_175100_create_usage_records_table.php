<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('usage_records')) {
            return;
        }

        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_item_id')->nullable()->constrained('news_items')->nullOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('billing_department')->nullable();
            $table->string('billing_type')->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->date('used_at')->nullable();
            $table->unsignedInteger('images_count')->default(0);
            $table->decimal('video_minutes', 10, 2)->nullable();
            $table->decimal('price_per_image', 12, 2)->nullable();
            $table->decimal('price_per_minute', 12, 2)->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->string('article_url')->nullable();
            $table->boolean('auto_detected')->default(false);
            $table->boolean('confirmed')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_records');
    }
};
