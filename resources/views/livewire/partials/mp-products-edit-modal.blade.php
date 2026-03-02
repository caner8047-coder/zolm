{{-- ═══ ÜRÜN DÜZENLEME MODAL ═══ --}}
<div x-data="{ open: @entangle('showEditModal') }" x-show="open" class="relative z-50" style="display: none;">
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div x-show="open"
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95"
                 class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-xl transition-all w-full sm:my-8 sm:max-w-2xl">
                <form wire:submit.prevent="saveProduct">
                    {{-- Modal Header --}}
                    <div class="bg-white px-4 pt-5 sm:px-6 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            {{ $editingId ? 'Ürün Düzenle' : 'Yeni Ürün Ekle' }}
                        </h3>
                        {{-- Sekmeler --}}
                        <div class="flex gap-1 -mb-px overflow-x-auto">
                            @foreach(['basic' => 'Temel', 'pricing' => 'Fiyat & Maliyet', 'logistics' => 'Stok & Lojistik'] as $key => $label)
                                <button type="button" wire:click="$set('editTab', '{{ $key }}')"
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
                                        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Birim Maliyet (COGS) *</label>
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
                    </div>

                    {{-- Modal Footer --}}
                    <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 border-t border-gray-100">
                        <button type="submit" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-500 shadow-sm sm:ml-3 transition-colors">
                            {{ $editingId ? 'Güncelle' : 'Kaydet' }}
                        </button>
                        <button type="button" wire:click="closeEditModal" class="mt-3 sm:mt-0 w-full sm:w-auto px-4 py-3 sm:py-2 text-sm font-semibold text-gray-900 bg-white rounded-lg ring-1 ring-inset ring-gray-300 hover:bg-gray-50 transition-colors">
                            İptal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
