@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
@endphp

<div class="w-full space-y-4 lg:space-y-6">
    {{-- Header --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
            Ön Muhasebe
        </div>
        <h1 class="mt-3 text-xl font-semibold tracking-tight text-slate-950 lg:text-2xl">Ön Muhasebe & ERP Dashboard</h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
            ZOLM Kurumsal Ön Muhasebe ve ERP süreçlerinizi, cari hesap bakiyelerinizi, envanter durumunuzu ve finansal raporlarınızı tek panelden yönetin.
        </p>

        <!-- KPI Kartları -->
        <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Açık Alacak Toplamı</p>
                <p class="mt-2 text-xl font-bold text-emerald-700">{{ $formatMoney($this->kpis['open_receivables']) }}</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Açık Borç Toplamı</p>
                <p class="mt-2 text-xl font-bold text-rose-700">{{ $formatMoney($this->kpis['open_payables']) }}</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Kasa & Banka Toplamı</p>
                <p class="mt-2 text-xl font-bold text-blue-700">{{ $formatMoney($this->kpis['cash_bank']) }}</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Stok Envanter Değeri</p>
                <p class="mt-2 text-xl font-bold text-slate-700">{{ $formatMoney($this->kpis['stock_value']) }}</p>
            </div>
        </div>
    </section>

    <!-- Modül Kartları Grid -->
    <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 lg:gap-6">
        <!-- Cari Açık Hesap -->
        <div class="rounded-[10px] border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between hover:shadow-md transition-shadow">
            <div>
                <div class="flex items-center justify-between">
                    <span class="p-2 bg-emerald-50 text-emerald-700 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </span>
                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-emerald-100 text-emerald-800">Hazır (MVP)</span>
                </div>
                <h3 class="mt-4 text-base font-semibold text-slate-900">Cari Açık Hesap</h3>
                <p class="mt-2 text-sm text-slate-500">Müşteri ve tedarikçi bazlı cari hareketlerin takibi, manuel işlem girişi ve bakiye ekstreleri.</p>
            </div>
            <div class="mt-6">
                <a href="{{ route('accounting.party-ledger') }}" class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors">
                    Yönet
                </a>
            </div>
        </div>

        <!-- Hesap Planı / Yevmiye -->
        <div class="rounded-[10px] border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between hover:shadow-md transition-shadow">
            <div>
                <div class="flex items-center justify-between">
                    <span class="p-2 bg-blue-50 text-blue-700 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </span>
                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-emerald-100 text-emerald-800">Hazır (MVP)</span>
                </div>
                <h3 class="mt-4 text-base font-semibold text-slate-900">Hesap Planı & Yevmiye</h3>
                <p class="mt-2 text-sm text-slate-500">Muhasebe çekirdeği entegrasyonu, çift taraflı kayıt yevmiye fişleri ve hesap listesi yönetimi.</p>
            </div>
            <div class="mt-6 flex gap-2">
                <a href="{{ route('accounting.chart-of-accounts') }}" class="w-1/2 inline-flex items-center justify-center px-3 py-2 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 transition-colors">
                    Hesap Planı
                </a>
                <a href="{{ route('accounting.journal') }}" class="w-1/2 inline-flex items-center justify-center px-3 py-2 text-xs font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors">
                    Yevmiye Fişleri
                </a>
            </div>
        </div>

        <!-- Kasa & Banka -->
        <div class="rounded-[10px] border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between hover:shadow-md transition-shadow">
            <div>
                <div class="flex items-center justify-between">
                    <span class="p-2 bg-indigo-50 text-indigo-700 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </span>
                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-emerald-100 text-emerald-800">Hazır (MVP)</span>
                </div>
                <h3 class="mt-4 text-base font-semibold text-slate-900">Kasa & Banka</h3>
                <p class="mt-2 text-sm text-slate-500">Nakit kasa, banka mevduat hesapları, virman ve transfer hareketlerinin yönetimi.</p>
            </div>
            <div class="mt-6">
                <a href="{{ route('accounting.cash-bank') }}" class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors">
                    Yönet
                </a>
            </div>
        </div>

        <!-- Stok -->
        <div class="rounded-[10px] border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between hover:shadow-md transition-shadow">
            <div>
                <div class="flex items-center justify-between">
                    <span class="p-2 bg-amber-50 text-amber-700 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </span>
                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-emerald-100 text-emerald-800">Hazır (MVP)</span>
                </div>
                <h3 class="mt-4 text-base font-semibold text-slate-900">Depo & Stok</h3>
                <p class="mt-2 text-sm text-slate-500">Envanter miktarları, stok giriş/çıkış fişleri, depo transferleri ve kritik seviye uyarıları.</p>
            </div>
            <div class="mt-6">
                <a href="{{ route('accounting.stock') }}" class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors">
                    Yönet
                </a>
            </div>
        </div>

        <!-- Satışlar -->
        <div class="rounded-[10px] border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between hover:shadow-md transition-shadow">
            <div>
                <div class="flex items-center justify-between">
                    <span class="p-2 bg-rose-50 text-rose-700 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </span>
                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-emerald-100 text-emerald-800">Hazır (MVP)</span>
                </div>
                <h3 class="mt-4 text-base font-semibold text-slate-900">Satışlar</h3>
                <p class="mt-2 text-sm text-slate-500">Satış siparişi kayıtları, müşteri sipariş durumları ve sipariş onay/fatura süreçleri.</p>
            </div>
            <div class="mt-6">
                <a href="{{ route('accounting.sales') }}" class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors">
                    Yönet
                </a>
            </div>
        </div>

        <!-- Satın Alma -->
        <div class="rounded-[10px] border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between hover:shadow-md transition-shadow">
            <div>
                <div class="flex items-center justify-between">
                    <span class="p-2 bg-teal-50 text-teal-700 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l2.414 2.414a1 1 0 01.293.707V15a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h2m.586-7H19" />
                        </svg>
                    </span>
                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-emerald-100 text-emerald-800">Hazır (MVP)</span>
                </div>
                <h3 class="mt-4 text-base font-semibold text-slate-900">Satın Alma</h3>
                <p class="mt-2 text-sm text-slate-500">Tedarikçi alış siparişleri, alış faturaları ve envanter girişlerinin kontrolü.</p>
            </div>
            <div class="mt-6">
                <a href="{{ route('accounting.purchases') }}" class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors">
                    Yönet
                </a>
            </div>
        </div>

        <!-- Hızlı Satış -->
        <div class="rounded-[10px] border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between hover:shadow-md transition-shadow">
            <div>
                <div class="flex items-center justify-between">
                    <span class="p-2 bg-purple-50 text-purple-700 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M9 7h6m-6 4h6m-7 4h.01M12 15h.01M16 15h.01M7 3h10a2 2 0 012 2v14a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z" />
                        </svg>
                    </span>
                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-emerald-100 text-emerald-800">Hazır (MVP)</span>
                </div>
                <h3 class="mt-4 text-base font-semibold text-slate-900">Hızlı Satış (POS)</h3>
                <p class="mt-2 text-sm text-slate-500">Perakende satış checkout arayüzü, sepet yönetimi, vardiya takipleri ve anında kasa entegrasyonu.</p>
            </div>
            <div class="mt-6">
                <a href="{{ route('accounting.pos') }}" class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors">
                    Yönet
                </a>
            </div>
        </div>

        <!-- e-Fatura -->
        <div class="rounded-[10px] border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between hover:shadow-md transition-shadow">
            <div>
                <div class="flex items-center justify-between">
                    <span class="p-2 bg-sky-50 text-sky-700 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5a2 2 0 10-2 2h2zm0 0h4m-4 0H8m12 3v10a2 2 0 01-2 2H6a2 2 0 01-2-2V11" />
                        </svg>
                    </span>
                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-emerald-100 text-emerald-800">Hazır (MVP)</span>
                </div>
                <h3 class="mt-4 text-base font-semibold text-slate-900">e-Fatura / e-Belge</h3>
                <p class="mt-2 text-sm text-slate-500">Simüle edilmiş e-Arşiv ve e-Fatura oluşturma, taslak yönetimi ve durum takipleri.</p>
            </div>
            <div class="mt-6">
                <a href="{{ route('accounting.e-documents') }}" class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors">
                    Yönet
                </a>
            </div>
        </div>

        <!-- Raporlar -->
        <div class="rounded-[10px] border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between hover:shadow-md transition-shadow">
            <div>
                <div class="flex items-center justify-between">
                    <span class="p-2 bg-violet-50 text-violet-700 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M11 3.055A9.003 9.003 0 1020.945 13H11V3.055z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                        </svg>
                    </span>
                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-emerald-100 text-emerald-800">Hazır (MVP)</span>
                </div>
                <h3 class="mt-4 text-base font-semibold text-slate-900">Finansal Raporlar</h3>
                <p class="mt-2 text-sm text-slate-500">Alacak/Borç yaşlandırma raporları, nakit akış tahminleri ve Gelir/Gider (P&L) özet tabloları.</p>
            </div>
            <div class="mt-6">
                <a href="{{ route('accounting.reports') }}" class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors">
                    Yönet
                </a>
            </div>
        </div>

        <!-- Asistan -->
        <div class="rounded-[10px] border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between hover:shadow-md transition-shadow">
            <div>
                <div class="flex items-center justify-between">
                    <span class="p-2 bg-fuchsia-50 text-fuchsia-700 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                        </svg>
                    </span>
                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-emerald-100 text-emerald-800">Hazır (MVP)</span>
                </div>
                <h3 class="mt-4 text-base font-semibold text-slate-900">AI Muhasebe Asistanı</h3>
                <p class="mt-2 text-sm text-slate-500">Doğal dil ile ön muhasebe ve finans verilerini analiz eden Gemini entegrasyonu.</p>
            </div>
            <div class="mt-6">
                <a href="{{ route('accounting.assistant') }}" class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors">
                    Yönet
                </a>
            </div>
        </div>

        <!-- Pazaryeri Finans Köprüsü -->
        <div class="rounded-[10px] border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between hover:shadow-md transition-shadow">
            <div>
                <div class="flex items-center justify-between">
                    <span class="p-2 bg-slate-50 text-slate-700 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                        </svg>
                    </span>
                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-emerald-100 text-emerald-800">Hazır (MVP)</span>
                </div>
                <h3 class="mt-4 text-base font-semibold text-slate-900">Pazaryeri Finans Köprüsü</h3>
                <p class="mt-2 text-sm text-slate-500">Pazaryeri sipariş aktarımları, sipariş bazlı envanter ve GL entegrasyon logları.</p>
            </div>
            <div class="mt-6">
                <a href="{{ route('accounting.marketplace-bridge') }}" class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors">
                    Yönet
                </a>
            </div>
        </div>
    </section>
</div>
