<div class="w-full space-y-6">
    {{-- Başlık --}}
    <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row items-start sm:items-center sm:justify-between gap-3 lg:gap-4">
            <div>
                <div class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium uppercase tracking-[0.24em] text-slate-500">
                    Reklam Zekâsı
                </div>
                <h1 class="mt-3 text-xl lg:text-2xl font-bold text-slate-900">Genel Bakış</h1>
                <p class="mt-1 text-sm text-slate-500">Reklam performansınızı analiz edin ve akıllı öneriler alın.</p>
            </div>
            <a href="{{ route('ads.import') }}" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-base sm:text-sm font-medium bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors">
                Veri İçe Aktar
            </a>
        </div>
    </section>

    {{-- Boş Durum --}}
    @if($totalAccounts === 0)
        <section class="rounded-[28px] border border-slate-200 bg-white p-8 lg:p-12 shadow-sm">
            <div class="text-center max-w-md mx-auto">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100">
                    <svg class="h-8 w-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                    </svg>
                </div>
                <h2 class="mt-4 text-lg font-semibold text-slate-900">Henüz reklam verisi bulunmuyor</h2>
                <p class="mt-2 text-sm text-slate-500">
                    Trendyol reklam raporlarınızı içe aktararak kampanya performansınızı analiz etmeye başlayın.
                </p>
                <a href="{{ route('ads.import') }}" class="mt-6 inline-flex items-center px-5 py-3 text-sm font-medium bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors">
                    <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    İlk Raporunuzu Yükleyin
                </a>
            </div>
        </section>
    @else
        {{-- Özet Kartları --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Reklam Hesabı</p>
                <p class="mt-3 text-2xl lg:text-3xl font-bold text-slate-900">{{ $totalAccounts }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Toplam Import</p>
                <p class="mt-3 text-2xl lg:text-3xl font-bold text-slate-900">{{ $totalImports }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Başarılı Import</p>
                <p class="mt-3 text-2xl lg:text-3xl font-bold text-emerald-600">{{ $completedImports }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Durum</p>
                <p class="mt-3 text-sm font-medium text-slate-600">Geliştirme Aşamasında</p>
            </div>
        </div>

        {{-- Son Importlar --}}
        @if(count($recentImports) > 0)
            <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Son Importlar</h2>
                <div class="mt-4 space-y-3">
                    @foreach($recentImports as $import)
                        <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-slate-900 truncate">{{ $import['source_filename'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ \App\Enums\AdImportType::tryFrom($import['import_type'])?->label() ?? $import['import_type'] }}
                                    · {{ $import['report_period_start'] }} — {{ $import['report_period_end'] }}
                                </p>
                            </div>
                            <div class="ml-4 shrink-0">
                                @php
                                    $status = \App\Enums\AdImportStatus::tryFrom($import['status']);
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $status?->color() ?? 'bg-gray-100 text-gray-700' }}">
                                    {{ $status?->label() ?? $import['status'] }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Yakında Gelecekler --}}
        <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Yakında</h2>
            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 lg:gap-4">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm font-medium text-slate-700">Ürün Reklamları Analizi</p>
                    <p class="mt-1 text-xs text-slate-500">Sprint 2'de aktif olacak</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm font-medium text-slate-700">Mağaza Reklamları</p>
                    <p class="mt-1 text-xs text-slate-500">Sprint 3'te aktif olacak</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm font-medium text-slate-700">Influencer Reklamları</p>
                    <p class="mt-1 text-xs text-slate-500">Sprint 4'te aktif olacak</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm font-medium text-slate-700">Kârlılık Merkezi</p>
                    <p class="mt-1 text-xs text-slate-500">Sprint 5'te aktif olacak</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm font-medium text-slate-700">AI Aksiyon Merkezi</p>
                    <p class="mt-1 text-xs text-slate-500">Sprint 6'da aktif olacak</p>
                </div>
            </div>
        </section>
    @endif
</div>
