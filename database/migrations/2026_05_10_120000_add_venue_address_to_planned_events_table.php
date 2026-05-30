<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('planned_events')) {
            return;
        }

        Schema::table('planned_events', function (Blueprint $table): void {
            if (! Schema::hasColumn('planned_events', 'venue_street')) {
                $table->string('venue_street', 255)->nullable()->after('location');
            }
            if (! Schema::hasColumn('planned_events', 'venue_postal_code')) {
                $table->string('venue_postal_code', 32)->nullable()->after('venue_street');
            }
            if (! Schema::hasColumn('planned_events', 'venue_city')) {
                $table->string('venue_city', 120)->nullable()->after('venue_postal_code');
            }
            if (! Schema::hasColumn('planned_events', 'venue_state')) {
                $table->string('venue_state', 120)->nullable()->after('venue_city');
            }
            if (! Schema::hasColumn('planned_events', 'venue_country')) {
                $table->string('venue_country', 120)->nullable()->after('venue_state');
            }
            if (! Schema::hasColumn('planned_events', 'venue_country_code')) {
                $table->string('venue_country_code', 2)->nullable()->after('venue_country');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('planned_events')) {
            return;
        }

        $cols = [
            'venue_street',
            'venue_postal_code',
            'venue_city',
            'venue_state',
            'venue_country',
            'venue_country_code',
        ];
        $toDrop = array_values(array_filter($cols, fn (string $c) => Schema::hasColumn('planned_events', $c)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('planned_events', function (Blueprint $table) use ($toDrop): void {
            $table->dropColumn($toDrop);
        });
    }
};
