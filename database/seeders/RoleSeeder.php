<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Admin', 'slug' => 'admin'],
            ['name' => 'Üretim Sorumlusu', 'slug' => 'uretim_sorumlusu'],
            ['name' => 'Operasyon Sorumlusu', 'slug' => 'operasyon_sorumlusu'],
            ['name' => 'CRM Sorumlusu', 'slug' => 'crm_sorumlusu'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
