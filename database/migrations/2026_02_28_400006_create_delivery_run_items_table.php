<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_run_id')->constrained('delivery_runs')->cascadeOnDelete();
            $table->foreignId('news_item_media_id')->constrained('news_item_media')->cascadeOnDelete();
            $table->string('filename');
            $table->string('status', 32)->default('pending'); // pending, success, failed
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_run_items');
    }
};
