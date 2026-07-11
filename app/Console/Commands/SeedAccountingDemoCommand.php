<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\LegalEntity;
use App\Models\Party;
use App\Models\PartyRole;
use App\Models\PartyIdentity;
use App\Models\Warehouse;
use App\Models\MpProduct;
use App\Models\Product;
use App\Models\Account;
use App\Models\Receivable;
use App\Models\Payable;
use App\Models\ReceivableAllocation;
use App\Models\PayableAllocation;
use App\Models\Collection;
use App\Models\Payment;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\StockMovement;
use App\Models\StockBalance;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\MoneyTransfer;
use App\Models\CashAccount;
use App\Models\PartyLedgerEntry;
use App\Models\BankAccount;
use App\Services\Accounting\PartyLedgerService;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\StockService;
use App\Services\Accounting\TradeService;
use App\Services\Accounting\CashBankService;
use App\Services\Accounting\CollectionPaymentService;
use Illuminate\Support\Facades\DB;

class SeedAccountingDemoCommand extends Command
{
    protected $signature = 'accounting:seed-demo {--user= : Zorunlu User ID} {--reset : Demo verileri sıfırlar ve baştan kurar} {--force : Production ortamında çalışmayı zorlar}';
    protected $description = 'Kullanıcı için ZOLM ERP demo verileri oluşturur veya resetler.';

    public function handle()
    {
        if (app()->environment('production') && !$this->option('force')) {
            $this->error('Hata: Canlı (Production) ortamda demo veri seeder sadece --force opsiyonu ile çalıştırılabilir.');
            return 1;
        }

        $userId = $this->option('user');
        if (!$userId) {
            $this->error('Hata: --user parametresi zorunludur (örn: --user=1).');
            return 1;
        }

        $user = User::find($userId);
        if (!$user) {
            $this->error("Hata: {$userId} ID'sine sahip kullanıcı bulunamadı.");
            return 1;
        }

        $reset = $this->option('reset');

        if ($reset) {
            $this->info("Kullanıcı #{$userId} için eski demo verileri temizleniyor...");
            $this->resetDemoData($userId);
        }

        $this->info("Kullanıcı #{$userId} için demo verileri oluşturuluyor...");
        $this->seedDemoData($userId);

        $this->info('Demo verileri başarıyla oluşturuldu!');
        
        $this->printSummary($userId);

        return 0;
    }

