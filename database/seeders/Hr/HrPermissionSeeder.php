<?php

namespace Database\Seeders\Hr;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HrPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = config('hr_permissions', require base_path('app/Modules/Hr/Core/Config/hr_permissions.php'));

        foreach ($permissions as $perm) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $perm, 'guard_name' => 'web'],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        // Varsayılan HR Admin rolü
        DB::table('roles')->updateOrInsert(
            ['slug' => 'hr_admin'],
            ['name' => 'İK Admin', 'created_at' => now(), 'updated_at' => now()]
        );
        $adminRoleId = DB::table('roles')->where('slug', 'hr_admin')->value('id');

        // Mevcut atamaları temizle ve yeniden ata
        DB::table('role_permission')->where('role_id', $adminRoleId)->delete();
        $allPermissions = DB::table('permissions')->pluck('id')->toArray();
        foreach ($allPermissions as $permId) {
            DB::table('role_permission')->insert([
                'role_id' => $adminRoleId,
                'permission_id' => $permId,
            ]);
        }

        // Varsayılan HR Manager rolü
        DB::table('roles')->updateOrInsert(
            ['slug' => 'hr_manager'],
            ['name' => 'İK Müdürü', 'created_at' => now(), 'updated_at' => now()]
        );
        $managerRoleId = DB::table('roles')->where('slug', 'hr_manager')->value('id');

        DB::table('role_permission')->where('role_id', $managerRoleId)->delete();
        $managerPermissions = DB::table('permissions')
            ->whereNotIn('name', ['hr.payroll.manage_rules', 'hr.settings.manage'])
            ->pluck('id')
            ->toArray();

        foreach ($managerPermissions as $permId) {
            DB::table('role_permission')->insert([
                'role_id' => $managerRoleId,
                'permission_id' => $permId,
            ]);
        }
    }
}
