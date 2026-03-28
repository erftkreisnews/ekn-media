<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('news_items', function (Blueprint $table) {
            $table->string('country', 255)->nullable()->after('region');
            $table->string('federal_state', 255)->nullable()->after('country');
            $table->string('city', 255)->nullable()->after('federal_state');
            $table->string('street', 255)->nullable()->after('city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_items', function (Blueprint $table) {
            $table->dropColumn(['country', 'federal_state', 'city', 'street']);
        });
    }
};
