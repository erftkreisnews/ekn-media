<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('news_items')) {
            $hasNewsBrandId = Schema::hasColumn('news_items', 'brand_id');
            $hasNewsProjectId = Schema::hasColumn('news_items', 'project_id');
            $hasNewsChannelType = Schema::hasColumn('news_items', 'channel_type');
            $hasNewsBrandIdx = $this->hasIndex('news_items', 'news_items_brand_id_idx');
            $hasNewsProjectIdx = $this->hasIndex('news_items', 'news_items_project_id_idx');
            $hasNewsChannelIdx = $this->hasIndex('news_items', 'news_items_channel_type_idx');
            $hasNewsBrandFk = $this->hasForeignKey('news_items', 'news_items_brand_id_fk');
            $hasNewsProjectFk = $this->hasForeignKey('news_items', 'news_items_project_id_fk');

            Schema::table('news_items', function (Blueprint $table) use (
                $hasNewsBrandId,
                $hasNewsProjectId,
                $hasNewsChannelType,
                $hasNewsBrandIdx,
                $hasNewsProjectIdx,
                $hasNewsChannelIdx,
                $hasNewsBrandFk,
                $hasNewsProjectFk
            ) {
                if (! $hasNewsBrandId) {
                    $table->unsignedBigInteger('brand_id')->nullable()->after('id');
                }
                if (! $hasNewsProjectId) {
                    $table->unsignedBigInteger('project_id')->nullable();
                }
                if (! $hasNewsChannelType) {
                    $table->string('channel_type', 50)->nullable();
                }
                if (! $hasNewsBrandIdx) {
                    $table->index('brand_id', 'news_items_brand_id_idx');
                }
                if (! $hasNewsProjectIdx) {
                    $table->index('project_id', 'news_items_project_id_idx');
                }
                if (! $hasNewsChannelIdx) {
                    $table->index('channel_type', 'news_items_channel_type_idx');
                }
                if (! $hasNewsBrandFk) {
                    $table->foreign('brand_id', 'news_items_brand_id_fk')
                        ->references('id')
                        ->on('brands')
                        ->nullOnDelete();
                }
                if (! $hasNewsProjectFk) {
                    $table->foreign('project_id', 'news_items_project_id_fk')
                        ->references('id')
                        ->on('projects')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('news_item_media')) {
            $hasMediaBrandId = Schema::hasColumn('news_item_media', 'brand_id');
            $hasMediaProjectId = Schema::hasColumn('news_item_media', 'project_id');
            $hasMediaChannelType = Schema::hasColumn('news_item_media', 'channel_type');
            $hasMediaVisibility = Schema::hasColumn('news_item_media', 'visibility');
            $hasMediaPublishState = Schema::hasColumn('news_item_media', 'publish_state');
            $hasMediaBrandIdx = $this->hasIndex('news_item_media', 'news_item_media_brand_id_idx');
            $hasMediaProjectIdx = $this->hasIndex('news_item_media', 'news_item_media_project_id_idx');
            $hasMediaChannelIdx = $this->hasIndex('news_item_media', 'news_item_media_channel_type_idx');
            $hasMediaBrandFk = $this->hasForeignKey('news_item_media', 'news_item_media_brand_id_fk');
            $hasMediaProjectFk = $this->hasForeignKey('news_item_media', 'news_item_media_project_id_fk');

            Schema::table('news_item_media', function (Blueprint $table) use (
                $hasMediaBrandId,
                $hasMediaProjectId,
                $hasMediaChannelType,
                $hasMediaVisibility,
                $hasMediaPublishState,
                $hasMediaBrandIdx,
                $hasMediaProjectIdx,
                $hasMediaChannelIdx,
                $hasMediaBrandFk,
                $hasMediaProjectFk
            ) {
                if (! $hasMediaBrandId) {
                    $table->unsignedBigInteger('brand_id')->nullable()->after('news_item_id');
                }
                if (! $hasMediaProjectId) {
                    $table->unsignedBigInteger('project_id')->nullable();
                }
                if (! $hasMediaChannelType) {
                    $table->string('channel_type', 50)->nullable();
                }
                if (! $hasMediaVisibility) {
                    $table->enum('visibility', ['public', 'customer_only', 'internal'])->default('public');
                }
                if (! $hasMediaPublishState) {
                    $table->enum('publish_state', ['draft', 'ready', 'published', 'archived'])->default('draft');
                }
                if (! $hasMediaBrandIdx) {
                    $table->index('brand_id', 'news_item_media_brand_id_idx');
                }
                if (! $hasMediaProjectIdx) {
                    $table->index('project_id', 'news_item_media_project_id_idx');
                }
                if (! $hasMediaChannelIdx) {
                    $table->index('channel_type', 'news_item_media_channel_type_idx');
                }
                if (! $hasMediaBrandFk) {
                    $table->foreign('brand_id', 'news_item_media_brand_id_fk')
                        ->references('id')
                        ->on('brands')
                        ->nullOnDelete();
                }
                if (! $hasMediaProjectFk) {
                    $table->foreign('project_id', 'news_item_media_project_id_fk')
                        ->references('id')
                        ->on('projects')
                        ->nullOnDelete();
                }
            });
        }

        $this->seedCoreBrandsAndBackfill();
    }

    public function down(): void
    {
        if (Schema::hasTable('news_item_media')) {
            Schema::table('news_item_media', function (Blueprint $table) {
                if (Schema::hasColumn('news_item_media', 'publish_state')) {
                    $table->dropColumn('publish_state');
                }
                if (Schema::hasColumn('news_item_media', 'visibility')) {
                    $table->dropColumn('visibility');
                }
                if (Schema::hasColumn('news_item_media', 'channel_type')) {
                    $table->dropColumn('channel_type');
                }
                if (Schema::hasColumn('news_item_media', 'project_id')) {
                    $table->dropConstrainedForeignId('project_id');
                }
                if (Schema::hasColumn('news_item_media', 'brand_id')) {
                    $table->dropConstrainedForeignId('brand_id');
                }
            });
        }

        if (Schema::hasTable('news_items')) {
            Schema::table('news_items', function (Blueprint $table) {
                if (Schema::hasColumn('news_items', 'channel_type')) {
                    $table->dropColumn('channel_type');
                }
                if (Schema::hasColumn('news_items', 'project_id')) {
                    $table->dropConstrainedForeignId('project_id');
                }
                if (Schema::hasColumn('news_items', 'brand_id')) {
                    $table->dropConstrainedForeignId('brand_id');
                }
            });
        }
    }

    private function seedCoreBrandsAndBackfill(): void
    {
        if (! Schema::hasTable('brands')) {
            return;
        }

        $now = now();
        DB::table('brands')->updateOrInsert(
            ['key' => 'erftkreis_news'],
            [
                'name' => 'Erftkreis News',
                'primary_host' => config('brands.hosts.erftkreis_news', 'erftkreis-news.media'),
                'secondary_hosts' => json_encode([]),
                'is_active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
        DB::table('brands')->updateOrInsert(
            ['key' => 'koelnimage'],
            [
                'name' => 'KoelnImage',
                'primary_host' => config('brands.hosts.koelnimage', 'koelnimage.de'),
                'secondary_hosts' => json_encode([]),
                'is_active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $defaultBrandId = DB::table('brands')->where('key', 'erftkreis_news')->value('id');
        if (! $defaultBrandId) {
            return;
        }

        if (Schema::hasTable('news_items') && Schema::hasColumn('news_items', 'brand_id')) {
            DB::table('news_items')->whereNull('brand_id')->update(['brand_id' => $defaultBrandId]);
        }
        if (Schema::hasTable('news_item_media') && Schema::hasColumn('news_item_media', 'brand_id')) {
            DB::table('news_item_media')->whereNull('brand_id')->update(['brand_id' => $defaultBrandId]);
        }
    }

    private function hasForeignKey(string $table, string $constraintName): bool
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraintName)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');

        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $indexName)
            ->exists();
    }
};
