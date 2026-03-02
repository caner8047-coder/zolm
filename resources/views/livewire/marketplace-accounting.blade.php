<div>
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- HEADER + DÖNEM SEÇİCİ --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div class="p-4 lg:p-6 bg-white border-b border-gray-200">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
            <div>
                <h1 class="text-xl lg:text-2xl font-bold text-gray-900">Pazaryeri Muhasebe</h1>
                <p class="text-sm lg:text-base text-gray-500">Trendyol Finansal Analiz & Denetim</p>
            </div>

            {{-- Dönem Seçici --}}
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                <select wire:model.live="selectedYear" class="px-3 py-2 border rounded-lg text-base sm:text-sm bg-white">
                    @for($y = 2024; $y <= 2027; $y++)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endfor
                </select>
                <select wire:model.live="selectedMonth" class="px-3 py-2 border rounded-lg text-base sm:text-sm bg-white">
                    <option value="0">Tüm Yıl (Bütün Aylar)</option>
                    @foreach(['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'] as $i => $ay)
                        <option value="{{ $i + 1 }}">{{ $ay }}</option>
                    @endforeach
                </select>
                <button wire:click="selectPeriod" class="px-4 py-3 sm:py-2 bg-gray-900 text-white rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors">
                    Seç
                </button>
                @if(!$selectedPeriodId)
                    <button wire:click="createPeriod" class="px-4 py-3 sm:py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 transition-colors">
                        + Yeni Dönem
                    </button>
                @else
                    @php 
                        $per = \App\Models\MpPeriod::find($selectedPeriodId); 
                        $isLocked = $per ? $per->is_locked : false; 
                    @endphp
                    
                    @if($isLocked)
                        <button wire:click="unlockPeriod" class="px-4 py-3 sm:py-2 bg-amber-500 text-white rounded-lg text-sm font-medium hover:bg-amber-600 transition-colors flex items-center gap-2" title="Kilitli Dönemi Aç">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                            Kilitli
                        </button>
                    @else
                        <button wire:click="lockPeriod" wire:confirm="Bu dönemi kilitlemek istediğinize emin misiniz? Dönem kilitlendiğinde yeni excel verisi yüklenemez." class="px-4 py-3 sm:py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-300 transition-colors flex items-center gap-2" title="Mutabakatı Kapat / Kilitle">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            Kilitle
                        </button>
                    @endif
                @endif
            </div>
        </div>

        {{-- Status Bar --}}
        @if($importStatus)
            <div class="px-4 py-2 rounded-lg text-sm {{ str_starts_with($importStatus, '✅') ? 'bg-emerald-50 text-emerald-700' : (str_starts_with($importStatus, '❌') ? 'bg-red-50 text-red-700' : 'bg-blue-50 text-blue-700') }}">
                {{ $importStatus }}
            </div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- TAB NAVİGASYONU (6 Tab) --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div class="border-b border-gray-200 bg-white overflow-x-auto">
        <nav class="flex min-w-max px-4 lg:px-6">
            @foreach([
                'dashboard' => '📊 Dashboard',
                'upload'    => '📁 Veri Yükleme',
                'search'    => '🔍 Sipariş Ara',
                'audit'     => '🛡️ Denetim',
                'profit'    => '💰 Kârlılık',
                'orders'    => '📋 Siparişler',
                'settings'  => '⚙️ Ayarlar',
            ] as $tab => $label)
                <button wire:click="$set('activeTab', '{{ $tab }}')"
                    class="px-4 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap
                           {{ $activeTab === $tab ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    <div class="p-4 lg:p-6">

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- TAB 1: DASHBOARD (5 KPI KARTI) --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        @if($activeTab === 'dashboard')
            @if($selectedPeriodId)
                @php $stats = $this->dashboardStats; @endphp

                {{-- ⚠️ FİNANSAL KAÇAK UYARI BANNER'I --}}
                @if($stats['audit_count'] > 0)
                    <div class="mb-6 bg-red-50 border-2 border-red-300 rounded-xl p-4 lg:p-5 shadow-sm animate-pulse-slow">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <span class="text-2xl flex-shrink-0 mt-0.5">⚠️</span>
                                <div>
                                    <p class="font-bold text-red-800 text-sm lg:text-base">
                                        Dikkat: Finansal Kaçak/Ceza Tespit Edildi!
                                    </p>
                                    <p class="text-red-700 text-xs lg:text-sm mt-1">
                                        Seçili dönemde <strong>{{ $stats['audit_count'] }} adet</strong> işlemde
                                        <strong>{{ number_format($stats['audit_amount'], 0, ',', '.') }} ₺</strong>
                                        tutarında finansal kaçak/ceza tespit edilmiştir.
                                        Barem aşımı, ağır kargo cezası, iade yanık maliyeti ve komisyon iadesi sorunlarını inceleyin.
                                    </p>
                                </div>
                            </div>
                            <button wire:click="$set('activeTab', 'audit')"
                                    class="flex-shrink-0 w-full sm:w-auto px-4 py-3 sm:py-2 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700 transition-colors text-center">
                                🛡️ Denetime Git
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Export Butonları --}}
                <div class="flex flex-col sm:flex-row gap-2 mb-6">
                    <button wire:click="exportMonthlyPivot" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 transition-colors flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Aylık Özet Excel
                    </button>
                    <button wire:click="exportAllOrders" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Tüm Siparişler Excel
                    </button>
                    <button wire:click="exportStopajReport" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-amber-500 text-white rounded-lg text-sm font-medium hover:bg-amber-600 transition-colors flex items-center justify-center gap-2" title="Mali Müşavir (193 Kodu) Formu">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v1a3 3 0 106 0v-1m-5 4h4a1 1 0 001-1v-4a1 1 0 00-1-1H9a1 1 0 00-1 1v4a1 1 0 001 1zm8-9V7a2 2 0 00-2-2H9a2 2 0 00-2 2v5m14 0h-2m-2 0H5m-2 0h2m10 0v5a2 2 0 01-2 2h-4a2 2 0 01-2-2v-5z"/></svg>
                        193 Stopaj Excel
                    </button>
                </div>

                {{-- 5 KPI KARTI --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 lg:gap-6 mb-6">

                    {{-- KPI 1: Toplam Brüt Ciro --}}
                    <div class="bg-white rounded-xl border border-gray-200 p-4 lg:p-5 shadow-sm">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Brüt Ciro</span>
                            <span class="text-lg">💰</span>
                        </div>
                        <p class="text-xl lg:text-2xl font-bold text-gray-900">
                            {{ number_format($stats['total_brut'], 0, ',', '.') }} ₺
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ $stats['total_orders'] }} sipariş — {{ $stats['return_rate'] }}% iade
                        </p>
                    </div>

                    {{-- KPI 2: Peşin Ödenen Stopaj --}}
                    <div class="bg-white rounded-xl border border-gray-200 p-4 lg:p-5 shadow-sm">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Stopaj</span>
                            <span class="text-lg">🏛️</span>
                        </div>
                        <p class="text-xl lg:text-2xl font-bold text-indigo-600">
                            {{ number_format($stats['total_stopaj'], 0, ',', '.') }} ₺
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            %1 E-Ticaret stopajı — yılsonunda mahsup
                        </p>
                    </div>

                    {{-- KPI 3: Lojistik Zararı --}}
                    <div class="bg-white rounded-xl border border-red-200 p-4 lg:p-5 shadow-sm">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-semibold text-red-400 uppercase tracking-wider">Lojistik Zararı</span>
                            <span class="text-lg">🔥</span>
                        </div>
                        <p class="text-xl lg:text-2xl font-bold text-red-600">
                            -{{ number_format($stats['logistic_loss']['total'], 0, ',', '.') }} ₺
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            Sunk: {{ number_format($stats['logistic_loss']['sunk_cargo'], 0, ',', '.') }} ₺ + Dönüş: {{ number_format($stats['logistic_loss']['return_cargo'], 0, ',', '.') }} ₺
                        </p>
                    </div>

                    {{-- KPI 4: Net KDV --}}
                    <div class="bg-white rounded-xl border border-gray-200 p-4 lg:p-5 shadow-sm">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Net KDV</span>
                            <span class="text-lg">📋</span>
                        </div>
                        <p class="text-xl lg:text-2xl font-bold {{ $stats['net_vat']['net_vat'] > 0 ? 'text-orange-600' : 'text-emerald-600' }}">
                            {{ $stats['net_vat']['net_vat'] > 0 ? '' : '+' }}{{ number_format($stats['net_vat']['net_vat'], 0, ',', '.') }} ₺
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ $stats['net_vat']['is_payable'] ? 'Devlete ödenecek' : 'KDV avantajı' }}
                        </p>
                    </div>

                    {{-- KPI 5: Gerçek Net Kâr --}}
                    <div class="rounded-xl border-2 p-4 lg:p-5 shadow-sm {{ $stats['real_profit']['total_profit'] >= 0 ? 'bg-emerald-50 border-emerald-300' : 'bg-red-50 border-red-300' }}">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-semibold {{ $stats['real_profit']['total_profit'] >= 0 ? 'text-emerald-600' : 'text-red-600' }} uppercase tracking-wider">Gerçek Net Kâr</span>
                            <span class="text-lg">{{ $stats['real_profit']['total_profit'] >= 0 ? '📈' : '📉' }}</span>
                        </div>
                        <p class="text-xl lg:text-2xl font-bold {{ $stats['real_profit']['total_profit'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                            {{ number_format($stats['real_profit']['total_profit'], 0, ',', '.') }} ₺
                        </p>
                        <p class="text-xs {{ $stats['real_profit']['total_profit'] >= 0 ? 'text-emerald-600' : 'text-red-600' }} mt-1">
                            {{ $stats['real_profit']['profitable_count'] }} kârlı, {{ $stats['real_profit']['bleeding_count'] }} zararlı ürün
                        </p>
                        @if(!$stats['real_profit']['has_cogs'])
                            <p class="text-xs text-amber-600 mt-1">⚠️ COGS verisi eksik — sonuçlar tahminidir</p>
                        @endif
                    </div>
                </div>

                {{-- Ek İstatistik Kartları --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 lg:gap-6">
                    <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                        <p class="text-xs text-gray-400 uppercase mb-1">Net Hakediş</p>
                        <p class="text-lg font-bold text-gray-900">{{ number_format($stats['total_hakedis'], 0, ',', '.') }} ₺</p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                        <p class="text-xs text-gray-400 uppercase mb-1">İade Sayısı</p>
                        <p class="text-lg font-bold text-gray-900">{{ $stats['total_returns'] }}</p>
                        <p class="text-xs text-gray-500">{{ $stats['return_rate'] }}% iade oranı</p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                        <p class="text-xs text-gray-400 uppercase mb-1">İptal Sayısı</p>
                        <p class="text-lg font-bold text-gray-900">{{ $stats['total_cancels'] }}</p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm {{ $stats['audit_count'] > 0 ? 'border-red-200' : '' }}">
                        <p class="text-xs text-gray-400 uppercase mb-1">Denetim Uyarıları</p>
                        <p class="text-lg font-bold {{ $stats['audit_count'] > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $stats['audit_count'] }}</p>
                        @if($stats['audit_amount'] > 0)
                            <p class="text-xs text-red-500">{{ number_format($stats['audit_amount'], 0, ',', '.') }} ₺ fark</p>
                        @endif
                    </div>
                </div>

                {{-- EPIC 7: AYLIK FATURA EŞLEŞTİRME (INVOICE RECONCILIATION) --}}
                @if($this->invoiceReconciliation)
                    @php $recon = $this->invoiceReconciliation; @endphp
                    <div class="mt-6 mb-6 bg-white rounded-xl border {{ ($recon['commission_match'] && $recon['cargo_match']) ? 'border-emerald-200' : 'border-amber-300' }} p-5 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-bold text-gray-800 uppercase tracking-widest flex items-center gap-2">
                                <svg class="w-5 h-5 {{ ($recon['commission_match'] && $recon['cargo_match']) ? 'text-emerald-500' : 'text-amber-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Aylık Fatura Mutabakat Durumu
                            </h3>
                            @if($recon['commission_match'] && $recon['cargo_match'])
                                <span class="bg-emerald-100 text-emerald-800 text-xs font-bold px-3 py-1 rounded-full uppercase">Eşleşti 100%</span>
                            @else
                                <span class="bg-amber-100 text-amber-800 text-xs font-bold px-3 py-1 rounded-full uppercase">Fark Var</span>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            {{-- Komisyon Kıyası --}}
                            <div class="p-4 rounded-lg bg-gray-50 border {{ $recon['commission_match'] ? 'border-gray-200' : 'border-amber-200' }}">
                                <p class="text-xs font-bold text-gray-500 uppercase mb-3">Komisyon Eşleştirmesi</p>
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-sm text-gray-600">Fatura Edilen (KDV Hariç):</span>
                                    <span class="font-medium text-gray-900">{{ number_format($recon['invoice_commission'], 2, ',', '.') }} ₺</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Sipariş Toplamı (Netleştirilmiş):</span>
                                    <span class="font-medium text-gray-900">{{ number_format($recon['order_commission'], 2, ',', '.') }} ₺</span>
                                </div>
                                <div class="mt-3 pt-3 border-t border-gray-200 flex justify-between items-center">
                                    <span class="text-xs font-semibold {{ $recon['commission_match'] ? 'text-emerald-600' : 'text-amber-600' }}">
                                        {{ $recon['commission_match'] ? 'Mutabık' : 'Uyuşmazlık' }}
                                    </span>
                                    <span class="text-sm font-bold {{ $recon['commission_match'] ? 'text-gray-900' : 'text-red-600' }}">
                                        Fark: {{ number_format($recon['commission_diff'], 2, ',', '.') }} ₺
                                    </span>
                                </div>
                            </div>

                            {{-- Kargo Kıyası --}}
                            <div class="p-4 rounded-lg bg-gray-50 border {{ $recon['cargo_match'] ? 'border-gray-200' : 'border-amber-200' }}">
                                <p class="text-xs font-bold text-gray-500 uppercase mb-3">Kargo Eşleştirmesi</p>
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-sm text-gray-600">Fatura Edilen (KDV Hariç):</span>
                                    <span class="font-medium text-gray-900">{{ number_format($recon['invoice_cargo'], 2, ',', '.') }} ₺</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Sipariş Toplamı (Netleştirilmiş):</span>
                                    <span class="font-medium text-gray-900">{{ number_format($recon['order_cargo'], 2, ',', '.') }} ₺</span>
                                </div>
                                <div class="mt-3 pt-3 border-t border-gray-200 flex justify-between items-center">
                                    <span class="text-xs font-semibold {{ $recon['cargo_match'] ? 'text-emerald-600' : 'text-amber-600' }}">
                                        {{ $recon['cargo_match'] ? 'Mutabık' : 'Uyuşmazlık' }}
                                    </span>
                                    <span class="text-sm font-bold {{ $recon['cargo_match'] ? 'text-gray-900' : 'text-red-600' }}">
                                        Fark: {{ number_format($recon['cargo_diff'], 2, ',', '.') }} ₺
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- NAKİT AKIŞI KANBAN (CASH FLOW) --}}
                @if(isset($stats['cash_flow']) && count($stats['cash_flow']['kanban']) > 0)
                    <div class="mt-6 mb-2">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-4 gap-2">
                            <h3 class="text-sm font-bold text-gray-800 uppercase tracking-widest flex items-center gap-2">
                                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Nakit Akışı (Beklenen Transferler)
                            </h3>
                            <span class="text-sm font-bold text-gray-900 bg-gray-100 px-4 py-1.5 rounded-full border border-gray-200 shadow-sm">
                                Toplam Beklenen: {{ number_format($stats['cash_flow']['total_expected'], 2, ',', '.') }} ₺
                            </span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            @foreach($stats['cash_flow']['kanban'] as $kanban)
                                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm relative overflow-hidden group hover:shadow-md transition-shadow">
                                    <div class="absolute left-0 top-0 bottom-0 w-1.5 bg-{{ $kanban['color'] }}-500"></div>
                                    <div class="pl-2">
                                        <p class="text-xs font-bold text-{{ $kanban['color'] }}-600 uppercase mb-1">{{ $kanban['label'] }}</p>
                                        <p class="text-xl lg:text-2xl font-bold text-gray-900">{{ number_format($kanban['amount'], 2, ',', '.') }} ₺</p>
                                        <p class="text-xs text-gray-500 mt-1">{{ $kanban['count'] }} adet işlem bekleniyor</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @else
                <div class="text-center py-16 text-gray-400">
                    <p class="text-4xl mb-4">📊</p>
                    <p class="text-lg font-medium">Dönem seçin veya yeni dönem oluşturun</p>
                    <p class="text-sm mt-2">Dashboard KPI'ları burada görüntülenecek</p>
                </div>
            @endif

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- TAB 2: VERİ YÜKLEME --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        @elseif($activeTab === 'upload')

        {{-- Tüm Verileri Sıfırla Butonu --}}
        <div class="mb-6 flex justify-end">
            <button wire:click="resetAllData" wire:confirm="DİKKAT! Tüm pazaryeri muhasebe verileri (Siparişler, Ödemeler, Faturalar, Dönemler, Denetim Logları) kalıcı olarak silinecektir. Devam etmek istiyor musunuz?" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors flex items-center gap-2 shadow-sm focus:ring-2 focus:ring-red-500 focus:outline-none focus:ring-offset-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Tüm Verileri Sıfırla
            </button>
        </div>

        <div x-data="{
            isProcessing: false,
            procProgress: 0,
            interval: null,
            startProcessing() {
                this.isProcessing = true;
                this.procProgress = 0;
                this.interval = setInterval(() => {
                    if (this.procProgress < 99) {
                        let inc = this.procProgress < 50 ? 2 : (this.procProgress < 85 ? 1 : 0.2);
                        this.procProgress += inc;
                    }
                }, 300);
            },
            stopProcessing() {
                clearInterval(this.interval);
                this.procProgress = 100;
                setTimeout(() => {
                    this.isProcessing = false;
                    this.procProgress = 0;
                }, 500);
            }
        }" x-on:import-finished.window="stopProcessing()">

            {{-- Toplu İşlem Kartı --}}
            <div x-data="{ 
                    isDragging: false, 
                    fileCount: 0,
                    fileNames: [],
                    handleFiles(files) {
                        this.fileCount = files.length;
                        this.fileNames = Array.from(files).map(f => f.name);
                        @this.uploadMultiple('bulkFiles', files);
                    }
                 }"
                 x-on:dragover.prevent="isDragging = true"
                 x-on:dragleave.prevent="isDragging = false"
                 x-on:drop.prevent="isDragging = false; handleFiles($event.dataTransfer.files)"
                 :class="{'border-blue-500 border-dashed bg-blue-100 ring-4 ring-blue-50': isDragging, 'border-blue-200 bg-blue-50': !isDragging}"
                 class="mb-6 border-2 rounded-xl p-4 lg:p-6 flex flex-col sm:flex-row items-center justify-between gap-4 shadow-sm relative overflow-hidden transition-all duration-300 group">
                
                <div class="flex-grow w-full relative z-10 pointer-events-none">
                    <h3 class="font-bold text-blue-900 text-lg flex items-center gap-2">
                        🚀 Toplu İşlem Seçeneği (Dosyaları Buraya Sürükleyin)
                        <span x-show="isDragging" x-transition class="text-xs bg-blue-500 text-white px-2 py-0.5 rounded-full animate-pulse">Bırakın yüklesin...</span>
                    </h3>
                    <p class="text-blue-700 text-sm mt-1 mb-3">
                        Trendyol Paneli'nden indirdiğiniz <strong>tüm Excel dosyalarını (Sipariş, Cari, Fatura, Stopaj, Ödeme)</strong> tek seferde sürükleyip bırakabilir veya çoklu seçebilirsiniz. Sistem dosyaları isimlerinden otomatik tanıyacaktır.
                    </p>
                    
                    <div class="pointer-events-auto">
                        <input type="file" wire:model.live="bulkFiles" multiple accept=".xlsx,.xls"
                               x-on:change="handleFiles($event.target.files)"
                               class="w-full text-sm text-blue-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-blue-100 file:text-blue-800 hover:file:bg-blue-200 focus:outline-none cursor-pointer border border-transparent hover:border-blue-300 transition-colors">
                    </div>

                    {{-- Seçilen Dosya Bilgisi --}}
                    <div x-show="fileCount > 0" x-transition.opacity class="mt-4 p-3 bg-blue-100/50 rounded border border-blue-200 pointer-events-auto">
                        <p class="text-sm font-semibold text-blue-900 mb-1">
                            ✅ <span x-text="fileCount"></span> adet dosya algılandı ve sisteme yükleniyor:
                        </p>
                        <ul class="text-xs text-blue-700 space-y-1 list-disc list-inside">
                            <template x-for="name in fileNames" :key="name">
                                <li x-text="name" class="truncate"></li>
                            </template>
                        </ul>
                    </div>
                    
                    <div wire:loading wire:target="bulkFiles" class="mt-2 text-xs font-medium text-blue-600 flex items-center gap-2">
                        <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Sisteme alınıyor...
                    </div>
                </div>

                <div class="flex-shrink-0 w-full sm:w-auto flex flex-col gap-2 relative mt-4 sm:mt-0">
                    <button wire:click="importAll" x-on:click="startProcessing()" wire:loading.attr="disabled" wire:target="importAll,ordersFile,transactionsFile,stopajFile,invoicesFile,bulkFiles"
                            class="w-full px-6 py-3 bg-blue-600 text-white rounded-lg font-bold hover:bg-blue-700 disabled:opacity-50 transition-colors shadow-md relative overflow-hidden group">
                        
                        <div x-show="isProcessing" class="absolute inset-0 bg-blue-500 transition-all duration-300 origin-left" x-bind:style="'transform: scaleX(' + (procProgress/100) + ')'"></div>

                        <div class="relative z-10">
                            <span wire:loading.remove wire:target="importAll">Tümünü Veritabanına Aktar</span>
                            <span wire:loading wire:target="importAll">
                                ⏳ Aktarılıyor <span x-text="'%' + Math.floor(procProgress)"></span>
                            </span>
                        </div>
                    </button>
                    <!-- Loading Progress Bar Under Button -->
                    <div x-show="isProcessing" class="w-full h-1.5 bg-blue-200 rounded-full overflow-hidden" style="display: none;">
                        <div class="h-full bg-blue-600 transition-all duration-300" x-bind:style="'width: ' + procProgress + '%'"></div>
                    </div>
                </div>
            </div>

            @if(session()->has('import_success'))
                <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg flex items-center gap-3">
                    <span class="text-xl">✅</span>
                    <span class="font-medium">{{ session('import_success') }}</span>
                </div>
            @endif
            @if(session()->has('import_error'))
                <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg flex items-center gap-3">
                    <span class="text-xl">⚠️</span>
                    <span class="font-medium">{{ session('import_error') }}</span>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 lg:gap-6">
                {{-- Sipariş Excel --}}
                <div class="bg-white rounded-xl border border-gray-200 p-4 lg:p-6 shadow-sm">
                    <h3 class="font-semibold text-gray-900 mb-3">📦 Sipariş Verileri</h3>
                    <p class="text-sm text-gray-500 mb-4">Trendyol siparişler Excel dosyası</p>
                    
                    <div x-data="{ isUploading: false, progress: 0 }"
                         x-on:livewire-upload-start="isUploading = true"
                         x-on:livewire-upload-finish="isUploading = false"
                         x-on:livewire-upload-error="isUploading = false"
                         x-on:livewire-upload-progress="progress = $event.detail.progress">
                         
                        <input type="file" wire:model="ordersFile" accept=".xlsx,.xls" id="ordersFileInput"
                               class="w-full text-base sm:text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-300 transition-colors cursor-pointer">
                        
                        {{-- Upload progress bar (Sadece dosya yüklenirken) --}}
                        <div x-show="isUploading" class="mt-3">
                            <div class="flex justify-between text-xs mb-1">
                                <span class="font-medium text-emerald-600">Dosya sisteme yükleniyor...</span>
                                <span class="font-bold text-emerald-600" x-text="progress + '%'"></span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2 border border-gray-200">
                                <div class="bg-emerald-500 h-2 rounded-full transition-all duration-300" x-bind:style="'width: ' + progress + '%'"></div>
                            </div>
                        </div>
                    </div>

                    @if($ordersFile)
                        <p class="text-xs text-green-700 font-medium mt-3 bg-green-50 p-2 rounded border border-green-100">
                            ✅ {{ $ordersFile->getClientOriginalName() }} ({{ number_format($ordersFile->getSize() / 1024, 0) }} KB) — Seçildi
                        </p>
                    @endif
                    @error('ordersFile') <p class="text-red-500 text-xs mt-2">{{ $message }}</p> @enderror
                    
                    <div class="mt-4 flex justify-end flex-col sm:flex-row gap-2 relative">
                        <button wire:click="importOrders" x-on:click="startProcessing()" wire:loading.attr="disabled" wire:target="ordersFile,importOrders,importAll"
                                class="w-full sm:w-auto px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900 disabled:opacity-50 transition-colors shadow relative overflow-hidden">
                            <div x-show="isProcessing" class="absolute inset-0 bg-gray-700 transition-all duration-300 origin-left" x-bind:style="'transform: scaleX(' + (procProgress/100) + ')'"></div>
                            <div class="relative z-10">
                                <span wire:loading.remove wire:target="importOrders">Sadece Bunu Kaydet</span>
                                <span wire:loading wire:target="importOrders">⏳ Kaydediliyor <span x-text="'%' + Math.floor(procProgress)"></span></span>
                            </div>
                        </button>
                    </div>
                </div>

                {{-- Cari Hesap Ekstresi --}}
                <div class="bg-white rounded-xl border border-gray-200 p-4 lg:p-6 shadow-sm">
                    <h3 class="font-semibold text-gray-900 mb-3">🏦 Cari Hesap Ekstresi</h3>
                    <p class="text-sm text-gray-500 mb-4">Trendyol cari hesap ekstresi</p>
                    
                    <div x-data="{ isUploading: false, progress: 0 }"
                         x-on:livewire-upload-start="isUploading = true"
                         x-on:livewire-upload-finish="isUploading = false"
                         x-on:livewire-upload-error="isUploading = false"
                         x-on:livewire-upload-progress="progress = $event.detail.progress">
                         
                        <input type="file" wire:model="transactionsFile" accept=".xlsx,.xls" id="transactionsFileInput"
                               class="w-full text-base sm:text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-300 transition-colors cursor-pointer">
                        
                        <div x-show="isUploading" class="mt-3">
                            <div class="flex justify-between text-xs mb-1">
                                <span class="font-medium text-emerald-600">Dosya sisteme yükleniyor...</span>
                                <span class="font-bold text-emerald-600" x-text="progress + '%'"></span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2 border border-gray-200">
                                <div class="bg-emerald-500 h-2 rounded-full transition-all duration-300" x-bind:style="'width: ' + progress + '%'"></div>
                            </div>
                        </div>
                    </div>

                    @if($transactionsFile)
                        <p class="text-xs text-green-700 font-medium mt-3 bg-green-50 p-2 rounded border border-green-100">
                            ✅ {{ $transactionsFile->getClientOriginalName() }} ({{ number_format($transactionsFile->getSize() / 1024, 0) }} KB) — Seçildi
                        </p>
                    @endif
                    @error('transactionsFile') <p class="text-red-500 text-xs mt-2">{{ $message }}</p> @enderror
                    
                    <div class="mt-4 flex justify-end flex-col sm:flex-row gap-2 relative">
                        <button wire:click="importTransactions" x-on:click="startProcessing()" wire:loading.attr="disabled" wire:target="transactionsFile,importTransactions,importAll"
                                class="w-full sm:w-auto px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900 disabled:opacity-50 transition-colors shadow relative overflow-hidden">
                            <div x-show="isProcessing" class="absolute inset-0 bg-gray-700 transition-all duration-300 origin-left" x-bind:style="'transform: scaleX(' + (procProgress/100) + ')'"></div>
                            <div class="relative z-10">
                                <span wire:loading.remove wire:target="importTransactions">Sadece Bunu Kaydet</span>
                                <span wire:loading wire:target="importTransactions">⏳ Kaydediliyor <span x-text="'%' + Math.floor(procProgress)"></span></span>
                            </div>
                        </button>
                    </div>
                </div>

                {{-- Stopaj --}}
                <div class="bg-white rounded-xl border border-gray-200 p-4 lg:p-6 shadow-sm">
                    <h3 class="font-semibold text-gray-900 mb-3">🏛️ Stopaj / Tevkifat</h3>
                    <p class="text-sm text-gray-500 mb-4">E-Ticaret stopajı Excel dosyası</p>
                    
                    <div x-data="{ isUploading: false, progress: 0 }"
                         x-on:livewire-upload-start="isUploading = true"
                         x-on:livewire-upload-finish="isUploading = false"
                         x-on:livewire-upload-error="isUploading = false"
                         x-on:livewire-upload-progress="progress = $event.detail.progress">
                         
                        <input type="file" wire:model="stopajFile" accept=".xlsx,.xls" id="stopajFileInput"
                               class="w-full text-base sm:text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-300 transition-colors cursor-pointer">
                        
                        <div x-show="isUploading" class="mt-3">
                            <div class="flex justify-between text-xs mb-1">
                                <span class="font-medium text-emerald-600">Dosya sisteme yükleniyor...</span>
                                <span class="font-bold text-emerald-600" x-text="progress + '%'"></span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2 border border-gray-200">
                                <div class="bg-emerald-500 h-2 rounded-full transition-all duration-300" x-bind:style="'width: ' + progress + '%'"></div>
                            </div>
                        </div>
                    </div>

                    @if($stopajFile)
                        <p class="text-xs text-green-700 font-medium mt-3 bg-green-50 p-2 rounded border border-green-100">
                            ✅ {{ $stopajFile->getClientOriginalName() }} ({{ number_format($stopajFile->getSize() / 1024, 0) }} KB) — Seçildi
                        </p>
                    @endif
                    @error('stopajFile') <p class="text-red-500 text-xs mt-2">{{ $message }}</p> @enderror
                    
                    <div class="mt-4 flex justify-end flex-col sm:flex-row gap-2 relative">
                        <button wire:click="importStopaj" x-on:click="startProcessing()" wire:loading.attr="disabled" wire:target="stopajFile,importStopaj,importAll"
                                class="w-full sm:w-auto px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900 disabled:opacity-50 transition-colors shadow relative overflow-hidden">
                            <div x-show="isProcessing" class="absolute inset-0 bg-gray-700 transition-all duration-300 origin-left" x-bind:style="'transform: scaleX(' + (procProgress/100) + ')'"></div>
                            <div class="relative z-10">
                                <span wire:loading.remove wire:target="importStopaj">Sadece Bunu Kaydet</span>
                                <span wire:loading wire:target="importStopaj">⏳ Kaydediliyor <span x-text="'%' + Math.floor(procProgress)"></span></span>
                            </div>
                        </button>
                    </div>
                </div>

                {{-- Faturalar --}}
                <div class="bg-white rounded-xl border border-gray-200 p-4 lg:p-6 shadow-sm">
                    <h3 class="font-semibold text-gray-900 mb-3">🧾 Faturalar</h3>
                    <p class="text-sm text-gray-500 mb-4">Trendyol toplu faturalar</p>
                    
                    <div x-data="{ isUploading: false, progress: 0 }"
                         x-on:livewire-upload-start="isUploading = true"
                         x-on:livewire-upload-finish="isUploading = false"
                         x-on:livewire-upload-error="isUploading = false"
                         x-on:livewire-upload-progress="progress = $event.detail.progress">
                         
                        <input type="file" wire:model="invoicesFile" accept=".xlsx,.xls" id="invoicesFileInput"
                               class="w-full text-base sm:text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-300 transition-colors cursor-pointer">
                        
                        <div x-show="isUploading" class="mt-3">
                            <div class="flex justify-between text-xs mb-1">
                                <span class="font-medium text-emerald-600">Dosya sisteme yükleniyor...</span>
                                <span class="font-bold text-emerald-600" x-text="progress + '%'"></span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2 border border-gray-200">
                                <div class="bg-emerald-500 h-2 rounded-full transition-all duration-300" x-bind:style="'width: ' + progress + '%'"></div>
                            </div>
                        </div>
                    </div>

                    @if($invoicesFile)
                        <p class="text-xs text-green-700 font-medium mt-3 bg-green-50 p-2 rounded border border-green-100">
                            ✅ {{ $invoicesFile->getClientOriginalName() }} ({{ number_format($invoicesFile->getSize() / 1024, 0) }} KB) — Seçildi
                        </p>
                    @endif
                    @error('invoicesFile') <p class="text-red-500 text-xs mt-2">{{ $message }}</p> @enderror
                    
                    <div class="mt-4 flex justify-end flex-col sm:flex-row gap-2 relative">
                        <button wire:click="importInvoices" x-on:click="startProcessing()" wire:loading.attr="disabled" wire:target="invoicesFile,importInvoices,importAll"
                                class="w-full sm:w-auto px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900 disabled:opacity-50 transition-colors shadow relative overflow-hidden">
                            <div x-show="isProcessing" class="absolute inset-0 bg-gray-700 transition-all duration-300 origin-left" x-bind:style="'transform: scaleX(' + (procProgress/100) + ')'"></div>
                            <div class="relative z-10">
                                <span wire:loading.remove wire:target="importInvoices">Sadece Bunu Kaydet</span>
                                <span wire:loading wire:target="importInvoices">⏳ Kaydediliyor <span x-text="'%' + Math.floor(procProgress)"></span></span>
                            </div>
                        </button>
                    </div>
                </div>

                {{-- Ödeme Detay (Hakedişler) --}}
                <div class="bg-white rounded-xl border border-gray-200 p-4 lg:p-6 shadow-sm">
                    <h3 class="font-semibold text-gray-900 mb-3">💸 Ödeme Detay / Hakediş</h3>
                    <p class="text-sm text-gray-500 mb-4">Trendyol haftalık ödeme detay excel'i</p>
                    
                    <div x-data="{ isUploading: false, progress: 0 }"
                         x-on:livewire-upload-start="isUploading = true"
                         x-on:livewire-upload-finish="isUploading = false"
                         x-on:livewire-upload-error="isUploading = false"
                         x-on:livewire-upload-progress="progress = $event.detail.progress">
                         
                        <input type="file" wire:model="settlementsFile" accept=".xlsx,.xls" id="settlementsFileInput"
                               class="w-full text-base sm:text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-300 transition-colors cursor-pointer">
                        
                        <div x-show="isUploading" class="mt-3">
                            <div class="flex justify-between text-xs mb-1">
                                <span class="font-medium text-emerald-600">Dosya sisteme yükleniyor...</span>
                                <span class="font-bold text-emerald-600" x-text="progress + '%'"></span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2 border border-gray-200">
                                <div class="bg-emerald-500 h-2 rounded-full transition-all duration-300" x-bind:style="'width: ' + progress + '%'"></div>
                            </div>
                        </div>
                    </div>

                    @if($settlementsFile)
                        <p class="text-xs text-green-700 font-medium mt-3 bg-green-50 p-2 rounded border border-green-100">
                            ✅ {{ $settlementsFile->getClientOriginalName() }} ({{ number_format($settlementsFile->getSize() / 1024, 0) }} KB) — Seçildi
                        </p>
                    @endif
                    @error('settlementsFile') <p class="text-red-500 text-xs mt-2">{{ $message }}</p> @enderror
                    
                    <div class="mt-4 flex justify-end flex-col sm:flex-row gap-2 relative">
                        <button wire:click="importSettlements" x-on:click="startProcessing()" wire:loading.attr="disabled" wire:target="settlementsFile,importSettlements,importAll"
                                class="w-full sm:w-auto px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900 disabled:opacity-50 transition-colors shadow relative overflow-hidden">
                            <div x-show="isProcessing" class="absolute inset-0 bg-gray-700 transition-all duration-300 origin-left" x-bind:style="'transform: scaleX(' + (procProgress/100) + ')'"></div>
                            <div class="relative z-10">
                                <span wire:loading.remove wire:target="importSettlements">Sadece Bunu Kaydet</span>
                                <span wire:loading wire:target="importSettlements">⏳ Kaydediliyor <span x-text="'%' + Math.floor(procProgress)"></span></span>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Import Errors --}}
            @if(count($importErrors) > 0)
                <div class="mt-4 bg-red-50 border border-red-200 rounded-xl p-4">
                    <h4 class="text-sm font-medium text-red-800 mb-2">İşleme Hataları ({{ count($importErrors) }})</h4>
                    <ul class="text-xs text-red-600 space-y-1 max-h-32 overflow-y-auto">
                        @foreach($importErrors as $err)
                            <li>• {{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- TAB 3: SİPARİŞ ARA (5N1K) --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        @elseif($activeTab === 'search')
            <div class="bg-white rounded-xl border border-gray-200 p-4 lg:p-6 mb-6">
                <h3 class="font-semibold text-gray-900 mb-3">🔍 5N1K Sipariş Dedektifi</h3>
                <div class="flex flex-col sm:flex-row gap-3 items-center">
                    <input type="text" wire:model.defer="searchQuery" wire:keydown.enter="searchOrder"
                           placeholder="Sipariş No, Barkod, Stok Kodu veya Ürün adı yazın..."
                           class="flex-1 w-full px-4 py-3 sm:py-2 border border-gray-300 rounded-lg text-base sm:text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm">
                    <button wire:click="searchOrder"
                            class="w-full sm:w-auto px-6 py-3 sm:py-2 bg-gray-900 text-white rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors shadow-sm flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Ara
                    </button>
                </div>
                <p class="text-xs text-gray-400 mt-2 ml-1">İpucu: Kısmi iadeleri ve çoklu sepetleri bulmak için <strong class="text-gray-500">Barkod</strong> aramasını kullanın.</p>
            </div>

            @if($searchResult !== null)
                @if(count($searchResult) === 0)
                    <div class="text-center py-12 text-gray-400">
                        <p class="text-3xl mb-2">🔎</p>
                        <p>Sonuç bulunamadı</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($searchResult as $result)
                            <div wire:click="showOrderDetail({{ $result['id'] }})"
                                 class="bg-white rounded-xl border border-gray-200 p-4 cursor-pointer hover:shadow-md transition-shadow
                                        {{ $result['is_flagged'] ? 'border-red-300 bg-red-50' : '' }}">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium text-gray-900 truncate">{{ $result['product_name'] }}</p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            #{{ $result['order_number'] }} · {{ $result['barcode'] }} · {{ $result['order_date'] }} · <span class="font-medium text-blue-600">{{ $result['period_name'] ?? '' }}</span>
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 flex-shrink-0 mt-2 sm:mt-0">
                                        <span class="px-2.5 py-1 text-xs font-semibold rounded-full {{ $result['status_color'] }} border border-opacity-20 shadow-sm">
                                            {{ $result['status'] }}
                                        </span>
                                        <span class="text-sm font-bold text-gray-900 ml-2">
                                            {{ number_format($result['gross_amount'], 2, ',', '.') }} ₺
                                        </span>
                                        @if($result['is_flagged'])
                                            <span class="px-2 py-1 bg-red-100 border border-red-200 text-red-800 text-xs font-bold rounded-md shadow-sm flex items-center gap-1" title="Hata Tespit Edildi">
                                                🚩 Sorunlu Satır
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- TAB 4: DENETİM (AUDİT) --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        @elseif($activeTab === 'audit')
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                <div class="flex flex-col sm:flex-row gap-2">
                    <button wire:click="runAudit" wire:loading.attr="disabled"
                            class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-amber-600 text-white rounded-lg text-sm font-medium hover:bg-amber-700 disabled:opacity-50 transition-colors">
                        <span wire:loading.remove wire:target="runAudit">🔍 Denetimi Çalıştır (10 Kural)</span>
                        <span wire:loading wire:target="runAudit">Denetim yapılıyor...</span>
                    </button>
                    <button wire:click="exportAuditReport"
                            class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Audit Excel
                    </button>
                </div>

                <select wire:model.live="auditFilter" class="px-3 py-2 border rounded-lg text-base sm:text-sm bg-white">
                    <option value="all">Tümü</option>
                    <option value="critical">🔴 Kritik</option>
                    <option value="warning">🟡 Uyarı</option>
                    <option value="info">🔵 Bilgi</option>
                </select>
            </div>

            @if($lastAuditResult)
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                    <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-center">
                        <p class="text-2xl font-bold text-red-600">{{ $lastAuditResult['total_errors'] }}</p>
                        <p class="text-xs text-red-500">Kritik Hata</p>
                    </div>
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-center">
                        <p class="text-2xl font-bold text-amber-600">{{ $lastAuditResult['total_warnings'] }}</p>
                        <p class="text-xs text-amber-500">Uyarı</p>
                    </div>
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-center">
                        <p class="text-2xl font-bold text-blue-600">{{ number_format(abs($lastAuditResult['total_amount']), 0, ',', '.') }} ₺</p>
                        <p class="text-xs text-blue-500">Toplam Fark</p>
                    </div>
                </div>
            @endif

            {{-- Audit Log Listesi --}}
            @if($auditLogs->isNotEmpty())
                <div class="space-y-3">
                    @foreach($auditLogs as $log)
                        <div class="bg-white rounded-xl border p-4
                                    {{ $log->severity === 'critical' ? 'border-red-300 bg-red-50' : ($log->severity === 'warning' ? 'border-amber-300 bg-amber-50' : 'border-blue-200 bg-blue-50') }}">
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-sm">{{ $log->severity_icon }}</span>
                                        <span class="px-2 py-0.5 text-xs font-mono rounded {{ $log->severity === 'critical' ? 'bg-red-200 text-red-800' : ($log->severity === 'warning' ? 'bg-amber-200 text-amber-800' : 'bg-blue-200 text-blue-800') }}">
                                            {{ $log->rule_code }}
                                        </span>
                                    </div>
                                    <p class="font-medium text-gray-900 text-sm">{{ $log->title }}</p>
                                    <p class="text-xs text-gray-600 mt-1 leading-relaxed">{{ $log->description }}</p>
                                </div>
                                @if($log->difference > 0)
                                    <span class="text-sm font-bold text-red-600 flex-shrink-0">
                                        {{ number_format($log->difference, 2, ',', '.') }} ₺
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @elseif($selectedPeriodId)
                <div class="text-center py-12 text-gray-400">
                    <p class="text-3xl mb-2">🛡️</p>
                    <p>Henüz denetim çalıştırılmamış</p>
                    <p class="text-sm mt-1">Yukarıdaki butona tıklayarak 10 kuralı çalıştırın</p>
                </div>
            @endif

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- TAB 5: KÂRLILIK (Kâr Motoru — SKU Bazlı) --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        @elseif($activeTab === 'profit')
            @if($selectedPeriodId)
                @php $profitItems = $this->profitData; @endphp

                {{-- Filtre + Export + COGS Sync --}}
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model.live="showOnlyBleeding"
                                   class="w-4 h-4 text-red-600 rounded border-gray-300 focus:ring-red-500">
                            <span class="text-sm text-gray-700">Sadece zararlı ürünleri göster</span>
                        </label>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <button wire:click="syncCogs" wire:loading.attr="disabled" wire:target="syncCogs"
                                class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 disabled:opacity-50 transition-colors flex items-center justify-center gap-2 shadow-sm">
                            <span wire:loading.remove wire:target="syncCogs">🔄 Maliyetleri Güncelle (COGS Sync)</span>
                            <span wire:loading wire:target="syncCogs">
                                <svg class="animate-spin h-4 w-4 inline mr-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                Güncelleniyor...
                            </span>
                        </button>
                        <button wire:click="exportUnitEconomics"
                                class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700 transition-colors flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Birim İktisadı Excel
                        </button>
                    </div>
                </div>

                {{-- COGS Coverage Info --}}
                @php
                    $cogsStats = \App\Models\MpProduct::where('user_id', auth()->id() ?? 1)
                        ->selectRaw('COUNT(*) as total, SUM(CASE WHEN cogs > 0 THEN 1 ELSE 0 END) as with_cogs')
                        ->first();
                    $cogsPct = $cogsStats->total > 0 ? round($cogsStats->with_cogs / $cogsStats->total * 100) : 0;
                @endphp
                <div class="mb-4 p-3 rounded-lg border flex flex-col sm:flex-row sm:items-center justify-between gap-2
                    {{ $cogsPct >= 90 ? 'bg-emerald-50 border-emerald-200' : ($cogsPct >= 70 ? 'bg-amber-50 border-amber-200' : 'bg-red-50 border-red-200') }}">
                    <div class="flex items-center gap-2">
                        <span class="text-sm">{{ $cogsPct >= 90 ? '✅' : ($cogsPct >= 70 ? '⚠️' : '🚨') }}</span>
                        <span class="text-sm font-medium {{ $cogsPct >= 90 ? 'text-emerald-800' : ($cogsPct >= 70 ? 'text-amber-800' : 'text-red-800') }}">
                            COGS Kapsam: %{{ $cogsPct }} ({{ $cogsStats->with_cogs }}/{{ $cogsStats->total }} ürün)
                        </span>
                    </div>
                    <a href="{{ route('mp.products') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 underline">
                        Ürünler sayfasında COGS gir →
                    </a>
                </div>

                @if(count($profitItems) > 0)
                    {{-- Summary Bar --}}
                    @php
                        $totalProfit = collect($profitItems)->sum('total_net_profit');
                        $bleedingTotal = collect($profitItems)->where('is_bleeding', true)->count();
                    @endphp
                    <div class="flex flex-col sm:flex-row gap-4 mb-6">
                        <div class="flex-1 rounded-xl p-4 {{ $totalProfit >= 0 ? 'bg-emerald-50 border border-emerald-200' : 'bg-red-50 border border-red-200' }}">
                            <p class="text-xs font-semibold {{ $totalProfit >= 0 ? 'text-emerald-600' : 'text-red-600' }} uppercase">Toplam Net Kâr</p>
                            <p class="text-xl font-bold {{ $totalProfit >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                                {{ number_format($totalProfit, 0, ',', '.') }} ₺
                            </p>
                        </div>
                        <div class="flex-1 rounded-xl bg-white border border-gray-200 p-4">
                            <p class="text-xs font-semibold text-gray-400 uppercase">SKU Sayısı</p>
                            <p class="text-xl font-bold text-gray-900">{{ count($profitItems) }}</p>
                        </div>
                        @if($bleedingTotal > 0)
                            <div class="flex-1 rounded-xl bg-red-50 border border-red-200 p-4">
                                <p class="text-xs font-semibold text-red-600 uppercase">Kanayan Ürün</p>
                                <p class="text-xl font-bold text-red-700">{{ $bleedingTotal }}</p>
                            </div>
                        @endif
                    </div>

                    {{-- SKU Tablosu --}}
                    <div class="overflow-x-auto bg-white rounded-xl border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ürün</th>
                                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700"
                                        wire:click="sortProfit('order_count')">
                                        Sipariş {{ $profitSortBy === 'order_count' ? ($profitSortDir === 'asc' ? '↑' : '↓') : '' }}
                                    </th>
                                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700"
                                        wire:click="sortProfit('total_gross')">
                                        Ciro {{ $profitSortBy === 'total_gross' ? ($profitSortDir === 'asc' ? '↑' : '↓') : '' }}
                                    </th>
                                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700"
                                        wire:click="sortProfit('total_hakedis')">
                                        Hakediş {{ $profitSortBy === 'total_hakedis' ? ($profitSortDir === 'asc' ? '↑' : '↓') : '' }}
                                    </th>
                                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">COGS</th>
                                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700"
                                        wire:click="sortProfit('total_net_profit')">
                                        Net Kâr {{ $profitSortBy === 'total_net_profit' ? ($profitSortDir === 'asc' ? '↑' : '↓') : '' }}
                                    </th>
                                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700"
                                        wire:click="sortProfit('avg_margin')">
                                        Margin {{ $profitSortBy === 'avg_margin' ? ($profitSortDir === 'asc' ? '↑' : '↓') : '' }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($profitItems as $item)
                                    <tr class="{{ $item['is_bleeding'] ? 'bg-red-100 text-red-800' : 'hover:bg-gray-50' }}">
                                        <td class="px-3 py-3">
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium truncate max-w-xs {{ $item['is_bleeding'] ? 'text-red-900' : 'text-gray-900' }}">
                                                    {{ $item['product_name'] }}
                                                </p>
                                                <p class="text-xs {{ $item['is_bleeding'] ? 'text-red-600' : 'text-gray-500' }}">
                                                    {{ $item['barcode'] }} · {{ $item['stock_code'] }}
                                                    @if(!$item['has_cogs']) <span class="text-amber-500">⚠️ COGS yok</span> @endif
                                                </p>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 text-right text-sm">{{ $item['order_count'] }} ({{ $item['total_quantity'] }} ad.)</td>
                                        <td class="px-3 py-3 text-right text-sm font-medium">{{ number_format($item['total_gross'], 0, ',', '.') }} ₺</td>
                                        <td class="px-3 py-3 text-right text-sm">{{ number_format($item['total_hakedis'], 0, ',', '.') }} ₺</td>
                                        <td class="px-3 py-3 text-right text-sm">{{ number_format($item['total_cogs'], 0, ',', '.') }} ₺</td>
                                        <td class="px-3 py-3 text-right text-sm font-bold {{ $item['is_bleeding'] ? 'text-red-800' : ($item['total_net_profit'] > 0 ? 'text-emerald-600' : 'text-gray-900') }}">
                                            {{ number_format($item['total_net_profit'], 0, ',', '.') }} ₺
                                            @if($item['is_bleeding'])
                                                <span class="ml-1">🩸</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 text-right text-sm font-medium {{ $item['avg_margin'] < 0 ? 'text-red-600' : ($item['avg_margin'] > 20 ? 'text-emerald-600' : 'text-gray-700') }}">
                                            %{{ $item['avg_margin'] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-12 text-gray-400">
                        <p class="text-3xl mb-2">💰</p>
                        <p>Kârlılık verisi bulunamadı</p>
                        <p class="text-sm mt-1">"Teslim Edildi" durumunda sipariş olmalı</p>
                    </div>
                @endif
            @else
                <div class="text-center py-16 text-gray-400">
                    <p class="text-4xl mb-4">💰</p>
                    <p class="text-lg font-medium">Dönem seçin</p>
                </div>
            @endif

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- TAB 6: SİPARİŞLER LİSTESİ --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        @elseif($activeTab === 'orders')
            @if($selectedPeriodId)
                <div class="flex flex-col gap-4 mb-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex flex-wrap gap-3">

                            <select wire:model.live="orderStatusFilter" class="px-3 py-2 border rounded-lg text-base sm:text-sm bg-white min-w-[150px] focus:ring-2 focus:ring-blue-500">
                                <option value="all">Durum: Tümü</option>
                                <option value="Teslim Edildi">Teslim Edilenler</option>
                                <option value="İade Edildi">İadeler</option>
                                <option value="İptal Edildi">İptaller</option>
                                <option value="Kargoda">Kargoda</option>
                            </select>

                            <select wire:model.live="perPage" class="px-3 py-2 border rounded-lg text-base sm:text-sm bg-white min-w-[120px] focus:ring-2 focus:ring-blue-500" title="Sayfadaki Satır Sınırı">
                                <option value="20">20 Satır</option>
                                <option value="50">50 Satır</option>
                                <option value="100">100 Satır</option>
                            </select>
                        </div>
                        
                        <button wire:click="exportAllOrders"
                                class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Excel İndir
                        </button>
                    </div>

                    {{-- Bulk Reconcile İşlemleri --}}
                    @if(count($selectedOrders) > 0)
                        <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-3 flex flex-col sm:flex-row items-center justify-between gap-4 mt-2 mb-2 shadow-sm animate-pulse-slow">
                            <div class="flex items-center gap-2">
                                <span class="bg-indigo-600 text-white text-xs font-bold px-2 py-1 rounded-full">{{ count($selectedOrders) }}</span>
                                <span class="text-sm font-medium text-indigo-900">Sipariş Seçildi</span>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <button wire:click="bulkReconcile('lock')" class="px-3 py-2 bg-gray-900 text-white rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors shadow-sm flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    Mutabık Kıl
                                </button>
                                <button wire:click="bulkReconcile('unlock')" class="px-3 py-2 bg-white text-gray-700 border border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors shadow-sm flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                                    Aç
                                </button>
                                <button wire:click="bulkPushToErp" class="px-3 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors shadow-sm flex items-center gap-1.5 ml-2 border border-indigo-700">
                                    ☁️ ERP'ye Gönder
                                </button>
                                <button wire:click="retryFailedErpPushes" class="px-3 py-2 bg-white text-indigo-700 border border-indigo-200 rounded-lg text-sm font-medium hover:bg-indigo-50 transition-colors shadow-sm flex items-center gap-1.5">
                                    🔄 Hatalıları Tekrarla
                                </button>
                            </div>
                        </div>
                    @endif

                    {{-- 5N1K Hızlı Filtre Çipleri --}}
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-semibold text-gray-500 mr-2">5N1K Dedektifi:</span>
                        
                        <button wire:click="$set('advancedOrderFilter', 'all')" 
                                class="px-3 py-1.5 rounded-full text-xs font-semibold border transition-all {{ $advancedOrderFilter === 'all' ? 'bg-gray-800 text-white border-gray-800 shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
                            Tümü
                        </button>
                        
                        <button wire:click="$set('advancedOrderFilter', 'lost_payments')" 
                                class="px-3 py-1.5 rounded-full text-xs font-semibold border transition-all {{ $advancedOrderFilter === 'lost_payments' ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm' : 'bg-white text-indigo-700 border-indigo-200 hover:bg-indigo-50' }}">
                            🤔 Kayıp Ödemeler (Yatmayanlar)
                        </button>
                        
                        <button wire:click="$set('advancedOrderFilter', 'underpaid')" 
                                class="px-3 py-1.5 rounded-full text-xs font-semibold border transition-all {{ $advancedOrderFilter === 'underpaid' ? 'bg-red-600 text-white border-red-600 shadow-sm' : 'bg-white text-red-700 border-red-200 hover:bg-red-50' }}">
                            🚨 Eksik Yatanlar
                        </button>
                        
                        <button wire:click="$set('advancedOrderFilter', 'penalized')" 
                                class="px-3 py-1.5 rounded-full text-xs font-semibold border transition-all {{ $advancedOrderFilter === 'penalized' ? 'bg-amber-500 text-white border-amber-500 shadow-sm' : 'bg-white text-amber-700 border-amber-200 hover:bg-amber-50' }}">
                            ⚠️ Cezalı İşlemler
                        </button>

                        <button wire:click="$set('advancedOrderFilter', 'returned')" 
                                class="px-3 py-1.5 rounded-full text-xs font-semibold border transition-all {{ $advancedOrderFilter === 'returned' ? 'bg-purple-600 text-white border-purple-600 shadow-sm' : 'bg-white text-purple-700 border-purple-200 hover:bg-purple-50' }}">
                            📦 Sadece İadeler
                        </button>
                    </div>
                </div>

                @if(isset($orders) && count($orders) > 0)
                    <div class="overflow-x-auto bg-white rounded-xl border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 w-8 text-center text-xs font-medium text-gray-500 uppercase">
                                        <input type="checkbox" wire:model.live="selectAll" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    </th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sipariş</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ürün</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Durum</th>
                                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Brüt</th>
                                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Hakediş</th>
                                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Komisyon</th>
                                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Kargo</th>
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Detay</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($orders as $order)
                                    @php
                                        $isFuturePayment = false;
                                        if ($order->settlement && $order->settlement->due_date) {
                                            $isFuturePayment = \Carbon\Carbon::parse($order->settlement->due_date)->startOfDay()->isAfter(\Carbon\Carbon::today());
                                        }
                                    @endphp
                                    <tr class="{{ $order->is_flagged ? 'bg-red-50' : ($order->is_reconciled ? 'bg-gray-100 opacity-75' : ($isFuturePayment ? 'bg-gray-50 opacity-80 border-l-4 border-amber-400' : 'hover:bg-gray-50')) }}">
                                        <td class="px-3 py-3 text-center">
                                            <input type="checkbox" wire:model.live="selectedOrders" value="{{ $order->id }}" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        </td>
                                        <td class="px-3 py-3">
                                            <div class="flex items-center gap-2">
                                                <div>
                                                    <div class="flex items-center gap-1">
                                                        <p class="text-sm font-medium text-gray-900">#{{ $order->order_number }}</p>
                                                        @if($order->is_reconciled)
                                                            <svg class="w-3.5 h-3.5 text-gray-500" fill="currentColor" viewBox="0 0 20 20" title="Mutabık (Kilitli)"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clipped-rule="evenodd" /></svg>
                                                        @endif
                                                        @if($order->erp_status === 'success')
                                                            <span title="ERP'ye İletildi" class="text-xs cursor-default">☁️</span>
                                                        @elseif($order->erp_status === 'pending' || $order->erp_status === 'retry')
                                                            <span title="ERP'ye Gönderilecek (Kuyrukta)" class="text-xs cursor-default animate-pulse">⏳</span>
                                                        @elseif($order->erp_status === 'failed')
                                                            <span title="ERP Hatası! Bknz: {{ Str::limit($order->erp_response, 100) }}" class="text-xs cursor-help">❌</span>
                                                        @endif
                                                    </div>
                                                    <p class="text-xs text-gray-500">{{ $order->order_date?->format('d.m.Y') }}</p>
                                                </div>
                                                @if($isFuturePayment)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-800" title="Vade Tarihi: {{ \Carbon\Carbon::parse($order->settlement->due_date)->format('d.m.Y') }}">
                                                        ⏳ Gelecek Vade
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-3 py-3">
                                            <p class="text-sm truncate max-w-xs">{{ $order->product_name }}</p>
                                            <p class="text-xs text-gray-400">{{ $order->barcode }}</p>
                                        </td>
                                        <td class="px-3 py-3">
                                            <span class="px-2 py-1 text-xs font-medium rounded-full {{ $order->status_color }}">
                                                {{ $order->status }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3 text-right text-sm font-medium {{ $isFuturePayment ? 'opacity-50' : '' }}">{{ number_format($order->gross_amount, 2, ',', '.') }} ₺</td>
                                        <td class="px-3 py-3 text-right text-sm {{ $isFuturePayment ? 'font-bold text-amber-600' : '' }}">
                                            {{ number_format($order->net_hakedis, 2, ',', '.') }} ₺
                                            @if($isFuturePayment)
                                                <div class="text-[10px] text-amber-500 font-normal">Henüz Yatmadı</div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 text-right text-sm {{ $isFuturePayment ? 'opacity-50' : '' }}">{{ number_format($order->commission_amount, 2, ',', '.') }} ₺</td>
                                        <td class="px-3 py-3 text-right text-sm {{ $isFuturePayment ? 'opacity-50' : '' }}">{{ number_format($order->cargo_amount, 2, ',', '.') }} ₺</td>
                                        <td class="px-3 py-3 text-center">
                                            <button wire:click="showOrderDetail({{ $order->id }})" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                                5N1K
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    @if($orders instanceof \Illuminate\Pagination\LengthAwarePaginator)
                        <div class="mt-4">{{ $orders->links() }}</div>
                    @endif
                @else
                    <div class="text-center py-12 text-gray-400">
                        <p class="text-3xl mb-2">📋</p>
                        <p>Bu filtrelere uygun sipariş bulunamadı</p>
                    </div>
                @endif
            @else
                <div class="text-center py-16 text-gray-400">
                    <p class="text-4xl mb-4">📋</p>
                    <p class="text-lg font-medium">Dönem seçin</p>
                </div>
            @endif
        
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- TAB 7: AYARLAR (PROFESYONEL PANEL) --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        @elseif($activeTab === 'settings')
            @include('livewire.mp-settings-panel')
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- 5N1K SİPARİŞ DETAY MODAL --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- Orijinal Modal yerine yeni zenginleştirilmiş Sub-View --}}
    @include('livewire.mp-order-modal')
</div>
