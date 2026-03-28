<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
        });

        DB::table('invoices')
            ->whereNull('product_id')
            ->orderBy('id')
            ->chunk(100, function ($rows) {
                foreach ($rows as $row) {
                    $productId = DB::table('usage_records')
                        ->where('invoice_id', $row->id)
                        ->value('product_id');

                    if ($productId) {
                        DB::table('invoices')
                            ->where('id', $row->id)
                            ->update(['product_id' => $productId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
        });
    }
};
