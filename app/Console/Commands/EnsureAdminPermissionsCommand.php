<?php

namespace App\Console\Commands;

use App\Support\AdminPermissions;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class EnsureAdminPermissionsCommand extends Command
{
    protected $signature = 'admin:ensure-permissions';

    protected $description = 'Legt alle Spatie-Permissions für den Admin-Bereich an (idempotent). Hilft, wenn migrate „Nothing to migrate“ meldet, die Rechte in der DB aber fehlen.';

    public function handle(): int
    {
        foreach (AdminPermissions::all() as $name) {
            Permission::findOrCreate($name);
        }

        Permission::findOrCreate('access_admin');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->info('Admin-Permissions sind angelegt bzw. bereits vorhanden.');

        return self::SUCCESS;
    }
}
