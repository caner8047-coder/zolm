<div class="w-full space-y-4 lg:space-y-6">
    {{-- Mesaj Bildirimi --}}
    @if($message !== '')
        <div class="rounded-[8px] border p-4 text-sm {{ $messageType === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
            {{ $message }}
        </div>
    @endif

    {{-- Üst Workspace Summary Kartı --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                    Köprü Kontrol Paneli
                </div>
                <h1 class="mt-3 text-xl font-semibold tracking-tight text-slate-950 lg:text-2xl">Pazaryeri Finans Köprüsü</h1>
                <p class="mt-2 text-sm text-slate-500">
                    Pazaryeri siparişlerinizi satış belgesine dönüştürün; finansal hakediş ve masraf olaylarını genel muhasebeye entegre edin.
                </p>
            </div>
        </div>

        {{-- KPI Metrik Kartları Grid --}}
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 mt-6">
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                <span class="block text-[11px] font-medium text-slate-500 uppercase tracking-wider">Bekleyen Sipariş</span>
                <span class="block mt-1 text-lg font-bold text-slate-900">{{ $this->kpiMetrics['pending_orders'] }}</span>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                <span class="block text-[11px] font-medium text-slate-500 uppercase tracking-wider">Köprülenen Sipariş</span>
                <span class="block mt-1 text-lg font-bold text-slate-900">{{ $this->kpiMetrics['bridged_orders'] }}</span>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-rose-50/40 p-3 border-rose-100">
                <span class="block text-[11px] font-medium text-rose-600 uppercase tracking-wider">Hatalı Sipariş</span>
                <span class="block mt-1 text-lg font-bold text-rose-700">{{ $this->kpiMetrics['failed_orders'] }}</span>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                <span class="block text-[11px] font-medium text-slate-500 uppercase tracking-wider">Bekleyen Finans</span>
                <span class="block mt-1 text-lg font-bold text-slate-900">{{ $this->kpiMetrics['pending_events'] }}</span>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                <span class="block text-[11px] font-medium text-slate-500 uppercase tracking-wider">Muhasebeleşen</span>
                <span class="block mt-1 text-lg font-bold text-slate-900">{{ $this->kpiMetrics['bridged_events'] }}</span>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-rose-50/40 p-3 border-rose-100">
                <span class="block text-[11px] font-medium text-rose-600 uppercase tracking-wider">Hatalı Finans</span>
                <span class="block mt-1 text-lg font-bold text-rose-700">{{ $this->kpiMetrics['failed_events'] }}</span>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                <span class="block text-[11px] font-medium text-slate-500 uppercase tracking-wider">Son Başarılı</span>
                <span class="block mt-1 text-xs font-semibold text-slate-700">{{ $this->kpiMetrics['last_success_at'] }}</span>
            </div>
        </div>
    </section>

    {{-- Guidance Accordion --}}
    <div x-data="{ open: false }" class="rounded-[10px] border border-slate-200 bg-white overflow-hidden shadow-sm">
        <button @click="open = !open" class="w-full flex items-center justify-between p-4 text-sm font-semibold text-slate-800 bg-slate-50/40 hover:bg-slate-50 transition-colors">
            <span>💡 Pazaryeri köprüsü ne yapar ve nasıl çalışır?</span>
            <svg class="w-4 h-4 transform transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="open" x-collapse class="p-4 border-t border-slate-100 text-xs text-slate-500 space-y-2">
            <p>1. <strong>Sipariş Köprüleme:</strong> Onaylanmış pazaryeri siparişlerini otomatik fatura alacağı ve depo çıkışıyla birlikte Satış Belgesine dönüştürür. Stok kontrolü yapar, yetersiz stok durumunda işlemi iptal eder.</p>
            <p>2. <strong>Finansal Olay Entegrasyonu:</strong> Komisyon, kargo kesintileri ve banka hakediş ödemelerini (payout) Genel Muhasebeye otomatik fiş (Journal) olarak kaydeder.</p>
            <p>3. <strong>Hata Yönetimi ve Retry:</strong> Eksik ürün kodu eşleşmesi, yetersiz stok vb. durumlarda oluşan hataları işlem geçmişinden izleyebilir ve veriyi düzelttikten sonra tek tıkla yeniden çalıştırabilirsiniz.</p>
        </div>
    </div>

    {{-- Tab Bar --}}
    <div class="flex border-b border-slate-200 gap-4">
        <button wire:click="$set('activeTab', 'orders')" class="pb-3 text-sm font-semibold border-b-2 px-1 transition-all {{ $activeTab === 'orders' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-400 hover:text-slate-600' }}">
            Siparişler
        </button>
        <button wire:click="$set('activeTab', 'financial_events')" class="pb-3 text-sm font-semibold border-b-2 px-1 transition-all {{ $activeTab === 'financial_events' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-400 hover:text-slate-600' }}">
            Finansal Olaylar
        </button>
        <button wire:click="$set('activeTab', 'runs')" class="pb-3 text-sm font-semibold border-b-2 px-1 transition-all {{ $activeTab === 'runs' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-400 hover:text-slate-600' }}">
            İşlem Geçmişi
        </button>
    </div>

    {{-- Ana Section: Filtre ve Tablo Birleşik Kart --}}
    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm overflow-hidden">

        {{-- Filtre & Command Bar --}}
        <div class="p-4 border-b border-slate-100 bg-slate-50/20 space-y-4">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                {{-- Sol: Arama ve Temel Filtreler --}}
                <div class="flex flex-wrap items-center gap-2">
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Arama yapın..."
                           class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-400 focus:outline-none min-h-[40px] w-64" />

                    <select wire:model.live="storeId" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-400 focus:outline-none min-h-[40px]">
                        <option value="">Tüm Mağazalar</option>
                        @foreach($this->stores as $s)
                            <option value="{{ $s->id }}">{{ $s->store_name }}</option>
                        @endforeach
                    </select>

                    <select wire:model.live="marketplace" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-400 focus:outline-none min-h-[40px]">
                        <option value="">Tüm Pazaryerleri</option>
                        <option value="trendyol">Trendyol</option>
                        <option value="hepsiburada">Hepsiburada</option>
                        <option value="n11">n11</option>
                    </select>

                    @if($activeTab === 'orders' || $activeTab === 'financial_events')
                        <select wire:model.live="bridgeStatus" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-400 focus:outline-none min-h-[40px]">
                            <option value="">Tüm Köprü Durumları</option>
                            <option value="pending">Köprülenmemiş (Bekleyen)</option>
                            <option value="bridged">Köprülenmiş (Tamamlanan)</option>
                        </select>
                    @endif

                    @if($activeTab === 'financial_events')
                        <select wire:model.live="eventType" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-400 focus:outline-none min-h-[40px]">
                            <option value="">Tüm Finans Tipleri</option>
                            <option value="commission">Komisyon</option>
                            <option value="shipping_fee">Kargo</option>
                            <option value="payout">Ödeme (Payout)</option>
                        </select>
                    @endif

                    @if($activeTab === 'runs')
                        <select wire:model.live="runStatus" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-400 focus:outline-none min-h-[40px]">
                            <option value="">Tüm İşlem Durumları</option>
                            <option value="succeeded">Başarılı</option>
                            <option value="failed">Başarısız</option>
                            <option value="skipped">Atlandı</option>
                        </select>
                    @endif
                </div>

                {{-- Sağ: Toplu İşlemler & Kolon Ayarları --}}
                <div class="flex items-center gap-2 justify-between lg:justify-end">
                    @if($activeTab === 'orders')
                        <button wire:click="bridgeFilteredOrders" wire:loading.attr="disabled"
                                class="inline-flex items-center justify-center px-4 py-2 text-xs font-semibold text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[40px] w-full sm:w-auto">
                            <span wire:loading.remove wire:target="bridgeFilteredOrders">Filtrelenenleri Köprüle (Max 50)</span>
                            <span wire:loading wire:target="bridgeFilteredOrders">Köprüleniyor...</span>
                        </button>
                    @elseif($activeTab === 'financial_events')
                        <button wire:click="bridgeFilteredEvents" wire:loading.attr="disabled"
                                class="inline-flex items-center justify-center px-4 py-2 text-xs font-semibold text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[40px] w-full sm:w-auto">
                            <span wire:loading.remove wire:target="bridgeFilteredEvents">Filtrelenenleri İşle (Max 50)</span>
                            <span wire:loading wire:target="bridgeFilteredEvents">İşleniyor...</span>
                        </button>
                    @endif

                    {{-- Kolon Seçici Dropdown --}}
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="inline-flex items-center gap-2 px-3 py-2 text-xs font-semibold border border-slate-200 rounded-[6px] bg-white text-slate-700 hover:bg-slate-50 transition-colors min-h-[40px]">
                            <span>Kolonlar</span>
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 mt-1 w-48 bg-white border border-slate-200 rounded-[8px] shadow-lg z-10 py-1">
                            @foreach($this->columnDefs as $col)
                                <label class="flex items-center px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 cursor-pointer">
                                    <input type="checkbox" wire:click="toggleColumn('{{ $col['name'] }}')" {{ in_array($col['name'], $visibleColumns, true) ? 'checked' : '' }} class="mr-2 rounded border-slate-300 text-slate-900 focus:ring-slate-900" />
                                    <span>{{ $col['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- Gelişmiş Tarih Filtreleri --}}
            <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100">
                <span class="text-xs font-medium text-slate-500">Tarih Aralığı:</span>
                <input type="date" wire:model.live="dateFrom" class="rounded-[6px] border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-700 focus:outline-none" />
                <span class="text-xs text-slate-400">—</span>
                <input type="date" wire:model.live="dateTo" class="rounded-[6px] border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-700 focus:outline-none" />

                <button wire:click="clearFilters" class="ml-auto text-xs text-slate-400 hover:text-slate-600 font-semibold">Filtreleri Temizle</button>
            </div>
        </div>

        {{-- ─── TAB 1: SİPARİŞLER TABLOSU ─── --}}
        @if($activeTab === 'orders')
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left border-collapse table-layout-fixed">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/50">
                            @foreach($this->columnDefs as $col)
                                @if(in_array($col['name'], $visibleColumns, true))
                                    <th class="p-3 text-xs font-semibold text-slate-600 {{ $col['sortable'] ? 'cursor-pointer select-none hover:text-slate-900' : '' }}"
                                        @if($col['sortable']) wire:click="sortTable('{{ $col['name'] }}')" @endif>
                                        <div class="flex items-center gap-1">
                                            <span>{{ $col['label'] }}</span>
                                            @if($col['sortable'] && $sortColumn === $col['name'])
                                                <span>{!! $sortDirection === 'asc' ? '▲' : '▼' !!}</span>
                                            @endif
                                        </div>
                                    </th>
                                @endif
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs text-slate-800">
                        @forelse($this->orders as $o)
                            <tr class="hover:bg-slate-50/30 transition-colors">
                                @if(in_array('id', $visibleColumns, true)) <td class="p-3 font-mono text-slate-400">{{ $o->id }}</td> @endif
                                @if(in_array('store_name', $visibleColumns, true)) <td class="p-3 font-medium text-slate-900">{{ $o->store->store_name }}</td> @endif
                                @if(in_array('order_number', $visibleColumns, true)) <td class="p-3 font-mono font-semibold">{{ $o->order_number }}</td> @endif
                                @if(in_array('customer_name', $visibleColumns, true)) <td class="p-3">{{ $o->customer_name }}</td> @endif
                                @if(in_array('ordered_at', $visibleColumns, true)) <td class="p-3 text-slate-500">{{ $o->ordered_at ? $o->ordered_at->format('d.m.Y H:i') : '-' }}</td> @endif
                                @if(in_array('status', $visibleColumns, true))
                                    <td class="p-3">
                                        @if($this->isBridgedOrder($o->id))
                                            <span class="px-2 py-0.5 text-[10px] font-semibold bg-emerald-50 text-emerald-700 rounded-[4px] border border-emerald-100">Köprülendi</span>
                                        @else
                                            <span class="px-2 py-0.5 text-[10px] font-semibold bg-amber-50 text-amber-700 rounded-[4px] border border-amber-100">Bekliyor</span>
                                        @endif
                                    </td>
                                @endif
                                @if(in_array('actions', $visibleColumns, true))
                                    <td class="p-3">
                                        @if(!$this->isBridgedOrder($o->id))
                                            <button wire:click="bridgeSingleOrder({{ $o->id }})" class="px-3 py-1.5 font-semibold text-white bg-slate-950 rounded-[4px] hover:bg-slate-800 transition-colors text-[11px] min-h-[32px]">Köprüle</button>
                                        @else
                                            <span class="text-slate-400 font-medium">İşlem Tamam</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="p-12 text-center text-slate-400">Köprülenecek sipariş kaydı bulunmamaktadır.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Siparişler Mobil Görünüm (Cards) --}}
            <div class="md:hidden divide-y divide-slate-100">
                @forelse($this->orders as $o)
                    <div class="p-4 space-y-2 text-xs">
                        <div class="flex justify-between items-center">
                            <span class="font-mono font-semibold text-slate-800">#{{ $o->order_number }}</span>
                            @if($this->isBridgedOrder($o->id))
                                <span class="px-2 py-0.5 text-[10px] font-semibold bg-emerald-50 text-emerald-700 rounded-[4px] border border-emerald-100">Köprülendi</span>
                            @else
                                <span class="px-2 py-0.5 text-[10px] font-semibold bg-amber-50 text-amber-700 rounded-[4px] border border-amber-100">Bekliyor</span>
                            @endif
                        </div>
                        <div class="text-slate-500 space-y-1">
                            <p><strong>Müşteri:</strong> {{ $o->customer_name }}</p>
                            <p><strong>Mağaza:</strong> {{ $o->store->store_name }}</p>
                            <p><strong>Tarih:</strong> {{ $o->ordered_at ? $o->ordered_at->format('d.m.Y H:i') : '-' }}</p>
                        </div>
                        @if(!$this->isBridgedOrder($o->id))
                            <div class="pt-2">
                                <button wire:click="bridgeSingleOrder({{ $o->id }})" class="w-full px-3 py-2 font-semibold text-white bg-slate-950 rounded-[4px] hover:bg-slate-800 text-center min-h-[44px]">Köprüle</button>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="p-12 text-center text-slate-400 text-xs">Köprülenecek sipariş kaydı bulunmamaktadır.</div>
                @endforelse
            </div>

            <div class="p-4 border-t border-slate-100">
                {{ $this->orders->links(data: ['pageName' => 'ordersPage']) }}
            </div>
        @endif

        {{-- ─── TAB 2: FİNANSAL OLAYLAR TABLOSU ─── --}}
        @if($activeTab === 'financial_events')
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left border-collapse table-layout-fixed">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/50">
                            @foreach($this->columnDefs as $col)
                                @if(in_array($col['name'], $visibleColumns, true))
                                    <th class="p-3 text-xs font-semibold text-slate-600 {{ $col['sortable'] ? 'cursor-pointer select-none hover:text-slate-900' : '' }}"
                                        @if($col['sortable']) wire:click="sortTable('{{ $col['name'] }}')" @endif>
                                        <div class="flex items-center gap-1">
                                            <span>{{ $col['label'] }}</span>
                                            @if($col['sortable'] && $sortColumn === $col['name'])
                                                <span>{!! $sortDirection === 'asc' ? '▲' : '▼' !!}</span>
                                            @endif
                                        </div>
                                    </th>
                                @endif
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs text-slate-800">
                        @forelse($this->events as $e)
                            <tr class="hover:bg-slate-50/30 transition-colors">
                                @if(in_array('id', $visibleColumns, true)) <td class="p-3 font-mono text-slate-400">{{ $e->id }}</td> @endif
                                @if(in_array('store_name', $visibleColumns, true)) <td class="p-3 font-medium text-slate-900">{{ $e->store->store_name }}</td> @endif
                                @if(in_array('event_type', $visibleColumns, true))
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 text-[10px] font-mono rounded bg-slate-100 text-slate-600 border border-slate-200">{{ $e->event_type }}</span>
                                    </td>
                                @endif
                                @if(in_array('amount', $visibleColumns, true)) <td class="p-3 font-mono font-semibold">₺{{ number_format($e->amount, 2, ',', '.') }}</td> @endif
                                @if(in_array('event_date', $visibleColumns, true)) <td class="p-3 text-slate-500">{{ $e->event_date ? $e->event_date->format('d.m.Y') : '-' }}</td> @endif
                                @if(in_array('status', $visibleColumns, true))
                                    <td class="p-3">
                                        @if($this->isBridgedEvent($e->id))
                                            <span class="px-2 py-0.5 text-[10px] font-semibold bg-emerald-50 text-emerald-700 rounded-[4px] border border-emerald-100">Muhasebeleşti</span>
                                        @else
                                            <span class="px-2 py-0.5 text-[10px] font-semibold bg-amber-50 text-amber-700 rounded-[4px] border border-amber-100">Bekliyor</span>
                                        @endif
                                    </td>
                                @endif
                                @if(in_array('actions', $visibleColumns, true))
                                    <td class="p-3">
                                        @if(!$this->isBridgedEvent($e->id))
                                            <button wire:click="bridgeSingleEvent({{ $e->id }})" class="px-3 py-1.5 font-semibold text-white bg-slate-950 rounded-[4px] hover:bg-slate-800 transition-colors text-[11px] min-h-[32px]">İşle</button>
                                        @else
                                            <span class="text-slate-400 font-medium">Muhasebeleşti</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="p-12 text-center text-slate-400">Muhasebeleşecek yeni finansal olay bulunmamaktadır.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Olaylar Mobil Görünüm (Cards) --}}
            <div class="md:hidden divide-y divide-slate-100">
                @forelse($this->events as $e)
                    <div class="p-4 space-y-2 text-xs">
                        <div class="flex justify-between items-center">
                            <span class="px-2 py-0.5 text-[10px] font-mono rounded bg-slate-100 text-slate-600 border border-slate-200">{{ $e->event_type }}</span>
                            @if($this->isBridgedEvent($e->id))
                                <span class="px-2 py-0.5 text-[10px] font-semibold bg-emerald-50 text-emerald-700 rounded-[4px] border border-emerald-100">Muhasebeleşti</span>
                            @else
                                <span class="px-2 py-0.5 text-[10px] font-semibold bg-amber-50 text-amber-700 rounded-[4px] border border-amber-100">Bekliyor</span>
                            @endif
                        </div>
                        <div class="text-slate-500 space-y-1">
                            <p><strong>Tutar:</strong> ₺{{ number_format($e->amount, 2, ',', '.') }}</p>
                            <p><strong>Mağaza:</strong> {{ $e->store->store_name }}</p>
                            <p><strong>Tarih:</strong> {{ $e->event_date ? $e->event_date->format('d.m.Y') : '-' }}</p>
                        </div>
                        @if(!$this->isBridgedEvent($e->id))
                            <div class="pt-2">
                                <button wire:click="bridgeSingleEvent({{ $e->id }})" class="w-full px-3 py-2 font-semibold text-white bg-slate-950 rounded-[4px] hover:bg-slate-800 text-center min-h-[44px]">İşle</button>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="p-12 text-center text-slate-400 text-xs">Muhasebeleşecek yeni finansal olay bulunmamaktadır.</div>
                @endforelse
            </div>

            <div class="p-4 border-t border-slate-100">
                {{ $this->events->links(data: ['pageName' => 'eventsPage']) }}
            </div>
        @endif

        {{-- ─── TAB 3: İŞLEM GEÇMİŞİ (RUNS) TABLOSU ─── --}}
        @if($activeTab === 'runs')
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left border-collapse table-layout-fixed">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/50">
                            @foreach($this->columnDefs as $col)
                                @if(in_array($col['name'], $visibleColumns, true))
                                    <th class="p-3 text-xs font-semibold text-slate-600 {{ $col['sortable'] ? 'cursor-pointer select-none hover:text-slate-900' : '' }}"
                                        @if($col['sortable']) wire:click="sortTable('{{ $col['name'] }}')" @endif>
                                        <div class="flex items-center gap-1">
                                            <span>{{ $col['label'] }}</span>
                                            @if($col['sortable'] && $sortColumn === $col['name'])
                                                <span>{!! $sortDirection === 'asc' ? '▲' : '▼' !!}</span>
                                            @endif
                                        </div>
                                    </th>
                                @endif
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs text-slate-800">
                        @forelse($this->runs as $r)
                            <tr class="hover:bg-slate-50/30 transition-colors">
                                @if(in_array('id', $visibleColumns, true)) <td class="p-3 font-mono text-slate-400">{{ $r->id }}</td> @endif
                                @if(in_array('bridge_type', $visibleColumns, true))
                                    <td class="p-3 uppercase font-semibold text-[10px] text-slate-500">{{ $r->bridge_type }}</td>
                                @endif
                                @if(in_array('source_key', $visibleColumns, true)) <td class="p-3 font-mono text-slate-600">{{ $r->source_key }}</td> @endif
                                @if(in_array('status', $visibleColumns, true))
                                    <td class="p-3">
                                        @if($r->isSucceeded())
                                            <span class="px-2 py-0.5 text-[10px] font-semibold bg-emerald-50 text-emerald-700 rounded-[4px] border border-emerald-100">Başarılı</span>
                                        @elseif($r->isFailed())
                                            <span class="px-2 py-0.5 text-[10px] font-semibold bg-rose-50 text-rose-700 rounded-[4px] border border-rose-100">Hatalı</span>
                                        @elseif($r->isSkipped())
                                            <span class="px-2 py-0.5 text-[10px] font-semibold bg-slate-100 text-slate-600 rounded-[4px] border border-slate-200">Atlandı</span>
                                        @else
                                            <span class="px-2 py-0.5 text-[10px] font-semibold bg-amber-50 text-amber-700 rounded-[4px] border border-amber-100">İşleniyor</span>
                                        @endif
                                    </td>
                                @endif
                                @if(in_array('error_message', $visibleColumns, true))
                                    <td class="p-3 text-rose-600 max-w-xs truncate" title="{{ $r->error_message }}">{{ $r->error_message ?: '—' }}</td>
                                @endif
                                @if(in_array('attempted_at', $visibleColumns, true)) <td class="p-3 text-slate-500">{{ $r->attempted_at ? $r->attempted_at->format('d.m.Y H:i') : '-' }}</td> @endif
                                @if(in_array('actions', $visibleColumns, true))
                                    <td class="p-3">
                                        @if($r->isRetryable())
                                            <button wire:click="retryRun({{ $r->id }})" class="px-3 py-1.5 font-semibold text-white bg-slate-950 rounded-[4px] hover:bg-slate-800 transition-colors text-[11px] min-h-[32px]">Yeniden Dene</button>
                                        @else
                                            <span class="text-slate-400 font-medium">—</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="p-12 text-center text-slate-400">İşlem geçmişi bulunmamaktadır.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Runs Mobil Görünüm (Cards) --}}
            <div class="md:hidden divide-y divide-slate-100">
                @forelse($this->runs as $r)
                    <div class="p-4 space-y-2 text-xs">
                        <div class="flex justify-between items-center">
                            <span class="uppercase font-semibold text-[10px] text-slate-500">{{ $r->bridge_type }}</span>
                            @if($r->isSucceeded())
                                <span class="px-2 py-0.5 text-[10px] font-semibold bg-emerald-50 text-emerald-700 rounded-[4px] border border-emerald-100">Başarılı</span>
                            @elseif($r->isFailed())
                                <span class="px-2 py-0.5 text-[10px] font-semibold bg-rose-50 text-rose-700 rounded-[4px] border border-rose-100">Hatalı</span>
                            @elseif($r->isSkipped())
                                <span class="px-2 py-0.5 text-[10px] font-semibold bg-slate-100 text-slate-600 rounded-[4px] border border-slate-200">Atlandı</span>
                            @endif
                        </div>
                        <div class="text-slate-500 space-y-1">
                            <p><strong>Kaynak Key:</strong> <span class="font-mono">{{ $r->source_key }}</span></p>
                            <p><strong>Tarih:</strong> {{ $r->attempted_at ? $r->attempted_at->format('d.m.Y H:i') : '-' }}</p>
                            @if($r->error_message)
                                <p class="text-rose-600"><strong>Hata:</strong> {{ $r->error_message }}</p>
                            @endif
                        </div>
                        @if($r->isRetryable())
                            <div class="pt-2">
                                <button wire:click="retryRun({{ $r->id }})" class="w-full px-3 py-2 font-semibold text-white bg-slate-950 rounded-[4px] hover:bg-slate-800 text-center min-h-[44px]">Yeniden Dene</button>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="p-12 text-center text-slate-400 text-xs">İşlem geçmişi bulunmamaktadır.</div>
                @endforelse
            </div>

            <div class="p-4 border-t border-slate-100">
                {{ $this->runs->links(data: ['pageName' => 'runsPage']) }}
            </div>
        @endif

    </section>
</div>
