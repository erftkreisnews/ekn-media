<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presseportal_offices', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('office_id')->comment('Presseportal Dienststellen-ID (URL-Segment nach /pm/)');
            $table->string('name');
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('office_id');
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presseportal_offices');
    }
};
