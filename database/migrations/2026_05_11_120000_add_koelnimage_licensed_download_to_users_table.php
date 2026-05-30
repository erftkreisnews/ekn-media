<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nach manueller Lizenz-/Rechnungsfreigabe (B2B): Nutzer darf Redaktionsdateien
     * aus der Kölnimage-Galerie herunterladen. Kein automatischer Zahlungsabgleich.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('koelnimage_licensed_download')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('koelnimage_licensed_download');
        });
    }
};
