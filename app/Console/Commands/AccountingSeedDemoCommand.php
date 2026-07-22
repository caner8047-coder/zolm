<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\JournalEntry;
use App\Models\LegalEntity;
use App\Models\Party;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\OutstandingInvoiceService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AccountingSeedDemoCommand extends Command
{
    protected $signature = 'accounting:seed-foundation {--user= : The ID of the user/tenant to seed foundation data for}';

    protected $description = 'Seed the legacy accounting foundation data for a user';

    public function handle(): int
    {
        $userId = $this->option('user');

        if (! $userId && app()->environment('production')) {
            $this->error('Production ortamında --user zorunludur; otomatik demo kullanıcı oluşturulmaz.');

            return self::FAILURE;
        }

        if ($userId) {
            $user = User::findOrFail($userId);
        } else {
            $user = User::where('role', 'admin')->first() ?: User::first();
            if (!$user) {
                $user = User::factory()->create([
                    'name' => 'Demo Admin',
                    'email' => 'demo@zolm.com',
                    'role' => 'admin',
                    'is_active' => true,
                ]);
            }
        }

        $userId = (int) $user->id;
        $this->info("Seeding legacy accounting foundation data for user ID: {$userId} ({$user->email})");

        DB::transaction(function () use ($userId) {
            // 1. Legal Entity
            $legalEntity = LegalEntity::firstOrCreate(
                ['user_id' => $userId, 'name' => 'ZOLM Demo Ticaret A.Ş.'],
                [
                    'tax_number' => '1234567890',
                    'tax_office' => 'Kadıköy Vergi Dairesi',
                    'is_active' => true,
                    'currency' => 'TRY',
                ]
            );

            // 2. Default accounts seeder
            $seeder = new ChartOfAccountsSeeder();
            $seeder->runForUser($userId);

            // 3. Custom equity/capital account group & account
            $equityGroup = AccountGroup::firstOrCreate(
                ['user_id' => $userId, 'code' => '50'],
                [
                    'name' => 'Öz Kaynaklar',
                    'type' => 'equity',
                    'normal_balance' => 'credit',
                    'sort_order' => 50,
                ]
            );

            $capitalAccount = Account::firstOrCreate(
                ['user_id' => $userId, 'code' => '500'],
                [
                    'account_group_id' => $equityGroup->id,
                    'name' => 'Sermaye',
                    'type' => 'equity',
                    'normal_balance' => 'credit',
                    'is_active' => true,
                    'currency_code' => 'TRY',
                ]
            );

            // 4. Default Warehouse
            $warehouse = Warehouse::firstOrCreate(
                ['user_id' => $userId, 'code' => 'DEMO-DEP'],
                [
                    'name' => 'Demo Ana Depo',
                    'is_default' => true,
                    'is_active' => true,
                ]
            );

            // 5. Default Parties
            $customer = Party::firstOrCreate(
                ['user_id' => $userId, 'primary_email' => 'musteri@example.com'],
                [
                    'display_name' => 'Örnek Müşteri A.Ş.',
                    'party_type' => 'customer',
                    'legal_entity_id' => $legalEntity->id,
                    'status' => 'active',
                ]
            );

            $vendor = Party::firstOrCreate(
                ['user_id' => $userId, 'primary_email' => 'tedarikci@example.com'],
                [
                    'display_name' => 'Örnek Tedarikçi Ltd.',
                    'party_type' => 'vendor',
                    'legal_entity_id' => $legalEntity->id,
                    'status' => 'active',
                ]
            );

            // 6. Demo opening/sermaye journal entry
            $cashAccount = Account::where('user_id', $userId)->where('code', '100')->first();
            if ($cashAccount && $capitalAccount) {
                // Check if demo opening entry already exists
                $exists = JournalEntry::where('user_id', $userId)
                    ->where('source_key', 'demo-opening-capital')
                    ->exists();

                if (!$exists) {
                    $journalService = app(JournalService::class);
                    $journalService->postManual([
                        'user_id' => $userId,
                        'entry_date' => now()->toDateString(),
                        'entry_type' => 'opening',
                        'source_key' => 'demo-opening-capital',
                        'description' => 'Demo Sermaye Girişi (Kasa)',
                        'legal_entity_id' => $legalEntity->id,
                    ], [
                        [
                            'account_id' => $cashAccount->id,
                            'debit_amount' => 100000.00,
                        ],
                        [
                            'account_id' => $capitalAccount->id,
                            'credit_amount' => 100000.00,
                        ]
                    ]);
                }
            }

            // 7. Demo Receivable (Sales Invoice)
            $existsReceivable = \App\Models\Receivable::where('user_id', $userId)
                ->where('document_number', 'INV-DEMO-001')
                ->exists();

            if (!$existsReceivable) {
                $invoiceService = app(OutstandingInvoiceService::class);
                $invoiceService->createReceivable([
                    'user_id' => $userId,
                    'party_id' => $customer->id,
                    'legal_entity_id' => $legalEntity->id,
                    'amount' => 5000.00,
                    'document_date' => now()->toDateString(),
                    'document_number' => 'INV-DEMO-001',
                    'currency_code' => 'TRY',
                    'exchange_rate' => 1.0,
                    'description' => 'Örnek Satış Faturası Borcu',
                ]);
            }

            // 8. Demo Payable (Purchase Invoice)
            $existsPayable = \App\Models\Payable::where('user_id', $userId)
                ->where('document_number', 'INV-DEMO-002')
                ->exists();

            if (!$existsPayable) {
                $invoiceService = app(OutstandingInvoiceService::class);
                $invoiceService->createPayable([
                    'user_id' => $userId,
                    'party_id' => $vendor->id,
                    'legal_entity_id' => $legalEntity->id,
                    'amount' => 2500.00,
                    'document_date' => now()->toDateString(),
                    'document_number' => 'INV-DEMO-002',
                    'currency_code' => 'TRY',
                    'exchange_rate' => 1.0,
                    'description' => 'Örnek Alış Faturası Alacağı',
                ]);
            }
        });

        $this->info('Legacy accounting foundation data seeded successfully.');
        return 0;
    }
}
