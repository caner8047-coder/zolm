<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;

class DefaultProfileSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@zolm.test')->first();

        if (! $admin) {
            return;
        }

        $this->runForUser($admin->id);
    }

    /**
     * Belirtilen kullanıcı için varsayılan motor profillerini idempotent oluşturur.
     */
    public function runForUser(int $userId): void
    {
        // Varsayılan Üretim Profili
        Profile::firstOrCreate(
            ['name' => 'Varsayılan Üretim', 'user_id' => $userId, 'type' => 'production'],
            [
                'is_default' => true,
                'input_config' => [
                    'sheet_name' => 'Siparişlerim-Detaylı',
                    'category_column' => 'Renk Etiketi',
                    'categories' => [
                        'BERJER' => 'BERJER',
                        'KÖŞE & KANEPE' => 'KÖŞE VE KANEPE',
                        'PUF & BENCH' => 'PUF',
                    ],
                ],
                'output_config' => [
                    'files' => [
                        ['name' => 'GÜNLÜK SİPARİŞLER', 'sheets' => ['TOPLAM SİPARİŞ', 'SİPARİŞ TAKİP']],
                        ['name' => 'BERJER', 'sheets' => ['DENİZLİ TOPLAM SİPARİŞ', 'NAZİLLİ SİPARİŞ TAKİP', 'NAZİLLİ KARGO TAKİP']],
                        ['name' => 'PUF', 'sheets' => ['DENİZLİ TOPLAM SİPARİŞ', 'NAZİLLİ SİPARİŞ TAKİP', 'NAZİLLİ KARGO TAKİP']],
                        ['name' => 'KÖŞE VE KANEPE', 'sheets' => ['DENİZLİ TOPLAM SİPARİŞ', 'NAZİLLİ SİPARİŞ TAKİP', 'NAZİLLİ KARGO TAKİP']],
                    ],
                ],
            ]
        );

        // Varsayılan Operasyon Profili
        Profile::firstOrCreate(
            ['name' => 'Varsayılan Operasyon', 'user_id' => $userId, 'type' => 'operation'],
            [
                'is_default' => true,
                'input_config' => [
                    'sheet_name' => 'Siparişlerim-Detaylı',
                ],
                'output_config' => [
                    'files' => [
                        ['name' => 'OPERASYONLİSTE'],
                    ],
                ],
            ]
        );
    }
}
