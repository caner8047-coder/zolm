@php
    $currentProfitMarketplace = $productProfitMarketplaceOptions[$defaultProfitMarketplace] ?? 'Mağaza ortalaması';
    $currentLabelTemplate = $labelTemplateOptions[$labelPrintSettings['template'] ?? 'courier'] ?? 'Kurye standart';
    $currentLabelPaper = $labelPaperOptions[$labelPrintSettings['paper'] ?? 'thermal_100x150'] ?? 'Termal 100x150';
    $currentDispatchTemplate = $dispatchTemplateOptions[$dispatchPrintSettings['template'] ?? 'classic'] ?? 'Klasik irsaliye';
    $currentDispatchPaper = $dispatchPaperOptions[$dispatchPrintSettings['paper'] ?? 'a4'] ?? 'A4 dikey';

    $generalToggles = [
        [
            'title' => 'Bilgilendirici yardım ipuçlarını göster',
            'description' => 'KPI, tablo ve kritik aksiyon açıklamalarını pazaryeri ekranlarında görünür tutar.',
            'model' => 'helpTipsEnabled',
            'enabled' => $helpTipsEnabled,
            'badge' => $helpTipsEnabled ? 'Aktif' : 'Kapalı',
        ],
        [
            'title' => 'Reçete maliyetini stok kartına işle',
            'description' => 'Aktif reçete maliyetini aynı stok koduyla eşleşen ürün kartına yazar.',
            'model' => 'recipeCostSyncEnabled',
            'enabled' => $recipeCostSyncEnabled,
            'badge' => $recipeCostSyncEnabled ? 'Otomatik açık' : 'Kapalı',
        ],
    ];

    $perPageOptions = [10 => '10', 20 => '20', 25 => '25', 50 => '50', 100 => '100'];
    $dateRangeOptions = [0 => 'Tüm zamanlar', 7 => '7 gün', 30 => '30 gün', 60 => '60 gün', 90 => '90 gün', 180 => '180 gün', 365 => '365 gün'];

    $labelToggles = [
        ['label' => 'Gönderici', 'hint' => 'Firma alanı', 'model' => 'labelPrintSettings.show_sender'],
        ['label' => 'Takip no', 'hint' => 'Başlık alanı', 'model' => 'labelPrintSettings.show_tracking_number'],
        ['label' => 'Mağaza', 'hint' => 'Pazaryeri adı', 'model' => 'labelPrintSettings.show_marketplace'],
        ['label' => 'Telefon', 'hint' => 'Alıcı telefonu', 'model' => 'labelPrintSettings.show_customer_phone'],
        ['label' => 'Ürünler', 'hint' => 'Detay satırları', 'model' => 'labelPrintSettings.show_items'],
        ['label' => 'Barkod metni', 'hint' => 'Okunabilir kod', 'model' => 'labelPrintSettings.show_barcode_text'],
        ['label' => 'Ürün özeti', 'hint' => 'Kısa rozetler', 'model' => 'labelPrintSettings.show_item_summary'],
    ];

    $dispatchToggles = [
        ['label' => 'Gönderici', 'hint' => 'Firma kartı', 'model' => 'dispatchPrintSettings.show_sender'],
        ['label' => 'Telefon', 'hint' => 'Alıcı telefonu', 'model' => 'dispatchPrintSettings.show_customer_phone'],
        ['label' => 'Fatura', 'hint' => 'Vergi bilgisi', 'model' => 'dispatchPrintSettings.show_billing_info'],
        ['label' => 'Ürün tablosu', 'hint' => 'Adet ve barkod', 'model' => 'dispatchPrintSettings.show_items'],
        ['label' => 'Paket barkodu', 'hint' => 'Üst blok', 'model' => 'dispatchPrintSettings.show_barcode'],
        ['label' => 'Barkod metni', 'hint' => 'Okunabilir kod', 'model' => 'dispatchPrintSettings.show_barcode_text'],
        ['label' => 'Mağaza', 'hint' => 'Pazaryeri adı', 'model' => 'dispatchPrintSettings.show_marketplace'],
        ['label' => 'İmza alanı', 'hint' => 'Alt kutu', 'model' => 'dispatchPrintSettings.show_signature_area'],
    ];
@endphp

