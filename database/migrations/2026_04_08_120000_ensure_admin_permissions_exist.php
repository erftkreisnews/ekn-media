<?php

use App\Support\AdminPermissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        foreach (AdminPermissions::all() as $name) {
            Permission::findOrCreate($name);
        }

        Permission::findOrCreate('access_admin');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
