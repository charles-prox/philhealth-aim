<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create basic permissions
        $permissions = [
            'manage users',
            'view inventory',
            'manage inventory',
            'manage procurement',
            'manage budget',
            'view reports',
            'view office analytics',
            'generate reports',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Define Roles and assign basic permissions
        $roles = [
            'Admin' => ['manage users', 'view inventory', 'manage inventory', 'manage procurement', 'manage budget', 'view reports'],
            'Procurement Officer' => ['manage procurement', 'view inventory'],
            'Canvasser' => ['manage procurement'],
            'Inventory Manager' => ['manage inventory', 'view inventory'],
            'Budget Officer' => ['manage budget', 'view reports'],
            'Admin Head' => ['view reports', 'view inventory'],
            'MSD Head' => ['view reports', 'view inventory'],
            'Auditor' => ['view reports', 'view inventory'],
            'Document custodian' => ['manage procurement'],
            'Office Head' => ['manage procurement', 'view office analytics', 'generate reports'],
            'Regional Vice President' => ['view reports', 'view inventory', 'view office analytics', 'generate reports'],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($rolePermissions);
        }
    }
}
