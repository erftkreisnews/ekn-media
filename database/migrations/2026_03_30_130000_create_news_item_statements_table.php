<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_item_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_item_id')->constrained('news_items')->cascadeOnDelete();
            $table->string('source_type', 64);
            $table->string('source_label')->nullable();
            $table->string('statement_type', 32);
            $table->longText('transcript');
            $table->text('summary')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_publishable')->default(true);
            $table->boolean('show_in_mail')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['news_item_id', 'is_active'], 'nis_news_active_idx');
            $table->index('source_type', 'nis_source_type_idx');
            $table->index('received_at', 'nis_received_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_item_statements');
    }
};
