<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingest_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->json('config_json')->nullable();
            $table->timestamps();
        });

        Schema::create('ingest_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingest_source_id')->constrained('ingest_sources')->cascadeOnDelete();
            $table->string('reference_label')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ingest_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingest_source_id')->constrained('ingest_sources')->cascadeOnDelete();
            $table->foreignId('ingest_batch_id')->nullable()->constrained('ingest_batches')->nullOnDelete();
            $table->string('original_name');
            $table->string('relative_path', 1024);
            /** Absoluter Pfad auf dem Server (Inbox/Processing/…) */
            $table->string('absolute_path', 2048);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('content_hash', 64)->nullable()->index();
            $table->string('mime', 128)->nullable();
            $table->decimal('duration_s', 12, 4)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->decimal('fps', 10, 4)->nullable();
            $table->string('codec', 64)->nullable();
            $table->string('status', 32)->index();
            $table->foreignId('news_item_id')->nullable()->constrained('news_items')->nullOnDelete();
            $table->unsignedSmallInteger('selection_order')->nullable();
            $table->foreignId('final_news_item_media_id')->nullable()->constrained('news_item_media')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->longText('ffprobe_json')->nullable();
            $table->timestamps();
        });

        Schema::create('ingest_render_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_item_id')->constrained('news_items')->cascadeOnDelete();
            $table->json('ingest_file_ids');
            $table->string('status', 32)->index();
            $table->string('local_output_path', 2048)->nullable();
            $table->foreignId('final_news_item_media_id')->nullable()->constrained('news_item_media')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        DB::table('ingest_sources')->insert([
            'name' => 'MC60 Standard',
            'slug' => 'mc60-default',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ingest_render_jobs');
        Schema::dropIfExists('ingest_files');
        Schema::dropIfExists('ingest_batches');
        Schema::dropIfExists('ingest_sources');
    }
};
