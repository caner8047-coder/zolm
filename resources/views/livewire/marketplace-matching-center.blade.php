@php
    $formatCount = fn ($value) => number_format((float) $value, 0, ',', '.');
    $guidanceItems = collect($diagnosticsGuidance['items'] ?? []);
    $primaryGuidance = $guidanceItems->first();
    $secondaryGuidance = $guidanceItems->slice(1)->take(4)->values();
    $pageIssueIds = $issues->pluck('id')->map(fn ($id) => (string) $id)->all();
    $allCurrentIssuesSelected = $pageIssueIds !== [] && count(array_intersect($selectedIssueIds, $pageIssueIds)) === count($pageIssueIds);
    $sortIcon = function (string $columnKey) use ($sortableColumns, $sortField, $sortDirection) {
        $dbColumn = $sortableColumns[$columnKey] ?? null;
        if (!$dbColumn) {
            return '';
        }

        return $sortField === $dbColumn
            ? ($sortDirection === 'asc' ? '▲' : '▼')
            : '⇅';
    };
@endphp

@once
    <style>
        .mp-matching-page .rounded-2xl { border-radius: 10px; }
        .mp-matching-page .rounded-xl { border-radius: 8px; }
        .mp-matching-page .rounded-lg { border-radius: 6px; }
        .mp-matching-page .rounded-md { border-radius: 6px; }
        .mp-matching-page .rounded-\[24px\] { border-radius: 10px; }
        .mp-matching-page > .space-y-6 > section,
        .mp-matching-page > section {
            border-color: rgb(226 232 240 / 0.92);
            box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
        }
        .mp-matching-surface {
            background:
                radial-gradient(circle at top left, rgba(253, 230, 138, 0.16), transparent 24%),
                linear-gradient(180deg, rgba(255, 251, 235, 0.28), rgba(255, 255, 255, 1));
        }
        .mp-matching-page .matching-kicker {
            letter-spacing: 0.22em;
        }
        .mp-matching-page .matching-soft-card {
            background: linear-gradient(180deg, rgba(255,255,255,0.96), rgba(248,250,252,0.94));
        }
        .mp-matching-page .matching-ledger-shell {
            background: linear-gradient(180deg, rgba(248, 250, 252, 0.92), rgba(255, 255, 255, 1));
        }
        .mp-matching-page .matching-ledger-top {
            background: linear-gradient(180deg, rgba(248, 250, 252, 0.96), rgba(255, 255, 255, 1));
        }
    </style>
@endonce

