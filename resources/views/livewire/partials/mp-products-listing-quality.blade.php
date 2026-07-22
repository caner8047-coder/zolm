@php
    $qualityScores = (array) data_get($listingQualityAnalysis, 'scores', []);
    $scoreLabels = [
        'title' => 'Başlık',
        'description' => 'Açıklama',
        'images' => 'Görseller',
        'attributes' => 'Nitelikler',
        'consistency' => 'Kanal uyumu',
        'reviews' => 'Yorum sinyali',
    ];
    $qualityIssues = (array) data_get($listingQualityAnalysis, 'issues', []);
    $reviewInsights = (array) data_get($listingQualityAnalysis, 'review_insights', []);
@endphp

<div class="space-y-4 lg:space-y-6" data-testid="mp-products-listing-quality">
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h4 class="text-base font-semibold text-slate-900">AI destekli listing kalite kontrolü</h4>
                    @if($listingQualityAnalysis)
                        <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-mono text-slate-600">
                            {{ data_get($listingQualityAnalysis, 'provider') === 'evidence_engine' ? 'Kanıt motoru' : 'AI + kanıt motoru' }}
                        </span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-slate-500">Ürün kartını, bağlı kanal ilanlarını ve bu ürünle eşleşmiş Trendyol yorumlarını birlikte değerlendirir.</p>
            </div>

            <button type="button"
                    wire:click="runListingQualityAnalysis"
                    wire:loading.attr="disabled"
                    wire:target="runListingQualityAnalysis"
                    class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-wait disabled:opacity-60 sm:w-auto sm:py-2">
                <span wire:loading.remove wire:target="runListingQualityAnalysis">{{ $listingQualityAnalysis ? 'Analizi yenile' : 'Kalite analizi yap' }}</span>
                <span wire:loading wire:target="runListingQualityAnalysis">Analiz ediliyor…</span>
            </button>
        </div>

        @if($listingQualityFeedback !== '')
            <div class="mt-4 rounded-[8px] border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-800" role="status">
                {{ $listingQualityFeedback }}
            </div>
        @endif
    </section>

    @if(!$listingQualityAnalysis)
        <section class="rounded-[10px] border border-dashed border-slate-300 bg-slate-50/60 p-6 text-center">
            <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-[8px] border border-slate-200 bg-white text-slate-500">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
            <h5 class="mt-3 text-sm font-semibold text-slate-900">Bu ürün henüz analiz edilmedi</h5>
            <p class="mt-1 text-sm text-slate-500">Analiz yalnız erişilebilir gerçek kayıtlara dayanır; eksik veri ayrıca görünür.</p>
        </section>
    @else
        <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-200 p-4 sm:flex-row sm:items-center lg:p-6">
                <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-[10px] border border-slate-200 bg-slate-50/70">
                    <div class="text-center">
                        <p class="text-2xl font-bold text-slate-900">{{ (int) data_get($listingQualityAnalysis, 'overall_score', 0) }}</p>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">/ 100</p>
                    </div>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-900">{{ data_get($listingQualityAnalysis, 'score_label') }}</p>
                    <p class="mt-1 text-sm leading-6 text-slate-500">{{ data_get($listingQualityAnalysis, 'summary') }}</p>
                    <div class="mt-2 flex flex-wrap gap-2 text-xs text-slate-500">
                        <span>{{ (int) data_get($listingQualityAnalysis, 'listing_count', 0) }} bağlı ilan</span>
                        <span>•</span>
                        <span>{{ (int) data_get($reviewInsights, 'sample_count', 0) }} eşleşmiş yorum</span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3 lg:p-6">
                @foreach($scoreLabels as $key => $label)
                    @php $score = (int) ($qualityScores[$key] ?? 0); @endphp
                    <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <span class="truncate text-xs font-medium text-slate-600">{{ $label }}</span>
                            <span class="text-sm font-semibold {{ $score >= 80 ? 'text-emerald-600' : ($score >= 60 ? 'text-amber-600' : 'text-rose-600') }}">{{ $score }}</span>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200">
                            <div class="h-full rounded-full {{ $score >= 80 ? 'bg-emerald-500' : ($score >= 60 ? 'bg-amber-500' : 'bg-rose-500') }}" style="width: {{ max(0, min(100, $score)) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 py-3">
                    <h5 class="text-sm font-semibold text-slate-900">Öncelikli geliştirme alanları</h5>
                    <p class="mt-1 text-xs text-slate-500">Her bulgu kaynak kimlikleriyle izlenebilir.</p>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($qualityIssues as $issue)
                        <div class="p-4">
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 h-2.5 w-2.5 shrink-0 rounded-full {{ ($issue['severity'] ?? '') === 'critical' ? 'bg-rose-500' : (($issue['severity'] ?? '') === 'info' ? 'bg-blue-500' : 'bg-amber-500') }}"></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-semibold text-slate-900">{{ $issue['title'] ?? '-' }}</p>
                                        @foreach((array) ($issue['evidence_ids'] ?? []) as $evidenceId)
                                            <span class="rounded-[6px] bg-slate-100 px-1.5 py-0.5 text-[10px] font-mono text-slate-500">{{ $evidenceId }}</span>
                                        @endforeach
                                    </div>
                                    <p class="mt-1 text-xs leading-5 text-slate-500">{{ $issue['reason'] ?? '' }}</p>
                                    @if(($issue['action_type'] ?? '') === 'ai_studio')
                                        <button type="button" wire:click="setEditTab('images')" class="mt-2 inline-flex min-h-[36px] items-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                            Görselleri aç
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-6 text-center text-sm text-slate-500">Kritik bir geliştirme alanı bulunmadı.</div>
                    @endforelse
                </div>
            </div>

            <div class="space-y-4">
                <div class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h5 class="text-sm font-semibold text-slate-900">Başlık taslağı</h5>
                            <p class="mt-1 text-xs text-slate-500">Yalnız ürün kartındaki doğrulanmış niteliklerden oluşturulur.</p>
                        </div>
                        <span class="text-xs text-slate-400">{{ mb_strlen($listingQualityDraftTitle) }}/180</span>
                    </div>
                    <textarea wire:model="listingQualityDraftTitle" rows="3" maxlength="180" class="mt-3 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:py-2 sm:text-sm"></textarea>
                    <button type="button" wire:click="applyListingQualityTitleDraft" class="mt-3 inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white hover:bg-slate-800 sm:w-auto sm:py-2">Başlığa uygula</button>
                </div>

                <div class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h5 class="text-sm font-semibold text-slate-900">Açıklama taslağı</h5>
                            <p class="mt-1 text-xs text-slate-500">Taslağı uyguladıktan sonra düzenleyebilir ve ürün güncellemesiyle kaydedebilirsiniz.</p>
                        </div>
                        <span class="text-xs text-slate-400">{{ mb_strlen($listingQualityDraftDescription) }}/1500</span>
                    </div>
                    <textarea wire:model="listingQualityDraftDescription" rows="7" maxlength="1500" class="mt-3 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:py-2 sm:text-sm"></textarea>
                    <button type="button" wire:click="applyListingQualityDescriptionDraft" class="mt-3 inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white hover:bg-slate-800 sm:w-auto sm:py-2">Açıklamaya uygula</button>
                </div>
            </div>
        </section>

        <p class="px-1 text-xs leading-5 text-slate-500">{{ data_get($listingQualityAnalysis, 'evidence_note') }}</p>
    @endif
</div>
