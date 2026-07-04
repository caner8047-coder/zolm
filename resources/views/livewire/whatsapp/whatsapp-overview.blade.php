<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl lg:text-2xl font-bold text-slate-900">WhatsApp Genel Bakış</h1>
            <p class="text-sm text-slate-500 mt-1">İletişim modülü durumu ve günlük gönderim metrikleri</p>
        </div>
        <div class="flex items-center gap-2">
            @if($testMode)
                <span class="px-2 py-1 text-xs font-medium bg-amber-100 text-amber-700 rounded">TEST MODU</span>
            @endif
            @if($accountActive)
                <span class="px-2 py-1 text-xs font-medium bg-emerald-100 text-emerald-700 rounded">Bağlı</span>
            @else
                <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-700 rounded">Bağlantı Yok</span>
            @endif
        </div>
    </div>

    {{-- KPI Kartları --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-3 lg:gap-4">
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <div class="text-xs text-slate-500 mb-1">Gönderilen</div>
            <div class="text-2xl font-bold text-slate-900">{{ $todaySent }}</div>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <div class="text-xs text-slate-500 mb-1">Teslim Edilen</div>
            <div class="text-2xl font-bold text-emerald-600">{{ $todayDelivered }}</div>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <div class="text-xs text-slate-500 mb-1">Okunan</div>
            <div class="text-2xl font-bold text-blue-600">{{ $todayRead }}</div>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <div class="text-xs text-slate-500 mb-1">Başarısız</div>
            <div class="text-2xl font-bold text-red-600">{{ $todayFailed }}</div>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <div class="text-xs text-slate-500 mb-1">Kuyrukta</div>
            <div class="text-2xl font-bold text-amber-600">{{ $totalQueued }}</div>
        </div>
    </div>

    {{-- Bilgi Notları --}}
    @if(!$accountActive)
        <div class="rounded-[10px] border border-amber-200 bg-amber-50 p-4">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <div>
                    <div class="font-medium text-amber-800 text-sm">WhatsApp hesabı tanımlanmamış</div>
                    <div class="text-sm text-amber-700 mt-1">Hesap Ayarları sayfasından Meta WABA bilgilerinizi girin.</div>
                </div>
            </div>
        </div>
    @endif

    @if($testMode)
        <div class="rounded-[10px] border border-blue-200 bg-blue-50 p-4">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-blue-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <div class="font-medium text-blue-800 text-sm">Test modu aktif</div>
                    <div class="text-sm text-blue-700 mt-1">Sadece izinli test numaralarına mesaj gönderilecek.</div>
                </div>
            </div>
        </div>
    @endif
</div>
