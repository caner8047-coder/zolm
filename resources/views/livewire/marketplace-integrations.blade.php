@php
    $inputClass = 'w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-base sm:text-sm text-slate-900 shadow-sm transition focus:border-slate-900 focus:outline-none';
    $labelClass = 'text-sm font-medium text-slate-700';
    $toggleLabelClass = 'flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3';
@endphp

@once
    <style>
        .mp-integrations-page .rounded-2xl { border-radius: 10px; }
        .mp-integrations-page .rounded-xl { border-radius: 8px; }
        .mp-integrations-page .rounded-lg { border-radius: 6px; }
        .mp-integrations-page .rounded-md { border-radius: 6px; }
        .mp-integrations-page > .space-y-6 > section,
        .mp-integrations-page > section {
            border-color: rgb(226 232 240 / 0.92);
            box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
        }
        .mp-integrations-surface {
            background:
                radial-gradient(circle at top right, rgba(167, 243, 208, 0.18), transparent 26%),
                linear-gradient(180deg, rgba(255, 251, 235, 0.35), rgba(255, 255, 255, 1));
        }
        .mp-integrations-page .integration-kicker {
            letter-spacing: 0.22em;
        }
        .mp-integrations-page .integration-soft-card {
            background: linear-gradient(180deg, rgba(255,255,255,0.96), rgba(248,250,252,0.94));
        }
    </style>
@endonce

