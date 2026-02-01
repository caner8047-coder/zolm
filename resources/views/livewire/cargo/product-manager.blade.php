<div class="space-y-6 w-full max-w-full overflow-x-hidden">
    {{-- Mesaj --}}
    @if($message)
        <div class="rounded-lg p-4 {{ 
            $messageType === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 
            ($messageType === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : 
            ($messageType === 'warning' ? 'bg-yellow-50 text-yellow-800 border border-yellow-200' : 
            'bg-blue-50 text-blue-800 border border-blue-200')) 
        }}">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <p>{{ $message }}</p>
                <button wire:click="clearMessage" class="text-current opacity-50 hover:opacity-100 p-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    @endif

    {{-- İstatistikler --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 lg:gap-4">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-3 lg:p-4 text-white">
            <div class="text-blue-100 text-xs">Toplam Ürün</div>
            <div class="text-lg lg:text-2xl font-bold">{{ number_format($this->stats['total']) }}</div>
        </div>
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-3 lg:p-4 text-white">
            <div class="text-green-100 text-xs">Aktif Ürün</div>
            <div class="text-lg lg:text-2xl font-bold">{{ number_format($this->stats['active']) }}</div>
        </div>
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-3 lg:p-4 text-white">
            <div class="text-purple-100 text-xs">Kategori</div>
            <div class="text-lg lg:text-2xl font-bold">{{ number_format($this->stats['categories']) }}</div>
        </div>
    </div>

    {{-- Araç Çubuğu --}}
    <div class="flex flex-col lg:flex-row gap-3 items-start lg:items-center justify-between bg-gray-50 rounded-lg p-3 lg:p-4">
        {{-- Arama --}}
        <div class="w-full lg:flex-1 lg:max-w-xs">
            <div class="relative">
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="search"
                    placeholder="Stok kodu veya ürün adı ara..."
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-base sm:text-sm"
                >
                <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-2 w-full lg:w-auto">
            {{-- Kategori Filtresi --}}
            <select 
                wire:model.live="filterCategory"
                class="w-full sm:w-auto px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-base sm:text-sm"
            >
                <option value="">Tüm Kategoriler</option>
                @foreach($this->categories as $cat)
                    <option value="{{ $cat }}">{{ \App\Models\Product::getCategoryName($cat) }} ({{ $cat }})</option>
                @endforeach
            </select>

            {{-- Butonlar --}}
            <div class="flex gap-2">
                <button 
                    wire:click="openImportModal"
                    class="flex-1 sm:flex-none px-3 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition flex items-center justify-center gap-1 text-sm"
                    title="İçeri Aktar"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    <span class="hidden xl:inline">İçeri Aktar</span>
                </button>
                <button 
                    wire:click="exportToExcel"
                    class="flex-1 sm:flex-none px-3 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center justify-center gap-1 text-sm"
                    title="Dışarı Aktar"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    <span class="hidden xl:inline">Dışarı Aktar</span>
                </button>
                <button 
                    wire:click="$set('showAddForm', true)"
                    class="flex-1 sm:flex-none px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center justify-center gap-1 text-sm"
                    title="Yeni Ürün"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span class="hidden xl:inline">Yeni Ürün</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Yeni Ürün Formu --}}
    @if($showAddForm)
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="font-medium text-blue-900 mb-4">Yeni Ürün Ekle</h3>
            <form wire:submit="addProduct" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-6 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Stok Kodu</label>
                    <input 
                        type="text" 
                        wire:model="newProduct.stok_kodu"
                        class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 min-h-[44px] text-base sm:text-sm @error('newProduct.stok_kodu') border-red-500 @enderror"
                        placeholder="1BRJZEM00001"
                    >
                    @error('newProduct.stok_kodu') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ürün Adı</label>
                    <input 
                        type="text" 
                        wire:model="newProduct.urun_adi"
                        class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 min-h-[44px] text-base sm:text-sm @error('newProduct.urun_adi') border-red-500 @enderror"
                        placeholder="Ürün adını girin"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Parça</label>
                    <input 
                        type="number" 
                        wire:model="newProduct.parca"
                        min="1" max="20"
                        class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 min-h-[44px] text-base sm:text-sm"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Desi</label>
                    <input 
                        type="number" 
                        wire:model="newProduct.desi"
                        step="0.01" min="0"
                        class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 min-h-[44px] text-base sm:text-sm"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tutar (TL)</label>
                    <input 
                        type="number" 
                        wire:model="newProduct.tutar"
                        step="0.01" min="0"
                        class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 min-h-[44px]"
                    >
                </div>
                <div class="md:col-span-6 flex flex-col sm:flex-row justify-end gap-2 pt-2">
                    <button 
                        type="button"
                        wire:click="$set('showAddForm', false)"
                        class="w-full sm:w-auto px-4 py-3 sm:py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50"
                    >
                        İptal
                    </button>
                    <button 
                        type="submit"
                        class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                    >
                        Ekle
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- Ürün Tablosu --}}
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden max-w-full">
        <div class="overflow-x-auto max-w-full">
            <table class="w-full divide-y divide-gray-200 table-fixed">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 whitespace-nowrap"
                            wire:click="sortBy('stok_kodu')">
                            <span class="flex items-center gap-1">
                                Stok Kodu
                                @if($sortField === 'stok_kodu')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                              d="{{ $sortDirection === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/>
                                    </svg>
                                @endif
                            </span>
                        </th>
                        <th class="px-2 py-2 sm:px-4 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 w-[35%]"
                            wire:click="sortBy('urun_adi')">
                            Ürün Adı
                        </th>
                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 whitespace-nowrap"
                            wire:click="sortBy('parca')">
                            Parça
                        </th>
                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 whitespace-nowrap"
                            wire:click="sortBy('desi')">
                            Desi
                        </th>
                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 whitespace-nowrap"
                            wire:click="sortBy('tutar')">
                            Tutar
                        </th>
                        <th class="px-2 py-2 sm:px-4 sm:py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider hidden lg:table-cell">
                            Kategori
                        </th>
                        <th class="px-2 py-2 sm:px-4 sm:py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-20">
                            İşlem
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($this->products as $product)
                        <tr class="hover:bg-gray-50 transition">
                            @if($editingId === $product->id)
                                {{-- Düzenleme Modu --}}
                                <td class="px-3 py-2 sm:px-6 sm:py-3">
                                    <input type="text" wire:model="editingProduct.stok_kodu"
                                           class="w-full px-2 py-1 text-sm border rounded focus:ring-2 focus:ring-blue-500">
                                </td>
                                <td class="px-3 py-2 sm:px-6 sm:py-3">
                                    <input type="text" wire:model="editingProduct.urun_adi"
                                           class="w-full px-2 py-1 text-sm border rounded focus:ring-2 focus:ring-blue-500">
                                </td>
                                <td class="px-3 py-2 sm:px-6 sm:py-3 text-center">
                                    <input type="number" wire:model="editingProduct.parca" min="1"
                                           class="w-16 px-2 py-1 text-sm border rounded text-center focus:ring-2 focus:ring-blue-500">
                                </td>
                                <td class="px-3 py-2 sm:px-6 sm:py-3 text-center">
                                    <input type="number" wire:model="editingProduct.desi" step="0.01"
                                           class="w-20 px-2 py-1 text-sm border rounded text-center focus:ring-2 focus:ring-blue-500">
                                </td>
                                <td class="px-3 py-2 sm:px-6 sm:py-3 text-center">
                                    <input type="number" wire:model="editingProduct.tutar" step="0.01"
                                           class="w-24 px-2 py-1 text-sm border rounded text-center focus:ring-2 focus:ring-blue-500">
                                </td>
                                <td class="px-3 py-2 sm:px-6 sm:py-3 text-center">
                                    <span class="text-gray-400 text-sm">{{ $product->kategori }}</span>
                                </td>
                                <td class="px-3 py-2 sm:px-6 sm:py-3 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button wire:click="saveEdit" class="text-green-600 hover:text-green-800 p-2">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        </button>
                                        <button wire:click="cancelEdit" class="text-gray-600 hover:text-gray-800 p-2">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            @else
                                {{-- Normal Görünüm --}}
                                <td class="px-3 py-2 sm:px-6 sm:py-3 whitespace-nowrap">
                                    <span class="font-mono text-xs text-gray-900">{{ $product->stok_kodu }}</span>
                                </td>
                                <td class="px-2 py-2 sm:px-4 sm:py-3 truncate max-w-0">
                                    <span class="text-gray-900 text-xs" title="{{ $product->urun_adi }}">{{ $product->urun_adi }}</span>
                                </td>
                                <td class="px-3 py-2 sm:px-6 sm:py-3 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $product->parca }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 sm:px-6 sm:py-3 text-center whitespace-nowrap">
                                    <span class="text-gray-900 font-medium text-xs">{{ number_format($product->desi, 2) }}</span>
                                    @if($product->desi > 100)
                                        <span class="text-xs text-orange-600 block">100+ desi</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 sm:px-6 sm:py-3 text-center whitespace-nowrap">
                                    <span class="text-gray-900 font-medium text-xs">{{ number_format($product->tutar, 2) }} ₺</span>
                                </td>
                                <td class="px-2 py-2 sm:px-4 sm:py-3 text-center hidden lg:table-cell">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        {{ $product->kategori ?? '-' }}
                                    </span>
                                </td>
                                <td class="px-2 py-2 sm:px-4 sm:py-3 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button 
                                            wire:click="startEdit({{ $product->id }})"
                                            class="text-blue-600 hover:text-blue-800 p-2 hover:bg-blue-50 rounded"
                                            title="Düzenle"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </button>
                                        <button 
                                            wire:click="confirmDelete({{ $product->id }})"
                                            class="text-red-600 hover:text-red-800 p-2 hover:bg-red-50 rounded"
                                            title="Sil"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                <svg class="w-12 h-12 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                                <p class="text-lg font-medium">Ürün bulunamadı</p>
                                <p class="text-sm">Excel dosyası yükleyerek veya manuel olarak ürün ekleyebilirsiniz.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($this->products->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $this->products->links() }}
            </div>
        @endif
    </div>

    {{-- Import Modal --}}
    @if($showImportModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showImportModal', false)"></div>
                
                <div class="relative bg-white rounded-lg w-full max-w-2xl mx-4 sm:mx-auto shadow-xl max-h-[90vh] overflow-y-auto">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Excel'den İçeri Aktar</h3>
                    </div>

                    <div class="p-6">
                        {{-- Dosya Yükleme --}}
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Excel Dosyası Seçin</label>
                            <input 
                                type="file" 
                                wire:model="importFile"
                                accept=".xlsx,.xls"
                                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                            >
                            <p class="mt-1 text-xs text-gray-500">
                                Excel dosyasında şu kolonlar olmalı: Stok Kodu, Ürün Adı, Parça, Desi, Tutar
                            </p>
                        </div>

                        {{-- Önizleme --}}
                        @if(!empty($importPreview))
                            <div class="mb-4">
                                <h4 class="text-sm font-medium text-gray-700 mb-2">Önizleme (İlk 5 satır)</h4>
                                <div class="overflow-x-auto border rounded-lg">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-2 text-left">Stok Kodu</th>
                                                <th class="px-3 py-2 text-left">Ürün Adı</th>
                                                <th class="px-3 py-2 text-center">Parça</th>
                                                <th class="px-3 py-2 text-center">Desi</th>
                                                <th class="px-3 py-2 text-center">Tutar</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            @foreach($importPreview as $row)
                                                <tr>
                                                    <td class="px-3 py-2 font-mono">{{ $row['stok_kodu'] ?? '-' }}</td>
                                                    <td class="px-3 py-2">{{ $row['urun_adi'] ?? '-' }}</td>
                                                    <td class="px-3 py-2 text-center">{{ $row['parca'] ?? '-' }}</td>
                                                    <td class="px-3 py-2 text-center">{{ $row['desi'] ?? '-' }}</td>
                                                    <td class="px-3 py-2 text-center">{{ $row['tutar'] ?? '-' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <p class="mt-2 text-sm text-gray-600">Toplam {{ $importCount }} satır bulundu.</p>
                            </div>
                        @endif
                    </div>

                    <div class="px-6 py-4 border-t border-gray-200 flex flex-col sm:flex-row justify-end gap-3">
                        <button 
                            wire:click="$set('showImportModal', false)"
                            class="w-full sm:w-auto px-4 py-3 sm:py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50"
                        >
                            İptal
                        </button>
                        <button 
                            wire:click="executeImport"
                            class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                            @if(!$importFile) disabled @endif
                        >
                            <span wire:loading.remove wire:target="executeImport">İçeri Aktar</span>
                            <span wire:loading wire:target="executeImport">Aktarılıyor...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Silme Onay Modal --}}
    @if($showDeleteModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showDeleteModal', false)"></div>
                
                <div class="relative bg-white rounded-lg w-full max-w-md mx-4 sm:mx-auto shadow-xl">
                    <div class="p-6 text-center">
                        <svg class="w-16 h-16 mx-auto text-red-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Ürünü Sil</h3>
                        <p class="text-gray-500 mb-6">Bu ürünü silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.</p>
                        
                        <div class="flex flex-col sm:flex-row justify-center gap-3">
                            <button 
                                wire:click="$set('showDeleteModal', false)"
                                class="w-full sm:w-auto px-4 py-3 sm:py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50"
                            >
                                İptal
                            </button>
                            <button 
                                wire:click="deleteProduct"
                                class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
                            >
                                Sil
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
