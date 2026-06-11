@php
    $badge = fn($claim) => $claim?->statusBadgeColor() ?? ['bg' => 'bg-slate-50', 'text' => 'text-slate-500', 'ring' => 'ring-slate-200'];
    $crmLinks = app(\App\Services\Crm\CrmSourceLinkService::class);
    $crmSnapshots = app(\App\Services\Crm\CrmCustomerSnapshotService::class);
    $marketplaces = $stores->pluck('marketplace')->filter()->unique()->sort()->values();
    $columnLabels = [
        'date' => 'Tarih',
        'marketplace' => 'Pazaryeri',
        'claim' => 'İade No',
        'order' => 'Sipariş',
        'customer' => 'Müşteri',
        'tracking' => 'Takip',
        'status' => 'Durum',
        'reason' => 'Neden',
    ];
@endphp

<div class="space-y-4 lg:space-y-6 {{ $embedded ? '' : 'p-4 lg:p-6' }}">
    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="grid grid-cols-1 gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start lg:p-6">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Pazaryeri İadeleri</p>
                <h2 class="mt-1 text-xl font-bold text-slate-900 lg:text-2xl">Gelen İade Talepleri</h2>
                <p class="mt-1 max-w-2xl text-sm text-slate-500">
                    Trendyol, Hepsiburada, N11, Pazarama, Çiçeksepeti, Koçtaş ve WooCommerce iade/refund kayıtları tek karar yüzeyinde izlenir.
                </p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row lg:justify-end">
                <button type="button" wire:click="syncClaims" wire:loading.attr="disabled" wire:target="syncClaims" class="inline-flex w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-60 sm:w-auto sm:py-2">
                    <span wire:loading.remove wire:target="syncClaims">İadeleri Çek</span>
                    <span wire:loading wire:target="syncClaims">Kuyruğa alınıyor...</span>
                </button>
                <button type="button" wire:click="exportExcel" class="inline-flex w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto sm:py-2">
                    Excel
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 border-t border-slate-200 bg-slate-50/60 p-4 sm:grid-cols-2 xl:grid-cols-4 lg:p-6">
            <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Açık talep</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($kpis['waiting']) }}</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Karar bekleyen</p>
                <p class="mt-1 text-2xl font-bold text-blue-700">{{ number_format($kpis['decision']) }}</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Onaylanan</p>
                <p class="mt-1 text-2xl font-bold text-emerald-700">{{ number_format($kpis['approved']) }}</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Riskli / red</p>
                <p class="mt-1 text-2xl font-bold text-rose-700">{{ number_format($kpis['rejected']) }}</p>
            </div>
        </div>
    </section>

    @if($message !== '')
        <div class="rounded-[8px] border px-4 py-3 text-sm {{ $messageType === 'error' ? 'border-rose-200 bg-rose-50 text-rose-700' : ($messageType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-sky-200 bg-sky-50 text-sky-700') }}">
            {{ $message }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_380px]">
        <section class="min-w-0 rounded-[10px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-4 lg:p-6">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div class="grid min-w-0 flex-1 grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <input type="search" wire:model.live.debounce.400ms="searchQuery" placeholder="İade, sipariş, müşteri veya barkod ara" class="rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 placeholder:text-slate-400 focus:border-slate-400 focus:ring-0 sm:text-sm">
                        <select wire:model.live="statusFilter" class="rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-400 focus:ring-0 sm:text-sm">
                            <option value="all">Tüm durumlar</option>
                            @foreach($statusLabels as $status => $label)
                                <option value="{{ $status }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="marketplaceFilter" class="rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-400 focus:ring-0 sm:text-sm">
                            <option value="all">Tüm pazaryerleri</option>
                            @foreach($marketplaces as $marketplace)
                                <option value="{{ $marketplace }}">{{ \Illuminate\Support\Str::headline($marketplace) }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="dateFilter" class="rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-400 focus:ring-0 sm:text-sm">
                            <option value="today">Bugün</option>
                            <option value="yesterday">Dün</option>
                            <option value="last7days">Son 7 gün</option>
                            <option value="last30days">Son 30 gün</option>
                            <option value="all">Tüm zamanlar</option>
                        </select>
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row lg:justify-end">
                        <select wire:model.live="storeFilter" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-400 focus:ring-0 sm:w-auto sm:text-sm">
                            <option value="all">Tüm mağazalar</option>
                            @foreach($stores as $store)
                                @if($marketplaceFilter === 'all' || $store->marketplace === $marketplaceFilter)
                                    <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                                @endif
                            @endforeach
                        </select>

                        <div class="relative" x-data="{ open: false }">
                            <button type="button" @click="open = !open" class="inline-flex w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto sm:py-2">
                                Kolonlar
                            </button>
                            <div x-cloak x-show="open" @click.outside="open = false" class="absolute right-0 z-20 mt-2 w-56 rounded-[8px] border border-slate-200 bg-white p-2 shadow-lg">
                                @foreach($columnLabels as $column => $label)
                                    <label class="flex items-center gap-2 rounded-[6px] px-2 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                        <input type="checkbox" wire:click="toggleColumn('{{ $column }}')" @checked($visibleColumns[$column] ?? false) class="rounded border-slate-300">
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    <span class="rounded-[6px] border border-slate-200 bg-slate-50/60 px-2 py-1">{{ number_format($claims->total()) }} kayıt</span>
                    @if($searchQuery !== '')
                        <span class="rounded-[6px] border border-slate-200 bg-slate-50/60 px-2 py-1">Arama: {{ $searchQuery }}</span>
                    @endif
                </div>
            </div>

            <div class="md:hidden divide-y divide-slate-200">
                @forelse($claims as $claim)
                    @php
                        $colors = $badge($claim);
                    @endphp
                    <button type="button" wire:click="selectClaim({{ $claim->id }})" class="block w-full px-4 py-4 text-left {{ $selectedClaim?->id === $claim->id ? 'bg-slate-50' : 'bg-white' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $claim->external_claim_id }}</p>
                                <p class="mt-1 truncate text-xs text-slate-500">{{ $claim->store?->store_name }} · {{ $claim->order_number ?: 'Sipariş yok' }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium ring-1 {{ $colors['bg'] }} {{ $colors['text'] }} {{ $colors['ring'] }}">{{ $claim->statusLabel() }}</span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-slate-500">
                            <span>{{ $claim->created_date?->format('d.m.Y H:i') ?? '-' }}</span>
                            <span class="truncate text-right">{{ $claim->customer_name ?: '-' }}</span>
                            <span class="truncate col-span-2">{{ $claim->reason ?: 'Neden girilmemiş' }}</span>
                        </div>
                    </button>
                @empty
                    <div class="p-6 text-center text-sm text-slate-500">Filtreye uygun iade bulunamadı.</div>
                @endforelse
            </div>

            <div class="hidden md:block">
                <div class="overflow-x-auto [scrollbar-gutter:stable]" x-data="marketplaceClaimColumnResize()">
                    <table class="w-full table-fixed divide-y divide-slate-200">
                        <thead class="bg-slate-50/70">
                            <tr>
                                @if($visibleColumns['date'] ?? false)<th class="relative w-32 px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"><button wire:click="sortTable('created_date')">Tarih</button><span class="claim-col-resize-handle" @mousedown.prevent.stop="startResize($event, $el.parentElement)"></span></th>@endif
                                @if($visibleColumns['marketplace'] ?? false)<th class="relative w-40 px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Pazaryeri<span class="claim-col-resize-handle" @mousedown.prevent.stop="startResize($event, $el.parentElement)"></span></th>@endif
                                @if($visibleColumns['claim'] ?? false)<th class="relative w-40 px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">İade No<span class="claim-col-resize-handle" @mousedown.prevent.stop="startResize($event, $el.parentElement)"></span></th>@endif
                                @if($visibleColumns['order'] ?? false)<th class="relative w-36 px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"><button wire:click="sortTable('order_number')">Sipariş</button><span class="claim-col-resize-handle" @mousedown.prevent.stop="startResize($event, $el.parentElement)"></span></th>@endif
                                @if($visibleColumns['customer'] ?? false)<th class="relative w-44 px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"><button wire:click="sortTable('customer_name')">Müşteri</button><span class="claim-col-resize-handle" @mousedown.prevent.stop="startResize($event, $el.parentElement)"></span></th>@endif
                                @if($visibleColumns['tracking'] ?? false)<th class="relative w-36 px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Takip<span class="claim-col-resize-handle" @mousedown.prevent.stop="startResize($event, $el.parentElement)"></span></th>@endif
                                @if($visibleColumns['status'] ?? false)<th class="relative w-52 px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"><button wire:click="sortTable('status')">Durum</button><span class="claim-col-resize-handle" @mousedown.prevent.stop="startResize($event, $el.parentElement)"></span></th>@endif
                                @if($visibleColumns['reason'] ?? false)<th class="relative w-56 px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Neden<span class="claim-col-resize-handle" @mousedown.prevent.stop="startResize($event, $el.parentElement)"></span></th>@endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse($claims as $claim)
                                @php
                                    $colors = $badge($claim);
                                @endphp
                                <tr wire:click="selectClaim({{ $claim->id }})" class="cursor-pointer hover:bg-slate-50 {{ $selectedClaim?->id === $claim->id ? 'bg-slate-50' : '' }}">
                                    @if($visibleColumns['date'] ?? false)<td class="px-3 py-3 text-sm text-slate-600">{{ $claim->created_date?->format('d.m.Y H:i') ?? '-' }}</td>@endif
                                    @if($visibleColumns['marketplace'] ?? false)<td class="px-3 py-3 text-sm font-medium text-slate-900 truncate">{{ $claim->store?->store_name ?? '-' }}</td>@endif
                                    @if($visibleColumns['claim'] ?? false)<td class="px-3 py-3 text-sm font-mono text-slate-700 truncate">{{ $claim->external_claim_id }}</td>@endif
                                    @if($visibleColumns['order'] ?? false)<td class="px-3 py-3 text-sm text-slate-700 truncate">{{ $claim->order_number ?: '-' }}</td>@endif
                                    @if($visibleColumns['customer'] ?? false)<td class="px-3 py-3 text-sm text-slate-700 truncate">{{ $claim->customer_name ?: '-' }}</td>@endif
                                    @if($visibleColumns['tracking'] ?? false)<td class="px-3 py-3 text-sm text-slate-600 truncate">{{ $claim->cargo_tracking_number ?: '-' }}</td>@endif
                                    @if($visibleColumns['status'] ?? false)<td class="px-3 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium ring-1 {{ $colors['bg'] }} {{ $colors['text'] }} {{ $colors['ring'] }}">{{ $claim->statusLabel() }}</span></td>@endif
                                    @if($visibleColumns['reason'] ?? false)<td class="px-3 py-3 text-sm text-slate-600 truncate">{{ $claim->reason ?: '-' }}</td>@endif
                                </tr>
                            @empty
                                <tr><td colspan="8" class="px-4 py-10 text-center text-sm text-slate-500">Filtreye uygun iade bulunamadı.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="border-t border-slate-200 px-4 py-3">
                {{ $claims->links() }}
            </div>
        </section>

        <aside class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
            @if($selectedClaim)
                @php
                    $selectedColors = $badge($selectedClaim);
                    $selectedClaimCrmSnapshot = $crmSnapshots->forSubject(auth()->user(), 'claim', $selectedClaim);
                @endphp
                <div class="border-b border-slate-200 p-4 lg:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Seçili iade</p>
                            <h3 class="mt-1 truncate text-lg font-bold text-slate-900">{{ $selectedClaim->external_claim_id }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ $selectedClaim->store?->store_name }} · {{ $selectedClaim->order_number ?: 'Sipariş no yok' }}</p>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-2">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium ring-1 {{ $selectedColors['bg'] }} {{ $selectedColors['text'] }} {{ $selectedColors['ring'] }}">{{ $selectedClaim->statusLabel() }}</span>
                            <a href="{{ $crmLinks->urlFor('claim', $selectedClaim) }}"
                               class="inline-flex min-h-[32px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                CRM 360
                            </a>
                        </div>
                    </div>
                    <x-zolm.crm-snapshot :snapshot="$selectedClaimCrmSnapshot" variant="panel" class="mt-4" />
                </div>

                <div class="space-y-4 p-4 lg:p-6">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-2">
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Müşteri</p>
                            <p class="mt-1 truncate text-sm font-semibold text-slate-900">{{ $selectedClaim->customer_name ?: '-' }}</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Takip</p>
                            <p class="mt-1 truncate text-sm font-semibold text-slate-900">{{ $selectedClaim->cargo_tracking_number ?: '-' }}</p>
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Neden</p>
                        <p class="mt-2 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3 text-sm text-slate-700">{{ $selectedClaim->reason_detail ?: $selectedClaim->reason ?: $selectedClaim->customer_note ?: 'Neden bilgisi gelmedi.' }}</p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Kalemler</p>
                        <div class="mt-2 divide-y divide-slate-100 rounded-[8px] border border-slate-200">
                            @forelse($selectedClaim->items as $item)
                                <div class="p-3">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $item->product_name ?: 'Ürün adı yok' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $item->stock_code ?: 'SKU yok' }} · {{ $item->barcode ?: 'Barkod yok' }} · {{ (int) $item->quantity }} adet
                                    </p>
                                </div>
                            @empty
                                <div class="p-3 text-sm text-slate-500">Kalem bilgisi gelmedi.</div>
                            @endforelse
                        </div>
                    </div>

                    <div class="space-y-3 border-t border-slate-200 pt-4">
                        <button type="button" wire:click="approveSelectedClaim" @disabled(!$actionCapabilities['approve']) class="inline-flex w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 sm:py-2">
                            Pazaryerinde Onayla
                        </button>
                        <textarea wire:model.defer="rejectReason" rows="3" placeholder="Red / analiz nedeni" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 placeholder:text-slate-400 focus:border-slate-400 focus:ring-0 sm:text-sm"></textarea>
                        @error('rejectReason') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <button type="button" wire:click="rejectSelectedClaim" @disabled(!$actionCapabilities['reject']) class="inline-flex w-full items-center justify-center rounded-[6px] border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700 hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-50 sm:py-2">
                                Reddet / Analize Gönder
                            </button>
                            <button type="button" wire:click="markNeedsReview" class="inline-flex w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 sm:py-2">
                                İncelemeye Al
                            </button>
                        </div>
                        @if(!$actionCapabilities['approve'] && !$actionCapabilities['reject'])
                            <p class="text-xs text-slate-500">Bu kanal için pazaryeri üzerinde onay/red aksiyonu yok; kayıt ZOLM içinde takip edilir.</p>
                        @endif
                    </div>
                </div>
            @else
                <div class="p-6 text-sm text-slate-500">Bir iade seçildiğinde detay ve aksiyonlar burada görünür.</div>
            @endif
        </aside>
    </div>

    @once
        <style>
            .claim-col-resize-handle {
                position: absolute;
                top: 8px;
                right: 0;
                bottom: 8px;
                width: 7px;
                cursor: col-resize;
                border-right: 1px solid transparent;
            }

            .claim-col-resize-handle:hover,
            .claim-col-resize-handle.active {
                border-right-color: rgb(148 163 184);
            }
        </style>
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('marketplaceClaimColumnResize', () => ({
                    resizing: false,
                    startX: 0,
                    startWidth: 0,
                    currentTh: null,
                    handle: null,
                    startResize(event, th) {
                        this.resizing = true;
                        this.startX = event.pageX;
                        this.startWidth = th.offsetWidth;
                        this.currentTh = th;
                        this.handle = event.target;
                        this.handle.classList.add('active');

                        const onMouseMove = (moveEvent) => {
                            if (!this.resizing || !this.currentTh) {
                                return;
                            }

                            const newWidth = Math.max(96, this.startWidth + (moveEvent.pageX - this.startX));
                            this.currentTh.style.width = newWidth + 'px';
                            this.currentTh.style.minWidth = newWidth + 'px';
                        };

                        const onMouseUp = () => {
                            this.resizing = false;

                            if (this.handle) {
                                this.handle.classList.remove('active');
                            }

                            this.currentTh = null;
                            this.handle = null;
                            document.removeEventListener('mousemove', onMouseMove);
                            document.removeEventListener('mouseup', onMouseUp);
                        };

                        document.addEventListener('mousemove', onMouseMove);
                        document.addEventListener('mouseup', onMouseUp);
                    },
                }));
            });
        </script>
    @endonce
</div>
