<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BackofficeUsersPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('admin.access');
        Permission::findOrCreate('admin.users');
    }

    public function test_guest_is_redirected_from_users_index(): void
    {
        $this->get(route('admin.backoffice.users.index'))
            ->assertRedirect();
    }

    public function test_user_without_admin_users_gets_forbidden(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('admin.access');

        $this->actingAs($user)
            ->get(route('admin.backoffice.users.index'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_user_with_admin_users_permission_can_open_users_index(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['admin.access', 'admin.users']);

        $this->actingAs($user)
            ->get(route('admin.backoffice.users.index'))
            ->assertOk();
    }

    public function test_user_with_legacy_access_admin_can_open_users_index(): void
    {
        Permission::findOrCreate('access_admin');

        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $this->actingAs($user)
            ->get(route('admin.backoffice.users.index'))
            ->assertOk();
    }

    public function test_user_with_admin_role_can_open_users_index(): void
    {
        $adminRole = Role::findOrCreate('admin');
        $adminRole->givePermissionTo(Permission::all());

        $user = User::factory()->create();
        $user->assignRole($adminRole);

        $this->actingAs($user)
            ->get(route('admin.backoffice.users.index'))
            ->assertOk();
    }

    public function test_admin_can_open_create_user_form(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['admin.access', 'admin.users']);

        $this->actingAs($user)
            ->get(route('admin.backoffice.users.create'))
            ->assertOk();
    }

    public function test_admin_can_create_user_with_password(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['admin.access', 'admin.users']);

        $this->actingAs($user)
            ->post(route('admin.backoffice.users.store'), [
                'name' => 'Neu Tester',
                'email' => 'neu-tester@example.test',
                'password' => 'SecurePass!234',
                'password_confirmation' => 'SecurePass!234',
                'roles' => [],
            ])
            ->assertRedirect(route('admin.backoffice.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'neu-tester@example.test',
            'name' => 'Neu Tester',
        ]);

        $created = User::query()->where('email', 'neu-tester@example.test')->first();
        $this->assertNotNull($created);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('SecurePass!234', $created->password));
    }

    public function test_admin_can_change_user_password(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(['admin.access', 'admin.users']);

        $target = User::factory()->create([
            'email' => 'target@example.test',
            'password' => 'OldPassword!99',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.backoffice.users.update', $target), [
                'name' => $target->name,
                'email' => $target->email,
                'password' => 'NewPassword!88',
                'password_confirmation' => 'NewPassword!88',
                'roles' => [],
            ])
            ->assertRedirect(route('admin.backoffice.users.index'));

        $target->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewPassword!88', $target->password));
    }
}