    protected function resetDemoData(int $userId): void
    {
        DB::transaction(function () use ($userId) {
            $demoLe = LegalEntity::where('user_id', $userId)->where('tax_number', '1234567890')->first();

            // 1. Demo ID listelerini toplayalım
            $demoCollectionIds = Collection::where('user_id', $userId)->where('source_key', 'like', 'demo_%')->pluck('id');
            $demoPaymentIds = Payment::where('user_id', $userId)->where('source_key', 'like', 'demo_%')->pluck('id');
            $demoReceivableIds = Receivable::where('user_id', $userId)->where('document_number', 'like', 'DEMO-%')->pluck('id');
            $demoPayableIds = Payable::where('user_id', $userId)->where('document_number', 'like', 'DEMO-%')->pluck('id');
            $demoTransferIds = MoneyTransfer::where('user_id', $userId)->where('source_key', 'like', 'demo_%')->pluck('id');

            // 6. ÖNCE JournalLines / JournalEntries (Sadece demo fişlere bağlı olanları temizle)
            $demoJournalIds = collect();

            // a. Doğrudan demo_ ile başlayan source_key'e sahip olanlar
            $directJournalIds = JournalEntry::where('user_id', $userId)->where('source_key', 'like', 'demo_%')->pluck('id');
            $demoJournalIds = $demoJournalIds->merge($directJournalIds);

            // b. Demo Receivables/Payables yevmiye fişleri
            $recJournalIds = Receivable::whereIn('id', $demoReceivableIds)->whereNotNull('journal_entry_id')->pluck('journal_entry_id');
            $demoJournalIds = $demoJournalIds->merge($recJournalIds);

            $payJournalIds = Payable::whereIn('id', $demoPayableIds)->whereNotNull('journal_entry_id')->pluck('journal_entry_id');
            $demoJournalIds = $demoJournalIds->merge($payJournalIds);

            // c. Demo Collections/Payments yevmiye fişleri
            $collJournalIds = Collection::whereIn('id', $demoCollectionIds)->whereNotNull('journal_entry_id')->pluck('journal_entry_id');
            $demoJournalIds = $demoJournalIds->merge($collJournalIds);

            $pmtJournalIds = Payment::whereIn('id', $demoPaymentIds)->whereNotNull('journal_entry_id')->pluck('journal_entry_id');
            $demoJournalIds = $demoJournalIds->merge($pmtJournalIds);

            // d. Demo Transfers yevmiye fişleri
            $trJournalIds = MoneyTransfer::whereIn('id', $demoTransferIds)->whereNotNull('journal_entry_id')->pluck('journal_entry_id');
            $demoJournalIds = $demoJournalIds->merge($trJournalIds);

            // e. Demo Sipariş post fişleri
            $demoSalesIds = SalesOrder::where('user_id', $userId)->where('source_key', 'like', 'demo_%')->pluck('id');
            $demoPurchaseIds = PurchaseOrder::where('user_id', $userId)->where('source_key', 'like', 'demo_%')->pluck('id');

            $orderJournalKeys = [];
            foreach ($demoSalesIds as $dsId) {
                $orderJournalKeys[] = 'sales_order_post_' . $dsId;
            }
            foreach ($demoPurchaseIds as $dpId) {
                $orderJournalKeys[] = 'purchase_order_post_' . $dpId;
            }
            if (!empty($orderJournalKeys)) {
                $orderJournals = JournalEntry::where('user_id', $userId)->whereIn('source_key', $orderJournalKeys)->pluck('id');
                $demoJournalIds = $demoJournalIds->merge($orderJournals);
            }

            $demoJournalIds = $demoJournalIds->unique()->filter()->values();

            JournalLine::whereIn('journal_entry_id', $demoJournalIds)->delete();
            JournalEntry::whereIn('id', $demoJournalIds)->delete();

            // Sadece demo collection/payment/receivable/payable kayıtlarına bağlı allocation'ları temizleyelim
            ReceivableAllocation::where('user_id', $userId)
                ->where(function($q) use ($demoCollectionIds, $demoReceivableIds) {
                    $q->whereIn('collection_id', $demoCollectionIds)
                      ->orWhereIn('receivable_id', $demoReceivableIds);
                })->delete();

            PayableAllocation::where('user_id', $userId)
                ->where(function($q) use ($demoPaymentIds, $demoPayableIds) {
                    $q->whereIn('payment_id', $demoPaymentIds)
                      ->orWhereIn('payable_id', $demoPayableIds);
                })->delete();

            // 2. Collections / Payments
            Collection::where('user_id', $userId)->where('source_key', 'like', 'demo_%')->delete();
            Payment::where('user_id', $userId)->where('source_key', 'like', 'demo_%')->delete();

            // 3. Receivables / Payables
            Receivable::where('user_id', $userId)->where('document_number', 'like', 'DEMO-%')->delete();
            Payable::where('user_id', $userId)->where('document_number', 'like', 'DEMO-%')->delete();

            // 4. PartyLedgerEntries
            PartyLedgerEntry::where('user_id', $userId)->where('source_key', 'like', 'demo_%')->delete();

            // 5. MoneyTransfers
            MoneyTransfer::where('user_id', $userId)->where('source_key', 'like', 'demo_%')->delete();
            StockMovement::where('user_id', $userId)->where('source_key', 'like', 'demo_%')->delete();
            
            // 8. StockBalances
            StockBalance::where('user_id', $userId)->where('stock_code', 'like', 'demo_prod_%')->delete();

            // 9. SalesOrders / PurchaseOrders
            $demoSalesIds = SalesOrder::where('user_id', $userId)->where('source_key', 'like', 'demo_%')->pluck('id');
            SalesOrderItem::whereIn('sales_order_id', $demoSalesIds)->delete();
            SalesOrder::whereIn('id', $demoSalesIds)->delete();

            $demoPurchaseIds = PurchaseOrder::where('user_id', $userId)->where('source_key', 'like', 'demo_%')->pluck('id');
            PurchaseOrderItem::whereIn('purchase_order_id', $demoPurchaseIds)->delete();
            PurchaseOrder::whereIn('id', $demoPurchaseIds)->delete();

            // 10. MpProducts
            MpProduct::where('user_id', $userId)->where('stock_code', 'like', 'demo_prod_%')->delete();

            // [P1 FIX] Global Product tablosunu silme kaldırıldı.

            // 12. Warehouses (Demo marker'lı olanlar)
            Warehouse::where('user_id', $userId)->where('meta_json->demo', true)->delete();

            // 13. Kasa & Banka (GL Hesapları Dahil, Demo marker'lı olanlar)
            $cashAccounts = CashAccount::where('user_id', $userId)
                ->whereHas('account', function($q) {
                    $q->where('meta_json->demo', true);
                })->get();
            foreach ($cashAccounts as $ca) {
                $ca->delete();
                Account::where('user_id', $userId)->where('id', $ca->account_id)->delete();
            }

            $bankAccounts = BankAccount::where('user_id', $userId)
                ->whereHas('account', function($q) {
                    $q->where('meta_json->demo', true);
                })->get();
            foreach ($bankAccounts as $ba) {
                $ba->delete();
                Account::where('user_id', $userId)->where('id', $ba->account_id)->delete();
            }

            // 14. Parties / Roles / Identities (Demo marker'lı olanlar)
            $parties = Party::where('user_id', $userId)->where('meta_json->demo', true)->get();
            foreach ($parties as $p) {
                PartyIdentity::where('party_id', $p->id)->delete();
                PartyRole::where('party_id', $p->id)->delete();
                $p->delete();
            }

            // 15. LegalEntities
            if ($demoLe) {
                $demoLe->delete();
            }
        });
    }

