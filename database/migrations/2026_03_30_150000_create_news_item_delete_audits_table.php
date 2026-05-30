<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_item_delete_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('news_item_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('title', 265)->nullable();
            $table->string('slug', 255)->nullable()->index();
            $table->string('status', 32)->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('deleted_at')->nullable()->index();
            $table->string('request_ip', 64)->nullable();
            $table->string('user_agent', 1024)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_item_delete_audits');
    }
};
