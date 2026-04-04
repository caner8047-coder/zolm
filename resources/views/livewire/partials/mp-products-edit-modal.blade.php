{{-- ═══ ÜRÜN DÜZENLEME MODAL ═══ --}}
@if($showEditModal)
<div class="fixed inset-0 z-50 overflow-y-auto" x-data @keydown.escape.window="$wire.closeEditModal()">
    <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" wire:click="closeEditModal"></div>
    <div class="relative flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-xl transition-all w-full sm:my-8 sm:max-w-2xl">
                <form wire:submit.prevent="saveProduct">
                    {{-- Modal Header --}}
                    <div class="bg-white px-4 pt-5 sm:px-6 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            {{ $editingId ? 'Ürün Düzenle' : 'Yeni Ürün Ekle' }}
                        </h3>
                        {{-- Sekmeler --}}
                        <div class="flex gap-1 -mb-px overflow-x-auto">
                            @foreach(['basic' => 'Temel', 'pricing' => 'Fiyat & Maliyet', 'logistics' => 'Stok & Lojistik', 'images' => 'Görseller'] as $key => $label)
                                <button type="button"
                                    wire:click="setEditTab('{{ $key }}')"
                                    class="px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 transition-colors
                                    {{ $editTab === $key ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
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
                                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Barkod *</label>
                                    <input type="text" wire:model="f_barcode" class="w-full px-3 py-2 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="869123456789">
                                    @error('f_barcode') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Stok Kodu</label>
                                    <input type="text" wire:model="f_stock_code" class="w-full px-3 py-2 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="1BNCZEM00001">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Ürün Adı</label>
                                <input type="text" wire:model="f_product_name" class="w-full px-3 py-2 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="Örn: Şila Bench Beyaz Peluş">
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Marka</label>
                                    <input type="text" wire:model="f_brand" class="w-full px-3 py-2 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="Zem">
                                </div>
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Kategori</label>
                                    <input type="text" wire:model="f_category_name" class="w-full px-3 py-2 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="Puf & Bench">
                                </div>
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Model Kodu</label>
                                    <input type="text" wire:model="f_model_code" class="w-full px-3 py-2 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="ZEMBNO">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Renk</label>
                                    <input type="text" wire:model="f_color" class="w-full px-3 py-2 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Beden</label>
                                    <input type="text" wire:model="f_size" class="w-full px-3 py-2 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Durum</label>
                                    <select wire:model="f_status" class="w-full px-3 py-2 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                        <option value="active">Satışta</option>
                                        <option value="out_of_stock">Tükendi</option>
                                        <option value="pending">Onay Bekliyor</option>
                                        <option value="suspended">Beklemede</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Platformlar</label>
                                <input type="text" wire:model="f_platforms" class="w-full px-3 py-2 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="Trendyol, Hepsiburada, N11...">
                            </div>
                        </div>
                        @endif

                        {{-- TAB: Fiyat & Maliyet --}}
                        @if($editTab === 'pricing')
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Satış Fiyatı (KDV Dahil) *</label>
                                    <div class="relative">
                                        <input type="number" step="0.01" wire:model="f_sale_price" class="w-full px-3 py-2 pr-8 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₺</span>
                                    </div>
                                    @error('f_sale_price') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Piyasa Fiyatı (KDV Dahil) *</label>
                                    <div class="relative">
                                        <input type="number" step="0.01" wire:model="f_market_price" class="w-full px-3 py-2 pr-8 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₺</span>
                                    </div>
                                    @error('f_market_price') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="border-t border-gray-100 pt-4">
                                <h4 class="text-sm font-semibold text-gray-700 mb-3">💰 Maliyet Bilgileri (Muhasebe Modülü)</h4>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Birim Maliyet *</label>
                                        <div class="relative">
                                            <input type="number" step="0.01" wire:model="f_cogs" class="w-full px-3 py-2 pr-8 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₺</span>
                                        </div>
                                        @error('f_cogs') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Ambalaj Gideri *</label>
                                        <div class="relative">
                                            <input type="number" step="0.01" wire:model="f_packaging_cost" class="w-full px-3 py-2 pr-8 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₺</span>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Kargo Maliyeti *</label>
                                        <div class="relative">
                                            <input type="number" step="0.01" wire:model="f_cargo_cost" class="w-full px-3 py-2 pr-8 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₺</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">KDV Oranı (%) *</label>
                                    <select wire:model="f_vat_rate" class="w-full px-3 py-2 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                        <option value="1">%1 (Gıda vb.)</option>
                                        <option value="10">%10 (Tekstil vb.)</option>
                                        <option value="20">%20 (Elektronik/Genel)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Komisyon Oranı (%) *</label>
                                    <div class="relative">
                                        <input type="number" step="0.1" wire:model="f_commission_rate" class="w-full px-3 py-2 pr-8 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- TAB: Stok & Lojistik --}}
                        @if($editTab === 'logistics')
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Stok Adedi *</label>
                                    <input type="number" wire:model="f_stock_quantity" class="w-full px-3 py-2 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                    @error('f_stock_quantity') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Desi *</label>
                                    <input type="number" step="0.01" wire:model="f_desi" class="w-full px-3 py-2 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Parça Sayısı *</label>
                                    <input type="number" wire:model="f_pieces" class="w-full px-3 py-2 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Variyant</label>
                                <input type="text" wire:model="f_variant" class="w-full px-3 py-2 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Ürün Açıklaması</label>
                                <textarea wire:model="f_description" rows="4" class="w-full px-3 py-2 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="Ürün hakkında detaylı açıklama..."></textarea>
                            </div>
                        </div>
                        @endif

                        {{-- TAB: Görseller --}}
                        @if($editTab === 'images')
                        <div class="space-y-5">
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
                                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Ana görsel URL</label>
                                    <input type="text"
                                           wire:model="f_image_url"
                                           class="w-full px-3 py-2 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
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
                                                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Görsel URL {{ $index + 1 }}</label>
                                                <input type="text"
                                                       wire:model="f_image_urls.{{ $index }}"
                                                       class="w-full px-3 py-2 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
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
                                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-900 text-white">
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
                    </div>

                    {{-- Modal Footer --}}
                    <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 border-t border-gray-100">
                        <button type="submit"
                                wire:loading.attr="disabled"
                                wire:loading.class="cursor-wait opacity-60"
                                wire:target="saveProduct"
                                class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-500 shadow-sm sm:ml-3 transition-colors disabled:cursor-not-allowed">
                            {{ $editingId ? 'Güncelle' : 'Kaydet' }}
                        </button>
                        <button type="button"
                                wire:click="closeEditModal"
                                wire:loading.attr="disabled"
                                wire:target="saveProduct"
                                class="mt-3 sm:mt-0 w-full sm:w-auto px-4 py-3 sm:py-2 text-sm font-semibold text-gray-900 bg-white rounded-lg ring-1 ring-inset ring-gray-300 hover:bg-gray-50 transition-colors disabled:cursor-not-allowed disabled:opacity-60">
                            İptal
                        </button>
                    </div>
                </form>
        </div>
    </div>
</div>
@endif