<div class="mp-matching-page w-full space-y-5" x-data="{
    expanded: [],
    advancedFilters: false,
    bulkActionOpen: false,
    bulkAction: '',
    bulkActionLabels: {
        create_products: 'Seçilileri ürün listesine ekle',
        ignore: 'Seçilileri inceleme dışı bırak',
        reopen: 'Seçilileri yeniden aç',
    },
    selectedIssueIds: @js($selectedIssueIds),
    pageIssueIds: @js($pageIssueIds),
    submitPageSelection(checked, form) {
        const previous = [...this.selectedIssueIds];
        this.selectedIssueIds = checked
            ? [...new Set([...this.selectedIssueIds, ...this.pageIssueIds])]
            : this.selectedIssueIds.filter(id => !this.pageIssueIds.includes(String(id)));

        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).catch(() => {
            this.selectedIssueIds = previous;
        });
    },
    toggleIssueSelection(id, checked, form) {
        const previous = [...this.selectedIssueIds];
        this.selectedIssueIds = checked
            ? [...new Set([...this.selectedIssueIds, String(id)])]
            : this.selectedIssueIds.filter(selectedId => String(selectedId) !== String(id));

        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).catch(() => {
            this.selectedIssueIds = previous;
        });
    },
}">
    @if(session()->has('success') || session()->has('warning'))
        @php
            $toastTone = session()->has('success') ? 'success' : 'warning';
            $toastMessage = session('success') ?: session('warning');
        @endphp
        <div x-data="{ show: true }"
             x-init="window.setTimeout(() => show = false, 4200)"
             x-show="show"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-2 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 translate-y-2 scale-95"
             class="fixed right-4 top-24 z-50 w-[calc(100vw-2rem)] max-w-sm rounded-[10px] border bg-white p-4 shadow-lg sm:right-6 {{ $toastTone === 'success' ? 'border-emerald-200' : 'border-amber-200' }}"
             role="status">
            <div class="flex items-start gap-3">
                <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-[8px] {{ $toastTone === 'success' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600' }}">
                    @if($toastTone === 'success')
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    @else
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        </svg>
                    @endif
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-900">{{ $toastTone === 'success' ? 'İşlem tamam' : 'Kontrol gerekli' }}</p>
                    <p class="mt-1 text-sm leading-5 text-slate-600">{{ $toastMessage }}</p>
                </div>
                <button type="button"
                        @click="show = false"
                        class="rounded-[6px] p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                        aria-label="Bildirimi kapat">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="mt-3 h-1 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full {{ $toastTone === 'success' ? 'bg-emerald-500' : 'bg-amber-500' }}"
                     x-data
                     x-init="$el.animate([{ width: '100%' }, { width: '0%' }], { duration: 4200, easing: 'linear', fill: 'forwards' })"></div>
            </div>
        </div>
    @endif

    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Ürün eşleştirme</span>
                    <span class="rounded-[6px] bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">{{ $formatCount($stats['pending_issues']) }} bekleyen kayıt</span>
                </div>
                <h1 class="mt-3 text-xl font-bold tracking-tight text-slate-900 lg:text-2xl">Pazaryeri ürünlerini ana ürün listenize ekleyin</h1>
                <p class="mt-2 text-sm text-slate-500">Satırdan <span class="font-medium text-slate-700">Ürün listesine ekle</span> seçin; mevcut ürün varsa satırı açıp eşleştirin.</p>
            </div>
            <div class="grid grid-cols-2 gap-3 lg:w-[250px]">
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Eklenecek</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900">{{ $formatCount($stats['pending_issues']) }}</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Eşleşmiş</p>
                    <p class="mt-1 text-lg font-semibold text-emerald-600">{{ $formatCount($stats['resolved_issues']) }}</p>
                </div>
            </div>
        </div>
    </section>

    <div class="space-y-6">
        <section class="rounded-2xl border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
            <div>
                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3 lg:gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-bold text-slate-900">Sorun Listesi</h2>
                            <x-zolm.help-tip position="left" title="Sorun Listesi" summary="Eşleşme problemi yaşayan kanal kayıtlarını öncelik sırasıyla gösterir." source="Pazaryeri eşleşme sorun kayıtları ve aday ürün havuzu." refresh="Yeni sorun üretildiğinde, toplu aksiyon çalıştığında veya sorun çözüldüğünde." impact="Ürün ve kârlılık akışını bozan kayıtları operasyon sırasına sokar." />
                        </div>
                        <p class="mt-2 text-sm text-slate-500">Önce <span class="font-medium text-slate-700">Ürün listesine ekle</span> ile ana ürün oluşturun; mevcut ürün varsa satırı açıp eşleştirin.</p>
                    </div>

                    <div class="flex flex-col items-start gap-2 lg:items-end">
                        <p class="text-sm text-slate-500 lg:text-right">
                            {{ count($activeFilters) > 0 ? implode(' · ', $activeFilters) : 'Henüz aktif filtre yok.' }}
                            · {{ $formatCount($issues->total()) }} kayıt
                        </p>

                        <div class="flex flex-wrap items-center gap-3">
                            <div x-data="{ open: false }" class="relative hidden md:block">
                                <button @click="open = !open"
                                        type="button"
                                        class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                    Kolonlar
                                </button>

                                <div x-show="open"
                                     @click.outside="open = false"
                                     x-transition
                                     class="absolute right-0 top-full z-30 mt-2 w-60 rounded-2xl border border-slate-200 bg-white p-3 shadow-xl">
                                    <p class="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">Görünür kolonlar</p>
                                    <div class="mt-3 space-y-1.5">
                                        @foreach($columnDefs as $colKey => $colLabel)
                                            <label class="flex items-center gap-2 rounded-lg px-2 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                                <input type="checkbox"
                                                       wire:click="toggleColumn('{{ $colKey }}')"
                                                       {{ in_array($colKey, $visibleColumns, true) ? 'checked' : '' }}
                                                       class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-indigo-200">
                                                <span>{{ $colLabel }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <details class="mt-4 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                    <summary class="cursor-pointer text-sm font-medium text-slate-700">Tanı ve gelişmiş operasyon araçları</summary>
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Bugün önce bunlara bak</p>
                            <p class="mt-2 text-sm text-slate-500">
                                En kritik eşleştirme sorunu önce görünür. Diğer kümeler ihtiyaç olduğunda açılır.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-medium text-rose-700">
                                Kritik {{ $formatCount($diagnosticsGuidance['totals']['critical']) }}
                            </span>
                            <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700">
                                Uyarı {{ $formatCount($diagnosticsGuidance['totals']['warning']) }}
                            </span>
                            <button type="button"
                                    wire:click="focusTopGuidance"
                                    class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                {{ $this->guidanceFocusLabel() }}
                            </button>
                            <button type="button"
                                    wire:click="syncTopGuidance"
                                    class="inline-flex min-h-[44px] items-center justify-center rounded-lg bg-slate-900 px-4 py-3 sm:py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                                {{ $this->guidanceSyncLabel() }}
                            </button>
                            <button wire:click="exportDiagnosticsGuidanceCsv"
                                    class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                Karar desteği CSV
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 space-y-3">
                        @if($primaryGuidance)
                            <a href="{{ $this->guidanceRoute($primaryGuidance) }}"
                               class="block rounded-xl border border-slate-200 bg-white px-4 py-4 transition hover:border-slate-300 hover:bg-slate-50">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-sm font-semibold text-slate-900">{{ $primaryGuidance['store_name'] ?: '-' }}</p>
                                            <span class="text-xs text-slate-400">·</span>
                                            <p class="text-xs text-slate-500">{{ $this->humanMarketplace($primaryGuidance['marketplace']) }}</p>
                                            <span class="text-xs text-slate-400">·</span>
                                            <p class="text-xs text-slate-500">{{ $this->guidanceCategoryLabel($primaryGuidance['category']) }}</p>
                                        </div>
                                        <p class="mt-2 text-sm font-medium text-slate-900">{{ $primaryGuidance['title'] }}</p>
                                        <p class="mt-2 text-sm text-slate-500">{{ $primaryGuidance['recommended_action'] }}</p>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                                        <x-zolm.status-badge :tone="$this->guidanceSeverityTone($primaryGuidance['severity'])">
                                            {{ $this->guidanceSeverityLabel($primaryGuidance['severity']) }}
                                        </x-zolm.status-badge>
                                        <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">
                                            {{ $formatCount($primaryGuidance['impact_count']) }} kayıt
                                        </span>
                                        <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">
                                            {{ $this->guidanceRouteLabel($primaryGuidance['route']) }}
                                        </span>
                                    </div>
                                </div>
                            </a>
                        @endif

                        @if($secondaryGuidance->isNotEmpty())
                            <div class="flex justify-end">
                                <button type="button"
                                        @click="prioritiesOpen = !prioritiesOpen"
                                        class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                    <span x-text="prioritiesOpen ? 'Diğer öncelikleri gizle' : 'Diğer öncelikleri göster'"></span>
                                </button>
                            </div>

                            <div x-show="prioritiesOpen" x-cloak x-transition class="grid grid-cols-1 gap-3">
                                @foreach($secondaryGuidance as $item)
                                    <a href="{{ $this->guidanceRoute($item) }}"
                                       class="block rounded-xl border border-slate-200 bg-white px-4 py-3 transition hover:border-slate-300 hover:bg-slate-50">
                                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="text-sm font-semibold text-slate-900">{{ $item['store_name'] ?: '-' }}</p>
                                                    <span class="text-xs text-slate-400">·</span>
                                                    <p class="text-xs text-slate-500">{{ $this->humanMarketplace($item['marketplace']) }}</p>
                                                    <span class="text-xs text-slate-400">·</span>
                                                    <p class="text-xs text-slate-500">{{ $this->guidanceCategoryLabel($item['category']) }}</p>
                                                </div>
                                                <p class="mt-2 text-sm font-medium text-slate-800">{{ $item['title'] }}</p>
                                                <p class="mt-2 text-sm text-slate-500">{{ $item['recommended_action'] }}</p>
                                            </div>

                                            <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                                                <x-zolm.status-badge :tone="$this->guidanceSeverityTone($item['severity'])">
                                                    {{ $this->guidanceSeverityLabel($item['severity']) }}
                                                </x-zolm.status-badge>
                                                <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">
                                                    {{ $formatCount($item['impact_count']) }} kayıt
                                                </span>
                                            </div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        @if(!$primaryGuidance)
                            <div class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-5 text-sm text-slate-500">
                                Eşleştirme merkezini etkileyen kayıtlı bir öncelik bulunmuyor.
                            </div>
                        @endif
                    </div>
                </details>

                <div class="matching-ledger-shell mt-4 overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
                    <div class="matching-ledger-top border-b border-slate-200 px-4 py-4 lg:px-5 lg:py-5">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900">Filtreler</p>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ count($activeFilters) > 0 ? implode(' · ', array_slice($activeFilters, 0, 4)) : 'Henüz aktif filtre yok.' }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600">
                                {{ count($activeFilters) }} aktif filtre
                            </span>
                            <button type="button"
                                    @click="advancedFilters = !advancedFilters"
                                    class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                <span x-text="advancedFilters ? 'Gelişmiş filtreleri gizle' : 'Gelişmiş filtreler'"></span>
                            </button>
                            <button wire:click="resetFilters"
                                    class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-rose-600 transition hover:bg-rose-50">
                                Filtreleri temizle
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 lg:gap-4">
                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-slate-500">Arama</label>
                            <input wire:model.live.debounce.300ms="search"
                                   type="text"
                                   placeholder="Stok kodu, barkod, listing..."
                                   class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-slate-500">Mağaza</label>
                            <select wire:model.live="storeFilter"
                                    class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <option value="">Tümü</option>
                                @foreach($storeOptions as $store)
                                    <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <div class="flex items-center gap-1.5">
                                <label class="block text-xs sm:text-sm font-medium text-slate-500">Sorun durumu</label>
                                <x-zolm.help-tip title="Sorun durumu" summary="Listeyi yalnızca bekleyen, çözülen veya göz ardı edilen sorun kümelerine göre daraltır." source="Sorun durumu alanı." refresh="Her satır durumu güncellendiğinde anında." impact="Elle çözüm kuyruğunu daha hızlı temizlemenizi sağlar." />
                            </div>
                            <select wire:model.live="statusFilter"
                                    class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <option value="pending">Bekleyen</option>
                                <option value="resolved">Çözülen</option>
                                <option value="ignored">Göz ardı</option>
                                <option value="">Tümü</option>
                            </select>
                        </div>

                    </div>

                    <div x-show="advancedFilters" x-cloak x-transition class="mt-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-slate-500">Pazaryeri</label>
                            <select wire:model.live="marketplaceFilter"
                                    class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <option value="">Tümü</option>
                                @foreach($marketplaceOptions as $marketplace)
                                    <option value="{{ $marketplace }}">{{ $this->humanMarketplace($marketplace) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-slate-500">Firma</label>
                            <select wire:model.live="legalEntityFilter"
                                    class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <option value="">Tümü</option>
                                @foreach($legalEntities as $entity)
                                    <option value="{{ $entity->id }}">{{ $entity->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-slate-500">Sebep</label>
                            <select wire:model.live="reasonFilter"
                                    class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <option value="">Tümü</option>
                                @foreach($reasonOptions as $reason)
                                    <option value="{{ $reason }}">{{ $this->reasonLabel($reason) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-slate-500">Aday / listing</label>
                            <select wire:model.live="candidateStateFilter"
                                    class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <option value="">Tümü</option>
                                <option value="with_candidates">Aday var</option>
                                <option value="without_candidates">Aday yok</option>
                                <option value="with_listing">Listelemeye bağlı</option>
                                <option value="without_listing">Listeleme yok</option>
                            </select>
                        </div>
                    </div>

                    <section x-cloak x-show="selectedIssueIds.length > 0" class="mt-4 flex flex-col gap-3 rounded-[8px] border border-indigo-200 bg-indigo-50/40 p-3 sm:flex-row sm:items-center sm:justify-between" aria-label="Toplu işlem">
                        <div class="flex items-center gap-2 text-sm font-medium text-slate-700">
                            <span x-text="selectedIssueIds.length" class="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-slate-900 px-1.5 text-xs font-semibold text-white">{{ number_format(count($selectedIssueIds), 0, ',', '.') }}</span>
                            kayıt seçildi
                        </div>
                        <form method="POST" action="{{ route('mp.matching.apply') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                            @csrf
                            <div class="relative w-full sm:w-56">
                                <input type="hidden" name="action" :value="bulkAction">
                                <button type="button"
                                        @click="bulkActionOpen = !bulkActionOpen"
                                        class="flex min-h-[44px] w-full items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-left text-sm text-slate-900 shadow-sm transition hover:bg-slate-50 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                    <span x-text="bulkAction ? bulkActionLabels[bulkAction] : 'Toplu sorun aksiyonu'"></span>
                                    <svg class="h-4 w-4 text-slate-400 transition" :class="{ 'rotate-180': bulkActionOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6" /></svg>
                                </button>
                                <div x-cloak
                                     x-show="bulkActionOpen"
                                     x-transition.origin.top.right
                                     @click.outside="bulkActionOpen = false"
                                     class="absolute bottom-full right-0 z-40 mb-2 w-full overflow-hidden rounded-lg border border-slate-200 bg-white p-1 shadow-lg">
                                    <button type="button" @click="bulkAction = 'create_products'; bulkActionOpen = false" class="flex w-full items-center rounded-md px-3 py-2 text-left text-sm font-medium text-slate-900 hover:bg-slate-50">Seçilileri ürün listesine ekle</button>
                                    <button type="button" @click="bulkAction = 'ignore'; bulkActionOpen = false" class="flex w-full items-center rounded-md px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50">Seçilileri inceleme dışı bırak</button>
                                    <button type="button" @click="bulkAction = 'reopen'; bulkActionOpen = false" class="flex w-full items-center rounded-md px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50">Seçilileri yeniden aç</button>
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <button type="submit"
                                        class="inline-flex min-h-[44px] items-center justify-center rounded-lg bg-slate-900 px-4 py-3 sm:py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                                    Uygula
                                </button>
                                <button type="submit" formaction="{{ route('mp.matching.clear-selection') }}"
                                        class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                    Seçimi temizle
                                </button>
                            </div>
                            </div>
                        </form>
                    </section>
                    </div>

                    <div class="px-4 pb-4 pt-3 lg:px-5 lg:pb-5">
                <div class="space-y-3 md:hidden">
                    @forelse($issues as $issue)
                        @php
                            $candidateCount = $candidateMap[$issue->id]->count();
                        @endphp
                        <article class="rounded-xl border border-slate-200 bg-white shadow-sm">
                            <button type="button"
                                    class="w-full p-4 text-left"
                                    @click="expanded.includes({{ $issue->id }}) ? expanded = expanded.filter(i => i !== {{ $issue->id }}) : expanded.push({{ $issue->id }})">
                                <div class="flex items-start gap-3">
                                    <label class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center" @click.stop>
                                        <input type="checkbox"
                                               wire:model.live="selectedIssueIds"
                                               value="{{ $issue->id }}"
                                               class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-indigo-200">
                                    </label>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <svg class="h-4 w-4 shrink-0 text-slate-400 transition"
                                                 :class="{ 'rotate-90 text-slate-900': expanded.includes({{ $issue->id }}) }"
                                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                            <p class="truncate text-sm font-semibold text-slate-900">{{ $issue->channel_title_alias ?: 'Kanal ürünü yok' }}</p>
                                        </div>
                                        <p class="mt-1 text-xs text-slate-500">{{ $issue->channel_stock_code_alias ?: '-' }} · {{ $issue->channel_barcode_alias ?: '-' }}</p>
                                        <div class="mt-3 grid grid-cols-2 gap-2">
                                            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 px-3 py-2">
                                                <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Aday</p>
                                                <p class="mt-1 text-sm font-medium text-slate-900">{{ $candidateCount }}</p>
                                            </div>
                                            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 px-3 py-2">
                                                <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Mağaza</p>
                                                <p class="mt-1 text-sm font-medium text-slate-900 truncate">{{ $issue->store_name_alias ?: '-' }}</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-right">
                                        <x-zolm.status-badge :tone="$this->statusTone($issue->match_status)">
                                            {{ $this->statusLabel($issue->match_status) }}
                                        </x-zolm.status-badge>
                                        <p class="mt-3 text-xs {{ $this->reasonTone($issue->match_reason) === 'danger' ? 'text-rose-600' : 'text-amber-600' }}">{{ $this->reasonLabel($issue->match_reason) }}</p>
                                    </div>
                                </div>
                            </button>

                            <div x-show="expanded.includes({{ $issue->id }})" x-cloak x-transition class="border-t border-slate-200 bg-slate-50/60 p-4">
                                @include('livewire.partials.marketplace-matching-issue-detail', ['issue' => $issue])
                            </div>
                        </article>
                    @empty
                        <div class="rounded-[24px] border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">
                            Seçilen filtrelere uygun sorun bulunamadı.
                        </div>
                    @endforelse
                </div>

                <div class="hidden md:block">
                    @php
                        $columnMeta = [
                            'urun' => ['label' => 'Kanal Ürünü', 'width' => '250px'],
                            'magaza' => ['label' => 'Mağaza', 'width' => '150px'],
                            'sebep' => ['label' => 'Sorun', 'width' => '170px'],
                            'aday' => ['label' => 'Aday', 'width' => '84px'],
                            'durum' => ['label' => 'Durum', 'width' => '100px'],
                            'aksiyon' => ['label' => 'Aksiyon', 'width' => '90px'],
                        ];
                    @endphp

                    <div class="overflow-x-auto [scrollbar-gutter:stable]" x-data="columnResize()">
                        <table class="matching-v2-table w-full divide-y divide-slate-200 table-fixed" style="min-width: 900px;">
                            <thead class="bg-slate-50 text-slate-500">
                                <tr>
                                    <th class="w-10 px-3 py-3 text-center text-xs font-semibold uppercase tracking-[0.16em]">
                                        <form method="POST" action="{{ $allCurrentIssuesSelected ? route('mp.matching.clear-selection') : route('mp.matching.select') }}" class="inline-flex" x-ref="pageSelectionForm">
                                            @csrf
                                            @if(! $allCurrentIssuesSelected)
                                                @foreach($issues as $issue)
                                                    <input type="hidden" name="issue_ids[]" value="{{ $issue->id }}">
                                                @endforeach
                                            @endif
                                            <input type="checkbox"
                                                   aria-label="Bu sayfadaki kayıtları seç"
                                                   title="Bu sayfadaki {{ $issues->count() }} kaydı seç"
                                                   :checked="pageIssueIds.length > 0 && pageIssueIds.every(id => selectedIssueIds.includes(String(id)))"
                                                   @change="submitPageSelection($event.target.checked, $event.target.form)"
                                                   class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-indigo-200">
                                        </form>
                                    </th>
                                    <th class="w-10 px-3 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em]"></th>
                                    @foreach($columnMeta as $columnKey => $meta)
                                        @if(in_array($columnKey, $visibleColumns, true))
                                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em]"
                                                style="width: {{ $meta['width'] }}; min-width: {{ $meta['width'] }};"
                                                @if(isset($sortableColumns[$columnKey])) wire:click="sortTable('{{ $columnKey }}')" @endif>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span>{{ $meta['label'] }}</span>
                                                    @if(isset($sortableColumns[$columnKey]))
                                                        <span class="text-[10px] {{ ($sortableColumns[$columnKey] ?? null) === $sortField ? 'text-slate-700' : 'text-slate-300' }}">{{ $sortIcon($columnKey) }}</span>
                                                    @endif
                                                </div>
                                            </th>
                                        @endif
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                                @forelse($issues as $issue)
                                    <tr class="transition hover:bg-slate-50/80">
                                        <td class="px-3 py-4 align-top">
                                            <form method="POST" action="{{ route('mp.matching.toggle-selection') }}" class="inline-flex">
                                                @csrf
                                                <input type="hidden" name="issue_id" value="{{ $issue->id }}">
                                                <input type="hidden" name="selected" value="0">
                                                <input type="checkbox"
                                                       name="selected"
                                                       value="1"
                                                       aria-label="{{ $issue->channel_title_alias ?: 'Kanal ürünü' }} kaydını seç"
                                                       :checked="selectedIssueIds.includes('{{ $issue->id }}')"
                                                       @change="toggleIssueSelection('{{ $issue->id }}', $event.target.checked, $event.target.form)"
                                                       class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-indigo-200">
                                            </form>
                                        </td>
                                            <td class="px-3 py-4 align-top">
                                                <button type="button"
                                                    title="Ürünü incele ve eşleştir"
                                                    aria-label="Ürünü incele ve eşleştir"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-900"
                                                    @click="expanded.includes({{ $issue->id }}) ? expanded = expanded.filter(i => i !== {{ $issue->id }}) : expanded.push({{ $issue->id }})">
                                                <svg class="h-4 w-4 transition" :class="{ 'rotate-90': expanded.includes({{ $issue->id }}) }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                </svg>
                                            </button>
                                        </td>

                                        @if(in_array('urun', $visibleColumns, true))
                                            <td class="px-3 py-4 align-top">
                                                <div class="font-semibold text-slate-900 truncate">{{ $issue->channel_title_alias ?: 'Kanal ürünü yok' }}</div>
                                                <div class="mt-1 text-xs text-slate-500 truncate">{{ $issue->channel_stock_code_alias ?: '-' }} · {{ $issue->channel_barcode_alias ?: '-' }}</div>
                                                <div class="mt-1 text-[11px] text-slate-400">Listeleme {{ $issue->listing_id_alias ?: '-' }}</div>
                                            </td>
                                        @endif

                                        @if(in_array('magaza', $visibleColumns, true))
                                            <td class="px-3 py-4 align-top">
                                                <div class="font-semibold text-slate-900">{{ $issue->store_name_alias ?: '-' }}</div>
                                                <div class="mt-1 text-xs text-slate-500">{{ $this->humanMarketplace($issue->marketplace_alias) }}</div>
                                                <div class="mt-1 text-[11px] text-slate-400">{{ $issue->legal_entity_name_alias ?: '-' }}</div>
                                            </td>
                                        @endif

                                        @if(in_array('sebep', $visibleColumns, true))
                                            <td class="px-3 py-4 align-top">
                                                <x-zolm.status-badge :tone="$this->reasonTone($issue->match_reason)">
                                                    {{ $this->reasonLabel($issue->match_reason) }}
                                                </x-zolm.status-badge>
                                            </td>
                                        @endif

                                        @if(in_array('aday', $visibleColumns, true))
                                            <td class="px-3 py-4 align-top">
                                                <div class="font-semibold text-slate-900">{{ $formatCount($candidateMap[$issue->id]->count()) }}</div>
                                                <div class="mt-1 text-xs text-slate-500">{{ $formatCount($issue->candidate_count_metric ?? 0) }} otomatik</div>
                                            </td>
                                        @endif

                                        @if(in_array('durum', $visibleColumns, true))
                                            <td class="px-3 py-4 align-top">
                                                <x-zolm.status-badge :tone="$this->statusTone($issue->match_status)">
                                                    {{ $this->statusLabel($issue->match_status) }}
                                                </x-zolm.status-badge>
                                            </td>
                                        @endif

                                        @if(in_array('aksiyon', $visibleColumns, true))
                                            <td class="px-3 py-4 align-top text-right">
                                                @if($issue->match_status === 'pending')
                                                    <form method="POST" action="{{ route('mp.matching.create-product') }}">
                                                        @csrf
                                                        <input type="hidden" name="issue_id" value="{{ $issue->id }}">
                                                        <button type="submit" class="inline-flex min-h-[36px] items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-xs font-medium text-white transition hover:bg-slate-800">
                                                            Ürün listesine ekle
                                                        </button>
                                                    </form>
                                                @else
                                                    <button wire:click="reopenIssue({{ $issue->id }})"
                                                            class="inline-flex min-h-[36px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                                                        Aç
                                                    </button>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>

                                    <tr x-show="expanded.includes({{ $issue->id }})" x-cloak x-transition class="expanded-row bg-slate-50/60">
                                        <td colspan="{{ count($visibleColumns) + 2 }}" class="p-0">
                                            <div class="px-4 py-4 lg:px-6">
                                                @include('livewire.partials.marketplace-matching-issue-detail', ['issue' => $issue])
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($visibleColumns) + 2 }}" class="px-6 py-12 text-center text-sm text-slate-500">
                                            Seçilen filtrelere uygun sorun bulunamadı.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2">
                        <label class="text-xs sm:text-sm text-slate-500">Sayfa boyutu</label>
                        <select wire:model.live="perPage"
                                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            <option value="10">10</option>
                            <option value="15">15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </div>

                    {{ $issues->links() }}
                </div>
                    </div>
            </div>
        </section>

    </div>

    {{-- TABLO STİLLERİ --}}
    <style>
        .matching-v2-table {
            table-layout: fixed;
            width: 100%;
            min-width: 900px;
        }
        .matching-v2-table th {
            white-space: nowrap;
        }
        .matching-v2-table tbody > tr:not(.expanded-row) > td {
            overflow: hidden;
            text-overflow: clip;
            white-space: normal;
        }
        .col-resize-handle {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 5px;
            cursor: col-resize;
            user-select: none;
        }
        .col-resize-handle:hover,
        .col-resize-handle:active {
            background-color: #cbd5e1;
        }
        .sortable-th {
            cursor: pointer;
            user-select: none;
        }
        .sortable-th:hover {
            background-color: #f1f5f9;
        }
    </style>

    {{-- Column Resize Alpine.js Component --}}
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
                        const diff = ev.pageX - this.startX;
                        const newWidth = Math.max(40, this.startWidth + diff);
                        this.currentTh.style.width = newWidth + 'px';
                        this.currentTh.style.minWidth = newWidth + 'px';
                    };

                    const onMouseUp = () => {
                        this.resizing = false;
                        if (this.handle) this.handle.classList.remove('active');
                        this.currentTh = null;
                        document.removeEventListener('mousemove', onMouseMove);
                        document.removeEventListener('mouseup', onMouseUp);
                    };

                    document.addEventListener('mousemove', onMouseMove);
                    document.addEventListener('mouseup', onMouseUp);
                }
            }));
        });
    </script>
</div>
