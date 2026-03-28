<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('buyer_reference')->nullable()->after('active');
            $table->string('lexware_contact_id')->nullable()->after('buyer_reference');
            $table->string('billing_name')->nullable()->after('lexware_contact_id');
            $table->string('billing_company')->nullable()->after('billing_name');
            $table->string('billing_street')->nullable()->after('billing_company');
            $table->string('billing_postal_code', 20)->nullable()->after('billing_street');
            $table->string('billing_city')->nullable()->after('billing_postal_code');
            $table->string('billing_country', 100)->nullable()->after('billing_city');
            $table->string('billing_email_primary')->nullable()->after('billing_country');
            $table->string('billing_email_secondary')->nullable()->after('billing_email_primary');
            $table->text('billing_notes')->nullable()->after('billing_email_secondary');
        });

        DB::table('products')
            ->join('organizations', 'organizations.id', '=', 'products.organization_id')
            ->select([
                'products.id as product_id',
                'products.name as product_name',
                'products.buyer_reference as product_buyer_reference',
                'products.lexware_contact_id as product_lexware_contact_id',
                'organizations.name as organization_name',
                'organizations.buyer_reference as organization_buyer_reference',
                'organizations.lexware_contact_id as organization_lexware_contact_id',
            ])
            ->orderBy('products.id')
            ->chunk(100, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('products')
                        ->where('id', $row->product_id)
                        ->update([
                            'buyer_reference' => $row->product_buyer_reference ?: $row->organization_buyer_reference,
                            'lexware_contact_id' => $row->product_lexware_contact_id ?: $row->organization_lexware_contact_id,
                            'billing_name' => $row->product_name,
                            'billing_company' => $row->organization_name,
                            'billing_country' => 'Deutschland',
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'buyer_reference',
                'lexware_contact_id',
                'billing_name',
                'billing_company',
                'billing_street',
                'billing_postal_code',
                'billing_city',
                'billing_country',
                'billing_email_primary',
                'billing_email_secondary',
                'billing_notes',
            ]);
        });
    }
};