    protected function seedDemoData(int $userId): void
    {
        DB::transaction(function () use ($userId) {
            // 1. Legal Entity
            $legalEntity = LegalEntity::firstOrCreate([
                'user_id' => $userId,
                'tax_number' => '1234567890'
            ], [
                'name' => 'ZOLM Demo Ticaret A.Ş.',
                'tax_office' => 'Kadıköy',
                'address' => 'İstanbul',
            ]);

            // 2. Hesap Planı (GL Accounts)
            $accounts = [
                '120' => ['name' => 'Alıcılar', 'type' => 'asset', 'normal' => 'debit', 'ar' => true, 'ap' => false],
                '320' => ['name' => 'Satıcılar', 'type' => 'liability', 'normal' => 'credit', 'ar' => false, 'ap' => true],
                '600' => ['name' => 'Yurt İçi Satışlar', 'type' => 'revenue', 'normal' => 'credit', 'ar' => false, 'ap' => false],
                '153' => ['name' => 'Ticari Mallar', 'type' => 'asset', 'normal' => 'debit', 'ar' => false, 'ap' => false],
                '391' => ['name' => 'Hesaplanan KDV', 'type' => 'liability', 'normal' => 'credit', 'ar' => false, 'ap' => false],
                '191' => ['name' => 'İndirilecek KDV', 'type' => 'asset', 'normal' => 'debit', 'ar' => false, 'ap' => false],
                '770' => ['name' => 'Genel Yönetim Giderleri', 'type' => 'expense', 'normal' => 'debit', 'ar' => false, 'ap' => false],
            ];

            $glAccounts = [];
            foreach ($accounts as $code => $info) {
                $glAccounts[$code] = Account::firstOrCreate([
                    'user_id' => $userId,
                    'code' => $code
                ], [
                    'name' => $info['name'],
                    'type' => $info['type'],
                    'normal_balance' => $info['normal'],
                    'is_ar_account' => $info['ar'],
                    'is_ap_account' => $info['ap'],
                    'is_active' => true,
                    'currency_code' => 'TRY',
                    'meta_json' => ['demo' => true, 'demo_seed' => 'accounting_p14'],
                ]);
            }

            // 3. Müşteri Cari
            $customer1 = Party::firstOrCreate([
                'user_id' => $userId,
                'display_name' => 'ZOLM Demo Perakende Müşteri A.Ş.'
            ], [
                'party_type' => 'customer',
                'status' => 'active',
                'primary_email' => 'musteri@example.com',
                'meta_json' => ['demo' => true, 'demo_seed' => 'accounting_p14'],
            ]);
            PartyRole::firstOrCreate(['user_id' => $userId, 'party_id' => $customer1->id, 'role' => 'customer']);
            PartyIdentity::firstOrCreate([
                'user_id' => $userId,
                'party_id' => $customer1->id,
                'source_type' => 'demo',
                'identity_kind' => 'email',
                'identity_value' => 'perakende@demo.zolm.com'
            ]);

            $customer2 = Party::firstOrCreate([
                'user_id' => $userId,
                'display_name' => 'ZOLM Demo Kurumsal Müşteri Ltd.'
            ], [
                'party_type' => 'customer',
                'status' => 'active',
                'meta_json' => ['demo' => true, 'demo_seed' => 'accounting_p14'],
            ]);
            PartyRole::firstOrCreate(['user_id' => $userId, 'party_id' => $customer2->id, 'role' => 'customer']);

            // 4. Tedarikçi Cari
            $supplier1 = Party::firstOrCreate([
                'user_id' => $userId,
                'display_name' => 'ZOLM Demo Ana Sağlayıcı A.Ş.'
            ], [
                'party_type' => 'supplier',
                'status' => 'active',
                'primary_email' => 'tedarikci@example.com',
                'meta_json' => ['demo' => true, 'demo_seed' => 'accounting_p14'],
            ]);
            PartyRole::firstOrCreate(['user_id' => $userId, 'party_id' => $supplier1->id, 'role' => 'supplier']);
            PartyIdentity::firstOrCreate([
                'user_id' => $userId,
                'party_id' => $supplier1->id,
                'source_type' => 'demo',
                'identity_kind' => 'email',
                'identity_value' => 'saglayici@demo.zolm.com'
            ]);

            $supplier2 = Party::firstOrCreate([
                'user_id' => $userId,
                'display_name' => 'ZOLM Demo Ambalaj Sanayi Ltd.'
            ], [
                'party_type' => 'supplier',
                'status' => 'active',
                'meta_json' => ['demo' => true, 'demo_seed' => 'accounting_p14'],
            ]);
            PartyRole::firstOrCreate(['user_id' => $userId, 'party_id' => $supplier2->id, 'role' => 'supplier']);

            // 5. Kasa Hesabı
            $cashAccountExists = CashAccount::where('user_id', $userId)->where('name', 'ZOLM Demo Merkez Kasa (TL)')->first();
            if (!$cashAccountExists) {
                $cashAccount = app(CashBankService::class)->createCashAccount($userId, 'ZOLM Demo Merkez Kasa (TL)', 'TRY', $legalEntity->id);
            } else {
                $cashAccount = $cashAccountExists;
            }
            $cashAccount->account->update([
                'meta_json' => ['demo' => true, 'demo_seed' => 'accounting_p14']
            ]);

            // 6. Banka Hesabı
            $bankAccountExists = BankAccount::where('user_id', $userId)->where('bank_name', 'ZOLM Demo Ziraat Bankası (Vadesiz)')->first();
            if (!$bankAccountExists) {
                $bankAccount = app(CashBankService::class)->createBankAccount($userId, [
                    'bank_name' => 'ZOLM Demo Ziraat Bankası (Vadesiz)',
                    'account_number' => '123456789',
                    'iban' => 'TR990001000000000012345678',
                    'currency_code' => 'TRY',
                    'legal_entity_id' => $legalEntity->id,
                ]);
            } else {
                $bankAccount = $bankAccountExists;
            }
            $bankAccount->account->update([
                'meta_json' => ['demo' => true, 'demo_seed' => 'accounting_p14']
            ]);

            // 7. Depo (Warehouse)
            $warehouse = Warehouse::firstOrCreate([
                'user_id' => $userId,
                'code' => 'demo-depo-merkez'
            ], [
                'name' => 'ZOLM Demo Merkez Depo',
                'is_default' => true,
                'is_active' => true,
                'legal_entity_id' => $legalEntity->id,
                'meta_json' => ['demo' => true, 'demo_seed' => 'accounting_p14'],
            ]);

            // 8. Ürünler
            $productsData = [
                'demo_prod_1' => ['name' => 'ZOLM Masa Sandalye Seti', 'cogs' => 1000.0, 'sale' => 2500.0],
                'demo_prod_2' => ['name' => 'ZOLM Ofis Masası', 'cogs' => 800.0, 'sale' => 1800.0],
                'demo_prod_3' => ['name' => 'ZOLM Ergonomik Koltuk', 'cogs' => 600.0, 'sale' => 1400.0],
                'demo_prod_4' => ['name' => 'ZOLM Kitaplık Raflı', 'cogs' => 400.0, 'sale' => 900.0],
                'demo_prod_5' => ['name' => 'ZOLM Metal Sehpa', 'cogs' => 200.0, 'sale' => 500.0],
            ];

            foreach ($productsData as $code => $data) {
                // Global Master
                Product::firstOrCreate([
                    'stok_kodu' => $code
                ], [
                    'urun_adi' => $data['name'],
                    'parca' => 1,
                    'desi' => 15.00,
                    'tutar' => $data['sale'],
                    'is_active' => true,
                ]);

                // MpProduct
                MpProduct::firstOrCreate([
                    'user_id' => $userId,
                    'stock_code' => $code
                ], [
                    'product_name' => $data['name'],
                    'barcode' => 'BAR-' . $code,
                    'cogs' => $data['cogs'],
                    'sale_price' => $data['sale'],
                    'status' => 'active',
                    'stock_quantity' => 0,
                ]);

                // 9. Stok Açılış Hareketi (100 adet)
                $movement = app(StockService::class)->recordMovement([
                    'user_id' => $userId,
                    'warehouse_id' => $warehouse->id,
                    'stock_code' => $code,
                    'movement_type' => 'in_opening',
                    'direction' => 'in',
                    'quantity' => 100,
                    'unit_cost' => $data['cogs'],
                    'source_type' => 'opening',
                    'source_key' => 'demo_stock_opening_' . $code,
                    'reference_number' => 'ACC-OPEN-001',
                    'legal_entity_id' => $legalEntity->id,
                    'description' => 'Açılış Stok Kaydı',
                ]);
                $movement->update([
                    'meta_json' => ['demo' => true, 'demo_seed' => 'accounting_p14']
                ]);
            }

            // 10. Satış Siparişi (SalesOrder)
            $salesOrderHeader = [
                'user_id' => $userId,
                'party_id' => $customer1->id,
                'legal_entity_id' => $legalEntity->id,
                'warehouse_id' => $warehouse->id,
                'document_number' => 'DEMO-SO-001',
                'order_date' => now()->toDateString(),
                'currency_code' => 'TRY',
                'exchange_rate' => 1.0,
                'discount_amount' => 100.0,
                'description' => 'Demo Müşteri Siparişi',
                'source_key' => 'demo_sales_order_1',
            ];

            $salesOrderItems = [
                [
                    'stock_code' => 'demo_prod_1',
                    'quantity' => 2,
                    'unit_price' => 2500.0,
                    'vat_rate' => 20.0,
                    'discount_rate' => 0.0,
                ],
                [
                    'stock_code' => 'demo_prod_3',
                    'quantity' => 1,
                    'unit_price' => 1400.0,
                    'vat_rate' => 20.0,
                    'discount_rate' => 0.0,
                ]
            ];

            $salesOrder = app(TradeService::class)->createSalesOrder($salesOrderHeader, $salesOrderItems);
            if ($salesOrder->status === 'draft') {
                app(TradeService::class)->approveSalesOrder($salesOrder);
            }

            // 11. Satın Alma Siparişi (PurchaseOrder)
            $purchaseOrderHeader = [
                'user_id' => $userId,
                'party_id' => $supplier1->id,
                'legal_entity_id' => $legalEntity->id,
                'warehouse_id' => $warehouse->id,
                'document_number' => 'DEMO-PO-001',
                'order_date' => now()->toDateString(),
                'currency_code' => 'TRY',
                'exchange_rate' => 1.0,
                'discount_amount' => 0.0,
                'description' => 'Demo Satın Alma Siparişi',
                'source_key' => 'demo_purchase_order_1',
            ];

            $purchaseOrderItems = [
                [
                    'stock_code' => 'demo_prod_4',
                    'quantity' => 10,
                    'unit_price' => 400.0,
                    'vat_rate' => 20.0,
                    'discount_rate' => 0.0,
                ]
            ];

            $purchaseOrder = app(TradeService::class)->createPurchaseOrder($purchaseOrderHeader, $purchaseOrderItems);
            if ($purchaseOrder->status === 'draft') {
                app(TradeService::class)->approvePurchaseOrder($purchaseOrder);
            }

            // 12. Tahsilat (Collection)
            $receivable = Receivable::where('user_id', $userId)->where('document_number', 'DEMO-SO-001')->first();
            if ($receivable) {
                $cashGlAccount = $cashAccount->account;
                $collection = app(CollectionPaymentService::class)->recordCollection([
                    'user_id' => $userId,
                    'party_id' => $customer1->id,
                    'account_id' => $cashGlAccount->id,
                    'amount' => 3000.0,
                    'collection_date' => now()->toDateString(),
                    'payment_method' => 'cash',
                    'description' => 'Demo Kasa Tahsilatı',
                    'legal_entity_id' => $legalEntity->id,
                    'reference_number' => 'REF-COLL-001',
                    'source_key' => 'demo_collection_1',
                ]);

                $alreadyAllocated = (float) $collection->allocations()->sum('amount');
                $remaining = (float) $collection->amount - $alreadyAllocated;
                if ($remaining >= 3000.0) {
                    app(CollectionPaymentService::class)->allocateCollection($collection, [
                        [
                            'receivable_id' => $receivable->id,
                            'amount' => 3000.0,
                        ]
                    ]);
                }
            }

            // 13. Ödeme (Payment)
            $payable = Payable::where('user_id', $userId)->where('document_number', 'DEMO-PO-001')->first();
            if ($payable) {
                $bankGlAccount = $bankAccount->account;
                $payment = app(CollectionPaymentService::class)->recordPayment([
                    'user_id' => $userId,
                    'party_id' => $supplier1->id,
                    'account_id' => $bankGlAccount->id,
                    'amount' => 2000.0,
                    'payment_date' => now()->toDateString(),
                    'payment_method' => 'bank',
                    'description' => 'Demo Banka Ödemesi',
                    'legal_entity_id' => $legalEntity->id,
                    'reference_number' => 'REF-PAY-001',
                    'source_key' => 'demo_payment_1',
                ]);

                $alreadyAllocatedPay = (float) $payment->allocations()->sum('amount');
                $remainingPay = (float) $payment->amount - $alreadyAllocatedPay;
                if ($remainingPay >= 2000.0) {
                    app(CollectionPaymentService::class)->allocatePayment($payment, [
                        [
                            'payable_id' => $payable->id,
                            'amount' => 2000.0,
                        ]
                    ]);
                }
            }

            // 14. Virman
            app(CashBankService::class)->transferFunds([
                'user_id' => $userId,
                'from_account_id' => $cashAccount->account_id,
                'to_account_id' => $bankAccount->account_id,
                'amount' => 1000.0,
                'exchange_rate' => 1.0,
                'transfer_date' => now()->toDateString(),
                'description' => 'Demo Kasadan Bankaya Transfer',
                'reference_number' => 'REF-TX-001',
                'legal_entity_id' => $legalEntity->id,
                'source_key' => 'demo_transfer_1',
            ]);

            // 15. Manuel Yevmiye Fişi
            app(JournalService::class)->postManual([
                'user_id' => $userId,
                'entry_date' => now()->toDateString(),
                'entry_type' => 'manual',
                'description' => 'Demo Diğer Giderler Fişi',
                'currency_code' => 'TRY',
                'exchange_rate' => 1.0,
                'legal_entity_id' => $legalEntity->id,
                'source_key' => 'demo_journal_entry_1',
                'meta_json' => ['demo' => true, 'demo_seed' => 'accounting_p14'],
            ], [
                [
                    'account_id' => $glAccounts['770']->id,
                    'debit_amount' => 500.0,
                    'credit_amount' => 0.0,
                ],
                [
                    'account_id' => $cashAccount->account_id,
                    'debit_amount' => 0.0,
                    'credit_amount' => 500.0,
                ]
            ]);
        });
    }

