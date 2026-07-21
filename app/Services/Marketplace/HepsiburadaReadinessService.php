<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceStore;
use App\Models\HepsiburadaReadinessAudit;
use App\Models\User;
use App\Services\Marketplace\Connectors\HepsiburadaConnector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class HepsiburadaReadinessService
{
    public const GUARDED_TABLES = [
        'channel_products',
        'channel_listings',
        'mp_categories',
        'mp_category_attributes',
        'mp_category_attribute_values',
        'mp_orders',
        'mp_transactions',
    ];

    public function __construct(
        protected HepsiburadaConnector $connector,
        protected HepsiburadaReadinessOutputSanitizer $sanitizer
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

        // Service-level Actor Validation
        $actorId = $options['actor_id'] ?? auth()->id();
        if (!$actorId) {
            return [
                'correlation_id'   => $correlationId,
                'is_ready'         => false,
                'is_live_verified' => false,
                'http_attempted'   => false,
                'decision'         => 'audit_actor_missing',
                'message'          => 'İşlemi gerçekleştiren aktör kullanıcısı belirtilmedi (audit_actor_missing).',
            ];
        }

        $actor = User::find($actorId);
        if (!$actor || !$actor->is_active) {
            return [
                'correlation_id'   => $correlationId,
                'is_ready'         => false,
                'is_live_verified' => false,
                'http_attempted'   => false,
                'decision'         => 'authorization_failed',
                'message'          => 'Aktör kullanıcısı bulunamadı veya pasif durumda (authorization_failed).',
            ];
        }

        // Service-level Store Authorization Validation
        try {
            app(MarketplaceStoreAccessResolver::class)
                ->resolveForCredentialManagement($actor, $store->id);
        } catch (\Throwable $e) {
            return [
                'correlation_id'   => $correlationId,
                'is_ready'         => false,
                'is_live_verified' => false,
                'http_attempted'   => false,
                'decision'         => 'authorization_failed',
                'message'          => 'Aktörün bu mağaza üzerinde denetim yapma yetkisi bulunmuyor (authorization_failed).',
            ];
        }

        // Service-level Reason Validation
        $reason = trim((string) ($options['reason'] ?? ''));
        $reason = preg_replace('/[\x00-\x1F\x7F]/u', '', $reason);
        if ($reason === '') {
            return [
                'correlation_id'   => $correlationId,
                'is_ready'         => false,
                'is_live_verified' => false,
                'http_attempted'   => false,
                'decision'         => 'audit_reason_missing',
                'message'          => 'İşlem gerekçesi (reason) boş olamaz.',
            ];
        }

        if (mb_strlen($reason) > 255) {
            return [
                'correlation_id'   => $correlationId,
                'is_ready'         => false,
                'is_live_verified' => false,
                'http_attempted'   => false,
                'decision'         => 'audit_reason_invalid',
                'message'          => 'İşlem gerekçesi 255 karakterden uzun olamaz.',
            ];
        }

        if (preg_match('/(api_key|service_key|bearer\s|secret|password)/i', $reason)) {
            return [
                'correlation_id'   => $correlationId,
                'is_ready'         => false,
                'is_live_verified' => false,
                'http_attempted'   => false,
                'decision'         => 'audit_reason_invalid',
                'message'          => 'İşlem gerekçesi hassas veri / credential kelimeleri içeremez.',
            ];
        }

        // 0. Mutation Guard Table Existence Check
        foreach (self::GUARDED_TABLES as $guardedTable) {
            if (!Schema::hasTable($guardedTable)) {
                $this->audit($correlationId, $store, $actor->id, $reason, $operation, $confirmRead, false, false, null, 'TABLE_MISSING', 0, 0, 'mutation_guard_table_missing');
                return [
                    'correlation_id'   => $correlationId,
                    'is_ready'         => false,
                    'is_live_verified' => false,
                    'http_attempted'   => false,
                    'decision'         => 'mutation_guard_table_missing',
                    'message'          => "Kritik koruma tablosu veritabanında bulunamadı: {$guardedTable}",
                ];
            }
        }

        $connection = $store->connection;
        if (!$connection) {
            $this->audit($correlationId, $store, $actor->id, $reason, $operation, $confirmRead, false, false, null, null, 0, 0, 'not_configured');
            return [
                'correlation_id'   => $correlationId,
                'is_ready'         => false,
                'is_live_verified' => false,
                'http_attempted'   => false,
                'decision'         => 'not_configured',
                'message'          => 'Hepsiburada bağlantı kaydı bulunmuyor.',
            ];
        }

        // 1. Decryption Check
        try {
            $credentials = $connection->credentials_encrypted ?? [];
        } catch (\Throwable $e) {
            $this->audit($correlationId, $store, $actor->id, $reason, $operation, $confirmRead, false, false, null, 'DECRYPT_FAIL', 0, 0, 'credential_decryption_failed');
            return [
                'correlation_id'   => $correlationId,
                'is_ready'         => false,
                'is_live_verified' => false,
                'http_attempted'   => false,
                'decision'         => 'credential_decryption_failed',
                'message'          => 'Bağlantı şifreli verileri çözülemedi.',
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
            $this->audit($correlationId, $store, $actor->id, $reason, $operation, $confirmRead, false, false, null, null, 0, 0, 'not_configured');
            return [
                'correlation_id'   => $correlationId,
                'is_ready'         => false,
                'is_live_verified' => false,
                'http_attempted'   => false,
                'decision'         => 'not_configured',
                'message'          => 'Merchant ID / Service Key veya Legacy kullanıcı/şifre alanları eksik.',
            ];
        }

        // 3. Service Key Placeholder Check (Merchant ID is evaluated separately)
        if ($this->containsPlaceholderCredential([$serviceKey, $legacyUser, $legacyPass])) {
            $this->audit($correlationId, $store, $actor->id, $reason, $operation, $confirmRead, false, false, null, 'PLACEHOLDER', 0, 0, 'credential_placeholder');
            return [
                'correlation_id'   => $correlationId,
                'is_ready'         => false,
                'is_live_verified' => false,
                'http_attempted'   => false,
                'decision'         => 'credential_placeholder',
                'message'          => 'Giriş yapılan kimlik bilgileri test veya placeholder değerler içeriyor.',
            ];
        }

        // 4. Merchant ID Consistency Check
        $credMerchantId = trim((string) data_get($credentials, 'merchant_id', ''));
        if ($store->seller_id !== '' && $credMerchantId !== '' && $store->seller_id !== $credMerchantId) {
            $this->audit($correlationId, $store, $actor->id, $reason, $operation, $confirmRead, false, false, null, 'MISMATCH', 0, 0, 'merchant_id_mismatch');
            return [
                'correlation_id'   => $correlationId,
                'is_ready'         => false,
                'is_live_verified' => false,
                'http_attempted'   => false,
                'decision'         => 'merchant_id_mismatch',
                'message'          => 'Mağaza satıcı kimliği ile bağlantı Merchant ID bilgisi uyuşmuyor.',
            ];
        }

        // 5. Rollout Gate Status
        $gateEnabled = false;
        if ($operation === 'connection') {
            $gateEnabled = (bool) config('marketplace.hepsiburada.p0_connection_probe_enabled', false);
        } elseif (in_array($operation, ['categories', 'attributes'], true)) {
            $gateEnabled = (bool) config('marketplace.hepsiburada.p0_reference_sync_enabled', false);
        } elseif ($operation === 'catalog') {
            $gateEnabled = (bool) config('marketplace.hepsiburada.p0_catalog_sync_enabled', false);
        } elseif ($operation === 'batch') {
            $gateEnabled = (bool) config('marketplace.hepsiburada.p0_batch_status_sync_enabled', false);
        }

        // If confirm_read is not passed: Return configured_not_verified (No HTTP request!)
        if (!$confirmRead) {
            $decision = 'configured_not_verified';
            $msg = 'Bağlantı parametreleri ve kimlik bilgileri geçerli. Canlı HTTP okuma doğrulaması için --confirm-read onaylanmalıdır.';

            $auditSaved = $this->audit($correlationId, $store, $actor->id, $reason, $operation, false, $gateEnabled, false, null, null, 0, 0, $decision);
            if (!$auditSaved) {
                return [
                    'correlation_id'   => $correlationId,
                    'is_ready'         => false,
                    'is_live_verified' => false,
                    'http_attempted'   => false,
                    'decision'         => 'audit_persistence_failed',
                    'message'          => 'Denetim kaydı veritabanına işlenirken bir hata oluştu (audit_persistence_failed).',
                ];
            }

            return [
                'correlation_id'   => $correlationId,
                'is_ready'         => true,
                'is_live_verified' => false,
                'http_attempted'   => false,
                'decision'         => $decision,
                'message'          => $msg,
            ];
        }

        // If confirm_read is passed BUT rollout gate is false: Return rollout_disabled (No HTTP request!)
        if (!$gateEnabled) {
            $auditSaved = $this->audit($correlationId, $store, $actor->id, $reason, $operation, true, false, false, null, null, 0, 0, 'rollout_disabled');
            if (!$auditSaved) {
                return [
                    'correlation_id'   => $correlationId,
                    'is_ready'         => false,
                    'is_live_verified' => false,
                    'http_attempted'   => false,
                    'decision'         => 'audit_persistence_failed',
                    'message'          => 'Denetim kaydı veritabanına işlenirken bir hata oluştu (audit_persistence_failed).',
                ];
            }

            return [
                'correlation_id'   => $correlationId,
                'is_ready'         => false,
                'is_live_verified' => false,
                'http_attempted'   => false,
                'decision'         => 'rollout_disabled',
                'message'          => "Hepsiburada rollout kapısı kapalı. Bu işlem için ilgili rollout flag'ini aktif edin.",
            ];
        }

        // 6. Perform Live Bounded HTTP Probe in Transaction + Mutation Guard Listener
        $startTime = microtime(true);
        $httpStatus = null;
        $errorCode = null;
        $itemCount = 0;
        $decision = 'read_probe_failed';
        $message = 'API Probe başarısız oldu.';
        $details = [];

        $attemptedMutations = 0;
        $guardedTables = self::GUARDED_TABLES;

        DB::listen(function ($query) use (&$attemptedMutations, $guardedTables) {
            $sql = strtoupper($query->sql);
            if (preg_match('/\b(INSERT|UPDATE|DELETE|REPLACE|TRUNCATE)\b/', $sql)) {
                foreach ($guardedTables as $tbl) {
                    if (str_contains(strtolower($query->sql), $tbl)) {
                        $attemptedMutations++;
                    }
                }
            }
        });

        DB::beginTransaction();
        try {
            if ($operation === 'connection') {
                $this->connector->testConnection($store);
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
                $maxShow = min((int) ($options['max_items'] ?? 5), 5);
                $sliced = array_slice($categories, 0, $maxShow);
                $details = $this->sanitizer->sanitizeCategoryItems($sliced);
            } elseif ($operation === 'attributes') {
                $catId = (string) ($options['category_id'] ?? '');
                $attributes = $this->connector->getCategoryAttributes($store, $catId);
                $httpStatus = 200;
                $decision = 'read_probe_success';
                $rawAttrs = $attributes['attributes'] ?? [];
                $itemCount = count($rawAttrs);
                $message = 'Hepsiburada kategori nitelik probe başarılı.';
                $maxShow = min((int) ($options['max_items'] ?? 10), 10);
                $sliced = array_slice($rawAttrs, 0, $maxShow);
                $details = $this->sanitizer->sanitizeAttributeItems($sliced);
            } elseif ($operation === 'catalog') {
                $maxItems = min((int) ($options['max_items'] ?? 5), 5);
                $catalog = $this->connector->pullCatalogProducts($store, [
                    'smoke_mode' => true,
                    'max_items'  => $maxItems,
                    'max_pages'  => 1,
                ]);
                $httpStatus = 200;
                $decision = 'read_probe_success';
                $rawItems = $catalog['items'] ?? [];
                $itemCount = count($rawItems);
                $message = 'Hepsiburada katalog probe başarılı.';
                $sliced = array_slice($rawItems, 0, $maxItems);
                $details = $this->sanitizer->sanitizeCatalogItems($sliced);
            } elseif ($operation === 'batch') {
                $batchId = (string) ($options['batch_id'] ?? '');
                $opType = (string) ($options['batch_operation'] ?? 'price-uploads');
                $batch = $this->connector->pullBatchStatus($store, $batchId, $opType);
                $httpStatus = 200;
                $decision = 'read_probe_success';
                $itemCount = count($batch['items'] ?? []);
                $message = 'Hepsiburada batch status probe başarılı.';
                $details = $this->sanitizer->sanitizeBatchResult($batch, $batchId);
            }
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $httpStatus = $e->response?->status() ?? 500;
            if ($httpStatus === 401) {
                $decision = 'authentication_failed';
                $errorCode = '401_UNAUTHORIZED';
                $message = 'Kimlik doğrulama hatası (401 Unauthorized). Hepsiburada servis anahtarı geçersiz.';
            } elseif ($httpStatus === 403) {
                $decision = 'permission_blocked';
                $errorCode = '403_FORBIDDEN';
                $message = 'Erişim engellendi (403 Forbidden). Entegratör yetkisi yetersiz.';
            } elseif ($httpStatus === 404) {
                $decision = 'resource_not_found';
                $errorCode = '404_NOT_FOUND';
                $message = 'İstenen kaynak bulunamadı (404 Not Found).';
            } elseif ($httpStatus === 429) {
                $decision = 'rate_limited';
                $errorCode = '429_TOO_MANY_REQUESTS';
                $message = 'İstek limiti aşıldı (429 Too Many Requests).';
            } elseif ($httpStatus >= 500) {
                $decision = 'provider_unavailable';
                $errorCode = '5XX_SERVER_ERROR';
                $message = 'Hepsiburada sunucularına erişilemiyor (5xx Gateway Error).';
            } else {
                $decision = 'read_probe_failed';
                $errorCode = 'READ_PROBE_ERROR';
                $message = 'HTTP isteği başarısız oldu.';
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $decision = 'network_timeout';
            $errorCode = 'CONNECT_TIMEOUT';
            $message = 'Hepsiburada servisi bağlantı zaman aşımına uğradı.';
        } catch (\JsonException $e) {
            $decision = 'invalid_provider_response';
            $errorCode = 'INVALID_JSON';
            $message = 'Hepsiburada servis yanıtı geçersiz biçimde.';
        } catch (\Throwable $e) {
            $decision = 'read_probe_failed';
            $errorCode = 'READ_PROBE_ERROR';
            $message = 'Probe kontrolü sırasında bir hata oluştu.';
        } finally {
            DB::rollBack();
        }

        $durationMs = (int) (round(microtime(true) - $startTime, 4) * 1000);

        // Evaluate DB Mutation Violations
        if ($attemptedMutations > 0) {
            $decision = 'read_probe_mutated_database';
            $message = "Veritabanı değişikliği (mutation) saptandı! Probe sırasında DB yazma/güncelleme yapılamaz.";
            $errorCode = 'DB_MUTATION_VIOLATION';
        }

        $isLiveVerified = ($decision === 'authentication_success' || $decision === 'read_probe_success');

        // Persist Audit AFTER Rollback (Fail-Closed if audit fails)
        $auditSaved = $this->audit(
            $correlationId,
            $store,
            $actor->id,
            $reason,
            $operation,
            true,
            $gateEnabled,
            true,
            $httpStatus,
            $errorCode,
            $itemCount,
            $attemptedMutations,
            $decision,
            $durationMs
        );

        if (!$auditSaved) {
            return [
                'correlation_id'   => $correlationId,
                'is_ready'         => false,
                'is_live_verified' => false,
                'http_attempted'   => true,
                'decision'         => 'audit_persistence_failed',
                'message'          => 'Denetim kaydı veritabanına işlenirken bir hata oluştu (audit_persistence_failed).',
                'duration_ms'      => $durationMs,
                'item_count'       => $itemCount,
                'details'          => [],
            ];
        }

        return [
            'correlation_id'   => $correlationId,
            'is_ready'         => $isLiveVerified,
            'is_live_verified' => $isLiveVerified,
            'http_attempted'   => true,
            'decision'         => $decision,
            'message'          => $message,
            'duration_ms'      => $durationMs,
            'item_count'       => $itemCount,
            'details'          => $details,
        ];
    }

    /**
     * Check if any credential string matches exact placeholder denylist or patterns.
     *
     * @param  array<int, string>  $values
     * @return bool
     */
    protected function containsPlaceholderCredential(array $values): bool
    {
        $exactDenylist = [
            'test',
            'test-key',
            'test_key',
            'test-secret',
            'test_secret',
            'service-key',
            'service_key',
            'your-service-key',
            'your_service_key',
            'placeholder',
            'changeme',
            'example',
            'demo',
            '123456',
        ];

        foreach ($values as $val) {
            $clean = strtolower(trim($val));
            if ($clean === '') {
                continue;
            }

            if (in_array($clean, $exactDenylist, true)) {
                return true;
            }

            if (preg_match('/^(test[-_]?key|test[-_]?secret|service[-_]?key|your[-_]service[-_]key|placeholder|changeme|example|demo|123456)$/i', $clean)) {
                return true;
            }
        }

        return false;
    }

    protected function audit(
        string $correlationId,
        MarketplaceStore $store,
        int $actingUserId,
        string $reason,
        string $operation,
        bool $confirmRead,
        bool $rolloutGate,
        bool $httpAttempted,
        ?int $httpStatus,
        ?string $errorCode,
        int $itemCount,
        int $dbMutationCount,
        string $decision,
        ?int $durationMs = null
    ): bool {
        try {
            HepsiburadaReadinessAudit::create([
                'correlation_id'      => $correlationId,
                'store_id'            => $store->id,
                'connection_id'       => $store->connection?->id,
                'acting_user_id'      => $actingUserId,
                'reason'              => HepsiburadaReadinessOutputSanitizer::shortenString($reason, 255),
                'tenant_user_id'      => $store->user_id,
                'release_sha'         => $this->getReleaseSha(),
                'runtime_id'          => getmypid() ? (string) getmypid() : null,
                'operation'           => $operation,
                'confirm_read'        => $confirmRead,
                'rollout_gate'        => $rolloutGate,
                'http_attempted'      => $httpAttempted,
                'http_status'         => $httpStatus,
                'provider_error_code' => $errorCode ? HepsiburadaReadinessOutputSanitizer::sanitizeErrorCode($errorCode) : null,
                'duration_ms'         => $durationMs,
                'item_count'          => $itemCount,
                'db_mutation_count'   => $dbMutationCount,
                'decision'            => $decision,
            ]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
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
