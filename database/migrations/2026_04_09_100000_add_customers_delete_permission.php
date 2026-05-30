<?php

use App\Support\AdminPermissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $perm = Permission::findOrCreate(AdminPermissions::CUSTOMERS_DELETE);

        foreach (['technik', 'buchhaltung', 'admin'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo($perm);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
