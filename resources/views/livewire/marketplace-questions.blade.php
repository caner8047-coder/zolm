@php
    $crmLinks = app(\App\Services\Crm\CrmSourceLinkService::class);
    $crmSnapshots = app(\App\Services\Crm\CrmCustomerSnapshotService::class);
@endphp

<div class="space-y-4 lg:space-y-6 p-4 lg:p-6">
    @if($toastMessage)
        <div
            x-data="{ show: true }"
            x-init="setTimeout(() => { show = false; $wire.set('toastMessage', '') }, 3200)"
            x-show="show"
            x-transition
            class="fixed right-4 top-4 z-50 w-[min(420px,calc(100vw-2rem))] rounded-[10px] border px-4 py-3 text-sm shadow-lg
                {{ $toastTone === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : '' }}
                {{ $toastTone === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-800' : '' }}
                {{ $toastTone === 'error' ? 'border-red-200 bg-red-50 text-red-800' : '' }}
                {{ $toastTone === 'info' ? 'border-slate-200 bg-white text-slate-700' : '' }}"
        >
            {{ $toastMessage }}
        </div>
    @endif

    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-slate-200 p-4 lg:flex-row lg:items-center lg:justify-between lg:p-5">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Pazaryeri iletişim merkezi</div>
                <h1 class="mt-1 text-xl font-semibold text-slate-900 lg:text-2xl">Müşteri Soruları</h1>
                <p class="mt-1 text-sm text-slate-500">Tüm pazaryeri sorularını ZOLM içinde cevaplayın, hazır cevap ve otonom kurallarla hızlanın.</p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                <button wire:click="syncQuestions" wire:loading.attr="disabled" wire:target="syncQuestions" type="button" class="w-full rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-wait disabled:opacity-70 sm:w-auto sm:py-2">
                    <span wire:loading.remove wire:target="syncQuestions">Soruları Çek</span>
                    <span wire:loading wire:target="syncQuestions">Sorular çekiliyor...</span>
                </button>
                <button wire:click="createTemplate" type="button" class="w-full rounded-[6px] border border-slate-900 bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 sm:w-auto sm:py-2">
                    Hazır Cevap Ekle
                </button>
                <button wire:click="createRule" type="button" class="w-full rounded-[6px] border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-100 sm:w-auto sm:py-2">
                    Otonom Kural
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 xl:grid-cols-4 lg:p-5">
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Bekleyen</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($metrics['open'], 0, ',', '.') }}</div>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Taslak</div>
                <div class="mt-2 text-2xl font-semibold text-amber-600">{{ number_format($metrics['draft'], 0, ',', '.') }}</div>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Cevaplandı</div>
                <div class="mt-2 text-2xl font-semibold text-emerald-600">{{ number_format($metrics['answered'], 0, ',', '.') }}</div>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Aktif Kural</div>
                <div class="mt-2 text-2xl font-semibold text-indigo-600">{{ number_format($metrics['rules'], 0, ',', '.') }}</div>
            </div>
        </div>
    </section>

    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-4 lg:p-5">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <input wire:model.live.debounce.350ms="search" type="search" placeholder="Soru, ürün, SKU, müşteri ara" class="rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none transition focus:border-slate-400 sm:text-sm">
                <select wire:model.live="statusFilter" class="rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none transition focus:border-slate-400 sm:text-sm">
                    <option value="open">Bekleyen + Taslak</option>
                    <option value="">Tüm Durumlar</option>
                    <option value="draft">Taslak</option>
                    <option value="answered">Cevaplandı</option>
                    <option value="closed">Kapalı</option>
                </select>
                <select wire:model.live="marketplaceFilter" class="rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none transition focus:border-slate-400 sm:text-sm">
                    <option value="">Tüm Pazaryerleri</option>
                    @foreach($marketplaces as $marketplace)
                        <option value="{{ $marketplace }}">{{ \Illuminate\Support\Str::headline($marketplace) }}</option>
                    @endforeach
                </select>
                <select wire:model.live="storeFilter" class="rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none transition focus:border-slate-400 sm:text-sm">
                    <option value="">Tüm Mağazalar</option>
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}">{{ $store->store_name }} · {{ \Illuminate\Support\Str::headline($store->marketplace) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-[360px_minmax(0,1fr)_360px]">
            <aside class="border-b border-slate-200 xl:border-b-0 xl:border-r">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Soru havuzu</div>
                        <div class="text-sm font-semibold text-slate-900">{{ $questions->total() }} kayıt</div>
                    </div>
                </div>

                <div class="max-h-[720px] divide-y divide-slate-100 overflow-y-auto">
                    @forelse($questions as $question)
                        @php
                            $statusTone = match($question->status) {
                                'answered' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'draft' => 'bg-amber-50 text-amber-700 border-amber-200',
                                'closed' => 'bg-slate-100 text-slate-500 border-slate-200',
                                default => 'bg-blue-50 text-blue-700 border-blue-200',
                            };
                            $statusLabel = match($question->status) {
                                'answered' => 'Cevaplandı',
                                'draft' => 'Taslak',
                                'closed' => 'Kapalı',
                                default => 'Bekliyor',
                            };
                        @endphp
                        <button wire:click="selectQuestion({{ $question->id }})" type="button" class="block w-full px-4 py-3 text-left transition hover:bg-slate-50 {{ $selectedQuestionId === $question->id ? 'bg-slate-50' : '' }}">
                            <div class="flex min-w-0 items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold text-slate-900">{{ $question->product_name ?: 'Ürün bilgisi yok' }}</div>
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
                                        <span>{{ $question->store?->store_name }}</span>
                                        <span>·</span>
                                        <span>{{ \Illuminate\Support\Str::headline($question->store?->marketplace ?? '') }}</span>
                                    </div>
                                </div>
                                <span class="shrink-0 rounded-full border px-2 py-0.5 text-xs font-semibold {{ $statusTone }}">{{ $statusLabel }}</span>
                            </div>
                            <p class="mt-2 line-clamp-2 text-sm text-slate-600">{{ $question->question_text }}</p>
                            <div class="mt-2 text-xs text-slate-400">{{ optional($question->asked_at)->format('d.m.Y H:i') ?: 'Tarih yok' }}</div>
                        </button>
                    @empty
                        <div class="p-6 text-sm text-slate-500">Filtreye uygun müşteri sorusu yok.</div>
                    @endforelse
                </div>

                <div class="border-t border-slate-200 p-3">
                    {{ $questions->links() }}
                </div>
            </aside>

            <main class="min-w-0 border-b border-slate-200 xl:border-b-0 xl:border-r">
                @if($selectedQuestion)
                    @php($selectedQuestionCrmSnapshot = $crmSnapshots->forSubject(auth()->user(), 'question', $selectedQuestion))
                    <div class="border-b border-slate-200 p-4 lg:p-5">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-semibold text-slate-600">{{ \Illuminate\Support\Str::headline($selectedQuestion->store->marketplace) }}</span>
                                    <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-semibold text-slate-600">{{ $selectedQuestion->store->store_name }}</span>
                                    @if($selectedQuestion->matchedRule)
                                        <span class="rounded-full border border-indigo-200 bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700">Kural: {{ $selectedQuestion->matchedRule->name }}</span>
                                    @endif
                                </div>
                                <h2 class="mt-3 text-lg font-semibold text-slate-900">{{ $selectedQuestion->product_name ?: 'Ürün sorusu' }}</h2>
                                <div class="mt-1 flex flex-wrap gap-2 text-xs text-slate-500">
                                    @if($selectedQuestion->product_sku)<span>SKU {{ $selectedQuestion->product_sku }}</span>@endif
                                    @if($selectedQuestion->product_barcode)<span>Barkod {{ $selectedQuestion->product_barcode }}</span>@endif
                                    @if($selectedQuestion->product_url)
                                        <a href="{{ $selectedQuestion->product_url }}" target="_blank" rel="noopener" class="font-semibold text-slate-700 underline decoration-slate-300 underline-offset-2">Ürüne git</a>
                                    @endif
                                </div>
                            </div>
                            <div class="flex flex-col items-start gap-2 sm:items-end">
                                <div class="text-sm text-slate-500">{{ optional($selectedQuestion->asked_at)->format('d.m.Y H:i') }}</div>
                                <a href="{{ $crmLinks->urlFor('question', $selectedQuestion) }}"
                                   class="inline-flex min-h-[36px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                    CRM 360 Aç
                                </a>
                            </div>
                        </div>
                        <x-zolm.crm-snapshot :snapshot="$selectedQuestionCrmSnapshot" variant="panel" class="mt-4" />
                    </div>

                    <div class="space-y-3 p-4 lg:p-5">
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Müşteri sorusu</div>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-800">{{ $selectedQuestion->question_text }}</p>
                        </div>

                        @php($hasSellerAnswerMessage = $selectedQuestion->messages->contains(fn ($message) => $message->direction === 'seller' && trim((string) $message->body) !== ''))
                        @foreach($selectedQuestion->messages as $message)
                            @if($message->body !== $selectedQuestion->question_text || $loop->count > 1)
                                <div class="rounded-[8px] border border-slate-200 bg-white p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-xs font-semibold uppercase tracking-[0.14em] {{ $message->direction === 'seller' ? 'text-emerald-500' : 'text-slate-400' }}">
                                            {{ $message->direction === 'seller' ? 'Satıcı cevabı' : 'Müşteri mesajı' }}
                                        </span>
                                        <span class="text-xs text-slate-400">{{ optional($message->sent_at)->format('d.m.Y H:i') }}</span>
                                    </div>
                                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-800">{{ $message->body }}</p>
                                </div>
                            @endif
                        @endforeach
                        @if($selectedQuestion->answer_text && !$hasSellerAnswerMessage)
                            <div class="rounded-[8px] border border-slate-200 bg-white p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-500">Satıcı cevabı</span>
                                    <span class="text-xs text-slate-400">{{ optional($selectedQuestion->answered_at)->format('d.m.Y H:i') }}</span>
                                </div>
                                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-800">{{ $selectedQuestion->answer_text }}</p>
                            </div>
                        @endif

                        <div class="rounded-[8px] border border-slate-200 bg-white p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Cevap alanı</div>
                                    <p class="mt-1 text-sm text-slate-500">AI veya hazır cevapla taslak oluşturun, son kontrol sonrası gönderin.</p>
                                </div>
                                <div class="flex flex-col gap-2 sm:flex-row">
                                    <button wire:click="generateAiAnswer" type="button" class="rounded-[6px] border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100">AI Taslak</button>
                                    <button wire:click="applyRuleToSelected" type="button" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Kural Dene</button>
                                </div>
                            </div>

                            <textarea wire:model.live.debounce.250ms="answerText" rows="7" class="mt-4 w-full rounded-[8px] border border-slate-200 bg-white px-3 py-3 text-base leading-6 text-slate-900 outline-none transition focus:border-slate-400 sm:text-sm" placeholder="Müşteriye gönderilecek cevabı yazın..."></textarea>
                            @error('answerText')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror

                            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:justify-end">
                                <button wire:click="saveDraft" type="button" class="w-full rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto sm:py-2">Taslak Kaydet</button>
                                <button wire:click="sendAnswer" type="button" class="w-full rounded-[6px] border border-slate-900 bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800 sm:w-auto sm:py-2">Pazaryerine Gönder</button>
                            </div>
                        </div>

                        @if($selectedQuestion->answerLogs->isNotEmpty())
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                                <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">İşlem geçmişi</div>
                                <div class="mt-3 space-y-2">
                                    @foreach($selectedQuestion->answerLogs->take(5) as $log)
                                        <div class="flex flex-col gap-1 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm sm:flex-row sm:items-center sm:justify-between">
                                            <div class="text-slate-700">
                                                <span class="font-semibold">{{ match($log->status) { 'sent' => 'Gönderildi', 'failed' => 'Hata', 'draft' => 'Taslak', default => 'Kuyruk' } }}</span>
                                                <span class="text-slate-400">· {{ $log->source }}</span>
                                            </div>
                                            <div class="text-xs text-slate-400">{{ $log->created_at->format('d.m.Y H:i') }}</div>
                                        </div>
                                        @if($log->error_message)
                                            <div class="rounded-[6px] border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">{{ $log->error_message }}</div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="flex min-h-[520px] items-center justify-center p-6 text-center">
                        <div>
                            <div class="text-lg font-semibold text-slate-900">Henüz soru seçilmedi</div>
                            <p class="mt-2 max-w-sm text-sm text-slate-500">Soldaki havuzdan bir müşteri sorusu seçerek cevaplamaya başlayın.</p>
                        </div>
                    </div>
                @endif
            </main>

            <aside class="space-y-4 bg-slate-50/40 p-4 lg:p-5">
                <div class="rounded-[8px] border border-slate-200 bg-white p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Hazır cevaplar</div>
                            <div class="text-sm font-semibold text-slate-900">{{ $templates->count() }} kayıt</div>
                        </div>
                        <button wire:click="createTemplate" type="button" class="rounded-[6px] border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Ekle</button>
                    </div>
                    <div class="mt-3 space-y-2">
                        @forelse($templates->take(8) as $template)
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-semibold text-slate-900">{{ $template->title }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ $template->category ?: 'Genel' }} @if($template->marketplace) · {{ \Illuminate\Support\Str::headline($template->marketplace) }} @endif</div>
                                    </div>
                                    <span class="rounded-full border px-2 py-0.5 text-xs {{ $template->is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-500' }}">{{ $template->is_active ? 'Aktif' : 'Pasif' }}</span>
                                </div>
                                <p class="mt-2 line-clamp-2 text-sm text-slate-600">{{ $template->body }}</p>
                                <div class="mt-3 grid grid-cols-3 gap-2">
                                    <button wire:click="useTemplate({{ $template->id }})" type="button" class="rounded-[6px] border border-slate-200 bg-white px-2 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Kullan</button>
                                    <button wire:click="editTemplate({{ $template->id }})" type="button" class="rounded-[6px] border border-slate-200 bg-white px-2 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Düzenle</button>
                                    <button wire:click="deleteTemplate({{ $template->id }})" wire:confirm="Bu hazır cevap silinsin mi?" type="button" class="rounded-[6px] border border-red-200 bg-white px-2 py-2 text-xs font-semibold text-red-600 hover:bg-red-50">Sil</button>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">Henüz hazır cevap yok.</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-[8px] border border-slate-200 bg-white p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Otonom kurallar</div>
                            <div class="text-sm font-semibold text-slate-900">{{ $rules->count() }} kayıt</div>
                        </div>
                        <button wire:click="createRule" type="button" class="rounded-[6px] border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Ekle</button>
                    </div>
                    <div class="mt-3 space-y-2">
                        @forelse($rules->take(8) as $rule)
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-semibold text-slate-900">{{ $rule->name }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ $rule->store?->store_name ?: 'Tüm mağazalar' }} · {{ $rule->action_mode === 'auto_send' && !$rule->requires_approval ? 'Direkt gönder' : 'Taslak hazırla' }}</div>
                                    </div>
                                    <span class="rounded-full border px-2 py-0.5 text-xs {{ $rule->is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-500' }}">{{ $rule->is_active ? 'Aktif' : 'Pasif' }}</span>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-1">
                                    @foreach(array_slice($rule->keywords_json ?? [], 0, 4) as $keyword)
                                        <span class="rounded-full border border-slate-200 bg-white px-2 py-0.5 text-xs text-slate-500">{{ $keyword }}</span>
                                    @endforeach
                                </div>
                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    <button wire:click="editRule({{ $rule->id }})" type="button" class="rounded-[6px] border border-slate-200 bg-white px-2 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Düzenle</button>
                                    <button wire:click="deleteRule({{ $rule->id }})" wire:confirm="Bu otonom kural silinsin mi?" type="button" class="rounded-[6px] border border-red-200 bg-white px-2 py-2 text-xs font-semibold text-red-600 hover:bg-red-50">Sil</button>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">Henüz otonom kural yok.</p>
                        @endforelse
                    </div>
                </div>
            </aside>
        </div>
    </section>

    @if($showTemplateForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4">
            <form wire:submit.prevent="saveTemplate" class="w-full max-w-2xl rounded-[10px] border border-slate-200 bg-white shadow-xl">
                <div class="border-b border-slate-200 p-4">
                    <h2 class="text-lg font-semibold text-slate-900">{{ $editingTemplateId ? 'Hazır Cevabı Düzenle' : 'Hazır Cevap Ekle' }}</h2>
                </div>
                <div class="space-y-3 p-4">
                    <input wire:model.defer="templateTitle" type="text" placeholder="Başlık" class="w-full rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:text-sm">
                    @error('templateTitle')<div class="text-sm text-red-600">{{ $message }}</div>@enderror
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <input wire:model.defer="templateCategory" type="text" placeholder="Kategori (örn. Kargo)" class="rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:text-sm">
                        <select wire:model.defer="templateMarketplace" class="rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:text-sm">
                            <option value="">Tüm pazaryerleri</option>
                            @foreach($marketplaces as $marketplace)
                                <option value="{{ $marketplace }}">{{ \Illuminate\Support\Str::headline($marketplace) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <textarea wire:model.defer="templateBody" rows="6" placeholder="Cevap metni. Değişkenler: {urun}, {sku}, {barkod}, {magaza}, {pazaryeri}" class="w-full rounded-[8px] border border-slate-200 px-3 py-3 text-base sm:text-sm"></textarea>
                    @error('templateBody')<div class="text-sm text-red-600">{{ $message }}</div>@enderror
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input wire:model.defer="templateIsActive" type="checkbox" class="rounded border-slate-300">
                        Aktif
                    </label>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3">
                    <button wire:click="resetTemplateForm" type="button" class="rounded-[6px] border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700">İptal</button>
                    <button type="submit" class="rounded-[6px] border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Kaydet</button>
                </div>
            </form>
        </div>
    @endif

    @if($showRuleForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4">
            <form wire:submit.prevent="saveRule" class="w-full max-w-3xl rounded-[10px] border border-slate-200 bg-white shadow-xl">
                <div class="border-b border-slate-200 p-4">
                    <h2 class="text-lg font-semibold text-slate-900">{{ $editingRuleId ? 'Otonom Kuralı Düzenle' : 'Otonom Kural Ekle' }}</h2>
                    <p class="mt-1 text-sm text-slate-500">Direkt gönder modunu sadece çok net ve risksiz sorularda kullanın.</p>
                </div>
                <div class="space-y-3 p-4">
                    <input wire:model.defer="ruleName" type="text" placeholder="Kural adı" class="w-full rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:text-sm">
                    @error('ruleName')<div class="text-sm text-red-600">{{ $message }}</div>@enderror
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <select wire:model.defer="ruleStoreId" class="rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:text-sm">
                            <option value="">Tüm mağazalar</option>
                            @foreach($stores as $store)
                                <option value="{{ $store->id }}">{{ $store->store_name }} · {{ \Illuminate\Support\Str::headline($store->marketplace) }}</option>
                            @endforeach
                        </select>
                        <select wire:model.defer="ruleTemplateId" class="rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:text-sm">
                            <option value="">Hazır cevap seçilmedi</option>
                            @foreach($templates as $template)
                                <option value="{{ $template->id }}">{{ $template->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <select wire:model.defer="ruleMatchType" class="rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:text-sm">
                            <option value="contains">İçeriyorsa</option>
                            <option value="exact">Aynıysa</option>
                            <option value="regex">Regex</option>
                        </select>
                        <select wire:model.defer="ruleActionMode" class="rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:text-sm">
                            <option value="draft">Taslak hazırla</option>
                            <option value="auto_send">Direkt gönder</option>
                        </select>
                        <input wire:model.defer="rulePriority" type="number" min="1" max="9999" placeholder="Öncelik" class="rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:text-sm">
                    </div>
                    <textarea wire:model.defer="ruleKeywords" rows="3" placeholder="Anahtar kelimeler: ölçü, renk, kargo vb. Virgül veya satırla ayırın." class="w-full rounded-[8px] border border-slate-200 px-3 py-3 text-base sm:text-sm"></textarea>
                    @error('ruleKeywords')<div class="text-sm text-red-600">{{ $message }}</div>@enderror
                    <textarea wire:model.defer="ruleResponseText" rows="5" placeholder="Hazır cevap seçmediyseniz kuralın yazacağı cevap metni" class="w-full rounded-[8px] border border-slate-200 px-3 py-3 text-base sm:text-sm"></textarea>
                    @error('ruleResponseText')<div class="text-sm text-red-600">{{ $message }}</div>@enderror
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input wire:model.defer="ruleRequiresApproval" type="checkbox" class="rounded border-slate-300">
                            Göndermeden onay iste
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input wire:model.defer="ruleIsActive" type="checkbox" class="rounded border-slate-300">
                            Aktif
                        </label>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3">
                    <button wire:click="resetRuleForm" type="button" class="rounded-[6px] border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700">İptal</button>
                    <button type="submit" class="rounded-[6px] border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Kaydet</button>
                </div>
            </form>
        </div>
    @endif
</div>
