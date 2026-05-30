<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_item_witness_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('label', 191)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedSmallInteger('max_uploads')->default(50);
            $table->timestamps();
        });

        Schema::create('news_item_witness_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_item_witness_link_id')->constrained('news_item_witness_links')->cascadeOnDelete();
            $table->foreignId('news_item_id')->constrained()->cascadeOnDelete();
            $table->string('submitter_name', 191);
            $table->string('submitter_email', 191);
            $table->string('submitter_phone', 64)->nullable();
            $table->boolean('consent_terms')->default(false);
            $table->boolean('consent_rights')->default(false);
            $table->string('consent_text_version', 64);
            $table->string('consent_body_hash', 64);
            $table->string('stored_disk', 32);
            $table->string('stored_path', 512)->nullable();
            $table->string('original_filename', 255);
            $table->string('mime', 127)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamps();

            $table->index(['news_item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_item_witness_submissions');
        Schema::dropIfExists('news_item_witness_links');
    }
};
