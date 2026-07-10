@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $statusLabel = fn ($status) => match ($status) {
        'active' => 'Aktif',
        'out_of_stock' => 'Tükendi',
        'pending' => 'Onay Bekliyor',
        'suspended' => 'Pasif',
        default => 'Bilinmiyor',
    };
@endphp

<div class="w-full space-y-4 lg:space-y-6">
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                    Ürün Kartları
                </div>
                <h1 class="mt-3 text-xl font-semibold tracking-tight text-slate-950 lg:text-2xl">Ürün Kartları</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
                    Barkod, SKU, kategori, KDV, maliyet, satış fiyatı ve kritik stok seviyesini tek formda yönetin.
                </p>
            </div>
            <button wire:click="openCreateForm" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-slate-800 sm:w-auto">
                + Yeni Ürün
            </button>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Toplam Ürün</p>
                <p class="mt-2 text-xl font-bold text-slate-900">{{ $this->kpis['total'] }}</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Aktif</p>
                <p class="mt-2 text-xl font-bold text-emerald-700">{{ $this->kpis['active'] }}</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Kritik Stok</p>
                <p class="mt-2 text-xl font-bold text-rose-700">{{ $this->kpis['critical'] }}</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Stok Değeri</p>
                <p class="mt-2 text-xl font-bold text-blue-700">{{ $formatMoney($this->kpis['stock_value']) }}</p>
            </div>
        </div>
    </section>

    @if($message !== '')
        <div class="rounded-[8px] border px-4 py-3 text-sm {{ $messageType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800' }}">
            {{ $message }}
        </div>
    @endif

    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 p-4 lg:p-5">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Ürün, SKU, barkod, marka ara..." class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 outline-none transition focus:border-slate-400 sm:col-span-2 sm:text-sm">
                <select wire:model.live="filterStatus" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 outline-none transition focus:border-slate-400 sm:text-sm">
                    <option value="">Tüm Durumlar</option>
                    <option value="active">Aktif</option>
                    <option value="out_of_stock">Tükendi</option>
                    <option value="pending">Onay Bekliyor</option>
                    <option value="suspended">Pasif</option>
                </select>
                <select wire:model.live="filterCritical" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 outline-none transition focus:border-slate-400 sm:text-sm">
                    <option value="">Tüm Stoklar</option>
                    <option value="critical">Kritik stokta</option>
                </select>
            </div>
        </div>

        @if($showForm)
            <div class="border-b border-slate-100 bg-slate-50/60 p-4 lg:p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ $isEditing ? 'Ürün Kartını Düzenle' : 'Yeni Ürün Kartı' }}</h2>
                        <p class="mt-1 text-xs text-slate-500">Barkod ve SKU alanları satış, stok, POS ve pazaryeri köprüsünde kullanılır.</p>
                    </div>
                    <button wire:click="resetForm" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Kapat</button>
                </div>

                <form wire:submit.prevent="saveProduct" class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Barkod</label>
                        <input wire:model.defer="barcode" type="text" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                        @error('barcode') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">SKU / Stok Kodu</label>
                        <input wire:model.defer="stockCode" type="text" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                        @error('stockCode') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Ürün Adı</label>
                        <input wire:model.defer="productName" type="text" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                        @error('productName') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Marka</label>
                        <input wire:model.defer="brand" type="text" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Kategori</label>
                        <input wire:model.defer="categoryName" type="text" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Birim</label>
                        <input wire:model.defer="unitName" type="text" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Durum</label>
                        <select wire:model.defer="status" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                            <option value="active">Aktif</option>
                            <option value="out_of_stock">Tükendi</option>
                            <option value="pending">Onay Bekliyor</option>
                            <option value="suspended">Pasif</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">KDV %</label>
                        <input wire:model.defer="vatRate" type="number" step="0.01" min="0" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Alış/Maliyet</label>
                        <input wire:model.defer="cogs" type="number" step="0.01" min="0" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Satış Fiyatı</label>
                        <input wire:model.defer="salePrice" type="number" step="0.01" min="0" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Stok</label>
                        <input wire:model.defer="stockQuantity" type="number" min="0" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Kritik Stok</label>
                        <input wire:model.defer="criticalStockThreshold" type="number" min="0" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                    </div>
                    <div class="sm:col-span-2 xl:col-span-4">
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Açıklama</label>
                        <textarea wire:model.defer="description" rows="2" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 sm:col-span-2 xl:col-span-4">
                        <button type="button" wire:click="resetForm" class="min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Vazgeç</button>
                        <button type="submit" class="min-h-[44px] rounded-[6px] bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Kaydet</button>
                    </div>
                </form>
            </div>
        @endif

        <div class="hidden overflow-x-auto md:block">
            <table class="w-full table-fixed text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50/80 text-[11px] uppercase tracking-[0.16em] text-slate-500">
                    <tr>
                        <th class="w-[260px] cursor-pointer px-4 py-3" wire:click="sortTable('product_name')">Ürün</th>
                        <th class="w-[130px] cursor-pointer px-4 py-3" wire:click="sortTable('stock_code')">SKU</th>
                        <th class="w-[150px] cursor-pointer px-4 py-3" wire:click="sortTable('barcode')">Barkod</th>
                        <th class="w-[150px] cursor-pointer px-4 py-3" wire:click="sortTable('category_name')">Kategori</th>
                        <th class="w-[90px] px-4 py-3">Birim</th>
                        <th class="w-[110px] cursor-pointer px-4 py-3 text-right" wire:click="sortTable('sale_price')">Satış</th>
                        <th class="w-[100px] cursor-pointer px-4 py-3 text-right" wire:click="sortTable('stock_quantity')">Stok</th>
                        <th class="w-[110px] cursor-pointer px-4 py-3" wire:click="sortTable('status')">Durum</th>
                        <th class="w-[150px] px-4 py-3 text-right">İşlem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($this->products as $product)
                        @php
                            $isCritical = $product->critical_stock_threshold !== null && (int) $product->stock_quantity <= (int) $product->critical_stock_threshold;
                        @endphp
                        <tr class="align-top hover:bg-slate-50/70">
                            <td class="px-4 py-3">
                                <div class="truncate font-semibold text-slate-900">{{ $product->product_name }}</div>
                                <div class="mt-1 truncate text-xs text-slate-500">{{ $product->brand ?: 'Marka yok' }}</div>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-700">{{ $product->stock_code }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $product->barcode }}</td>
                            <td class="px-4 py-3 text-xs text-slate-600">{{ $product->category_name ?: '-' }}</td>
                            <td class="px-4 py-3 text-xs text-slate-600">{{ $product->unit_name ?: 'adet' }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-900">{{ $formatMoney($product->sale_price) }}</td>
                            <td class="px-4 py-3 text-right">
                                <span class="font-semibold {{ $isCritical ? 'text-rose-700' : 'text-slate-900' }}">{{ $product->stock_quantity }}</span>
                                @if($isCritical)
                                    <div class="mt-1 text-[11px] font-semibold text-rose-600">Kritik</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded px-2 py-0.5 text-[11px] font-semibold {{ $product->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $statusLabel($product->status) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <button wire:click="editProduct({{ $product->id }})" class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Düzenle</button>
                                    @if($product->status === 'suspended')
                                        <button wire:click="markActive({{ $product->id }})" class="rounded-[6px] bg-slate-900 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-slate-800">Aktifle</button>
                                    @else
                                        <button wire:click="markPassive({{ $product->id }})" class="rounded-[6px] bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-200">Pasifle</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-10 text-center text-sm text-slate-500">Ürün kartı bulunamadı.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="space-y-3 p-4 md:hidden">
            @forelse($this->products as $product)
                @php
                    $isCritical = $product->critical_stock_threshold !== null && (int) $product->stock_quantity <= (int) $product->critical_stock_threshold;
                @endphp
                <div class="rounded-[8px] border border-slate-200 bg-white p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="truncate font-semibold text-slate-900">{{ $product->product_name }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $product->stock_code }} · {{ $product->unit_name ?: 'adet' }}</div>
                        </div>
                        <div class="text-right text-sm font-semibold text-slate-900">{{ $formatMoney($product->sale_price) }}</div>
                    </div>
                    <div class="mt-3 flex items-center justify-between text-xs">
                        <span class="text-slate-600">{{ $product->category_name ?: 'Kategori yok' }}</span>
                        <span class="{{ $isCritical ? 'text-rose-700 font-semibold' : 'text-slate-600' }}">Stok: {{ $product->stock_quantity }}</span>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <button wire:click="editProduct({{ $product->id }})" class="min-h-[40px] flex-1 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700">Düzenle</button>
                        @if($product->status === 'suspended')
                            <button wire:click="markActive({{ $product->id }})" class="min-h-[40px] flex-1 rounded-[6px] bg-slate-900 px-3 py-2 text-xs font-medium text-white">Aktifle</button>
                        @else
                            <button wire:click="markPassive({{ $product->id }})" class="min-h-[40px] flex-1 rounded-[6px] bg-slate-100 px-3 py-2 text-xs font-medium text-slate-600">Pasifle</button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-[8px] border border-dashed border-slate-200 p-6 text-center text-sm text-slate-500">Ürün kartı bulunamadı.</div>
            @endforelse
        </div>

        <div class="border-t border-slate-100 px-4 py-3">
            {{ $this->products->links() }}
        </div>
    </section>
</div>
