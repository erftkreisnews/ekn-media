<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('media_publication_finding_media')) {
            Schema::create('media_publication_finding_media', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('media_publication_finding_id');
                $table->unsignedBigInteger('news_item_media_id');
                $table->timestamps();

                $table->foreign('media_publication_finding_id', 'mpf_media_finding_id_fk')
                    ->references('id')
                    ->on('media_publication_findings')
                    ->cascadeOnDelete();
                $table->foreign('news_item_media_id', 'mpf_media_news_item_media_fk')
                    ->references('id')
                    ->on('news_item_media')
                    ->cascadeOnDelete();

                $table->unique(
                    ['media_publication_finding_id', 'news_item_media_id'],
                    'mpf_finding_media_unique'
                );
            });
        } else {
            Schema::table('media_publication_finding_media', function (Blueprint $table) {
                if (! $this->indexExists('media_publication_finding_media', 'mpf_finding_media_unique')) {
                    $table->unique(
                        ['media_publication_finding_id', 'news_item_media_id'],
                        'mpf_finding_media_unique'
                    );
                }
            });

            $this->addForeignKeyIfMissing(
                'media_publication_finding_media',
                'mpf_media_finding_id_fk',
                'media_publication_finding_id',
                'media_publication_findings',
                'id'
            );
            $this->addForeignKeyIfMissing(
                'media_publication_finding_media',
                'mpf_media_news_item_media_fk',
                'news_item_media_id',
                'news_item_media',
                'id'
            );
        }

        if (! Schema::hasTable('media_publication_findings')) {
            return;
        }

        DB::table('media_publication_findings')
            ->whereNotNull('news_item_media_id')
            ->orderBy('id')
            ->each(function (object $row): void {
                $exists = DB::table('media_publication_finding_media')
                    ->where('media_publication_finding_id', $row->id)
                    ->where('news_item_media_id', $row->news_item_media_id)
                    ->exists();

                if ($exists) {
                    return;
                }

                DB::table('media_publication_finding_media')->insert([
                    'media_publication_finding_id' => $row->id,
                    'news_item_media_id' => $row->news_item_media_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_publication_finding_media');
    }

    private function indexExists(string $table, string $index): bool
    {
        $indexes = DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$index]);

        return $indexes !== [];
    }

    private function addForeignKeyIfMissing(
        string $table,
        string $constraint,
        string $column,
        string $referencedTable,
        string $referencedColumn
    ): void {
        $exists = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$table, $constraint]
        );

        if ($exists !== []) {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`) ON DELETE CASCADE',
            $table,
            $constraint,
            $column,
            $referencedTable,
            $referencedColumn
        ));
    }
};
