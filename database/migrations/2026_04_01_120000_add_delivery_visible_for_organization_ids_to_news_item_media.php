<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_item_media', function (Blueprint $table) {
            if (! Schema::hasColumn('news_item_media', 'delivery_visible_for_organization_ids')) {
                $table->json('delivery_visible_for_organization_ids')->nullable()->after('versand');
            }
        });
    }

    public function down(): void
    {
        Schema::table('news_item_media', function (Blueprint $table) {
            if (Schema::hasColumn('news_item_media', 'delivery_visible_for_organization_ids')) {
                $table->dropColumn('delivery_visible_for_organization_ids');
            }
        });
    }
};
