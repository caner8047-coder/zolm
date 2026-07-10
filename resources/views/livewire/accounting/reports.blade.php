@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
@endphp

<div class="w-full space-y-4 lg:space-y-6">
    {{-- Mesaj Paneli --}}
    @if($message !== '')
        <div class="rounded-[8px] border p-4 text-sm {{ $messageType === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
            {{ $message }}
        </div>
    @endif

    {{-- Üst Section --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                    Finansal Tablolar
                </div>
                <h1 class="mt-3 text-xl font-semibold tracking-tight text-slate-950 lg:text-2xl">Finansal Raporlar & Mizan</h1>
                <p class="mt-2 text-sm text-slate-500">
                    Mizan (Geçici Mizan), Bilanço ve Gelir Tablosu gibi temel finansal raporları tarih filtreleriyle hazırlayıp anında Excel formatında indirin.
                </p>
            </div>
            @if(!empty($reportData))
                <div class="shrink-0">
                    <button wire:click="exportExcel" class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[44px]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Excel Olarak İndir
                    </button>
                </div>
            @endif
        </div>
    </section>

    {{-- Filtre Kontrol Barı --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Rapor Tipi</label>
                <select wire:model="reportType" wire:change="runReport" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                    <option value="trial_balance">Geçici Mizan (Trial Balance)</option>
                    <option value="balance_sheet">Bilanço (Balance Sheet)</option>
                    <option value="income_statement">Gelir Tablosu (P&L Summary)</option>
                </select>
            </div>

            @if($reportType !== 'balance_sheet')
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Başlangıç Tarihi</label>
                    <input type="date" wire:model="dateFrom" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                </div>
            @endif

            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Bitiş / Rapor Tarihi</label>
                <input type="date" wire:model="dateTo" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
            </div>

            <div>
                <button wire:click="runReport" class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-200 rounded-[6px] transition-colors min-h-[44px]">
                    Raporu Yenile
                </button>
            </div>
        </div>
    </section>

    {{-- Rapor İçeriği --}}
    @if(empty($reportData))
        <section class="rounded-[10px] border border-slate-200 bg-white p-12 text-center text-slate-400">
            Filtrelenen aralıkta hesap hareketi veya rapor verisi bulunamadı.
        </section>
    @else
        @if($reportType === 'trial_balance')
            {{-- MİZAN GÖRÜNÜMÜ --}}
            <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-4">
                <h3 class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-2">Hesap Mizan Tablosu</h3>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-sm text-slate-600 min-w-[800px]">
                        <thead>
                            <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider bg-slate-50/50">
                                <th class="p-3 w-28">Hesap Kodu</th>
                                <th class="p-3">Hesap Adı</th>
                                <th class="p-3 text-right w-36">Borç Toplamı</th>
                                <th class="p-3 text-right w-36">Alacak Toplamı</th>
                                <th class="p-3 text-right w-36">Borç Bakiyesi</th>
                                <th class="p-3 text-right w-36">Alacak Bakiyesi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-mono text-xs">
                            @foreach($reportData as $row)
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="p-3 font-bold text-slate-900">{{ $row['code'] }}</td>
                                    <td class="p-3 text-slate-700 font-sans text-sm">{{ $row['name'] }}</td>
                                    <td class="p-3 text-right text-slate-600">{{ $formatMoney($row['debit']) }}</td>
                                    <td class="p-3 text-right text-slate-600">{{ $formatMoney($row['credit']) }}</td>
                                    <td class="p-3 text-right font-bold text-slate-900">{{ $row['debit_balance'] > 0 ? $formatMoney($row['debit_balance']) : '-' }}</td>
                                    <td class="p-3 text-right font-bold text-slate-900">{{ $row['credit_balance'] > 0 ? $formatMoney($row['credit_balance']) : '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-slate-50/70 border-t border-slate-200 text-xs font-bold text-slate-900">
                            @php
                                $totalDebit = array_sum(array_column($reportData, 'debit'));
                                $totalCredit = array_sum(array_column($reportData, 'credit'));
                                $totalDebBal = array_sum(array_column($reportData, 'debit_balance'));
                                $totalCreBal = array_sum(array_column($reportData, 'credit_balance'));
                            @endphp
                            <tr class="font-mono">
                                <td colspan="2" class="p-3 font-sans text-right">TOPLAM:</td>
                                <td class="p-3 text-right">{{ $formatMoney($totalDebit) }}</td>
                                <td class="p-3 text-right">{{ $formatMoney($totalCredit) }}</td>
                                <td class="p-3 text-right text-slate-950">{{ $formatMoney($totalDebBal) }}</td>
                                <td class="p-3 text-right text-slate-950">{{ $formatMoney($totalCreBal) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

        @elseif($reportType === 'balance_sheet')
            {{-- BİLANÇO GÖRÜNÜMÜ --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- Aktifler (Assets) --}}
                <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-emerald-800 border-b border-emerald-100 pb-2">AKTİFLER (Varlıklar)</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-left text-sm text-slate-600">
                            <thead>
                                <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase bg-slate-50/50">
                                    <th class="p-3 w-28">Hesap Kodu</th>
                                    <th class="p-3">Hesap Adı</th>
                                    <th class="p-3 text-right w-36">Tutar (TRY)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-mono text-xs">
                                @forelse($reportData['assets'] ?? [] as $row)
                                    <tr class="hover:bg-slate-50/50">
                                        <td class="p-3 font-bold text-slate-900">{{ $row['code'] }}</td>
                                        <td class="p-3 font-sans text-sm text-slate-700">{{ $row['name'] }}</td>
                                        <td class="p-3 text-right font-bold text-slate-800">{{ $formatMoney($row['balance']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="p-6 text-center text-slate-400 font-sans">Aktif hesaplarda bakiye yok.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="bg-emerald-50 border-t border-emerald-200 text-xs font-bold text-emerald-950">
                                <tr class="font-mono">
                                    <td colspan="2" class="p-3 font-sans text-right">TOPLAM AKTİFLER:</td>
                                    <td class="p-3 text-right text-base">{{ $formatMoney($reportData['total_assets'] ?? 0) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                {{-- Pasifler & Özkaynak (Liabilities & Equity) --}}
                <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-rose-800 border-b border-rose-100 pb-2">PASİFLER (Borçlar & Özkaynak)</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-left text-sm text-slate-600">
                            <thead>
                                <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase bg-slate-50/50">
                                    <th class="p-3 w-28">Hesap Kodu</th>
                                    <th class="p-3">Hesap Adı</th>
                                    <th class="p-3 text-right w-36">Tutar (TRY)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-mono text-xs">
                                @forelse($reportData['liabilities_and_equity'] ?? [] as $row)
                                    <tr class="hover:bg-slate-50/50">
                                        <td class="p-3 font-bold text-slate-900">{{ $row['code'] }}</td>
                                        <td class="p-3 font-sans text-sm text-slate-700">{{ $row['name'] }}</td>
                                        <td class="p-3 text-right font-bold text-slate-800">{{ $formatMoney($row['balance']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="p-6 text-center text-slate-400 font-sans">Pasif hesaplarda bakiye yok.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="bg-rose-50 border-t border-rose-200 text-xs font-bold text-rose-950">
                                <tr class="font-mono">
                                    <td colspan="2" class="p-3 font-sans text-right">TOPLAM PASİFLER & ÖZKAYNAK:</td>
                                    <td class="p-3 text-right text-base">{{ $formatMoney($reportData['total_liabilities_and_equity'] ?? 0) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

            </div>

        @elseif($reportType === 'income_statement')
            {{-- GELİR TABLOSU GÖRÜNÜMÜ --}}
            <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-6">
                <h3 class="text-base font-semibold text-slate-950 border-b border-slate-100 pb-3">Dönem Gelir Tablosu (P&L)</h3>

                <div class="space-y-4 max-w-2xl mx-auto">
                    {{-- Gelirler --}}
                    <div class="flex justify-between items-center p-4 bg-slate-50 border border-slate-200 rounded-[8px]">
                        <div>
                            <h4 class="text-sm font-bold text-slate-900">Brüt Satış Gelirleri</h4>
                            <p class="text-xs text-slate-500 mt-1">Yurt İçi Satışlar (600) hesabı alacak bakiyeleri toplamı.</p>
                        </div>
                        <div class="font-mono text-base font-bold text-emerald-700">
                            + {{ $formatMoney($reportData['gross_revenue'] ?? 0) }}
                        </div>
                    </div>

                    {{-- Giderler --}}
                    <div class="flex justify-between items-center p-4 bg-slate-50 border border-slate-200 rounded-[8px]">
                        <div>
                            <h4 class="text-sm font-bold text-slate-900">Faaliyet Giderleri</h4>
                            <p class="text-xs text-slate-500 mt-1">Pazarlama (760), Genel Yönetim (770) vb. gider hesapları toplamı.</p>
                        </div>
                        <div class="font-mono text-base font-bold text-rose-700">
                            - {{ $formatMoney($reportData['total_expense'] ?? 0) }}
                        </div>
                    </div>

                    {{-- Net Kâr / Zarar --}}
                    <div class="flex justify-between items-center p-5 rounded-[8px] border {{ ($reportData['net_profit'] ?? 0) >= 0 ? 'bg-emerald-50/50 border-emerald-200' : 'bg-rose-50/50 border-rose-200' }}">
                        <div>
                            <h4 class="text-base font-bold text-slate-950">Dönem Net Kârı veya Zararı</h4>
                            <p class="text-xs text-slate-500 mt-1">Satış gelirlerinden faaliyet giderleri çıkarıldıktan sonra kalan bakiye.</p>
                        </div>
                        <div class="font-mono text-xl font-black {{ ($reportData['net_profit'] ?? 0) >= 0 ? 'text-emerald-800' : 'text-rose-800' }}">
                            {{ ($reportData['net_profit'] ?? 0) >= 0 ? '+' : '' }} {{ $formatMoney($reportData['net_profit'] ?? 0) }}
                        </div>
                    </div>
                </div>
            </section>
        @endif
    @endif
</div>
