<?php

namespace Tests\Feature\Admin;

use App\Models\DeliveryDestination;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeliveryDestinationBillingSensitiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin');
        Permission::findOrCreate(AdminPermissions::CUSTOMERS);
        Permission::findOrCreate(AdminPermissions::CUSTOMERS_BILLING_SENSITIVE);
        Permission::findOrCreate(AdminPermissions::ACCESS);
    }

    public function test_user_without_billing_sensitive_cannot_change_external_ids_on_product_destination(): void
    {
        $org = Organization::query()->create([
            'name' => 'O',
            'active' => true,
        ]);
        $product = Product::query()->create([
            'organization_id' => $org->id,
            'name' => 'P',
            'active' => true,
        ]);
        $dest = DeliveryDestination::query()->create([
            'organization_id' => $org->id,
            'product_id' => $product->id,
            'type' => 'ftp',
            'label' => 'FTP Ziel',
            'host' => 'ftp.example.test',
            'port' => 21,
            'username' => 'u',
            'active' => true,
            'passive' => true,
            'timeout' => 20,
            'external_author_id' => 'KEEP-123',
            'external_reference_label' => null,
            'external_supplier_id' => null,
            'external_vendor_code' => null,
            'include_in_email' => true,
            'include_in_filename' => true,
            'generate_sidecar' => true,
        ]);

        $user = User::factory()->create();
        $user->assignRole('admin');
        $user->givePermissionTo(['admin.access', AdminPermissions::CUSTOMERS]);

        $this->actingAs($user)
            ->put(route('admin.destinations.update', $dest), [
                'type' => 'ftp',
                'label' => 'FTP Ziel Updated',
                'active' => '1',
                'host' => 'ftp.example.test',
                'username' => 'u',
                'external_author_id' => 'HACK-999',
                'include_in_filename' => '0',
                'generate_sidecar' => '0',
            ])
            ->assertRedirect();

        $dest->refresh();
        $this->assertSame('KEEP-123', $dest->external_author_id);
        $this->assertSame('FTP Ziel Updated', $dest->label);
        $this->assertTrue($dest->include_in_filename);
        $this->assertTrue($dest->generate_sidecar);
    }
}
