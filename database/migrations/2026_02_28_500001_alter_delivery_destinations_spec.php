<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_destinations', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
        });

        Schema::table('delivery_destinations', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });
        DB::statement('ALTER TABLE delivery_destinations MODIFY product_id BIGINT UNSIGNED NULL');
        Schema::table('delivery_destinations', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });

        Schema::table('delivery_destinations', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_destinations', 'host')) {
                $table->string('host')->nullable()->after('label');
            }
            if (! Schema::hasColumn('delivery_destinations', 'port')) {
                $table->unsignedSmallInteger('port')->nullable()->after('host');
            }
            if (! Schema::hasColumn('delivery_destinations', 'username')) {
                $table->string('username')->nullable()->after('port');
            }
            if (! Schema::hasColumn('delivery_destinations', 'password_encrypted')) {
                $table->text('password_encrypted')->nullable()->after('username');
            }
            if (! Schema::hasColumn('delivery_destinations', 'private_key_encrypted')) {
                $table->text('private_key_encrypted')->nullable()->after('password_encrypted');
            }
            if (! Schema::hasColumn('delivery_destinations', 'private_key_passphrase_encrypted')) {
                $table->text('private_key_passphrase_encrypted')->nullable()->after('private_key_encrypted');
            }
            if (! Schema::hasColumn('delivery_destinations', 'remote_path')) {
                $table->string('remote_path')->nullable()->after('private_key_passphrase_encrypted');
            }
            if (! Schema::hasColumn('delivery_destinations', 'passive')) {
                $table->boolean('passive')->default(true)->after('remote_path');
            }
            if (! Schema::hasColumn('delivery_destinations', 'timeout')) {
                $table->unsignedSmallInteger('timeout')->default(20)->after('passive');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_destinations', function (Blueprint $table) {
            $cols = ['organization_id', 'host', 'port', 'username', 'password_encrypted', 'private_key_encrypted', 'private_key_passphrase_encrypted', 'remote_path', 'passive', 'timeout'];
            foreach ($cols as $c) {
                if (Schema::hasColumn('delivery_destinations', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
        Schema::table('delivery_destinations', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });
        DB::statement('ALTER TABLE delivery_destinations MODIFY product_id BIGINT UNSIGNED NOT NULL');
        Schema::table('delivery_destinations', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
        Schema::table('delivery_destinations', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
        });
    }
};
