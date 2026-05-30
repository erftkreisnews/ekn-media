<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_item_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_item_id')->constrained('news_items')->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('title')->nullable();
            $table->longText('body');
            $table->string('source_type', 64)->nullable();
            $table->string('source_label')->nullable();
            $table->dateTime('happened_at')->nullable();
            $table->boolean('show_in_mail')->default(false);
            $table->boolean('show_in_article')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('statement_id')->nullable()->constrained('news_item_statements')->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['news_item_id', 'is_active'], 'niu_news_active_idx');
            $table->index('type', 'niu_type_idx');
            $table->index('happened_at', 'niu_happened_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_item_updates');
    }
};
