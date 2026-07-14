<div class="w-full space-y-4 lg:space-y-6 p-4 lg:p-6">
    {{-- Workspace / Summary --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div class="min-w-0">
                <div class="inline-flex items-center rounded px-2 py-0.5 text-xs font-mono bg-slate-100 text-slate-600 uppercase tracking-wider mb-2">
                    ZOLM AI Müşteri Merkezi
                </div>
                <h1 id="cc-page-title" class="text-xl lg:text-2xl font-bold text-slate-900 tracking-tight">
                    AI Müşteri İletişim Merkezi
                </h1>
                <p class="mt-1 text-sm text-slate-500 max-w-3xl">
                    Pazaryeri, WhatsApp, sosyal medya, Google yorumları ve web chat kanallarını tek destek çekirdeğinde yönetin.
                    Otomatik yanıtlar güvenlik kapıları geçmeden açılmaz; manuel ve copilot operasyon her zaman kontrol sizde kalır.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span id="cc-status-badge" class="inline-flex items-center rounded-[6px] border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-800">
                    <span class="mr-1.5 h-2 w-2 rounded-full bg-emerald-500"></span>
                    Modül Ayakta
                </span>
                <span class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-600">
                    {{ ucfirst($automationMode) }} Mod
                </span>
            </div>
        </div>
    </section>

    {{-- Metrics --}}
    <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3 lg:gap-4">
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4 min-w-0">
            <p class="text-xs uppercase tracking-[0.18em] text-slate-500 font-mono">Mağaza</p>
            <p class="mt-3 text-2xl font-bold text-slate-900">{{ $storeCount }}</p>
            <p class="mt-1 text-xs text-slate-500">Erişilebilir mağaza</p>
        </div>

        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4 min-w-0">
            <p class="text-xs uppercase tracking-[0.18em] text-slate-500 font-mono">Aktif Kanal</p>
            <p class="mt-3 text-2xl font-bold text-slate-900">{{ $channelCount }}</p>
            <p class="mt-1 text-xs text-slate-500">Kullanıcı tarafından açılmış kanal</p>
        </div>

        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4 min-w-0">
            <p class="text-xs uppercase tracking-[0.18em] text-slate-500 font-mono">Açık Konuşma</p>
            <p class="mt-3 text-2xl font-bold text-slate-900">{{ $openConversationCount }}</p>
            <p class="mt-1 text-xs text-slate-500">Open / pending destek akışı</p>
        </div>

        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4 min-w-0">
            <p class="text-xs uppercase tracking-[0.18em] text-slate-500 font-mono">Outbox</p>
            <p class="mt-3 text-2xl font-bold text-slate-900">{{ $pendingDispatchCount }}</p>
            <p class="mt-1 text-xs text-slate-500">Bekleyen haricî gönderim</p>
        </div>

        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4 min-w-0">
            <p class="text-xs uppercase tracking-[0.18em] text-slate-500 font-mono">Otomatik Yanıt</p>
            <p class="mt-3 text-lg font-bold {{ $isAutoReplyEnabled ? 'text-emerald-700' : 'text-red-700' }}">
                {{ $isAutoReplyEnabled ? 'Otomatik Yanıt Açık' : 'Otomatik Yanıt Kapalı' }}
            </p>
            <p class="mt-1 text-xs text-slate-500">Global gönderim güvenlik bayrağı</p>
        </div>
    </section>

    {{-- Activation Flow --}}
    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="px-4 lg:px-6 py-4 border-b border-slate-100 bg-slate-50/60">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Ayağa Kaldırma Akışı</h2>
                    <p class="text-sm text-slate-500 mt-0.5">Modülü kullanıma almak için güvenli sırayı buradan takip edin.</p>
                </div>
                <span class="inline-flex w-fit items-center rounded-[6px] border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800">
                    Auto-reply bilinçli olarak kapalı başlar
                </span>
            </div>
        </div>

        <div class="p-4 lg:p-6">
            @if(empty($quickLinks))
                <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50/60 p-5 text-center">
                    <p class="text-sm font-semibold text-slate-900">Henüz açılmış alt modül yok</p>
                    <p class="mt-1 text-sm text-slate-500">
                        Güvenli varsayılan nedeniyle alt ekranlar feature flag kapalı gelir. En az ayarlar veya gelen kutusu bayrağını açarak başlayın.
                    </p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 lg:gap-4">
                    @foreach($quickLinks as $link)
                        <a href="{{ route($link['route']) }}"
                           class="group rounded-[8px] border border-slate-200 bg-white p-4 shadow-sm hover:border-slate-300 hover:shadow-md transition-all min-w-0">
                            <div class="flex items-center justify-between gap-3">
                                <span class="inline-flex items-center rounded-[6px] bg-slate-100 px-2 py-0.5 text-[11px] font-mono text-slate-600">
                                    {{ $link['badge'] }}
                                </span>
                                <span class="text-slate-400 group-hover:text-slate-700 transition-colors">→</span>
                            </div>
                            <h3 class="mt-3 text-sm font-semibold text-slate-900">{{ $link['title'] }}</h3>
                            <p class="mt-1 text-xs text-slate-500 leading-5">{{ $link['description'] }}</p>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- Safety Contract --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 lg:gap-4">
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-sm font-semibold text-slate-900">Kaynaklı Cevap</p>
                <p class="mt-1 text-xs text-slate-500">Fiyat, stok, sipariş ve kargo iddiaları canlı ZOLM verisi veya onaylı bilgiyle desteklenir.</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-sm font-semibold text-slate-900">Fail-Closed Otomasyon</p>
                <p class="mt-1 text-xs text-slate-500">Güven, policy, kota, circuit breaker veya kanal sertifikası geçmezse AI gönderim yapmaz.</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-sm font-semibold text-slate-900">Tenant ve KVKK İzolasyonu</p>
                <p class="mt-1 text-xs text-slate-500">Mağaza/organizasyon sınırı, PII maskeleme, audit ledger ve retention politikaları çekirdeğe bağlıdır.</p>
            </div>
        </div>
    </section>
</div>
