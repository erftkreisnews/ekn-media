<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MultiBrandCoreSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('brands')) {
            return;
        }

        $brandRows = DB::table('brands')
            ->whereIn('key', ['erftkreis_news', 'koelnimage'])
            ->pluck('id', 'key')
            ->all();

        if (! Schema::hasTable('channels') || $brandRows === []) {
            return;
        }

        $now = now();
        $channelSeeds = [
            'erftkreis_news' => [
                ['key' => 'news_site', 'name' => 'News Site'],
                ['key' => 'witness', 'name' => 'Witness'],
                ['key' => 'delivery', 'name' => 'Delivery'],
                ['key' => 'archive', 'name' => 'Archiv'],
            ],
            'koelnimage' => [
                ['key' => 'gallery', 'name' => 'Galerie'],
                ['key' => 'customer_download', 'name' => 'Kundendownload'],
                ['key' => 'delivery', 'name' => 'Delivery'],
                ['key' => 'archive', 'name' => 'Archiv'],
            ],
        ];

        foreach ($channelSeeds as $brandKey => $rows) {
            $brandId = $brandRows[$brandKey] ?? null;
            if (! $brandId) {
                continue;
            }
            foreach ($rows as $row) {
                DB::table('channels')->updateOrInsert(
                    ['brand_id' => $brandId, 'key' => $row['key']],
                    [
                        'name' => $row['name'],
                        'is_active' => true,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }

        if (! Schema::hasTable('project_types')) {
            return;
        }

        $projectTypes = [
            ['key' => 'news_case', 'name' => 'News Case'],
            ['key' => 'sport_event', 'name' => 'Sport Event'],
            ['key' => 'motorsport_event', 'name' => 'Motorsport Event'],
            ['key' => 'b2b_job', 'name' => 'B2B Job'],
            ['key' => 'wedding_job', 'name' => 'Wedding Job'],
            ['key' => 'editorial_assignment', 'name' => 'Editorial Assignment'],
        ];

        foreach ($projectTypes as $row) {
            DB::table('project_types')->updateOrInsert(
                ['key' => $row['key']],
                [
                    'name' => $row['name'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }
}
