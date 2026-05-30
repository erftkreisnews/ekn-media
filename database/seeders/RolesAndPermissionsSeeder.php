<?php

namespace Database\Seeders;

use App\Support\AdminPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (AdminPermissions::all() as $name) {
            Permission::findOrCreate($name);
        }

        // Legacy: direktes Recht „access_admin“ = voller Zugriff (siehe AppServiceProvider::Gate)
        Permission::findOrCreate('access_admin');

        $assign = function (Role $role, array $permissionNames): void {
            $role->syncPermissions(
                Permission::query()->whereIn('name', $permissionNames)->get()
            );
        };

        $redaktion = Role::findOrCreate('redaktion');
        $assign($redaktion, [
            AdminPermissions::ACCESS,
            AdminPermissions::NEWS,
            AdminPermissions::DELIVERIES,
            AdminPermissions::MEDIA,
            AdminPermissions::CUSTOMERS,
        ]);

        $technik = Role::findOrCreate('technik');
        $assign($technik, [
            AdminPermissions::ACCESS,
            AdminPermissions::MEDIA,
            AdminPermissions::INGEST,
            AdminPermissions::CUSTOMERS,
            AdminPermissions::CUSTOMERS_DELETE,
            AdminPermissions::SETTINGS,
        ]);

        $buchhaltung = Role::findOrCreate('buchhaltung');
        $assign($buchhaltung, [
            AdminPermissions::ACCESS,
            AdminPermissions::BACKOFFICE,
            AdminPermissions::CUSTOMERS,
            AdminPermissions::CUSTOMERS_DELETE,
            AdminPermissions::CUSTOMERS_BILLING_SENSITIVE,
        ]);

        $admin = Role::findOrCreate('admin');
        $admin->syncPermissions(Permission::all());

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
