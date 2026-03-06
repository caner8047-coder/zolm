<div class="space-y-4 lg:space-y-6">
    <!-- Header & Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl lg:text-2xl font-bold text-gray-900">Pazaryeri Siparişlerim</h1>
            <p class="mt-1 text-sm lg:text-base text-gray-700">Tüm detaylı operasyonel siparişlerinizi, muhasebe verilerini ve ürün satırlarını tek ekranda inceleyin.</p>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3">
            <button wire:click="exportCsv" wire:loading.attr="disabled"
                    class="min-h-[44px] inline-flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors w-full sm:w-auto">
                <svg class="-ml-1 mr-2 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Dışa Aktar (CSV)
            </button>
            <button wire:click="runSyncEngine" wire:loading.attr="disabled"
                    class="min-h-[44px] inline-flex items-center justify-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full sm:w-auto">
                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Finansal Senkronizasyon
            </button>
        </div>
    </div>

    <!-- System Messages -->
    @if(session()->has('sync_message'))
        <div class="rounded-md bg-green-50 p-4 border border-green-200">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3"><p class="text-sm font-medium text-green-800">{{ session('sync_message') }}</p></div>
            </div>
        </div>
    @endif

    @if($importMessage)
        <div class="rounded-md bg-blue-50 p-4 border border-blue-200 mb-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div class="ml-3"><p class="text-sm text-blue-700">{{ $importMessage }}</p></div>
            </div>
        </div>
    @endif

    <!-- ═══════════ ÖZET KARTLARI ═══════════ -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
        <!-- Toplam Sipariş -->
        <div class="bg-white rounded-lg shadow p-3 lg:p-4 border-l-4 border-indigo-500">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                </div>
                <div class="ml-3">
                    <p class="text-xs font-medium text-gray-500 uppercase">Toplam Sipariş</p>
                    <p class="text-lg lg:text-xl font-bold text-gray-900">{{ number_format($stats['total_orders']) }}</p>
                </div>
            </div>
        </div>

        <!-- Toplam Ciro -->
        <div class="bg-white rounded-lg shadow p-3 lg:p-4 border-l-4 border-green-500">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <div class="ml-3">
                    <p class="text-xs font-medium text-gray-500 uppercase">Toplam Ciro</p>
                    <p class="text-lg lg:text-xl font-bold text-gray-900">₺{{ number_format($stats['total_revenue'], 0, ',', '.') }}</p>
                </div>
            </div>
        </div>

        <!-- Ort. Sepet -->
        <div class="bg-white rounded-lg shadow p-3 lg:p-4 border-l-4 border-amber-500">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                    </div>
                </div>
                <div class="ml-3">
                    <p class="text-xs font-medium text-gray-500 uppercase">Ort. Sepet</p>
                    <p class="text-lg lg:text-xl font-bold text-gray-900">₺{{ number_format($stats['avg_order_value'], 0, ',', '.') }}</p>
                </div>
            </div>
        </div>

        <!-- Durum Dağılımı -->
        <div class="bg-white rounded-lg shadow p-3 lg:p-4 border-l-4 border-purple-500">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                </div>
                <div class="ml-3">
                    <p class="text-xs font-medium text-gray-500 uppercase">Durum Dağılımı</p>
                    <div class="flex items-center space-x-2 mt-1 flex-wrap">
                        <span class="inline-flex items-center text-xs"><span class="w-2 h-2 rounded-full bg-green-500 mr-1"></span>{{ $stats['delivered'] }}</span>
                        <span class="inline-flex items-center text-xs"><span class="w-2 h-2 rounded-full bg-yellow-500 mr-1"></span>{{ $stats['in_transit'] }}</span>
                        <span class="inline-flex items-center text-xs"><span class="w-2 h-2 rounded-full bg-red-500 mr-1"></span>{{ $stats['cancelled'] }}</span>
                        <span class="inline-flex items-center text-xs"><span class="w-2 h-2 rounded-full bg-orange-500 mr-1"></span>{{ $stats['returned'] }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Importer Form -->
    <div class="bg-white shadow rounded-lg mb-4 lg:mb-6">
        <div class="p-4 lg:p-6">
            <h3 class="text-base lg:text-lg leading-6 font-medium text-gray-900">Operasyonel Sipariş İçe Aktarım</h3>
            <div class="mt-2 text-sm text-gray-500">
                <p>Müşteri detayı, ürün ve varyant içeren geniş kapsamlı "Sipariş Kayıtları" Excelinizi buraya yükleyin.</p>
            </div>
            <form wire:submit.prevent="importOrders" class="mt-4 lg:mt-5 flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="w-full sm:max-w-xs">
                    <input type="file" wire:model="file" accept=".xlsx,.xls"
                           class="min-h-[44px] block w-full text-base sm:text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer border border-gray-300 rounded-md">
                </div>
                
                <div wire:loading wire:target="file" class="ml-3 text-sm text-indigo-600 font-medium">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-indigo-600 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Sunucuya alınıyor...
                </div>

                <button type="submit" wire:loading.attr="disabled" wire:target="importOrders, file"
                        class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-3 sm:py-2 border border-transparent shadow-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 min-h-[44px] transition disabled:opacity-50">
                    <span wire:loading.remove wire:target="importOrders">Yükle ve İşle</span>
                    <span wire:loading wire:target="importOrders">İşleniyor...</span>
                </button>
            </form>
            @error('file') <span class="text-red-500 text-xs mt-2 block font-medium">{{ $message }}</span> @enderror
        </div>
    </div>

    <!-- ═══════════ FİLTRELER ═══════════ -->
    <div class="bg-white shadow rounded-lg p-3 lg:p-4" x-data="{ showAdvanced: false }">
        <!-- Birincil Filtreler -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 lg:gap-4">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <input wire:model.live.debounce.500ms="search" type="text" placeholder="Sipariş / Paket No..." 
                       class="min-h-[44px] block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 text-base sm:text-sm">
            </div>

            <div>
                <input wire:model.live.debounce.500ms="searchBarcode" type="text" placeholder="Barkod / Ürün / Stok Kodu..." 
                       class="min-h-[44px] block w-full px-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-base sm:text-sm">
            </div>

            <div>
                <input wire:model.live.debounce.500ms="searchCustomer" type="text" placeholder="Müşteri / Telefon / E-Posta..." 
                       class="min-h-[44px] block w-full px-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-base sm:text-sm">
            </div>

            <div>
                <select wire:model.live="statusFilter" class="min-h-[44px] block w-full pl-3 pr-10 py-2 border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-base sm:text-sm rounded-md">
                    <option value="">Tüm Durumlar</option>
                    @foreach($uniqueStatuses as $st)
                        <option value="{{ $st }}">{{ $st }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center space-x-2">
                <button @click="showAdvanced = !showAdvanced" type="button"
                        class="min-h-[44px] flex-1 inline-flex items-center justify-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition">
                    <svg class="h-4 w-4 mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                    <span x-text="showAdvanced ? 'Gizle' : 'Gelişmiş'"></span>
                </button>
                <button wire:click="resetFilters" type="button"
                        class="min-h-[44px] inline-flex items-center justify-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md text-red-600 bg-white hover:bg-red-50 transition"
                        title="Filtreleri Temizle">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        <!-- Gelişmiş Filtreler -->
        <div x-show="showAdvanced" x-collapse class="mt-3 pt-3 border-t border-gray-200">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 lg:gap-4">
                <div>
                    <label class="block text-xs sm:text-sm font-medium text-gray-500 mb-1">Başlangıç Tarihi</label>
                    <input wire:model.live="dateFrom" type="date" 
                           class="min-h-[44px] block w-full px-3 py-2 border border-gray-300 rounded-md text-base sm:text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs sm:text-sm font-medium text-gray-500 mb-1">Bitiş Tarihi</label>
                    <input wire:model.live="dateTo" type="date" 
                           class="min-h-[44px] block w-full px-3 py-2 border border-gray-300 rounded-md text-base sm:text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs sm:text-sm font-medium text-gray-500 mb-1">Şehir</label>
                    <select wire:model.live="cityFilter" class="min-h-[44px] block w-full pl-3 pr-10 py-2 border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-base sm:text-sm rounded-md">
                        <option value="">Tüm Şehirler</option>
                        @foreach($uniqueCities as $city)
                            <option value="{{ $city }}">{{ $city }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs sm:text-sm font-medium text-gray-500 mb-1">Marka</label>
                    <select wire:model.live="brandFilter" class="min-h-[44px] block w-full pl-3 pr-10 py-2 border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-base sm:text-sm rounded-md">
                        <option value="">Tüm Markalar</option>
                        @foreach($uniqueBrands as $brand)
                            <option value="{{ $brand }}">{{ $brand }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs sm:text-sm font-medium text-gray-500 mb-1">Fatura Tipi</label>
                    <select wire:model.live="corporateFilter" class="min-h-[44px] block w-full pl-3 pr-10 py-2 border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-base sm:text-sm rounded-md">
                        <option value="">Tümü</option>
                        <option value="Evet">Kurumsal</option>
                        <option value="Hayır">Bireysel</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════ SİPARİŞ LİSTESİ ═══════════ -->
    <div x-data="{ expanded: [] }">

        @forelse($orders as $order)
            @php
                $hasFinancial = $order->financialOrders->isNotEmpty();
                $netProfit = $hasFinancial ? $order->total_net_profit : null;
                $netHakedis = $hasFinancial ? $order->total_net_hakedis : null;
                $alert = $order->financial_alert;
                $hasCost = $order->has_cost_data;
                $estMargin = $order->estimated_margin;
                $estMarginPct = $order->estimated_margin_percent;
                $estCogs = $order->estimated_cogs;
                $estCargo = $order->estimated_cargo;
                $estPackaging = $order->estimated_packaging;
                $estCommission = $order->estimated_commission;
                $estDiscount = (float) $order->items->sum('discount_amount') + (float) $order->items->sum('trendyol_discount');
                $st = mb_strtolower($order->status ?? '');
                $color = 'bg-gray-100 text-gray-800';
                if(str_contains($st, 'teslim') || str_contains($st, 'tamamlandı')) $color = 'bg-green-100 text-green-800';
                if(str_contains($st, 'iptal') || str_contains($st, 'iade')) $color = 'bg-red-100 text-red-800';
                if(str_contains($st, 'tedarik') || str_contains($st, 'kargo')) $color = 'bg-yellow-100 text-yellow-800';
            @endphp

            <!-- ══ MOBİL KART (lg altı) ══ -->
            <div class="lg:hidden bg-white shadow rounded-lg mb-3 overflow-hidden border border-gray-100">
                <!-- Kart Üst: Tıklanabilir Başlık -->
                <div class="p-3 cursor-pointer active:bg-gray-50 transition-colors"
                     @click="expanded.includes({{ $order->id }}) ? expanded = expanded.filter(i => i !== {{ $order->id }}) : expanded.push({{ $order->id }})">
                    <!-- Üst Satır: Sipariş No + Durum -->
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 flex-shrink-0 text-gray-400 transform transition-transform"
                                     :class="{ 'rotate-90 text-indigo-500': expanded.includes({{ $order->id }}) }"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                                <span class="font-semibold text-indigo-600 text-sm">{{ $order->order_number }}</span>
                            </div>
                            <div class="text-xs text-gray-500 mt-0.5 ml-6">{{ $order->order_date ? $order->order_date->format('d M Y - H:i') : '-' }}</div>
                        </div>
                        <div class="flex flex-col items-end gap-1 flex-shrink-0">
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $color }}">
                                {{ $order->status ?? 'Durum Yok' }}
                            </span>
                            @if($alert['type'])
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full border {{ $alert['color'] }} flex items-center gap-1">
                                    @if($alert['type'] === 'iade')
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                    @elseif($alert['type'] === 'ceza')
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                    @endif
                                    {{ $alert['label'] }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Orta: Müşteri + Ciro Bilgisi -->
                    <div class="mt-2 ml-6 flex items-center justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="font-medium text-gray-900 text-sm truncate">{{ $order->customer_name ?? 'Bilinmiyor' }}</div>
                            <div class="text-xs text-gray-500 flex items-center gap-1 mt-0.5">
                                <svg class="w-3 h-3 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                <span class="truncate">{{ $order->customer_city }}{{ $order->customer_district ? ', ' . $order->customer_district : '' }}</span>
                            </div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="font-bold text-gray-900 text-sm">₺{{ number_format($order->total_gross_amount, 2, ',', '.') }}</div>
                            <div class="text-xs text-indigo-600 font-medium">{{ $order->items->count() }} Ürün</div>
                        </div>
                    </div>

                    <!-- Alt: Kargo + Muhasebe + ROI (varsa) -->
                    <div class="mt-2 ml-6 flex items-center justify-between gap-2">
                        <span class="bg-gray-100 text-gray-700 text-[11px] px-2 py-0.5 rounded border border-gray-200">{{ $order->cargo_company ?? '-' }}</span>
                        @if($order->is_corporate_invoice === 'Evet')
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-blue-100 text-blue-800">Kurumsal</span>
                        @endif
                        <div class="flex items-center gap-2">
                            @if($hasCost && $estMargin !== null)
                                <span class="text-[11px] font-bold {{ $estMargin >= 0 ? 'text-emerald-600' : 'text-red-600' }} bg-emerald-50 px-1.5 py-0.5 rounded border {{ $estMargin >= 0 ? 'border-emerald-200' : 'border-red-200 bg-red-50' }}">
                                    <svg class="w-3 h-3 inline -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                    {{ $estMargin >= 0 ? '+' : '' }}₺{{ number_format($estMargin, 0, ',', '.') }}
                                    <span class="text-[10px] font-medium {{ $estMarginPct >= 20 ? 'text-emerald-500' : ($estMarginPct >= 0 ? 'text-amber-500' : 'text-red-500') }}">({{ number_format($estMarginPct, 0) }}%)</span>
                                </span>
                            @endif
                            @if($hasFinancial)
                                <span class="text-xs font-bold {{ $netProfit >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $netProfit >= 0 ? '+' : '' }}₺{{ number_format($netProfit, 2, ',', '.') }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Mobil Detay Paneli -->
                <div x-show="expanded.includes({{ $order->id }})"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-cloak
                     class="border-t border-gray-200 bg-slate-50/50">
                    <div class="p-3 space-y-3">
                        @include('livewire.partials.marketplace-order-detail', ['order' => $order, 'hasFinancial' => $hasFinancial, 'netProfit' => $netProfit])
                    </div>
                </div>
            </div>

            <!-- ══ DESKTOP TABLO SATIRI (lg ve üstü) ══ -->
            {{-- Aşağıdaki desktop satırları sadece lg+ ekranda gösterilir --}}
        @empty
            <!-- Boş Durum (Mobil) -->
            <div class="lg:hidden bg-white shadow rounded-lg p-8 text-center">
                <svg class="h-12 w-12 text-gray-400 mb-3 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
                <span class="text-base font-medium text-gray-900 block">Kayıt Bulunamadı</span>
                <span class="text-sm text-gray-500 mt-1 block">Lütfen detaylı sipariş Excel'inizi yukarıdan yükleyin.</span>
            </div>
        @endforelse

        <!-- ══ DESKTOP TABLO (lg ve üstü) ══ -->
        <div class="hidden lg:block bg-white shadow rounded-lg overflow-hidden">
            <div class="overflow-x-auto w-full">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="relative px-3 py-3 w-10"><span class="sr-only">Toggle</span></th>
                            <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sipariş No & Tarih</th>
                            <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Müşteri</th>
                            <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lojistik</th>
                            <th scope="col" class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ciro</th>
                            <th scope="col" class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Muhasebe</th>
                            <th scope="col" class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">ROI</th>
                            <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Durum</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 text-sm">
                        @forelse($orders as $order)
                            @php
                                $hasFinancial = $order->financialOrders->isNotEmpty();
                                $netProfit = $hasFinancial ? $order->total_net_profit : null;
                                $netHakedis = $hasFinancial ? $order->total_net_hakedis : null;
                                $alert = $order->financial_alert;
                                $hasCost = $order->has_cost_data;
                                $estMargin = $order->estimated_margin;
                                $estMarginPct = $order->estimated_margin_percent;
                                $estCogs = $order->estimated_cogs;
                                $estCargo = $order->estimated_cargo;
                                $estPackaging = $order->estimated_packaging;
                                $estCommission = $order->estimated_commission;
                                $estDiscount = (float) $order->items->sum('discount_amount') + (float) $order->items->sum('trendyol_discount');
                                $st = mb_strtolower($order->status ?? '');
                                $color = 'bg-gray-100 text-gray-800';
                                if(str_contains($st, 'teslim') || str_contains($st, 'tamamlandı')) $color = 'bg-green-100 text-green-800';
                                if(str_contains($st, 'iptal') || str_contains($st, 'iade')) $color = 'bg-red-100 text-red-800';
                                if(str_contains($st, 'tedarik') || str_contains($st, 'kargo')) $color = 'bg-yellow-100 text-yellow-800';
                            @endphp
                            <!-- MASTER ROW -->
                            <tr class="hover:bg-gray-50 cursor-pointer transition-colors" @click="expanded.includes({{ $order->id }}) ? expanded = expanded.filter(i => i !== {{ $order->id }}) : expanded.push({{ $order->id }})">
                                <td class="px-3 py-4 whitespace-nowrap text-center text-gray-500">
                                    <svg class="w-5 h-5 inline-block transform transition-transform"
                                         :class="{ 'rotate-90 text-indigo-500': expanded.includes({{ $order->id }}) }"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <div class="font-medium text-indigo-600">{{ $order->order_number }}</div>
                                    @if($order->package_number)
                                        <div class="text-gray-400 text-[10px] font-mono">PKT: {{ $order->package_number }}</div>
                                    @endif
                                    <div class="text-gray-500 text-xs mt-0.5">{{ $order->order_date ? $order->order_date->format('d M Y - H:i') : '-' }}</div>
                                </td>
                                <td class="px-3 py-4">
                                    <div class="font-medium text-gray-900">{{ $order->customer_name ?? 'Bilinmiyor' }}</div>
                                    <div class="text-gray-500 flex items-center space-x-1 mt-0.5 text-xs">
                                        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        <span>{{ $order->customer_city }}{{ $order->customer_district ? ', ' . $order->customer_district : '' }}</span>
                                    </div>
                                    @if($order->is_corporate_invoice === 'Evet')
                                        <span class="mt-0.5 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-blue-100 text-blue-800">Kurumsal</span>
                                    @endif
                                </td>
                                <td class="px-3 py-4">
                                    <span class="bg-gray-100 text-gray-800 text-xs px-2 py-0.5 rounded border border-gray-200">{{ $order->cargo_company ?? '-' }}</span>
                                    <div class="text-gray-500 text-xs mt-1 truncate max-w-[140px]" title="{{ $order->tracking_number }}">
                                        {{ $order->tracking_number ?? 'Takip No Yok' }}
                                    </div>
                                    @if($order->cargo_delivery_date)
                                        <div class="text-[10px] text-gray-400 mt-0.5">Kargoya: {{ $order->cargo_delivery_date->format('d/m') }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-right">
                                    <div class="font-semibold text-gray-900">₺{{ number_format($order->total_gross_amount, 2, ',', '.') }}</div>
                                    @if($order->total_discount > 0)
                                        <div class="text-red-500 text-xs">-₺{{ number_format($order->total_discount, 2, ',', '.') }}</div>
                                    @endif
                                    <div class="text-indigo-600 text-xs font-semibold mt-0.5">
                                        {{ $order->items->count() }} Ürün
                                    </div>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-right">
                                    @if($hasFinancial)
                                        <div class="text-xs text-gray-500">Hakediş</div>
                                        <div class="font-semibold text-gray-900 text-sm">₺{{ number_format($netHakedis, 2, ',', '.') }}</div>
                                        <div class="mt-0.5 {{ $netProfit >= 0 ? 'text-green-600' : 'text-red-600' }} text-xs font-bold">
                                            {{ $netProfit >= 0 ? '+' : '' }}₺{{ number_format($netProfit, 2, ',', '.') }}
                                        </div>
                                        @if($alert['type'])
                                            <span class="mt-1 inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-[10px] font-bold border {{ $alert['color'] }}">
                                                @if($alert['type'] === 'iade')
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                                @elseif($alert['type'] === 'ceza')
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                                @else
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                                @endif
                                                {{ $alert['label'] }}
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-xs text-gray-400 italic">Veri yok</span>
                                    @endif
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-right">
                                    @if($hasCost && $estMargin !== null)
                                        <div class="text-[10px] text-gray-500">Maliyet</div>
                                        <div class="text-xs text-red-500">₺{{ number_format($estCogs, 0, ',', '.') }}</div>
                                        @if($estDiscount > 0)
                                            <div class="text-[10px] text-rose-500">İnd: ₺{{ number_format($estDiscount, 0, ',', '.') }}</div>
                                        @endif
                                        @if($estCargo > 0)
                                            <div class="text-[10px] text-orange-500">Kargo: ₺{{ number_format($estCargo, 0, ',', '.') }}</div>
                                        @endif
                                        @if($estCommission > 0)
                                            <div class="text-[10px] text-rose-500">Kom: ₺{{ number_format($estCommission, 0, ',', '.') }}</div>
                                        @endif
                                        <div class="mt-0.5 text-xs font-bold {{ $estMargin >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                            {{ $estMargin >= 0 ? '+' : '' }}₺{{ number_format($estMargin, 0, ',', '.') }}
                                            <span class="text-[10px] font-medium {{ $estMarginPct >= 20 ? 'text-emerald-500' : ($estMarginPct >= 0 ? 'text-amber-500' : 'text-red-500') }}">(%{{ number_format($estMarginPct, 0) }})</span>
                                        </div>
                                    @else
                                        <span class="text-[10px] text-gray-400 italic">Maliyet yok</span>
                                    @endif
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $color }}">
                                        {{ $order->status ?? 'Durum Yok' }}
                                    </span>
                                    @if($order->alt_delivery_status)
                                        <div class="text-[10px] text-gray-400 mt-0.5 truncate max-w-[100px]" title="{{ $order->alt_delivery_status }}">{{ $order->alt_delivery_status }}</div>
                                    @endif
                                </td>
                            </tr>

                            <!-- ═══════════ DETAY PANELİ (Desktop) ═══════════ -->
                            <tr x-show="expanded.includes({{ $order->id }})" class="bg-slate-50/50"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 -translate-y-2"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                x-cloak>
                                <td colspan="8" class="p-0 border-b border-gray-200">
                                    <div class="p-4 pl-14 space-y-4">
                                        @include('livewire.partials.marketplace-order-detail', ['order' => $order, 'hasFinancial' => $hasFinancial, 'netProfit' => $netProfit])
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-10 text-center">
                                    <div class="flex flex-col items-center">
                                        <svg class="h-12 w-12 text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                        </svg>
                                        <span class="text-base font-medium text-gray-900">Kayıt Bulunamadı</span>
                                        <span class="text-sm text-gray-500 mt-1">Lütfen detaylı sipariş Excel'inizi yukarıdan yükleyin.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        @if($orders->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 bg-white rounded-b-lg sm:px-6 mt-0 lg:mt-0">
                {{ $orders->links() }}
            </div>
        @endif
    </div>

</div>
