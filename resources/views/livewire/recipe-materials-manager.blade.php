@php
    $activeFilterCount = collect([
        $search !== '',
        $categoryFilter !== 'all',
        $statusFilter !== 'active',
        $supplierFilter !== 'all',
    ])->filter()->count();

    $recipeFilterCount = collect([
        $searchRecipe !== '',
        $statusFilterRecipe !== 'active',
        $showRiskOnlyRecipe,
    ])->filter()->count();

    $materialTabActive = $activeTab === 'materials';
    $recipeTabActive = $activeTab === 'recipes';
@endphp

<div class="w-full space-y-4 lg:space-y-6">
    @if(session('success'))
        <div class="rounded-[8px] border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition>{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-[8px] border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ session('error') }}</div>
    @endif

    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1.1fr)_minmax(360px,0.9fr)]">
            <div class="min-w-0">
                <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                    Reçete Workspace
                </div>
                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div class="min-w-0">
                        <h1 class="text-xl font-bold tracking-tight text-slate-950 lg:text-2xl">Reçete ve Ham Madde Yönetimi</h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                            Malzeme kartları, fiyat güncellemeleri ve üretim reçeteleri aynı açık panel düzeninde yönetilir.
                        </p>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <button
                            type="button"
                            wire:click="openCreateModal"
                            class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 sm:w-auto sm:py-2"
                        >
                            <span class="text-base leading-none">+</span>
                            Yeni Malzeme
                        </button>
                        <button
                            type="button"
                            wire:click="createRecipe"
                            class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2"
                        >
                            <x-lucide.icon name="package" class="h-4 w-4" />
                            Yeni Reçete
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-3 lg:gap-4">
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3 lg:p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Malzeme</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">{{ $stats['total'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">Toplam kart</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3 lg:p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Kumaş</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">{{ $stats['fabric'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">Metraj odaklı</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3 lg:p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Sünger</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">{{ $stats['foam'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">Hacim odaklı</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3 lg:p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Ahşap</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">{{ $stats['wood'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">Alan / adet</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3 lg:p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Reçete</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">{{ $stats['recipes'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">Listelenen</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3 lg:p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Risk</p>
                    <p class="mt-2 text-2xl font-semibold {{ ($stats['risk_recipes'] ?? 0) > 0 ? 'text-amber-700' : 'text-slate-950' }}">{{ $stats['risk_recipes'] ?? 0 }}</p>
                    <p class="mt-1 text-xs text-slate-500">Kontrol bekleyen</p>
                </div>
            </div>
        </div>
    </section>

    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-4 lg:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Kontrol Yüzeyi</p>
                    <h2 class="mt-2 text-lg font-semibold text-slate-900">{{ $materialTabActive ? 'Ham madde kartları' : 'Reçete ledgerı' }}</h2>
                </div>
                <div class="grid grid-cols-2 rounded-[8px] border border-slate-200 bg-slate-50/70 p-1 sm:flex">
                    <button
                        type="button"
                        wire:click="$set('activeTab', 'materials')"
                        class="rounded-[6px] px-3 py-2 text-sm font-medium transition {{ $materialTabActive ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-900' }}"
                    >
                        Malzemeler
                    </button>
                    <button
                        type="button"
                        wire:click="$set('activeTab', 'recipes')"
                        class="rounded-[6px] px-3 py-2 text-sm font-medium transition {{ $recipeTabActive ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-900' }}"
                    >
                        Reçeteler
                    </button>
                </div>
            </div>
        </div>

        @if($activeTab === 'materials')
            <div class="border-b border-slate-200 bg-slate-50/60 p-4 lg:p-5">
                <div class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(260px,1fr)_auto]">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(260px,1fr)_180px_180px_180px]">
                        <div class="relative sm:col-span-2 xl:col-span-1">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <x-lucide.icon name="search" class="h-4 w-4" />
                            </div>
                            <input
                                wire:model.live.debounce.300ms="search"
                                type="text"
                                placeholder="Stok kodu veya malzeme adı ara"
                                class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white py-2 pl-10 pr-3 text-base text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-slate-900 focus:ring-1 focus:ring-slate-900 sm:text-sm"
                            >
                        </div>
                        <select wire:model.live="categoryFilter" class="min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 shadow-sm outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 sm:text-sm">
                            <option value="all">Tüm Kategoriler</option>
                            @foreach(\App\Models\Material::CATEGORIES as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="statusFilter" class="min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 shadow-sm outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 sm:text-sm">
                            <option value="active">Aktif</option>
                            <option value="inactive">Pasif</option>
                            <option value="all">Tümü</option>
                        </select>
                        @if(!empty($suppliers))
                            <select wire:model.live="supplierFilter" class="min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 shadow-sm outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 sm:text-sm">
                                <option value="all">Tüm Tedarikçiler</option>
                                @foreach($suppliers as $sup)
                                    <option value="{{ $sup }}">{{ $sup }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row xl:justify-end">
                        <div class="inline-grid min-h-[44px] grid-cols-2 rounded-[6px] border border-slate-200 bg-white p-1">
                            <button type="button" wire:click="$set('viewMode', 'table')" class="rounded-[5px] px-3 py-1.5 text-sm font-medium transition {{ $viewMode === 'table' ? 'bg-slate-900 text-white' : 'text-slate-500 hover:text-slate-900' }}">Tablo</button>
                            <button type="button" wire:click="$set('viewMode', 'grid')" class="rounded-[5px] px-3 py-1.5 text-sm font-medium transition {{ $viewMode === 'grid' ? 'bg-slate-900 text-white' : 'text-slate-500 hover:text-slate-900' }}">Grid</button>
                        </div>
                        <button type="button" wire:click="exportMaterials" class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2">
                            <x-lucide.icon name="upload-cloud" class="h-4 w-4" />
                            Dışa Aktar
                        </button>
                        <button type="button" wire:click="$set('showImportModal', true)" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2">
                            Excel Import
                        </button>
                    </div>
                </div>

                <div class="mt-3 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 font-medium text-slate-600">{{ $materials->total() }} kayıt</span>
                        <span class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 font-medium text-slate-600">{{ $activeFilterCount }} aktif filtre</span>
                        @if($search !== '')
                            <span class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-slate-500">Arama: {{ $search }}</span>
                        @endif
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <button type="button" wire:click="openFactoryImportModal" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm font-medium text-indigo-700 transition hover:bg-indigo-100 sm:w-auto sm:py-2">
                            Fabrika Import
                        </button>
                        <button type="button" wire:click="openVendorSyncModal" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 transition hover:bg-emerald-100 sm:w-auto sm:py-2">
                            Tedarikçi Sync
                        </button>
                        <button type="button" wire:click="exportPriceSyncTemplate" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-700 transition hover:bg-amber-100 sm:w-auto sm:py-2">
                            Fiyat Şablonu
                        </button>
                        <button type="button" wire:click="$set('showPriceSyncModal', true)" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 sm:w-auto sm:py-2">
                            Fiyat Yükle
                        </button>
                    </div>
                </div>
            </div>

            @if(count($selectedMaterials) > 0)
                <div class="border-b border-slate-200 bg-sky-50 p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                        <span class="text-sm font-semibold text-sky-900">{{ count($selectedMaterials) }} malzeme seçili</span>
                        <select wire:model="bulkAction" class="min-h-[44px] w-full rounded-[6px] border border-sky-200 bg-white px-3 py-2 text-base text-slate-900 sm:w-auto sm:text-sm">
                            <option value="">İşlem seç</option>
                            <option value="activate">Aktifleştir</option>
                            <option value="deactivate">Pasife Al</option>
                            <option value="change_waste">Fire Değiştir</option>
                            <option value="change_category">Kategori Değiştir</option>
                            <option value="update_price_percent">Toplu Zam/İndirim (%)</option>
                            <option value="delete">Sil</option>
                        </select>
                        @if($bulkAction === 'change_waste')
                            <input wire:model="bulkWasteRate" type="number" step="0.01" min="0" max="1" placeholder="Yeni fire" class="min-h-[44px] w-full rounded-[6px] border border-sky-200 bg-white px-3 py-2 text-base sm:w-36 sm:text-sm">
                        @endif
                        @if($bulkAction === 'update_price_percent')
                            <input wire:model="bulkPricePercent" type="number" step="1" placeholder="Yüzde" class="min-h-[44px] w-full rounded-[6px] border border-sky-200 bg-white px-3 py-2 text-base sm:w-44 sm:text-sm">
                        @endif
                        @if($bulkAction === 'change_category')
                            <select wire:model="bulkCategory" class="min-h-[44px] w-full rounded-[6px] border border-sky-200 bg-white px-3 py-2 text-base sm:w-auto sm:text-sm">
                                @foreach(\App\Models\Material::CATEGORIES as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        @endif
                        <button type="button" wire:click="applyBulkAction" wire:confirm="Seçili malzemelere bu işlemi uygulamak istediğinize emin misiniz?" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 sm:w-auto sm:py-2">
                            Uygula
                        </button>
                    </div>
                </div>
            @endif

            @if($viewMode === 'table')
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-left text-sm" style="table-layout: fixed; min-width: 1040px;">
                        <thead class="border-b border-slate-200 bg-white text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                            <tr>
                                <th class="px-4 py-3" style="width:44px"><input type="checkbox" wire:model.live="selectAll" class="rounded border-slate-300 text-slate-900 focus:ring-slate-900"></th>
                                <th class="px-4 py-3" style="width:130px">Stok Kodu</th>
                                <th class="px-4 py-3">Malzeme</th>
                                <th class="px-4 py-3" style="width:130px">Kategori</th>
                                <th class="px-4 py-3 text-center" style="width:90px">Birim</th>
                                <th class="px-4 py-3 text-center" style="width:90px">Fire</th>
                                <th class="px-4 py-3 text-center" style="width:110px">Kumaş Eni</th>
                                <th class="px-4 py-3 text-right" style="width:130px">Fiyat</th>
                                <th class="px-4 py-3 text-center" style="width:90px">Durum</th>
                                <th class="px-4 py-3 text-right" style="width:150px">İşlem</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($materials as $material)
                                <tr class="transition hover:bg-slate-50 {{ !$material->is_active ? 'opacity-55' : '' }}" wire:key="material-modern-{{ $material->id }}">
                                    <td class="px-4 py-3"><input type="checkbox" wire:model.live="selectedMaterials" value="{{ $material->id }}" class="rounded border-slate-300 text-slate-900 focus:ring-slate-900"></td>
                                    <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $material->code }}</td>
                                    <td class="px-4 py-3">
                                        <p class="truncate font-medium text-slate-900" title="{{ $material->name }}">{{ $material->name }}</p>
                                        @if($material->supplier)
                                            <p class="mt-1 truncate text-xs text-slate-500">{{ $material->supplier }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-[6px] border px-2 py-0.5 text-xs font-medium {{ match($material->category) {
                                            'fabric' => 'border-sky-200 bg-sky-50 text-sky-700',
                                            'foam' => 'border-amber-200 bg-amber-50 text-amber-700',
                                            'wood' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                            'hardware' => 'border-slate-200 bg-slate-50 text-slate-700',
                                            'packaging' => 'border-violet-200 bg-violet-50 text-violet-700',
                                            'textile' => 'border-indigo-200 bg-indigo-50 text-indigo-700',
                                            'lining' => 'border-rose-200 bg-rose-50 text-rose-700',
                                            default => 'border-slate-200 bg-slate-50 text-slate-600',
                                        } }}">{{ $material->category_label }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center text-slate-600">{{ $material->unit_label }}</td>
                                    <td class="px-4 py-3 text-center">
                                        @if($inlineEditingId === $material->id && $inlineEditingField === 'default_waste_rate')
                                            <input wire:model="inlineEditingValue" wire:keydown.enter="saveInlineEdit" wire:keydown.escape="cancelInlineEdit" wire:blur="cancelInlineEdit" type="number" step="0.01" class="w-16 rounded-[6px] border border-slate-200 px-2 py-1 text-center text-xs focus:border-slate-900 focus:ring-1 focus:ring-slate-900" autofocus>
                                        @else
                                            <button type="button" class="text-sm font-medium text-slate-700 transition hover:text-slate-950" wire:click="startInlineEdit({{ $material->id }}, 'default_waste_rate', '{{ $material->default_waste_rate }}')" title="Düzenlemek için tıkla">
                                                %{{ number_format($material->default_waste_rate * 100, 0) }}
                                            </button>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center text-slate-600">{{ $material->fabric_width_cm ? $material->fabric_width_cm . ' cm' : '—' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        @if($inlineEditingId === $material->id && $inlineEditingField === 'unit_price')
                                            <input wire:model="inlineEditingValue" wire:keydown.enter="saveInlineEdit" wire:keydown.escape="cancelInlineEdit" wire:blur="cancelInlineEdit" type="number" step="0.01" class="w-24 rounded-[6px] border border-slate-200 px-2 py-1 text-right text-xs focus:border-slate-900 focus:ring-1 focus:ring-slate-900" autofocus>
                                        @else
                                            <button type="button" class="font-medium text-slate-800 transition hover:text-slate-950" wire:click="startInlineEdit({{ $material->id }}, 'unit_price', '{{ $material->unit_price }}')" title="Düzenlemek için tıkla">
                                                {{ $material->unit_price ? number_format($material->unit_price, 2, ',', '.') . ' ₺' : '—' }}
                                            </button>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button type="button" wire:click="toggleActive({{ $material->id }})" class="rounded-[6px] border px-2 py-0.5 text-xs font-medium {{ $material->is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-500' }}">
                                            {{ $material->is_active ? 'Aktif' : 'Pasif' }}
                                        </button>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-1.5">
                                            <button type="button" wire:click="showUsageTree({{ $material->id }})" class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs font-medium text-slate-600 transition hover:bg-slate-50" title="Nerede kullanılıyor?">Kullanım</button>
                                            <button type="button" wire:click="openReplaceModal({{ $material->id }})" class="rounded-[6px] border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 transition hover:bg-amber-100" title="Değiştir / birleştir">Değiştir</button>
                                            <button type="button" wire:click="editMaterial({{ $material->id }})" class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs font-medium text-slate-700 transition hover:bg-slate-50">Düzenle</button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-4 py-12 text-center">
                                        <p class="text-sm font-medium text-slate-700">Malzeme bulunamadı</p>
                                        <button type="button" wire:click="openCreateModal" class="mt-3 text-sm font-medium text-slate-900 underline underline-offset-4">İlk malzemeyi ekle</button>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @elseif($viewMode === 'grid')
                <div class="hidden grid-cols-2 gap-4 p-4 md:grid lg:grid-cols-3 xl:grid-cols-4">
                    @forelse($materials as $material)
                        <article class="flex min-w-0 flex-col overflow-hidden rounded-[8px] border border-slate-200 bg-white shadow-sm transition hover:shadow-md {{ !$material->is_active ? 'opacity-60 grayscale' : '' }}" wire:key="material-grid-modern-{{ $material->id }}">
                            <div class="relative h-32 border-b border-slate-200 bg-slate-50">
                                @if($material->image_path)
                                    <img src="{{ Storage::url($material->image_path) }}" class="h-full w-full object-cover" alt="{{ $material->name }}">
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-slate-300">
                                        <x-lucide.icon name="package" class="h-10 w-10" />
                                    </div>
                                @endif
                                <span class="absolute right-2 top-2 rounded-[6px] border border-slate-200 bg-white px-2 py-0.5 text-xs font-semibold text-slate-900 shadow-sm">
                                    {{ $material->unit_price ? number_format($material->unit_price, 2, ',', '.') . ' ₺' : 'Fiyatsız' }}
                                </span>
                            </div>
                            <div class="flex flex-1 flex-col p-3">
                                <div class="min-w-0">
                                    <h3 class="truncate text-sm font-semibold text-slate-900" title="{{ $material->name }}">{{ $material->name }}</h3>
                                    <p class="mt-1 font-mono text-xs text-slate-500">{{ $material->code }}</p>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-1.5">
                                    <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs text-slate-600">{{ $material->category_label }}</span>
                                    <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs text-slate-600">{{ $material->unit_label }}</span>
                                    <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs text-slate-600">%{{ number_format($material->default_waste_rate * 100, 0) }} fire</span>
                                </div>
                                <div class="mt-auto flex items-center justify-between gap-2 border-t border-slate-100 pt-3">
                                    <div class="flex gap-1.5">
                                        <button type="button" wire:click="showUsageTree({{ $material->id }})" class="rounded-[6px] border border-slate-200 px-2 py-1 text-xs text-slate-600">Kullanım</button>
                                        <button type="button" wire:click="openReplaceModal({{ $material->id }})" class="rounded-[6px] border border-amber-200 bg-amber-50 px-2 py-1 text-xs text-amber-700">Değiştir</button>
                                    </div>
                                    <button type="button" wire:click="editMaterial({{ $material->id }})" class="text-xs font-semibold text-slate-900">Düzenle</button>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="col-span-full rounded-[8px] border border-dashed border-slate-300 bg-slate-50/70 p-10 text-center text-sm text-slate-500">
                            Malzeme bulunamadı.
                        </div>
                    @endforelse
                </div>
            @endif

            <div class="divide-y divide-slate-100 md:hidden">
                @forelse($materials as $material)
                    <article class="p-4 {{ !$material->is_active ? 'opacity-60' : '' }}" wire:key="material-card-modern-{{ $material->id }}">
                        <div class="flex items-start gap-3">
                            <input type="checkbox" wire:model.live="selectedMaterials" value="{{ $material->id }}" class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <h3 class="truncate text-sm font-semibold text-slate-900">{{ $material->name }}</h3>
                                        <p class="mt-1 font-mono text-xs text-slate-500">{{ $material->code }}</p>
                                    </div>
                                    <span class="shrink-0 rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs text-slate-600">{{ $material->category_label }}</span>
                                </div>
                                <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                    <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2"><span class="block text-slate-500">Birim</span><span class="mt-1 block font-semibold text-slate-900">{{ $material->unit_label }}</span></div>
                                    <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2"><span class="block text-slate-500">Fire</span><span class="mt-1 block font-semibold text-slate-900">%{{ number_format($material->default_waste_rate * 100, 0) }}</span></div>
                                    <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2"><span class="block text-slate-500">En</span><span class="mt-1 block font-semibold text-slate-900">{{ $material->fabric_width_cm ? $material->fabric_width_cm . ' cm' : '—' }}</span></div>
                                    <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2"><span class="block text-slate-500">Fiyat</span><span class="mt-1 block font-semibold text-slate-900">{{ $material->unit_price ? number_format($material->unit_price, 2, ',', '.') . ' ₺' : '—' }}</span></div>
                                </div>
                                <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                    <button type="button" wire:click="editMaterial({{ $material->id }})" class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] bg-slate-900 px-3 py-2 text-sm font-medium text-white">Düzenle</button>
                                    <button type="button" wire:click="deleteMaterial({{ $material->id }})" wire:confirm="Sil?" class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700">Sil</button>
                                </div>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="p-10 text-center text-sm text-slate-500">Malzeme bulunamadı.</div>
                @endforelse
            </div>

            <div class="border-t border-slate-200 px-4 py-3">{{ $materials->links() }}</div>
        @else
            <div class="border-b border-slate-200 bg-slate-50/60 p-4 lg:p-5">
                <div class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(260px,1fr)_auto]">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(260px,1fr)_180px_220px]">
                        <div class="relative sm:col-span-2 xl:col-span-1">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <x-lucide.icon name="search" class="h-4 w-4" />
                            </div>
                            <input wire:model.live.debounce.300ms="searchRecipe" type="text" placeholder="Reçete adı, stok kodu veya versiyon ara" class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white py-2 pl-10 pr-3 text-base text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-slate-900 focus:ring-1 focus:ring-slate-900 sm:text-sm">
                        </div>
                        <select wire:model.live="statusFilterRecipe" class="min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 shadow-sm outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 sm:text-sm">
                            <option value="active">Aktif</option>
                            <option value="inactive">Pasif</option>
                            <option value="all">Tümü</option>
                        </select>
                        <div class="flex min-h-[44px] items-center gap-2 rounded-[6px] border border-slate-200 bg-white px-2">
                            <select wire:model.live="recipeSortField" class="min-w-0 flex-1 border-none bg-transparent text-sm font-medium text-slate-700 outline-none focus:ring-0">
                                <option value="updated_at">Güncelleme Tarihi</option>
                                <option value="stock_code">Stok Kodu</option>
                                <option value="name">Reçete Adı</option>
                            </select>
                            <button type="button" wire:click="$set('recipeSortDirection', '{{ $recipeSortDirection === 'asc' ? 'desc' : 'asc' }}')" class="rounded-[6px] px-2 py-1 text-sm font-semibold text-slate-500 hover:bg-slate-50" title="Sıralama yönü">
                                {{ $recipeSortDirection === 'asc' ? 'Artan' : 'Azalan' }}
                            </button>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row xl:justify-end">
                        <button type="button" wire:click="$toggle('showRiskOnlyRecipe')" class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border px-4 py-3 text-sm font-medium transition sm:w-auto sm:py-2 {{ $showRiskOnlyRecipe ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' }}">
                            <x-lucide.icon name="alert-triangle" class="h-4 w-4" />
                            Riskli ({{ $stats['risk_recipes'] ?? 0 }})
                        </button>
                        <button type="button" wire:click="exportRecipes" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2">Dışa Aktar</button>
                        <button type="button" wire:click="openRecipeImportModal" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2">Excel Import</button>
                        <button type="button" wire:click="openFactoryImportModal" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm font-medium text-indigo-700 transition hover:bg-indigo-100 sm:w-auto sm:py-2">Fabrika Import</button>
                        <button type="button" wire:click="createRecipe" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 sm:w-auto sm:py-2">Yeni Reçete</button>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                    <span class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 font-medium text-slate-600">{{ $recipes->total() }} kayıt</span>
                    <span class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 font-medium text-slate-600">{{ $recipeFilterCount }} aktif filtre</span>
                    @if($searchRecipe !== '')
                        <span class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-slate-500">Arama: {{ $searchRecipe }}</span>
                    @endif
                </div>
            </div>

            @if(count($selectedRecipes) > 0)
                <div class="border-b border-slate-200 bg-sky-50 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <span class="text-sm font-semibold text-sky-900">{{ count($selectedRecipes) }} reçete seçili</span>
                        <select wire:model="bulkActionRecipe" class="min-h-[44px] w-full rounded-[6px] border border-sky-200 bg-white px-3 py-2 text-base sm:w-auto sm:text-sm">
                            <option value="">İşlem seç</option>
                            <option value="activate">Aktifleştir</option>
                            <option value="deactivate">Pasife Al</option>
                            <option value="delete">Sil</option>
                        </select>
                        <button type="button" wire:click="applyBulkActionRecipe" wire:confirm="Seçili reçetelere bu işlemi uygulamak istediğinize emin misiniz?" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 sm:w-auto sm:py-2">
                            Uygula
                        </button>
                    </div>
                </div>
            @endif

            <div class="border-b border-slate-200 bg-white px-4 py-3">
                <label class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">
                    <input type="checkbox" wire:model.live="selectAllRecipes" class="rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                    Tümünü Seç
                </label>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($recipes as $recipe)
                    @php
                        $recipeHasRisk = $recipe->lines->contains(fn ($line) => !$line->material || $line->material->unit_price <= 0 || !$line->material->is_active);
                        $recipeIdentityCode = $recipe->identity_code;
                    @endphp
                    <article class="p-4 transition hover:bg-slate-50 {{ $recipe->status !== 'active' ? 'opacity-60' : '' }}" wire:key="recipe-modern-{{ $recipe->id }}">
                        <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                            <div class="flex min-w-0 flex-1 items-start gap-3">
                                <input type="checkbox" wire:model.live="selectedRecipes" value="{{ $recipe->id }}" class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="min-w-0 truncate text-sm font-semibold text-slate-900 sm:text-base">{{ $recipe->name }}</h3>
                                        <span class="rounded-[6px] border px-2 py-0.5 font-mono text-xs font-semibold {{ $recipeIdentityCode !== '' ? 'border-slate-300 bg-white text-slate-800' : 'border-amber-200 bg-amber-50 text-amber-700' }}">
                                            {{ $recipeIdentityCode !== '' ? $recipeIdentityCode : 'Stok kodu yok' }}
                                        </span>
                                        <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-medium text-slate-600">{{ $recipe->version }}</span>
                                        <span class="rounded-[6px] border px-2 py-0.5 text-xs font-medium {{ $recipe->status === 'active' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-500' }}">{{ $recipe->status_label }}</span>
                                        @if($recipeHasRisk)
                                            <span class="inline-flex items-center gap-1 rounded-[6px] border border-rose-200 bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">
                                                <x-lucide.icon name="alert-triangle" class="h-3.5 w-3.5" />
                                                Riskli
                                            </span>
                                        @endif
                                    </div>
                                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                                        @if($recipe->product)
                                            <span class="max-w-full truncate">Ürün: {{ \Illuminate\Support\Str::limit($recipe->product->product_name, 48) }}</span>
                                        @endif
                                        <span>{{ $recipe->lines->count() }} satır</span>
                                        @if($recipe->total_cost > 0)
                                            <span class="font-semibold text-slate-900">{{ number_format($recipe->total_cost, 2, ',', '.') }} ₺</span>
                                        @endif
                                        <span>{{ $recipe->updated_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:justify-end">
                                <button type="button" wire:click="openQuickViewRecipe({{ $recipe->id }})" class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:min-h-0">Hızlı Bak</button>
                                <button type="button" wire:click="editRecipe({{ $recipe->id }})" class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] bg-slate-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800 sm:min-h-0">Düzenle</button>
                                <button type="button" wire:click="exportProductionTicket({{ $recipe->id }})" class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-100 sm:min-h-0">Fiş Al</button>
                                <button type="button" wire:click="duplicateRecipe({{ $recipe->id }})" class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 transition hover:bg-indigo-100 sm:min-h-0">Kopyala</button>
                                <button type="button" wire:click="deleteRecipe({{ $recipe->id }})" wire:confirm="Bu reçeteyi silmek istediğinize emin misiniz?" class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-100 sm:min-h-0">Sil</button>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="p-10 text-center">
                        <p class="text-sm font-medium text-slate-700">Reçete bulunamadı</p>
                        <button type="button" wire:click="createRecipe" class="mt-3 text-sm font-medium text-slate-900 underline underline-offset-4">İlk reçeteyi oluştur</button>
                    </div>
                @endforelse
            </div>

            <div class="border-t border-slate-200 px-4 py-3">{{ $recipes->links() }}</div>
        @endif
    </section>

    {{-- ═══════════════ MALZEME OLUŞTUR/DÜZENLE MODAL ═══════════════ --}}
    @if($showCreateModal)
    <div class="fixed inset-0 bg-gray-900/50 z-50 flex items-center justify-center p-4" wire:click.self="$set('showCreateModal', false)">
        <div class="bg-white rounded-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b">
                <h2 class="text-lg font-bold">{{ $editingMaterialId ? 'Malzeme Düzenle' : 'Yeni Malzeme' }}</h2>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Stok Kodu *</label>
                        <input wire:model="formCode" type="text" class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg" placeholder="HMKMŞ001">
                        @error('formCode') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-medium text-gray-600">Kategori *</label>
                            <span class="text-[10px] text-gray-400">Adına göre otomatik seçilebilir</span>
                        </div>
                        <select wire:model.live="formCategory" class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg">
                            @foreach(\App\Models\Material::CATEGORIES as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @if($formCategory === 'fabric' || $formCategory === 'textile')
                            <p class="text-[10px] text-blue-500 mt-1">📏 Formül: (En x Boy) / Kumaş Eni = Metre</p>
                        @elseif($formCategory === 'foam')
                            <p class="text-[10px] text-amber-500 mt-1">📦 Formül: (En x Boy x Yükseklik) = Metreküp (m³)</p>
                        @elseif($formCategory === 'wood')
                            <p class="text-[10px] text-emerald-500 mt-1">🪚 Formül: (En x Boy) = Metrekare (m²)</p>
                        @endif
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Malzeme Adı * <span class="font-normal text-gray-400">(Sünger, Kumaş, Vida vb. yazarak deneyin)</span></label>
                    <input wire:model.live.debounce.500ms="formName" type="text" class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg" placeholder="HM KUMAŞ DİĞER...">
                    @error('formName') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Birim</label>
                        <select wire:model="formUnit" class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg">
                            @foreach(\App\Models\Material::UNITS as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Varsayılan Fire</label>
                        <div class="flex items-center gap-2">
                            <input wire:model="formWasteRate" type="number" step="0.01" min="0" max="1"
                                   class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg" placeholder="0.10">
                            <span class="text-xs text-gray-400 whitespace-nowrap">= %{{ number_format(($formWasteRate ?? 0) * 100, 0) }}</span>
                        </div>
                    </div>
                </div>

                {{-- Kumaş-Spesifik Alanlar --}}
                @if($formCategory === 'fabric' || $formCategory === 'textile')
                <div class="p-4 bg-blue-50 rounded-lg space-y-3">
                    <h4 class="text-xs font-semibold text-blue-700 uppercase">Kumaş Ayarları</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-blue-600 mb-1">Kumaş Eni (cm)</label>
                            <input wire:model="formFabricWidth" type="number" step="0.5"
                                   class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg" placeholder="140">
                        </div>
                        <div>
                            <label class="block text-xs text-blue-600 mb-1">Metre Hesap Yöntemi</label>
                            <select wire:model="formFabricMethod" class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg">
                                @foreach(\App\Models\Material::FABRIC_METHODS as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Sünger-Spesifik Alanlar --}}
                @if($formCategory === 'foam')
                <div class="p-4 bg-amber-50 rounded-lg space-y-3">
                    <h4 class="text-xs font-semibold text-amber-700 uppercase">Sünger Ayarları</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-amber-600 mb-1">Yoğunluk (kg/m³)</label>
                            <input wire:model="formDensity" type="number" step="0.1"
                                   class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg" placeholder="15">
                        </div>
                        <div>
                            <label class="block text-xs text-amber-600 mb-1">Kalınlık (cm)</label>
                            <input wire:model="formThickness" type="number" step="0.5"
                                   class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg" placeholder="10">
                        </div>
                    </div>
                </div>
                @endif

                {{-- Fiyat --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Birim Fiyat</label>
                        <input wire:model="formUnitPrice" type="number" step="0.01"
                               class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg" placeholder="0.00">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Para Birimi</label>
                        <select wire:model="formCurrency" class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg">
                            <option value="TRY">₺ TRY</option>
                            <option value="USD">$ USD</option>
                            <option value="EUR">€ EUR</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Tedarikçi</label>
                        <input wire:model="formSupplier" type="text" class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg" placeholder="Örn: Yıldız Entegre">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Etiketler (Tags)</label>
                        <input wire:model="formTags" type="text" class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg" placeholder="Örn: Kışlık, Premium, Lüks (Virgülle ayırın)">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Malzeme Görseli</label>
                    <div class="flex items-center gap-4">
                        @if ($formImage)
                            <img src="{{ $formImage->temporaryUrl() }}" class="w-16 h-16 object-cover rounded-lg border border-gray-200">
                        @elseif ($formImagePreview)
                            <img src="{{ Storage::url($formImagePreview) }}" class="w-16 h-16 object-cover rounded-lg border border-gray-200">
                        @else
                            <div class="w-16 h-16 bg-gray-50 flex items-center justify-center text-2xl border border-dashed border-gray-300 rounded-lg text-gray-400">📷</div>
                        @endif
                        <div class="flex-1">
                            <input wire:model="formImage" type="file" accept="image/*" class="w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border border-gray-200 rounded-lg">
                            <div wire:loading wire:target="formImage" class="text-xs text-indigo-500 mt-1">Yükleniyor...</div>
                            @error('formImage') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Notlar</label>
                    <textarea wire:model="formNotes" rows="2" class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg"></textarea>
                </div>
            </div>
            <div class="p-6 border-t flex flex-col sm:flex-row sm:justify-end gap-3">
                <button wire:click="$set('showCreateModal', false)" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm border rounded-lg hover:bg-gray-50">İptal</button>
                <button wire:click="saveMaterial" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm bg-gray-900 text-white rounded-lg hover:bg-gray-800">
                    {{ $editingMaterialId ? 'Güncelle' : 'Kaydet' }}
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ═══════════════ EXCEL IMPORT MODAL ═══════════════ --}}
    @if($showImportModal)
    <div class="fixed inset-0 bg-gray-900/50 z-50 flex items-center justify-center p-4" wire:click.self="$set('showImportModal', false)">
        <div class="bg-white rounded-2xl w-full max-w-md">
            <div class="p-6 border-b">
                <h2 class="text-lg font-bold">📥 Excel Import</h2>
                <p class="text-xs text-gray-500 mt-1">Stok kartları Excel dosyanızı yükleyin. Stok kodu eşleşirse Fiyat kolonu mevcut malzeme fiyatını günceller.</p>
            </div>
            <div class="p-6">
                <input wire:model="importFile" type="file" accept=".xlsx,.xls"
                       class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg">
                @error('importFile') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
            </div>
            <div class="p-6 border-t flex flex-col sm:flex-row sm:justify-end gap-3">
                <button wire:click="$set('showImportModal', false)" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm border rounded-lg hover:bg-gray-50">İptal</button>
                <button wire:click="importExcel" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm bg-gray-900 text-white rounded-lg hover:bg-gray-800">
                    Import Et
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ═══════════════ REÇETE IMPORT MODAL ═══════════════ --}}
    @if($showRecipeImportModal)
    <div class="fixed inset-0 bg-gray-900/50 z-50 flex items-center justify-center p-4" wire:click.self="$set('showRecipeImportModal', false)">
        <div class="bg-white rounded-2xl w-full max-w-md">
            <div class="p-6 border-b">
                <h2 class="text-lg font-bold">📋 Excel'den Reçete Import</h2>
                <p class="text-xs text-gray-500 mt-1">"ürün ağacı" sayfası olan bir Excel dosyası yükleyin. Eksik malzemeler otomatik oluşturulur.</p>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Reçete Adı *</label>
                    <input wire:model="recipeImportName" type="text" class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg" placeholder="Berjer Alaves Reçetesi">
                    @error('recipeImportName') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Excel Dosyası *</label>
                    <input wire:model="recipeImportFile" type="file" accept=".xlsx,.xls"
                           class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg">
                    @error('recipeImportFile') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                </div>
                <div class="bg-blue-50 rounded-lg p-3 text-xs text-blue-700">
                    <strong>Beklenen Excel yapısı:</strong>
                    <ul class="mt-1 space-y-0.5 list-disc list-inside">
                        <li>"ürün ağacı" sayfası (2. sayfa)</li>
                        <li>A = Operasyon (KUMAŞ, AHŞAP, SÜNGER...)</li>
                        <li>B = Stok Kodu, C = Malzeme Adı</li>
                        <li>F = En, G = Boy, H = Yükseklik, I = Adet</li>
                        <li>K = Hesaplanan miktar, L = Fire oranı</li>
                    </ul>
                </div>
            </div>
            <div class="p-6 border-t flex flex-col sm:flex-row sm:justify-end gap-3">
                <button wire:click="$set('showRecipeImportModal', false)" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm border rounded-lg hover:bg-gray-50">İptal</button>
                <button wire:click="importRecipeExcel" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm bg-gray-900 text-white rounded-lg hover:bg-gray-800">
                    Import Et
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ═══════════════ FABRİKA IMPORT MODAL ═══════════════ --}}
    @if($showFactoryImportModal)
    <div class="fixed inset-0 bg-gray-900/50 z-50 flex items-center justify-center p-4" wire:click.self="$set('showFactoryImportModal', false)">
        <div class="bg-white rounded-2xl w-full max-w-lg">
            <div class="p-6 border-b">
                <h2 class="text-lg font-bold flex items-center gap-2">🏭 Fabrika Verileri Import</h2>
                <p class="text-xs text-gray-500 mt-1">Ham madde listesi ve reçeteleri toplu olarak içeri aktarın.</p>
            </div>
            <div class="p-6 space-y-4">
                @if($factoryImportStep === 'ready')
                    <div class="bg-indigo-50 rounded-xl p-4 space-y-3">
                        <h3 class="text-sm font-semibold text-indigo-900">İki aşamalı import yapılacak:</h3>
                        <div class="space-y-2">
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold">1</div>
                                <div>
                                    <p class="text-sm font-medium text-indigo-900">Ham Madde Kartları</p>
                                    <p class="text-xs text-indigo-600">ham_madde_list.json → 506 malzeme kartı (fiyat bilgisi dahil)</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold">2</div>
                                <div>
                                    <p class="text-sm font-medium text-indigo-900">Reçeteler</p>
                                    <p class="text-xs text-indigo-600">recete_list.json → 4571 reçete satırı (mamul bazlı gruplandırılarak)</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-amber-50 rounded-lg p-3 text-xs text-amber-800">
                        <strong>⚠️ Not:</strong> Mevcut malzemeler atlanır, sadece yeni veriler eklenir. Fiyatlar güncellenebilir.
                    </div>
                @elseif($factoryImportStep === 'importing_materials')
                    <div class="text-center py-8">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100 mb-4">
                            <svg class="w-8 h-8 text-blue-600 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-gray-900">Ham maddeler aktarılıyor...</p>
                        <p class="text-xs text-gray-500 mt-1">Aşama 1/2</p>
                    </div>
                @elseif($factoryImportStep === 'importing_recipes')
                    <div class="text-center py-8">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-indigo-100 mb-4">
                            <svg class="w-8 h-8 text-indigo-600 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-gray-900">Reçeteler aktarılıyor...</p>
                        <p class="text-xs text-gray-500 mt-1">Aşama 2/2</p>
                        @if(!empty($factoryImportResult['materials']))
                        <div class="mt-3 text-xs text-emerald-700 bg-emerald-50 rounded-lg p-2 inline-block">
                            ✅ {{ $factoryImportResult['materials']['imported'] }} malzeme eklendi
                        </div>
                        @endif
                    </div>
                @elseif($factoryImportStep === 'done')
                    <div class="space-y-3">
                        <div class="text-center mb-4">
                            <div class="text-4xl mb-2">✅</div>
                            <h3 class="text-lg font-bold text-gray-900">Import Tamamlandı!</h3>
                        </div>
                        @if(!empty($factoryImportResult['materials']))
                        <div class="bg-emerald-50 rounded-xl p-4">
                            <h4 class="text-sm font-semibold text-emerald-900 mb-2">📦 Ham Maddeler</h4>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="flex justify-between"><span class="text-emerald-700">Yeni eklenen</span><span class="font-bold text-emerald-900">{{ $factoryImportResult['materials']['imported'] }}</span></div>
                                <div class="flex justify-between"><span class="text-emerald-700">Fiyat güncellenen</span><span class="font-bold text-emerald-900">{{ $factoryImportResult['materials']['price_updated'] }}</span></div>
                                <div class="flex justify-between"><span class="text-gray-500">Atlanan (mevcut)</span><span class="font-medium">{{ $factoryImportResult['materials']['skipped'] }}</span></div>
                                @if($factoryImportResult['materials']['errors'] > 0)
                                <div class="flex justify-between"><span class="text-red-600">Hata</span><span class="font-bold text-red-700">{{ $factoryImportResult['materials']['errors'] }}</span></div>
                                @endif
                            </div>
                        </div>
                        @endif
                        @if(!empty($factoryImportResult['recipes']))
                        <div class="bg-indigo-50 rounded-xl p-4">
                            <h4 class="text-sm font-semibold text-indigo-900 mb-2">📋 Reçeteler</h4>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="flex justify-between"><span class="text-indigo-700">Oluşturulan reçete</span><span class="font-bold text-indigo-900">{{ $factoryImportResult['recipes']['recipes_created'] }}</span></div>
                                <div class="flex justify-between"><span class="text-indigo-700">Toplam satır</span><span class="font-bold text-indigo-900">{{ $factoryImportResult['recipes']['lines_created'] }}</span></div>
                                <div class="flex justify-between"><span class="text-indigo-700">Otomatik malzeme</span><span class="font-bold text-indigo-900">{{ $factoryImportResult['recipes']['materials_created'] }}</span></div>
                                <div class="flex justify-between"><span class="text-gray-500">Atlanan reçete</span><span class="font-medium">{{ $factoryImportResult['recipes']['recipes_skipped'] }}</span></div>
                                @if($factoryImportResult['recipes']['errors'] > 0)
                                <div class="flex justify-between"><span class="text-red-600">Hata</span><span class="font-bold text-red-700">{{ $factoryImportResult['recipes']['errors'] }}</span></div>
                                @endif
                            </div>
                        </div>
                        @endif
                    </div>
                @endif
            </div>
            <div class="p-6 border-t flex flex-col sm:flex-row sm:justify-end gap-3">
                @if($factoryImportStep === 'ready')
                    <button wire:click="$set('showFactoryImportModal', false)" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm border rounded-lg hover:bg-gray-50">İptal</button>
                    <button wire:click="startFactoryImport"
                            wire:confirm="Fabrika verilerini import etmek istediğinize emin misiniz? Bu işlem birkaç dakika sürebilir."
                            class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                        🚀 Import Başlat
                    </button>
                @elseif($factoryImportStep === 'done')
                    <button wire:click="$set('showFactoryImportModal', false)" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm bg-gray-900 text-white rounded-lg hover:bg-gray-800">Kapat</button>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- ═══════════════ HIZLI GÖRÜNÜM MODAL ═══════════════ --}}
    @if($showQuickViewModal && $this->quickViewRecipe)
    <div class="fixed inset-0 bg-gray-900/50 z-50 flex items-center justify-center p-4" wire:click.self="$set('showQuickViewModal', false)">
        <div class="bg-white rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
            <div class="p-6 border-b flex justify-between items-center bg-gray-50">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">{{ $this->quickViewRecipe->name }}</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Stok Kodu: <span class="font-mono font-semibold text-gray-800">{{ $this->quickViewRecipe->identity_code ?: '—' }}</span>
                        | Versiyon: {{ $this->quickViewRecipe->version }}
                        | Maliyet: <span class="font-bold text-gray-800">{{ number_format($this->quickViewRecipe->total_cost, 2, ',', '.') }} ₺</span>
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <button wire:click="toggleRecipeStatus({{ $this->quickViewRecipe->id }})"
                            class="px-3 py-1.5 text-sm font-medium rounded-lg border {{ $this->quickViewRecipe->status === 'active' ? 'bg-red-50 text-red-700 border-red-200 hover:bg-red-100' : 'bg-green-50 text-green-700 border-green-200 hover:bg-green-100' }}">
                        {{ $this->quickViewRecipe->status === 'active' ? 'Pasife Al' : 'Aktifleştir' }}
                    </button>
                    <button wire:click="editRecipe({{ $this->quickViewRecipe->id }})" class="px-3 py-1.5 text-sm font-medium border border-gray-300 bg-white text-gray-700 rounded-lg hover:bg-gray-50">
                        Tam Düzenle
                    </button>
                    <button wire:click="$set('showQuickViewModal', false)" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
            <div class="p-0 overflow-y-auto flex-1">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-500 bg-gray-50 sticky top-0">
                        <tr>
                            <th class="px-4 py-3 font-medium">Operasyon</th>
                            <th class="px-4 py-3 font-medium">Malzeme</th>
                            <th class="px-4 py-3 font-medium text-right">Miktar</th>
                            <th class="px-4 py-3 font-medium text-right">B.Fiyat</th>
                            <th class="px-4 py-3 font-medium text-right">Tutar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($this->quickViewRecipe->lines->sortBy('sort_order') as $line)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs">{{ $line->operation_label ?? $line->operation }}</span>
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-900">
                                {{ $line->material ? $line->material->name : 'Bilinmeyen Malzeme' }}
                                <div class="text-xs text-gray-500">{{ $line->material ? $line->material->code : '-' }}</div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="font-medium">{{ number_format($line->calculated_qty, 4, ',', '.') }}</span>
                                <span class="text-xs text-gray-500 ml-1">{{ $line->calculated_unit }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                {{ $line->material ? number_format($line->material->unit_price, 2, ',', '.') . ' ₺' : '-' }}
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-gray-900">
                                {{ number_format($line->line_cost, 2, ',', '.') }} ₺
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- ═══════════════ MODALLAR ═══════════════ --}}

    {{-- Nerede Kullanılıyor (Usage Tree) Modal --}}
    @if($showUsageTreeModal)
    <div class="fixed inset-0 bg-gray-900/50 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-white rounded-xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden shadow-2xl">
            <div class="p-4 sm:p-6 border-b flex justify-between items-center bg-blue-50">
                <h2 class="text-lg font-bold text-blue-900">🔍 Nerede Kullanılıyor?</h2>
                <button wire:click="$set('showUsageTreeModal', false)" class="text-blue-400 hover:text-blue-600">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <div class="p-4 sm:p-6 overflow-y-auto">
                @if(empty($usageTreeData))
                    <div class="text-center py-8 text-gray-500">
                        <div class="text-3xl mb-2">🤷</div>
                        <p>Bu malzeme henüz hiçbir reçetede kullanılmamış.</p>
                    </div>
                @else
                    <p class="text-sm text-gray-600 mb-4">Bu malzeme aşağıdaki <strong>{{ count($usageTreeData) }}</strong> reçetede aktif olarak kullanılmaktadır:</p>
                    <div class="space-y-3">
                        @foreach($usageTreeData as $usage)
                        <div class="p-3 border border-gray-100 bg-gray-50 rounded-lg flex justify-between items-center">
                            <div>
                                <div class="font-bold text-gray-900">{{ $usage['recipe_name'] }}</div>
                                <div class="text-xs text-gray-500 mt-0.5">Ürün: {{ $usage['product'] }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-mono text-indigo-600">{{ $usage['qty'] }}</div>
                                <div class="text-xs text-gray-400">{{ $usage['operation'] }}</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- Malzeme Birleştir / Değiştir Modal --}}
    @if($showReplaceModal)
    <div class="fixed inset-0 bg-gray-900/50 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-white rounded-xl w-full max-w-xl max-h-[90vh] flex flex-col overflow-hidden shadow-2xl">
            <div class="p-4 sm:p-6 border-b flex justify-between items-center bg-amber-50">
                <h2 class="text-lg font-bold text-amber-900">🔀 Malzeme Değiştir / Birleştir</h2>
                <button wire:click="$set('showReplaceModal', false)" class="text-amber-400 hover:text-amber-600">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <div class="p-4 sm:p-6 overflow-y-auto">
                <div class="mb-4">
                    <div class="flex gap-4 border-b border-gray-200">
                        <button wire:click="$set('replaceAction', 'replace')" class="pb-2 text-sm font-medium border-b-2 {{ $replaceAction === 'replace' ? 'border-amber-500 text-amber-700' : 'border-transparent text-gray-500' }}">Sadece Değiştir</button>
                        <button wire:click="$set('replaceAction', 'merge')" class="pb-2 text-sm font-medium border-b-2 {{ $replaceAction === 'merge' ? 'border-red-500 text-red-700' : 'border-transparent text-gray-500' }}">Birleştir & Eski Sil</button>
                    </div>
                </div>

                <div class="bg-gray-50 border rounded-lg p-3 mb-4">
                    <div class="text-xs text-gray-500 mb-1">Eski Malzeme:</div>
                    @php $oldM = \App\Models\Material::find($replaceOldMaterialId); @endphp
                    <div class="font-medium text-gray-900">{{ $oldM->code ?? '' }} - {{ $oldM->name ?? '' }}</div>
                </div>

                <div class="mb-2 text-center text-gray-400">⬇️ YERİNE GEÇECEK ⬇️</div>

                <div class="relative">
                    <input type="text" wire:model.live.debounce.300ms="replaceSearchTerm" placeholder="Yeni malzeme ara (Stok kodu veya ad)..." class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-amber-500 focus:border-amber-500 text-sm">

                    @if(!empty($replaceSearchResults))
                    <div class="absolute z-10 mt-1 w-full bg-white shadow-lg border border-gray-200 rounded-md max-h-60 overflow-y-auto">
                        <ul class="divide-y divide-gray-100">
                            @foreach($replaceSearchResults as $res)
                            <li>
                                <button wire:click="executeReplace({{ $res['id'] }})" wire:confirm="Emin misiniz? Tüm reçeteler etkilenecektir." class="w-full text-left px-4 py-3 hover:bg-amber-50 transition-colors flex justify-between items-center">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">{{ $res['name'] }}</div>
                                        <div class="text-xs text-gray-500">{{ $res['code'] }} • {{ $res['category'] }}</div>
                                    </div>
                                    <div class="text-sm text-gray-900 font-medium">
                                        {{ number_format($res['unit_price'] ?? 0, 2) }} ₺
                                    </div>
                                </button>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                </div>

                @if($replaceAction === 'merge')
                    <div class="mt-4 bg-red-50 text-red-700 p-3 rounded-lg text-xs flex items-start gap-2">
                        <span class="text-lg">⚠️</span>
                        <p><strong>DİKKAT:</strong> Bu işlemi yaparsanız, eski malzeme sistemden tamamen silinir ve kullanıldığı her yerde yeni seçtiğiniz malzeme geçerli olur. Geri alınamaz.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- Akıllı Fiyat Senkronizasyonu Modal --}}
    @if($showPriceSyncModal)
    <div class="fixed inset-0 bg-gray-900/50 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-white rounded-xl w-full max-w-lg shadow-2xl">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-900">💰 Akıllı Fiyat Yükleme</h3>
                <button wire:click="$set('showPriceSyncModal', false)" class="text-gray-400 hover:text-gray-500">✕</button>
            </div>
            <div class="p-6">
                <div class="mb-6 bg-amber-50 p-4 rounded-lg border border-amber-100 text-sm text-amber-800">
                    Sistemden indirdiğiniz <strong>"Fiyat Güncelleme Şablonu"</strong> excel dosyasındaki "YENİ FİYAT" sütununu ya da tedarikçi/fabrika listesindeki "MF Fiyatı" sütununu kullanabilirsiniz. Sadece fiyatı değişen malzemeler güncellenecektir.
                </div>

                <input type="file" wire:model="priceSyncFile" accept=".xlsx,.xls" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border border-gray-300 rounded-lg mb-4">
                @error('priceSyncFile') <span class="text-sm text-red-600">{{ $message }}</span> @enderror

                <button wire:click="importPriceSync" wire:loading.attr="disabled" wire:target="priceSyncFile" class="w-full py-2.5 bg-gray-900 text-white rounded-lg font-medium hover:bg-gray-800 transition-colors">
                    Fiyatları Güncelle
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ═══════════════ TEDARİKÇİ EXCEL GÜNCELLEME MODAL ═══════════════ --}}
    @if($showVendorSyncModal)
    <div class="fixed inset-0 bg-gray-900/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden shadow-xl">
            <div class="p-6 border-b flex justify-between items-center bg-emerald-50">
                <div>
                    <h2 class="text-xl font-bold text-emerald-900">🔄 Tedarikçi Fiyat Güncelle (Vendor Sync)</h2>
                    <p class="text-sm text-emerald-700 mt-1">Excel listesinden isim/kod eşleşmesiyle fiyatları topluca güncelleyin.</p>
                </div>
                <button wire:click="$set('showVendorSyncModal', false)" class="text-emerald-500 hover:text-emerald-700">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div class="p-6 overflow-y-auto flex-1">
                @if(!$vendorSyncReady)
                    <div class="space-y-4">
                        <div class="bg-blue-50 text-blue-800 p-4 rounded-lg text-sm">
                            <p class="font-bold mb-2">Excel Şablonu Kuralları:</p>
                            <ul class="list-disc pl-5 space-y-1">
                                <li>İlk satır başlık satırı olmalıdır.</li>
                                <li><strong>"Kod"</strong> veya <strong>"Ad"</strong> kelimesi içeren bir sütun bulunmalıdır.</li>
                                <li><strong>"Fiyat"</strong> kelimesi içeren bir sütun bulunmalıdır.</li>
                                <li>Sistem ad veya koda göre otomatik eşleştirme yapar, sadece fiyatı değişenleri listeler.</li>
                            </ul>
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Excel Dosyası Yükle (.xlsx, .xls)</label>
                            <input type="file" wire:model="vendorSyncFile" accept=".xlsx,.xls,.csv" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 border border-gray-300 rounded-lg">
                            <div wire:loading wire:target="vendorSyncFile" class="text-sm text-indigo-600 mt-2">
                                Dosya okunuyor, eşleştirmeler aranıyor... ⏳
                            </div>
                            @error('vendorSyncFile') <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                @else
                    <div class="space-y-4">
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="font-bold text-gray-900">Eşleşen ve Fiyatı Değişecek Malzemeler</h3>
                            <span class="bg-emerald-100 text-emerald-800 text-xs font-bold px-2 py-1 rounded">{{ count($vendorSyncPreview) }} Kayıt</span>
                        </div>

                        @if(empty($vendorSyncPreview))
                            <div class="text-center py-8 text-gray-500">
                                <div class="text-4xl mb-2">👍</div>
                                <p>Sistemdeki tüm fiyatlar Excel ile aynı, güncellenecek malzeme bulunamadı.</p>
                            </div>
                        @else
                            <div class="border rounded-lg overflow-hidden">
                                <table class="w-full text-sm text-left">
                                    <thead class="text-xs text-gray-500 bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 font-medium">Stok Kodu / Adı</th>
                                            <th class="px-4 py-2 font-medium text-right">Eski Fiyat</th>
                                            <th class="px-4 py-2 font-medium text-right">Yeni Fiyat</th>
                                            <th class="px-4 py-2 font-medium text-center">Fark</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach($vendorSyncPreview as $item)
                                            <tr>
                                                <td class="px-4 py-2">
                                                    <div class="font-medium text-gray-900">{{ $item['name'] }}</div>
                                                    <div class="text-xs text-gray-500">{{ $item['code'] }}</div>
                                                </td>
                                                <td class="px-4 py-2 text-right text-gray-500">
                                                    {{ number_format($item['old_price'], 2, ',', '.') }} ₺
                                                </td>
                                                <td class="px-4 py-2 text-right font-bold text-emerald-600">
                                                    {{ number_format($item['new_price'], 2, ',', '.') }} ₺
                                                </td>
                                                <td class="px-4 py-2 text-center">
                                                    @if($item['new_price'] > $item['old_price'])
                                                        <span class="text-red-500 font-bold text-xs" title="%{{ number_format((($item['new_price'] - $item['old_price']) / ($item['old_price'] ?: 1)) * 100, 1) }}">📈</span>
                                                    @elseif($item['new_price'] < $item['old_price'])
                                                        <span class="text-green-500 font-bold text-xs" title="%{{ number_format((($item['old_price'] - $item['new_price']) / ($item['old_price'] ?: 1)) * 100, 1) }}">📉</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <div class="p-6 border-t flex justify-end gap-3 bg-gray-50">
                <button wire:click="$set('showVendorSyncModal', false)" class="px-4 py-2 text-sm border bg-white rounded-lg hover:bg-gray-50">İptal</button>
                @if($vendorSyncReady && !empty($vendorSyncPreview))
                    <button wire:click="processVendorSync" wire:loading.attr="disabled"
                            class="px-4 py-2 text-sm bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50">
                        <span wire:loading.remove wire:target="processVendorSync">🚀 Fiyatları Güncelle</span>
                        <span wire:loading wire:target="processVendorSync">⏳ Güncelleniyor...</span>
                    </button>
                @endif
            </div>
        </div>
    </div>
    @endif

</div>
