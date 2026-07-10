@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $summary = $this->executiveSummary;
@endphp

<div class="w-full space-y-4 lg:space-y-6">
    {{-- Üst Yönetim Raporları Başlık --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                Analitik & Finansal Raporlar
            </div>
            <h1 class="mt-3 text-xl font-bold tracking-tight text-slate-900 lg:text-2xl">Yönetim Raporları</h1>
            <p class="text-sm text-slate-500">
                Şirketinizin anlık nakit akışı, envanter değeri, alacak/borç yaşlandırma ve mali durumunu tek bir ekrandan inceleyin.
            </p>
        </div>
    </div>

    {{-- Mesaj/Hata Panelleri --}}
    @if($message !== '')
        <div class="rounded-[10px] border p-4 text-sm {{ $messageType === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
            {{ $message }}
        </div>
    @endif

    {{-- KPI Kartları (Executive Summary) --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 lg:gap-4">
        {{-- Nakit Mevcudu --}}
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block">Kasa & Banka</span>
            <span class="mt-2 text-lg lg:text-xl font-bold text-slate-900 block font-mono">
                {{ $formatMoney($summary['cash_balance'] ?? 0) }}
            </span>
        </div>

        {{-- Açık Alacak --}}
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block">Açık Alacaklar</span>
            <span class="mt-2 text-lg lg:text-xl font-bold text-emerald-700 block font-mono">
                {{ $formatMoney($summary['total_open_receivables'] ?? 0) }}
            </span>
        </div>

        {{-- Açık Borç --}}
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block">Açık Borçlar</span>
            <span class="mt-2 text-lg lg:text-xl font-bold text-rose-700 block font-mono">
                {{ $formatMoney($summary['total_open_payables'] ?? 0) }}
            </span>
        </div>

        {{-- 30 Gün Nakit Projeksiyonu --}}
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block">Nakit Projeksiyon (30g)</span>
            <span class="mt-2 text-lg lg:text-xl font-bold text-slate-900 block font-mono">
                {{ $formatMoney($summary['projected_closing_cash'] ?? 0) }}
            </span>
        </div>

        {{-- Stok Değeri --}}
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block">Envanter Değeri</span>
            <span class="mt-2 text-lg lg:text-xl font-bold text-slate-900 block font-mono">
                {{ $formatMoney($summary['inventory_value'] ?? 0) }}
            </span>
        </div>

        {{-- Net Sonuç --}}
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block">Dönem Net Kar/Zarar</span>
            <span class="mt-2 text-lg lg:text-xl font-bold block font-mono {{ ($summary['net_profit_loss'] ?? 0) >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                {{ $formatMoney($summary['net_profit_loss'] ?? 0) }}
            </span>
        </div>
    </div>

    {{-- Ana Rapor Section --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-4 lg:space-y-6">

        {{-- Rapor Tipi Seçimi Tab Bar ve Kolon Toggle --}}
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between border-b border-slate-200 pb-4 gap-4">

            {{-- Tab Menüsü --}}
            <nav class="flex flex-wrap gap-1.5" aria-label="Tabs">
                <button wire:click="$set('reportType', 'executive')" class="px-3 py-2 text-xs font-medium rounded-[6px] border min-h-[44px] transition-colors {{ $reportType === 'executive' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
                    Yönetici Özeti
                </button>
                <button wire:click="$set('reportType', 'receivables_aging')" class="px-3 py-2 text-xs font-medium rounded-[6px] border min-h-[44px] transition-colors {{ $reportType === 'receivables_aging' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
                    Alacak Yaşlandırma
                </button>
                <button wire:click="$set('reportType', 'payables_aging')" class="px-3 py-2 text-xs font-medium rounded-[6px] border min-h-[44px] transition-colors {{ $reportType === 'payables_aging' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
                    Borç Yaşlandırma
                </button>
                <button wire:click="$set('reportType', 'cash_flow')" class="px-3 py-2 text-xs font-medium rounded-[6px] border min-h-[44px] transition-colors {{ $reportType === 'cash_flow' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
                    Nakit Akışı (30g)
                </button>
                <button wire:click="$set('reportType', 'income_expense')" class="px-3 py-2 text-xs font-medium rounded-[6px] border min-h-[44px] transition-colors {{ $reportType === 'income_expense' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
                    Gelir / Gider Özeti
                </button>
                <button wire:click="$set('reportType', 'stock_inventory')" class="px-3 py-2 text-xs font-medium rounded-[6px] border min-h-[44px] transition-colors {{ $reportType === 'stock_inventory' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
                    Stok Envanter Değeri
                </button>
                <button wire:click="$set('reportType', 'party_balances')" class="px-3 py-2 text-xs font-medium rounded-[6px] border min-h-[44px] transition-colors {{ $reportType === 'party_balances' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
                    Cari Bakiyeler
                </button>
                <button wire:click="$set('reportType', 'trial_balance')" class="px-3 py-2 text-xs font-medium rounded-[6px] border min-h-[44px] transition-colors {{ $reportType === 'trial_balance' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
                    Mizan
                </button>
                <button wire:click="$set('reportType', 'balance_sheet')" class="px-3 py-2 text-xs font-medium rounded-[6px] border min-h-[44px] transition-colors {{ $reportType === 'balance_sheet' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
                    Bilanço
                </button>
            </nav>

            {{-- Kolon Özelleştirme --}}
            @if($reportType !== 'executive')
                <div class="relative shrink-0" x-data="{ open: false }">
                    <button @click="open = !open" class="w-full lg:w-auto inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 border border-slate-200 rounded-[6px] transition-colors min-h-[44px]">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                        </svg>
                        Kolonlar
                    </button>
                    <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-56 rounded-[8px] border border-slate-200 bg-white p-2 shadow-lg z-50 space-y-1" x-cloak>
                        @foreach($this->columnDefs as $colKey => $colLabel)
                            <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-slate-50 rounded cursor-pointer text-xs font-medium text-slate-700">
                                <input type="checkbox" wire:click="toggleColumn('{{ $colKey }}')" {{ in_array($colKey, $visibleColumns, true) ? 'checked' : '' }} class="rounded border-slate-300 text-slate-900 focus:ring-slate-900" />
                                <span>{{ $colLabel }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Filtre Paneli --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Yasal Birlik</label>
                <select wire:model.live="legalEntityId" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                    <option value="">Tüm Şirketler</option>
                    @foreach($this->legalEntities as $le)
                        <option value="{{ $le->id }}">{{ $le->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Müşteri / Tedarikçi</label>
                <select wire:model.live="partyId" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                    <option value="">Tüm Cariler</option>
                    @foreach($this->parties as $party)
                        <option value="{{ $party->id }}">{{ $party->display_name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Depo</label>
                <select wire:model.live="warehouseId" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                    <option value="">Tüm Depolar</option>
                    @foreach($this->warehouses as $wh)
                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Başlangıç</label>
                    <input type="date" wire:model.live="dateFrom" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-2 py-2 text-xs sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Bitiş</label>
                    <input type="date" wire:model.live="dateTo" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-2 py-2 text-xs sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                </div>
            </div>

            <div class="flex gap-2">
                <button wire:click="clearFilters" class="w-full inline-flex items-center justify-center px-3 py-2 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-200 rounded-[6px] transition-colors min-h-[44px]">
                    Temizle
                </button>
                <button wire:click="refreshReport" class="w-full inline-flex items-center justify-center px-3 py-2 text-xs font-semibold text-white bg-slate-900 hover:bg-slate-800 rounded-[6px] transition-colors min-h-[44px]">
                    Yenile
                </button>
            </div>
        </div>

        {{-- Rapor İçerik Alanı --}}
        <div class="border-t border-slate-100 pt-4">

            {{-- 1. YÖNETİCİ ÖZETİ (EXECUTIVE SUMMARY) --}}
            @if($reportType === 'executive')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Finansal Sağlık & Nakit Akış Bilgisi --}}
                    <div class="rounded-[8px] border border-slate-200 p-5 space-y-4">
                        <h3 class="text-sm font-bold text-slate-900">30 Günlük Tahmini Nakit Akışı</h3>
                        <p class="text-xs text-slate-500">Önümüzdeki 30 gün içinde vadesi dolacak alacaklar ve borçlar dikkate alınarak nakit tahmini yapılmıştır.</p>

                        <div class="space-y-2 font-mono text-xs text-slate-700">
                            <div class="flex justify-between border-b pb-1">
                                <span>Açılış Nakit Bakiyesi</span>
                                <span>{{ $formatMoney($summary['cash_balance'] ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between border-b pb-1 text-emerald-700">
                                <span>(+) Beklenen Tahsilatlar</span>
                                <span>+ {{ $formatMoney($summary['total_open_receivables'] ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between border-b pb-1 text-rose-700">
                                <span>(-) Beklenen Ödemeler</span>
                                <span>- {{ $formatMoney($summary['total_open_payables'] ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between font-bold text-slate-900 text-sm pt-1">
                                <span>Kapanış Nakit Bakiyesi</span>
                                <span>{{ $formatMoney($summary['projected_closing_cash'] ?? 0) }}</span>
                            </div>
                        </div>

                        {{-- Basit Progress Bar görselleştirmesi --}}
                        @php
                            $in = (float)($summary['total_open_receivables'] ?? 0);
                            $out = (float)($summary['total_open_payables'] ?? 0);
                            $tot = $in + $out;
                            $inPct = $tot > 0 ? round(($in / $tot) * 100) : 50;
                        @endphp
                        <div class="space-y-1">
                            <div class="flex justify-between text-[11px] font-semibold text-slate-500">
                                <span>Tahsilatlar (%{{ $inPct }})</span>
                                <span>Ödemeler (%{{ 100 - $inPct }})</span>
                            </div>
                            <div class="w-full bg-rose-200 h-2 rounded-full overflow-hidden flex">
                                <div class="bg-emerald-500 h-full" style="width: {{ $inPct }}%"></div>
                            </div>
                        </div>
                    </div>

                    {{-- Envanter & Cari Bilgiler --}}
                    <div class="rounded-[8px] border border-slate-200 p-5 space-y-4">
                        <h3 class="text-sm font-bold text-slate-900">Envanter & Operasyon Durumu</h3>
                        <p class="text-xs text-slate-500">Mevcut aktif stok bakiyeleri ve ticari faaliyet gösterdiğiniz cari hesap durumları.</p>

                        <div class="grid grid-cols-2 gap-4 text-center">
                            <div class="bg-slate-50 p-3 rounded border">
                                <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block">Toplam Stok Değeri</span>
                                <span class="text-base font-bold text-slate-900 block mt-1 font-mono">
                                    {{ $formatMoney($summary['inventory_value'] ?? 0) }}
                                </span>
                            </div>
                            <div class="bg-slate-50 p-3 rounded border">
                                <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block">Aktif Cari Sayısı</span>
                                <span class="text-base font-bold text-slate-900 block mt-1 font-mono">
                                    {{ $summary['active_parties'] ?? 0 }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

            {{-- 2. DİĞER DETAYLI TABLOLU RAPORLAR --}}
            @else
                @if(empty($this->reportData))
                    <div class="text-center p-8 text-slate-400 text-xs">
                        Seçilen filtrelere uygun rapor verisi bulunamadı veya tüm değerler sıfırdır.
                    </div>
                @else
                    {{-- DESKTOP TABLO GÖRÜNÜMÜ --}}
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full border-collapse text-left text-xs text-slate-600 min-w-[700px]">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50 text-slate-500 font-semibold uppercase tracking-wider">
                                    @foreach($this->columnDefs as $colKey => $colLabel)
                                        @if(in_array($colKey, $visibleColumns, true))
                                            <th class="p-3 cursor-pointer select-none hover:text-slate-800" wire:click="sortTable('{{ $colKey }}')">
                                                <div class="flex items-center gap-1">
                                                    <span>{{ $colLabel }}</span>
                                                    @if($sortColumn === $colKey)
                                                        <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                                    @endif
                                                </div>
                                            </th>
                                        @endif
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-mono">
                                {{-- A. Yaşlandırma Raporları (Tek Satır Özet veya Detaylar) --}}
                                @if($reportType === 'receivables_aging' || $reportType === 'payables_aging')
                                    <tr class="hover:bg-slate-50/50">
                                        @foreach($this->columnDefs as $colKey => $colLabel)
                                            @if(in_array($colKey, $visibleColumns, true))
                                                <td class="p-3 text-slate-900 font-bold">
                                                    @if($colKey === 'count')
                                                        {{ $this->reportData['count'] ?? 0 }}
                                                    @else
                                                        {{ $formatMoney($this->reportData[$colKey] ?? 0) }}
                                                    @endif
                                                </td>
                                            @endif
                                        @endforeach
                                    </tr>

                                {{-- B. Daily Rows (Nakit Akışı) --}}
                                @elseif($reportType === 'cash_flow')
                                    @foreach($this->reportData['daily_rows'] ?? [] as $row)
                                        <tr class="hover:bg-slate-50/50">
                                            @foreach($this->columnDefs as $colKey => $colLabel)
                                                @if(in_array($colKey, $visibleColumns, true))
                                                    <td class="p-3">
                                                        @if($colKey === 'date')
                                                            <span class="font-sans text-slate-700">{{ $row['date'] }}</span>
                                                        @elseif($colKey === 'net_flow')
                                                            <span class="{{ $row['net_flow'] >= 0 ? 'text-emerald-700 font-bold' : 'text-rose-700 font-bold' }}">
                                                                {{ $row['net_flow'] >= 0 ? '+' : '' }}{{ $formatMoney($row['net_flow']) }}
                                                            </span>
                                                        @else
                                                            {{ $formatMoney($row[$colKey] ?? 0) }}
                                                        @endif
                                                    </td>
                                                @endif
                                            @endforeach
                                        </tr>
                                    @endforeach

                                {{-- C. Gelir Gider & Stok & Mizan & Bilanço --}}
                                @elseif(in_array($reportType, ['income_expense', 'stock_inventory', 'trial_balance', 'balance_sheet'], true))
                                    @php
                                        $rows = $this->reportData['rows'] ?? $this->reportData;
                                        // Bilanço için array yapısını eşleştirelim
                                        if ($reportType === 'balance_sheet') {
                                            $rows = array_merge($this->reportData['assets'] ?? [], $this->reportData['liabilities_and_equity'] ?? []);
                                        }
                                    @endphp
                                    @foreach($rows as $row)
                                        <tr class="hover:bg-slate-50/50">
                                            @foreach($this->columnDefs as $colKey => $colLabel)
                                                @if(in_array($colKey, $visibleColumns, true))
                                                    <td class="p-3 text-slate-800">
                                                        @if(in_array($colKey, ['amount', 'inventory_value', 'debit', 'credit', 'debit_balance', 'credit_balance', 'balance', 'unit_cost'], true))
                                                            {{ $formatMoney($row[$colKey] ?? 0) }}
                                                        @elseif($colKey === 'status')
                                                            <span class="px-2 py-0.5 rounded text-[10px] font-sans font-bold {{ $row['status'] === 'critical' ? 'bg-amber-100 text-amber-800' : ($row['status'] === 'out_of_stock' ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800') }}">
                                                                {{ $row['status'] === 'critical' ? 'Kritik' : ($row['status'] === 'out_of_stock' ? 'Tükendi' : 'Stokta') }}
                                                            </span>
                                                        @else
                                                            <span class="font-sans">{{ $row[$colKey] ?? '' }}</span>
                                                        @endif
                                                    </td>
                                                @endif
                                            @endforeach
                                        </tr>
                                    @endforeach

                                {{-- D. Cari Bakiyeler Raporu --}}
                                @elseif($reportType === 'party_balances')
                                    @php
                                        $debtors = $this->reportData['top_debtors'] ?? [];
                                        $creditors = $this->reportData['top_creditors'] ?? [];
                                    @endphp

                                    {{-- Borçlular --}}
                                    @if(count($debtors) > 0)
                                        <tr class="bg-slate-50 font-bold"><td colspan="2" class="p-3 font-sans text-slate-800">Alacaklı Olduğumuz Cariler (Borçlular)</td></tr>
                                        @foreach($debtors as $row)
                                            <tr class="hover:bg-slate-50/50">
                                                <td class="p-3 font-sans">{{ $row['party_name'] }}</td>
                                                <td class="p-3 font-mono text-emerald-700 font-bold text-right">{{ $formatMoney($row['balance']) }}</td>
                                            </tr>
                                        @endforeach
                                    @endif

                                    {{-- Alacaklılar --}}
                                    @if(count($creditors) > 0)
                                        <tr class="bg-slate-50 font-bold"><td colspan="2" class="p-3 font-sans text-slate-800">Borçlu Olduğumuz Cariler (Alacaklılar)</td></tr>
                                        @foreach($creditors as $row)
                                            <tr class="hover:bg-slate-50/50">
                                                <td class="p-3 font-sans">{{ $row['party_name'] }}</td>
                                                <td class="p-3 font-mono text-rose-700 font-bold text-right">{{ $formatMoney($row['balance']) }}</td>
                                            </tr>
                                        @endforeach
                                    @endif
                                @endif
                            </tbody>
                        </table>
                    </div>

                    {{-- MOBİL KART GÖRÜNÜMÜ (md:hidden) --}}
                    <div class="md:hidden space-y-3">
                        @if($reportType === 'receivables_aging' || $reportType === 'payables_aging')
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50 p-4 space-y-2 text-xs font-mono">
                                @foreach($this->columnDefs as $colKey => $colLabel)
                                    @if(in_array($colKey, $visibleColumns, true))
                                        <div class="flex justify-between border-b pb-1">
                                            <span class="font-sans text-slate-500 font-semibold">{{ $colLabel }}</span>
                                            <span class="font-bold text-slate-900">
                                                @if($colKey === 'count')
                                                    {{ $this->reportData['count'] ?? 0 }}
                                                @else
                                                    {{ $formatMoney($this->reportData[$colKey] ?? 0) }}
                                                @endif
                                            </span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>

                        @elseif($reportType === 'cash_flow')
                            @foreach($this->reportData['daily_rows'] ?? [] as $row)
                                <div class="rounded-[8px] border border-slate-200 bg-slate-50 p-3 space-y-1.5 text-xs font-mono">
                                    <div class="flex justify-between font-bold border-b pb-1 font-sans text-slate-800">
                                        <span>Tarih</span>
                                        <span>{{ $row['date'] }}</span>
                                    </div>
                                    @foreach($this->columnDefs as $colKey => $colLabel)
                                        @if($colKey !== 'date' && in_array($colKey, $visibleColumns, true))
                                            <div class="flex justify-between">
                                                <span class="font-sans text-slate-500">{{ $colLabel }}</span>
                                                <span class="{{ $colKey === 'net_flow' ? ($row['net_flow'] >= 0 ? 'text-emerald-700 font-bold' : 'text-rose-700 font-bold') : 'text-slate-700' }}">
                                                    @if($colKey === 'net_flow')
                                                        {{ $row['net_flow'] >= 0 ? '+' : '' }}{{ $formatMoney($row['net_flow']) }}
                                                    @else
                                                        {{ $formatMoney($row[$colKey] ?? 0) }}
                                                    @endif
                                                </span>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endforeach

                        @elseif(in_array($reportType, ['income_expense', 'stock_inventory', 'trial_balance', 'balance_sheet'], true))
                            @php
                                $rows = $this->reportData['rows'] ?? $this->reportData;
                                if ($reportType === 'balance_sheet') {
                                    $rows = array_merge($this->reportData['assets'] ?? [], $this->reportData['liabilities_and_equity'] ?? []);
                                }
                            @endphp
                            @foreach($rows as $row)
                                <div class="rounded-[8px] border border-slate-200 bg-slate-50 p-3 space-y-1.5 text-xs font-mono">
                                    @foreach($this->columnDefs as $colKey => $colLabel)
                                        @if(in_array($colKey, $visibleColumns, true))
                                            <div class="flex justify-between border-b pb-1 last:border-0 last:pb-0">
                                                <span class="font-sans text-slate-500 font-semibold">{{ $colLabel }}</span>
                                                <span class="text-slate-800 font-bold">
                                                    @if(in_array($colKey, ['amount', 'inventory_value', 'debit', 'credit', 'debit_balance', 'credit_balance', 'balance', 'unit_cost'], true))
                                                        {{ $formatMoney($row[$colKey] ?? 0) }}
                                                    @elseif($colKey === 'status')
                                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-sans font-bold {{ $row['status'] === 'critical' ? 'bg-amber-100 text-amber-800' : ($row['status'] === 'out_of_stock' ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800') }}">
                                                            {{ $row['status'] === 'critical' ? 'Kritik' : ($row['status'] === 'out_of_stock' ? 'Tükendi' : 'Stokta') }}
                                                        </span>
                                                    @else
                                                        <span class="font-sans">{{ $row[$colKey] ?? '' }}</span>
                                                    @endif
                                                </span>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endforeach

                        @elseif($reportType === 'party_balances')
                            @php
                                $debtors = $this->reportData['top_debtors'] ?? [];
                                $creditors = $this->reportData['top_creditors'] ?? [];
                            @endphp
                            @if(count($debtors) > 0)
                                <div class="text-[11px] font-bold text-slate-800 mt-2 uppercase tracking-wide">Alacaklı Olduğumuz Cariler</div>
                                @foreach($debtors as $row)
                                    <div class="rounded-[8px] border border-slate-200 bg-slate-50 p-3 flex justify-between text-xs">
                                        <span class="font-semibold text-slate-700">{{ $row['party_name'] }}</span>
                                        <span class="font-mono font-bold text-emerald-700">{{ $formatMoney($row['balance']) }}</span>
                                    </div>
                                @endforeach
                            @endif
                            @if(count($creditors) > 0)
                                <div class="text-[11px] font-bold text-slate-800 mt-2 uppercase tracking-wide">Borçlu Olduğumuz Cariler</div>
                                @foreach($creditors as $row)
                                    <div class="rounded-[8px] border border-slate-200 bg-slate-50 p-3 flex justify-between text-xs">
                                        <span class="font-semibold text-slate-700">{{ $row['party_name'] }}</span>
                                        <span class="font-mono font-bold text-rose-700">{{ $formatMoney($row['balance']) }}</span>
                                    </div>
                                @endforeach
                            @endif
                        @endif
                    </div>
                @endif
            @endif

        </div>
    </section>
</div>
