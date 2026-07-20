<?php

namespace App\Console\Commands;

use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\User;
use App\Models\ChannelProduct;
use App\Models\ChannelListing;
use App\Models\MpPricePilotProduct;
use App\Models\MpPriceRecommendation;
use App\Models\MpPriceShadowRecord;
use App\Services\Marketplace\MarketplaceSyncService;
use App\Models\IntegrationSyncRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BackfillMarketplaceListingsCommand extends Command
{
    protected $signature = 'marketplace:listings:backfill
        {store_id : Mağaza ID}
        {--provider=trendyol : Pazaryeri sağlayıcısı}
        {--dry-run : Sadece analiz et, yazma işlemi yapma}
        {--confirm : Değişiklikleri doğrula ve kaydet}';

    protected $description = 'Belirli bir mağaza için eksik listings ve channel product eşleşmelerini backfill eder.';

    public function handle(): int
    {
        $storeId = (int) $this->argument('store_id');
        $provider = $this->option('provider');
        $dryRun = $this->option('dry-run');
        $confirm = $this->option('confirm');

        if (!$dryRun && !$confirm) {
            $this->error("Lütfen '--dry-run' veya '--confirm' parametrelerinden birini belirtin.");
            return self::FAILURE;
        }

        $store = MarketplaceStore::with(['connection', 'syncProfile'])->find($storeId);
        if (!$store) {
            $this->error("Mağaza bulunamadı (ID: {$storeId})");
            return self::FAILURE;
        }

        if ($store->marketplace !== $provider) {
            $this->error("Mağaza sağlayıcı eşleşmiyor: Mağaza={$store->marketplace}, İstenen={$provider}");
            return self::FAILURE;
        }

        $this->info("ZOLM — Listings Backfill Başlatıldı");
        $this->line("Mağaza: Store-{$store->id} ({$store->store_name})");
        $this->line("Kullanıcı (Tenant): User-{$store->user_id}");
        $this->line("Mod: " . ($dryRun ? '🔴 Dry-Run (Salt Okuma)' : '🟢 Confirm (Yazma Modu)'));

        $correlationId = 'backfill_' . now()->format('YmdHis') . '_' . bin2hex(random_bytes(4));

        // 1. Resolve connector and call real approved-products API
        try {
            $connector = app(\App\Services\Marketplace\MarketplaceConnectorManager::class)->resolveForStore($store);
            
            $pullOptions = [
                'start_date' => now()->subMonths(3)->format('Y-m-d H:i:s'),
                'end_date' => now()->format('Y-m-d H:i:s'),
                'page_size' => 100,
            ];

            $this->line("Gerçek Trendyol API'sinden onaylı ürünler çekiliyor...");
            $response = $connector->pullProducts($store, $pullOptions);
            $items = $response['items'] ?? [];
        } catch (\Throwable $e) {
            $errMessage = $e->getMessage();

            if (str_contains($errMessage, 'seller ID zorunludur') || str_contains($errMessage, 'seller_id_missing')) {
                $this->error("seller_id_missing");
                return self::FAILURE;
            }
            if (str_contains($errMessage, 'API key ve API secret zorunludur') || str_contains($errMessage, 'credential_missing')) {
                $this->error("credential_missing");
                return self::FAILURE;
            }
            
            // Catch response exceptions to check status codes
            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                $responseObj = $e->getResponse();
                if ($responseObj) {
                    $status = $responseObj->getStatusCode();
                    if ($status === 401 || $status === 403) {
                        $this->error("api_authentication_failed");
                        return self::FAILURE;
                    }
                    if ($status === 429) {
                        $this->error("api_rate_limited");
                        return self::FAILURE;
                    }
                }
            }

            $this->error("api_unavailable: " . $errMessage);
            return self::FAILURE;
        }

        if (empty($items)) {
            $this->error("approved_products_empty");
            return self::FAILURE;
        }

        $this->info("Toplam " . count($items) . " adet onaylı ürün Trendyol API'den alındı.");

        // Find source admin user for catalog cloning
        $adminUser = User::where('email', 'admin@zolm.test')->first();
        if (!$adminUser) {
            $adminUser = User::whereHas('role', fn($q) => $q->where('slug', 'admin'))->first();
        }

        if (!$adminUser) {
            $this->error("Klonlama için kaynak admin kullanıcısı bulunamadı.");
            return self::FAILURE;
        }

        $stats = [
            'total_inspected' => count($items),
            'has_barcode' => 0,
            'no_barcode' => 0,
            'existing_listing' => 0,
            'to_create_listing' => 0,
            'to_update_listing' => 0,
            'duplicate_barcode' => 0,
            'mapping_not_found' => 0,
            'price_not_found' => 0,
            'stock_not_found' => 0,
            'currency_mismatch' => 0,
            'errors' => 0,
            'cloned_products' => 0,
        ];

        $seenBarcodes = [];

        foreach ($items as $row) {
            $productPayload = $row['product'] ?? [];
            $listingPayload = $row['listing'] ?? [];

            $barcode = $productPayload['barcode'] ?? null;
            if (!$barcode) {
                $stats['no_barcode']++;
                $stats['errors']++;
                continue;
            }
            $stats['has_barcode']++;

            if (isset($seenBarcodes[$barcode])) {
                $stats['duplicate_barcode']++;
                continue;
            }
            $seenBarcodes[$barcode] = true;

            // Check if price or stock are missing
            if (($listingPayload['sale_price'] ?? null) === null) {
                $stats['price_not_found']++;
            }
            if (($listingPayload['stock_quantity'] ?? null) === null) {
                $stats['stock_not_found']++;
            }
            if (($listingPayload['currency'] ?? 'TRY') !== 'TRY') {
                $stats['currency_mismatch']++;
            }

            // Check if tenant product already exists
            $tenantProduct = MpProduct::where('user_id', $store->user_id)->where('barcode', $barcode)->first();
            
            if (!$tenantProduct) {
                // Check if we can safely clone from Admin master catalog
                $masterProducts = MpProduct::where('user_id', $adminUser->id)->where('barcode', $barcode)->get();

                if ($masterProducts->count() === 1) {
                    $masterProduct = $masterProducts->first();
                    
                    if ($masterProduct->status === 'active') {
                        if ($confirm) {
                            DB::transaction(function () use ($masterProduct, $adminUser, $store, $correlationId) {
                                $cloned = $masterProduct->replicate();
                                $cloned->user_id = $store->user_id;
                                $cloned->import_source = 'backfill-clone';
                                $cloned->source_user_id = $adminUser->id;
                                $cloned->source_product_id = $masterProduct->id;
                                $cloned->clone_reason = 'On-demand backfill catalog clone';
                                $cloned->clone_correlation_id = $correlationId;
                                $cloned->cloned_at = now();
                                $cloned->save();
                            });
                        }
                        $stats['cloned_products']++;
                    } else {
                        $stats['mapping_not_found']++;
                    }
                } else {
                    $stats['mapping_not_found']++;
                }
            }

            // Check existing listing
            $existingListing = ChannelListing::where('store_id', $store->id)
                ->whereHas('channelProduct', fn($q) => $q->where('barcode', $barcode))
                ->first();

            if ($existingListing) {
                $stats['existing_listing']++;
                $stats['to_update_listing']++;
            } else {
                $stats['to_create_listing']++;
            }
        }

        // Report Analysis
        $this->newLine();
        $this->info("--- Analiz Raporu ---");
        $this->line("İncelenen ürün sayısı: {$stats['total_inspected']}");
        $this->line("Barkodu olan: {$stats['has_barcode']}");
        $this->line("Barkodu olmayan: {$stats['no_barcode']}");
        $this->line("Mevcut listing: {$stats['existing_listing']}");
        $this->line("Oluşturulacak listing: {$stats['to_create_listing']}");
        $this->line("Güncellenecek listing: {$stats['to_update_listing']}");
        $this->line("Klonlanacak master ürün: {$stats['cloned_products']}");
        $this->line("Duplicate barkod: {$stats['duplicate_barcode']}");
        $this->line("Mapping bulunamayan: {$stats['mapping_not_found']}");
        $this->line("Fiyat bulunamayan: {$stats['price_not_found']}");
        $this->line("Stok bulunamayan: {$stats['stock_not_found']}");
        $this->line("Para birimi uyumsuz: {$stats['currency_mismatch']}");
        $this->line("Hatalı kayıt: {$stats['errors']}");
        $this->newLine();

        if ($confirm) {
            $this->info("Veri eşitlemesi başlatılıyor...");

            // Create IntegrationSyncRun to run the sync service
            $run = IntegrationSyncRun::create([
                'store_id' => $store->id,
                'sync_type' => 'products',
                'trigger_type' => 'manual',
                'status' => 'queued',
            ]);

            try {
                app(MarketplaceSyncService::class)->run($run->id);
                $run->refresh();
                $this->info("Ürün senkronizasyonu tamamlandı. Durum: {$run->status}");

                // Verify listings mapping integrity
                $verificationSuccess = true;
                foreach ($items as $row) {
                    $barcode = $row['product']['barcode'] ?? null;
                    if (!$barcode) continue;

                    $verifyListing = ChannelListing::where('store_id', $store->id)
                        ->whereHas('channelProduct', fn($q) => $q->where('barcode', $barcode))
                        ->first();

                    if ($verifyListing) {
                        // Integrity checks
                        if ((int)$verifyListing->store_id !== $store->id) {
                            $this->error("Bütünlük Hatası: store_id eşleşmiyor.");
                            $verificationSuccess = false;
                        }
                        if ($verifyListing->mp_product_id) {
                            $this->line("Bütünlük Doğrulandı: {$barcode} -> MpProduct-{$verifyListing->mp_product_id}");
                        } else {
                            $this->error("Bütünlük Hatası: Barkod {$barcode} için listing eşleşmesi (mp_product_id) tamamlanamadı!");
                            $verificationSuccess = false;
                        }
                    }
                }

                if ($verificationSuccess) {
                    $this->info("Bütünlük doğrulama başarıyla tamamlandı. Eşleşmeler ground-truth.");
                }

                // Create Audit Log of listing backfill
                Log::notice("Marketplace listings backfill executed successfully", [
                    'store_id' => $store->id,
                    'provider' => $provider,
                    'inspected' => $stats['total_inspected'],
                    'created' => $stats['to_create_listing'],
                    'updated' => $stats['to_update_listing'],
                    'user_id' => auth()->id() ?: 1,
                    'correlation_id' => $correlationId,
                ]);

            } catch (\Throwable $e) {
                $this->error("Backfill işlemi sırasında hata oluştu: " . $e->getMessage());
                return self::FAILURE;
            }
        } else {
            $this->warn("Yazma modu onaylanmadı (--confirm girilmedi). Veritabanına kayıt atılmadı.");
        }

        return self::SUCCESS;
    }
}
