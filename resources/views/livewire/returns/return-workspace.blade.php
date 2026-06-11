@php
    $surfaceNav = collect([
        $canAccessIntake ? [
            'id' => 'kabul',
            'eyebrow' => 'Kabul',
            'title' => 'İade Kabul Formu',
            'description' => 'Depo alımı ve fotoğraf yükleme',
            'tone' => 'border-emerald-200 bg-emerald-50/70 text-emerald-700',
            'icon' => '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>'
        ] : null,
        $canAccessReview ? [
            'id' => 'pazaryeri',
            'eyebrow' => 'Pazaryeri',
            'title' => 'Pazaryeri İadeleri',
            'description' => 'Gelen claim ve refund talepleri',
            'tone' => 'border-indigo-200 bg-indigo-50/70 text-indigo-700',
            'icon' => '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l2 2 4-4M7 7h10M7 11h4m-6 9h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>'
        ] : null,
        $canAccessReview ? [
            'id' => 'havuz',
            'eyebrow' => 'Karar',
            'title' => 'Akıllı İade Merkezi',
            'description' => 'Karar havuzu ve ürün eşleşmeleri',
            'tone' => 'border-sky-200 bg-sky-50/70 text-sky-700',
            'icon' => '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>'
        ] : null,
        $canAccessReview ? [
            'id' => 'whatsapp',
            'eyebrow' => 'WhatsApp',
            'title' => 'WhatsApp Köprüsü',
            'description' => 'Thread takibi ve mesajlaşma',
            'tone' => 'border-amber-200 bg-amber-50/70 text-amber-700',
            'icon' => '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>'
        ] : null,
    ])->filter()->values();

    $statCards = [
        ['label' => 'Bugün gelen', 'value' => $workspaceStats['todayArrivals'], 'tone' => 'text-slate-900', 'bg' => 'bg-slate-50/70'],
        ['label' => 'Karar bekleyen', 'value' => $workspaceStats['awaitingDecision'], 'tone' => 'text-amber-700', 'bg' => 'bg-amber-50/50'],
        ['label' => 'Pazaryeri iade', 'value' => $workspaceStats['marketplaceClaimsWaiting'], 'tone' => 'text-indigo-700', 'bg' => 'bg-indigo-50/50'],
        ['label' => 'Açık WhatsApp', 'value' => $workspaceStats['activeThreads'], 'tone' => 'text-sky-700', 'bg' => 'bg-sky-50/50'],
    ];

    $defaultTab = $canAccessIntake ? 'kabul' : 'havuz';
@endphp
<div
    x-data
    x-init="
        const hashTab = window.location.hash.replace('#', '');
        const allowedTabs = @js($availableTabs);
        const hasQueryTab = new URLSearchParams(window.location.search).has('tab');

        if (hashTab && allowedTabs.includes(hashTab) && !hasQueryTab) {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', hashTab);
            url.hash = '';
            window.location.replace(url.toString());
        }
    "
    class="space-y-4 lg:space-y-6 p-4 lg:p-6"
>

    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="grid grid-cols-1 gap-4 p-4 lg:grid-cols-3 lg:gap-6 lg:p-6">
            <div class="space-y-4 lg:col-span-2">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Araçlar</p>
                    <h1 class="mt-1 text-xl font-bold text-slate-900 lg:text-2xl">Akıllı İade Merkezi</h1>
                    <p class="mt-1 max-w-xl text-sm text-slate-500">
                        İade süreci, karar havuzu ve WhatsApp köprüsü tek bir workspace içinde birleştirildi. İşleminize
                        uygun sekmeyi seçerek ilerleyin.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($statCards as $card)
                        <div class="rounded-[8px] border border-slate-200 {{ $card['bg'] }} p-3 lg:p-4">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">
                                {{ $card['label'] }}</p>
                            <p class="mt-1.5 text-2xl font-bold {{ $card['tone'] }}">{{ number_format($card['value']) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex flex-col justify-end lg:col-span-1">
                <div class="rounded-[8px] border border-violet-200 bg-violet-50/60 p-4">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 rounded-full bg-violet-200 p-1.5 text-violet-700">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-violet-900">Otomatik analiz aktif</p>
                            <p class="mt-1 text-xs text-violet-700/80">
                                WhatsApp'tan atılan etiket fotoğrafları veya kabul alanından girilen kayıtlar arka
                                planda Gemini AI ile saniyeler içinde analiz edilip siparişle eşleştirilir.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Navigasyon Sekmeleri --}}
        <div class="border-t border-slate-200 bg-slate-50/50 px-4 pt-4 lg:px-6 lg:pt-6">
            <div class="flex flex-wrap gap-2 lg:gap-3 border-b border-slate-200">
                @foreach($surfaceNav as $nav)
                    <button
                        type="button"
                        wire:click="showTab('{{ $nav['id'] }}')"
                        class="{{ $activeTab === $nav['id']
                            ? 'border-b-2 border-slate-900 text-slate-900 font-semibold bg-white'
                            : 'border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 font-medium' }} inline-flex items-center gap-2 px-4 py-3 text-sm transition-colors mb-[-1px]"
                    >
                        <span class="{{ $activeTab === $nav['id'] ? 'text-slate-700' : 'text-slate-400' }}">
                            {!! $nav['icon'] !!}
                        </span>
                        {{ $nav['title'] }}
                    </button>
                @endforeach
            </div>
        </div>
    </section>

    {{-- İçerik Değişimi --}}
    <div class="min-h-[400px] mt-4 lg:mt-6">
        @if($activeTab === 'kabul' && $canAccessIntake)
            <div class="w-full">
                <livewire:returns.return-intake :embedded="true" :key="'returns-intake-embedded'" />
            </div>
        @elseif($activeTab === 'pazaryeri' && $canAccessReview)
            <div class="w-full">
                <livewire:returns.marketplace-claims-center :embedded="true" :key="'returns-marketplace-claims-embedded'" />
            </div>
        @elseif($activeTab === 'havuz' && $canAccessReview)
            <div class="w-full">
                <livewire:returns.return-intelligence-center :embedded="true" :key="'returns-center-embedded'" />
            </div>
        @elseif($activeTab === 'whatsapp' && $canAccessReview)
            <div class="w-full">
                <livewire:returns.return-whatsapp-bridge :embedded="true" :key="'returns-whatsapp-embedded'" />
            </div>
        @endif
    </div>
</div>