<div class="-mt-1 w-full space-y-4 lg:-mt-4 lg:space-y-6">
    @if (session('settings_success'))
        <div class="rounded-[8px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('settings_success') }}
        </div>
    @endif

    @if (session('document_settings_success'))
        <div class="rounded-[8px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('document_settings_success') }}
        </div>
    @endif

    @if (session('delivery_term_settings_success'))
        <div class="rounded-[8px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('delivery_term_settings_success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-[8px] border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Pazaryeri Ayarları</p>
                <h1 class="mt-2 text-xl font-semibold text-slate-900 lg:text-2xl">Daha az seçenek, daha net kontrol.</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
                    Genel görünüm, kârlılık hesabı ve çıktı şablonlarını tek ekrandan yönetin. Değişiklikler kaydedildikten sonra ilgili pazaryeri modüllerinde kullanılır.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 lg:min-w-[520px]">
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-[11px] font-medium uppercase tracking-[0.16em] text-slate-400">Yardım</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ $helpTipsEnabled ? 'Aktif' : 'Kapalı' }}</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-[11px] font-medium uppercase tracking-[0.16em] text-slate-400">Kâr görünümü</p>
                    <p class="mt-2 truncate text-sm font-semibold text-slate-900">{{ $currentProfitMarketplace }}</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-[11px] font-medium uppercase tracking-[0.16em] text-slate-400">Çıktı</p>
                    <p class="mt-2 truncate text-sm font-semibold text-slate-900">{{ $currentLabelPaper }}</p>
                </div>
            </div>
        </div>
    </section>

    <section data-testid="delivery-term-settings"
             class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Ürün operasyonu</p>
                <h2 class="mt-2 text-lg font-semibold text-slate-900">Termin görünümü</h2>
                <p class="mt-1 max-w-3xl text-sm leading-5 text-slate-500">
                    Ürün tablosundaki teslimat etiketlerini firmanızın hazırlık kapasitesine göre sınıflandırın. Çok mağazalı ürünlerde en uzun termin süresi esas alınır.
                </p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                <button type="button"
                        wire:click="saveDeliveryTermSettings"
                        wire:loading.attr="disabled"
                        wire:target="saveDeliveryTermSettings"
                        class="inline-flex w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto sm:py-2">
                    <span wire:loading.remove wire:target="saveDeliveryTermSettings">Termin ayarlarını kaydet</span>
                    <span wire:loading wire:target="saveDeliveryTermSettings">Kaydediliyor...</span>
                </button>
                <button type="button"
                        wire:click="resetDeliveryTermSettings"
                        wire:loading.attr="disabled"
                        class="inline-flex w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto sm:py-2">
                    Varsayılan
                </button>
            </div>
        </div>

        <div class="mt-4 rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-[6px] border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                    Hızlı teslimat · 0–{{ $deliveryFastMaxDays }} gün
                </span>
                <span class="rounded-[6px] border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                    Standart · {{ $deliveryFastMaxDays + 1 }}–{{ $deliveryStandardMaxDays }} gün
                </span>
                <span class="rounded-[6px] border border-orange-200 bg-orange-50 px-2.5 py-1 text-xs font-semibold text-orange-700">
                    Yavaş gönderim · {{ $deliveryStandardMaxDays + 1 }}–{{ $deliverySlowMaxDays }} gün
                </span>
                <span class="rounded-[6px] border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">
                    Çok yavaş · {{ $deliverySlowMaxDays + 1 }}+ gün
                </span>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Hızlı teslimat üst sınırı</label>
                    <div class="relative">
                        <input type="number"
                               min="0"
                               max="363"
                               wire:model.live="deliveryFastMaxDays"
                               class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-4 py-3 pr-12 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">gün</span>
                    </div>
                    @error('deliveryFastMaxDays')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Standart teslimat üst sınırı</label>
                    <div class="relative">
                        <input type="number"
                               min="1"
                               max="364"
                               wire:model.live="deliveryStandardMaxDays"
                               class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-4 py-3 pr-12 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">gün</span>
                    </div>
                    @error('deliveryStandardMaxDays')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Yavaş gönderim üst sınırı</label>
                    <div class="relative">
                        <input type="number"
                               min="2"
                               max="365"
                               wire:model.live="deliverySlowMaxDays"
                               class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-4 py-3 pr-12 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">gün</span>
                    </div>
                    @error('deliverySlowMaxDays')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <p class="mt-3 text-[11px] leading-4 text-slate-500">
                Eşikler artan sırada olmalıdır. Son sınırın üzerindeki tüm kayıtlar otomatik olarak “Çok yavaş” sınıfına alınır.
            </p>
        </div>
    </section>

    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Genel</p>
                <h2 class="mt-2 text-lg font-semibold text-slate-900">Arayüz ve ürün hesabı</h2>
                <p class="mt-1 text-sm text-slate-500">Pazaryeri ekranlarının davranışını belirleyen ortak ayarlar.</p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                <button
                    type="button"
                    wire:click="saveSettings"
                    wire:loading.attr="disabled"
                    wire:target="saveSettings"
                    class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto sm:py-2"
                >
                    <span wire:loading.remove wire:target="saveSettings">Kaydet</span>
                    <span wire:loading wire:target="saveSettings">Kaydediliyor...</span>
                </button>

                <button
                    type="button"
                    wire:click="resetUiSettings"
                    wire:loading.attr="disabled"
                    class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto sm:py-2"
                >
                    Varsayılan
                </button>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(320px,0.55fr)]">
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-slate-900">Ürün kârlılığı ve komisyon</h3>
                        <p class="mt-1 text-sm leading-5 text-slate-500">Ürünlerde varsayılan kâr hesabı ve WooCommerce kesinti oranı.</p>
                    </div>
                    <span class="inline-flex w-fit items-center rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-600">
                        {{ $currentProfitMarketplace }}
                    </span>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Varsayılan pazaryeri</label>
                        <select wire:model.live="defaultProfitMarketplace" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                            @foreach($productProfitMarketplaceOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('defaultProfitMarketplace')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">WooCommerce komisyonu</label>
                        <div class="relative">
                            <input type="number" min="0" max="100" step="0.01" wire:model.live="woocommerceCommissionRate" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 pr-9 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm font-medium text-slate-400">%</span>
                        </div>
                        @error('woocommerceCommissionRate')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <p class="mt-3 text-xs leading-5 text-slate-500">
                    WooCommerce için API komisyonu yoksa burada girilen oran kullanılır.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-3">
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                    <h3 class="text-sm font-semibold text-slate-900">Sayfa başına kayıt</h3>
                    <p class="mt-1 text-sm leading-5 text-slate-500">Liste ekranlarındaki varsayılan sayfa boyutu.</p>
                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Siparişler</label>
                            <select wire:model.live="ordersPerPage" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                                @foreach($perPageOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Ürünler</label>
                            <select wire:model.live="productsPerPage" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                                @foreach($perPageOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                    <h3 class="text-sm font-semibold text-slate-900">Varsayılan tarih aralığı</h3>
                    <p class="mt-1 text-sm leading-5 text-slate-500">Ekranlar ilk açıldığında kullanılacak tarih filtresi.</p>
                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Siparişler</label>
                            <select wire:model.live="ordersDefaultDateRangeDays" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                                @foreach($dateRangeOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Finans</label>
                            <select wire:model.live="financeDefaultDateRangeDays" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                                @foreach($dateRangeOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                    <h3 class="text-sm font-semibold text-slate-900">Trendyol zaman damgası ofseti</h3>
                    <p class="mt-1 text-sm leading-5 text-slate-500">Trendyol'dan gelen numeric timestamp'lerden çıkarılan saniye. Varsayılan 10800 (Türkiye/+03:00). Pozitif değer saati geri alır, negatif değer ileri taşır.</p>
                    <div class="mt-3">
                        <label class="mb-1 block text-xs font-medium text-slate-500">Saniye (-43200–50400)</label>
                        <input type="number" min="-43200" max="50400" wire:model.defer="trendyolTimestampOffsetSeconds" class="w-full min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                    </div>
                </div>

                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                    <h3 class="text-sm font-semibold text-slate-900">Eşleştirme ağırlıkları</h3>
                    <p class="mt-1 text-sm leading-5 text-slate-500">Aday ürünleri sıralarken kullanılan sinyal puanları. 0 girilirse o sinyal devre dışı kalır.</p>
                    <div class="mt-3">
                        <label class="mb-1 block text-xs font-medium text-slate-500">Otomatik önerme eşiği (1–500)</label>
                        <input type="number" min="1" max="500" wire:model.defer="autoRecommendThreshold" class="w-full min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Barkod birebir</label>
                            <input type="number" min="0" max="500" wire:model.defer="matchingWeights.barcode_exact" class="w-full min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Stok kodu birebir</label>
                            <input type="number" min="0" max="500" wire:model.defer="matchingWeights.stock_code_exact" class="w-full min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Model birebir</label>
                            <input type="number" min="0" max="500" wire:model.defer="matchingWeights.model_exact" class="w-full min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Model ailesi</label>
                            <input type="number" min="0" max="500" wire:model.defer="matchingWeights.model_family" class="w-full min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Marka</label>
                            <input type="number" min="0" max="500" wire:model.defer="matchingWeights.brand_exact" class="w-full min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Kategori</label>
                            <input type="number" min="0" max="500" wire:model.defer="matchingWeights.category_exact" class="w-full min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Başlık token</label>
                            <input type="number" min="0" max="500" wire:model.defer="matchingWeights.title_token" class="w-full min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Başlık üst sınır</label>
                            <input type="number" min="0" max="500" wire:model.defer="matchingWeights.title_max" class="w-full min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="mb-1 block text-xs font-medium text-slate-500">Arama durak kelimeleri</label>
                        <textarea wire:model.defer="matchingStopWords" rows="2" class="w-full min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm" placeholder="virgül veya yeni satırla ayırın"></textarea>
                        <p class="mt-1 text-[11px] leading-4 text-slate-400">Bu kelimeler aday arama token'larından çıkarılır; skor ağırlıklarını değiştirmez.</p>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Arama limiti (1–100)</label>
                            <input type="number" min="1" max="100" wire:model.defer="candidateSearchLimit" class="w-full min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Sonuç limiti (1–50)</label>
                            <input type="number" min="1" max="50" wire:model.defer="candidateResultLimit" class="w-full min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                        </div>
                    </div>
                    <p class="mt-1 text-[11px] leading-4 text-slate-400">Arama limiti aday havuzunu, sonuç limiti ekranda gösterilecek en iyi aday sayısını belirler.</p>
                    <label class="mt-3 flex cursor-pointer items-start justify-between gap-4 rounded-[6px] border border-slate-200 bg-white p-3">
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-slate-900">Otomatik eşleme</h3>
                            <p class="mt-1 text-[11px] leading-4 text-slate-500">Yeni/varsayılan senkron profillerde otomatik eşleme açık gelsin. Mevcut mağaza profillerini değiştirmez.</p>
                        </div>
                        <div class="relative h-7 w-14 shrink-0">
                            <input type="checkbox" wire:model.live="autoRunMatchingOnSync" class="sr-only peer">
                            <span class="pointer-events-none absolute inset-0 rounded-full bg-slate-300 shadow-inner transition peer-checked:bg-slate-900 peer-focus:ring-4 peer-focus:ring-slate-300"></span>
                            <span class="pointer-events-none absolute left-1 top-1 h-5 w-5 rounded-full border border-slate-200 bg-white shadow-sm transition-transform peer-checked:translate-x-7"></span>
                        </div>
                    </label>
                </div>

                @foreach($generalToggles as $toggle)
                    <label class="flex cursor-pointer items-start justify-between gap-4 rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-sm font-semibold text-slate-900">{{ $toggle['title'] }}</h3>
                                <span class="inline-flex rounded-[6px] border px-2 py-0.5 text-[11px] font-medium {{ $toggle['enabled'] ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-500' }}">
                                    {{ $toggle['badge'] }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm leading-5 text-slate-500">{{ $toggle['description'] }}</p>
                        </div>

                        <div class="relative h-7 w-14 shrink-0">
                            <input type="checkbox" wire:model.live="{{ $toggle['model'] }}" class="sr-only peer">
                            <span class="pointer-events-none absolute inset-0 rounded-full bg-slate-300 shadow-inner transition peer-checked:bg-slate-900 peer-focus:ring-4 peer-focus:ring-slate-300"></span>
                            <span class="pointer-events-none absolute left-1 top-1 h-5 w-5 rounded-full border border-slate-200 bg-white shadow-sm transition-transform peer-checked:translate-x-7"></span>
                        </div>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="mt-4 rounded-[8px] border border-slate-200 bg-white px-4 py-3">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm font-medium text-slate-900">Etkilenen modüller</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($helpTipCoverage as $moduleLabel)
                        <span class="inline-flex min-h-[28px] items-center rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-600">
                            {{ $moduleLabel }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Çıktı</p>
                <h2 class="mt-2 text-lg font-semibold text-slate-900">Kargo etiketi ve irsaliye</h2>
                <p class="mt-1 text-sm text-slate-500">Sipariş aksiyonlarından indirilen yerel PDF çıktılarının temel düzeni.</p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                <button
                    type="button"
                    wire:click="saveDocumentSettings"
                    wire:loading.attr="disabled"
                    wire:target="saveDocumentSettings"
                    class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto sm:py-2"
                >
                    <span wire:loading.remove wire:target="saveDocumentSettings">Kaydet</span>
                    <span wire:loading wire:target="saveDocumentSettings">Kaydediliyor...</span>
                </button>

                <button
                    type="button"
                    wire:click="resetDocumentSettings"
                    wire:loading.attr="disabled"
                    class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto sm:py-2"
                >
                    Varsayılan
                </button>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Kargo etiketi</h3>
                        <p class="mt-1 text-sm text-slate-500">Tekil veya toplu etiket çıktısı.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span class="inline-flex min-h-[28px] items-center rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600">{{ $currentLabelTemplate }}</span>
                        <span class="inline-flex min-h-[28px] items-center rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600">{{ $currentLabelPaper }}</span>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Şablon</label>
                        <select wire:model.live="labelPrintSettings.template" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                            @foreach($labelTemplateOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Kağıt</label>
                        <select wire:model.live="labelPrintSettings.paper" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                            @foreach($labelPaperOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Barkod yüksekliği</label>
                        <input type="number" min="32" max="96" step="1" wire:model.live="labelPrintSettings.barcode_height" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach($labelToggles as $toggle)
                        <label class="flex min-h-[62px] cursor-pointer items-start gap-3 rounded-[8px] border border-slate-200 bg-white p-3">
                            <input type="checkbox" wire:model.live="{{ $toggle['model'] }}" class="mt-1 rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-900">
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-slate-900">{{ $toggle['label'] }}</span>
                                <span class="mt-0.5 block text-xs text-slate-500">{{ $toggle['hint'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <div class="mt-4">
                    <label class="mb-1 block text-xs font-medium text-slate-500">Etiket dip notu</label>
                    <textarea wire:model.live="labelPrintSettings.footer_note" rows="2" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm" placeholder="Örn. Teslimat sırasında paket hasarını kontrol edin."></textarea>
                </div>
            </div>

            <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Sevk ve irsaliye</h3>
                        <p class="mt-1 text-sm text-slate-500">Paket veya sipariş bazlı sevk çıktısı.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span class="inline-flex min-h-[28px] items-center rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600">{{ $currentDispatchTemplate }}</span>
                        <span class="inline-flex min-h-[28px] items-center rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600">{{ $currentDispatchPaper }}</span>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Şablon</label>
                        <select wire:model.live="dispatchPrintSettings.template" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                            @foreach($dispatchTemplateOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Kağıt</label>
                        <select wire:model.live="dispatchPrintSettings.paper" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                            @foreach($dispatchPaperOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Barkod yüksekliği</label>
                        <input type="number" min="32" max="96" step="1" wire:model.live="dispatchPrintSettings.barcode_height" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach($dispatchToggles as $toggle)
                        <label class="flex min-h-[62px] cursor-pointer items-start gap-3 rounded-[8px] border border-slate-200 bg-white p-3">
                            <input type="checkbox" wire:model.live="{{ $toggle['model'] }}" class="mt-1 rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-900">
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-slate-900">{{ $toggle['label'] }}</span>
                                <span class="mt-0.5 block text-xs text-slate-500">{{ $toggle['hint'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <div class="mt-4">
                    <label class="mb-1 block text-xs font-medium text-slate-500">İrsaliye dip notu</label>
                    <textarea wire:model.live="dispatchPrintSettings.footer_note" rows="2" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm" placeholder="Örn. Ürün tesliminde hasar ve eksik kontrolü yapılmıştır."></textarea>
                </div>
            </div>
        </div>

        <div class="mt-4 rounded-[8px] border border-slate-200 bg-white p-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-slate-900">Gönderici fallback bilgisi</h3>
                    <p class="mt-1 text-sm text-slate-500">Siparişte bağlı firma yoksa etiket ve irsaliye bu bilgileri kullanır.</p>
                </div>
                <span class="inline-flex w-fit items-center rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-600">
                    {{ filled($companyForm['name'] ?? '') ? 'Tanımlı' : 'Boş' }}
                </span>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Firma adı</label>
                    <input type="text" wire:model.live="companyForm.name" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm" placeholder="ZOLM Tekstil">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Telefon</label>
                    <input type="text" wire:model.live="companyForm.phone" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm" placeholder="0212 000 00 00">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Vergi numarası</label>
                    <input type="text" wire:model.live="companyForm.tax_number" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm" placeholder="1234567890">
                </div>
                <div class="xl:col-span-1">
                    <label class="mb-1 block text-xs font-medium text-slate-500">Adres</label>
                    <textarea wire:model.live="companyForm.address" rows="1" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm" placeholder="Mahalle, cadde, no, ilçe / il"></textarea>
                </div>
            </div>
        </div>
    </section>
</div>
