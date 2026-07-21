<?php
 
namespace App\Services\Marketplace;
 
use App\Models\MarketplaceStore;
use App\Models\HepsiburadaReadinessAudit;
use App\Services\Marketplace\Connectors\HepsiburadaConnector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class HepsiburadaReadinessService
{
    public function __construct(
        protected HepsiburadaConnector $connector
    ) {}

    /**
     * Inspect Hepsiburada store credentials and rollout configuration.
     *
     * @param  MarketplaceStore  $store
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function inspect(MarketplaceStore $store, array $options = []): array
    {
        $correlationId = (string) Str::uuid();
        $confirmRead = (bool) ($options['confirm_read'] ?? false);
        $operation = (string) ($options['operation'] ?? 'connection');

        $connection = $store->connection;
        if (!$connection) {
            $this->audit($correlationId, $store, $operation, $confirmRead, false, false, null, null, 0, 'not_configured');
            return [
                'correlation_id' => $correlationId,
                'is_ready'       => false,
                'decision'       => 'not_configured',
                'message'        => 'Hepsiburada bağlantı kaydı bulunmuyor.',
            ];
        }

        // 1. Decryption Check
        try {
            $credentials = $connection->credentials_encrypted ?? [];
        } catch (\Throwable $e) {
            $this->audit($correlationId, $store, $operation, $confirmRead, false, false, null, null, 0, 'credential_decryption_failed');
            return [
                'correlation_id' => $correlationId,
                'is_ready'       => false,
                'decision'       => 'credential_decryption_failed',
                'message'        => 'Bağlantı şifreli verileri çözülemedi: ' . $e->getMessage(),
            ];
        }

        // 2. Presence Check
        $merchantId = trim((string) ($store->seller_id ?: data_get($credentials, 'merchant_id') ?: ''));
        $serviceKey = trim((string) (data_get($credentials, 'api_key') ?: ''));
        $legacyUser = trim((string) (data_get($credentials, 'extra_user') ?: ''));
        $legacyPass = trim((string) (data_get($credentials, 'extra_password') ?: data_get($credentials, 'api_secret') ?: ''));

        $hasNewAuth = ($merchantId !== '' && $serviceKey !== '');
        $hasLegacyAuth = ($legacyUser !== '' && $legacyPass !== '');

        if (!$hasNewAuth && !$hasLegacyAuth) {
            $this->audit($correlationId, $store, $operation, $confirmRead, false, false, null, null, 0, 'not_configured');
            return [
                'correlation_id' => $correlationId,
                'is_ready'       => false,
                'decision'       => 'not_configured',
                'message'        => 'Merchant ID / Service Key veya Legacy kullanıcı/şifre alanları eksik.',
            ];
        }

        // 3. Placeholder Check
        $placeholders = ['example', 'placeholder', '123456', 'service-key', 'zem_dev', 'test', 'owner-key', 'owner-secret'];
        $containsPlaceholder = false;
        foreach ([$merchantId, $serviceKey, $legacyUser, $legacyPass] as $val) {
            if ($val !== '') {
                foreach ($placeholders as $p) {
                    if (str_contains(strtolower($val), $p)) {
                        $containsPlaceholder = true;
                        break 2;
                    }
                }
            }
        }

        if ($containsPlaceholder) {
            $this->audit($correlationId, $store, $operation, $confirmRead, false, false, null, null, 0, 'credential_placeholder');
            return [
                'correlation_id' => $correlationId,
                'is_ready'       => false,
                'decision'       => 'credential_placeholder',
                'message'        => 'Giriş yapılan kimlik bilgileri test veya placeholder değerler içeriyor.',
            ];
        }

        // 4. Merchant ID Consistency
        $credMerchantId = trim((string) data_get($credentials, 'merchant_id', ''));
        if ($store->seller_id !== '' && $credMerchantId !== '' && $store->seller_id !== $credMerchantId) {
            $this->audit($correlationId, $store, $operation, $confirmRead, false, false, null, null, 0, 'merchant_id_mismatch');
            return [
                'correlation_id' => $correlationId,
                'is_ready'       => false,
                'decision'       => 'merchant_id_mismatch',
                'message'        => 'Mağaza satıcı kimliği ile bağlantı Merchant ID bilgisi uyuşmuyor.',
            ];
        }

        // 5. Rollout Gate Status
        $gateEnabled = false;
        if ($operation === 'connection') {
            $gateEnabled = true; // testConnection always permitted for diagnostic reasons
        } elseif (in_array($operation, ['categories', 'attributes'], true)) {
            $gateEnabled = (bool) config('marketplace.hepsiburada.p0_reference_sync_enabled', false);
        } elseif ($operation === 'catalog') {
            $gateEnabled = (bool) config('marketplace.hepsiburada.p0_catalog_sync_enabled', false);
        } elseif ($operation === 'batch') {
            $gateEnabled = (bool) config('marketplace.hepsiburada.p0_batch_status_sync_enabled', false);
        }

        if (!$gateEnabled) {
            $this->audit($correlationId, $store, $operation, $confirmRead, false, false, null, null, 0, 'rollout_disabled');
            return [
                'correlation_id' => $correlationId,
                'is_ready'       => false,
                'decision'       => 'rollout_disabled',
                'message'        => "Hepsiburada rollout kapısı kapalı. Bu işlem için ilgili rollout flag'ini aktif edin.",
            ];
        }

        // If confirm_read is not checked, return success mock check
        if (!$confirmRead) {
            $this->audit($correlationId, $store, $operation, false, $gateEnabled, false, null, null, 0, 'authentication_success');
            return [
                'correlation_id' => $correlationId,
                'is_ready'       => true,
                'decision'       => 'authentication_success',
                'message'        => 'Bağlantı parametreleri ve rollout kapıları canlı okuma doğrulaması için hazır.',
            ];
        }

        // 6. Live HTTP Probe
        $startTime = microtime(true);
        $httpStatus = null;
        $errorCode = null;
        $itemCount = 0;
        $decision = 'read_probe_failed';
        $message = 'API Probe başarısız oldu.';
        $details = [];

        try {
            if ($operation === 'connection') {
                $probeResult = $this->connector->testConnection($store);
                $httpStatus = 200;
                $decision = 'authentication_success';
                $message = 'Hepsiburada canlı bağlantı doğrulaması başarılı.';
                $itemCount = 1;
            } elseif ($operation === 'categories') {
                $categories = $this->connector->getCategories($store);
                $httpStatus = 200;
                $decision = 'read_probe_success';
                $itemCount = count($categories);
                $message = 'Hepsiburada kategori ağacı probe başarılı.';
                $details = array_slice($categories, 0, (int) ($options['max_items'] ?? 5));
            } elseif ($operation === 'attributes') {
                $catId = (string) ($options['category_id'] ?? '');
                $attributes = $this->connector->getCategoryAttributes($store, $catId);
                $httpStatus = 200;
                $decision = 'read_probe_success';
                $itemCount = count($attributes['attributes'] ?? []);
                $message = 'Hepsiburada kategori nitelik probe başarılı.';
                $details = array_slice($attributes['attributes'] ?? [], 0, (int) ($options['max_items'] ?? 10));
            } elseif ($operation === 'catalog') {
                $catalog = $this->connector->pullCatalogProducts($store);
                $httpStatus = 200;
                $decision = 'read_probe_success';
                $itemCount = count($catalog['items'] ?? []);
                $message = 'Hepsiburada katalog probe başarılı.';
                $details = array_slice($catalog['items'] ?? [], 0, (int) ($options['max_items'] ?? 5));
            } elseif ($operation === 'batch') {
                $batchId = (string) ($options['batch_id'] ?? '');
                $opType = (string) ($options['batch_operation'] ?? 'price-uploads');
                $batch = $this->connector->pullBatchStatus($store, $batchId, $opType);
                $httpStatus = 200;
                $decision = 'read_probe_success';
                $itemCount = count($batch['items'] ?? []);
                $message = 'Hepsiburada batch status probe başarılı.';
                $details = $batch;
            }
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $httpStatus = $e->response->status();
            $errorCode = (string) $e->response->json('message');
            if ($httpStatus === 401) {
                $decision = 'authentication_failed';
                $message = 'Kimlik doğrulama hatası (401 Unauthorized). Hepsiburada service_key hatalı.';
            } elseif ($httpStatus === 403) {
                $decision = 'permission_blocked';
                $message = 'Erişim engellendi (403 Forbidden). Entegratör yetkisi eksik.';
            } elseif ($httpStatus === 429) {
                $decision = 'rate_limited';
                $message = 'İstek limiti aşıldı (429 Too Many Requests).';
            } elseif ($httpStatus >= 500) {
                $decision = 'provider_unavailable';
                $message = 'Hepsiburada sunucularına erişilemiyor (5xx Gateway Error).';
            } else {
                $decision = 'read_probe_failed';
                $message = 'HTTP isteği başarısız: ' . $e->getMessage();
            }
        } catch (\Throwable $e) {
            $errorCode = $e->getMessage();
            $decision = 'read_probe_failed';
            $message = 'Probe sırasında hata oluştu: ' . $e->getMessage();
        }

        $durationMs = (int) (round(microtime(true) - $startTime, 4) * 1000);

        $this->audit(
            $correlationId,
            $store,
            $operation,
            true,
            $gateEnabled,
            true,
            $httpStatus,
            $errorCode,
            $itemCount,
            $decision,
            $durationMs
        );

        return [
            'correlation_id' => $correlationId,
            'is_ready'       => ($decision === 'authentication_success' || $decision === 'read_probe_success'),
            'decision'       => $decision,
            'message'        => $message,
            'duration_ms'    => $durationMs,
            'item_count'     => $itemCount,
            'details'        => $details,
        ];
    }

    protected function audit(
        string $correlationId,
        MarketplaceStore $store,
        string $operation,
        bool $confirmRead,
        bool $rolloutGate,
        bool $httpAttempted,
        ?int $httpStatus,
        ?string $errorCode,
        int $itemCount,
        string $decision,
        ?int $durationMs = null
    ): void {
        HepsiburadaReadinessAudit::create([
            'correlation_id'      => $correlationId,
            'store_id'            => $store->id,
            'connection_id'       => $store->connection?->id,
            'acting_user_id'      => auth()->id(),
            'tenant_user_id'      => $store->user_id,
            'release_sha'         => $this->getReleaseSha(),
            'runtime_id'          => getmypid() ?: null,
            'operation'           => $operation,
            'confirm_read'        => $confirmRead,
            'rollout_gate'        => $rolloutGate,
            'http_attempted'      => $httpAttempted,
            'http_status'         => $httpStatus,
            'provider_error_code' => $errorCode ? substr($errorCode, 0, 100) : null,
            'duration_ms'         => $durationMs,
            'item_count'          => $itemCount,
            'db_mutation_count'   => 0,
            'decision'            => $decision,
        ]);
    }

    protected function getReleaseSha(): ?string
    {
        try {
            $sha = trim((string) shell_exec('git rev-parse HEAD'));
            return $sha !== '' ? substr($sha, 0, 40) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
