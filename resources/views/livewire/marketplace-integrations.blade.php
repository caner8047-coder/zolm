@php
    $inputClass = 'w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-slate-900 focus:outline-none';
    $labelClass = 'mb-1 block text-sm font-medium text-slate-700';
    $toggleLabelClass = 'flex items-start gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm';
    $readinessLookup = collect($this->readinessSummary['rows'] ?? [])->keyBy('store_id');
    $selectedGuidanceItems = $this->selectedStoreGuidanceItems ?? [];
    $selectedGuidanceTopItem = $selectedGuidanceItems[0] ?? null;
    $selectedLegacyProjection = $this->selectedStoreLegacyProjection;
    $safeProfileStatus = $this->selectedStoreSafeProfileStatus;
    $safeProfileLabel = $selectedStore ? ($this->providerOptions[$selectedStore->marketplace] ?? ucfirst($selectedStore->marketplace)) : 'Mağaza';
    $recommendedWebhookTopics = collect($this->selectedStoreRecommendedWebhookTopics() ?? [])->filter()->values();
    $currentWebhookTopics = collect($syncForm['webhookTopics'] ?? [])->filter()->values();
    $hasWebhookTopicMismatch = $selectedStore
        && in_array($selectedStore->marketplace, ['woocommerce', 'shopify'], true)
        && ($syncForm['webhookEnabled'] ?? false)
        && $recommendedWebhookTopics->all() !== $currentWebhookTopics->all();
    $webhookTopicLabel = $selectedStore && $selectedStore->marketplace === 'shopify' ? 'Shopify' : 'WooCommerce';
@endphp

