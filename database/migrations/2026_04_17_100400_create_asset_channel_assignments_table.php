<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_channel_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_asset_id')->constrained('news_item_media')->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('visibility', ['public', 'customer_only', 'internal'])->default('public');
            $table->enum('publish_state', ['draft', 'ready', 'published', 'archived'])->default('draft');
            $table->timestamps();

            $table->unique(
                ['media_asset_id', 'brand_id', 'channel_id', 'project_id'],
                'uniq_asset_brand_channel_project'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_channel_assignments');
    }
};
