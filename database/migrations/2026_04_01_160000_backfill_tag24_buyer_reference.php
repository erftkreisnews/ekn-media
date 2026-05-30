<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * TAG24: Lexware-Kundennummer in EKN nachziehen (war oft nur in Lexware gepflegt).
     */
    public function up(): void
    {
        $orgIds = DB::table('organizations')
            ->whereRaw('LOWER(name) LIKE ?', ['%tag24%'])
            ->pluck('id');

        foreach ($orgIds as $orgId) {
            DB::table('organizations')
                ->where('id', $orgId)
                ->where(function ($q) {
                    $q->whereNull('buyer_reference')->orWhere('buyer_reference', '');
                })
                ->update(['buyer_reference' => '10022']);

            DB::table('products')
                ->where('organization_id', $orgId)
                ->where(function ($q) {
                    $q->whereNull('buyer_reference')->orWhere('buyer_reference', '');
                })
                ->update(['buyer_reference' => '10022']);
        }
    }

    public function down(): void
    {
        $orgIds = DB::table('organizations')
            ->whereRaw('LOWER(name) LIKE ?', ['%tag24%'])
            ->pluck('id');

        foreach ($orgIds as $orgId) {
            DB::table('organizations')
                ->where('id', $orgId)
                ->where('buyer_reference', '10022')
                ->update(['buyer_reference' => null]);

            DB::table('products')
                ->where('organization_id', $orgId)
                ->where('buyer_reference', '10022')
                ->update(['buyer_reference' => null]);
        }
    }
};
