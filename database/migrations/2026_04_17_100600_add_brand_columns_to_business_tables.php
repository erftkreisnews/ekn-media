<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['organizations', 'products', 'deliveries', 'invoices', 'usage_records'];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $hasColumn = Schema::hasColumn($tableName, 'brand_id');
            $indexName = $tableName.'_brand_id_idx';
            $fkName = $tableName.'_brand_id_fk';
            $hasIndex = $this->hasIndex($tableName, $indexName);
            $hasForeign = $this->hasForeignKey($tableName, $fkName);

            Schema::table($tableName, function (Blueprint $table) use ($hasColumn, $hasIndex, $hasForeign, $indexName, $fkName) {
                if (! $hasColumn) {
                    $table->unsignedBigInteger('brand_id')->nullable();
                }
                if (! $hasIndex) {
                    $table->index('brand_id', $indexName);
                }
                if (! $hasForeign) {
                    $table->foreign('brand_id', $fkName)
                        ->references('id')
                        ->on('brands')
                        ->nullOnDelete();
                }
            });
        }

        $defaultBrandId = DB::table('brands')->where('key', 'erftkreis_news')->value('id');
        if (! $defaultBrandId) {
            return;
        }

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'brand_id')) {
                DB::table($tableName)->whereNull('brand_id')->update(['brand_id' => $defaultBrandId]);
            }
        }
    }

    public function down(): void
    {
        $tables = ['usage_records', 'invoices', 'deliveries', 'products', 'organizations'];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'brand_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('brand_id');
            });
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
