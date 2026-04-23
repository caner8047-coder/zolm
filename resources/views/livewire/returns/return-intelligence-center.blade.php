<div class="{{ $embedded ? 'space-y-4 lg:space-y-6' : 'space-y-4 lg:space-y-6 p-4 lg:p-6' }}">
    {{-- Sayfa Başlığı --}}
    @unless($embedded)
        <div>
            <p class="text-xs font-semibold tracking-widest text-slate-400 uppercase">Araçlar</p>
            <h1 class="text-xl lg:text-2xl font-bold text-slate-900 mt-1">Akıllı İade Merkezi</h1>
            <p class="text-sm text-slate-500 mt-0.5">Depodan gelen iade kayıtlarını, pazaryeri claim'lerini ve sipariş eşleştirmelerini tek ekrandan incele.</p>
        </div>
    @endunless

    {{-- Flash Mesaj --}}
    @if($message)
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="rounded-[8px] px-4 py-3 text-sm font-medium flex items-center justify-between
                    {{ $messageType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : '' }}
                    {{ $messageType === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : '' }}
                    {{ $messageType === 'info' ? 'bg-blue-50 text-blue-800 border border-blue-200' : '' }}">
            <span>{{ $message }}</span>
            <button @click="show = false" class="ml-4 text-current opacity-60 hover:opacity-100">&times;</button>
        </div>
    @endif

    {{-- KPI Kartları --}}
    @unless($embedded)
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 lg:gap-4">
            @php
                $kpiCards = [
                    ['label' => 'Analiz Kuyruğu', 'value' => $kpis['queued'] ?? 0, 'color' => 'text-blue-600', 'bg' => 'bg-blue-50/80 border-blue-100'],
                    ['label' => 'Karar Hazır', 'value' => $kpis['ready'] ?? 0, 'color' => 'text-emerald-600', 'bg' => 'bg-emerald-50/80 border-emerald-100'],
                    ['label' => 'İnceleme Gereken', 'value' => $kpis['review'] ?? 0, 'color' => 'text-amber-600', 'bg' => 'bg-amber-50/80 border-amber-100'],
                    ['label' => 'Karara Bağlanan', 'value' => $kpis['decisioned'] ?? 0, 'color' => 'text-slate-600', 'bg' => 'bg-slate-50/80 border-slate-100'],
                ];
            @endphp
            @foreach($kpiCards as $kpi)
                <div class="rounded-[8px] border {{ $kpi['bg'] }} p-3 lg:p-4">
                    <p class="text-[10px] font-semibold tracking-widest {{ $kpi['color'] }} uppercase">{{ $kpi['label'] }}</p>
                    <p class="text-2xl font-bold {{ $kpi['color'] }} mt-1">{{ number_format($kpi['value']) }}</p>
                </div>
            @endforeach
        </div>
    @endunless

    {{-- Operasyon Özeti + Oto-Politika Kompakt --}}
    <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="p-4 lg:p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Bugünün Operasyon Özeti</h2>
                <p class="text-xs text-slate-500 mt-0.5">Günlük iade akışı ve otomatik politika durumu.</p>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                @php
                    $dr = $dailyReport['totals'] ?? [];
                    $ap = $autoPolicyPreview ?? [];
                @endphp
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] bg-slate-50 border border-slate-200 text-xs font-medium text-slate-700">
                    Gelen <span class="font-bold">{{ $dr['submitted'] ?? 0 }}</span>
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] bg-red-50 border border-red-200 text-xs font-medium text-red-700">
                    Hasarlı <span class="font-bold">{{ $dr['damaged'] ?? 0 }}</span>
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] bg-emerald-50 border border-emerald-200 text-xs font-medium text-emerald-700">
                    Otomatik <span class="font-bold">{{ $dr['auto_decisioned'] ?? 0 }}</span>
                </span>
                @if(($ap['eligible'] ?? 0) > 0)
                    <button wire:click="runAutoPolicies" wire:loading.attr="disabled" wire:target="runAutoPolicies"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[6px] bg-slate-900 text-white text-xs font-medium hover:bg-slate-800 transition-colors disabled:opacity-50">
                        <span wire:loading.remove wire:target="runAutoPolicies">Otomatik politikayı çalıştır ({{ $ap['eligible'] }})</span>
                        <span wire:loading wire:target="runAutoPolicies" class="flex items-center gap-1.5">
                            <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            Çalışıyor...
                        </span>
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Ana İçerik: Tablo + Detay Paneli --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:gap-6">
        {{-- Sol: Tablo Bölümü --}}
        <section class="min-w-0 w-full flex-1">
            <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
                {{-- Command Bar: Filtreler + Kolon Yönetimi --}}
                <div class="p-4 border-b border-slate-200">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                        {{-- Arama --}}
                        <div class="relative flex-1">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input wire:model.live.debounce.400ms="searchQuery" type="text" placeholder="Sipariş, takip no, barkod, müşteri ara..."
                                   class="w-full pl-10 pr-4 py-2 text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white focus:ring-2 focus:ring-slate-200 focus:border-slate-300 outline-none transition-colors">
                        </div>
                        {{-- Filtreler --}}
                        <div class="flex items-center gap-2 flex-wrap">
                            <select wire:model.live="dateFilter" class="px-3 py-2 text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white">
                                <option value="all">Tüm Zamanlar</option>
                                <option value="today">Bugün</option>
                                <option value="yesterday">Dün</option>
                                <option value="last7days">Son 7 Gün</option>
                            </select>
                            <select wire:model.live="statusFilter" class="px-3 py-2 text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white">
                                <option value="all">Tüm durumlar</option>
                                @foreach(\App\Models\ReturnIntakeItem::STATUS_LABELS as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <select wire:model.live="conditionFilter" class="px-3 py-2 text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white">
                                <option value="all">Tüm hasarlar</option>
                                @foreach(\App\Models\ReturnIntakeItem::CONDITION_LABELS as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <select wire:model.live="decisionFilter" class="px-3 py-2 text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white">
                                <option value="all">Tüm kararlar</option>
                                @foreach(\App\Models\ReturnIntakeItem::DECISION_LABELS as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="flex items-center gap-2 mt-3 xl:mt-0">
                            {{-- Excel Export --}}
                            <button wire:click="exportExcel" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium text-emerald-700 bg-emerald-50 rounded-[6px] border border-emerald-200 hover:bg-emerald-100 transition-colors" title="Tabloda görünen kayıtları Excel olarak indir">
                                <span wire:loading.remove wire:target="exportExcel">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                </span>
                                <span wire:loading wire:target="exportExcel">
                                    <svg class="animate-spin h-3.5 w-3.5 text-emerald-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </span>
                                Excel'e Aktar
                            </button>
                            
                            {{-- Kolon Yönetimi Dropdown --}}
                            <div x-data="{ open: false }" class="relative">
                                <button @click="open = !open" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium text-slate-600 rounded-[6px] border border-slate-200 bg-white hover:bg-slate-50 transition-colors">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                                Kolonlar
                            </button>
                            <div x-show="open" @click.outside="open = false" x-transition
                                 class="absolute right-0 mt-2 w-48 rounded-[8px] border border-slate-200 bg-white shadow-lg z-20 py-2">
                                @php
                                    $columnLabels = [
                                        'date' => 'Tarih', 'type' => 'Tür', 'reference' => 'Referans',
                                        'marketplace' => 'Pazaryeri', 'status' => 'Durum', 'condition' => 'Hasar',
                                        'decision' => 'Karar', 'confidence' => 'Güven', 'operator' => 'Operatör',
                                    ];
                                @endphp
                                @foreach($columnLabels as $col => $label)
                                    <label class="flex items-center gap-2 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50 cursor-pointer">
                                        <input type="checkbox" wire:click="toggleColumn('{{ $col }}')" {{ $visibleColumns[$col] ?? false ? 'checked' : '' }}
                                               class="rounded border-slate-300 text-slate-900 focus:ring-slate-500 h-3.5 w-3.5">
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @if($searchQuery)
                        <p class="text-xs text-slate-500 mt-2">
                            "<span class="font-medium text-slate-700">{{ $searchQuery }}</span>" için {{ $items->total() }} sonuç bulundu.
                        </p>
                    @endif
                </div>

                {{-- Desktop Tablo --}}
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-sm" style="table-layout: fixed;">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/60">
                                @if($visibleColumns['date'] ?? false)
                                    <th class="px-4 py-2.5 text-left text-xs font-medium text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700 w-[120px]"
                                        wire:click="sortTable('arrived_at')">
                                        Tarih
                                        @if($sortField === 'arrived_at')
                                            <span class="ml-0.5">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                        @endif
                                    </th>
                                @endif
                                @if($visibleColumns['type'] ?? false)
                                    <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-[80px]">Tür</th>
                                @endif
                                @if($visibleColumns['reference'] ?? false)
                                    <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-[160px]">Referans</th>
                                @endif
                                @if($visibleColumns['marketplace'] ?? false)
                                    <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-[120px]">Pazaryeri</th>
                                @endif
                                @if($visibleColumns['status'] ?? false)
                                    <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700 w-[120px]"
                                        wire:click="sortTable('intake_status')">
                                        Durum
                                        @if($sortField === 'intake_status')
                                            <span class="ml-0.5">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                        @endif
                                    </th>
                                @endif
                                @if($visibleColumns['condition'] ?? false)
                                    <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-[90px]">Hasar</th>
                                @endif
                                @if($visibleColumns['decision'] ?? false)
                                    <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700 w-[120px]"
                                        wire:click="sortTable('decision_status')">
                                        Karar
                                        @if($sortField === 'decision_status')
                                            <span class="ml-0.5">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                        @endif
                                    </th>
                                @endif
                                @if($visibleColumns['confidence'] ?? false)
                                    <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700 w-[70px]"
                                        wire:click="sortTable('matching_confidence')">
                                        Güven
                                        @if($sortField === 'matching_confidence')
                                            <span class="ml-0.5">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                        @endif
                                    </th>
                                @endif
                                @if($visibleColumns['operator'] ?? false)
                                    <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-[100px]">Operatör</th>
                                @endif
                                <th class="px-3 py-2.5 text-center text-xs font-medium text-slate-500 uppercase tracking-wider w-[60px]">Detay</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($items as $item)
                                <tr wire:click="selectItem({{ $item->id }})" wire:key="row-{{ $item->id }}"
                                    class="cursor-pointer transition-colors hover:bg-slate-50/80 {{ $selectedItemId === $item->id ? 'bg-slate-50 ring-2 ring-inset ring-slate-200' : '' }}">
                                    @if($visibleColumns['date'] ?? false)
                                        <td class="px-4 py-3 text-xs text-slate-600 truncate">{{ $item->arrived_at?->format('d.m.Y H:i') ?? '-' }}</td>
                                    @endif
                                    @if($visibleColumns['type'] ?? false)
                                        <td class="px-3 py-3">
                                            @if($item->intake_type === 'damaged')
                                                <span class="px-2 py-0.5 text-xs font-mono rounded bg-red-50 text-red-700 border border-red-200">Hasarlı</span>
                                            @else
                                                <span class="px-2 py-0.5 text-xs font-mono rounded bg-emerald-50 text-emerald-700 border border-emerald-200">Hasarsız</span>
                                            @endif
                                        </td>
                                    @endif
                                    @if($visibleColumns['reference'] ?? false)
                                        <td class="px-3 py-3 text-xs text-slate-900 font-medium truncate" title="{{ $item->detected_tracking_number ?: $item->manual_reference ?: $item->operator_barcode }}">
                                            {{ $item->detected_tracking_number ?: $item->detected_order_number ?: $item->manual_reference ?: $item->operator_barcode ?: 'Referans yok' }}
                                        </td>
                                    @endif
                                    @if($visibleColumns['marketplace'] ?? false)
                                        <td class="px-3 py-3 text-xs text-slate-600 truncate">{{ $item->store?->store_name ?: $item->claim?->store?->store_name ?: '-' }}</td>
                                    @endif
                                    @if($visibleColumns['status'] ?? false)
                                        <td class="px-3 py-3">
                                            @php
                                                $statusColor = match($item->intake_status) {
                                                    'queued', 'analyzing' => 'bg-blue-50 text-blue-700 border-blue-200',
                                                    'matched', 'ready_for_decision' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                                    'needs_review' => 'bg-amber-50 text-amber-700 border-amber-200',
                                                    'decisioned' => 'bg-slate-50 text-slate-600 border-slate-200',
                                                    'failed' => 'bg-red-50 text-red-700 border-red-200',
                                                    default => 'bg-slate-50 text-slate-500 border-slate-200',
                                                };
                                            @endphp
                                            <span class="px-2 py-0.5 text-xs font-mono rounded border {{ $statusColor }}">{{ $item->statusLabel() }}</span>
                                        </td>
                                    @endif
                                    @if($visibleColumns['condition'] ?? false)
                                        <td class="px-3 py-3">
                                            @if($item->condition_status === 'damaged')
                                                <span class="inline-flex h-2 w-2 rounded-full bg-red-500 mr-1"></span>
                                                <span class="text-xs text-red-700">Hasarlı</span>
                                            @elseif($item->condition_status === 'undamaged')
                                                <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500 mr-1"></span>
                                                <span class="text-xs text-emerald-700">Sağlam</span>
                                            @else
                                                <span class="inline-flex h-2 w-2 rounded-full bg-slate-400 mr-1"></span>
                                                <span class="text-xs text-slate-500">Belirsiz</span>
                                            @endif
                                        </td>
                                    @endif
                                    @if($visibleColumns['decision'] ?? false)
                                        <td class="px-3 py-3">
                                            @php
                                                $decisionColor = match($item->decision_status) {
                                                    'approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                                    'rejected' => 'bg-red-50 text-red-700 border-red-200',
                                                    'restocked' => 'bg-blue-50 text-blue-700 border-blue-200',
                                                    'scrapped' => 'bg-slate-100 text-slate-600 border-slate-200',
                                                    'needs_review' => 'bg-amber-50 text-amber-700 border-amber-200',
                                                    default => 'bg-slate-50 text-slate-500 border-slate-200',
                                                };
                                            @endphp
                                            <span class="px-2 py-0.5 text-xs font-mono rounded border {{ $decisionColor }}">{{ $item->decisionLabel() }}</span>
                                        </td>
                                    @endif
                                    @if($visibleColumns['confidence'] ?? false)
                                        <td class="px-3 py-3 text-xs text-slate-600 font-mono">{{ $item->matching_confidence ? number_format($item->matching_confidence, 0) . '%' : '-' }}</td>
                                    @endif
                                    @if($visibleColumns['operator'] ?? false)
                                        <td class="px-3 py-3 text-xs text-slate-600 truncate">{{ $item->submittedBy?->name ?? '-' }}</td>
                                    @endif
                                    <td class="px-3 py-3 text-center">
                                        <button class="text-slate-400 hover:text-slate-700 transition-colors">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="20" class="px-4 py-12 text-center text-sm text-slate-400">
                                        Henüz iade kaydı yok.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Mobil Kart Görünümü --}}
                <div class="md:hidden divide-y divide-slate-100">
                    @forelse($items as $item)
                        <div wire:click="selectItem({{ $item->id }})" wire:key="mobile-{{ $item->id }}"
                             class="p-4 cursor-pointer transition-colors hover:bg-slate-50/80 {{ $selectedItemId === $item->id ? 'bg-slate-50 ring-2 ring-inset ring-slate-200' : '' }}">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-slate-900 truncate">
                                        {{ $item->detected_tracking_number ?: $item->detected_order_number ?: $item->manual_reference ?: $item->operator_barcode ?: 'Referans yok' }}
                                    </p>
                                    <p class="text-xs text-slate-500 mt-0.5">{{ $item->arrived_at?->format('d.m.Y H:i') ?? '-' }}</p>
                                </div>
                                <div class="flex flex-col items-end gap-1">
                                    @if($item->intake_type === 'damaged')
                                        <span class="px-2 py-0.5 text-[10px] font-mono rounded bg-red-50 text-red-700 border border-red-200">Hasarlı</span>
                                    @else
                                        <span class="px-2 py-0.5 text-[10px] font-mono rounded bg-emerald-50 text-emerald-700 border border-emerald-200">Hasarsız</span>
                                    @endif
                                    @php
                                        $mStatusColor = match($item->intake_status) {
                                            'queued', 'analyzing' => 'bg-blue-50 text-blue-700 border-blue-200',
                                            'matched', 'ready_for_decision' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                            'needs_review' => 'bg-amber-50 text-amber-700 border-amber-200',
                                            'failed' => 'bg-red-50 text-red-700 border-red-200',
                                            default => 'bg-slate-50 text-slate-500 border-slate-200',
                                        };
                                    @endphp
                                    <span class="px-2 py-0.5 text-[10px] font-mono rounded border {{ $mStatusColor }}">{{ $item->statusLabel() }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 mt-2 text-xs text-slate-500">
                                <span>{{ $item->store?->store_name ?: '-' }}</span>
                                <span class="text-slate-300">·</span>
                                <span>{{ $item->decisionLabel() }}</span>
                                @if($item->matching_confidence)
                                    <span class="text-slate-300">·</span>
                                    <span class="font-mono">{{ number_format($item->matching_confidence, 0) }}%</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-sm text-slate-400">Henüz iade kaydı yok.</div>
                    @endforelse
                </div>

                {{-- Pagination --}}
                @if($items->hasPages())
                    <div class="px-4 py-3 border-t border-slate-200">
                        {{ $items->links() }}
                    </div>
                @endif
            </div>
        </section>

        {{-- Sağ: Detay Paneli --}}
        <aside class="min-w-0 w-full lg:w-[360px] xl:w-[400px] lg:flex-shrink-0">
            <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm lg:sticky lg:top-20 lg:max-h-[calc(100vh-6.5rem)] lg:overflow-y-auto">
                @if($selectedItem)
                    <div class="p-4 border-b border-slate-200">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-slate-900">İade Detayı</h3>
                            <div class="flex items-center gap-2">
                                <button wire:click="reanalyzeSelectedItem" wire:loading.attr="disabled" wire:target="reanalyzeSelectedItem"
                                        class="text-xs text-slate-500 hover:text-slate-700 transition-colors flex items-center gap-1">
                                    <svg wire:loading.remove wire:target="reanalyzeSelectedItem" class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    <svg wire:loading wire:target="reanalyzeSelectedItem" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    Tekrar Analiz
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Durum Kartları --}}
                    <div class="p-4 space-y-3">
                        {{-- Tespit Özeti --}}
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-[10px] font-semibold tracking-widest text-slate-400 uppercase mb-2">Yapay Zeka Tespiti</p>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <span class="text-slate-500">Takip No:</span>
                                    <p class="font-mono font-medium text-slate-900 truncate">{{ $selectedItem->detected_tracking_number ?: '-' }}</p>
                                </div>
                                <div>
                                    <span class="text-slate-500">Sipariş No:</span>
                                    <p class="font-mono font-medium text-slate-900 truncate">{{ $selectedItem->detected_order_number ?: '-' }}</p>
                                </div>
                                <div>
                                    <span class="text-slate-500">Müşteri:</span>
                                    <p class="font-medium text-slate-900 truncate">{{ $selectedItem->detected_customer_name ?: '-' }}</p>
                                </div>
                                <div>
                                    <span class="text-slate-500">Kargo:</span>
                                    <p class="font-medium text-slate-900 truncate">{{ $selectedItem->cargo_provider ?: '-' }}</p>
                                </div>
                                <div>
                                    <span class="text-slate-500">Barkod:</span>
                                    <p class="font-mono font-medium text-slate-900 truncate">{{ $selectedItem->detected_barcode ?: $selectedItem->operator_barcode ?: '-' }}</p>
                                </div>
                                <div>
                                    <span class="text-slate-500">Güven:</span>
                                    <p class="font-mono font-medium text-slate-900">{{ $selectedItem->matching_confidence ? number_format($selectedItem->matching_confidence, 0) . '%' : '-' }}</p>
                                </div>
                            </div>
                            @if($selectedItem->last_error)
                                <div class="mt-2 px-2 py-1.5 rounded bg-red-50 border border-red-200 text-xs text-red-700">
                                    ⚠️ {{ $selectedItem->last_error }}
                                </div>
                            @endif
                        </div>

                        {{-- Sistem Önerisi --}}
                        @if($selectedItem->suggested_decision)
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                                <p class="text-[10px] font-semibold tracking-widest text-slate-400 uppercase mb-1">Sistem Önerisi</p>
                                @php
                                    $suggColor = match($selectedItem->suggested_decision) {
                                        'approve_marketplace', 'restock' => 'text-emerald-700 bg-emerald-50 border-emerald-200',
                                        'reject_marketplace', 'scrap' => 'text-red-700 bg-red-50 border-red-200',
                                        default => 'text-amber-700 bg-amber-50 border-amber-200',
                                    };
                                @endphp
                                <span class="inline-block px-2 py-0.5 text-xs font-mono rounded border {{ $suggColor }}">{{ $selectedItem->suggestedDecisionLabel() }}</span>
                                @if($selectedItem->suggestion_summary)
                                    <p class="text-xs text-slate-600 mt-1.5">{{ $selectedItem->suggestion_summary }}</p>
                                @endif
                            </div>
                        @endif

                        {{-- Eşleşme Bilgisi --}}
                        @if($selectedItem->order || $selectedItem->claim)
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                                <p class="text-[10px] font-semibold tracking-widest text-slate-400 uppercase mb-2">Eşleşen Sipariş</p>
                                @if($selectedItem->order)
                                    <p class="text-xs"><span class="text-slate-500">Sipariş:</span> <span class="font-mono font-medium text-slate-900">{{ $selectedItem->order->order_number }}</span></p>
                                    <p class="text-xs mt-0.5"><span class="text-slate-500">Müşteri:</span> <span class="font-medium text-slate-900">{{ $selectedItem->order->customer_name ?? '-' }}</span></p>
                                    @if($selectedItem->order->items->count())
                                        <div class="mt-2 space-y-1">
                                            @foreach($selectedItem->order->items as $orderItem)
                                                <div class="flex items-center justify-between text-xs bg-white rounded px-2 py-1 border border-slate-100">
                                                    <span class="truncate flex-1 min-w-0 text-slate-700">{{ $orderItem->product_name ?? $orderItem->barcode ?? '-' }}</span>
                                                    <span class="text-slate-500 font-mono ml-2">x{{ $orderItem->quantity ?? 1 }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                @endif
                                @if($selectedItem->claim)
                                    <div class="mt-2 pt-2 border-t border-slate-100">
                                        <p class="text-xs"><span class="text-slate-500">Claim:</span> <span class="font-mono font-medium text-slate-900">{{ $selectedItem->claim->external_claim_id ?? '-' }}</span></p>
                                        <p class="text-xs mt-0.5"><span class="text-slate-500">Durum:</span> <span class="font-medium text-slate-900">{{ $selectedItem->claim->statusLabel() }}</span></p>
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- Görseller --}}
                        @if($selectedItem->media->count())
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                                @php $labelMedia = $selectedItem->media->where('kind', 'label')->values(); @endphp
                                <p class="text-[10px] font-semibold tracking-widest text-slate-400 uppercase mb-2">Kanıt Görselleri ({{ $selectedItem->media->count() }})</p>
                                <div class="grid grid-cols-3 gap-2">
                                    @foreach($selectedItem->media as $media)
                                        @php $url = $media->thumbnailUrl() ?: $media->publicUrl(); @endphp
                                        @if($url)
                                            <a href="{{ $media->publicUrl() }}" target="_blank" class="block aspect-square rounded-[6px] overflow-hidden border border-slate-200 bg-white hover:ring-2 hover:ring-slate-300 transition-all">
                                                <img src="{{ $url }}" alt="{{ $media->kind }}" class="w-full h-full object-cover" loading="lazy">
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                                <div class="flex gap-1.5 mt-2">
                                    @foreach(['label' => 'Etiket', 'product' => 'Ürün', 'damage' => 'Hasar'] as $kind => $label)
                                        @php $count = $selectedItem->media->where('kind', $kind)->count(); @endphp
                                        @if($count)
                                            <span class="px-2 py-0.5 text-[10px] font-mono rounded bg-white border border-slate-200 text-slate-600">{{ $label }}: {{ $count }}</span>
                                        @endif
                                    @endforeach
                                </div>
                                @if($labelMedia->count() > 1)
                                    <div class="mt-3 rounded-[8px] border border-slate-200 bg-white p-3">
                                        <p class="text-xs font-medium text-slate-900">{{ $labelMedia->count() }} etiket yüklendi</p>
                                        <p class="mt-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Toplu yükleme listesi</p>
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            @foreach($labelMedia as $index => $media)
                                                <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] text-slate-600">
                                                    Etiket {{ $index + 1 }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- Depo Notu --}}
                        @if($selectedItem->warehouse_note)
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                                <p class="text-[10px] font-semibold tracking-widest text-slate-400 uppercase mb-1">Depo Notu</p>
                                <p class="text-xs text-slate-700">{{ $selectedItem->warehouse_note }}</p>
                            </div>
                        @endif
                    </div>

                    {{-- Aksiyon Butonları --}}
                    @if($selectedItem->decision_status === 'pending' || $selectedItem->decision_status === 'needs_review')
                        <div class="p-4 border-t border-slate-200 space-y-3">
                            {{-- Karar Notu --}}
                            <textarea wire:model="decisionNote" rows="2" placeholder="İç karar notu (opsiyonel)..."
                                      class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-2 focus:ring-2 focus:ring-slate-200 focus:border-slate-300 outline-none resize-none"></textarea>

                            {{-- İç Kararlar --}}
                            <div class="flex flex-wrap gap-2">
                                <button wire:click="markRestocked" class="flex-1 min-w-[100px] px-3 py-2.5 sm:py-2 text-xs font-medium rounded-[6px] bg-emerald-600 text-white hover:bg-emerald-700 transition-colors">
                                    Stoka Al
                                </button>
                                <button wire:click="markScrapped" class="flex-1 min-w-[100px] px-3 py-2.5 sm:py-2 text-xs font-medium rounded-[6px] bg-slate-600 text-white hover:bg-slate-700 transition-colors">
                                    Hurdaya
                                </button>
                                <button wire:click="markNeedsReview" class="flex-1 min-w-[100px] px-3 py-2.5 sm:py-2 text-xs font-medium rounded-[6px] border border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100 transition-colors">
                                    İncele
                                </button>
                            </div>

                            {{-- Pazaryeri Aksiyonları --}}
                            @if($selectedItem->claim)
                                <div class="pt-2 border-t border-slate-100 space-y-2">
                                    <p class="text-[10px] font-semibold tracking-widest text-slate-400 uppercase">Pazaryeri Aksiyonu</p>
                                    <button wire:click="approveClaim" wire:loading.attr="disabled" wire:target="approveClaim"
                                            class="w-full px-3 py-2.5 sm:py-2 text-xs font-medium rounded-[6px] bg-emerald-50 text-emerald-700 border border-emerald-300 hover:bg-emerald-100 transition-colors">
                                        ✓ Pazaryerinde Onayla
                                    </button>
                                    <div>
                                        <input wire:model="rejectReason" type="text" placeholder="Red nedeni yazın..."
                                               class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-2 focus:ring-2 focus:ring-slate-200 focus:border-slate-300 outline-none mb-2">
                                        <button wire:click="rejectClaim" wire:loading.attr="disabled" wire:target="rejectClaim"
                                                class="w-full px-3 py-2.5 sm:py-2 text-xs font-medium rounded-[6px] bg-red-50 text-red-700 border border-red-300 hover:bg-red-100 transition-colors">
                                            ✕ Pazaryerinde Reddet
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @else
                        {{-- Karar verilmiş --}}
                        <div class="p-4 border-t border-slate-200">
                            <div class="rounded-[8px] bg-slate-50 border border-slate-200 p-3 text-center">
                                <p class="text-xs text-slate-500">Bu kaydın kararı verildi:</p>
                                <p class="text-sm font-semibold text-slate-700 mt-1">{{ $selectedItem->decisionLabel() }}</p>
                                @if($selectedItem->latestDecision)
                                    <p class="text-xs text-slate-400 mt-1">
                                        {{ $selectedItem->latestDecision->user?->name ?? 'Otomatik' }}
                                        · {{ $selectedItem->latestDecision->created_at?->format('d.m H:i') }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    @endif
                @else
                    {{-- Boş detay paneli --}}
                    <div class="p-4 lg:p-5 space-y-4">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">İade Detayı</p>
                            <p class="mt-1 text-xs text-slate-500">Soldan bir kayıt seçildiğinde analiz, görseller ve karar aksiyonları bu panelde açılır.</p>
                        </div>

                        <div class="rounded-[8px] border border-dashed border-slate-200 bg-slate-50/70 p-4 text-center">
                            <svg class="h-10 w-10 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <p class="text-sm font-medium text-slate-700">Henüz kayıt seçilmedi</p>
                            <p class="mt-1 text-xs text-slate-500">Tablodaki herhangi bir satıra tıklayın; detay kartı aynı anda burada güncellensin.</p>
                        </div>

                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                            <p class="text-[10px] font-semibold tracking-widest text-slate-400 uppercase">Bu panelde ne göreceksiniz?</p>
                            <div class="mt-3 space-y-2">
                                <div class="rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                                    <p class="text-xs font-medium text-slate-800">Eşleşme ve güven skoru</p>
                                    <p class="mt-1 text-[11px] text-slate-500">Takip no, sipariş eşleşmesi ve AI güven oranı birlikte görünür.</p>
                                </div>
                                <div class="rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                                    <p class="text-xs font-medium text-slate-800">Kanıt görselleri</p>
                                    <p class="mt-1 text-[11px] text-slate-500">Etiket, ürün ve hasar fotoğrafları hızlıca kontrol edilir.</p>
                                </div>
                                <div class="rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                                    <p class="text-xs font-medium text-slate-800">Karar aksiyonları</p>
                                    <p class="mt-1 text-[11px] text-slate-500">Stoka al, hurdaya ayır veya pazaryeri kararını aynı yerden verin.</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3 lg:grid-cols-1">
                            <div class="rounded-[8px] border border-slate-200 bg-white px-3 py-2.5">
                                <p class="text-[10px] font-semibold tracking-widest text-slate-400 uppercase">Aktif Sonuç</p>
                                <p class="mt-1 text-lg font-semibold text-slate-900">{{ number_format($items->total()) }}</p>
                                <p class="text-[11px] text-slate-500">Mevcut filtreye uyan kayıt</p>
                            </div>
                            <div class="rounded-[8px] border border-amber-200 bg-amber-50/60 px-3 py-2.5">
                                <p class="text-[10px] font-semibold tracking-widest text-amber-600 uppercase">İnceleme</p>
                                <p class="mt-1 text-lg font-semibold text-amber-700">{{ number_format($kpis['review'] ?? 0) }}</p>
                                <p class="text-[11px] text-amber-700/80">Manuel bakılması önerilen</p>
                            </div>
                            <div class="rounded-[8px] border border-emerald-200 bg-emerald-50/60 px-3 py-2.5">
                                <p class="text-[10px] font-semibold tracking-widest text-emerald-600 uppercase">Karar Hazır</p>
                                <p class="mt-1 text-lg font-semibold text-emerald-700">{{ number_format($kpis['ready'] ?? 0) }}</p>
                                <p class="text-[11px] text-emerald-700/80">Doğrudan aksiyona yakın kayıt</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </aside>
    </div>
</div>
