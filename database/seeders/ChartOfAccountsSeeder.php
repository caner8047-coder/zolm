<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Minimal Türkiye Tek Düzen Hesap Planı (TDHP) seed'i.
 *
 * Bu seeder, yalnızca accounting_enabled=true olan kullanıcılar için
 * ilk çalıştırmada temel hesap grubunu ve hesapları oluşturur.
 *
 * İdempotent: Kullanıcı başına mevcut kayıtlar atlanır (firstOrCreate).
 *
 * Kapsam: Faz 2 MVP — manuel fiş için yeterli minimum hesap seti.
 * İleride genişletilecek; şu an sadece 1, 3, 4, 6 numaralı ana sınıflar.
 */
class ChartOfAccountsSeeder extends Seeder
{
    /**
     * Tek kullanıcı için hesap planı oluştur.
     */
    public function runForUser(int $userId): void
    {
        $groups = $this->accountGroups($userId);
        $this->createAccounts($userId, $groups);
    }

    /**
     * Tüm aktif kullanıcılar için çalıştır (genel seeder çağrısı).
     */
    public function run(): void
    {
        User::where('is_active', true)->chunk(50, function ($users) {
            foreach ($users as $user) {
                $this->runForUser($user->id);
            }
        });
    }

    /**
     * Ana hesap grupları (TDHP'ye uygun, MVP kapsamı).
     */
    private function accountGroups(int $userId): array
    {
        $groupDefs = [
            // Dönen Varlıklar
            ['code' => '1',   'name' => 'Dönen Varlıklar',         'type' => 'asset',     'normal_balance' => 'debit',  'sort_order' => 10],
            ['code' => '10',  'name' => 'Hazır Değerler',           'type' => 'asset',     'normal_balance' => 'debit',  'sort_order' => 11, 'parent_code' => '1'],
            ['code' => '12',  'name' => 'Ticari Alacaklar',         'type' => 'asset',     'normal_balance' => 'debit',  'sort_order' => 12, 'parent_code' => '1'],
            // Duran Varlıklar
            ['code' => '2',   'name' => 'Duran Varlıklar',         'type' => 'asset',     'normal_balance' => 'debit',  'sort_order' => 20],
            // Kısa Vadeli Yabancı Kaynaklar
            ['code' => '3',   'name' => 'Kısa Vadeli Borçlar',     'type' => 'liability', 'normal_balance' => 'credit', 'sort_order' => 30],
            ['code' => '32',  'name' => 'Ticari Borçlar',          'type' => 'liability', 'normal_balance' => 'credit', 'sort_order' => 31, 'parent_code' => '3'],
            // Öz Kaynaklar
            ['code' => '5',   'name' => 'Öz Kaynaklar',            'type' => 'equity',    'normal_balance' => 'credit', 'sort_order' => 50],
            // Gelirler
            ['code' => '6',   'name' => 'Gelirler',                'type' => 'revenue',   'normal_balance' => 'credit', 'sort_order' => 60],
            // Giderler
            ['code' => '7',   'name' => 'Giderler',                'type' => 'expense',   'normal_balance' => 'debit',  'sort_order' => 70],
        ];

        $createdGroups = [];

        // İlk geçiş: parent olmayan gruplar
        foreach ($groupDefs as $def) {
            if (!isset($def['parent_code'])) {
                $group = AccountGroup::firstOrCreate(
                    ['user_id' => $userId, 'code' => $def['code']],
                    [
                        'name'           => $def['name'],
                        'type'           => $def['type'],
                        'normal_balance' => $def['normal_balance'],
                        'sort_order'     => $def['sort_order'],
                        'is_active'      => true,
                    ]
                );
                $createdGroups[$def['code']] = $group;
            }
        }

        // İkinci geçiş: parent'lı gruplar
        foreach ($groupDefs as $def) {
            if (isset($def['parent_code'])) {
                $parentId = $createdGroups[$def['parent_code']]->id ?? null;
                $group = AccountGroup::firstOrCreate(
                    ['user_id' => $userId, 'code' => $def['code']],
                    [
                        'parent_id'      => $parentId,
                        'name'           => $def['name'],
                        'type'           => $def['type'],
                        'normal_balance' => $def['normal_balance'],
                        'sort_order'     => $def['sort_order'],
                        'is_active'      => true,
                    ]
                );
                $createdGroups[$def['code']] = $group;
            }
        }

        return $createdGroups;
    }

    /**
     * Temel hesaplar — Faz 2 MVP için minimum set.
     */
    private function createAccounts(int $userId, array $groups): void
    {
        $accountDefs = [
            // --- Hazır Değerler ---
            [
                'code' => '100', 'name' => 'Kasa', 'type' => 'asset',
                'normal_balance' => 'debit', 'group_code' => '10',
                'is_cash_account' => true, 'is_system' => true,
            ],
            [
                'code' => '102', 'name' => 'Bankalar', 'type' => 'asset',
                'normal_balance' => 'debit', 'group_code' => '10',
                'is_bank_account' => true, 'is_system' => true,
            ],
            // --- Ticari Alacaklar ---
            [
                'code' => '120', 'name' => 'Alıcılar', 'type' => 'asset',
                'normal_balance' => 'debit', 'group_code' => '12',
                'is_ar_account' => true, 'is_system' => true,
            ],
            // --- Ticari Borçlar ---
            [
                'code' => '320', 'name' => 'Satıcılar', 'type' => 'liability',
                'normal_balance' => 'credit', 'group_code' => '32',
                'is_ap_account' => true, 'is_system' => true,
            ],
            // --- Gelirler ---
            [
                'code' => '600', 'name' => 'Yurt İçi Satışlar', 'type' => 'revenue',
                'normal_balance' => 'credit', 'group_code' => '6',
                'is_system' => true,
            ],
            // --- Giderler ---
            [
                'code' => '760', 'name' => 'Pazarlama/Satış Giderleri', 'type' => 'expense',
                'normal_balance' => 'debit', 'group_code' => '7',
                'is_system' => true,
            ],
            [
                'code' => '770', 'name' => 'Genel Yönetim Giderleri', 'type' => 'expense',
                'normal_balance' => 'debit', 'group_code' => '7',
                'is_system' => true,
            ],
        ];

        foreach ($accountDefs as $def) {
            $groupId = isset($def['group_code']) ? ($groups[$def['group_code']]->id ?? null) : null;

            Account::firstOrCreate(
                ['user_id' => $userId, 'code' => $def['code']],
                [
                    'account_group_id' => $groupId,
                    'name'             => $def['name'],
                    'type'             => $def['type'],
                    'normal_balance'   => $def['normal_balance'],
                    'is_cash_account'  => $def['is_cash_account'] ?? false,
                    'is_bank_account'  => $def['is_bank_account'] ?? false,
                    'is_ar_account'    => $def['is_ar_account'] ?? false,
                    'is_ap_account'    => $def['is_ap_account'] ?? false,
                    'is_system'        => $def['is_system'] ?? false,
                    'is_active'        => true,
                    'currency_code'    => 'TRY',
                ]
            );
        }
    }
}
