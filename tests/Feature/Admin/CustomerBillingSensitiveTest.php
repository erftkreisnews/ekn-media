<?php

namespace Tests\Feature\Admin;

use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerBillingSensitiveTest extends TestCase
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

    public function test_user_without_billing_sensitive_cannot_change_external_ids_on_organization(): void
    {
        $org = Organization::query()->create([
            'name' => 'Test MH',
            'active' => true,
            'external_author_id' => 'KEEP-123',
            'external_reference_label' => null,
            'external_supplier_id' => null,
            'external_vendor_code' => null,
        ]);

        $user = User::factory()->create();
        $user->assignRole('admin');
        $user->givePermissionTo(['admin.access', AdminPermissions::CUSTOMERS]);

        $this->actingAs($user)
            ->post(route('admin.customers.update', $org), [
                'name' => 'Test MH Updated',
                'notes' => null,
                'active' => '1',
                'external_author_id' => 'HACK-999',
            ])
            ->assertRedirect();

        $org->refresh();
        $this->assertSame('KEEP-123', $org->external_author_id);
        $this->assertSame('Test MH Updated', $org->name);
    }

    public function test_import_lexware_is_forbidden_without_billing_sensitive(): void
    {
        config(['lexware.api_key' => 'test-key']);

        $org = Organization::query()->create([
            'name' => 'O',
            'active' => true,
        ]);
        $product = Product::query()->create([
            'organization_id' => $org->id,
            'name' => 'P',
            'active' => true,
            'lexware_contact_id' => 'lex-1',
        ]);

        $user = User::factory()->create();
        $user->assignRole('admin');
        $user->givePermissionTo(['admin.access', AdminPermissions::CUSTOMERS]);

        $this->actingAs($user)
            ->post(route('admin.customers.products.import-lexware', [$org, $product]))
            ->assertRedirect(route('admin.dashboard'));
    }
}
