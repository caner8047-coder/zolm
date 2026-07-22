@php
    $reviewInsightProducts = $this->reviewInsightProducts;
    $insights = $reviewInsights;
    $riskScore = (int) ($insights['risk_score'] ?? 0);
    $riskTone = $riskScore >= 65
        ? 'border-red-200 bg-red-50/70 text-red-800'
        : ($riskScore >= 35 ? 'border-amber-200 bg-amber-50/70 text-amber-800' : 'border-emerald-200 bg-emerald-50/70 text-emerald-800');
    $priorityTone = fn (string $priority): string => match ($priority) {
        'high' => 'border-red-200 bg-red-50 text-red-700',
        'medium' => 'border-amber-200 bg-amber-50 text-amber-700',
        default => 'border-slate-200 bg-slate-50 text-slate-600',
    };
    $actionLabel = fn (string $type): string => match ($type) {
        'listing' => 'Listing',
        'ai_studio' => 'Görsel / Video',
        'quality' => 'Ürün Kalitesi',
        'operations' => 'Operasyon',
        'pricing' => 'Fiyat',
        default => 'Ürün',
    };
@endphp

<div class="bg-slate-50/30 p-4 lg:p-6" data-testid="booster-review-insights">
    <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-slate-50/60 p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Kanıt tabanlı yorum zekâsı</p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-900">Yorumları ürün kararına dönüştür</h3>
                    <p class="mt-1 max-w-2xl text-sm text-slate-500">Mağaza portföyünü veya tek ürünü analiz edin. Spam yorumlar dışlanır; her bulgu yorum kanıtı ve örneklem güveniyle gösterilir.</p>
                </div>
                <div class="flex w-full flex-col gap-2 sm:flex-row lg:w-auto">
                    <label class="min-w-0 sm:min-w-[280px]">
                        <span class="mb-1 block text-xs font-medium text-slate-600">Analiz kapsamı</span>
                        <select wire:model.live="reviewInsightProductId"
                                class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-800 sm:py-2 sm:text-sm">
                            <option value="">Tüm mağaza yorumları</option>
                            @foreach($reviewInsightProducts as $product)
                                <option value="{{ $product['trendyol_product_id'] }}">{{ Str::limit($product['product_title'], 52) }} · {{ $product['review_count'] }} yorum</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="button" wire:click="runReviewInsights" wire:loading.attr="disabled" wire:target="runReviewInsights"
                            class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-50 sm:w-auto sm:py-2">
                        <span wire:loading.remove wire:target="runReviewInsights">Analizi çalıştır</span>
                        <span wire:loading wire:target="runReviewInsights">Yorumlar okunuyor…</span>
                    </button>
                </div>
            </div>
        </div>

        @if(empty($insights))
            <div class="p-8 text-center lg:p-12">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-[8px] border border-slate-200 bg-slate-50 text-slate-500">
                    <x-lucide.icon name="sparkles" class="h-5 w-5" />
                </div>
                <h4 class="mt-4 text-base font-semibold text-slate-900">Henüz içgörü üretilmedi</h4>
                <p class="mx-auto mt-2 max-w-lg text-sm text-slate-500">Kapsamı seçip analizi çalıştırın. AI servisi kullanılamazsa aynı yorumlardan açıklanabilir kanıt motoru sonuç üretmeye devam eder.</p>
            </div>
        @else
            <div class="space-y-4 p-4 lg:space-y-6 lg:p-6">
                <div class="flex flex-col gap-3 rounded-[8px] border border-slate-200 bg-slate-50/60 p-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs font-mono text-slate-600">{{ data_get($insights, 'provider') === 'evidence_engine' ? 'Kanıt motoru' : strtoupper((string) data_get($insights, 'provider', 'AI')) }}</span>
                            <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs text-slate-600">{{ data_get($insights, 'scope.label', 'Yorum analizi') }}</span>
                        </div>
                        <p class="mt-3 text-sm leading-6 text-slate-800">{{ $insights['summary'] ?? '' }}</p>
                    </div>
                    <button type="button" wire:click="runReviewInsights" wire:loading.attr="disabled" wire:target="runReviewInsights"
                            class="inline-flex min-h-[44px] w-full shrink-0 items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 sm:w-auto">
                        Yeniden analiz et
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-xs text-slate-500">Analiz edilen</p>
                        <p class="mt-1 text-xl font-semibold text-slate-900">{{ (int) ($insights['sample_count'] ?? 0) }} yorum</p>
                        <p class="mt-1 text-xs text-slate-500">{{ (int) ($insights['product_count'] ?? 0) }} ürün</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-xs text-slate-500">Ortalama puan</p>
                        <p class="mt-1 text-xl font-semibold text-slate-900">{{ number_format((float) ($insights['average_rating'] ?? 0), 1, ',', '.') }} / 5</p>
                        <p class="mt-1 text-xs text-slate-500">Düşük puan oranı %{{ number_format((float) ($insights['negative_rate'] ?? 0), 1, ',', '.') }}</p>
                    </div>
                    <div class="rounded-[8px] border p-4 {{ $riskTone }}">
                        <p class="text-xs opacity-75">Yorum risk skoru</p>
                        <p class="mt-1 text-xl font-semibold">{{ $riskScore }} / 100</p>
                        <p class="mt-1 text-xs opacity-75">{{ $insights['risk_label'] ?? 'Hesaplanmadı' }}</p>
                    </div>
                    <div class="rounded-[8px] border border-sky-200 bg-sky-50/70 p-4 text-sky-900">
                        <p class="text-xs text-sky-700">Örneklem güveni</p>
                        <p class="mt-1 text-xl font-semibold">%{{ (int) ($insights['confidence_score'] ?? 0) }}</p>
                        <p class="mt-1 text-xs text-sky-700">{{ $insights['confidence_label'] ?? 'Sınırlı örneklem' }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                    <section class="overflow-hidden rounded-[8px] border border-emerald-200 bg-white">
                        <div class="border-b border-emerald-100 bg-emerald-50/70 px-4 py-3">
                            <h4 class="text-sm font-semibold text-emerald-900">Güçlü bulunan yönler</h4>
                            <p class="mt-0.5 text-xs text-emerald-700">Tekrarlanan olumlu yorum temaları</p>
                        </div>
                        <div class="divide-y divide-slate-100">
                            @forelse(($insights['praises'] ?? []) as $theme)
                                <div class="p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-sm font-semibold text-slate-900">{{ $theme['label'] }}</p>
                                        <span class="rounded-[6px] bg-emerald-50 px-2 py-0.5 text-xs font-mono text-emerald-700">{{ $theme['count'] }} yorum</span>
                                    </div>
                                    @if(!empty($theme['evidence'][0]))
                                        <p class="mt-2 text-xs leading-5 text-slate-500"><span class="font-mono text-slate-400">[{{ $theme['evidence'][0]['id'] }}]</span> “{{ $theme['evidence'][0]['snippet'] }}”</p>
                                    @endif
                                </div>
                            @empty
                                <p class="p-4 text-sm text-slate-500">Tekrarlanan olumlu tema için yeterli kanıt bulunamadı.</p>
                            @endforelse
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-[8px] border border-amber-200 bg-white">
                        <div class="border-b border-amber-100 bg-amber-50/70 px-4 py-3">
                            <h4 class="text-sm font-semibold text-amber-900">Geliştirme alanları</h4>
                            <p class="mt-0.5 text-xs text-amber-700">Tekrarlanan şikâyet ve risk temaları</p>
                        </div>
                        <div class="divide-y divide-slate-100">
                            @forelse(($insights['complaints'] ?? []) as $theme)
                                <div class="p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-sm font-semibold text-slate-900">{{ $theme['label'] }}</p>
                                        <span class="rounded-[6px] bg-amber-50 px-2 py-0.5 text-xs font-mono text-amber-700">%{{ number_format((float) $theme['share_percent'], 1, ',', '.') }}</span>
                                    </div>
                                    @if(!empty($theme['evidence'][0]))
                                        <p class="mt-2 text-xs leading-5 text-slate-500"><span class="font-mono text-slate-400">[{{ $theme['evidence'][0]['id'] }}]</span> “{{ $theme['evidence'][0]['snippet'] }}”</p>
                                    @endif
                                </div>
                            @empty
                                <p class="p-4 text-sm text-slate-500">Tekrarlanan belirgin bir şikâyet teması bulunamadı.</p>
                            @endforelse
                        </div>
                    </section>
                </div>

                @if(!empty($insights['ai_findings']))
                    <section class="rounded-[8px] border border-violet-200 bg-violet-50/40 p-4">
                        <div class="flex items-center gap-2">
                            <x-lucide.icon name="sparkles" class="h-4 w-4 text-violet-600" />
                            <h4 class="text-sm font-semibold text-violet-900">AI bağlamsal bulguları</h4>
                        </div>
                        <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-2">
                            @foreach($insights['ai_findings'] as $finding)
                                <div class="rounded-[6px] border border-violet-100 bg-white p-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm font-semibold text-slate-900">{{ $finding['label'] }}</span>
                                        <span class="text-[11px] font-mono text-violet-600">{{ implode(' ', array_map(fn ($id) => '['.$id.']', $finding['evidence_ids'] ?? [])) }}</span>
                                    </div>
                                    <p class="mt-1 text-xs leading-5 text-slate-600">{{ $finding['reason'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                <section class="overflow-hidden rounded-[8px] border border-slate-200 bg-white">
                    <div class="border-b border-slate-200 bg-slate-50/60 px-4 py-3">
                        <h4 class="text-sm font-semibold text-slate-900">Önerilen aksiyonlar</h4>
                        <p class="mt-0.5 text-xs text-slate-500">Yeni modül oluşturmaz; ilgili ZOLM ürün veya karar yüzeyine bağlanır.</p>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @forelse(($insights['actions'] ?? []) as $action)
                            <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-[6px] border px-2 py-0.5 text-xs font-medium {{ $priorityTone((string) ($action['priority'] ?? 'low')) }}">{{ strtoupper((string) ($action['priority'] ?? 'low')) }}</span>
                                        <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-0.5 text-xs text-slate-600">{{ $actionLabel((string) ($action['type'] ?? '')) }}</span>
                                        <p class="text-sm font-semibold text-slate-900">{{ $action['title'] ?? 'Ürün aksiyonu' }}</p>
                                    </div>
                                    <p class="mt-1 text-xs leading-5 text-slate-500">{{ $action['reason'] ?? '' }} @if(!empty($action['evidence_ids']))<span class="font-mono text-slate-400">{{ implode(' ', array_map(fn ($id) => '['.$id.']', $action['evidence_ids'])) }}</span>@endif</p>
                                </div>
                                <a href="{{ ($action['type'] ?? '') === 'pricing' ? route('mp.trendyol-booster', ['booster' => 'profit_loss']) : route('mp.products') }}"
                                   class="inline-flex min-h-[44px] w-full shrink-0 items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 sm:w-auto">
                                    {{ ($action['type'] ?? '') === 'pricing' ? 'Kâr hesabında aç' : 'Ürünlerde aç' }}
                                </a>
                            </div>
                        @empty
                            <p class="p-4 text-sm text-slate-500">Aksiyon üretmek için yeterli tekrarlanan şikâyet bulunamadı.</p>
                        @endforelse
                    </div>
                </section>

                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <p class="text-xs leading-5 text-slate-500">{{ $insights['evidence_note'] ?? '' }}</p>
                        <span class="shrink-0 text-xs font-mono text-slate-400">{{ isset($insights['generated_at']) ? \Carbon\Carbon::parse($insights['generated_at'])->format('d.m.Y H:i') : '' }}</span>
                    </div>
                </div>
            </div>
        @endif
    </section>
</div>
