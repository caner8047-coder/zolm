<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('slug', 'admin')->first();

        $admin = User::firstOrNew(['email' => 'admin@zolm.test']);

        if (! $admin->exists) {
            $admin->name = 'Admin';
            $admin->password = Hash::make('password');
        }

        $admin->forceFill([
            'role' => 'admin',
            'role_id' => $adminRole?->id,
            'is_active' => true,
        ])->save();

        // Create System Actor
        $systemUser = User::firstOrNew(['email' => 'system@zolm.com']);
        if (!$systemUser->exists) {
            $systemUser->name = 'System Actor';
            $systemUser->password = Hash::make(bin2hex(random_bytes(16)));
        }
        $systemUser->forceFill([
            'role' => 'admin',
            'role_id' => $adminRole?->id,
            'is_active' => true,
        ])->save();
    }
}
