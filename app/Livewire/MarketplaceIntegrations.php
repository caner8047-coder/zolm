<?php

namespace App\Livewire;

use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncProfile;
use App\Models\IntegrationSyncRun;
use App\Models\IntegrationWebhookEvent;
use App\Models\LegalEntity;
use App\Models\LegalEntitySetting;
use App\Models\MarketplaceStore;
use App\Services\MpSettingsService;
use App\Services\Marketplace\Contracts\TestsConnection;
use App\Services\Marketplace\LegacyFinancialProjectionService;
use App\Services\Marketplace\LegacyFinancialProjectionInsightsService;
use App\Services\Marketplace\MarketplaceConnectionReadinessService;
use App\Services\Marketplace\MarketplaceConnectorManager;
use App\Services\Marketplace\MarketplaceDiagnosticsGuidanceService;
use App\Services\Marketplace\MarketplaceManualSyncDispatchService;
use App\Services\Marketplace\MarketplaceProviderRegistry;
use App\Services\Marketplace\MarketplaceRiskSignalService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class MarketplaceIntegrations extends Component
{
    public ?int $selectedStoreId = null;
    public ?string $saveResult = null;

    public array $entityForm = [];

    public array $storeForm = [];

    public array $connectionForm = [];

    public array $syncForm = [];

    public ?string $flashMessage = null;

    public string $flashMessageType = 'success';

    /**
     * @var array<string, mixed>
     */
    public array $legacyProjectionPreview = [];

    public function mount(): void
    {
        $this->resetEntityForm();
        $this->resetStoreForm();
        $this->resetConnectionForm();
        $this->resetSyncForm();

        $requestedStoreId = (int) request()->integer('store');
        $resolver = app(\App\Services\Marketplace\MarketplaceStoreAccessResolver::class);
        $accessible = $resolver->accessibleStores(Auth::user());

        if ($requestedStoreId > 0 && (clone $accessible)->whereKey($requestedStoreId)->exists()) {
            $this->selectStore($requestedStoreId);

            return;
        }

        $firstStoreId = (clone $accessible)
            ->latest('id')
            ->value('id');

        if ($firstStoreId) {
            $this->selectStore((int) $firstStoreId);
        }
    }

    public function saveLegalEntity(): void
    {
        $validated = $this->validate([
            'entityForm.name' => ['required', 'string', 'max:150'],
            'entityForm.taxNumber' => ['required', 'string', 'max:32'],
            'entityForm.taxOffice' => ['nullable', 'string', 'max:120'],
            'entityForm.mersisNumber' => ['nullable', 'string', 'max:32'],
            'entityForm.companyType' => ['required', 'string', 'max:50'],
            'entityForm.phone' => ['nullable', 'string', 'max:32'],
            'entityForm.email' => ['nullable', 'email', 'max:150'],
            'entityForm.address' => ['nullable', 'string'],
            'entityForm.iban' => ['nullable', 'string', 'max:64'],
            'entityForm.bankName' => ['nullable', 'string', 'max:120'],
            'entityForm.currency' => ['required', 'string', 'size:3'],
            'entityForm.isActive' => ['boolean'],
        ]);

        $user = Auth::user();

        DB::transaction(function () use ($validated, $user): void {
            $entity = LegalEntity::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'tax_number' => $validated['entityForm']['taxNumber'],
                ],
                [
                    'name' => $validated['entityForm']['name'],
                    'tax_office' => $validated['entityForm']['taxOffice'] ?: null,
                    'mersis_number' => $validated['entityForm']['mersisNumber'] ?: null,
                    'company_type' => $validated['entityForm']['companyType'],
                    'phone' => $validated['entityForm']['phone'] ?: null,
                    'email' => $validated['entityForm']['email'] ?: null,
                    'address' => $validated['entityForm']['address'] ?: null,
                    'iban' => $validated['entityForm']['iban'] ?: null,
                    'bank_name' => $validated['entityForm']['bankName'] ?: null,
                    'currency' => Str::upper($validated['entityForm']['currency']),
                    'is_active' => (bool) $validated['entityForm']['isActive'],
                ],
            );

            LegalEntitySetting::firstOrCreate(
                ['legal_entity_id' => $entity->id],
                ['settings_json' => []],
            );

            if (blank($this->storeForm['legalEntityId'] ?? null)) {
                $this->storeForm['legalEntityId'] = $entity->id;
            }
        });

        $this->notify('Firma kaydedildi. Artık bu firmaya mağaza bağlayabilirsiniz.');
        $this->resetEntityForm();
    }

    public function saveStore(): void
    {
        $validated = $this->validate([
            'storeForm.legalEntityId' => [
                'required',
                Rule::exists('legal_entities', 'id')->where(fn ($query) => $query->where('user_id', Auth::id())),
            ],
            'storeForm.marketplace' => ['required', Rule::in(array_keys(MarketplaceProviderRegistry::providers()))],
            'storeForm.storeName' => ['required', 'string', 'max:150'],
            'storeForm.storeCode' => ['nullable', 'string', 'max:100'],
            'storeForm.sellerId' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('marketplace_stores', 'seller_id')
                    ->ignore($this->selectedStoreId)
                    ->where(fn ($query) => $query->where('marketplace', $this->storeForm['marketplace'])),
            ],
            'storeForm.timezone' => ['required', 'string', 'max:64'],
            'storeForm.currency' => ['required', 'string', 'size:3'],
            'storeForm.isActive' => ['boolean'],
        ]);

        $user = Auth::user();
        $isUpdate = false;

        DB::transaction(function () use ($validated, $user, &$isUpdate): void {
            $store = $this->selectedStore;

            if ($store) {
                $isUpdate = true;
            } else {
                $store = new MarketplaceStore();
                $store->user_id = $user->id;
            }

            $store->fill([
                'legal_entity_id' => $validated['storeForm']['legalEntityId'],
                'marketplace' => $validated['storeForm']['marketplace'],
                'store_name' => $validated['storeForm']['storeName'],
                'store_code' => $validated['storeForm']['storeCode'] ?: null,
                'seller_id' => $validated['storeForm']['sellerId'] ?: null,
                'timezone' => $validated['storeForm']['timezone'],
                'currency' => Str::upper($validated['storeForm']['currency']),
                'is_active' => (bool) $validated['storeForm']['isActive'],
                'status' => $store->status ?: 'draft',
            ]);
            $store->save();

            $store->connection()->updateOrCreate(
                ['store_id' => $store->id],
                [
                    'provider' => $store->marketplace,
                    'auth_type' => $store->connection?->auth_type ?? 'api_key_secret',
                    'api_base_url' => $store->connection?->api_base_url ?: MarketplaceProviderRegistry::defaultApiBaseUrl($store->marketplace),
                    'webhook_url' => $this->buildWebhookUrl($store),
                    'webhook_secret' => $store->connection?->webhook_secret ?: Str::random(40),
                    'status' => $store->connection?->status ?? 'draft',
                ],
            );

            $store->syncProfile()->firstOrCreate(
                ['store_id' => $store->id],
                IntegrationSyncProfile::defaultsForMarketplace($store->marketplace),
            );

            $this->selectedStoreId = $store->id;
        });

        $this->loadSelectedStore();
        $this->notify($isUpdate ? 'Mağaza bilgileri güncellendi.' : 'Mağaza oluşturuldu ve varsayılan entegrasyon profili hazırlandı.');
    }

    public function startNewStore(): void
    {
        $this->selectedStoreId = null;
        $this->resetStoreForm();
        $this->resetConnectionForm();
        $this->resetSyncForm();
    }

    public function selectStore(int $storeId): void
    {
        try {
            app(\App\Services\Marketplace\MarketplaceStoreAccessResolver::class)->resolveForView(Auth::user(), $storeId);
            $this->selectedStoreId = $storeId;
            $this->loadSelectedStore();
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->saveResult = 'authorization_denied';
            $this->notify($e->getMessage(), 'error');
        }
    }

    public function deleteSelectedStore(): void
    {
        if (!Auth::check()) {
            $this->saveResult = 'session_expired';
            return;
        }

        try {
            $store = app(\App\Services\Marketplace\MarketplaceStoreAccessResolver::class)->resolveForView(Auth::user(), (int) $this->selectedStoreId);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->saveResult = 'authorization_denied';
            $this->notify($e->getMessage(), 'error');
            return;
        }

        $storeName = $store->store_name;

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($store) {
                $store->connection()->delete();
                $store->syncProfile()->delete();
                $store->syncRuns()->delete();
                $store->webhookEvents()->delete();
                $store->pushRuns()->delete();
                $store->orderActionRuns()->delete();

                $store->channelProducts()->delete();
                $store->channelListings()->delete();
                $store->channelOrders()->delete();

                $store->delete();
            });

            $this->selectedStoreId = null;
            $this->loadSelectedStore();

            $this->notify("{$storeName} mağazası, bağlı sipariş/ürün kayıtları ve bağlantı ayarları başarıyla silindi.");
        } catch (\Throwable $exception) {
            $this->notify('Mağaza silinirken bir hata oluştu: ' . $exception->getMessage(), 'error');
        }
    }

    public function saveConnection(): void
    {
        if (!Auth::check()) {
            $this->saveResult = 'session_expired';
            return;
        }

        try {
            $store = app(\App\Services\Marketplace\MarketplaceStoreAccessResolver::class)->resolveForCredentialManagement(
                Auth::user(),
                (int) $this->selectedStoreId
            );
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->saveResult = 'authorization_denied';
            $this->notify($e->getMessage(), 'error');
            return;
        }

        $validated = $this->validate([
            'connectionForm.authType' => ['required', 'string', 'max:50'],
            'connectionForm.apiBaseUrl' => ['nullable', 'url', 'max:255'],
            'connectionForm.webhookSecret' => ['nullable', 'string', 'max:120'],
            'connectionForm.apiKey' => ['nullable', 'string', 'max:255'],
            'connectionForm.apiSecret' => ['nullable', 'string', 'max:255'],
            'connectionForm.zolmBoosterApiKey' => ['nullable', 'string', 'max:255'],
            'connectionForm.storeFrontCode' => ['nullable', 'string', 'max:64'],
            'connectionForm.extraUser' => ['nullable', 'string', 'max:255'],
            'connectionForm.extraPassword' => ['nullable', 'string', 'max:255'],
            'connectionForm.storeUrl' => ['nullable', 'url', 'max:255'],
        ]);

        $placeholders = ['test-key', 'test-secret', 'your-api-key', 'your-api-secret', 'changeme', 'demo'];
        $apiKeyToCheck = trim(Str::lower($validated['connectionForm']['apiKey'] ?? ''));
        $apiSecretToCheck = trim(Str::lower($validated['connectionForm']['apiSecret'] ?? ''));

        if (in_array($apiKeyToCheck, $placeholders, true) || in_array($apiSecretToCheck, $placeholders, true)) {
            if (app()->environment('production')) {
                $this->addError('connectionForm.apiKey', 'Geçerli production API bilgileri girilmelidir.');
                $this->saveResult = 'validation_failed';
                return;
            }
        }

        $existingConnection = $store->connection;
        $isDemo = $existingConnection?->isDemo() ?? false;
        $existingCredentials = $existingConnection?->credentials_encrypted ?? [];

        $providedSecret = $validated['connectionForm']['apiSecret'] ?? null;
        $apiSecret = ($providedSecret && $providedSecret !== '********')
            ? $providedSecret
            : ($existingCredentials['api_secret'] ?? null);

        $providedZolmBoosterApiKey = $validated['connectionForm']['zolmBoosterApiKey'] ?? null;
        $zolmBoosterApiKey = ($providedZolmBoosterApiKey && $providedZolmBoosterApiKey !== '********')
            ? $providedZolmBoosterApiKey
            : ($existingCredentials['zolm_booster_api_key'] ?? null);

        $providedExtraPassword = $validated['connectionForm']['extraPassword'] ?? null;
        $extraPassword = ($providedExtraPassword && $providedExtraPassword !== '********')
            ? $providedExtraPassword
            : ($existingCredentials['extra_password'] ?? null);

        $credentials = [
            'api_key' => $validated['connectionForm']['apiKey'] ?: null,
            'api_secret' => $apiSecret,
            'zolm_booster_api_key' => $store->marketplace === 'woocommerce' ? $zolmBoosterApiKey : null,
            'store_front_code' => $validated['connectionForm']['storeFrontCode'] ?: ($existingCredentials['store_front_code'] ?? null),
            'extra_user' => $validated['connectionForm']['extraUser'] ?: null,
            'extra_password' => $extraPassword,
            'store_url' => $validated['connectionForm']['storeUrl'] ?: null,
        ];

        $resolvedApiBaseUrl = $this->resolveConnectionApiBaseUrl(
            marketplace: $store->marketplace,
            explicitApiBaseUrl: $validated['connectionForm']['apiBaseUrl'] ?: null,
            storeUrl: $validated['connectionForm']['storeUrl'] ?: null,
        );

        $readiness = $this->inspectConnectionDraft(
            $store,
            array_filter($credentials, fn ($value) => filled($value)),
            $resolvedApiBaseUrl,
        );

        $connectionStatus = $isDemo
            ? IntegrationConnection::STATUS_DEMO
            : ($readiness['is_ready'] ? 'configured' : 'draft');
        $connectionError = $readiness['is_ready'] ? null : ($readiness['failures'][0] ?? null);

        $preUpdateUpdatedAt = $existingConnection ? $existingConnection->updated_at : null;
        $preUpdateFingerprint = $existingConnection ? hash('sha256', json_encode($existingCredentials)) : null;

        $targetTenantUserId = $store->user_id;
        $actingUser = Auth::user();
        $isCrossTenant = (int) $targetTenantUserId !== (int) $actingUser->id;

        DB::transaction(function () use ($store, $validated, $credentials, $resolvedApiBaseUrl, $connectionStatus, $connectionError, $existingConnection, $isDemo): void {
            $store->connection()->updateOrCreate(
                ['store_id' => $store->id],
                [
                    'provider' => $store->marketplace,
                    'auth_type' => $validated['connectionForm']['authType'],
                    'credentials_encrypted' => array_filter($credentials, fn ($value) => filled($value)),
                    'webhook_secret' => $validated['connectionForm']['webhookSecret'] ?: ($existingConnection?->webhook_secret ?: Str::random(40)),
                    'webhook_url' => $this->buildWebhookUrl($store),
                    'api_base_url' => $resolvedApiBaseUrl,
                    'status' => $connectionStatus,
                    'last_verified_at' => null,
                    'last_error' => $connectionError,
                ],
            );

            $store->forceFill([
                'status' => $isDemo ? $store->status : $connectionStatus,
            ])->save();
        });

        $store->refresh();
        $newConnection = $store->connection;
        $newCredentials = $newConnection?->credentials_encrypted ?? [];
        $newFingerprint = hash('sha256', json_encode($newCredentials));

        $updatedAtChanged = $newConnection && (!$preUpdateUpdatedAt || $newConnection->updated_at->gt($preUpdateUpdatedAt));
        $fingerprintChanged = $preUpdateFingerprint !== $newFingerprint;

        $newApiKeyToCheck = trim(Str::lower($newCredentials['api_key'] ?? ''));
        $newApiSecretToCheck = trim(Str::lower($newCredentials['api_secret'] ?? ''));
        $hasPlaceholder = in_array($newApiKeyToCheck, $placeholders, true) || in_array($newApiSecretToCheck, $placeholders, true);

        if (!$newConnection || $hasPlaceholder || (int) $newConnection->store_id !== (int) $store->id) {
            $this->saveResult = 'credential_save_failed';
            $this->notify('Bağlantı bilgileri doğrulanırken hata oluştu.', 'error');
            return;
        }

        if ($fingerprintChanged) {
            if ($isCrossTenant) {
                \App\Models\ActivityLog::log(
                    'update_connection_credentials',
                    "Updated store connection credentials via tenant context. Acting user: {$actingUser->id}, Target tenant: {$targetTenantUserId}, Target store: {$store->id}, Reason: credential maintenance",
                    'MarketplaceStore',
                    $store->id,
                    [
                        'api_key_present' => filled($credentials['api_key'] ?? null),
                        'api_secret_present' => filled($credentials['api_secret'] ?? null),
                        'api_key_length' => strlen($credentials['api_key'] ?? ''),
                        'api_secret_length' => strlen($credentials['api_secret'] ?? ''),
                        'fingerprint_changed' => true,
                    ]
                );
            }

            $this->saveResult = 'credential_saved';
        } else {
            $this->saveResult = 'credential_unchanged';
        }

        $this->loadSelectedStore();

        if ($readiness['is_ready']) {
            if (($readiness['warnings'] ?? []) !== []) {
                $this->notify(
                    'Bağlantı bilgileri kaydedildi ancak mağaza uyarılı durumda: '
                    . ($readiness['warnings'][0] ?? 'Ek uyarılar var.'),
                    'warning',
                );

                return;
            }

            $this->notify('Bağlantı bilgileri kaydedildi. Gizli alanları boş bırakırsanız mevcut değer korunur.');
            return;
        }

        $this->notify(
            'Bağlantı bilgileri kaydedildi ancak mağaza henüz hazır değil: ' . ($readiness['failures'][0] ?? 'Eksik zorunlu alanlar var.'),
            'error',
        );
    }

    protected function resolveConnectionApiBaseUrl(string $marketplace, ?string $explicitApiBaseUrl, ?string $storeUrl): ?string
    {
        $explicitApiBaseUrl = filled($explicitApiBaseUrl) ? rtrim(trim($explicitApiBaseUrl), '/') : null;
        $storeUrl = filled($storeUrl) ? rtrim(trim($storeUrl), '/') : null;
        $normalizedMarketplace = MarketplaceProviderRegistry::normalize($marketplace);

        if ($normalizedMarketplace === 'pazarama' && $explicitApiBaseUrl !== null) {
            $host = Str::lower((string) parse_url($explicitApiBaseUrl, PHP_URL_HOST));

            if ($host === 'isortagimgiris.pazarama.com') {
                return MarketplaceProviderRegistry::defaultApiBaseUrl($marketplace);
            }
        }

        if ($explicitApiBaseUrl !== null) {
            return $explicitApiBaseUrl;
        }

        if (in_array($normalizedMarketplace, ['woocommerce', 'shopify'], true) && $storeUrl !== null) {
            return $storeUrl;
        }

        return MarketplaceProviderRegistry::defaultApiBaseUrl($marketplace);
    }

    public function saveSyncProfile(): void
    {
        if (!Auth::check()) {
            $this->saveResult = 'session_expired';
            return;
        }

        try {
            $store = app(\App\Services\Marketplace\MarketplaceStoreAccessResolver::class)->resolveForView(Auth::user(), (int) $this->selectedStoreId);
            $this->selectedStore = $store;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->saveResult = 'authorization_denied';
            $this->notify($e->getMessage(), 'error');
            return;
        }

        $marketplace = $this->selectedStore->marketplace;
        $allowedWebhookTopics = IntegrationSyncProfile::recommendedWebhookTopicsForMarketplace($marketplace);

        $validated = $this->validate([
            'syncForm.ordersPollMinutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'syncForm.financePollMinutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'syncForm.productsPollMinutes' => ['required', 'integer', 'min:5', 'max:10080'],
            'syncForm.claimsPollMinutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'syncForm.questionsPollMinutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'syncForm.backfillMode' => ['required', Rule::in(array_keys(MarketplaceProviderRegistry::backfillOptions()))],
            'syncForm.backfillCustomFrom' => [Rule::requiredIf(($this->syncForm['backfillMode'] ?? null) === 'custom'), 'nullable', 'date'],
            'syncForm.backfillCustomTo' => [Rule::requiredIf(($this->syncForm['backfillMode'] ?? null) === 'custom'), 'nullable', 'date'],
            'syncForm.ordersEnabled' => ['boolean'],
            'syncForm.financeEnabled' => ['boolean'],
            'syncForm.productsEnabled' => ['boolean'],
            'syncForm.claimsEnabled' => ['boolean'],
            'syncForm.questionsEnabled' => ['boolean'],
            'syncForm.webhookEnabled' => ['boolean'],
            'syncForm.pricePushEnabled' => ['boolean'],
            'syncForm.stockPushEnabled' => ['boolean'],
            'syncForm.autoMatchEnabled' => ['boolean'],
            'syncForm.barcodeFallbackEnabled' => ['boolean'],
            'syncForm.strictUniqueMatchEnabled' => ['boolean'],
            'syncForm.nightlyRepairSyncEnabled' => ['boolean'],
            'syncForm.maxParallelJobs' => ['required', 'integer', 'min:1', 'max:5'],
            'syncForm.requestJitterSeconds' => ['required', 'integer', 'min:0', 'max:60'],
            'syncForm.webhookTopics' => ['nullable', 'array'],
            'syncForm.webhookTopics.*' => ['string', Rule::in($allowedWebhookTopics)],
        ]);

        $backfillMode = $validated['syncForm']['backfillMode'];

        if (
            $backfillMode === 'custom'
            && filled($validated['syncForm']['backfillCustomFrom'])
            && filled($validated['syncForm']['backfillCustomTo'])
            && strtotime($validated['syncForm']['backfillCustomTo']) < strtotime($validated['syncForm']['backfillCustomFrom'])
        ) {
            $this->addError('syncForm.backfillCustomTo', 'Bitiş tarihi başlangıç tarihinden önce olamaz.');

            return;
        }
        $backfillDays = match ($backfillMode) {
            '7_days' => 7,
            '30_days' => 30,
            '90_days' => 90,
            '180_days' => 180,
            default => null,
        };

        $extraSettings = $this->selectedStore->syncProfile?->extra_settings ?? [];

        if (in_array($marketplace, ['woocommerce', 'shopify'], true)) {
            $extraSettings['webhook_topics'] = collect(Arr::wrap($validated['syncForm']['webhookTopics'] ?? []))
                ->filter(fn ($topic) => filled($topic))
                ->map(fn ($topic) => (string) $topic)
                ->unique()
                ->values()
                ->all();
        }

        [$featureToggles, $forcedOffSettings] = $this->normalizeSyncFeatureToggles(
            $validated['syncForm'],
            app(MarketplaceConnectorManager::class)->resolveForStore($this->selectedStore)->capabilities(),
        );

        $this->selectedStore->syncProfile()->updateOrCreate(
            ['store_id' => $this->selectedStore->id],
            [
                'orders_poll_minutes' => $validated['syncForm']['ordersPollMinutes'],
                'finance_poll_minutes' => $validated['syncForm']['financePollMinutes'],
                'products_poll_minutes' => $validated['syncForm']['productsPollMinutes'],
                'claims_poll_minutes' => $validated['syncForm']['claimsPollMinutes'],
                'questions_poll_minutes' => $validated['syncForm']['questionsPollMinutes'],
                'backfill_mode' => $backfillMode,
                'backfill_days' => $backfillMode === 'custom' || $backfillMode === 'max_allowed' ? null : $backfillDays,
                'backfill_custom_from' => $backfillMode === 'custom' ? $validated['syncForm']['backfillCustomFrom'] : null,
                'backfill_custom_to' => $backfillMode === 'custom' ? $validated['syncForm']['backfillCustomTo'] : null,
                'orders_enabled' => $featureToggles['ordersEnabled'],
                'finance_enabled' => $featureToggles['financeEnabled'],
                'products_enabled' => $featureToggles['productsEnabled'],
                'claims_enabled' => $featureToggles['claimsEnabled'],
                'questions_enabled' => $featureToggles['questionsEnabled'],
                'webhook_enabled' => $featureToggles['webhookEnabled'],
                'price_push_enabled' => $featureToggles['pricePushEnabled'],
                'stock_push_enabled' => $featureToggles['stockPushEnabled'],
                'auto_match_enabled' => (bool) $validated['syncForm']['autoMatchEnabled'],
                'barcode_fallback_enabled' => (bool) $validated['syncForm']['barcodeFallbackEnabled'],
                'strict_unique_match_enabled' => (bool) $validated['syncForm']['strictUniqueMatchEnabled'],
                'nightly_repair_sync_enabled' => (bool) $validated['syncForm']['nightlyRepairSyncEnabled'],
                'max_parallel_jobs' => $validated['syncForm']['maxParallelJobs'],
                'request_jitter_seconds' => $validated['syncForm']['requestJitterSeconds'],
                'extra_settings' => $extraSettings,
            ],
        );

        $this->loadSelectedStore();

        if ($forcedOffSettings !== []) {
            $this->notify(
                'Senkron profili kaydedildi. Desteklenmeyen ayarlar pasife alındı: ' . implode(', ', $forcedOffSettings) . '.',
                'warning',
            );

            return;
        }

        $this->notify('Senkron profili kaydedildi.');
    }

    public function applyWooSafeProfile(): void
    {
        $this->applySafeProfilePreset('woocommerce');
    }

    public function applyShopifySafeProfile(): void
    {
        $this->applySafeProfilePreset('shopify');
    }

    public function applyTrendyolSafeProfile(): void
    {
        $this->applySafeProfilePreset('trendyol');
    }

    public function applyHepsiburadaSafeProfile(): void
    {
        $this->applySafeProfilePreset('hepsiburada');
    }

    public function applySelectedStoreSafeProfile(): void
    {
        abort_unless($this->selectedStore, 404);

        $this->applySafeProfilePreset($this->selectedStore->marketplace);
    }

    public function applyRecommendedWebhookTopics(): void
    {
        abort_unless($this->selectedStore, 404);

        $topics = IntegrationSyncProfile::recommendedWebhookTopicsForMarketplace($this->selectedStore->marketplace);

        if ($topics === []) {
            $this->notify('Bu mağaza için önerilen webhook topic seti tanımlı değil.', 'warning');

            return;
        }

        $this->syncForm['webhookTopics'] = $topics;
        $this->syncForm['webhookEnabled'] = true;

        $label = $this->selectedStore->marketplace === 'shopify'
            ? 'Shopify'
            : 'WooCommerce';

        $this->notify("{$label} için önerilen webhook topic seti forma uygulandı. Kalıcı olması için kaydetmeyi unutmayın.");
    }

    protected function applySafeProfilePreset(string $marketplace): void
    {
        abort_unless($this->selectedStore, 404);

        if ($this->selectedStore->marketplace !== $marketplace) {
            $label = MarketplaceProviderRegistry::get($marketplace)['label'] ?? ucfirst($marketplace);
            $this->notify("Güvenli {$label} profili sadece {$label} mağazalarında kullanılabilir.", 'warning');

            return;
        }

        $defaults = $this->safeProfileFormDefaults($marketplace);
        $label = MarketplaceProviderRegistry::get($marketplace)['label'] ?? ucfirst($marketplace);

        $this->syncForm = [
            'ordersPollMinutes' => $defaults['orders_poll_minutes'],
            'financePollMinutes' => $defaults['finance_poll_minutes'],
            'productsPollMinutes' => $defaults['products_poll_minutes'],
            'claimsPollMinutes' => $defaults['claims_poll_minutes'],
            'questionsPollMinutes' => $defaults['questions_poll_minutes'],
            'backfillMode' => $defaults['backfill_mode'],
            'backfillCustomFrom' => '',
            'backfillCustomTo' => '',
            'ordersEnabled' => (bool) $defaults['orders_enabled'],
            'financeEnabled' => (bool) $defaults['finance_enabled'],
            'productsEnabled' => (bool) $defaults['products_enabled'],
            'claimsEnabled' => (bool) $defaults['claims_enabled'],
            'questionsEnabled' => (bool) $defaults['questions_enabled'],
            'webhookEnabled' => (bool) $defaults['webhook_enabled'],
            'pricePushEnabled' => (bool) $defaults['price_push_enabled'],
            'stockPushEnabled' => (bool) $defaults['stock_push_enabled'],
            'autoMatchEnabled' => app(MpSettingsService::class)->getAutoRunMatchingOnSync(),
            'barcodeFallbackEnabled' => (bool) $defaults['barcode_fallback_enabled'],
            'strictUniqueMatchEnabled' => (bool) $defaults['strict_unique_match_enabled'],
            'nightlyRepairSyncEnabled' => (bool) $defaults['nightly_repair_sync_enabled'],
            'maxParallelJobs' => $defaults['max_parallel_jobs'],
            'requestJitterSeconds' => $defaults['request_jitter_seconds'],
            'webhookTopics' => Arr::wrap(data_get($defaults, 'extra_settings.webhook_topics', [])),
        ];

        $this->notify("{$label} için düşük etkili profil forma uygulandı. İnceleyip kaydedebilirsiniz.");
    }

    /**
     * @return array<string, mixed>
     */
    protected function safeProfileFormDefaults(string $marketplace): array
    {
        $defaults = IntegrationSyncProfile::defaultsForMarketplace($marketplace);

        if (in_array(strtolower($marketplace), ['hepsiburada', 'shopify'], true)) {
            $defaults['orders_poll_minutes'] = 20;
        }

        return $defaults;
    }

    public function runSync(string $syncType): void
    {
        if (!Auth::check()) {
            $this->saveResult = 'session_expired';
            return;
        }

        try {
            $store = app(\App\Services\Marketplace\MarketplaceStoreAccessResolver::class)->resolveForView(Auth::user(), (int) $this->selectedStoreId);
            $this->selectedStore = $store;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->saveResult = 'authorization_denied';
            $this->notify($e->getMessage(), 'error');
            return;
        }

        $readiness = $this->selectedConnectionReadiness;

        if (!$this->selectedStore->connection || !($readiness['is_ready'] ?? false)) {
            $message = $readiness['failures'][0] ?? 'Önce bağlantı bilgilerini kaydedin. Taslak mağaza ile senkron başlatılamaz.';
            $this->notify('Senkron başlatılamadı: ' . $message, 'error');

            return;
        }

        $allowedSyncTypes = ['orders', 'products', 'finance', 'questions', 'claims'];

        if (!in_array($syncType, $allowedSyncTypes, true)) {
            $this->notify('Geçersiz senkron tipi seçildi.', 'error');

            return;
        }

        try {
            $syncOptions = [];

            if (
                $syncType === 'products'
                && MarketplaceProviderRegistry::normalize((string) $this->selectedStore->marketplace) === 'woocommerce'
            ) {
                $syncOptions = [
                    'full_catalog_refresh' => true,
                    'page_size' => 100,
                ];
            }

            $result = app(MarketplaceManualSyncDispatchService::class)->dispatch($this->selectedStore, $syncType, [
                'options' => $syncOptions,
                'bypass_recent' => (bool) ($syncOptions['full_catalog_refresh'] ?? false),
                'force_inline' => $syncType === 'products',
                'ignore_queued_active' => $syncType === 'products',
                'source' => 'manual_sync_button',
                'origin_screen' => 'integrations',
            ]);

            $feedback = app(MarketplaceManualSyncDispatchService::class)->feedback(
                $result,
                $this->syncTypeLabel($syncType),
                $this->selectedStore->store_name,
            );

            $this->notify($feedback['message'], $feedback['tone']);
        } catch (\Throwable $exception) {
            $this->notify('Senkron kuyruğa alınamadı: ' . $exception->getMessage(), 'error');
        }
    }

    public function regenerateWebhookSecret(): void
    {
        if (!Auth::check()) {
            $this->saveResult = 'session_expired';
            return;
        }

        try {
            $store = app(\App\Services\Marketplace\MarketplaceStoreAccessResolver::class)->resolveForView(Auth::user(), (int) $this->selectedStoreId);
            $this->selectedStore = $store;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->saveResult = 'authorization_denied';
            $this->notify($e->getMessage(), 'error');
            return;
        }

        $this->connectionForm['webhookSecret'] = Str::random(40);
    }

    public function verifyConnection(
        MarketplaceConnectorManager $connectorManager,
        MarketplaceConnectionReadinessService $connectionReadinessService,
    ): void
    {
        if (!Auth::check()) {
            $this->saveResult = 'session_expired';
            return;
        }

        try {
            $store = app(\App\Services\Marketplace\MarketplaceStoreAccessResolver::class)->resolveForView(Auth::user(), (int) $this->selectedStoreId);
            $this->selectedStore = $store;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->saveResult = 'authorization_denied';
            $this->notify($e->getMessage(), 'error');
            return;
        }

        try {
            $this->selectedStore->loadMissing('connection');
            $connection = $this->selectedStore->connection;
            $isDemo = $connection?->isDemo() ?? false;
            $connector = $connectorManager->resolveForStore($this->selectedStore);

            if (!$connector instanceof TestsConnection) {
                $this->notify('Bu kanal için bağlantı doğrulama henüz desteklenmiyor.', 'error');

                return;
            }

            $result = $connector->testConnection($this->selectedStore);

            if (!(bool) ($result['ok'] ?? false)) {
                $connection?->forceFill([
                    'status' => $isDemo ? IntegrationConnection::STATUS_DEMO : 'draft',
                    'last_error' => $result['message'] ?? 'Bağlantı doğrulanamadı.',
                ])->save();

                $this->notify($result['message'] ?? 'Bağlantı doğrulanamadı.', 'error');
                $this->loadSelectedStore();

                return;
            }

            $verifiedAt = now();

            $connection?->forceFill([
                'status' => $isDemo ? IntegrationConnection::STATUS_DEMO : 'configured',
                'last_verified_at' => $isDemo ? $connection->last_verified_at : $verifiedAt,
                'last_error' => null,
            ])->save();

            $readiness = $connectionReadinessService->inspect($this->selectedStore->fresh(['connection', 'syncProfile']));

            if (!(bool) ($readiness['is_ready'] ?? false)) {
                $connection?->forceFill([
                    'status' => $isDemo ? IntegrationConnection::STATUS_DEMO : 'draft',
                    'last_verified_at' => $isDemo ? $connection->last_verified_at : $verifiedAt,
                    'last_error' => $readiness['failures'][0] ?? 'Bağlantı doğrulandı ancak mağaza henüz sync için hazır değil.',
                ])->save();

                $this->notify(
                    'API bağlantısı doğrulandı ancak mağaza henüz sync için hazır değil: '
                    . ($readiness['failures'][0] ?? 'Eksik zorunlu alanlar var.'),
                    'warning'
                );
                $this->loadSelectedStore();

                return;
            }

            $connection?->forceFill([
                'status' => $isDemo ? IntegrationConnection::STATUS_DEMO : 'configured',
                'last_verified_at' => $isDemo ? $connection->last_verified_at : $verifiedAt,
                'last_error' => null,
            ])->save();

            if (($readiness['warnings'] ?? []) !== []) {
                $this->notify(
                    ($result['message'] ?? 'Bağlantı doğrulandı.')
                    . ' Ancak mağaza uyarılı durumda: '
                    . ($readiness['warnings'][0] ?? 'Ek uyarılar var.'),
                    'warning'
                );
                $this->loadSelectedStore();

                return;
            }

            $this->notify($result['message'] ?? 'Bağlantı doğrulandı.');
            $this->loadSelectedStore();
        } catch (\Throwable $exception) {
            $message = $this->friendlyConnectionExceptionMessage($exception);

            $failedConnection = $this->selectedStore->connection;
            $failedStatus = $failedConnection?->isDemo() === true
                ? IntegrationConnection::STATUS_DEMO
                : 'error';
            $failedConnection?->forceFill([
                'status' => $failedStatus,
                'last_error' => $message,
            ])->save();

            $this->notify('Bağlantı doğrulaması başarısız: '.$message, 'error');
            $this->loadSelectedStore();
        }
    }

    protected function friendlyConnectionExceptionMessage(\Throwable $exception): string
    {
        $message = $exception->getMessage();

        if (
            $this->selectedStore?->marketplace === 'hepsiburada'
            && str_contains(Str::lower($message), 'merchant api authorization failed')
        ) {
            return 'Hepsiburada yetkilendirmesi reddedildi. Merchant ID ve servis anahtarı doğru görünse bile Hepsiburada bu User-Agent / entegratör kullanıcı için mağaza yetkisi istiyor. Prapazar anahtarını kullanıyorsanız Prapazar’ın Hepsiburada User-Agent adını girin; bilmiyorsanız Hepsiburada destekten ZOLM veya SelfIntegration için API yetkisi açtırın.';
        }

        return $message;
    }

    public function exportReadinessCsv()
    {
        $stores = app(\App\Services\Marketplace\MarketplaceStoreAccessResolver::class)
            ->accessibleStores(Auth::user())
            ->with(['legalEntity', 'connection', 'syncProfile'])
            ->orderBy('store_name')
            ->get();

        $readinessRows = collect(app(MarketplaceConnectionReadinessService::class)->inspectCollection($stores)['rows'])
            ->keyBy('store_id');
        $guidanceRows = collect($this->storeGuidanceMap);

        $filename = 'pazaryeri_hazirlik_raporu_' . now()->format('Ymd_His') . '.csv';

        return response()->stream(function () use ($stores, $readinessRows, $guidanceRows) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");

            $legacyRows = collect($this->legacyProjectionStoreMap);

            fputcsv($file, [
                'Firma',
                'Mağaza',
                'Pazaryeri',
                'Bağlantı Durumu',
                'Webhook',
                'Hazırlık Durumu',
                'Özet',
                'İlk Eksik',
                'İlk Uyarı',
                'İlk Öneri',
                'Öneri Seviyesi',
                'Önerilen Ekran',
                'Eski Veri Bekleyen',
                'Eski Veri Kesine Dönen',
                'Eski Veri Son Yansıtma',
                'Ön Test Komutu',
            ], ';');

            foreach ($stores as $store) {
                $row = $readinessRows->get($store->id, []);
                $guidance = $guidanceRows->get($store->id, []);
                $topItem = $guidance['top_item'] ?? null;
                $legacy = $legacyRows->get($store->id, []);

                fputcsv($file, [
                    $this->cleanExportString($store->legalEntity?->name),
                    $this->cleanExportString($store->store_name),
                    $this->cleanExportString($this->providerOptions[$store->marketplace] ?? ucfirst($store->marketplace)),
                    $this->cleanExportString($store->connection?->status ?? 'taslak'),
                    $this->cleanExportString($store->syncProfile?->webhook_enabled ? 'Açık' : 'Kapalı'),
                    $this->cleanExportString($this->readinessStateLabel((string) ($row['state'] ?? 'missing'))),
                    $this->cleanExportString($row['summary'] ?? ''),
                    $this->cleanExportString($row['first_failure'] ?? ''),
                    $this->cleanExportString($row['first_warning'] ?? ''),
                    $this->cleanExportString(data_get($topItem, 'title')),
                    $this->cleanExportString(data_get($topItem, 'severity') ? $this->guidanceSeverityLabel((string) data_get($topItem, 'severity')) : ''),
                    $this->cleanExportString(data_get($topItem, 'route') ? $this->guidanceRouteLabel((string) data_get($topItem, 'route')) : ''),
                    (int) data_get($legacy, 'pending_rows', 0),
                    (int) data_get($legacy, 'confirmed_orders', 0),
                    $this->cleanExportString(data_get($legacy, 'last_projected_at') ? \Illuminate\Support\Carbon::parse((string) data_get($legacy, 'last_projected_at'))->format('d.m.Y H:i:s') : ''),
                    $this->cleanExportString('php artisan marketplace:smoke-test ' . $store->id . ' --type=all --hours=24 --preview=2 --persist'),
                ], ';');
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportSelectedStoreSmokeCsv()
    {
        abort_unless($this->selectedStore, 404);

        $rows = $this->selectedStore
            ->syncRuns()
            ->where('trigger_type', 'smoke_test')
            ->latest('created_at')
            ->get();

        $filename = 'pazaryeri_smoke_gecmisi_' . $this->selectedStore->id . '_' . now()->format('Ymd_His') . '.csv';

        return response()->stream(function () use ($rows) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'Mağaza',
                'Senkron Türü',
                'Durum',
                'Tetik',
                'Tarih',
                'Alınan Kayıt',
                'Uyarı Sayısı',
                'Özet Uyarılar',
                'Son Hata',
            ], ';');

            foreach ($rows as $run) {
                fputcsv($file, [
                    $this->cleanExportString($run->store?->store_name),
                    $this->cleanExportString(Str::headline((string) $run->sync_type)),
                    $this->cleanExportString($run->status),
                    $this->cleanExportString($run->triggerLabel()),
                    $run->created_at?->format('d/m/Y H:i'),
                    (int) ($run->items_received ?? 0),
                    $run->diagnosticWarningCount(),
                    $this->cleanExportString(implode(' | ', $run->diagnosticsWarnings())),
                    $this->cleanExportString(data_get($run->notes_json, 'last_error')),
                ], ';');
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function previewSelectedStoreLegacyProjection(): void
    {
        abort_unless($this->selectedStore, 404);

        $store = $this->selectedStore;
        $summary = app(LegacyFinancialProjectionService::class)->previewStore($store, true);

        $this->legacyProjectionPreview = [
            'store_id' => $store->id,
            'store_name' => $store->store_name,
            'executed' => false,
            'generated_at' => now()->toDateTimeString(),
            'projected_rows' => (int) ($summary['projected_rows'] ?? 0),
            'created' => (int) ($summary['created'] ?? 0),
            'updated' => (int) ($summary['updated'] ?? 0),
            'skipped' => (int) ($summary['skipped'] ?? 0),
            'impacted_orders' => count($summary['impacted_order_ids'] ?? []),
        ];

        if (($this->legacyProjectionPreview['projected_rows'] ?? 0) === 0) {
            $this->notify('Seçili mağaza için projekte edilecek legacy finans satırı bulunamadı.', 'warning');

            return;
        }

        $this->notify('Eski veri yansıtma ön izlemesi hazırlandı. Aday satırları kontrol edip gerçek taşıma çalıştırabilirsiniz.');
    }

    public function runSelectedStoreLegacyProjection(): void
    {
        abort_unless($this->selectedStore, 404);

        $store = $this->selectedStore;
        $preview = app(LegacyFinancialProjectionService::class)->previewStore($store, true);

        if (((int) ($preview['projected_rows'] ?? 0)) === 0) {
            $this->legacyProjectionPreview = [
                'store_id' => $store->id,
                'store_name' => $store->store_name,
                'executed' => false,
                'generated_at' => now()->toDateTimeString(),
                'projected_rows' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'impacted_orders' => 0,
            ];

            $this->notify('Seçili mağaza için projekte edilecek legacy finans satırı bulunamadı.', 'warning');

            return;
        }

        $result = app(LegacyFinancialProjectionService::class)->projectStore($store, true);

        $this->loadSelectedStore();
        $this->legacyProjectionPreview = [
            'store_id' => $store->id,
            'store_name' => $store->store_name,
            'executed' => true,
            'generated_at' => now()->toDateTimeString(),
            'projected_rows' => (int) ($result['projected_rows'] ?? 0),
            'created' => (int) ($result['created'] ?? 0),
            'updated' => (int) ($result['updated'] ?? 0),
            'skipped' => (int) ($result['skipped'] ?? 0),
            'impacted_orders' => count($result['impacted_order_ids'] ?? []),
        ];
        $this->notify('Eski veri yansıtması tamamlandı. '
            . number_format((int) ($result['created'] ?? 0), 0, ',', '.') . ' yeni, '
            . number_format((int) ($result['updated'] ?? 0), 0, ',', '.') . ' güncelleme işlendi.');
    }

    public function getSelectedStoreProperty(): ?MarketplaceStore
    {
        if (!$this->selectedStoreId) {
            return null;
        }

        try {
            $store = app(\App\Services\Marketplace\MarketplaceStoreAccessResolver::class)->resolveForView(Auth::user(), (int) $this->selectedStoreId);
            return $store->load(['legalEntity', 'connection', 'syncProfile']);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return null;
        }
    }

    /**
     * @return array{aligned: bool, mismatches: array<int, array<string, mixed>>, summary: string}|null
     */
    public function getSelectedStoreSafeProfileStatusProperty(): ?array
    {
        $marketplace = $this->selectedStore?->marketplace;

        if (!in_array($marketplace, ['trendyol', 'hepsiburada', 'woocommerce', 'shopify'], true)) {
            return null;
        }

        $defaults = $this->safeProfileFormDefaults($marketplace);
        $label = MarketplaceProviderRegistry::get($marketplace)['label'] ?? ucfirst((string) $marketplace);
        $checks = [
            'ordersPollMinutes' => ['label' => 'Sipariş senkronu', 'expected' => $defaults['orders_poll_minutes']],
            'financePollMinutes' => ['label' => 'Finans senkronu', 'expected' => $defaults['finance_poll_minutes']],
            'productsPollMinutes' => ['label' => 'Ürün senkronu', 'expected' => $defaults['products_poll_minutes']],
            'claimsPollMinutes' => ['label' => 'İade senkronu', 'expected' => $defaults['claims_poll_minutes']],
            'questionsPollMinutes' => ['label' => 'Soru senkronu', 'expected' => $defaults['questions_poll_minutes']],
            'webhookEnabled' => ['label' => 'Webhook aktif', 'expected' => (bool) $defaults['webhook_enabled']],
            'financeEnabled' => ['label' => 'Finans senkronu aktif', 'expected' => (bool) $defaults['finance_enabled']],
            'claimsEnabled' => ['label' => 'İade senkronu aktif', 'expected' => (bool) $defaults['claims_enabled']],
            'questionsEnabled' => ['label' => 'Soru senkronu aktif', 'expected' => (bool) $defaults['questions_enabled']],
            'pricePushEnabled' => ['label' => 'Fiyat gönderimi aktif', 'expected' => (bool) $defaults['price_push_enabled']],
            'stockPushEnabled' => ['label' => 'Stok gönderimi aktif', 'expected' => (bool) $defaults['stock_push_enabled']],
            'maxParallelJobs' => ['label' => 'Maks. paralel iş', 'expected' => $defaults['max_parallel_jobs']],
            'requestJitterSeconds' => ['label' => 'İstek sapması', 'expected' => $defaults['request_jitter_seconds']],
        ];

        $mismatches = [];

        foreach ($checks as $key => $rule) {
            $currentValue = $this->syncForm[$key] ?? null;

            if ((string) $currentValue === (string) $rule['expected']) {
                continue;
            }

            $mismatches[] = [
                'label' => $rule['label'],
                'current' => $currentValue,
                'expected' => $rule['expected'],
            ];
        }

        return [
            'aligned' => $mismatches === [],
            'mismatches' => $mismatches,
            'summary' => $mismatches === []
                ? "Form güvenli {$label} profili ile uyumlu."
                : count($mismatches) . " alanda güvenli {$label} profilinden sapma var.",
        ];
    }

    /**
     * @return array{aligned: bool, mismatches: array<int, array<string, mixed>>, summary: string}|null
     */
    public function getSelectedStoreWooSafeProfileStatusProperty(): ?array
    {
        return $this->getSelectedStoreSafeProfileStatusProperty();
    }

    public function getSelectedStoreSupportsSafeProfileProperty(): bool
    {
        return in_array($this->selectedStore?->marketplace, ['trendyol', 'hepsiburada', 'woocommerce', 'shopify'], true);
    }

    public function getSelectedStoreSafeProfileButtonLabelProperty(): string
    {
        $label = MarketplaceProviderRegistry::get((string) $this->selectedStore?->marketplace)['label'] ?? 'Mağaza';

        return "{$label} güvenli profil uygula";
    }

    public function getSelectedStoreSafeProfileTitleProperty(): string
    {
        $label = MarketplaceProviderRegistry::get((string) $this->selectedStore?->marketplace)['label'] ?? 'Bu kanal';

        return "{$label} için düşük etkili önerilen profil";
    }

    public function getSelectedStoreSafeProfileDescriptionProperty(): string
    {
        $label = MarketplaceProviderRegistry::get((string) $this->selectedStore?->marketplace)['label'] ?? 'Bu kanal';

        return "Bu aksiyon formu güvenli {$label} varsayılanları ile doldurur. Kalıcı olması için aşağıdan kaydetmeniz gerekir.";
    }

    /**
     * @return array<string, string>
     */
    public function getProviderOptionsProperty(): array
    {
        return MarketplaceProviderRegistry::options();
    }

    /**
     * @return array<string, mixed>
     */
    public function getSelectedProviderMetaProperty(): array
    {
        $provider = $this->selectedStore?->marketplace ?: ($this->storeForm['marketplace'] ?? 'trendyol');

        return MarketplaceProviderRegistry::get($provider);
    }

    /**
     * @return array<string, bool>
     */
    public function getSelectedCapabilitiesProperty(): array
    {
        $provider = $this->selectedStore?->marketplace ?: ($this->storeForm['marketplace'] ?? 'trendyol');

        $manager = app(MarketplaceConnectorManager::class);

        return ($this->selectedStore
            ? $manager->resolveForStore($this->selectedStore)
            : $manager->resolve($provider))
            ->capabilities();
    }

    /**
     * @return array<string, string>
     */
    public function capabilityLabels(): array
    {
        return [
            'orders' => 'Sipariş',
            'products' => 'Ürün',
            'finance' => 'Finans',
            'webhooks' => 'Webhook',
            'questions' => 'Soru',
            'price_push' => 'Fiyat Gönderimi',
            'stock_push' => 'Stok Gönderimi',
            'package_status' => 'Paket Durumu',
            'package_picking' => 'Toplama',
            'package_invoiced' => 'Fatura Bildirimi',
            'common_label' => 'Ortak Barkod',
            'package_common_label_create' => 'Ortak Barkod Talebi',
            'package_common_label_get' => 'Ortak Barkod Çekme',
            'invoice_link' => 'Fatura Linki',
            'package_invoice_link' => 'Paket Fatura Linki',
        ];
    }

    public function getWebhookUrlPreviewProperty(): ?string
    {
        if (!$this->selectedStore) {
            return null;
        }

        return $this->buildWebhookUrl($this->selectedStore);
    }

    public function getSmokeTestCommandProperty(): ?string
    {
        if (!$this->selectedStore) {
            return null;
        }

        return 'php artisan marketplace:smoke-test ' . $this->selectedStore->id . ' --type=all --hours=24 --preview=2 --persist';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSelectedConnectionReadinessProperty(): ?array
    {
        if (!$this->selectedStore) {
            return null;
        }

        return app(MarketplaceConnectionReadinessService::class)->inspect($this->selectedStore);
    }

    /**
     * @return array{
     *     totals: array{stores: int, ready: int, warning: int, missing: int},
     *     rows: array<int, array<string, mixed>>
     * }|null
     */
    public function getReadinessSummaryProperty(): ?array
    {
        $stores = app(\App\Services\Marketplace\MarketplaceStoreAccessResolver::class)
            ->accessibleStores(Auth::user())
            ->with(['connection'])
            ->orderBy('store_name')
            ->get();

        if ($stores->isEmpty()) {
            return null;
        }

        return app(MarketplaceConnectionReadinessService::class)->inspectCollection($stores);
    }

    /**
     * @return array{totals: array<string, int>, items: array<int, array<string, mixed>>}
     */
    public function getDiagnosticsGuidanceSummaryProperty(): array
    {
        return app(MarketplaceDiagnosticsGuidanceService::class)->guidanceForUser(Auth::id() ?? 1, [
            'hours' => 168,
            'limit' => 200,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getRiskGuidanceProperty(): array
    {
        return app(MarketplaceRiskSignalService::class)->guidanceForContext(
            (int) (Auth::id() ?? 1),
            'integrations'
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getStoreGuidanceMapProperty(): array
    {
        return collect($this->diagnosticsGuidanceSummary['items'] ?? [])
            ->groupBy('store_id')
            ->map(function ($items): array {
                $items = collect($items)->values();

                return [
                    'total' => $items->count(),
                    'critical' => $items->where('severity', 'critical')->count(),
                    'warning' => $items->where('severity', 'warning')->count(),
                    'info' => $items->where('severity', 'info')->count(),
                    'top_item' => $items->first(),
                    'items' => $items->take(3)->values()->all(),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLegacyProjectionStoreMapProperty(): array
    {
        $service = app(LegacyFinancialProjectionInsightsService::class);

        return app(\App\Services\Marketplace\MarketplaceStoreAccessResolver::class)
            ->accessibleStores(Auth::user())
            ->with('legalEntity:id,name')
            ->orderBy('store_name')
            ->get(['id', 'legal_entity_id', 'marketplace', 'store_name'])
            ->mapWithKeys(function (MarketplaceStore $store) use ($service): array {
                $summary = $service->summaryForUser(Auth::id() ?? 1, $store->id, $store->legal_entity_id);

                return [
                    $store->id => [
                        'store_id' => (int) $store->id,
                        'store_name' => $store->store_name,
                        'marketplace' => $store->marketplace,
                        'legal_entity_name' => $store->legalEntity?->name,
                        'pending_rows' => (int) ($summary['pending_rows'] ?? 0),
                        'projected_rows' => (int) ($summary['projected_rows'] ?? 0),
                        'legacy_event_orders' => (int) ($summary['legacy_event_orders'] ?? 0),
                        'confirmed_orders' => (int) ($summary['confirmed_orders'] ?? 0),
                        'last_projected_at' => $summary['last_projected_at'] ?? null,
                    ],
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSelectedStoreLegacyProjectionProperty(): ?array
    {
        if (!$this->selectedStore) {
            return null;
        }

        return data_get($this->legacyProjectionStoreMap, $this->selectedStore->id);
    }

    public function getSelectedStoreLegacyProjectionDryRunCommandProperty(): ?string
    {
        if (!$this->selectedStore) {
            return null;
        }

        return 'php artisan marketplace:project-legacy-financials '
            . $this->selectedStore->id
            . ' --only-unprojected --dry-run';
    }

    public function getSelectedStoreLegacyProjectionRunCommandProperty(): ?string
    {
        if (!$this->selectedStore) {
            return null;
        }

        return 'php artisan marketplace:project-legacy-financials '
            . $this->selectedStore->id
            . ' --only-unprojected';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSelectedStoreGuidanceItemsProperty(): array
    {
        if (!$this->selectedStore) {
            return [];
        }

        return data_get($this->storeGuidanceMap, $this->selectedStore->id . '.items', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSelectedConnectionGuideProperty(): array
    {
        $provider = MarketplaceProviderRegistry::normalize((string) ($this->selectedStore?->marketplace ?: ($this->storeForm['marketplace'] ?? 'trendyol')));

        return match ($provider) {
            'trendyol' => [
                'default_auth_type' => 'api_key_secret',
                'seller_id_label' => 'Satıcı ID',
                'seller_id_placeholder' => 'Trendyol supplierId',
                'api_base_url_label' => 'Trendyol API URL',
                'api_base_url_placeholder' => config('marketplace.trendyol.base_url'),
                'api_key_label' => 'API Anahtarı',
                'api_key_placeholder' => 'Trendyol API key',
                'api_secret_label' => 'API Gizli Anahtarı',
                'api_secret_placeholder' => 'Trendyol API secret',
                'store_front_code_label' => 'StoreFrontCode',
                'store_front_code_placeholder' => 'TR, SA, AE vb.',
                'extra_user_label' => 'User-Agent firma adı',
                'extra_user_placeholder' => 'Boşsa SelfIntegration kullanılır',
                'extra_password_label' => 'Ek şifre',
                'extra_password_placeholder' => 'Şimdilik kullanılmıyor',
                'store_url_label' => 'Mağaza / panel URL',
                'store_url_placeholder' => 'https://partner.trendyol.com',
                'hints' => [
                    'Seller ID, API key ve API secret değerlerini Satıcı Paneli > Hesap Bilgilerim > Entegrasyon Bilgileri ekranından alın.',
                    'Trendyol istekleri Basic Auth ile çalışır; satıcı ID mağaza formunda, API key ve secret bağlantı formunda tutulur.',
                    'StoreFrontCode alanı varsa Trendyol isteklerinde header olarak gönderilir; çoğu Türkiye mağazasında boş bırakılabilir.',
                    'User-Agent başlığı otomatik gönderilir; bu alanı doldurursanız "SellerId - FirmaAdi" formatında kullanılır, boşsa SelfIntegration seçilir.',
                    'Sipariş senkronu yeni stream endpoint ile cursor tabanlı çalışır ve sistem büyük taramalarda bunu tercih eder.',
                ],
            ],
            'hepsiburada' => [
                'default_auth_type' => 'merchant_id_service_key',
                'seller_id_label' => 'Satıcı ID',
                'seller_id_placeholder' => 'Hepsiburada merchantId',
                'api_base_url_label' => 'OMS API URL',
                'api_base_url_placeholder' => config('marketplace.hepsiburada.oms_base_url'),
                'api_key_label' => 'Servis Anahtarı',
                'api_key_placeholder' => 'Hepsiburada servis anahtarı',
                'api_secret_label' => 'Eski gizli anahtar',
                'api_secret_placeholder' => 'Opsiyonel eski gizli anahtar',
                'store_front_code_label' => 'Ek mağaza kodu',
                'store_front_code_placeholder' => 'Opsiyonel',
                'extra_user_label' => 'User-Agent / entegratör kullanıcı',
                'extra_user_placeholder' => 'SelfIntegration, ZOLM veya yetkili entegratör adı',
                'extra_password_label' => 'Eski şifre',
                'extra_password_placeholder' => 'Opsiyonel eski şifre',
                'store_url_label' => 'Mağaza / panel URL',
                'store_url_placeholder' => 'https://merchant.hepsiburada.com',
                'hints' => [
                    'Satıcı ID alanına Hepsiburada merchantId girilir.',
                    'API anahtarı alanı servis anahtarı olarak kullanılır; kimlik doğrulama burada merchantId + serviceKey ile kurulur.',
                    'User-Agent / entegratör kullanıcı alanı Hepsiburada tarafından yetki kontrolünde kullanılır.',
                    'Prapazar gibi başka entegratörden alınan anahtarlar yalnız o entegratör User-Agent adıyla yetkili olabilir; bilmiyorsanız Hepsiburada destekten ZOLM veya SelfIntegration için yetki isteyin.',
                    'Fiyat ve stok gönderimi XML toplu paket olarak gider. Aynı anda bekleyen güncelleme sayısının 5’i geçmemesi önerilir.',
                    'Paket operasyonlarında şu an yalnız resmi ortak barkod etiket servisi aktif. Paket durumu ve fatura linki daha sonra açılacaktır.',
                ],
            ],
            'n11' => [
                'default_auth_type' => 'api_key_secret',
                'seller_id_label' => 'N11 mağaza / satıcı kodu',
                'seller_id_placeholder' => 'Opsiyonel satıcı kodu',
                'api_base_url_label' => 'N11 API URL',
                'api_base_url_placeholder' => 'https://api.n11.com',
                'api_key_label' => 'API Anahtarı',
                'api_key_placeholder' => 'N11 API anahtarı',
                'api_secret_label' => 'API Gizli Anahtarı',
                'api_secret_placeholder' => 'N11 API gizli anahtarı',
                'store_front_code_label' => 'Ek mağaza kodu',
                'store_front_code_placeholder' => 'Şimdilik kullanılmıyor',
                'extra_user_label' => 'Ek kullanıcı',
                'extra_user_placeholder' => 'Şimdilik kullanılmıyor',
                'extra_password_label' => 'Ek şifre',
                'extra_password_placeholder' => 'Şimdilik kullanılmıyor',
                'store_url_label' => 'Mağaza / panel URL',
                'store_url_placeholder' => 'https://www.n11.com/magaza/ornek',
                'hints' => [
                    'N11 REST servisleri https://api.n11.com altında çalışır ve isteklerde appkey / appsecret header’ları kullanılır.',
                    'Bu iterasyonda sipariş listeleme, satıcı ürün sorgulama ve fiyat-stok görev kuyruğu akışları aktiftir.',
                    'Fiyat ve stok güncelleme servisi task mantığı ile çalışır; başarılı response sonrası task ID döner ve işleme kuyruğa alınır.',
                    'Finans ve webhook akışları N11 tarafında bu connector içinde henüz aktif değildir; bu ayarlar pasif tutulur.',
                ],
            ],
            'koctas' => [
                'default_auth_type' => 'api_key_secret',
                'seller_id_label' => 'Koçtaş shop / mağaza kodu (opsiyonel)',
                'seller_id_placeholder' => 'Boş bırakabilirsiniz',
                'seller_id_help' => 'Koçtaş panelinde yalnız API anahtarı varsa bu alanı boş bırakın; bağlantı API key ile kurulur.',
                'seller_id_empty_label' => 'Shop ID opsiyonel',
                'api_base_url_label' => 'Koçtaş API URL',
                'api_base_url_placeholder' => 'https://koctas.mirakl.net',
                'api_key_label' => 'API Anahtarı',
                'api_key_placeholder' => 'Koçtaş API anahtarı',
                'api_secret_label' => 'API Gizli Anahtarı (opsiyonel)',
                'api_secret_placeholder' => 'Boş bırakabilirsiniz',
                'store_front_code_label' => 'Ek mağaza kodu',
                'store_front_code_placeholder' => 'Şimdilik kullanılmıyor',
                'extra_user_label' => 'Ek kullanıcı',
                'extra_user_placeholder' => 'Şimdilik kullanılmıyor',
                'extra_password_label' => 'Ek şifre',
                'extra_password_placeholder' => 'Şimdilik kullanılmıyor',
                'store_url_label' => 'Mağaza / panel URL',
                'store_url_placeholder' => 'https://koctas.mirakl.net',
                'hints' => [
                    'Koçtaş satıcı paneli Mirakl tabanlı çalışır; varsayılan API URL olarak https://koctas.mirakl.net kullanılır.',
                    'Panelde yalnız API anahtarı görünüyorsa mağaza/shop ID ve secret alanlarını boş bırakabilirsiniz.',
                    'Bağlantı doğrulama /api/account çağrısı ile yapılır ve Authorization header içinde API key kullanılır.',
                    'Bu iterasyonda sipariş, offer bazlı ürün listesi, fiyat push ve stok push akışları açıktır.',
                    'Finans ve webhook akışları Koçtaş tarafında henüz aktif değildir; bu ayarlar otomatik pasif tutulur.',
                ],
            ],
            'pazarama' => [
                'default_auth_type' => 'api_key_secret',
                'seller_id_label' => 'Pazarama mağaza / satıcı kodu',
                'seller_id_placeholder' => 'Opsiyonel satıcı kodu',
                'api_base_url_label' => 'Pazarama API URL',
                'api_base_url_placeholder' => 'https://isortagimapi.pazarama.com',
                'api_key_label' => 'API Anahtarı',
                'api_key_placeholder' => 'Pazarama API anahtarı',
                'api_secret_label' => 'API Gizli Anahtarı',
                'api_secret_placeholder' => 'Pazarama API gizli anahtarı',
                'store_front_code_label' => 'Ek mağaza kodu',
                'store_front_code_placeholder' => 'Şimdilik kullanılmıyor',
                'extra_user_label' => 'Ek kullanıcı',
                'extra_user_placeholder' => 'Şimdilik kullanılmıyor',
                'extra_password_label' => 'Ek şifre',
                'extra_password_placeholder' => 'Şimdilik kullanılmıyor',
                'store_url_label' => 'Mağaza / panel URL',
                'store_url_placeholder' => 'https://www.pazarama.com/magaza/ornek',
                'hints' => [
                    'Pazarama Satıcı Paneli > Entegrasyon Bilgileri alanındaki API anahtarlarınızı kullanın.',
                    'API Key alanına Pazarama "API Key" değerini girin.',
                    'API Secret alanına Pazarama "API Secret" değerini girin.',
                    'API URL alanı merchant API adresidir; token adresi olan isortagimgiris.pazarama.com/connect/token buraya yazılmamalıdır.',
                    'Bağlantı doğrulandıktan sonra Sipariş ve Ürün akışlarını aktif edebilirsiniz.',
                ],
            ],
            'amazon' => [
                'default_auth_type' => 'api_key_secret',
                'seller_id_label' => 'Amazon seller / merchant kodu',
                'seller_id_placeholder' => 'Opsiyonel merchant id',
                'api_base_url_label' => 'Amazon API URL',
                'api_base_url_placeholder' => 'Region endpoint onaylandığında doldurulacak',
                'api_key_label' => 'API Anahtarı / Erişim Anahtarı',
                'api_key_placeholder' => 'Amazon erişim anahtarı',
                'api_secret_label' => 'API Gizli Anahtarı',
                'api_secret_placeholder' => 'Amazon API gizli anahtarı',
                'store_front_code_label' => 'Marketplace / region kodu',
                'store_front_code_placeholder' => 'Opsiyonel TR marketplace code',
                'extra_user_label' => 'Ek kullanıcı / role bilgisi',
                'extra_user_placeholder' => 'Şimdilik kullanılmıyor',
                'extra_password_label' => 'Ek şifre / token',
                'extra_password_placeholder' => 'Şimdilik kullanılmıyor',
                'store_url_label' => 'Mağaza / panel URL',
                'store_url_placeholder' => 'https://sellercentral.amazon.com.tr',
                'hints' => [
                    'Amazon için alan yapısı şimdilik güvenli iskelet modunda tutuluyor; erişim anahtarı ve gizli anahtar saklanabilir.',
                    'SP-API bölge, rol ve kimlik doğrulama akışı netleşmeden bağlantı doğrulama bilerek başarısız döner.',
                    'Bu nedenle yetenek rozetleri pasif görünür; mağaza kaydı ve kimlik bilgisi hazırlığı için kullanılır.',
                    'Canlı ön test açılmadan önce resmi Amazon SP-API dokümanı ve gerçek kimlik bilgileri ile alan eşleme sıkılaştırılacaktır.',
                ],
            ],
            'ciceksepeti' => [
                'default_auth_type' => 'api_key_secret',
                'seller_id_label' => 'Çiçeksepeti mağaza / satıcı kodu',
                'seller_id_placeholder' => 'User-Agent için zorunlu satıcı kodu',
                'api_base_url_label' => 'Çiçeksepeti API URL',
                'api_base_url_placeholder' => 'https://apis.ciceksepeti.com/api/v1',
                'api_key_label' => 'API Anahtarı',
                'api_key_placeholder' => 'Çiçeksepeti API anahtarı',
                'api_secret_label' => 'Ek gizli alan (opsiyonel)',
                'api_secret_placeholder' => 'Çoğu mağazada boş bırakılır',
                'store_front_code_label' => 'Ek mağaza kodu',
                'store_front_code_placeholder' => 'Şimdilik kullanılmıyor',
                'extra_user_label' => 'Ek kullanıcı',
                'extra_user_placeholder' => 'Şimdilik kullanılmıyor',
                'extra_password_label' => 'Ek şifre',
                'extra_password_placeholder' => 'Şimdilik kullanılmıyor',
                'store_url_label' => 'Mağaza / panel URL',
                'store_url_placeholder' => 'https://www.ciceksepeti.com/merchant',
                'hints' => [
                    'Resmi Çiçeksepeti akışında istekler x-api-key ve user-agent ile yetkilendirilir; ayrı bir API secret çoğu mağazada gerekmez.',
                    'Satıcı panelindeki Satıcı ID bilgisi user-agent içinde kullanılır; entegratör adı panelde tanımlıysa Çiçeksepeti tarafında ayrıca görünür.',
                    'Canlı prod URL varsayılan olarak https://apis.ciceksepeti.com/api/v1 kabul edilir; kök URL girilirse ZOLM bunu otomatik tamamlar.',
                    'API bilgileri panelde görünmüyorsa satıcı panelinden telefon doğrulaması yapın veya Destek > Konuşma Başlat > API Entegrasyon Süreçleri üzerinden talep açın.',
                ],
            ],
            'woocommerce' => [
                'default_auth_type' => 'consumer_key_secret',
                'seller_id_label' => 'Site / mağaza kimliği',
                'seller_id_placeholder' => 'Opsiyonel iç mağaza kodu',
                'api_base_url_label' => 'Mağaza URL / API URL',
                'api_base_url_placeholder' => 'https://magaza.example.com',
                'api_key_label' => 'Tüketici Anahtarı',
                'api_key_placeholder' => 'ck_...',
                'api_secret_label' => 'Tüketici Gizli Anahtarı',
                'api_secret_placeholder' => 'cs_...',
                'store_front_code_label' => 'Ek mağaza vitrini kodu',
                'store_front_code_placeholder' => 'Opsiyonel',
                'extra_user_label' => 'Ek kullanıcı',
                'extra_user_placeholder' => 'Şimdilik kullanılmıyor',
                'extra_password_label' => 'Ek şifre',
                'extra_password_placeholder' => 'Şimdilik kullanılmıyor',
                'store_url_label' => 'Site URL',
                'store_url_placeholder' => 'https://magaza.example.com',
                'hints' => [
                    'API anahtarı alanına WooCommerce tüketici anahtarı, gizli anahtar alanına tüketici gizli anahtarı girilir.',
                    'ZOLM Booster yorum eklentisi için WordPress > ZOLM Booster > Ayarlar ekranındaki ayrı API anahtarı kullanılır.',
                    'API URL alanına site kök URL’sini veya doğrudan /wp-json/wc/v3 temel URL’ini yazabilirsiniz.',
                    'WooCommerce tarafında finans servisi yok; sipariş ve ürün senkronu ile fiyat/stok gönderimi aktiftir.',
                    'Webhook doğrulaması için mağaza tarafında tanımlanan gizli anahtar ile ZOLM webhook gizli anahtarı aynı olmalıdır.',
                    'Düşük etkili önerilen profil: sipariş 15 dk, ürün 12 saat, finance kapalı, paralellik 1, jitter 15 sn.',
                    'Fiyat ve stok gönderimi küçük partiler halinde gönderilir; yine de ZOLM ana sistem değilse gönderim özelliklerini kapalı tutmanız daha güvenlidir.',
                ],
            ],
            'shopify' => [
                'default_auth_type' => 'access_token_app_secret',
                'seller_id_label' => 'Shopify mağaza kimliği',
                'seller_id_placeholder' => 'Opsiyonel mağaza kodu',
                'api_base_url_label' => 'Shopify mağaza URL / GraphQL API URL',
                'api_base_url_placeholder' => 'https://ornek.myshopify.com veya tam /admin/api/.../graphql.json URL',
                'api_key_label' => 'Admin API erişim anahtarı',
                'api_key_placeholder' => 'shpat_...',
                'api_secret_label' => 'Uygulama gizli anahtarı',
                'api_secret_placeholder' => 'Shopify uygulama gizli anahtarı',
                'store_front_code_label' => 'Ek mağaza vitrini kodu',
                'store_front_code_placeholder' => 'Şimdilik kullanılmıyor',
                'extra_user_label' => 'Ek kullanıcı',
                'extra_user_placeholder' => 'Şimdilik kullanılmıyor',
                'extra_password_label' => 'Ek şifre',
                'extra_password_placeholder' => 'Şimdilik kullanılmıyor',
                'store_url_label' => 'Site URL',
                'store_url_placeholder' => 'https://ornek.myshopify.com',
                'hints' => [
                    'Shopify için API anahtarı alanına Admin API erişim anahtarı, API gizli anahtarı alanına uygulama gizli anahtarı girilir.',
                    'API URL alanına mağaza kök URL’si yazılabilir; sistem bunu sürümlü GraphQL Admin uç noktasına tamamlar.',
                    'Webhook HMAC doğrulaması için webhook secret alanına app secret key ile aynı değer girilmesi önerilir.',
                    'Bu iterasyonda bağlantı doğrulama, webhook doğrulama, sipariş, ürün, finans ve fiyat/stok gönderimi hazırdır.',
                    'Shopify finans akışı ödeme ve gateway işlem kayıtlarını çeker; pazaryeri tipi hakediş mutabakatı beklenmemelidir.',
                ],
            ],
            default => [
                'default_auth_type' => 'api_key_secret',
                'seller_id_label' => 'Satıcı ID',
                'seller_id_placeholder' => 'Trendyol satıcı ID vb.',
                'api_base_url_label' => 'API URL',
                'api_base_url_placeholder' => 'https://...',
                'api_key_label' => 'API anahtarı',
                'api_key_placeholder' => 'API anahtarı',
                'api_secret_label' => 'API gizli anahtarı',
                'api_secret_placeholder' => 'Boş bırakırsan mevcut korunur',
                'store_front_code_label' => 'Mağaza vitrini kodu',
                'store_front_code_placeholder' => 'Opsiyonel mağaza vitrini kodu',
                'extra_user_label' => 'Ek kullanıcı',
                'extra_user_placeholder' => 'Opsiyonel kullanıcı',
                'extra_password_label' => 'Ek şifre',
                'extra_password_placeholder' => 'Boş bırakırsan mevcut korunur',
                'store_url_label' => 'Mağaza URL / site URL',
                'store_url_placeholder' => 'https://magaza.example.com',
                'hints' => [
                    'API key ve secret alanları kanalın kimlik bilgileri için kullanılır.',
                    'Ek kullanıcı ve mağaza URL alanları kanalın özel ihtiyaçları için saklanır.',
                ],
            ],
        };
    }

    public function render()
    {
        $user = Auth::user();

        $legalEntities = $user->legalEntities()
            ->withCount('stores')
            ->orderBy('name')
            ->get();

        $stores = app(\App\Services\Marketplace\MarketplaceStoreAccessResolver::class)
            ->accessibleStores($user)
            ->with(['legalEntity', 'connection', 'syncProfile'])
            ->latest('updated_at')
            ->get();

        $selectedStore = $this->selectedStore;

        $recentSyncRuns = $selectedStore
            ? $selectedStore->syncRuns()->where('trigger_type', '!=', 'smoke_test')->latest('created_at')->limit(5)->get()
            : collect();

        $recentSmokeRuns = $selectedStore
            ? $selectedStore->syncRuns()->where('trigger_type', 'smoke_test')->latest('created_at')->limit(4)->get()
            : collect();

        $recentWebhookEvents = $selectedStore
            ? $selectedStore->webhookEvents()->latest('created_at')->limit(5)->get()
            : collect();

        $stats = [
            'entities' => $legalEntities->where('is_active', true)->count(),
            'stores' => $stores->where('is_active', true)->count(),
            'configured' => $stores->filter(fn (MarketplaceStore $store) => in_array($store->connection?->status, ['configured', 'demo'], true))->count(),
            'webhookEnabled' => $stores->filter(fn (MarketplaceStore $store) => $store->syncProfile?->webhook_enabled)->count(),
            'smokeReady' => (int) data_get($this->readinessSummary, 'totals.ready', 0),
            'needsAttention' => (int) data_get($this->readinessSummary, 'totals.warning', 0) + (int) data_get($this->readinessSummary, 'totals.missing', 0),
        ];

        return view('livewire.marketplace-integrations', [
            'backfillOptions' => MarketplaceProviderRegistry::backfillOptions(),
            'legalEntities' => $legalEntities,
            'providerCatalog' => MarketplaceProviderRegistry::providers(),
            'recentSyncRuns' => $recentSyncRuns,
            'recentSmokeRuns' => $recentSmokeRuns,
            'recentWebhookEvents' => $recentWebhookEvents,
            'selectedStore' => $selectedStore,
            'stats' => $stats,
            'stores' => $stores,
        ])->layout('layouts.app', [
            'title' => 'Pazaryeri Entegrasyonları',
        ]);
    }

    protected function loadSelectedStore(): void
    {
        $store = $this->selectedStore;
        $this->legacyProjectionPreview = [];

        if (!$store) {
            $this->resetStoreForm();
            $this->resetConnectionForm();
            $this->resetSyncForm();

            return;
        }

        $store->loadMissing(['connection', 'syncProfile']);

        $this->storeForm = [
            'legalEntityId' => $store->legal_entity_id,
            'marketplace' => $store->marketplace,
            'storeName' => $store->store_name,
            'storeCode' => $store->store_code,
            'sellerId' => $store->seller_id,
            'timezone' => $store->timezone ?: 'Europe/Istanbul',
            'currency' => $store->currency ?: 'TRY',
            'isActive' => (bool) $store->is_active,
        ];

        $credentials = $store->connection?->credentials_encrypted ?? [];

        $this->connectionForm = [
            'authType' => $store->connection?->auth_type ?? 'api_key_secret',
            'apiBaseUrl' => $store->connection?->api_base_url ?: MarketplaceProviderRegistry::defaultApiBaseUrl($store->marketplace),
            'webhookSecret' => $store->connection?->webhook_secret ?: Str::random(40),
            'apiKey' => $credentials['api_key'] ?? '',
            'apiSecret' => filled($credentials['api_secret'] ?? null) ? '********' : '',
            'zolmBoosterApiKey' => filled($credentials['zolm_booster_api_key'] ?? null) ? '********' : '',
            'storeFrontCode' => $credentials['store_front_code'] ?? '',
            'extraUser' => $credentials['extra_user'] ?? '',
            'extraPassword' => filled($credentials['extra_password'] ?? null) ? '********' : '',
            'storeUrl' => $credentials['store_url'] ?? '',
        ];

        $syncProfile = $store->syncProfile;
        $defaults = IntegrationSyncProfile::defaultsForMarketplace($store->marketplace);

        $this->syncForm = [
            'ordersPollMinutes' => $syncProfile?->orders_poll_minutes ?? $defaults['orders_poll_minutes'],
            'financePollMinutes' => $syncProfile?->finance_poll_minutes ?? $defaults['finance_poll_minutes'],
            'productsPollMinutes' => $syncProfile?->products_poll_minutes ?? $defaults['products_poll_minutes'],
            'claimsPollMinutes' => $syncProfile?->claims_poll_minutes ?? $defaults['claims_poll_minutes'],
            'questionsPollMinutes' => $syncProfile?->questions_poll_minutes ?? $defaults['questions_poll_minutes'],
            'backfillMode' => $syncProfile?->backfill_mode ?? $defaults['backfill_mode'],
            'backfillCustomFrom' => optional($syncProfile?->backfill_custom_from)->format('Y-m-d\TH:i'),
            'backfillCustomTo' => optional($syncProfile?->backfill_custom_to)->format('Y-m-d\TH:i'),
            'ordersEnabled' => (bool) ($syncProfile?->orders_enabled ?? $defaults['orders_enabled']),
            'financeEnabled' => (bool) ($syncProfile?->finance_enabled ?? $defaults['finance_enabled']),
            'productsEnabled' => (bool) ($syncProfile?->products_enabled ?? $defaults['products_enabled']),
            'claimsEnabled' => (bool) ($syncProfile?->claims_enabled ?? $defaults['claims_enabled']),
            'questionsEnabled' => (bool) ($syncProfile?->questions_enabled ?? $defaults['questions_enabled']),
            'webhookEnabled' => (bool) ($syncProfile?->webhook_enabled ?? $defaults['webhook_enabled']),
            'pricePushEnabled' => (bool) ($syncProfile?->price_push_enabled ?? $defaults['price_push_enabled']),
            'stockPushEnabled' => (bool) ($syncProfile?->stock_push_enabled ?? $defaults['stock_push_enabled']),
            'autoMatchEnabled' => $syncProfile?->auto_match_enabled !== null
                ? (bool) $syncProfile->auto_match_enabled
                : app(MpSettingsService::class)->getAutoRunMatchingOnSync(),
            'barcodeFallbackEnabled' => (bool) ($syncProfile?->barcode_fallback_enabled ?? $defaults['barcode_fallback_enabled']),
            'strictUniqueMatchEnabled' => (bool) ($syncProfile?->strict_unique_match_enabled ?? $defaults['strict_unique_match_enabled']),
            'nightlyRepairSyncEnabled' => (bool) ($syncProfile?->nightly_repair_sync_enabled ?? $defaults['nightly_repair_sync_enabled']),
            'maxParallelJobs' => $syncProfile?->max_parallel_jobs ?? $defaults['max_parallel_jobs'],
            'requestJitterSeconds' => $syncProfile?->request_jitter_seconds ?? $defaults['request_jitter_seconds'],
            'webhookTopics' => Arr::wrap(data_get($syncProfile?->extra_settings ?? [], 'webhook_topics', data_get($defaults, 'extra_settings.webhook_topics', []))),
        ];
    }

    protected function resetEntityForm(): void
    {
        $this->entityForm = [
            'name' => '',
            'taxNumber' => '',
            'taxOffice' => '',
            'mersisNumber' => '',
            'companyType' => 'limited',
            'phone' => '',
            'email' => '',
            'address' => '',
            'iban' => '',
            'bankName' => '',
            'currency' => 'TRY',
            'isActive' => true,
        ];
    }

    protected function resetStoreForm(): void
    {
        $firstEntityId = Auth::check()
            ? Auth::user()->legalEntities()->orderBy('name')->value('id')
            : null;

        $this->storeForm = [
            'legalEntityId' => $firstEntityId,
            'marketplace' => 'trendyol',
            'storeName' => '',
            'storeCode' => '',
            'sellerId' => '',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'isActive' => true,
        ];
    }

    public function updatedStoreFormMarketplace(string $marketplace): void
    {
        if ($this->selectedStoreId) {
            return;
        }

        $guide = $this->getSelectedConnectionGuideProperty();

        $this->connectionForm['authType'] = $guide['default_auth_type'] ?? 'api_key_secret';
        $this->connectionForm['apiBaseUrl'] = MarketplaceProviderRegistry::defaultApiBaseUrl($marketplace);
        $this->resetSyncForm();
    }

    protected function resetConnectionForm(): void
    {
        $guide = $this->getSelectedConnectionGuideProperty();

        $this->connectionForm = [
            'authType' => $guide['default_auth_type'] ?? 'api_key_secret',
            'apiBaseUrl' => MarketplaceProviderRegistry::defaultApiBaseUrl($this->storeForm['marketplace'] ?? 'trendyol'),
            'webhookSecret' => Str::random(40),
            'apiKey' => '',
            'apiSecret' => '',
            'zolmBoosterApiKey' => '',
            'storeFrontCode' => '',
            'extraUser' => '',
            'extraPassword' => '',
            'storeUrl' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array<string, mixed>
     */
    protected function inspectConnectionDraft(MarketplaceStore $store, array $credentials, ?string $apiBaseUrl): array
    {
        $draftStore = clone $store;
        $draftConnection = $store->connection ? clone $store->connection : new IntegrationConnection();

        $draftConnection->provider = $store->marketplace;
        $draftConnection->auth_type = $this->connectionForm['authType'] ?? ($store->connection?->auth_type ?? 'api_key_secret');
        $draftConnection->credentials_encrypted = $credentials;
        $draftConnection->api_base_url = $apiBaseUrl ?: MarketplaceProviderRegistry::defaultApiBaseUrl($store->marketplace);
        $draftConnection->last_verified_at = null;
        $draftConnection->last_error = null;

        $draftStore->setRelation('connection', $draftConnection);

        return app(MarketplaceConnectionReadinessService::class)->inspect($draftStore);
    }

    protected function resetSyncForm(): void
    {
        $defaults = IntegrationSyncProfile::defaultsForMarketplace($this->storeForm['marketplace'] ?? 'trendyol');

        $this->syncForm = [
            'ordersPollMinutes' => $defaults['orders_poll_minutes'],
            'financePollMinutes' => $defaults['finance_poll_minutes'],
            'productsPollMinutes' => $defaults['products_poll_minutes'],
            'claimsPollMinutes' => $defaults['claims_poll_minutes'],
            'questionsPollMinutes' => $defaults['questions_poll_minutes'],
            'backfillMode' => $defaults['backfill_mode'],
            'backfillCustomFrom' => '',
            'backfillCustomTo' => '',
            'ordersEnabled' => $defaults['orders_enabled'],
            'financeEnabled' => $defaults['finance_enabled'],
            'productsEnabled' => $defaults['products_enabled'],
            'claimsEnabled' => $defaults['claims_enabled'],
            'questionsEnabled' => $defaults['questions_enabled'],
            'webhookEnabled' => $defaults['webhook_enabled'],
            'pricePushEnabled' => $defaults['price_push_enabled'],
            'stockPushEnabled' => $defaults['stock_push_enabled'],
            'autoMatchEnabled' => app(MpSettingsService::class)->getAutoRunMatchingOnSync(),
            'barcodeFallbackEnabled' => $defaults['barcode_fallback_enabled'],
            'strictUniqueMatchEnabled' => $defaults['strict_unique_match_enabled'],
            'nightlyRepairSyncEnabled' => $defaults['nightly_repair_sync_enabled'],
            'maxParallelJobs' => $defaults['max_parallel_jobs'],
            'requestJitterSeconds' => $defaults['request_jitter_seconds'],
            'webhookTopics' => Arr::wrap(data_get($defaults, 'extra_settings.webhook_topics', [])),
        ];
    }

    protected function notify(string $message, string $type = 'success'): void
    {
        $this->flashMessage = $message;
        $this->flashMessageType = $type;
    }

    /**
     * @param  array<string, mixed>  $form
     * @param  array<string, bool>  $capabilities
     * @return array{0: array<string, bool>, 1: array<int, string>}
     */
    protected function normalizeSyncFeatureToggles(array $form, array $capabilities): array
    {
        $map = [
            'ordersEnabled' => ['capability' => 'orders', 'label' => 'Sipariş sync'],
            'financeEnabled' => ['capability' => 'finance', 'label' => 'Finans sync'],
            'productsEnabled' => ['capability' => 'products', 'label' => 'Ürün sync'],
            'claimsEnabled' => ['capability' => 'claims', 'label' => 'İade sync'],
            'questionsEnabled' => ['capability' => 'questions', 'label' => 'Soru sync'],
            'webhookEnabled' => ['capability' => 'webhooks', 'label' => 'Webhook'],
            'pricePushEnabled' => ['capability' => 'price_push', 'label' => 'Fiyat gönderimi'],
            'stockPushEnabled' => ['capability' => 'stock_push', 'label' => 'Stok gönderimi'],
        ];

        $normalized = [];
        $forcedOff = [];

        foreach ($map as $formKey => $definition) {
            $enabled = (bool) ($form[$formKey] ?? false);
            $supported = (bool) ($capabilities[$definition['capability']] ?? false);

            $normalized[$formKey] = $enabled && $supported;

            if ($enabled && !$supported) {
                $forcedOff[] = $definition['label'];
            }
        }

        return [$normalized, $forcedOff];
    }

    protected function syncTypeLabel(?string $syncType): string
    {
        return match ($syncType) {
            'orders' => 'Sipariş',
            'products' => 'Ürün',
            'finance' => 'Finans',
            'claims' => 'İade',
            'webhook_refresh' => 'Webhook yenileme',
            default => Str::headline((string) $syncType),
        };
    }

    protected function readinessStateLabel(string $state): string
    {
        return match ($state) {
            'ready' => 'Hazır',
            'warning' => 'Kontrol et',
            default => 'Eksik',
        };
    }

    public function guidanceSeverityTone(?string $severity): string
    {
        return match ($severity) {
            'critical' => 'danger',
            'warning' => 'warning',
            'info' => 'info',
            default => 'default',
        };
    }

    public function guidanceSeverityLabel(?string $severity): string
    {
        return match ($severity) {
            'critical' => 'Kritik',
            'warning' => 'Uyarı',
            'info' => 'Bilgi',
            default => Str::headline((string) $severity),
        };
    }

    public function guidanceRouteLabel(?string $route): string
    {
        return match ($route) {
            'mp.matching' => 'Eşleştirme Merkezi',
            'mp.integrations' => 'Entegrasyonlar',
            'mp.finance' => 'Finans',
            'mp.products' => 'Ürünler',
            'mp.orders' => 'Siparişler',
            default => 'Kontrol Merkezi',
        };
    }

    /**
     * @return array<int, string>
     */
    public function selectedStoreRecommendedWebhookTopics(): array
    {
        if (!$this->selectedStore) {
            return [];
        }

        return IntegrationSyncProfile::recommendedWebhookTopicsForMarketplace($this->selectedStore->marketplace);
    }

    public function selectedStoreWebhookTopicPresetLabel(): string
    {
        return match ($this->selectedStore?->marketplace) {
            'shopify' => 'Önerilen Shopify webhook topic seti',
            default => 'Önerilen webhook topic seti',
        };
    }

    public function selectedStoreWebhookTopicPresetHint(): string
    {
        return match ($this->selectedStore?->marketplace) {
            'shopify' => 'Yalnız sipariş, iade, ürün ve stok değişimlerini dinleyin. Fazla topic açmak hem mağazayı hem de ZOLM queue tarafını gereksiz yorar.',
            default => 'Yalnız sipariş ve ürün değişimlerini dinleyin. Kupon, müşteri ve benzeri topic’ler gereksiz yük üretebilir.',
        };
    }

    public function selectedStoreWebhookTopicPresetNote(): string
    {
        return match ($this->selectedStore?->marketplace) {
            'shopify' => 'Bu liste dışındaki Shopify webhook’ları işlense bile senkron başlatılmaz; olay kaydı filtrelenmiş olarak loglanır.',
            default => 'Bu liste dışındaki WooCommerce webhook’ları işlense bile senkron başlatılmaz; olay kaydı filtrelenmiş olarak loglanır.',
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function guidanceRoute(array $item): string
    {
        $route = (string) ($item['route'] ?? 'mp.integrations');
        $storeId = $item['store_id'] ?? null;
        $marketplace = $item['marketplace'] ?? null;

        return match ($route) {
            'mp.matching' => route('mp.matching', array_filter([
                'storeFilter' => $storeId,
                'statusFilter' => 'pending',
            ], fn ($value) => $value !== null && $value !== '')),
            'mp.finance' => route('mp.finance', array_filter([
                'storeFilter' => $storeId,
            ], fn ($value) => $value !== null && $value !== '')),
            'mp.orders' => route('mp.orders', array_filter([
                'storeFilter' => $storeId,
            ], fn ($value) => $value !== null && $value !== '')),
            'mp.products' => route('mp.products', array_filter([
                'marketplaceFilter' => $marketplace,
            ], fn ($value) => $value !== null && $value !== '' && $value !== 'all')),
            default => route('mp.integrations', array_filter([
                'store' => $storeId,
            ], fn ($value) => $value !== null && $value !== '')),
        };
    }

    public function selectedGuidanceFocusLabel(): string
    {
        $topItem = $this->selectedStoreGuidanceItems[0] ?? null;

        if (!$topItem) {
            return 'Aksiyon yok';
        }

        return match ($topItem['route'] ?? null) {
            'mp.finance' => 'Finansa git',
            'mp.orders' => 'Siparişlere git',
            'mp.matching' => 'Eşleştirmeye git',
            'mp.products' => 'Ürünlere git',
            default => 'Detayı aç',
        };
    }

    public function selectedGuidanceSyncLabel(): string
    {
        $topItem = $this->selectedStoreGuidanceItems[0] ?? null;

        return match ($this->resolveGuidanceSyncType($topItem)) {
            'finance' => 'Finans senkronunu başlat',
            'products' => 'Ürün senkronunu başlat',
            'legacy_projection' => 'Yansıtma ekranına git',
            default => 'Sipariş senkronunu başlat',
        };
    }

    public function focusSelectedStoreGuidance()
    {
        $topItem = $this->selectedStoreGuidanceItems[0] ?? null;

        if (!$topItem) {
            $this->notify('Seçili mağaza için odaklanacak bir tanı kaydı bulunamadı.', 'warning');

            return null;
        }

        return $this->redirect($this->guidanceRoute($topItem), navigate: true);
    }

    public function syncSelectedStoreGuidance(): void
    {
        $topItem = $this->selectedStoreGuidanceItems[0] ?? null;
        $store = $this->selectedStore;

        if (!$topItem || !$store) {
            $this->notify('Seçili mağaza için senkron başlatacak bir tanı kaydı bulunamadı.', 'warning');

            return;
        }

        $store->loadMissing('connection');

        $readiness = $this->selectedConnectionReadiness;

        if (!$store->connection || !($readiness['is_ready'] ?? false)) {
            $this->notify(
                'Önce seçili mağazanın bağlantı bilgilerini tamamlayın: ' . ($readiness['failures'][0] ?? 'Eksik zorunlu alanlar var.'),
                'warning',
            );

            return;
        }

        $syncType = $this->resolveGuidanceSyncType($topItem);

        if ($syncType === 'legacy_projection') {
            $this->redirect($this->guidanceRoute($topItem), navigate: true);

            return;
        }

        try {
            $result = app(MarketplaceManualSyncDispatchService::class)->dispatch($store, $syncType, [
                'options' => [],
                'source' => 'guidance_shortcut',
                'category' => $topItem['category'] ?? null,
                'origin_screen' => 'integrations',
            ]);

            $feedback = app(MarketplaceManualSyncDispatchService::class)->feedback(
                $result,
                $this->syncTypeLabel($syncType),
                $store->store_name,
            );

            $this->notify($feedback['message'], $feedback['tone']);
        } catch (\Throwable $exception) {
            $this->notify('Senkron kuyruğa alınamadı: ' . $exception->getMessage(), 'error');
        }
    }

    protected function cleanExportString(mixed $value): mixed
    {
        return app(\App\Services\ExcelService::class)->cleanString($value);
    }

    /**
     * @param  array<string, mixed>|null  $item
     */
    protected function resolveGuidanceSyncType(?array $item): string
    {
        return match ($item['category'] ?? null) {
            'finance_mapping' => 'finance',
            'product_matching', 'listing_completeness' => 'products',
            'legacy_financial_projection' => 'legacy_projection',
            default => 'orders',
        };
    }

    protected function buildWebhookUrl(MarketplaceStore $store): string
    {
        return route('marketplace.webhooks.receive', [
            'provider' => $store->marketplace,
            'store' => $store->id,
        ]);
    }
}
