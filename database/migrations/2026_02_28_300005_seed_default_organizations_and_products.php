<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $wdr = DB::table('organizations')->where('name', 'WDR')->value('id');
        if ($wdr === null) {
            $wdr = DB::table('organizations')->insertGetId(['name' => 'WDR', 'created_at' => now(), 'updated_at' => now()]);
        }
        $ksta = DB::table('organizations')->where('name', 'Kölner Stadt-Anzeiger')->value('id');
        if ($ksta === null) {
            $ksta = DB::table('organizations')->insertGetId(['name' => 'Kölner Stadt-Anzeiger', 'created_at' => now(), 'updated_at' => now()]);
        }
        if (DB::table('products')->where('organization_id', $wdr)->exists()) {
            return;
        }

        DB::table('products')->insert([
            ['organization_id' => $wdr, 'name' => 'Lokalzeit Köln', 'created_at' => now(), 'updated_at' => now()],
            ['organization_id' => $wdr, 'name' => 'Aktuelle Stunde', 'created_at' => now(), 'updated_at' => now()],
            ['organization_id' => $ksta, 'name' => 'Print', 'created_at' => now(), 'updated_at' => now()],
            ['organization_id' => $ksta, 'name' => 'Online', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        $orgIds = DB::table('organizations')->whereIn('name', ['WDR', 'Kölner Stadt-Anzeiger'])->pluck('id');
        if ($orgIds->isNotEmpty()) {
            DB::table('products')->whereIn('organization_id', $orgIds)->delete();
            DB::table('organizations')->whereIn('id', $orgIds)->delete();
        }
    }
};
