<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('media_publication_findings')) {
            return;
        }

        Schema::create('media_publication_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_item_id')->nullable()->constrained('news_items')->nullOnDelete();
            $table->foreignId('news_item_media_id')->nullable()->constrained('news_item_media')->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('kind', 32);
            $table->string('url', 2048);
            $table->char('url_hash', 64)->nullable();
            $table->string('page_title')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('auto_detected')->default(false);
            $table->boolean('confirmed')->default(false);
            $table->string('scan_source', 32)->nullable();
            $table->timestamp('found_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['news_item_media_id', 'url_hash']);
            $table->index('kind');
            $table->index('confirmed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_publication_findings');
    }
};