<div class="w-full">
    @if($flashMessage)
        <div class="mb-6 rounded-xl border px-4 py-3 text-sm {{ $flashMessageType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($flashMessageType === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-rose-200 bg-rose-50 text-rose-700') }}">
            {{ $flashMessage }}
        </div>
    @endif

    <x-zolm.risk-guidance :guidance="$this->riskGuidance" context-label="Entegrasyonlar" class="mb-6" />

    <div class="flex flex-col lg:flex-row gap-6">

        <!-- ============================== -->
        <!-- SOL PANEL: MASTER (Navigasyon) -->
        <!-- ============================== -->
        <div class="w-full lg:w-80 shrink-0 space-y-4" x-data="{ mode: '{{ $selectedStore ? 'store' : 'new_store' }}' }">

            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-slate-900">Entegrasyonlar</h2>
            </div>
            <p class="text-sm text-slate-500">Pazaryeri ve e-ticaret bağlantılarınızı buradan yönetin.</p>

            <div class="flex flex-col gap-2">
                <button type="button" wire:click="startNewStore" class="inline-flex w-full min-h-[40px] items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    Yeni Mağaza Ekle
                </button>
            </div>

            <!-- Mağaza Listesi -->
            <div class="mt-6 space-y-2">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Mağazalarım</h3>
                @forelse($stores as $store)
                    @php
                        $isSelected = $selectedStore && $selectedStore->id === $store->id;
                        $readinessState = data_get($readinessLookup, $store->id . '.state');
                        $connectionStatusTone = match ($readinessState) {
                            'ready' => 'success',
                            'warning' => 'warning',
                            'missing' => 'danger',
                            default => ($store->connection?->status === 'configured' ? 'success' : 'warning'),
                        };
                        $connectionStatusLabel = match ($readinessState) {
                            'ready' => 'Hazır',
                            'warning' => 'Uyarı',
                            'missing' => 'Eksik',
                            default => ($store->connection?->status === 'configured' ? 'Hazır' : 'Eksik'),
                        };
                    @endphp
                    <button type="button" wire:click="selectStore({{ $store->id }})" class="w-full text-left rounded-lg border p-3 transition {{ $isSelected ? 'border-slate-900 bg-white ring-1 ring-slate-900 shadow-sm' : 'border-slate-200 bg-slate-50/50 hover:border-slate-300 hover:bg-white' }}">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-slate-900">{{ $store->store_name }}</p>
                            <x-zolm.status-badge :tone="$connectionStatusTone">{{ $connectionStatusLabel }}</x-zolm.status-badge>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">{{ $this->providerOptions[$store->marketplace] ?? ucfirst($store->marketplace) }}</p>
                    </button>
                @empty
                    <div class="rounded-lg border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">
                        Kayıtlı mağaza yok.
                    </div>
                @endforelse
            </div>

            <!-- Firma Listesi -->
            <div class="mt-8 space-y-2 border-t border-slate-200 pt-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Firmalarım</h3>
                </div>
                <div class="max-h-48 overflow-y-auto space-y-2">
                    @forelse($legalEntities as $ent)
                        <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                            <p class="text-sm font-semibold text-slate-900">{{ $ent->name }}</p>
                            <p class="text-xs text-slate-500">VN: {{ $ent->tax_number }}</p>
                        </div>
                    @empty
                        <p class="text-xs text-slate-500">Firma yok.</p>
                    @endforelse
                </div>
                <!-- Mini inline form to create entity instead of a giant form -->
                <div x-data="{ openFirma: false }" class="pt-2">
                    <button type="button" @click="openFirma = !openFirma" class="text-xs font-medium text-slate-600 hover:text-slate-900">+ Yeni Firma Tanımla</button>
                    <div x-show="openFirma" x-cloak x-transition class="mt-3 space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <input type="text" wire:model.defer="entityForm.name" class="{{ $inputClass }} text-xs py-1.5" placeholder="Firma Ünvanı">
                        <input type="text" wire:model.defer="entityForm.taxNumber" class="{{ $inputClass }} text-xs py-1.5" placeholder="Vergi No">
                        <select wire:model.defer="entityForm.companyType" class="{{ $inputClass }} text-xs py-1.5">
                            <option value="">Şirket Tipi</option>
                            <option value="limited">Limited</option>
                            <option value="anonim">Anonim</option>
                            <option value="sahis">Şahıs</option>
                        </select>
                        <select wire:model.defer="entityForm.currency" class="{{ $inputClass }} text-xs py-1.5">
                            <option value="TRY">TRY</option>
                        </select>
                        <button type="button" wire:click="saveLegalEntity" class="w-full rounded bg-slate-900 py-1.5 text-xs text-white">Kaydet</button>
                    </div>
                </div>
            </div>

        </div>

        <!-- ============================== -->
        <!-- SAĞ PANEL: DETAIL (Çalışma)  -->
        <!-- ============================== -->
        <div class="w-full flex-1 min-w-0">
            @if(!$selectedStore)
                <!-- Yeni Mağaza Formu -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 lg:p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-900">Mağaza Oluştur</h3>
                    <p class="mt-1 text-sm text-slate-500">Pazaryeri ve API modelini seçerek yeni mağazanızı sisteme ekleyin.</p>

                    @if($legalEntities->isEmpty())
                        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
                            Lütfen sol panelden önce bir firma kaydedin. Mağaza oluşturabilmek için şirket profiline ihtiyaç vardır.
                        </div>
                    @else
                        <form wire:submit.prevent="saveStore" class="mt-6 space-y-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="col-span-1 sm:col-span-2">
                                    <label class="{{ $labelClass }}">Hangi firmanız bağlansın?</label>
                                    <select wire:model.defer="storeForm.legalEntityId" class="{{ $inputClass }}">
                                        <option value="">Firma Seçiniz</option>
                                        @foreach($legalEntities as $entity)
                                            <option value="{{ $entity->id }}">{{ $entity->name }} ({{ $entity->tax_number }})</option>
                                        @endforeach
                                    </select>
                                    @error('storeForm.legalEntityId') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-span-1">
                                    <label class="{{ $labelClass }}">Kanal (Pazaryeri vb.)</label>
                                    <select wire:model.live="storeForm.marketplace" class="{{ $inputClass }}">
                                        <option value="">Seçiniz</option>
                                        @foreach($this->providerOptions as $pKey => $pLabel)
                                            <option value="{{ $pKey }}">{{ $pLabel }}</option>
                                        @endforeach
                                    </select>
                                    @error('storeForm.marketplace') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-span-1">
                                    <label class="{{ $labelClass }}">ZOLM İçi Mağaza Adı</label>
                                    <input type="text" wire:model.defer="storeForm.storeName" class="{{ $inputClass }}" placeholder="Örn: Trendyol Merkez">
                                    @error('storeForm.storeName') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                                </div>
                                @if(!empty($this->selectedConnectionGuide))
                                <div class="col-span-1 sm:col-span-2">
                                    <label class="{{ $labelClass }}">{{ $this->selectedConnectionGuide['seller_id_label'] ?? 'Satıcı ID' }}</label>
                                    <input type="text" wire:model.defer="storeForm.sellerId" class="{{ $inputClass }}" placeholder="{{ $this->selectedConnectionGuide['seller_id_placeholder'] ?? '' }}">
                                    <p class="mt-1 text-xs text-slate-500">{{ $this->selectedConnectionGuide['seller_id_help'] ?? 'API entegrasyonu için pazaryerindeki resmi satıcı/mağaza kodunuzdur.' }}</p>
                                    @error('storeForm.sellerId') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                                </div>
                                @endif
                                <div class="col-span-1 hidden">
                                    <!-- Defaulting -->
                                    <input type="hidden" wire:model.defer="storeForm.timezone" value="Europe/Istanbul">
                                    <input type="hidden" wire:model.defer="storeForm.currency" value="TRY">
                                    <input type="hidden" wire:model.defer="storeForm.isActive" value="1">
                                </div>
                            </div>
                            <div class="flex justify-end pt-4 border-t border-slate-100">
                                <button type="submit" class="rounded-lg bg-slate-900 px-5 py-2 text-sm font-medium text-white transition hover:bg-slate-800">Mağazayı Kaydet</button>
                            </div>
                        </form>
                    @endif
                </div>

            @else
                <!-- Seçili Mağaza Detayları (Tabbed) -->
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm" x-data="{ currentTab: 'credentials' }">
                    <!-- Mağaza Başlık & Aksiyonları -->
                    <div class="border-b border-slate-200 p-5 lg:p-6 bg-slate-50/50 rounded-t-xl">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div>
                                <h3 class="text-xl font-bold text-slate-900">{{ $selectedStore->store_name }}</h3>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ $this->providerOptions[$selectedStore->marketplace] ?? ucfirst($selectedStore->marketplace) }}
                                    · {{ $selectedStore->seller_id ?: ($this->selectedConnectionGuide['seller_id_empty_label'] ?? 'Satıcı ID Bekleniyor') }}
                                </p>
                            </div>
                            <!-- Hızlı Test Aksiyonları (Sadece Configured ise) -->
                            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:justify-end">
                                <button type="button" wire:click="verifyConnection" class="inline-flex w-full items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50 sm:w-auto sm:px-3 sm:py-2">
                                    Bağlantıyı Doğrula
                                </button>
                                <button type="button" wire:click="runSync('products')" @disabled(!($this->selectedCapabilities['products'] ?? false)) class="inline-flex w-full items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50 sm:w-auto sm:px-3 sm:py-2">
                                    Ürünleri Çek
                                </button>
                                <button type="button" wire:click="runSync('orders')" @disabled(!($this->selectedCapabilities['orders'] ?? false)) class="inline-flex w-full items-center justify-center rounded-lg bg-slate-900 px-4 py-3 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50 sm:w-auto sm:px-3 sm:py-2">
                                    Siparişleri Çek
                                </button>
                                <button type="button" wire:click="runSync('questions')" @disabled(!($this->selectedCapabilities['questions'] ?? false)) class="inline-flex w-full items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50 sm:w-auto sm:px-3 sm:py-2">
                                    Soruları Çek
                                </button>
                                <button type="button" wire:click="runSync('claims')" @disabled(!($this->selectedCapabilities['claims'] ?? false)) class="inline-flex w-full items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50 sm:w-auto sm:px-3 sm:py-2">
                                    İadeleri Çek
                                </button>
                                <button type="button" wire:click="deleteSelectedStore" wire:confirm.prompt="DİKKAT: Bu mağazayı sildiğinizde mağazaya bağlı TÜM SİPARİŞ ve ÜRÜN kayıtları kalıcı olarak uçacaktır!\n\nİşleme onay vermek için kutuya ONAY yazın.|ONAY" class="inline-flex w-full items-center justify-center rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 hover:bg-rose-100 sm:w-auto sm:px-3 sm:py-2">
                                    Sil
                                </button>
                            </div>
                        </div>

                        <!-- Tab Navigasyon -->
                        <div class="mt-6 flex space-x-1 rounded-lg bg-slate-200/50 p-1 w-full overflow-x-auto">
                            <button @click="currentTab = 'credentials'" :class="currentTab === 'credentials' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'" class="flex-1 rounded-md px-3 py-2 text-sm font-medium transition-all whitespace-nowrap">API Kimlikleri</button>
                            <button @click="currentTab = 'sync'" :class="currentTab === 'sync' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'" class="flex-1 rounded-md px-3 py-2 text-sm font-medium transition-all whitespace-nowrap">Senkronizasyon</button>
                            <button @click="currentTab = 'readiness'" :class="currentTab === 'readiness' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'" class="flex-1 rounded-md px-3 py-2 text-sm font-medium transition-all whitespace-nowrap">Durum ve Ön Test</button>
                        </div>
                    </div>

                    <!-- Tab: API Kimlikleri -->
                    <div x-show="currentTab === 'credentials'" x-cloak class="p-5 lg:p-6 space-y-5">
                        <div class="mb-4">
                            <p class="text-sm font-semibold text-slate-900">API Uç Noktaları ve Kimlik Doğrulama</p>
                            <p class="text-sm text-slate-500">Pazaryerinin entegrasyon ayarlarından aldığınız gizli anahtarları buraya girin.</p>
                        </div>

                        <!-- Provider Tips -->
                        @if(!empty($this->selectedConnectionGuide['hints']))
                            <div class="rounded-lg bg-indigo-50 border border-indigo-100 p-4">
                                <ul class="list-disc list-inside text-sm text-indigo-800 space-y-1">
                                    @foreach($this->selectedConnectionGuide['hints'] as $hint)
                                        <li>{{ $hint }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form wire:submit.prevent="saveConnection" class="space-y-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="col-span-1 sm:col-span-2 hidden">
                                    <label class="{{ $labelClass }}">Auth Tipi</label>
                                    <input type="text" wire:model.defer="connectionForm.authType" class="{{ $inputClass }}">
                                </div>
                                <div class="col-span-1">
                                    <label class="{{ $labelClass }}">{{ $this->selectedConnectionGuide['api_key_label'] ?? 'API Anahtarı (Key)' }}</label>
                                    <input type="text" wire:model.defer="connectionForm.apiKey" class="{{ $inputClass }}" placeholder="Client ID / API Key vb.">
                                    @error('connectionForm.apiKey') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-span-1">
                                    <label class="{{ $labelClass }}">{{ $this->selectedConnectionGuide['api_secret_label'] ?? 'API Şifresi (Secret)' }}</label>
                                    <input type="password" wire:model.defer="connectionForm.apiSecret" class="{{ $inputClass }}" placeholder="Parola / Secret / Token vb.">
                                    @error('connectionForm.apiSecret') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                </div>
                                @if($selectedStore->marketplace === 'trendyol')
                                <div class="col-span-1 sm:col-span-2">
                                    <label class="{{ $labelClass }}">{{ $this->selectedConnectionGuide['store_front_code_label'] ?? 'StoreFrontCode' }}</label>
                                    <input type="text" wire:model.defer="connectionForm.storeFrontCode" class="{{ $inputClass }}" placeholder="{{ $this->selectedConnectionGuide['store_front_code_placeholder'] ?? 'TR, SA, AE vb.' }}">
                                    <p class="mt-1 text-xs text-slate-500">Trendyol mağazanızda ayrıca verilmişse girin. Türkiye mağazalarında çoğu test için boş bırakılabilir.</p>
                                    @error('connectionForm.storeFrontCode') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                </div>
                                @endif
                                @if($selectedStore->marketplace === 'hepsiburada')
                                <div class="col-span-1 sm:col-span-2">
                                    <label class="{{ $labelClass }}">{{ $this->selectedConnectionGuide['extra_user_label'] ?? 'User-Agent / entegratör kullanıcı' }}</label>
                                    <input type="text" wire:model.defer="connectionForm.extraUser" class="{{ $inputClass }}" placeholder="{{ $this->selectedConnectionGuide['extra_user_placeholder'] ?? 'SelfIntegration veya yetkili entegratör kullanıcı' }}">
                                    <p class="mt-1 text-xs text-slate-500">Hepsiburada bu alanı yetkili entegratör adı olarak kontrol eder. Prapazar anahtarı kullanıyorsanız Prapazar'ın Hepsiburada için tanımlı User-Agent bilgisini girmeniz gerekir.</p>
                                    @error('connectionForm.extraUser') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                </div>
                                @endif
                                @if(in_array($selectedStore->marketplace, ['shopify', 'woocommerce']))
                                <div class="col-span-1 sm:col-span-2">
                                    <label class="{{ $labelClass }}">Mağaza URL Adresi</label>
                                    <input type="url" wire:model.defer="connectionForm.storeUrl" class="{{ $inputClass }}" placeholder="https://magaza.com">
                                </div>
                                @endif
                            </div>
                            <div class="flex justify-end pt-4 border-t border-slate-100 gap-2">
                                <button type="button" wire:click="verifyConnection" class="rounded-lg border border-slate-200 px-5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Doğrula</button>
                                <button type="submit" class="rounded-lg bg-slate-900 px-5 py-2 text-sm font-medium text-white hover:bg-slate-800">Kaydet</button>
                            </div>
                        </form>
                    </div>

                    <!-- Tab: Senkronizasyon Ayarları -->
                    <div x-show="currentTab === 'sync'" x-cloak class="p-5 lg:p-6 space-y-5">
                        <div class="mb-4">
                            <p class="text-sm font-semibold text-slate-900">Otomatik Veri Çekim Ayarları</p>
                            <p class="text-sm text-slate-500">ZOLM'un bu mağazadan otomatik arka planda hangi özelliklerle veri çekeceğini belirleyin.</p>
                        </div>

                        <form wire:submit.prevent="saveSyncProfile" class="space-y-6">
                            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4">
                                <label class="{{ $toggleLabelClass }} col-span-1 cursor-pointer">
                                    <input type="checkbox" wire:model.defer="syncForm.ordersEnabled" class="mt-1">
                                    <span class="min-w-0"><span class="block text-sm font-medium text-slate-900">Sipariş Akışı Açık</span></span>
                                </label>
                                <label class="{{ $toggleLabelClass }} col-span-1 cursor-pointer">
                                    <input type="checkbox" wire:model.defer="syncForm.productsEnabled" class="mt-1">
                                    <span class="min-w-0"><span class="block text-sm font-medium text-slate-900">Ürün Akışı Açık</span></span>
                                </label>
                                <label class="{{ $toggleLabelClass }} col-span-1 cursor-pointer">
                                    <input type="checkbox" wire:model.defer="syncForm.financeEnabled" class="mt-1">
                                    <span class="min-w-0"><span class="block text-sm font-medium text-slate-900">Finans Akışı Açık</span></span>
                                </label>
                                <label class="{{ $toggleLabelClass }} col-span-1 cursor-pointer {{ ($this->selectedCapabilities['claims'] ?? false) ? '' : 'opacity-60' }}">
                                    <input type="checkbox" wire:model.defer="syncForm.claimsEnabled" @disabled(!($this->selectedCapabilities['claims'] ?? false)) class="mt-1">
                                    <span class="min-w-0"><span class="block text-sm font-medium text-slate-900">İade Akışı Açık</span></span>
                                </label>
                                <label class="{{ $toggleLabelClass }} col-span-1 cursor-pointer {{ ($this->selectedCapabilities['questions'] ?? false) ? '' : 'opacity-60' }}">
                                    <input type="checkbox" wire:model.defer="syncForm.questionsEnabled" @disabled(!($this->selectedCapabilities['questions'] ?? false)) class="mt-1">
                                    <span class="min-w-0"><span class="block text-sm font-medium text-slate-900">Soru Akışı Açık</span></span>
                                </label>
                            </div>

                            <div class="rounded-lg border border-amber-200 bg-amber-50/70 p-4">
                                <div class="flex flex-col gap-1">
                                    <p class="text-sm font-semibold text-amber-900">Canlı mağazaya gönderim</p>
                                    <p class="text-sm text-amber-800">Bu ayarlar açılırsa Ürünler ekranındaki butonlar pazaryerindeki fiyatı veya stoğu ZOLM değerleriyle güncelleyebilir.</p>
                                </div>
                                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <label class="{{ $toggleLabelClass }} cursor-pointer {{ ($this->selectedCapabilities['price_push'] ?? false) ? '' : 'opacity-60' }}">
                                        <input type="checkbox" wire:model.defer="syncForm.pricePushEnabled" @disabled(!($this->selectedCapabilities['price_push'] ?? false)) class="mt-1">
                                        <span class="min-w-0">
                                            <span class="block text-sm font-medium text-slate-900">Fiyat gönderimi açık</span>
                                            <span class="mt-1 block text-xs text-slate-500">{{ ($this->selectedCapabilities['price_push'] ?? false) ? 'ZOLM fiyatını pazaryerine gönderebilirsiniz.' : 'Bu kanal fiyat gönderimini desteklemiyor.' }}</span>
                                        </span>
                                    </label>
                                    <label class="{{ $toggleLabelClass }} cursor-pointer {{ ($this->selectedCapabilities['stock_push'] ?? false) ? '' : 'opacity-60' }}">
                                        <input type="checkbox" wire:model.defer="syncForm.stockPushEnabled" @disabled(!($this->selectedCapabilities['stock_push'] ?? false)) class="mt-1">
                                        <span class="min-w-0">
                                            <span class="block text-sm font-medium text-slate-900">Stok gönderimi açık</span>
                                            <span class="mt-1 block text-xs text-slate-500">{{ ($this->selectedCapabilities['stock_push'] ?? false) ? 'ZOLM stoğunu pazaryerine gönderebilirsiniz.' : 'Bu kanal stok gönderimini desteklemiyor.' }}</span>
                                        </span>
                                    </label>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="{{ $labelClass }}">Sipariş Kontrol Sıklığı (Dakika)</label>
                                    <input type="number" wire:model.defer="syncForm.ordersPollMinutes" class="{{ $inputClass }}">
                                </div>
                                <div>
                                    <label class="{{ $labelClass }}">İade Kontrol Sıklığı (Dakika)</label>
                                    <input type="number" wire:model.defer="syncForm.claimsPollMinutes" class="{{ $inputClass }}">
                                </div>
                                <div>
                                    <label class="{{ $labelClass }}">Soru Kontrol Sıklığı (Dakika)</label>
                                    <input type="number" wire:model.defer="syncForm.questionsPollMinutes" class="{{ $inputClass }}">
                                </div>
                                <div>
                                    <label class="{{ $labelClass }}">Eski Veri Taşıma Modu (Geçmişi Getir)</label>
                                    <select wire:model.defer="syncForm.backfillMode" class="{{ $inputClass }}">
                                        @foreach(\App\Services\Marketplace\MarketplaceProviderRegistry::backfillOptions() as $bVal => $bLabel)
                                            <option value="{{ $bVal }}">{{ $bLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="flex justify-end pt-4 border-t border-slate-100">
                                <button type="submit" class="rounded-lg bg-slate-900 px-5 py-2 text-sm font-medium text-white hover:bg-slate-800">Senkron Ayarlarını Kaydet</button>
                            </div>
                        </form>
                    </div>

                    <!-- Tab: Durum, Uyarılar & Ön Test -->
                    <div x-show="currentTab === 'readiness'" x-cloak class="p-5 lg:p-6 space-y-5 bg-slate-50/50 rounded-b-xl">
                        <button type="button" wire:click="$refresh" class="mb-4 inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Durumu Güncelle (Refresh)
                        </button>

                        @if($selectedGuidanceTopItem)
                            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">İlk aksiyon</p>
                                        <h4 class="mt-2 text-sm font-semibold text-slate-900">Tanı bazlı ilk aksiyonlar</h4>
                                        <p class="mt-2 text-sm font-medium text-slate-900">{{ $selectedGuidanceTopItem['title'] }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $selectedGuidanceTopItem['recommended_action'] }}</p>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="button" wire:click="focusSelectedStoreGuidance" class="inline-flex rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                            {{ $this->selectedGuidanceFocusLabel() }}
                                        </button>
                                        <button type="button" wire:click="syncSelectedStoreGuidance" class="inline-flex rounded-lg bg-slate-900 px-3 py-2 text-xs font-medium text-white hover:bg-slate-800">
                                            {{ $this->selectedGuidanceSyncLabel() }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($this->selectedStoreSupportsSafeProfile && $safeProfileStatus && !($safeProfileStatus['aligned'] ?? true))
                            <div class="rounded-lg border border-rose-200 bg-rose-50/80 p-4 shadow-sm">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-rose-700">Güvenli profil ile fark var</p>
                                <h4 class="text-sm font-semibold text-rose-900">{{ $safeProfileLabel }} güvenli profilinden sapma var</h4>
                                <p class="mt-1 text-sm text-rose-800">Entegrasyonlar ekranında güvenli {{ $safeProfileLabel }} profilini uygula.</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button type="button" wire:click="applySelectedStoreSafeProfile" class="inline-flex rounded-lg border border-rose-200 bg-white px-3 py-2 text-xs font-medium text-rose-700 hover:bg-rose-100">
                                        {{ $this->selectedStoreSafeProfileButtonLabel }}
                                    </button>
                                </div>
                            </div>
                        @elseif($this->selectedStoreSupportsSafeProfile && $safeProfileStatus)
                            <div class="rounded-lg border border-emerald-200 bg-emerald-50/80 p-4 shadow-sm">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-emerald-700">Form güvenli profile uyumlu</p>
                                <h4 class="text-sm font-semibold text-emerald-900">{{ $safeProfileLabel }} güvenli profili hazır</h4>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button type="button" wire:click="applySelectedStoreSafeProfile" class="inline-flex rounded-lg border border-emerald-200 bg-white px-3 py-2 text-xs font-medium text-emerald-700 hover:bg-emerald-100">
                                        {{ $this->selectedStoreSafeProfileButtonLabel }}
                                    </button>
                                </div>
                            </div>
                        @endif

                        @if($hasWebhookTopicMismatch)
                            <div class="rounded-lg border border-amber-200 bg-amber-50/80 p-4 shadow-sm">
                                <h4 class="text-sm font-semibold text-amber-900">{{ $webhookTopicLabel }} webhook topic seti güvenli değil</h4>
                                <p class="mt-1 text-sm text-amber-800">Entegrasyonlar ekranında önerilen {{ $webhookTopicLabel }} webhook topic setini uygula.</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button type="button" wire:click="applyRecommendedWebhookTopics" class="inline-flex rounded-lg border border-amber-200 bg-white px-3 py-2 text-xs font-medium text-amber-700 hover:bg-amber-100">
                                        {{ $this->selectedStoreWebhookTopicPresetLabel() }}
                                    </button>
                                </div>
                            </div>
                        @endif

                        @if($this->selectedConnectionReadiness)
                            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                                <h4 class="font-semibold text-slate-900">Canlı Bağlantı Hazırlık Kontrolü</h4>
                                <p class="text-sm text-slate-500 mb-4">{{ $this->selectedConnectionReadiness['summary'] }}</p>

                                <div class="space-y-2">
                                    @foreach($this->selectedConnectionReadiness['checks'] as $check)
                                        <div class="flex justify-between text-sm border-b border-slate-100 pb-2">
                                            <span class="text-slate-700">{{ $check['label'] }}</span>
                                            <span class="{{ $check['state'] === 'ok' ? 'text-emerald-600' : ($check['state'] === 'warning' ? 'text-amber-600' : 'text-rose-600 font-medium') }}">
                                                {{ $check['state'] === 'ok' ? 'Başarılı' : ($check['state'] === 'warning' ? 'Uyarı (Opsiyonel)' : 'Hatalı / Eksik') }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>

                                @if(($this->selectedConnectionReadiness['warnings'] ?? []) !== [])
                                    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50/80 p-4">
                                        <p class="text-sm font-semibold text-amber-900">Ek Uyarılar</p>
                                        <ul class="mt-2 space-y-2 text-sm text-amber-800">
                                            @foreach($this->selectedConnectionReadiness['warnings'] as $warning)
                                                <li>{{ $warning }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                @if(($this->selectedConnectionReadiness['failures'] ?? []) !== [])
                                    <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50/80 p-4">
                                        <p class="text-sm font-semibold text-rose-900">Eksik veya Hatalı Alanlar</p>
                                        <ul class="mt-2 space-y-2 text-sm text-rose-800">
                                            @foreach($this->selectedConnectionReadiness['failures'] as $failure)
                                                <li>{{ $failure }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        @else
                            <p class="text-sm text-slate-500">Durum kontrolü için ayarların kaydedilmesi bekleniyor...</p>
                        @endif

                        @if($selectedLegacyProjection && ((int) ($selectedLegacyProjection['pending_rows'] ?? 0) > 0 || (int) ($selectedLegacyProjection['confirmed_orders'] ?? 0) > 0 || (int) ($selectedLegacyProjection['legacy_event_orders'] ?? 0) > 0))
                            <div class="rounded-lg border border-blue-100 bg-blue-50 p-4 shadow-sm">
                                <h4 class="font-semibold text-blue-900">Legacy backlog</h4>
                                <p class="text-sm text-blue-800 mb-2">Backlog var. Eski veri aktarım etkisi bu mağazada izleniyor.</p>
                                <div class="flex flex-wrap items-center gap-3 text-xs">
                                    <span class="rounded-md border border-blue-200 bg-white px-2.5 py-1 font-medium text-blue-700">Backlog var</span>
                                    <span class="rounded-md border border-emerald-200 bg-white px-2.5 py-1 font-medium text-emerald-700">Kesine dönen {{ (int) ($selectedLegacyProjection['confirmed_orders'] ?? 0) }}</span>
                                </div>
                                <p class="mt-3 text-xs text-blue-900">Eski veri aktarım etkisi</p>
                                <div class="mt-3 flex gap-2">
                                    <button wire:click="previewSelectedStoreLegacyProjection" class="text-sm bg-white border border-blue-200 px-3 py-1 rounded shadow-sm text-blue-700 hover:bg-blue-100">Aktarım ekranına git</button>
                                    <button wire:click="runSelectedStoreLegacyProjection" class="text-sm bg-blue-600 border border-blue-600 px-3 py-1 rounded shadow-sm text-white hover:bg-blue-700">Güvenle Aktar</button>
                                </div>
                                @if($this->selectedStoreLegacyProjectionRunCommand)
                                    <p class="mt-3 rounded-md border border-blue-100 bg-white px-3 py-2 font-mono text-[11px] text-blue-900">{{ $this->selectedStoreLegacyProjectionRunCommand }}</p>
                                @endif
                            </div>
                        @endif

                        @if($this->selectedStoreLegacyProjection)
                            <div class="rounded-lg border border-blue-100 bg-blue-50 p-4 shadow-sm mt-4">
                                <h4 class="font-semibold text-blue-900">Eski Veri Transit Kuyruğu</h4>
                                <p class="text-sm text-blue-800 mb-2">Bu mağazada çekilmiş ancak daha işlenmemiş finans veya sipariş kayıtlarınızı buradan doğrudan kesinleştirebilirsiniz.</p>
                                <div class="flex gap-2">
                                    <button wire:click="previewSelectedStoreLegacyProjection" class="text-sm bg-white border border-blue-200 px-3 py-1 rounded shadow-sm text-blue-700 hover:bg-blue-100">Önizleme Yap</button>
                                    <button wire:click="runSelectedStoreLegacyProjection" class="text-sm bg-blue-600 border border-blue-600 px-3 py-1 rounded shadow-sm text-white hover:bg-blue-700">Güvenle Aktar</button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
