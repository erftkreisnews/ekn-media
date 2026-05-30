<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_items', function (Blueprint $table) {
            if (! Schema::hasColumn('news_items', 'parent_news_item_id')) {
                $table->foreignId('parent_news_item_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('news_items')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('news_items', 'update_revision')) {
                $table->unsignedInteger('update_revision')
                    ->nullable()
                    ->after('parent_news_item_id');
            }
        });

        Schema::table('news_items', function (Blueprint $table) {
            $table->index('parent_news_item_id', 'news_items_parent_news_item_id_idx');
            $table->unique(['parent_news_item_id', 'update_revision'], 'news_items_parent_update_revision_unique');
        });
    }

    public function down(): void
    {
        Schema::table('news_items', function (Blueprint $table) {
            $table->dropUnique('news_items_parent_update_revision_unique');
            $table->dropIndex('news_items_parent_news_item_id_idx');
            if (Schema::hasColumn('news_items', 'update_revision')) {
                $table->dropColumn('update_revision');
            }
            if (Schema::hasColumn('news_items', 'parent_news_item_id')) {
                $table->dropConstrainedForeignId('parent_news_item_id');
            }
        });
    }
};
