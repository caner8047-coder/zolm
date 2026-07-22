{{-- ═══ ÜRÜN DÜZENLEME MODAL ═══ --}}
@if($showEditModal)
<div class="fixed inset-0 z-50 overflow-y-auto" x-data @keydown.escape.window="$wire.closeEditModal()">
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="closeEditModal"></div>
    <div class="relative flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-xl transition-all w-full sm:my-8 sm:max-w-4xl">
                <form wire:submit.prevent="saveProduct">
                    {{-- Modal Header --}}
                    <div class="bg-white px-4 pt-5 sm:px-6 border-b border-slate-100">
                        <h3 class="text-lg font-semibold text-slate-900 mb-4">
                            {{ $editingId ? 'Ürün Düzenle' : 'Yeni Ürün Ekle' }}
                        </h3>
                        {{-- Sekmeler --}}
                        <div class="flex gap-1 -mb-px overflow-x-auto">
                            @php
                                $editTabs = ['basic' => 'Temel', 'pricing' => 'Fiyat & Maliyet', 'logistics' => 'Stok & Lojistik', 'set' => 'Set İçeriği', 'images' => 'Görseller'];
                                if ($editingId) {
                                    $editTabs['listing_quality'] = 'Listing Kalitesi';
                                }
                            @endphp
                            @foreach($editTabs as $key => $label)
                                <button type="button"
                                    wire:click="setEditTab('{{ $key }}')"
                                    class="px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 transition-colors
                                    {{ $editTab === $key ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Modal Body --}}
                    <div class="px-4 py-5 sm:px-6 max-h-[60vh] overflow-y-auto">

                        {{-- TAB: Temel --}}
                        @if($editTab === 'basic')
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Barkod *</label>
                                    <input type="text" wire:model="f_barcode" class="w-full px-3 py-2 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200" placeholder="869123456789">
                                    @error('f_barcode') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Stok Kodu</label>
                                    <input type="text" wire:model="f_stock_code" class="w-full px-3 py-2 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200" placeholder="1BNCZEM00001">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Ürün Adı</label>
                                <input type="text" wire:model="f_product_name" class="w-full px-3 py-2 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200" placeholder="Örn: Şila Bench Beyaz Peluş">
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Marka</label>
                                    <input type="text" wire:model="f_brand" class="w-full px-3 py-2 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200" placeholder="Zem">
                                </div>
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Kategori</label>
                                    <input type="text" wire:model="f_category_name" class="w-full px-3 py-2 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200" placeholder="Puf & Bench">
                                </div>
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Model Kodu</label>
                                    <input type="text" wire:model="f_model_code" class="w-full px-3 py-2 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200" placeholder="ZEMBNO">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Renk</label>
                                    <input type="text" wire:model="f_color" class="w-full px-3 py-2 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200">
                                </div>
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Beden</label>
                                    <input type="text" wire:model="f_size" class="w-full px-3 py-2 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200">
                                </div>
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Durum</label>
                                    <select wire:model="f_status" class="w-full px-3 py-2 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200">
                                        <option value="active">Satışta</option>
                                        <option value="out_of_stock">Tükendi</option>
                                        <option value="pending">Onay Bekliyor</option>
                                        <option value="suspended">Beklemede</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Platformlar</label>
                                <input type="text" wire:model="f_platforms" class="w-full px-3 py-2 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200" placeholder="Trendyol, Hepsiburada, N11...">
                            </div>
                        </div>
                        @endif

                        {{-- TAB: Fiyat & Maliyet --}}
                        @if($editTab === 'pricing')
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Satış Fiyatı (KDV Dahil) *</label>
                                    <div class="relative">
                                        <input type="number" step="0.01" wire:model="f_sale_price" class="w-full px-3 py-2 pr-8 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">₺</span>
                                    </div>
                                    @error('f_sale_price') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Piyasa Fiyatı (KDV Dahil) *</label>
                                    <div class="relative">
                                        <input type="number" step="0.01" wire:model="f_market_price" class="w-full px-3 py-2 pr-8 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">₺</span>
                                    </div>
                                    @error('f_market_price') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="border-t border-slate-100 pt-4">
                                <h4 class="text-sm font-semibold text-slate-700 mb-3">Maliyet Bilgileri (Muhasebe Modülü)</h4>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Birim Maliyet *</label>
                                        <div class="relative">
                                            <input type="number" step="0.01" wire:model="f_cogs" class="w-full px-3 py-2 pr-8 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200">
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">₺</span>
                                        </div>
                                        @error('f_cogs') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Ambalaj Gideri *</label>
                                        <div class="relative">
                                            <input type="number" step="0.01" wire:model="f_packaging_cost" class="w-full px-3 py-2 pr-8 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200">
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">₺</span>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Kargo Maliyeti *</label>
                                        <div class="relative">
                                            <input type="number" step="0.01" wire:model="f_cargo_cost" class="w-full px-3 py-2 pr-8 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200">
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">₺</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Ek Gider (Sabit)</label>
                                        <div class="relative">
                                            <input type="number" step="0.01" wire:model="f_extra_cost_fixed" class="w-full px-3 py-2 pr-8 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200">
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">₺</span>
                                        </div>
                                        @error('f_extra_cost_fixed') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Ek Gider (%)</label>
                                        <div class="relative">
                                            <input type="number" step="0.1" wire:model="f_extra_cost_percentage" class="w-full px-3 py-2 pr-8 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200">
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">%</span>
                                        </div>
                                        @error('f_extra_cost_percentage') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">KDV Oranı (%) *</label>
                                    <select wire:model="f_vat_rate" class="w-full px-3 py-2 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200">
                                        <option value="1">%1 (Gıda vb.)</option>
                                        <option value="10">%10 (Tekstil vb.)</option>
                                        <option value="20">%20 (Elektronik/Genel)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Maliyet KDV Oranı (%)</label>
                                    <div class="relative">
                                        <input type="number" step="0.1" wire:model="f_cost_vat_rate" class="w-full px-3 py-2 pr-8 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200" placeholder="Boş bırakılabilir">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">%</span>
                                    </div>
                                    @error('f_cost_vat_rate') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Varsayılan Komisyon (%) *</label>
                                    <div class="relative">
                                        <input type="number" step="0.1" wire:model="f_commission_rate" class="w-full px-3 py-2 pr-8 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">%</span>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">Pazaryeri komisyonu gelmezse yedek oran olarak kullanılır.</p>
                                </div>
                            </div>

                            <label class="flex cursor-pointer items-start gap-3 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3 transition hover:bg-slate-50">
                                <input type="checkbox"
                                       wire:model="f_profit_commission_override_enabled"
                                       class="mt-1 rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-200">
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-slate-900">Kâr hesabında manuel komisyon kullan</span>
                                    <span class="mt-1 block text-xs leading-5 text-slate-500">
                                        Aktifse ürün tablosundaki kâr, hakediş ve oran hesabında pazaryeri komisyonu yerine bu ürünün varsayılan komisyon oranı kullanılır.
                                    </span>
                                </span>
                            </label>
                        </div>
                        @endif

                        {{-- TAB: Stok & Lojistik --}}
                        @if($editTab === 'logistics')
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Stok Adedi *</label>
                                    <input type="number" wire:model="f_stock_quantity" class="w-full px-3 py-2 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200">
                                    @error('f_stock_quantity') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Kritik Stok Eşiği</label>
                                    <input type="number" min="0" wire:model="f_critical_stock_threshold" class="w-full px-3 py-2 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200" placeholder="Kapalı">
                                    @error('f_critical_stock_threshold') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Desi *</label>
                                    <input type="number" step="0.01" wire:model="f_desi" class="w-full px-3 py-2 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200">
                                </div>
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Parça Sayısı *</label>
                                    <input type="number" wire:model="f_pieces" class="w-full px-3 py-2 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200">
                                </div>
                            </div>
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Variyant</label>
                                    <input type="text" wire:model="f_variant" class="w-full px-3 py-2 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200">
                                </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">İade Oranı (%)</label>
                                    <div class="relative">
                                        <input type="number" step="0.1" wire:model="f_return_rate" class="w-full px-3 py-2 pr-8 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200" placeholder="Sipariş geçmişinden hesaplanabilir">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">%</span>
                                    </div>
                                    @error('f_return_rate') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Teslimat Tipi</label>
                                    <input type="text" wire:model="f_fast_delivery_type" class="w-full px-3 py-2 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200" placeholder="Standart, hızlı teslimat, aynı gün">
                                    @error('f_fast_delivery_type') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Ürün Açıklaması</label>
                                <textarea wire:model="f_description" rows="4" class="w-full px-3 py-2 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200" placeholder="Ürün hakkında detaylı açıklama..."></textarea>
                            </div>
                        </div>
                        @endif

                        {{-- TAB: Set İçeriği --}}
                        @if($editTab === 'set')
                        @php
                            $setDefinition = $this->editingSetDefinition;
                            $setSummary = $this->editingSetSummary;
                            $componentOptions = $this->setComponentOptions;
                            $formatSetMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
                            $formatSetNumber = fn ($value, $decimals = 0) => number_format((float) $value, $decimals, ',', '.');
                        @endphp

                        <div class="space-y-4">
                            @if(!$editingId)
                                <div class="rounded-[10px] border border-amber-200 bg-amber-50/70 p-4 text-sm text-amber-800">
                                    Set içeriği tanımlamak için önce ürün kartını kaydedin. Ürün kaydedildikten sonra bu sekmeden bileşenleri ekleyebilirsiniz.
                                </div>
                            @else
                                <div class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
	                                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
	                                        <div class="min-w-0">
	                                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Set / Takım Çözümlemesi</p>
	                                            <h4 class="mt-1 break-words text-base font-semibold text-slate-900">{{ filled($f_product_name) ? $f_product_name : 'Set stok adı yok' }}</h4>
	                                            <div class="mt-2 flex flex-wrap items-center gap-2">
	                                                <span class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-600">
	                                                    Stok kodu: {{ $f_stock_code ?: $f_barcode ?: '-' }}
	                                                </span>
	                                            </div>
	                                            <p class="mt-1 text-sm text-slate-500">Maliyet, ambalaj, desi, parça ve beklenen kargo değeri bileşenlerden hesaplanır.</p>
	                                        </div>

                                        <div class="flex flex-col gap-2 sm:flex-row">
                                            <button type="button"
                                                    wire:click="refreshSetTotals"
                                                    wire:loading.attr="disabled"
                                                    wire:target="refreshSetTotals"
                                                    class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2">
                                                Toplamları Yenile
                                            </button>
                                            <button type="button"
                                                    wire:click="clearSetDefinition"
                                                    wire:confirm="Set içeriği kaldırılıp ürün tekil moda alınsın mı?"
                                                    class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-rose-200 bg-white px-4 py-3 text-sm font-medium text-rose-600 transition hover:bg-rose-50 sm:w-auto sm:py-2">
                                                Tekil Ürüne Çevir
                                            </button>
                                        </div>
                                    </div>

                                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                                            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Ürün Maliyeti</p>
                                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ $formatSetMoney($setSummary['cogs_cost'] ?? 0) }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ ($setSummary['cost_mode'] ?? 'sum_components') === 'manual_parent' ? 'Ürün kartından' : 'Bileşenlerden' }}</p>
                                        </div>
                                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                                            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Ambalaj</p>
                                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ $formatSetMoney($setSummary['packaging_cost'] ?? 0) }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ (int) ($setSummary['missing_cost_components'] ?? 0) }} eksik maliyet</p>
                                        </div>
                                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                                            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Kargo / Desi</p>
                                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ $formatSetMoney($setSummary['own_cargo_cost'] ?? 0) }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $formatSetNumber($setSummary['desi'] ?? 0, 1) }} desi · {{ (int) ($setSummary['pieces'] ?? 0) }} parça</p>
                                        </div>
                                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                                            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Set Stoku</p>
                                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ ($setSummary['stock_quantity'] ?? null) === null ? '—' : (int) $setSummary['stock_quantity'] }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ (int) ($setSummary['component_count'] ?? 0) }} bileşen satırı</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_18rem]">
                                    <div class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <h4 class="text-sm font-semibold text-slate-900">Bileşen ekle</h4>
                                                <p class="mt-1 text-sm text-slate-500">Tekli ürünleri seçerek setin fiziksel içeriğini oluşturun.</p>
                                            </div>
                                        </div>

                                        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            <div>
                                                <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Bileşen ara</label>
                                                <input type="text"
                                                       wire:model.live.debounce.350ms="setSearch"
                                                       class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:py-2 sm:text-sm"
                                                       placeholder="Ürün adı, barkod veya stok kodu">
                                            </div>
                                            <div>
                                                <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Bileşen ürün</label>
                                                <select wire:model="setComponentProductId"
                                                        class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:py-2 sm:text-sm">
                                                    <option value="">Ürün seçin</option>
                                                    @foreach($componentOptions as $option)
                                                        <option value="{{ $option->id }}">{{ $option->product_name ?: 'İsimsiz ürün' }} · {{ $option->stock_code ?: $option->barcode }}</option>
                                                    @endforeach
                                                </select>
                                                @error('setComponentProductId') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                                            </div>
                                        </div>

                                        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                            <div>
                                                <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Miktar</label>
                                                <input type="number"
                                                       min="0.001"
                                                       step="0.001"
                                                       wire:model="setComponentQuantity"
                                                       class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:py-2 sm:text-sm">
                                                @error('setComponentQuantity') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                                            </div>
                                            <div>
                                                <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Maliyet Override</label>
                                                <input type="number" step="0.01" wire:model="setCostOverride" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:py-2 sm:text-sm" placeholder="Boş">
                                            </div>
                                            <div>
                                                <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Kargo Override</label>
                                                <input type="number" step="0.01" wire:model="setCargoCostOverride" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:py-2 sm:text-sm" placeholder="Boş">
                                            </div>
                                            <div>
                                                <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Desi / Parça</label>
                                                <div class="grid grid-cols-2 gap-2">
                                                    <input type="number" step="0.01" wire:model="setDesiOverride" class="w-full rounded-[6px] border border-slate-200 bg-white px-2 py-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:py-2 sm:text-sm" placeholder="Desi">
                                                    <input type="number" min="1" wire:model="setPiecesOverride" class="w-full rounded-[6px] border border-slate-200 bg-white px-2 py-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:py-2 sm:text-sm" placeholder="Parça">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                                                <label class="inline-flex items-center gap-2 rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-700">
                                                    <input type="checkbox" wire:model="setIncludeCost" class="rounded border-slate-300 text-slate-900 focus:ring-slate-200">
                                                    Maliyet
                                                </label>
                                                <label class="inline-flex items-center gap-2 rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-700">
                                                    <input type="checkbox" wire:model="setIncludePackaging" class="rounded border-slate-300 text-slate-900 focus:ring-slate-200">
                                                    Ambalaj
                                                </label>
                                                <label class="inline-flex items-center gap-2 rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-700">
                                                    <input type="checkbox" wire:model="setIncludeLogistics" class="rounded border-slate-300 text-slate-900 focus:ring-slate-200">
                                                    Lojistik
                                                </label>
                                            </div>

                                            <button type="button"
                                                    wire:click="addSetComponent"
                                                    wire:loading.attr="disabled"
                                                    wire:target="addSetComponent"
                                                    class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 sm:w-auto sm:py-2">
                                                Bileşen Ekle
                                            </button>
                                        </div>
                                    </div>

                                    <div class="rounded-[10px] border border-slate-200 bg-slate-50/60 p-4">
                                        <h4 class="text-sm font-semibold text-slate-900">Hesaplama modu</h4>
                                        <div class="mt-3 space-y-3">
                                            <div>
                                                <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Maliyet</label>
                                                <select wire:model="setCostMode" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:py-2 sm:text-sm">
                                                    <option value="sum_components">Bileşenlerden topla</option>
                                                    <option value="manual_parent">Ürün kartından kullan</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Lojistik</label>
                                                <select wire:model="setLogisticsMode" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:py-2 sm:text-sm">
                                                    <option value="sum_components">Bileşenlerden topla</option>
                                                    <option value="manual_parent">Ürün kartından kullan</option>
                                                </select>
                                            </div>
                                            <button type="button"
                                                    wire:click="saveSetOptions"
                                                    wire:loading.attr="disabled"
                                                    wire:target="saveSetOptions"
                                                    class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:py-2">
                                                Ayarları Kaydet
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
                                    <div class="border-b border-slate-100 px-4 py-3">
                                        <h4 class="text-sm font-semibold text-slate-900">Kayıtlı bileşenler</h4>
                                    </div>
                                    <div class="divide-y divide-slate-100">
                                        @forelse(($setDefinition?->items ?? collect()) as $item)
                                            @php
                                                $component = $item->componentProduct;
                                                $displayCost = $item->include_cost
                                                    ? ($item->cost_override ?? ($component?->cogs ?? 0))
                                                    : 0;
                                                $displayCargoCost = $item->include_logistics
                                                    ? ($item->cargo_cost_override ?? ($component?->cargo_cost ?? 0))
                                                    : 0;
                                                $displayDesi = $item->include_logistics
                                                    ? ($item->desi_override ?? ($component?->desi ?? 0))
                                                    : 0;
                                                $displayPieces = $item->include_logistics
                                                    ? ($item->pieces_override ?? ($component?->pieces ?? 1))
                                                    : 0;
                                            @endphp
                                            <div class="grid grid-cols-1 gap-3 px-4 py-3 lg:grid-cols-[minmax(0,1fr)_7rem_22rem_auto] lg:items-center">
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $component?->product_name ?: 'Bileşen ürün bulunamadı' }}</p>
                                                    <p class="mt-1 truncate text-xs text-slate-500">{{ $component?->stock_code ?: $component?->barcode ?: '-' }}</p>
                                                </div>
                                                <input type="number"
                                                       min="0.001"
                                                       step="0.001"
                                                       value="{{ (float) $item->quantity }}"
                                                       wire:change="updateSetItemQuantity({{ $item->id }}, $event.target.value)"
                                                       class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:py-2 sm:text-sm">
                                                <div class="grid grid-cols-2 gap-2 text-xs text-slate-500 sm:grid-cols-4">
                                                    <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1">M {{ $formatSetMoney($displayCost) }}</span>
                                                    <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1">K {{ $formatSetMoney($displayCargoCost) }}</span>
                                                    <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1">D {{ $formatSetNumber($displayDesi, 1) }}</span>
                                                    <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1">P {{ (int) $displayPieces }}</span>
                                                </div>
                                                <button type="button"
                                                        wire:click="removeSetComponent({{ $item->id }})"
                                                        wire:confirm="Bu bileşen setten kaldırılsın mı?"
                                                        class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-rose-200 bg-white px-3 py-3 text-sm font-medium text-rose-600 transition hover:bg-rose-50 sm:py-2">
                                                    Kaldır
                                                </button>
                                            </div>
                                        @empty
                                            <div class="px-4 py-8 text-center text-sm text-slate-500">
                                                Henüz bileşen eklenmedi. Bu ürün şu anda tekil ürün gibi hesaplanır.
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            @endif
                        </div>
                        @endif

                        {{-- TAB: Görseller --}}
                        @if($editTab === 'images')
                        <div class="space-y-5">
                            @if($editingId)
                                @include('livewire.partials.mp-products-ai-studio')
                            @endif

                            <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <h4 class="text-sm font-semibold text-slate-900">Ana görsel</h4>
                                        <p class="mt-1 text-sm text-slate-500">
                                            Ürün listesinde ve detay panelinde gösterilecek kapak görselini buradan seçin.
                                        </p>
                                    </div>

                                    <div class="h-24 w-24 overflow-hidden rounded-xl border border-slate-200 bg-white">
                                        @if($f_image_url)
                                            <img src="{{ $f_image_url }}" alt="Ana görsel" class="h-full w-full object-cover">
                                        @else
                                            <div class="flex h-full w-full items-center justify-center text-slate-300">
                                                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Ana görsel URL</label>
                                    <input type="text"
                                           wire:model="f_image_url"
                                           class="w-full px-3 py-2 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200"
                                           placeholder="https://... veya mevcut görsel adresi">
                                    @error('f_image_url') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="rounded-xl border border-slate-200 bg-white p-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h4 class="text-sm font-semibold text-slate-900">Galeri görselleri</h4>
                                        <p class="mt-1 text-sm text-slate-500">Birden fazla görsel URL’si ekleyebilir, ana görseli galeriden seçebilirsiniz.</p>
                                    </div>

                                    <button type="button"
                                            wire:click="addImageUrlField"
                                            class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                        Görsel URL ekle
                                    </button>
                                </div>

                                <div class="mt-4 space-y-3">
                                    @forelse($f_image_urls as $index => $imageUrl)
                                        <div class="grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-slate-50/60 p-3 lg:grid-cols-[96px_minmax(0,1fr)_auto] lg:items-start">
                                            <div class="h-24 w-full overflow-hidden rounded-xl border border-slate-200 bg-white lg:w-24">
                                                @if(filled($imageUrl))
                                                    <img src="{{ $imageUrl }}" alt="Galeri görseli {{ $index + 1 }}" class="h-full w-full object-cover">
                                                @else
                                                    <div class="flex h-full w-full items-center justify-center text-slate-300">
                                                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    </div>
                                                @endif
                                            </div>

                                            <div>
                                                <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">Görsel URL {{ $index + 1 }}</label>
                                                <input type="text"
                                                       wire:model="f_image_urls.{{ $index }}"
                                                       class="w-full px-3 py-2 text-base sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-200"
                                                       placeholder="https://...">
                                                @error('f_image_urls.' . $index) <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                            </div>

                                            <div class="flex flex-col gap-2">
                                                <button type="button"
                                                        wire:click="useGalleryImageAsPrimary({{ $index }})"
                                                        class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                                    Ana görsel yap
                                                </button>
                                                <button type="button"
                                                        wire:click="removeImageUrlField({{ $index }})"
                                                        class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-rose-200 bg-white px-3 py-3 sm:py-2 text-sm font-medium text-rose-600 transition hover:bg-rose-50">
                                                    Kaldır
                                                </button>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                                            Henüz galeri görseli eklenmemiş.
                                        </div>
                                    @endforelse
                                </div>
                            </div>

                            <div class="rounded-xl border border-slate-200 bg-white p-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <h4 class="text-sm font-semibold text-slate-900">Bilgisayardan yükle</h4>
                                        <p class="mt-1 text-sm text-slate-500">
                                            PNG, JPG veya WEBP görsellerini ekleyin. Kaydettiğinizde galeriye otomatik eklenir.
                                        </p>
                                    </div>
                                    @if($f_image_uploads)
                                        <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">
                                            {{ count($f_image_uploads) }} dosya seçildi
                                        </span>
                                    @endif
                                </div>

                                <label class="mt-4 block cursor-pointer rounded-xl border border-dashed border-slate-300 bg-slate-50/60 px-4 py-5 transition hover:border-slate-400">
                                    <input wire:model="f_image_uploads" type="file" class="hidden" multiple accept="image/*">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                        <div class="flex h-11 w-11 items-center justify-center rounded-[8px] bg-slate-900 text-white">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-6l-4-4m0 0L8 10m4-4v12" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-slate-900">Görselleri seçin</p>
                                            <p class="mt-1 text-xs sm:text-sm text-slate-500">Toplu yükleme desteklenir. Seçilen dosyalar kaydetme sırasında ürüne eklenir.</p>
                                        </div>
                                    </div>
                                </label>

                                @error('f_image_uploads.*') <span class="text-red-500 text-xs mt-3 block">{{ $message }}</span> @enderror

                                @if($f_image_uploads)
                                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                                        @foreach($f_image_uploads as $uploadIndex => $upload)
                                            <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-2">
                                                <img src="{{ $upload->temporaryUrl() }}" alt="Yeni yüklenen görsel {{ $uploadIndex + 1 }}" class="h-24 w-full rounded-lg object-cover">
                                                <button type="button"
                                                        wire:click="removePendingImageUpload({{ $uploadIndex }})"
                                                        class="mt-2 inline-flex min-h-[36px] w-full items-center justify-center rounded-lg border border-rose-200 bg-white px-3 py-2 text-xs font-medium text-rose-600 transition hover:bg-rose-50">
                                                    Listeden çıkar
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                        @endif

                        {{-- TAB: Listing Kalitesi --}}
                        @if($editTab === 'listing_quality' && $editingId)
                            @include('livewire.partials.mp-products-listing-quality')
                        @endif
                    </div>

                    {{-- Modal Footer --}}
                    <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 border-t border-slate-100">
                        <button type="submit"
                                wire:loading.attr="disabled"
                                wire:loading.class="cursor-wait opacity-60"
                                wire:target="saveProduct"
                                class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm font-semibold text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 shadow-sm sm:ml-3 transition-colors disabled:cursor-not-allowed">
                            {{ $editingId ? 'Güncelle' : 'Kaydet' }}
                        </button>
                        <button type="button"
                                wire:click="closeEditModal"
                                wire:loading.attr="disabled"
                                wire:target="saveProduct"
                                class="mt-3 sm:mt-0 w-full sm:w-auto px-4 py-3 sm:py-2 text-sm font-semibold text-slate-900 bg-white rounded-lg ring-1 ring-inset ring-slate-300 hover:bg-slate-50 transition-colors disabled:cursor-not-allowed disabled:opacity-60">
                            İptal
                        </button>
                    </div>
                </form>
        </div>
    </div>
</div>
@endif
