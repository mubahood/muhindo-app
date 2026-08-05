<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Platform RBAC: super_admin (owner) · admin (future staff) · student · client.
 * Students and clients never see /admin. They get their own portals
 * (/learn, /portal) gated by plain `auth` + ownership policies, not
 * permissions. Permissions here only gate the back-office.
 */
class RbacSeeder extends Seeder
{
    public const PERMISSIONS = [
        'access-admin',
        'manage-users',
        'manage-settings',
        'portfolio.manage',
        'courses.manage',
        'clients.manage',
        'projects.manage',
        'billing.manage',
    ];

    /** @var array<string, '*'|list<string>> */
    public const MATRIX = [
        'super_admin' => '*',
        'admin' => ['access-admin', 'manage-settings', 'portfolio.manage', 'courses.manage', 'clients.manage', 'projects.manage', 'billing.manage'],
        'student' => [],
        'client' => [],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        foreach (self::MATRIX as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($perms === '*' ? self::PERMISSIONS : $perms);
        }

        $synced = 0;
        foreach (User::all() as $user) {
            $user->syncSpatieRole();
            $synced++;
        }

        $this->command->info('RbacSeeder: '.count(self::MATRIX).' roles + '.count(self::PERMISSIONS)." permissions set, {$synced} users synced.");
    }
}
