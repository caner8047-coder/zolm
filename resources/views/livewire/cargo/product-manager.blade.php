@php
    $stats = $this->stats;
    $categories = $this->categories;
    $columnDefs = \App\Livewire\Cargo\ProductManager::$allColumnDefs;
    $sortableColumns = \App\Livewire\Cargo\ProductManager::$sortableColumns;

    $messageClasses = match ($messageType) {
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'error' => 'border-rose-200 bg-rose-50 text-rose-800',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
        default => 'border-sky-200 bg-sky-50 text-sky-800',
    };

    $formatCount = fn ($value) => number_format((float) $value, 0, ',', '.');
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $sortIcon = function (string $columnKey) use ($sortableColumns, $sortField, $sortDirection) {
        $dbColumn = $sortableColumns[$columnKey] ?? null;
        if (!$dbColumn) {
            return '';
        }

        return $sortField === $dbColumn
            ? ($sortDirection === 'asc' ? '▲' : '▼')
            : '⇅';
    };
    $activeFilters = array_values(array_filter([
        $search !== '' ? 'Arama: ' . $search : null,
        $filterCategory !== '' ? 'Kategori: ' . \App\Models\Product::getCategoryName($filterCategory) : null,
    ]));
@endphp

<div class="w-full space-y-6 overflow-hidden">
    @if($message)
        <div class="rounded-2xl border p-4 text-sm shadow-sm {{ $messageClasses }}">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <p>{{ $message }}</p>
                <button wire:click="clearMessage" class="rounded-lg border border-current/10 px-3 py-2 text-sm">Kapat</button>
            </div>
        </div>
    @endif

    <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-4 lg:gap-6">
        <div class="min-w-0 max-w-3xl">
            <h2 class="text-xl lg:text-2xl font-bold text-slate-900">Referans ürün kartları</h2>
            <p class="mt-1 text-sm lg:text-base text-slate-700">
                Karşılaştırmada kullanılacak stok, desi ve tutar referanslarını tek listede yönetin.
            </p>
        </div>

        <div class="flex w-full xl:w-auto flex-col sm:flex-row gap-2 sm:gap-3">
            <button wire:click="openImportModal" class="min-h-[44px] w-full sm:w-auto rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">İçeri Aktar</button>
            <button wire:click="exportToExcel" class="min-h-[44px] w-full sm:w-auto rounded-md border border-emerald-200 bg-white px-4 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-50">Excel Dışa Aktar</button>
            <x-zolm.primary-button color="emerald" compact wire:click="$set('showAddForm', true)">Yeni Ürün</x-zolm.primary-button>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 lg:gap-4">
        <x-zolm.stat-card variant="orders" label="Toplam ürün" :value="$formatCount($stats['total'])" description="Referans havuzundaki tüm ürünler" tone="info">
            <x-slot:icon>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8 5-8-5m16 0l-8-5-8 5m16 0v10l-8 5-8-5V7" />
                </svg>
            </x-slot:icon>
        </x-zolm.stat-card>
        <x-zolm.stat-card variant="orders" label="Aktif ürün" :value="$formatCount($stats['active'])" description="Karşılaştırmada hazır kayıtlar" tone="success">
            <x-slot:icon>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </x-slot:icon>
        </x-zolm.stat-card>
        <x-zolm.stat-card variant="orders" label="Kategori" :value="$formatCount($stats['categories'])" description="Aktif kategori sayısı" tone="warning">
            <x-slot:icon>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h10M7 12h6m-6 5h10M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" />
                </svg>
            </x-slot:icon>
        </x-zolm.stat-card>
    </div>

    @if($showAddForm)
        <x-zolm.section-card variant="orders" eyebrow="Yeni Kayıt" title="Yeni ürün ekle" description="Stok kodu, desi ve kargo tutarı alanlarını eksiksiz girin." padding="p-4 lg:p-6">
            <form wire:submit="addProduct" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-6 gap-3 lg:gap-4">
                <div>
                    <label class="text-xs sm:text-sm font-medium text-slate-700">Stok kodu</label>
                    <input type="text" wire:model="newProduct.stok_kodu" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 @error('newProduct.stok_kodu') border-rose-400 @enderror" placeholder="1BRJZEM00001">
                    @error('newProduct.stok_kodu') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2 xl:col-span-2">
                    <label class="text-xs sm:text-sm font-medium text-slate-700">Ürün adı</label>
                    <input type="text" wire:model="newProduct.urun_adi" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 @error('newProduct.urun_adi') border-rose-400 @enderror" placeholder="Ürün adını girin">
                </div>
                <div>
                    <label class="text-xs sm:text-sm font-medium text-slate-700">Parça</label>
                    <input type="number" min="1" max="20" wire:model="newProduct.parca" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                </div>
                <div>
                    <label class="text-xs sm:text-sm font-medium text-slate-700">Desi</label>
                    <input type="number" step="0.01" min="0" wire:model="newProduct.desi" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                </div>
                <div>
                    <label class="text-xs sm:text-sm font-medium text-slate-700">Tutar</label>
                    <input type="number" step="0.01" min="0" wire:model="newProduct.tutar" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                </div>
                <div class="sm:col-span-2 xl:col-span-6 flex flex-col sm:flex-row justify-end gap-3 pt-2">
                    <button type="button" wire:click="$set('showAddForm', false)" class="w-full sm:w-auto rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">İptal</button>
                    <x-zolm.primary-button color="emerald" compact type="submit">Kaydet</x-zolm.primary-button>
                </div>
            </form>
        </x-zolm.section-card>
    @endif

    <x-zolm.section-card
        variant="orders"
        eyebrow="Ürün Listesi"
        title="Referans kayıtları"
        description="Arama, sıralama, düzenleme ve silme işlemleri bu listeden yapılır."
        headerPadding="px-4 pt-4 pb-2 lg:px-6 lg:pt-6 lg:pb-2"
        bodyPadding="px-4 pb-4 lg:px-6 lg:pb-6"
    >
        @once
            <style>
                .col-resize-handle { position: absolute; right: 0; top: 0; bottom: 0; width: 4px; cursor: col-resize; background: transparent; z-index: 10; transition: background 0.15s; }
                .col-resize-handle:hover, .col-resize-handle.active { background: #6366f1; }
                .sortable-th { cursor: pointer; user-select: none; position: relative; }
                .sortable-th:hover { background: #f8fafc; }
                #cargoProductsTable .text-xs { font-size: 10px !important; }
                #cargoProductsTable .text-sm { font-size: 12px !important; }
                #cargoProductsTable .text-\[10px\] { font-size: 8px !important; }
                #cargoProductsTable { table-layout: fixed; width: 100%; }
                #cargoProductsTable th, #cargoProductsTable td { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            </style>
            <script>
                document.addEventListener('alpine:init', () => {
                    Alpine.data('columnResize', () => ({
                        resizing: false,
                        startX: 0,
                        startWidth: 0,
                        currentTh: null,
                        handle: null,
                        startResize(e, th) {
                            this.resizing = true;
                            this.startX = e.pageX;
                            this.currentTh = th;
                            this.startWidth = th.offsetWidth;
                            this.handle = e.target;
                            this.handle.classList.add('active');

                            const onMouseMove = (ev) => {
                                if (!this.resizing) return;
                                const newWidth = Math.max(40, this.startWidth + (ev.pageX - this.startX));
                                this.currentTh.style.width = newWidth + 'px';
                                this.currentTh.style.minWidth = newWidth + 'px';
                            };

                            const onMouseUp = () => {
                                this.resizing = false;
                                if (this.handle) this.handle.classList.remove('active');
                                document.removeEventListener('mousemove', onMouseMove);
                                document.removeEventListener('mouseup', onMouseUp);
                            };

                            document.addEventListener('mousemove', onMouseMove);
                            document.addEventListener('mouseup', onMouseUp);
                        }
                    }));
                });
            </script>
        @endonce

        @php
            $columnMeta = [
                'stok_kodu' => ['width' => '140px', 'align' => 'text-left'],
                'urun_adi' => ['width' => '240px', 'align' => 'text-left'],
                'parca' => ['width' => '68px', 'align' => 'text-center'],
                'desi' => ['width' => '88px', 'align' => 'text-center'],
                'tutar' => ['width' => '96px', 'align' => 'text-center'],
                'kategori' => ['width' => '96px', 'align' => 'text-center'],
            ];
        @endphp

        <div class="space-y-3">
            <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-4">
                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3 lg:gap-4">
                    <div>
                        <h3 class="text-base lg:text-lg font-semibold text-slate-900">Referans arama</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            Stok, ürün adı veya kategori ile havuzu daraltın.
                        </p>
                    </div>
                    <div class="flex flex-col items-start gap-2 lg:items-end">
                        <p class="text-sm text-slate-500 lg:text-right">
                            {{ count($activeFilters) > 0 ? implode(' · ', $activeFilters) : 'Henüz aktif arama veya kategori filtresi yok.' }}
                            · {{ $formatCount($this->products->total()) }} kayıt gösteriliyor
                            @if($showAddForm)
                                · Yeni ürün formu açık
                            @endif
                        </p>
                        <div class="flex items-center gap-3">
                            <div class="text-xs text-slate-500">{{ count($visibleColumns) }} / {{ count($columnDefs) }} kolon gösteriliyor</div>
                            <div x-data="{ open: false }" class="relative">
                                <button @click="open = !open" type="button" class="w-full sm:w-auto rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                    Kolonlar
                                </button>
                                <div x-show="open" @click.outside="open = false" x-transition class="absolute right-0 top-full z-30 mt-2 w-60 rounded-[10px] border border-slate-200 bg-white p-3 shadow-xl">
                                    <p class="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">Görünür Kolonlar</p>
                                    <div class="mt-3 space-y-1.5">
                                        @foreach($columnDefs as $colKey => $colLabel)
                                            <label class="flex items-center gap-2 rounded-lg px-2 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                                <input type="checkbox" wire:click="toggleColumn('{{ $colKey }}')" {{ in_array($colKey, $visibleColumns, true) ? 'checked' : '' }} class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-200">
                                                <span>{{ $colLabel }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-8 gap-3 lg:gap-4">
                    <div class="xl:col-span-5">
                        <label class="block text-xs sm:text-sm font-medium text-slate-500">Arama</label>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Stok kodu, ürün adı veya kategori kodu ara..."
                            class="mt-1 min-h-[44px] w-full rounded-md border border-slate-300 px-3 py-2 text-base sm:text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200"
                        >
                    </div>
                    <div class="xl:col-span-3">
                        <label class="block text-xs sm:text-sm font-medium text-slate-500">Kategori</label>
                        <select wire:model.live="filterCategory" class="mt-1 min-h-[44px] w-full rounded-md border border-slate-300 px-3 py-2 text-base sm:text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                            <option value="">Tüm kategoriler</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}">{{ \App\Models\Product::getCategoryName($cat) }} ({{ $cat }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white overflow-hidden">
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full" x-data="columnResize()" id="cargoProductsTable">
                        <thead class="bg-slate-50 text-slate-500">
                            <tr>
                                @foreach($columnDefs as $colKey => $colLabel)
                                    @if(in_array($colKey, $visibleColumns, true))
                                        @php
                                            $meta = $columnMeta[$colKey];
                                            $align = $meta['align'];
                                        @endphp
                                        <th class="px-2 py-2 {{ $align }} text-xs font-medium uppercase tracking-[0.14em] sortable-th" style="position: relative; width: {{ $meta['width'] }}; min-width: 40px;" wire:click="sortTable('{{ $colKey }}')">
                                            <div class="flex items-center gap-1 {{ $align === 'text-center' ? 'justify-center' : ($align === 'text-right' ? 'justify-end' : '') }}">
                                                <span>{{ $colLabel }}</span>
                                                <span class="text-[10px] {{ ($sortableColumns[$colKey] ?? null) === $sortField ? 'text-slate-700' : 'text-slate-300' }}">{{ $sortIcon($colKey) }}</span>
                                            </div>
                                            <div class="col-resize-handle" @mousedown.stop.prevent="startResize($event, $el.parentElement)"></div>
                                        </th>
                                    @endif
                                @endforeach
                                <th class="w-20 px-2 py-2 text-right text-xs font-medium uppercase tracking-[0.14em]">İşlem</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse($this->products as $product)
                                <tr class="bg-white hover:bg-slate-50 transition">
                                    @if($editingId === $product->id)
                                        @if(in_array('stok_kodu', $visibleColumns, true))
                                            <td class="px-2 py-2"><input type="text" wire:model="editingProduct.stok_kodu" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200"></td>
                                        @endif
                                        @if(in_array('urun_adi', $visibleColumns, true))
                                            <td class="px-2 py-2"><input type="text" wire:model="editingProduct.urun_adi" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200"></td>
                                        @endif
                                        @if(in_array('parca', $visibleColumns, true))
                                            <td class="px-2 py-2 text-center"><input type="number" min="1" wire:model="editingProduct.parca" class="w-14 rounded-xl border border-slate-200 px-2 py-2 text-center text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200"></td>
                                        @endif
                                        @if(in_array('desi', $visibleColumns, true))
                                            <td class="px-2 py-2 text-center"><input type="number" step="0.01" wire:model="editingProduct.desi" class="w-20 rounded-xl border border-slate-200 px-2 py-2 text-center text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200"></td>
                                        @endif
                                        @if(in_array('tutar', $visibleColumns, true))
                                            <td class="px-2 py-2 text-center"><input type="number" step="0.01" wire:model="editingProduct.tutar" class="w-20 rounded-xl border border-slate-200 px-2 py-2 text-center text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200"></td>
                                        @endif
                                        @if(in_array('kategori', $visibleColumns, true))
                                            <td class="px-2 py-2 text-center text-sm text-slate-400">{{ $product->kategori ?? '-' }}</td>
                                        @endif
                                        <td class="px-2 py-2">
                                            <div class="flex justify-end gap-2">
                                                <button wire:click="saveEdit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 transition hover:bg-emerald-100">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                </button>
                                                <button wire:click="cancelEdit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </div>
                                        </td>
                                    @else
                                        @if(in_array('stok_kodu', $visibleColumns, true))
                                            <td class="px-2 py-2"><span class="font-mono text-xs text-slate-700">{{ $product->stok_kodu }}</span></td>
                                        @endif
                                        @if(in_array('urun_adi', $visibleColumns, true))
                                            <td class="px-2 py-2"><p class="line-clamp-2 text-sm text-slate-900" title="{{ $product->urun_adi }}">{{ $product->urun_adi }}</p></td>
                                        @endif
                                        @if(in_array('parca', $visibleColumns, true))
                                            <td class="px-2 py-2 text-center"><x-zolm.status-badge tone="info">{{ $product->parca }}</x-zolm.status-badge></td>
                                        @endif
                                        @if(in_array('desi', $visibleColumns, true))
                                            <td class="px-2 py-2 text-center text-sm font-medium text-slate-900">{{ number_format($product->desi, 2, ',', '.') }}</td>
                                        @endif
                                        @if(in_array('tutar', $visibleColumns, true))
                                            <td class="px-2 py-2 text-center text-sm font-medium text-slate-900">{{ $formatMoney($product->tutar) }}</td>
                                        @endif
                                        @if(in_array('kategori', $visibleColumns, true))
                                            <td class="px-2 py-2 text-center"><x-zolm.status-badge>{{ $product->kategori ?? '-' }}</x-zolm.status-badge></td>
                                        @endif
                                        <td class="px-2 py-2">
                                            <div class="flex justify-end gap-2">
                                                <button wire:click="startEdit({{ $product->id }})" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                </button>
                                                <button wire:click="confirmDelete({{ $product->id }})" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-700 transition hover:bg-rose-100">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($visibleColumns) + 1 }}" class="px-4 py-10 text-center text-sm text-slate-500">Kayıtlı ürün bulunamadı. Excel yükleyebilir veya manuel ürün ekleyebilirsiniz.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="md:hidden space-y-3 p-4">
                    @forelse($this->products as $product)
                        <div class="rounded-xl border border-slate-200 p-4">
                            @if($editingId === $product->id)
                                <div class="space-y-3">
                                    @if(in_array('stok_kodu', $visibleColumns, true))
                                        <input type="text" wire:model="editingProduct.stok_kodu" placeholder="Stok kodu" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-base sm:text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                    @endif
                                    @if(in_array('urun_adi', $visibleColumns, true))
                                        <input type="text" wire:model="editingProduct.urun_adi" placeholder="Ürün adı" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-base sm:text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                    @endif
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                        @if(in_array('parca', $visibleColumns, true))
                                            <input type="number" min="1" wire:model="editingProduct.parca" placeholder="Parça" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-center text-base sm:text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                        @endif
                                        @if(in_array('desi', $visibleColumns, true))
                                            <input type="number" step="0.01" wire:model="editingProduct.desi" placeholder="Desi" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-center text-base sm:text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                        @endif
                                        @if(in_array('tutar', $visibleColumns, true))
                                            <input type="number" step="0.01" wire:model="editingProduct.tutar" placeholder="Tutar" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-center text-base sm:text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                        @endif
                                    </div>
                                    <div class="flex flex-col sm:flex-row gap-2">
                                        <button wire:click="saveEdit" class="w-full sm:w-auto rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 transition hover:bg-emerald-100">Kaydet</button>
                                        <button wire:click="cancelEdit" class="w-full sm:w-auto rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">İptal</button>
                                    </div>
                                </div>
                            @else
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 text-xs text-slate-500">
                                            @if(in_array('stok_kodu', $visibleColumns, true))
                                                <span class="font-mono">{{ $product->stok_kodu }}</span>
                                            @endif
                                            @if(in_array('kategori', $visibleColumns, true) && $product->kategori)
                                                <x-zolm.status-badge>{{ $product->kategori }}</x-zolm.status-badge>
                                            @endif
                                        </div>
                                        @if(in_array('urun_adi', $visibleColumns, true))
                                            <p class="mt-2 line-clamp-2 text-sm font-semibold text-slate-900">{{ $product->urun_adi }}</p>
                                        @endif
                                    </div>
                                    <div class="flex gap-2">
                                        <button wire:click="startEdit({{ $product->id }})" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button wire:click="confirmDelete({{ $product->id }})" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-700 transition hover:bg-rose-100">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-2">
                                    @if(in_array('parca', $visibleColumns, true))
                                        <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"><span class="text-slate-500">Parça</span><span class="font-semibold text-slate-900">{{ $product->parca }}</span></div>
                                    @endif
                                    @if(in_array('desi', $visibleColumns, true))
                                        <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"><span class="text-slate-500">Desi</span><span class="font-semibold text-slate-900">{{ number_format($product->desi, 2, ',', '.') }}</span></div>
                                    @endif
                                    @if(in_array('tutar', $visibleColumns, true))
                                        <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"><span class="text-slate-500">Tutar</span><span class="font-semibold text-slate-900">{{ $formatMoney($product->tutar) }}</span></div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center text-sm text-slate-500">Kayıtlı ürün bulunamadı.</div>
                    @endforelse
                </div>

                @if($this->products->hasPages())
                    <div class="border-t border-slate-200 px-4 py-3">
                        {{ $this->products->links() }}
                    </div>
                @endif
            </div>
        </div>
    </x-zolm.section-card>

    @if($showImportModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex min-h-screen items-center justify-center px-4 py-6 sm:py-8">
                <button type="button" class="fixed inset-0 bg-slate-900/40" wire:click="$set('showImportModal', false)"></button>

                <div class="relative flex w-full max-h-[100dvh] min-h-0 flex-col overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-xl sm:max-h-[90vh] sm:max-w-2xl">
                    <div class="shrink-0 border-b border-slate-200 px-4 py-4 lg:px-6">
                        <x-zolm.eyebrow variant="classic">Excel Import</x-zolm.eyebrow>
                        <h3 class="mt-3 text-xl font-bold text-slate-900">Excel’den içeri aktar</h3>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4 lg:px-6">
                        <div class="space-y-6">
                        <div>
                            <label class="text-xs sm:text-sm font-medium text-slate-700">Excel dosyası</label>
                            <input type="file" wire:model="importFile" accept=".xlsx,.xls" class="mt-1 block w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-slate-800">
                            <p class="mt-2 text-sm text-slate-500">Kolonlar: Stok Kodu, Ürün Adı, Parça, Desi, Tutar</p>
                        </div>

                        @if(!empty($importPreview))
                            <div>
                                <p class="text-sm font-semibold text-slate-900">Önizleme</p>
                                <div class="mt-3 rounded-lg border border-slate-200 bg-white overflow-hidden">
                                    <div class="hidden md:block overflow-x-auto">
                                        <table class="min-w-full table-fixed text-sm">
                                            <thead class="bg-slate-50 text-slate-500">
                                                <tr>
                                                    <th class="w-36 px-3 py-2 text-left text-xs font-medium uppercase tracking-[0.16em]">Stok Kodu</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-[0.16em]">Ürün Adı</th>
                                                    <th class="w-20 px-3 py-2 text-center text-xs font-medium uppercase tracking-[0.16em]">Parça</th>
                                                    <th class="w-24 px-3 py-2 text-center text-xs font-medium uppercase tracking-[0.16em]">Desi</th>
                                                    <th class="w-24 px-3 py-2 text-center text-xs font-medium uppercase tracking-[0.16em]">Tutar</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-200">
                                                @foreach($importPreview as $row)
                                                    <tr class="bg-white">
                                                        <td class="px-3 py-2 font-mono text-xs text-slate-700">{{ $row['stok_kodu'] ?? '-' }}</td>
                                                        <td class="px-3 py-2 text-sm text-slate-900">{{ $row['urun_adi'] ?? '-' }}</td>
                                                        <td class="px-3 py-2 text-center text-sm text-slate-700">{{ $row['parca'] ?? '-' }}</td>
                                                        <td class="px-3 py-2 text-center text-sm text-slate-700">{{ $row['desi'] ?? '-' }}</td>
                                                        <td class="px-3 py-2 text-center text-sm text-slate-700">{{ $row['tutar'] ?? '-' }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="md:hidden space-y-3 p-4">
                                        @foreach($importPreview as $row)
                                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                                <div class="flex items-center justify-between gap-3">
                                                    <span class="font-mono text-xs text-slate-700">{{ $row['stok_kodu'] ?? '-' }}</span>
                                                    <x-zolm.status-badge tone="info">Önizleme</x-zolm.status-badge>
                                                </div>
                                                <p class="mt-2 line-clamp-2 text-sm font-semibold text-slate-900">{{ $row['urun_adi'] ?? '-' }}</p>
                                                <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-2">
                                                    <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                                        <span class="text-slate-500">Parça</span>
                                                        <span class="font-semibold text-slate-900">{{ $row['parca'] ?? '-' }}</span>
                                                    </div>
                                                    <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                                        <span class="text-slate-500">Desi</span>
                                                        <span class="font-semibold text-slate-900">{{ $row['desi'] ?? '-' }}</span>
                                                    </div>
                                                    <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                                        <span class="text-slate-500">Tutar</span>
                                                        <span class="font-semibold text-slate-900">{{ $row['tutar'] ?? '-' }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <p class="mt-2 text-sm text-slate-500">Toplam {{ $formatCount($importCount) }} satır bulundu.</p>
                            </div>
                        @endif
                        </div>
                    </div>

                    <div class="shrink-0 flex flex-col sm:flex-row justify-end gap-3 border-t border-slate-200 bg-white px-4 py-4 lg:px-6">
                        <button wire:click="$set('showImportModal', false)" class="w-full sm:w-auto rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">İptal</button>
                        @if($importFile)
                            <x-zolm.primary-button color="indigo" compact wire:click="executeImport">
                                <span wire:loading.remove wire:target="executeImport">İçeri Aktar</span>
                                <span wire:loading wire:target="executeImport">Aktarılıyor...</span>
                            </x-zolm.primary-button>
                        @else
                            <x-zolm.primary-button color="indigo" compact wire:click="executeImport" disabled>
                                <span>İçeri Aktar</span>
                            </x-zolm.primary-button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($showDeleteModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex min-h-screen items-center justify-center px-4 py-6 sm:py-8">
                <button type="button" class="fixed inset-0 bg-slate-900/40" wire:click="$set('showDeleteModal', false)"></button>

                <div class="relative w-full max-w-md rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-xl">
                    <x-zolm.eyebrow variant="classic">Silme Onayı</x-zolm.eyebrow>
                    <h3 class="mt-3 text-xl font-bold text-slate-900">Ürünü kaldır</h3>
                    <p class="mt-2 text-sm text-slate-500">Silinen ürün referans listeden çıkar ve sonraki kontrolleri etkiler.</p>

                    <div class="rounded-[8px] border border-rose-200 bg-rose-50 p-4 text-center">
                        <p class="text-sm font-semibold text-rose-800">Ürün kaydı silinecek</p>
                        <p class="mt-2 text-sm text-rose-700">Bu işlem seçili ürünü kalıcı olarak kaldırır.</p>
                    </div>
                    <div class="mt-6 flex flex-col sm:flex-row justify-center gap-3">
                        <button wire:click="$set('showDeleteModal', false)" class="w-full sm:w-auto rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">İptal</button>
                        <button wire:click="deleteProduct" class="w-full sm:w-auto rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 sm:py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-100">Sil</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
