<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            if (! Schema::hasColumn('organizations', 'notes')) {
                $table->text('notes')->nullable()->after('name');
            }
            if (! Schema::hasColumn('organizations', 'active')) {
                $table->boolean('active')->default(true)->after('notes');
            }
        });
    }

    public function down(): void
    {
        $cols = [];
        if (Schema::hasColumn('organizations', 'notes')) {
            $cols[] = 'notes';
        }
        if (Schema::hasColumn('organizations', 'active')) {
            $cols[] = 'active';
        }
        if ($cols !== []) {
            Schema::table('organizations', fn (Blueprint $t) => $t->dropColumn($cols));
        }
    }
};
