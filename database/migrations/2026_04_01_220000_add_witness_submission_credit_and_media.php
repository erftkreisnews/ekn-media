<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_item_witness_submissions', function (Blueprint $table) {
            $table->boolean('credit_anonymous')->default(false)->after('consent_rights');
            $table->string('witness_suggested_title', 255)->nullable()->after('credit_anonymous');
            $table->foreignId('news_item_media_id')->nullable()->after('status')->constrained('news_item_media')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('news_item_witness_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('news_item_media_id');
            $table->dropColumn(['witness_suggested_title', 'credit_anonymous']);
        });
    }
};
