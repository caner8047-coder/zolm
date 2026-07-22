<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class HrMockDataSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('hr:seed-mock-data');
    }
}