    protected function printSummary(int $userId): void
    {
        $legalEntitiesCount = LegalEntity::where('user_id', $userId)->where('tax_number', '1234567890')->count();
        $partiesCount = Party::where('user_id', $userId)->where('display_name', 'like', 'ZOLM Demo %')->count();
        $productsCount = MpProduct::where('user_id', $userId)->where('stock_code', 'like', 'demo_prod_%')->count();
        $stockMovementsCount = StockMovement::where('user_id', $userId)->where('source_key', 'like', 'demo_%')->count();
        $salesCount = SalesOrder::where('user_id', $userId)->where('source_key', 'like', 'demo_%')->count();
        $purchasesCount = PurchaseOrder::where('user_id', $userId)->where('source_key', 'like', 'demo_%')->count();
        $collectionsCount = Collection::where('user_id', $userId)->where('source_key', 'like', 'demo_%')->count();
        $paymentsCount = Payment::where('user_id', $userId)->where('source_key', 'like', 'demo_%')->count();
        $journalEntriesCount = JournalEntry::where('user_id', $userId)->where('source_key', 'like', 'demo_%')->count();

        $totalReceivable = (float) Receivable::where('user_id', $userId)->where('document_number', 'like', 'DEMO-%')->sum('amount');
        $totalPayable = (float) Payable::where('user_id', $userId)->where('document_number', 'like', 'DEMO-%')->sum('amount');
        $totalStockQty = (int) StockBalance::where('user_id', $userId)->where('stock_code', 'like', 'demo_prod_%')->sum('quantity');

        $this->table(
            ['Metrik', 'Değer'],
            [
                ['Legal Entity Sayısı', $legalEntitiesCount],
                ['Party Sayısı', $partiesCount],
                ['Ürün Sayısı', $productsCount],
                ['Stok Hareketi Sayısı', $stockMovementsCount],
                ['Satış Sayısı', $salesCount],
                ['Satın Alma Sayısı', $purchasesCount],
                ['Tahsilat Sayısı', $collectionsCount],
                ['Ödeme Sayısı', $paymentsCount],
                ['Yevmiye Sayısı', $journalEntriesCount],
                ['Toplam Alacak Değeri', '₺' . number_format($totalReceivable, 2, ',', '.')],
                ['Toplam Borç Değeri', '₺' . number_format($totalPayable, 2, ',', '.')],
                ['Toplam Stok Miktarı', $totalStockQty],
            ]
        );
    }
}
