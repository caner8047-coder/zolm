<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use Illuminate\Support\Facades\DB;

trait HasHrPermissions
{
    protected function assignHrAdminRole(User $user): void
    {
        $adminRole = DB::table('roles')->where('slug', 'hr_admin')->first();

        if ($adminRole) {
            // Mevcut kayıtları temizle
            DB::table('model_has_roles')
                ->where('model_id', $user->id)
                ->where('model_type', User::class)
                ->delete();

            // Yeni rol ata
            DB::table('model_has_roles')->insert([
                'role_id' => $adminRole->id,
                'model_id' => $user->id,
                'model_type' => User::class,
            ]);
        }
    }
}