<div class="mp-integrations-page w-full space-y-5 overflow-hidden">
    <section class="mp-integrations-surface rounded-2xl border border-slate-200 p-4 lg:p-6 shadow-sm">
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
            <div class="xl:col-span-5 rounded-2xl border border-slate-200 bg-white p-5 lg:p-6">
                <div class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase integration-kicker text-slate-500">
                    Kurulum Merkezi
                </div>
                <h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-900 lg:text-4xl">Firma ve Mağaza Profilleri</h1>
                <p class="mt-3 text-sm leading-6 text-slate-500 lg:text-base">
                    Firma yapısını, kanal mağazalarını ve güvenli bağlantı profilini tek akışta kurun. Karmaşık teknik alanlar seçili mağaza üzerinde, sade bir operasyon diliyle yönetilir.
                </p>

                <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <button type="button" wire:click="exportReadinessCsv" class="inline-flex min-h-[48px] items-center justify-center rounded-lg border border-slate-200 bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800">Hazırlık raporu CSV</button>
                    @if($selectedStore)
                        <button type="button" wire:click="exportSelectedStoreSmokeCsv" class="inline-flex min-h-[48px] items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Ön test geçmişi CSV</button>
                    @else
                        <div class="inline-flex min-h-[48px] items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500">Seçili mağaza oluşunca ön test raporu açılır</div>
                    @endif
                </div>

                <div class="mt-6 grid grid-cols-2 gap-2 text-xs text-slate-600 sm:grid-cols-4">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="uppercase tracking-[0.18em] text-slate-400">Firma</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ number_format($stats['entities'], 0, ',', '.') }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="uppercase tracking-[0.18em] text-slate-400">Mağaza</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ number_format($stats['stores'], 0, ',', '.') }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="uppercase tracking-[0.18em] text-slate-400">Webhook</p>
                        <div class="mt-1 inline-flex items-center gap-1.5 align-middle">
                            <p class="font-semibold text-slate-900">{{ number_format($stats['webhookEnabled'], 0, ',', '.') }}</p>
                            <x-zolm.help-tip title="Webhook" summary="Mağazadan sipariş ve durum olaylarının uygulamaya itildiği canlı bildirim kanalıdır." source="Senkron profilindeki webhook ayarı ve mağaza bağlantı yapılandırması." refresh="Profil güncellendiğinde veya test sonucu geldiğinde." impact="Siparişlerin uygulamaya ne kadar hızlı düştüğünü etkiler." button-class="h-3.5 w-3.5 text-[9px]" />
                        </div>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="uppercase tracking-[0.18em] text-slate-400">Ön test</p>
                        <div class="mt-1 inline-flex items-center gap-1.5 align-middle">
                            <p class="font-semibold text-slate-900">{{ number_format($stats['smokeReady'], 0, ',', '.') }}</p>
                            <x-zolm.help-tip title="Ön test hazırlığı" summary="Seçili mağaza için temel bağlantı, kimlik bilgisi ve örnek akış testlerinin çalışmaya hazır olup olmadığını gösterir." source="Bağlantı profili, kimlik bilgileri ve ön test kayıtları." refresh="Mağaza kaydedildiğinde veya ön test çalıştığında." impact="Canlı senkron açmadan önce güvenli kurulum doğrulaması sağlar." button-class="h-3.5 w-3.5 text-[9px]" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="xl:col-span-4 grid grid-cols-2 gap-3">
                <div class="integration-soft-card rounded-2xl border border-slate-200 p-4 lg:p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Aktif firma</p>
                    <p class="mt-3 text-3xl font-bold text-slate-900">{{ number_format($stats['entities'], 0, ',', '.') }}</p>
                    <p class="mt-2 text-sm text-slate-500">Vergi numarası bazlı yapı</p>
                </div>
                <div class="integration-soft-card rounded-2xl border border-slate-200 p-4 lg:p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Aktif mağaza</p>
                    <p class="mt-3 text-3xl font-bold text-slate-900">{{ number_format($stats['stores'], 0, ',', '.') }}</p>
                    <p class="mt-2 text-sm text-slate-500">Bağlı kanal profili</p>
                </div>
                <div class="integration-soft-card rounded-2xl border border-slate-200 p-4 lg:p-5">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Hazır bağlantı</p>
                        <x-zolm.help-tip title="Hazır bağlantı" summary="Kimlik bilgileri doğrulanmış, temel profil ayarları tamamlanmış mağaza sayısıdır." source="Mağaza bağlantı durumu ve senkron profil alanları." refresh="Mağaza kaydedildiğinde, bağlantı doğrulandığında veya profil tamamlandığında." impact="Canlıya alınabilecek mağaza kapasitesini gösterir." button-class="h-4 w-4 text-[10px]" />
                    </div>
                    <p class="mt-3 text-3xl font-bold text-emerald-600">{{ number_format($stats['configured'], 0, ',', '.') }}</p>
                    <p class="mt-2 text-sm text-slate-500">Kimlik bilgisi doğrulandı</p>
                </div>
                <div class="rounded-2xl border {{ $stats['needsAttention'] > 0 ? 'border-amber-200 bg-amber-50/80' : 'border-slate-200 bg-white/90' }} p-4 lg:p-5">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] {{ $stats['needsAttention'] > 0 ? 'text-amber-700' : 'text-slate-400' }}">Kontrol bekleyen</p>
                        <x-zolm.help-tip title="Kontrol bekleyen" summary="Eksik alanı, bağlantı uyarısı veya test sorunu olan mağaza sayısını verir." source="Readiness denetimleri, smoke test sonuçları ve profil eksikleri." refresh="Her kayıt veya testten sonra." impact="Önce hangi mağazaya dönmeniz gerektiğini gösterir." button-class="h-4 w-4 text-[10px]" />
                    </div>
                    <p class="mt-3 text-3xl font-bold {{ $stats['needsAttention'] > 0 ? 'text-amber-700' : 'text-slate-900' }}">{{ number_format($stats['needsAttention'], 0, ',', '.') }}</p>
                    <p class="mt-2 text-sm {{ $stats['needsAttention'] > 0 ? 'text-amber-800/80' : 'text-slate-500' }}">Eksik veya uyarılı mağaza</p>
                </div>
            </div>

            <div class="xl:col-span-3 rounded-2xl border border-slate-200 bg-white/90 p-4 lg:p-5">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">Çalışma alanı</p>
                @if($selectedStore)
                    <h2 class="mt-3 text-lg font-semibold text-slate-900">{{ $selectedStore->store_name }}</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $this->providerOptions[$selectedStore->marketplace] ?? ucfirst($selectedStore->marketplace) }}
                        @if($selectedStore->legalEntity)
                            · {{ $selectedStore->legalEntity->name }}
                        @endif
                    </p>
                    <div class="mt-4 space-y-2">
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-400">Durum</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ $selectedStore->connection?->status === 'configured' ? 'Bağlantı hazır' : 'Bağlantı taslak' }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-400">Webhook</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ $selectedStore->syncProfile?->webhook_enabled ? 'Açık' : 'Kapalı' }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-400">Satıcı ID</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ $selectedStore->seller_id ?: '-' }}</p>
                        </div>
                    </div>
                @else
                    <div class="mt-3 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-5 text-sm text-slate-500">
                        Sağdaki detay stüdyosu için önce bir mağaza seçin veya yeni mağaza oluşturun.
                    </div>
                @endif
            </div>
        </div>
    </section>

    @if($flashMessage)
        <div class="rounded-xl border px-4 py-3 text-sm {{ $flashMessageType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' }}">
            {{ $flashMessage }}
        </div>
    @endif

    <div class="space-y-6">
        <section class="rounded-2xl border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-bold text-slate-900">Firma ve Mağaza Havuzu</h2>
                <x-zolm.help-tip title="Firma ve Mağaza Havuzu" summary="Önce hukuki firma kartı açılır, ardından pazaryeri mağazaları bu firmaya bağlanır." source="Legal entity kayıtları ve store profilleri." refresh="Firma veya mağaza kaydı kaydedildiğinde." impact="Muhasebe, sipariş ve finans modüllerindeki tüm bağlam bu yapıdan beslenir." />
            </div>
            <p class="mt-1 text-sm text-slate-500">Önce firmayı tanımlayın, ardından mağazayı bu firmaya bağlayın.</p>
                <div x-data="{ setupTab: '{{ $selectedStore ? 'store' : 'entity' }}' }" class="mt-4 space-y-5">
                    <div class="inline-flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-slate-50/70 p-2">
                        <button type="button"
                                @click="setupTab = 'entity'"
                                :class="setupTab === 'entity' ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 hover:bg-slate-50'"
                                class="inline-flex min-h-[44px] items-center justify-center rounded-xl px-4 py-3 text-sm font-medium transition">
                            1. Firma oluştur
                        </button>
                        <button type="button"
                                @click="setupTab = 'store'"
                                :class="setupTab === 'store' ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 hover:bg-slate-50'"
                                class="inline-flex min-h-[44px] items-center justify-center rounded-xl px-4 py-3 text-sm font-medium transition">
                            2. Mağaza oluştur
                        </button>
                    </div>

                    <form wire:submit.prevent="saveLegalEntity" class="space-y-4" x-show="setupTab === 'entity'" x-cloak x-transition>
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">Yeni firma</p>
                                <p class="mt-1 text-sm text-slate-500">Vergi numarasına bağlı temel yapı.</p>
                            </div>
                            <x-zolm.status-badge tone="info">{{ $legalEntities->count() }} kayıt</x-zolm.status-badge>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 lg:gap-4">
                            <div class="sm:col-span-2">
                                <label class="{{ $labelClass }}">Firma adı</label>
                                <input type="text" wire:model.defer="entityForm.name" class="{{ $inputClass }}" placeholder="Zem Home Mobilya Ltd. Sti.">
                                @error('entityForm.name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">Vergi no</label>
                                <input type="text" wire:model.defer="entityForm.taxNumber" class="{{ $inputClass }}" placeholder="1234567890">
                                @error('entityForm.taxNumber') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">Şirket tipi</label>
                                <select wire:model.defer="entityForm.companyType" class="{{ $inputClass }}">
                                    <option value="limited">Limited</option>
                                    <option value="anonim">Anonim</option>
                                    <option value="sahis">Şahıs</option>
                                    <option value="diger">Diğer</option>
                                </select>
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">Vergi dairesi</label>
                                <input type="text" wire:model.defer="entityForm.taxOffice" class="{{ $inputClass }}" placeholder="Cankaya">
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">Mersis no</label>
                                <input type="text" wire:model.defer="entityForm.mersisNumber" class="{{ $inputClass }}" placeholder="Opsiyonel">
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">E-posta</label>
                                <input type="email" wire:model.defer="entityForm.email" class="{{ $inputClass }}" placeholder="info@firma.com">
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">Telefon</label>
                                <input type="text" wire:model.defer="entityForm.phone" class="{{ $inputClass }}" placeholder="0555 555 55 55">
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">Banka</label>
                                <input type="text" wire:model.defer="entityForm.bankName" class="{{ $inputClass }}" placeholder="Opsiyonel">
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">IBAN</label>
                                <input type="text" wire:model.defer="entityForm.iban" class="{{ $inputClass }}" placeholder="TR...">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="{{ $labelClass }}">Adres</label>
                                <textarea wire:model.defer="entityForm.address" rows="3" class="{{ $inputClass }}" placeholder="Firma adresi"></textarea>
                            </div>
                            <label class="{{ $toggleLabelClass }}">
                                <input type="checkbox" wire:model.defer="entityForm.isActive" class="mt-1 h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-slate-900">Firma aktif</span>
                                    <span class="mt-1 block text-sm text-slate-500">Yeni mağazalar bu firma altında aktif açılsın.</span>
                                </span>
                            </label>
                        </div>

                        <div class="flex justify-end">
                            <x-zolm.primary-button type="submit" wire:loading.attr="disabled" wire:target="saveLegalEntity">
                                <span wire:loading.remove wire:target="saveLegalEntity">Firma kaydet</span>
                                <span wire:loading wire:target="saveLegalEntity">Kaydediliyor...</span>
                            </x-zolm.primary-button>
                        </div>
                    </form>

                    <form wire:submit.prevent="saveStore" class="space-y-4" x-show="setupTab === 'store'" x-cloak x-transition>
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ $selectedStore ? 'Mağaza düzenle' : 'Yeni mağaza' }}</p>
                                <p class="mt-1 text-sm text-slate-500">Pazaryeri veya e-ticaret bağlantısını firma altına ekleyin.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                @if($selectedStore)
                                    <x-zolm.status-badge tone="warning">Seçili mağaza</x-zolm.status-badge>
                                @endif
                                <button type="button" wire:click="startNewStore" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50">
                                    Yeni kayıt
                                </button>
                            </div>
                        </div>

                        @if($legalEntities->isEmpty())
                            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                                Önce en az bir firma oluşturun. Mağaza kaydı firma seçimi olmadan açılamaz.
                            </div>
                        @else
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 lg:gap-4">
                                <div class="sm:col-span-2">
                                    <label class="{{ $labelClass }}">Firma</label>
                                    <select wire:model.defer="storeForm.legalEntityId" class="{{ $inputClass }}">
                                        @foreach($legalEntities as $entity)
                                            <option value="{{ $entity->id }}">{{ $entity->name }} - {{ $entity->tax_number }}</option>
                                        @endforeach
                                    </select>
                                    @error('storeForm.legalEntityId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="{{ $labelClass }}">Kanal</label>
                                    <select wire:model.defer="storeForm.marketplace" class="{{ $inputClass }}">
                                        @foreach($this->providerOptions as $providerKey => $providerLabel)
                                            <option value="{{ $providerKey }}">{{ $providerLabel }}</option>
                                        @endforeach
                                    </select>
                                    @error('storeForm.marketplace') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="{{ $labelClass }}">Mağaza adı</label>
                                    <input type="text" wire:model.defer="storeForm.storeName" class="{{ $inputClass }}" placeholder="ZEM HOME">
                                    @error('storeForm.storeName') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="{{ $labelClass }}">Mağaza kodu</label>
                                    <input type="text" wire:model.defer="storeForm.storeCode" class="{{ $inputClass }}" placeholder="Opsiyonel">
                                </div>
                                <div>
                                    <label class="{{ $labelClass }}">{{ $this->selectedConnectionGuide['seller_id_label'] }}</label>
                                    <input type="text" wire:model.defer="storeForm.sellerId" class="{{ $inputClass }}" placeholder="{{ $this->selectedConnectionGuide['seller_id_placeholder'] }}">
                                    @error('storeForm.sellerId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="{{ $labelClass }}">Saat dilimi</label>
                                    <input type="text" wire:model.defer="storeForm.timezone" class="{{ $inputClass }}" placeholder="Europe/Istanbul">
                                </div>
                                <div>
                                    <label class="{{ $labelClass }}">Para birimi</label>
                                    <input type="text" wire:model.defer="storeForm.currency" class="{{ $inputClass }}" placeholder="TRY">
                                </div>
                                <label class="{{ $toggleLabelClass }} sm:col-span-2">
                                    <input type="checkbox" wire:model.defer="storeForm.isActive" class="mt-1 h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                                    <span class="min-w-0">
                                        <span class="block text-sm font-medium text-slate-900">Mağaza aktif</span>
                                        <span class="mt-1 block text-sm text-slate-500">Bağlantı ve senkron akışları bu mağazada çalışabilsin.</span>
                                    </span>
                                </label>
                            </div>

                            <div class="flex justify-end">
                                <x-zolm.primary-button type="submit" wire:loading.attr="disabled" wire:target="saveStore">
                                    <span wire:loading.remove wire:target="saveStore">{{ $selectedStore ? 'Mağazayı güncelle' : 'Mağaza oluştur' }}</span>
                                    <span wire:loading wire:target="saveStore">Kaydediliyor...</span>
                                </x-zolm.primary-button>
                            </div>
                        @endif
                    </form>
                </div>

                <div class="mt-6 space-y-3">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">Bağlı mağazalar</p>
                            <p class="mt-1 text-sm text-slate-500">Seçili mağaza üzerinden bağlantı ve senkron ayarları düzenlenir.</p>
                        </div>
                        <x-zolm.status-badge>{{ $stores->count() }} mağaza</x-zolm.status-badge>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 lg:gap-4">
                        @forelse($stores as $store)
                            @php
                                $isSelected = $selectedStore && $selectedStore->id === $store->id;
                                $connectionStatusTone = $store->connection?->status === 'configured' ? 'success' : 'warning';
                                $readinessRow = collect($this->readinessSummary['rows'] ?? [])->firstWhere('store_id', $store->id);
                                $guidanceRow = data_get($this->storeGuidanceMap, $store->id);
                                $legacyRow = data_get($this->legacyProjectionStoreMap, $store->id, []);
                                $topGuidance = data_get($guidanceRow, 'top_item');
                                $readinessTone = match(data_get($readinessRow, 'state')) {
                                    'ready' => 'success',
                                    'warning' => 'warning',
                                    'missing' => 'danger',
                                    default => 'default',
                                };
                                $readinessLabel = match(data_get($readinessRow, 'state')) {
                                    'ready' => 'Ön test hazır',
                                    'warning' => 'Kontrol et',
                                    'missing' => 'Eksik var',
                                    default => 'Hazırlık yok',
                                };
                                $readinessHint = data_get($readinessRow, 'first_failure')
                                    ?: data_get($readinessRow, 'first_warning')
                                    ?: data_get($readinessRow, 'summary');
                            @endphp
                            <button type="button" wire:click="selectStore({{ $store->id }})" class="text-left rounded-xl border px-4 py-4 transition {{ $isSelected ? 'border-slate-900 bg-white shadow-sm ring-2 ring-slate-900/5' : 'border-slate-200 bg-slate-50/60 hover:border-slate-300 hover:bg-white' }}">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-900">{{ $store->store_name }}</p>
                                        <p class="mt-1 text-sm text-slate-500">{{ $this->providerOptions[$store->marketplace] ?? ucfirst($store->marketplace) }} · {{ $store->legalEntity?->name }}</p>
                                    </div>
                                    <div class="flex flex-col items-end gap-2">
                                        <x-zolm.status-badge :tone="$connectionStatusTone">{{ $store->connection?->status === 'configured' ? 'Hazır' : 'Taslak' }}</x-zolm.status-badge>
                                        <x-zolm.status-badge :tone="$readinessTone">{{ $readinessLabel }}</x-zolm.status-badge>
                                    </div>
                                </div>
                                <div class="mt-4 grid grid-cols-2 gap-2 text-sm text-slate-500">
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.18em] {{ $isSelected ? 'text-slate-400' : 'text-slate-400' }}">Satıcı ID</p>
                                        <p class="mt-1 font-medium text-slate-900">{{ $store->seller_id ?: '-' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.18em] {{ $isSelected ? 'text-slate-400' : 'text-slate-400' }}">Webhook</p>
                                        <p class="mt-1 font-medium text-slate-900">{{ $store->syncProfile?->webhook_enabled ? 'Açık' : 'Kapalı' }}</p>
                                    </div>
                                </div>
                                <div class="mt-4 rounded-2xl border {{ $isSelected ? 'border-slate-200 bg-slate-50' : 'border-slate-200 bg-white' }} px-3 py-3">
                                    <p class="text-xs uppercase tracking-[0.18em] {{ $isSelected ? 'text-slate-400' : 'text-slate-400' }}">Ön test hazırlığı</p>
                                    <p class="mt-2 text-sm text-slate-600">{{ $readinessHint }}</p>
                                </div>
                                @if((int) data_get($legacyRow, 'pending_rows', 0) > 0 || (int) data_get($legacyRow, 'confirmed_orders', 0) > 0 || (int) data_get($legacyRow, 'projected_rows', 0) > 0)
                                    <div class="mt-3 rounded-2xl border {{ $isSelected ? 'border-slate-200 bg-slate-50' : 'border-slate-200 bg-white' }} px-3 py-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <p class="text-xs uppercase tracking-[0.18em] {{ $isSelected ? 'text-slate-400' : 'text-slate-400' }}">Eski veri kuyruğu</p>
                                            <x-zolm.status-badge :tone="(int) data_get($legacyRow, 'pending_rows', 0) > 0 ? 'warning' : ((int) data_get($legacyRow, 'confirmed_orders', 0) > 0 ? 'success' : 'default')">
                                                {{ (int) data_get($legacyRow, 'pending_rows', 0) > 0 ? 'Kuyruk var' : 'Aktarım tamam' }}
                                            </x-zolm.status-badge>
                                        </div>
                                        <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-slate-500">
                                            <div class="rounded-xl border {{ $isSelected ? 'border-slate-200 bg-white' : 'border-slate-200 bg-slate-50' }} px-3 py-2">
                                                <p class="uppercase tracking-[0.16em] {{ $isSelected ? 'text-slate-400' : 'text-slate-400' }}">Bekleyen</p>
                                                <p class="mt-1 text-sm font-semibold {{ (int) data_get($legacyRow, 'pending_rows', 0) > 0 ? 'text-amber-500' : 'text-slate-900' }}">{{ number_format((int) data_get($legacyRow, 'pending_rows', 0), 0, ',', '.') }}</p>
                                            </div>
                                            <div class="rounded-xl border {{ $isSelected ? 'border-slate-200 bg-white' : 'border-slate-200 bg-slate-50' }} px-3 py-2">
                                                <p class="uppercase tracking-[0.16em] {{ $isSelected ? 'text-slate-400' : 'text-slate-400' }}">Kesine dönen</p>
                                                <p class="mt-1 text-sm font-semibold {{ (int) data_get($legacyRow, 'confirmed_orders', 0) > 0 ? 'text-emerald-500' : 'text-slate-900' }}">{{ number_format((int) data_get($legacyRow, 'confirmed_orders', 0), 0, ',', '.') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                <div class="mt-3 rounded-2xl border {{ $isSelected ? 'border-slate-200 bg-slate-50' : 'border-slate-200 bg-white' }} px-3 py-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <p class="text-xs uppercase tracking-[0.18em] {{ $isSelected ? 'text-slate-400' : 'text-slate-400' }}">İlk aksiyon</p>
                                        @if($topGuidance)
                                            <x-zolm.status-badge :tone="$this->guidanceSeverityTone(data_get($topGuidance, 'severity'))">
                                                {{ $this->guidanceSeverityLabel(data_get($topGuidance, 'severity')) }}
                                            </x-zolm.status-badge>
                                        @endif
                                    </div>
                                    @if($topGuidance)
                                        <p class="mt-2 text-sm font-medium text-slate-900">{{ data_get($topGuidance, 'title') }}</p>
                                        <p class="mt-2 text-sm text-slate-600">{{ data_get($topGuidance, 'recommended_action') }}</p>
                                        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                            <span class="rounded-full border {{ $isSelected ? 'border-slate-200 bg-white' : 'border-slate-200 bg-slate-50' }} px-3 py-1">
                                                {{ number_format((int) data_get($topGuidance, 'impact_count', 0), 0, ',', '.') }} kayıt
                                            </span>
                                            <span class="rounded-full border {{ $isSelected ? 'border-slate-200 bg-white' : 'border-slate-200 bg-slate-50' }} px-3 py-1">
                                                {{ $this->guidanceRouteLabel(data_get($topGuidance, 'route')) }}
                                            </span>
                                        </div>
                                    @else
                                        <p class="mt-2 text-sm text-slate-500">Bu mağaza için henüz diagnostik kaynaklı bir aksiyon önerisi oluşmadı.</p>
                                    @endif
                                </div>
                            </button>
                        @empty
                            <div class="sm:col-span-2 rounded-xl border border-dashed border-slate-300 bg-white px-5 py-6 text-sm text-slate-500">
                                Henüz mağaza eklenmedi. İlk Trendyol mağazasını oluşturduktan sonra webhook ve senkron profili aşağıda yönetilebilir olacak.
                            </div>
                        @endforelse
                    </div>
                </div>
            </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Bağlantı ve Erişim Bilgileri</h2>
            <p class="mt-1 text-sm text-slate-500">Seçili mağaza için API, webhook ve temel endpoint ayarlarını burada tutuyoruz.</p>
            <div class="mt-4">
                @if($selectedStore)
                    @php
                        $primaryStoreGuidance = $this->selectedStoreGuidanceItems[0] ?? null;
                        $secondaryStoreGuidance = array_slice($this->selectedStoreGuidanceItems, 1);
                    @endphp

                    <form wire:submit.prevent="saveConnection" x-data="{ assistantOpen: 'credentials', extraGuidanceOpen: false }" class="space-y-5">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 lg:p-5">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0 space-y-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                                            Seçili mağaza
                                        </span>
                                        <x-zolm.status-badge :tone="$selectedStore->connection?->status === 'configured' ? 'success' : 'warning'">
                                            {{ $selectedStore->connection?->status === 'configured' ? 'Bağlantı hazır' : 'Bağlantı taslak' }}
                                        </x-zolm.status-badge>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-slate-900">{{ $selectedStore->store_name }}</h3>
                                        <p class="mt-1 text-sm text-slate-500">
                                            {{ $selectedStore->legalEntity?->name }} · {{ $this->selectedProviderMeta['label'] ?? ucfirst($selectedStore->marketplace) }}
                                            @if($selectedStore->seller_id)
                                                · {{ $selectedStore->seller_id }}
                                            @endif
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($this->capabilityLabels() as $capabilityKey => $capabilityLabel)
                                            <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium {{ ($this->selectedCapabilities[$capabilityKey] ?? false) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-500' }}">
                                                {{ $capabilityLabel }} {{ ($this->selectedCapabilities[$capabilityKey] ?? false) ? 'açık' : 'pasif' }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="grid w-full grid-cols-1 gap-2 sm:grid-cols-3 lg:w-auto lg:min-w-[420px]">
                                    <button type="button"
                                            wire:click="runSync('orders')"
                                            wire:loading.attr="disabled"
                                            wire:target="runSync"
                                            @disabled(!($this->selectedCapabilities['orders'] ?? false))
                                            class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition enabled:hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">
                                        Sipariş çek
                                    </button>
                                    <button type="button"
                                            wire:click="runSync('products')"
                                            wire:loading.attr="disabled"
                                            wire:target="runSync"
                                            @disabled(!($this->selectedCapabilities['products'] ?? false))
                                            class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition enabled:hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">
                                        Ürün çek
                                    </button>
                                    <button type="button"
                                            wire:click="runSync('finance')"
                                            wire:loading.attr="disabled"
                                            wire:target="runSync"
                                            @disabled(!($this->selectedCapabilities['finance'] ?? false))
                                            class="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-slate-900 px-4 py-3 text-sm font-medium text-white transition enabled:hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50">
                                        Finans çek
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <x-zolm.assistant-accordion grouped name="credentials" eyebrow="Bağlantı" title="Bağlantı bilgileri" description="Kimlik bilgileri, endpoint alanları ve sağlayıcı notları burada tutulur." badge="Kaydet ve doğrula">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 lg:gap-4">
                                    <div>
                                        <label class="{{ $labelClass }}">Auth tipi</label>
                                        <input type="text" wire:model.defer="connectionForm.authType" class="{{ $inputClass }}" placeholder="{{ $this->selectedConnectionGuide['default_auth_type'] }}">
                                    </div>
                                    <div>
                                        <label class="{{ $labelClass }}">{{ $this->selectedConnectionGuide['api_base_url_label'] }}</label>
                                        <input type="url" wire:model.defer="connectionForm.apiBaseUrl" class="{{ $inputClass }}" placeholder="{{ $this->selectedConnectionGuide['api_base_url_placeholder'] }}">
                                        @error('connectionForm.apiBaseUrl') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="{{ $labelClass }}">{{ $this->selectedConnectionGuide['api_key_label'] }}</label>
                                        <input type="text" wire:model.defer="connectionForm.apiKey" class="{{ $inputClass }}" placeholder="{{ $this->selectedConnectionGuide['api_key_placeholder'] }}">
                                    </div>
                                    <div>
                                        <label class="{{ $labelClass }}">{{ $this->selectedConnectionGuide['api_secret_label'] }}</label>
                                        <input type="password" wire:model.defer="connectionForm.apiSecret" class="{{ $inputClass }}" placeholder="{{ $this->selectedConnectionGuide['api_secret_placeholder'] }}">
                                    </div>
                                    <div>
                                        <label class="{{ $labelClass }}">{{ $this->selectedConnectionGuide['store_front_code_label'] }}</label>
                                        <input type="text" wire:model.defer="connectionForm.storeFrontCode" class="{{ $inputClass }}" placeholder="{{ $this->selectedConnectionGuide['store_front_code_placeholder'] }}">
                                    </div>
                                    <div>
                                        <label class="{{ $labelClass }}">{{ $this->selectedConnectionGuide['extra_user_label'] }}</label>
                                        <input type="text" wire:model.defer="connectionForm.extraUser" class="{{ $inputClass }}" placeholder="{{ $this->selectedConnectionGuide['extra_user_placeholder'] }}">
                                    </div>
                                    <div>
                                        <label class="{{ $labelClass }}">{{ $this->selectedConnectionGuide['extra_password_label'] }}</label>
                                        <input type="password" wire:model.defer="connectionForm.extraPassword" class="{{ $inputClass }}" placeholder="{{ $this->selectedConnectionGuide['extra_password_placeholder'] }}">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="{{ $labelClass }}">{{ $this->selectedConnectionGuide['store_url_label'] }}</label>
                                        <input type="url" wire:model.defer="connectionForm.storeUrl" class="{{ $inputClass }}" placeholder="{{ $this->selectedConnectionGuide['store_url_placeholder'] }}">
                                    </div>
                                </div>

                                <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-4">
                                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-slate-900">Bağlantı alanı notları</p>
                                            <p class="mt-1 text-sm text-slate-500">Seçili sağlayıcıya göre hangi alanın ne anlama geldiğini burada özetliyoruz.</p>
                                        </div>
                                        <x-zolm.status-badge tone="info">{{ $this->selectedProviderMeta['label'] ?? ucfirst($selectedStore->marketplace) }}</x-zolm.status-badge>
                                    </div>
                                    <div class="mt-4 grid grid-cols-1 gap-2">
                                        @foreach($this->selectedConnectionGuide['hints'] as $hint)
                                            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                                                {{ $hint }}
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </x-zolm.assistant-accordion>

                            <x-zolm.assistant-accordion grouped name="readiness" eyebrow="Hazırlık" title="Hazırlık ve ilk aksiyon" description="Canlı test hazırlığı ve seçili mağaza için bugünkü ilk aksiyon burada toplanır." :badge="count($this->selectedStoreGuidanceItems) > 0 ? count($this->selectedStoreGuidanceItems) . ' öneri' : 'Hazır durumu'">
                                @if($this->selectedConnectionReadiness)
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-4">
                                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-slate-900">Ön test hazırlık kontrolü</p>
                                                <p class="mt-1 text-sm text-slate-500">{{ $this->selectedConnectionReadiness['summary'] }}</p>
                                            </div>
                                            <x-zolm.status-badge :tone="$this->selectedConnectionReadiness['is_ready'] ? 'success' : 'warning'">
                                                {{ $this->selectedConnectionReadiness['is_ready'] ? 'Hazır' : 'Eksik var' }}
                                            </x-zolm.status-badge>
                                        </div>

                                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            @foreach($this->selectedConnectionReadiness['checks'] as $check)
                                                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                                                    <div class="flex items-center justify-between gap-3">
                                                        <p class="text-sm font-medium text-slate-900">{{ $check['label'] }}</p>
                                                        <x-zolm.status-badge :tone="$check['state'] === 'ok' ? 'success' : 'warning'">
                                                            {{ $check['state'] === 'ok' ? 'Tamam' : 'Eksik' }}
                                                        </x-zolm.status-badge>
                                                    </div>
                                                    <p class="mt-2 text-sm text-slate-500">{{ $check['message'] }}</p>
                                                </div>
                                            @endforeach
                                        </div>

                                        @if(!empty($this->selectedConnectionReadiness['warnings']))
                                            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                                                <p class="text-sm font-medium text-amber-800">Uyarılar</p>
                                                <div class="mt-2 space-y-2">
                                                    @foreach($this->selectedConnectionReadiness['warnings'] as $warning)
                                                        <p class="text-sm text-amber-700">{{ $warning }}</p>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        @if(!empty($this->selectedConnectionReadiness['failures']))
                                            <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3">
                                                <p class="text-sm font-medium text-rose-800">Tamamlanması gerekenler</p>
                                                <div class="mt-2 space-y-2">
                                                    @foreach($this->selectedConnectionReadiness['failures'] as $failure)
                                                        <p class="text-sm text-rose-700">{{ $failure }}</p>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-4">
                                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-slate-900">Tanı bazlı ilk aksiyonlar</p>
                                            <p class="mt-1 text-sm text-slate-500">Ön test ve son senkron kayıtlarına göre bu mağaza için önce bakılması gereken alanlar.</p>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            @if(count($this->selectedStoreGuidanceItems) > 0)
                                                <button type="button"
                                                        wire:click="focusSelectedStoreGuidance"
                                                        class="inline-flex min-h-[36px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                                                    {{ $this->selectedGuidanceFocusLabel() }}
                                                </button>
                                                <button type="button"
                                                        wire:click="syncSelectedStoreGuidance"
                                                        class="inline-flex min-h-[36px] items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-xs font-medium text-white transition hover:bg-slate-800">
                                                    {{ $this->selectedGuidanceSyncLabel() }}
                                                </button>
                                            @endif
                                            <x-zolm.status-badge :tone="count($this->selectedStoreGuidanceItems) > 0 ? 'warning' : 'success'">
                                                {{ count($this->selectedStoreGuidanceItems) > 0 ? count($this->selectedStoreGuidanceItems) . ' öneri' : 'Temiz' }}
                                            </x-zolm.status-badge>
                                        </div>
                                    </div>

                                    <div class="mt-4 space-y-3">
                                        @if($primaryStoreGuidance)
                                            <a href="{{ $this->guidanceRoute($primaryStoreGuidance) }}"
                                               class="block rounded-xl border border-slate-200 bg-white px-4 py-3 transition hover:border-slate-300 hover:bg-slate-50">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div class="min-w-0">
                                                        <p class="text-sm font-medium text-slate-900">{{ $primaryStoreGuidance['title'] }}</p>
                                                        <p class="mt-1 text-sm text-slate-500">{{ $primaryStoreGuidance['recommended_action'] }}</p>
                                                    </div>
                                                    <x-zolm.status-badge :tone="$this->guidanceSeverityTone($primaryStoreGuidance['severity'])">
                                                        {{ $this->guidanceSeverityLabel($primaryStoreGuidance['severity']) }}
                                                    </x-zolm.status-badge>
                                                </div>
                                                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1">
                                                        {{ number_format((int) $primaryStoreGuidance['impact_count'], 0, ',', '.') }} kayıt etkileniyor
                                                    </span>
                                                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1">
                                                        {{ $this->guidanceRouteLabel($primaryStoreGuidance['route']) }}
                                                    </span>
                                                </div>
                                            </a>
                                        @else
                                            <div class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-5 text-sm text-slate-500">
                                                Bu mağaza için henüz kayıtlı bir aksiyon önerisi yok. İlk smoke test veya uyarılı sync sonrası burada sıralanacak.
                                            </div>
                                        @endif

                                        @if(count($secondaryStoreGuidance) > 0)
                                            <button type="button"
                                                    @click="extraGuidanceOpen = !extraGuidanceOpen"
                                                    class="inline-flex min-h-[36px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                                                <span x-show="!extraGuidanceOpen">Diğer önerileri göster</span>
                                                <span x-show="extraGuidanceOpen" x-cloak>Diğer önerileri gizle</span>
                                            </button>

                                            <div x-show="extraGuidanceOpen" x-cloak x-transition.opacity.duration.150ms class="space-y-3">
                                                @foreach($secondaryStoreGuidance as $item)
                                                    <a href="{{ $this->guidanceRoute($item) }}"
                                                       class="block rounded-xl border border-slate-200 bg-white px-4 py-3 transition hover:border-slate-300 hover:bg-slate-50">
                                                        <div class="flex items-start justify-between gap-3">
                                                            <div class="min-w-0">
                                                                <p class="text-sm font-medium text-slate-900">{{ $item['title'] }}</p>
                                                                <p class="mt-1 text-sm text-slate-500">{{ $item['recommended_action'] }}</p>
                                                            </div>
                                                            <x-zolm.status-badge :tone="$this->guidanceSeverityTone($item['severity'])">
                                                                {{ $this->guidanceSeverityLabel($item['severity']) }}
                                                            </x-zolm.status-badge>
                                                        </div>
                                                        <p class="mt-2 text-xs text-slate-500">{{ $item['why'] }}</p>
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </x-zolm.assistant-accordion>

                            @if($this->selectedStoreLegacyProjection)
                                <x-zolm.assistant-accordion grouped name="legacy" eyebrow="Eski veri kuyruğu" title="Eski veri aktarım etkisi" description="Eski muhasebe kuyruğunu ve aktarım sonrası kesin etkiyi burada yönetin." :badge="(int) data_get($this->selectedStoreLegacyProjection, 'pending_rows', 0) > 0 ? 'Kuyruk var' : 'İzle'" :tone="(int) data_get($this->selectedStoreLegacyProjection, 'pending_rows', 0) > 0 ? 'warning' : ((int) data_get($this->selectedStoreLegacyProjection, 'confirmed_orders', 0) > 0 ? 'success' : 'default')">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
                                        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                                            <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Bekleyen</p>
                                            <p class="mt-2 text-xl font-semibold {{ (int) data_get($this->selectedStoreLegacyProjection, 'pending_rows', 0) > 0 ? 'text-amber-600' : 'text-slate-900' }}">{{ number_format((int) data_get($this->selectedStoreLegacyProjection, 'pending_rows', 0), 0, ',', '.') }}</p>
                                        </div>
                                        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                                            <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Projekte</p>
                                            <p class="mt-2 text-xl font-semibold text-slate-900">{{ number_format((int) data_get($this->selectedStoreLegacyProjection, 'projected_rows', 0), 0, ',', '.') }}</p>
                                        </div>
                                        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                                            <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Eski veri olayı</p>
                                            <p class="mt-2 text-xl font-semibold text-slate-900">{{ number_format((int) data_get($this->selectedStoreLegacyProjection, 'legacy_event_orders', 0), 0, ',', '.') }}</p>
                                        </div>
                                        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                                            <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Kesine dönen</p>
                                            <p class="mt-2 text-xl font-semibold {{ (int) data_get($this->selectedStoreLegacyProjection, 'confirmed_orders', 0) > 0 ? 'text-emerald-600' : 'text-slate-900' }}">{{ number_format((int) data_get($this->selectedStoreLegacyProjection, 'confirmed_orders', 0), 0, ',', '.') }}</p>
                                        </div>
                                    </div>

                                    <div class="mt-4 flex flex-col gap-2 rounded-xl border border-slate-200 bg-slate-50/70 px-4 py-3 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                                        <span>
                                            Son aktarım:
                                            <span class="font-medium text-slate-900">
                                                {{ data_get($this->selectedStoreLegacyProjection, 'last_projected_at') ? \Illuminate\Support\Carbon::parse((string) data_get($this->selectedStoreLegacyProjection, 'last_projected_at'))->format('d.m.Y H:i') : 'Henüz yok' }}
                                            </span>
                                        </span>
                                        <a href="{{ route('mp.orders', ['storeFilter' => $selectedStore->id]) }}" class="text-sm font-medium text-slate-700 hover:text-slate-900">
                                            Aktarım ekranına git
                                        </a>
                                    </div>

                                    <div class="mt-4 rounded-xl border border-slate-200 bg-white px-4 py-4">
                                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                            <div class="min-w-0">
                                                <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Pilot aktarım akışı</p>
                                                <h4 class="mt-2 text-sm font-semibold text-slate-900">Önce dry-run, sonra gerçek taşıma</h4>
                                                <p class="mt-2 text-sm text-slate-500">Seçili mağaza için aday eski finans satırlarını önizleyin, sonra aktarımı çalıştırıp kesin etkiyi finans ekranında açın.</p>
                                            </div>
                                            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                                                <button type="button"
                                                        wire:click="previewSelectedStoreLegacyProjection"
                                                        wire:loading.attr="disabled"
                                                        wire:target="previewSelectedStoreLegacyProjection"
                                                        class="inline-flex min-h-[44px] w-full items-center justify-center rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-white sm:w-auto sm:py-2">
                                                    Önizleme yap
                                                </button>
                                                <button type="button"
                                                        wire:click="runSelectedStoreLegacyProjection"
                                                        wire:loading.attr="disabled"
                                                        wire:target="runSelectedStoreLegacyProjection"
                                                        class="inline-flex min-h-[44px] w-full items-center justify-center rounded-lg bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 sm:w-auto sm:py-2">
                                                    Aktarımı çalıştır
                                                </button>
                                                <a href="{{ route('mp.finance', ['storeFilter' => $selectedStore->id, 'marketplaceFilter' => $selectedStore->marketplace, 'legacyProjectionFilter' => 'backlog']) }}"
                                                   class="inline-flex min-h-[44px] w-full items-center justify-center rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-white sm:w-auto sm:py-2">
                                                    Finans backlogu aç
                                                </a>
                                                @if((int) data_get($this->selectedStoreLegacyProjection, 'confirmed_orders', 0) > 0)
                                                    <a href="{{ route('mp.finance', ['storeFilter' => $selectedStore->id, 'marketplaceFilter' => $selectedStore->marketplace, 'legacyProjectionFilter' => 'confirmed', 'financialStateFilter' => 'ready']) }}"
                                                       class="inline-flex min-h-[44px] w-full items-center justify-center rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-white sm:w-auto sm:py-2">
                                                        Kesin etkiyi aç
                                                    </a>
                                                @endif
                                            </div>
                                        </div>

                                        @if($legacyProjectionPreview)
                                            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50/70 px-4 py-3 text-sm text-slate-600">
                                                <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                                                    <div class="min-w-0">
                                                        <p class="font-medium text-slate-900">
                                                            {{ !empty($legacyProjectionPreview['executed']) ? 'Son aktarım sonucu' : 'Önizleme sonucu' }}
                                                        </p>
                                                        <p class="mt-1 text-xs text-slate-500">
                                                            {{ !empty($legacyProjectionPreview['generated_at']) ? \Illuminate\Support\Carbon::parse((string) $legacyProjectionPreview['generated_at'])->format('d.m.Y H:i') : 'Şimdi' }}
                                                        </p>
                                                    </div>
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600">
                                                            Aday {{ number_format((int) data_get($legacyProjectionPreview, 'projected_rows', 0), 0, ',', '.') }}
                                                        </span>
                                                        <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600">
                                                            Yeni {{ number_format((int) data_get($legacyProjectionPreview, 'created', 0), 0, ',', '.') }}
                                                        </span>
                                                        <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600">
                                                            Güncelleme {{ number_format((int) data_get($legacyProjectionPreview, 'updated', 0), 0, ',', '.') }}
                                                        </span>
                                                        <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600">
                                                            Etkilenen sipariş {{ number_format((int) data_get($legacyProjectionPreview, 'impacted_orders', 0), 0, ',', '.') }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif

                                        <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                                            <div class="rounded-xl border border-slate-200 bg-slate-50/70 px-4 py-3">
                                                <p class="text-xs uppercase tracking-[0.16em] text-slate-500">CLI önizleme</p>
                                                <p class="mt-2 break-all font-mono text-xs text-slate-700">{{ $this->selectedStoreLegacyProjectionDryRunCommand }}</p>
                                            </div>
                                            <div class="rounded-xl border border-slate-200 bg-slate-50/70 px-4 py-3">
                                                <p class="text-xs uppercase tracking-[0.16em] text-slate-500">CLI Gerçek Çalıştırma</p>
                                                <p class="mt-2 break-all font-mono text-xs text-slate-700">{{ $this->selectedStoreLegacyProjectionRunCommand }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </x-zolm.assistant-accordion>
                            @endif

                            <x-zolm.assistant-accordion grouped name="automation" eyebrow="Webhook ve smoke" title="Webhook, doğrulama ve smoke test" description="Webhook secret, bağlantı doğrulaması ve smoke test komutları burada tutulur." badge="Operasyon araçları">
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-4">
                                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-slate-900">Webhook URL</p>
                                            <p class="mt-2 break-all text-sm text-slate-500">{{ $this->webhookUrlPreview }}</p>
                                        </div>
                                        <div class="flex flex-col sm:flex-row gap-2">
                                            <button type="button" wire:click="verifyConnection" wire:loading.attr="disabled" wire:target="verifyConnection" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-white">
                                                Bağlantıyı doğrula
                                            </button>
                                            <button type="button" wire:click="regenerateWebhookSecret" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-white">
                                                Secret yenile
                                            </button>
                                        </div>
                                    </div>
                                    <div class="mt-4">
                                        <label class="{{ $labelClass }}">Webhook secret</label>
                                        <input type="text" wire:model.defer="connectionForm.webhookSecret" class="{{ $inputClass }}" placeholder="Webhook secret">
                                    </div>
                                </div>

                                <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-4">
                                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-slate-900">Ön test komutu</p>
                                            <p class="mt-1 text-sm text-slate-500">Kimlik bilgisi geldikten sonra eşleme kalitesini test edip sonucu senkron geçmişine kaydedeceğiz.</p>
                                        </div>
                                        <x-zolm.status-badge tone="info">CLI + Kayıt Hazır</x-zolm.status-badge>
                                    </div>
                                    <div class="mt-4 rounded-xl border border-slate-200 bg-white px-4 py-3">
                                        <p class="break-all font-mono text-xs text-slate-700">{{ $this->smokeTestCommand }}</p>
                                    </div>
                                </div>
                            </x-zolm.assistant-accordion>
                        </div>

                        <div class="flex justify-end">
                            <x-zolm.primary-button type="submit" wire:loading.attr="disabled" wire:target="saveConnection">
                                <span wire:loading.remove wire:target="saveConnection">Bağlantı bilgilerini kaydet</span>
                                <span wire:loading wire:target="saveConnection">Kaydediliyor...</span>
                            </x-zolm.primary-button>
                        </div>
                    </form>
                @else
                    <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-5 py-6 text-sm text-slate-500">
                        Bağlantı bilgilerini düzenlemek için yukarıdan bir mağaza seçin ya da yeni bir mağaza oluşturun.
                    </div>
                @endif
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Senkron ve Optimizasyon Profili</h2>
            <p class="mt-1 text-sm text-slate-500">Sipariş, finans, ürün ve webhook akışını mağaza bazında özelleştirin.</p>
            <div class="mt-4">
                @if($selectedStore)
                    <form wire:submit.prevent="saveSyncProfile" class="space-y-5">
                        @if($this->selectedStoreSupportsSafeProfile)
                            <div class="rounded-xl border border-amber-200 bg-amber-50/80 p-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-amber-900">{{ $this->selectedStoreSafeProfileTitle }}</p>
                                        <p class="mt-1 text-sm text-amber-800">
                                            {{ $this->selectedStoreSafeProfileDescription }}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="applySelectedStoreSafeProfile"
                                        wire:loading.attr="disabled"
                                        wire:target="applySelectedStoreSafeProfile"
                                        class="w-full rounded-lg border border-amber-300 bg-white px-4 py-3 text-sm font-medium text-amber-900 transition hover:bg-amber-100 sm:w-auto sm:py-2"
                                    >
                                        <span wire:loading.remove wire:target="applySelectedStoreSafeProfile">{{ $this->selectedStoreSafeProfileButtonLabel }}</span>
                                        <span wire:loading wire:target="applySelectedStoreSafeProfile">Uygulanıyor...</span>
                                    </button>
                                </div>

                                @if($this->selectedStoreSafeProfileStatus)
                                    <div class="mt-4 rounded-2xl border {{ $this->selectedStoreSafeProfileStatus['aligned'] ? 'border-emerald-200 bg-emerald-50/80' : 'border-amber-200 bg-white/80' }} p-4">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold {{ $this->selectedStoreSafeProfileStatus['aligned'] ? 'text-emerald-900' : 'text-amber-900' }}">
                                                    {{ $this->selectedStoreSafeProfileStatus['aligned'] ? 'Form güvenli profile uyumlu' : 'Güvenli profil ile fark var' }}
                                                </p>
                                                <p class="mt-1 text-sm {{ $this->selectedStoreSafeProfileStatus['aligned'] ? 'text-emerald-800' : 'text-amber-800' }}">
                                                    {{ $this->selectedStoreSafeProfileStatus['summary'] }}
                                                </p>
                                            </div>
                                            <x-zolm.status-badge :tone="$this->selectedStoreSafeProfileStatus['aligned'] ? 'success' : 'warning'">
                                                {{ $this->selectedStoreSafeProfileStatus['aligned'] ? 'Uyumlu' : 'Düzelt önerilir' }}
                                            </x-zolm.status-badge>
                                        </div>

                                        @if(!$this->selectedStoreSafeProfileStatus['aligned'])
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                @foreach(collect($this->selectedStoreSafeProfileStatus['mismatches'])->take(4) as $mismatch)
                                                    <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-900">
                                                        {{ $mismatch['label'] }}: {{ is_bool($mismatch['expected']) ? ($mismatch['expected'] ? 'Açık' : 'Kapalı') : $mismatch['expected'] }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                <div class="mt-4 rounded-2xl border border-slate-200 bg-white/80 p-4">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-slate-900">{{ $this->selectedStoreWebhookTopicPresetLabel() }}</p>
                                            <p class="mt-1 text-sm text-slate-500">
                                                {{ $this->selectedStoreWebhookTopicPresetHint() }}
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            wire:click="applyRecommendedWebhookTopics"
                                            wire:loading.attr="disabled"
                                            wire:target="applyRecommendedWebhookTopics"
                                            class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2"
                                        >
                                            <span wire:loading.remove wire:target="applyRecommendedWebhookTopics">Önerilen topic setini uygula</span>
                                            <span wire:loading wire:target="applyRecommendedWebhookTopics">Uygulanıyor...</span>
                                        </button>
                                    </div>
                                    <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        @foreach($this->selectedStoreRecommendedWebhookTopics() as $topic)
                                            <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-slate-700">
                                                <input type="checkbox" wire:model.defer="syncForm.webhookTopics" value="{{ $topic }}" class="mt-1 h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                                                <span class="font-medium">{{ $topic }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <p class="mt-3 text-xs text-slate-500">
                                        {{ $this->selectedStoreWebhookTopicPresetNote() }}
                                    </p>
                                </div>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 lg:gap-4">
                            <div>
                                <label class="{{ $labelClass }}">Sipariş senkronu (dk)</label>
                                <input type="number" min="5" wire:model.defer="syncForm.ordersPollMinutes" class="{{ $inputClass }}">
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">Finans senkronu (dk)</label>
                                <input type="number" min="5" wire:model.defer="syncForm.financePollMinutes" class="{{ $inputClass }}">
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">Ürün senkronu (dk)</label>
                                <input type="number" min="5" wire:model.defer="syncForm.productsPollMinutes" class="{{ $inputClass }}">
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">Backfill modu</label>
                                <select wire:model.live="syncForm.backfillMode" class="{{ $inputClass }}">
                                    @foreach($backfillOptions as $backfillKey => $backfillLabel)
                                        <option value="{{ $backfillKey }}">{{ $backfillLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">Maks. paralel iş</label>
                                <input type="number" min="1" max="5" wire:model.defer="syncForm.maxParallelJobs" class="{{ $inputClass }}">
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">Jitter (sn)</label>
                                <input type="number" min="0" max="60" wire:model.defer="syncForm.requestJitterSeconds" class="{{ $inputClass }}">
                            </div>

                            @if(($syncForm['backfillMode'] ?? null) === 'custom')
                                <div>
                                    <label class="{{ $labelClass }}">Özel başlangıç</label>
                                    <input type="datetime-local" wire:model.defer="syncForm.backfillCustomFrom" class="{{ $inputClass }}">
                                    @error('syncForm.backfillCustomFrom') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="{{ $labelClass }}">Özel bitiş</label>
                                    <input type="datetime-local" wire:model.defer="syncForm.backfillCustomTo" class="{{ $inputClass }}">
                                    @error('syncForm.backfillCustomTo') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                </div>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 lg:gap-4">
                            <label class="{{ $toggleLabelClass }}">
                                <input type="checkbox" wire:model.defer="syncForm.ordersEnabled" class="mt-1 h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                                <span><span class="block text-sm font-medium text-slate-900">Sipariş senkronu aktif</span><span class="mt-1 block text-sm text-slate-500">15 dakikalık tarama ile sipariş ve durum verisi çekilsin.</span></span>
                            </label>
                            <label class="{{ $toggleLabelClass }}">
                                <input type="checkbox" wire:model.defer="syncForm.financeEnabled" class="mt-1 h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                                <span><span class="block text-sm font-medium text-slate-900">Finans senkronu aktif</span><span class="mt-1 block text-sm text-slate-500">Hak ediş, komisyon ve kesintiler ayrı akışta toplansın.</span></span>
                            </label>
                            <label class="{{ $toggleLabelClass }}">
                                <input type="checkbox" wire:model.defer="syncForm.productsEnabled" class="mt-1 h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                                <span><span class="block text-sm font-medium text-slate-900">Ürün senkronu aktif</span><span class="mt-1 block text-sm text-slate-500">Listeleme, stok, fiyat ve yayın durumu çekilsin.</span></span>
                            </label>
                            <label class="{{ $toggleLabelClass }}">
                                <input type="checkbox" wire:model.defer="syncForm.webhookEnabled" class="mt-1 h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                                <span><span class="block text-sm font-medium text-slate-900">Webhook aktif</span><span class="mt-1 block text-sm text-slate-500">Anlık olay geldiğinde kontrollü yenileme tetiklensin.</span></span>
                            </label>
                            <label class="{{ $toggleLabelClass }}">
                                <input type="checkbox" wire:model.defer="syncForm.pricePushEnabled" class="mt-1 h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                                <span><span class="block text-sm font-medium text-slate-900">Fiyat gönderimi aktif</span><span class="mt-1 block text-sm text-slate-500">ZOLM ileride ana fiyat kaynağı olarak davransın.</span></span>
                            </label>
                            <label class="{{ $toggleLabelClass }}">
                                <input type="checkbox" wire:model.defer="syncForm.stockPushEnabled" class="mt-1 h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                                <span><span class="block text-sm font-medium text-slate-900">Stok gönderimi aktif</span><span class="mt-1 block text-sm text-slate-500">Stok çıkışları kanallara geri gönderilebilsin.</span></span>
                            </label>
                            <label class="{{ $toggleLabelClass }}">
                                <input type="checkbox" wire:model.defer="syncForm.autoMatchEnabled" class="mt-1 h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                                <span><span class="block text-sm font-medium text-slate-900">Otomatik ürün eşleştirme</span><span class="mt-1 block text-sm text-slate-500">Ana anahtar olarak stok kodu ile bağlansın.</span></span>
                            </label>
                            <label class="{{ $toggleLabelClass }}">
                                <input type="checkbox" wire:model.defer="syncForm.barcodeFallbackEnabled" class="mt-1 h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                                <span><span class="block text-sm font-medium text-slate-900">Barkod fallback</span><span class="mt-1 block text-sm text-slate-500">Stock code eşleşmezse barkod denensin.</span></span>
                            </label>
                            <label class="{{ $toggleLabelClass }}">
                                <input type="checkbox" wire:model.defer="syncForm.strictUniqueMatchEnabled" class="mt-1 h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                                <span><span class="block text-sm font-medium text-slate-900">Sadece tekil eşleşme</span><span class="mt-1 block text-sm text-slate-500">Birden fazla aday varsa manuel incelemeye düşür.</span></span>
                            </label>
                            <label class="{{ $toggleLabelClass }}">
                                <input type="checkbox" wire:model.defer="syncForm.nightlyRepairSyncEnabled" class="mt-1 h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                                <span><span class="block text-sm font-medium text-slate-900">Gece onarım senkronu</span><span class="mt-1 block text-sm text-slate-500">Son günlerde kaçan kayıtlar gece tekrar taransın.</span></span>
                            </label>
                        </div>

                        <div class="flex justify-end">
                            <x-zolm.primary-button type="submit" wire:loading.attr="disabled" wire:target="saveSyncProfile">
                                <span wire:loading.remove wire:target="saveSyncProfile">Senkron profilini kaydet</span>
                                <span wire:loading wire:target="saveSyncProfile">Kaydediliyor...</span>
                            </x-zolm.primary-button>
                        </div>
                    </form>
                @else
                    <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-5 py-6 text-sm text-slate-500">
                        Senkron profili düzenlemek için önce bir mağaza seçin.
                    </div>
                @endif
            </div>
        </section>
        {{-- CANLI TEST HAZIRLIĞI --}}
        <section x-data="{ readinessOpen: false }" class="rounded-2xl border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3 cursor-pointer" @click="readinessOpen = !readinessOpen">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Canlı Test Hazırlığı</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Ön test hazır {{ number_format($this->readinessSummary['totals']['ready'] ?? 0, 0, ',', '.') }} ·
                        Kontrol {{ number_format($this->readinessSummary['totals']['warning'] ?? 0, 0, ',', '.') }} ·
                        Eksik {{ number_format($this->readinessSummary['totals']['missing'] ?? 0, 0, ',', '.') }}
                    </p>
                </div>
                <svg class="h-5 w-5 text-slate-400 transition" :class="{ 'rotate-180': readinessOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 9l-7 7-7-7" /></svg>
            </div>
            <div x-cloak x-show="readinessOpen" x-transition class="mt-4 space-y-3">
                @if($this->readinessSummary)
                    @foreach(collect($this->readinessSummary['rows'])->take(4) as $row)
                        @php
                            $rowTone = match($row['state']) { 'ready' => 'emerald', 'warning' => 'amber', 'missing' => 'rose', default => 'slate' };
                        @endphp
                        <a href="{{ route('mp.integrations', ['store' => $row['store_id']]) }}" class="block rounded-lg border border-slate-200 bg-slate-50/60 p-3 transition hover:border-slate-300 hover:bg-white">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-900">{{ $row['store_name'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $this->providerOptions[$row['marketplace']] ?? ucfirst($row['marketplace']) }}</p>
                                </div>
                                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium {{ $rowTone === 'emerald' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($rowTone === 'amber' ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-rose-200 bg-rose-50 text-rose-700') }}">
                                    {{ $row['state'] === 'ready' ? 'Hazır' : ($row['state'] === 'warning' ? 'Kontrol et' : 'Eksik') }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm text-slate-600">{{ $row['first_failure'] ?: ($row['first_warning'] ?: $row['summary']) }}</p>
                        </a>
                    @endforeach
                @else
                    <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">Hazırlık özeti için önce en az bir mağaza ekleyin.</div>
                @endif
            </div>
        </section>

        {{-- BAĞLAYICI KATALOĞU --}}
        <section x-data="{ catalogOpen: false }" class="rounded-2xl border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3 cursor-pointer" @click="catalogOpen = !catalogOpen">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Bağlayıcı Kataloğu</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ count($providerCatalog) }} kanal</p>
                </div>
                <svg class="h-5 w-5 text-slate-400 transition" :class="{ 'rotate-180': catalogOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 9l-7 7-7-7" /></svg>
            </div>
            <div x-cloak x-show="catalogOpen" x-transition class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach($providerCatalog as $providerKey => $provider)
                    <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900">{{ $provider['label'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $provider['group'] }}</p>
                            </div>
                            <x-zolm.status-badge :tone="match($provider['status']) { 'ready' => 'success', 'priority' => 'success', 'pilot' => 'info', default => 'default' }">
                                {{ match($provider['status']) { 'ready' => 'Hazır', 'priority' => 'İlk faz', 'pilot' => 'Pilot', default => 'Sırada' } }}
                            </x-zolm.status-badge>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1">
                            @foreach($provider['supports'] as $feature => $enabled)
                                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs {{ $enabled ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-500' }}">
                                    {{ match($feature) { 'orders' => 'Sipariş', 'products' => 'Ürün', 'finance' => 'Finans', 'webhooks' => 'Webhook', 'price_push' => 'Fiyat gönderimi', 'stock_push' => 'Stok gönderimi', default => ucfirst($feature) } }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- SON WEBHOOK OLAYLARI --}}
        <section x-data="{ webhooksOpen: false }" class="rounded-2xl border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3 cursor-pointer" @click="webhooksOpen = !webhooksOpen">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Son Webhook Olayları</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ count($recentWebhookEvents) }} olay</p>
                </div>
                <svg class="h-5 w-5 text-slate-400 transition" :class="{ 'rotate-180': webhooksOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 9l-7 7-7-7" /></svg>
            </div>
            <div x-cloak x-show="webhooksOpen" x-transition class="mt-4 space-y-2">
                @forelse($recentWebhookEvents as $event)
                    <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-900">{{ $event->event_type ?: 'Olay tipi bekleniyor' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $event->received_at?->format('d.m.Y H:i') ?: $event->created_at?->format('d.m.Y H:i') }}</p>
                            </div>
                            <x-zolm.status-badge :tone="$event->signature_valid ? 'success' : 'warning'">{{ $event->signature_valid ? 'Doğrulandı' : 'Kontrol et' }}</x-zolm.status-badge>
                        </div>
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-500">Henüz webhook kaydı yok.</div>
                @endforelse
            </div>
        </section>

        {{-- SON SENKRON KAYITLARI --}}
        <section x-data="{ syncsOpen: false }" class="rounded-2xl border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3 cursor-pointer" @click="syncsOpen = !syncsOpen">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Son Senkron Kayıtları</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ count($recentSyncRuns) }} kayıt</p>
                </div>
                <svg class="h-5 w-5 text-slate-400 transition" :class="{ 'rotate-180': syncsOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 9l-7 7-7-7" /></svg>
            </div>
            <div x-cloak x-show="syncsOpen" x-transition class="mt-4 space-y-2">
                @forelse($recentSyncRuns as $run)
                    <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-900">{{ ucfirst(str_replace('_', ' ', $run->sync_type)) }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ ucwords(str_replace('_', ' ', (string) $run->trigger_type)) }} · {{ $run->created_at?->format('d.m.Y H:i') }}</p>
                            </div>
                            <x-zolm.status-badge :tone="match($run->status) { 'completed' => 'success', 'failed' => 'danger', 'queued' => 'warning', default => 'info' }">{{ ucfirst($run->status) }}</x-zolm.status-badge>
                        </div>
                        <div class="mt-2 grid grid-cols-3 gap-2 text-xs text-slate-500">
                            <div>Alınan <span class="font-semibold text-slate-900">{{ $run->items_received }}</span></div>
                            <div>Oluşan <span class="font-semibold text-slate-900">{{ $run->items_created }}</span></div>
                            <div>Güncel <span class="font-semibold text-slate-900">{{ $run->items_updated }}</span></div>
                        </div>
                        <x-zolm.sync-diagnostics :run="$run" compact />
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-500">İlk senkron çalıştığında kayıtlar burada görünecek.</div>
                @endforelse
            </div>
        </section>

        {{-- SMOKE TEST GEÇMİŞİ --}}
        <section x-data="{ smokeOpen: false }" class="rounded-2xl border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3 cursor-pointer" @click="smokeOpen = !smokeOpen">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Ön Test Geçmişi</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ count($recentSmokeRuns) }} koşu</p>
                </div>
                <svg class="h-5 w-5 text-slate-400 transition" :class="{ 'rotate-180': smokeOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 9l-7 7-7-7" /></svg>
            </div>
            <div x-cloak x-show="smokeOpen" x-transition class="mt-4 space-y-2">
                @forelse($recentSmokeRuns as $run)
                    <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-900">{{ ucfirst(str_replace('_', ' ', $run->sync_type)) }} ön testi</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $run->triggerLabel() }} · {{ $run->created_at?->format('d.m.Y H:i') }}</p>
                            </div>
                            <x-zolm.status-badge :tone="match($run->status) { 'completed' => 'success', 'failed' => 'danger', default => 'warning' }">{{ ucfirst($run->status) }}</x-zolm.status-badge>
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-2 text-xs text-slate-500">
                            <div>Alınan <span class="font-semibold text-slate-900">{{ $run->items_received }}</span></div>
                            <div>Uyarı <span class="font-semibold text-slate-900">{{ $run->diagnosticWarningCount() }}</span></div>
                        </div>
                        @if(data_get($run->notes_json, 'last_error'))
                            <p class="mt-2 text-xs text-rose-600">{{ data_get($run->notes_json, 'last_error') }}</p>
                        @endif
                        <x-zolm.sync-diagnostics :run="$run" compact />
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-500">Henüz smoke test kaydı yok.</div>
                @endforelse
            </div>
        </section>
    </div>
</div>
