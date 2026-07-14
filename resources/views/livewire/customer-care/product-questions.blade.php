<div class="space-y-4 lg:space-y-6 p-4 lg:p-6 bg-slate-50/50 min-h-screen">
    {{-- Workspace / Summary --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="min-w-0">
                <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-mono bg-slate-100 text-slate-600 uppercase tracking-wider">Bilgi ve Kalite</span>
                <h1 class="mt-2 text-xl lg:text-2xl font-semibold text-slate-900 tracking-tight">Ürün Soruları ve AI Eğitim Havuzu</h1>
                <p class="mt-1 text-sm text-slate-500 max-w-3xl">
                    Pazaryerlerindeki gerçek ürün soru-cevaplarını tek yerde inceleyin. Ham içerik doğrudan AI'a öğretilmez; yalnız PII temizliği ve insan onayından geçen kayıtlar bilgi tabanında kullanılır.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                <select wire:model.live="selectedStoreId" aria-label="Mağaza seçin"
                        class="w-full sm:w-64 text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-slate-900 focus:border-slate-400 focus:outline-none min-h-[44px]">
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}">{{ $store->store_name }} · {{ ucfirst($store->marketplace) }}</option>
                    @endforeach
                </select>
                <button type="button" wire:click="syncQuestions" wire:loading.attr="disabled" wire:target="syncQuestions"
                        class="w-full sm:w-auto px-4 py-3 sm:py-2 min-h-[44px] rounded-[6px] bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 disabled:opacity-60 flex items-center justify-center gap-2">
                    <svg wire:loading.remove wire:target="syncQuestions" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <svg wire:loading wire:target="syncQuestions" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="syncQuestions">Soru ve Cevapları Çek</span>
                    <span wire:loading wire:target="syncQuestions">Senkronize ediliyor…</span>
                </button>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3 lg:gap-4">
            @foreach([
                ['label' => 'Toplam Kayıt', 'value' => $metrics['total'], 'tone' => 'slate'],
                ['label' => 'Cevaplanmış', 'value' => $metrics['answered'], 'tone' => 'blue'],
                ['label' => 'İnceleme Adayı', 'value' => $metrics['candidate'], 'tone' => 'amber'],
                ['label' => 'Bilgi Tabanında', 'value' => $metrics['applied'], 'tone' => 'emerald'],
                ['label' => 'Golden Adayı', 'value' => $metrics['golden'], 'tone' => 'violet'],
            ] as $metric)
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4 min-w-0">
                    <p class="text-xs font-mono uppercase tracking-wider text-slate-500">{{ $metric['label'] }}</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($metric['value'], 0, ',', '.') }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Guidance --}}
    <section x-data="{ open: false }" class="rounded-[10px] border border-slate-200 bg-white shadow-sm overflow-hidden">
        <button type="button" @click="open = !open" class="w-full px-4 lg:px-6 py-3 flex items-center justify-between gap-3 text-left min-h-[44px]">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-900">Güvenli öğrenme akışı nasıl çalışır?</p>
                <p class="text-xs text-slate-500 mt-0.5">Siparişe özel ve riskli cevaplar yeniden kullanım dışında tutulur.</p>
            </div>
            <svg class="w-5 h-5 text-slate-400 transition-transform" :class="open && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
        <div x-show="open" x-collapse class="border-t border-slate-100 px-4 lg:px-6 py-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 lg:gap-4">
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                    <p class="text-sm font-semibold text-slate-900">1. Kaynak</p>
                    <p class="mt-1 text-xs text-slate-500">Soru, ürün ve pazaryeri cevabı kaynak kimliğiyle korunur.</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                    <p class="text-sm font-semibold text-slate-900">2. Güvenlik</p>
                    <p class="mt-1 text-xs text-slate-500">PII maskelenir; prompt injection ve kişiye özel cevaplar engellenir.</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                    <p class="text-sm font-semibold text-slate-900">3. İnsan onayı</p>
                    <p class="mt-1 text-xs text-slate-500">Aday kayıt Bilgi Bankası Önerileri ekranında düzenlenip onaylanır.</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                    <p class="text-sm font-semibold text-slate-900">4. Kullanım</p>
                    <p class="mt-1 text-xs text-slate-500">Onaylanan ürün bilgisi süreli ve kaynaklı RAG bağlamı olarak kullanılır.</p>
                </div>
            </div>
        </div>
    </section>

    @if($successMessage)
        <div class="rounded-[8px] border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800" role="status">{{ $successMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-[8px] border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $errorMessage }}</div>
    @endif

    {{-- Command bar + ledger --}}
    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm overflow-visible">
        <div class="p-4 lg:p-6 border-b border-slate-100 bg-slate-50/60">
            <div class="flex flex-col xl:flex-row xl:items-end xl:justify-between gap-3 lg:gap-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 flex-1">
                    <div class="sm:col-span-2">
                        <label for="product-question-search" class="block text-xs font-medium text-slate-600 mb-1">Soru, cevap veya ürün ara</label>
                        <input id="product-question-search" type="search" wire:model.live.debounce.350ms="search"
                               placeholder="Örn. ölçü, renk, SKU…"
                               class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 min-h-[44px] focus:border-slate-400 focus:outline-none">
                    </div>
                    <div>
                        <label for="answer-filter" class="block text-xs font-medium text-slate-600 mb-1">Cevap durumu</label>
                        <select id="answer-filter" wire:model.live="answerFilter" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 min-h-[44px] focus:border-slate-400 focus:outline-none">
                            <option value="all">Tüm kayıtlar</option>
                            <option value="answered">Cevaplanmış</option>
                            <option value="unanswered">Yanıt bekleyen</option>
                        </select>
                    </div>
                    <div>
                        <label for="learning-filter" class="block text-xs font-medium text-slate-600 mb-1">AI eğitim durumu</label>
                        <select id="learning-filter" wire:model.live="learningFilter" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 min-h-[44px] focus:border-slate-400 focus:outline-none">
                            <option value="all">Tüm durumlar</option>
                            <option value="new">Yeni</option>
                            <option value="candidate">İnceleme adayı</option>
                            <option value="applied">Bilgi tabanında</option>
                            <option value="golden">Golden adayı</option>
                            <option value="excluded">Eğitim dışı</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center gap-2" x-data="{ columnsOpen: false }">
                    <span class="text-xs text-slate-500">
                        Aktif filtre: {{ $learningFilter === 'all' ? 'Tüm eğitim durumları' : ucfirst($learningFilter) }}
                    </span>
                    <div class="relative">
                        <button type="button" @click="columnsOpen = !columnsOpen" @click.outside="columnsOpen = false"
                                class="w-full sm:w-auto px-4 py-3 sm:py-2 min-h-[44px] rounded-[6px] border border-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 flex items-center justify-center gap-2">
                            Kolonlar <span class="font-mono text-xs text-slate-400">{{ count($visibleColumns) }}/{{ count($columnLabels) }}</span>
                        </button>
                        <div x-show="columnsOpen" x-transition class="absolute right-0 z-30 mt-2 w-64 rounded-[8px] border border-slate-200 bg-white p-2 shadow-lg">
                            @foreach($columnLabels as $column => $label)
                                <label class="flex items-center gap-2 rounded-[6px] px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 cursor-pointer">
                                    <input type="checkbox" wire:click="toggleColumn('{{ $column }}')" @checked(in_array($column, $visibleColumns, true)) class="rounded border-slate-300 text-slate-900 focus:ring-slate-400">
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Mobile cards --}}
        <div class="md:hidden divide-y divide-slate-100">
            @forelse($questions as $question)
                @php($eligibility = $question->learning_eligibility)
                <article class="p-4 space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900 truncate">{{ $question->product_name ?: 'Ürün eşleşmedi' }}</p>
                            <p class="mt-0.5 text-xs font-mono text-slate-500">{{ $question->product_sku ?: $question->product_barcode ?: 'SKU yok' }} · {{ ucfirst($question->store->marketplace) }}</p>
                        </div>
                        @include('livewire.customer-care.partials.product-question-learning-badge', ['question' => $question])
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Müşteri sorusu</p>
                        <p class="mt-1 text-sm text-slate-800 break-words">{{ $question->question_text }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Yayınlanmış cevap</p>
                        <p class="mt-1 text-sm text-slate-700 break-words">{{ $question->answer_text ?: 'Henüz yayınlanmış cevap yok.' }}</p>
                    </div>
                    <p class="text-xs {{ $eligibility['eligible'] ? 'text-emerald-700' : 'text-amber-700' }}">{{ $eligibility['reason'] }}</p>
                    @include('livewire.customer-care.partials.product-question-actions', ['question' => $question, 'eligibility' => $eligibility])
                </article>
            @empty
                <div class="p-10 text-center">
                    <p class="text-sm font-semibold text-slate-900">Ürün sorusu bulunamadı</p>
                    <p class="mt-1 text-sm text-slate-500">Filtreleri değiştirin veya seçili mağazadan soru-cevapları çekin.</p>
                </div>
            @endforelse
        </div>

        {{-- Desktop ledger --}}
        <div class="hidden md:block overflow-x-auto rounded-b-lg" x-data="columnResize()">
            <table class="w-full table-fixed text-left text-sm">
                <thead class="bg-slate-50/80 text-xs uppercase tracking-wider text-slate-500">
                    <tr class="border-b border-slate-200">
                        @if(in_array('product', $visibleColumns, true))
                            <th class="relative w-48 px-4 py-3 font-semibold cursor-pointer" wire:click="sortTable('product_name')">Ürün <span class="text-slate-300">↕</span><span @mousedown="startResize($event)" class="absolute right-0 top-0 h-full w-1 cursor-col-resize"></span></th>
                        @endif
                        @if(in_array('marketplace', $visibleColumns, true))
                            <th class="relative w-28 px-4 py-3 font-semibold">Kanal<span @mousedown="startResize($event)" class="absolute right-0 top-0 h-full w-1 cursor-col-resize"></span></th>
                        @endif
                        @if(in_array('question', $visibleColumns, true))
                            <th class="relative w-64 px-4 py-3 font-semibold">Müşteri Sorusu<span @mousedown="startResize($event)" class="absolute right-0 top-0 h-full w-1 cursor-col-resize"></span></th>
                        @endif
                        @if(in_array('answer', $visibleColumns, true))
                            <th class="relative w-64 px-4 py-3 font-semibold">Yayınlanmış Cevap<span @mousedown="startResize($event)" class="absolute right-0 top-0 h-full w-1 cursor-col-resize"></span></th>
                        @endif
                        @if(in_array('learning', $visibleColumns, true))
                            <th class="relative w-56 px-4 py-3 font-semibold cursor-pointer" wire:click="sortTable('learning_status')">AI Eğitim Durumu <span class="text-slate-300">↕</span><span @mousedown="startResize($event)" class="absolute right-0 top-0 h-full w-1 cursor-col-resize"></span></th>
                        @endif
                        @if(in_array('date', $visibleColumns, true))
                            <th class="relative w-32 px-4 py-3 font-semibold cursor-pointer" wire:click="sortTable('asked_at')">Tarih <span class="text-slate-300">↕</span><span @mousedown="startResize($event)" class="absolute right-0 top-0 h-full w-1 cursor-col-resize"></span></th>
                        @endif
                        <th class="w-56 px-4 py-3 font-semibold text-right">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($questions as $question)
                        @php($eligibility = $question->learning_eligibility)
                        <tr class="hover:bg-slate-50/60 align-top">
                            @if(in_array('product', $visibleColumns, true))
                                <td class="px-4 py-4 overflow-hidden">
                                    <p class="font-semibold text-slate-900 truncate" title="{{ $question->product_name }}">{{ $question->product_name ?: 'Ürün eşleşmedi' }}</p>
                                    <p class="mt-1 text-xs font-mono text-slate-500 truncate">{{ $question->product_sku ?: $question->product_barcode ?: 'SKU yok' }}</p>
                                </td>
                            @endif
                            @if(in_array('marketplace', $visibleColumns, true))
                                <td class="px-4 py-4 overflow-hidden"><span class="px-2 py-0.5 text-xs font-mono rounded bg-slate-100 text-slate-700">{{ strtoupper($question->store->marketplace) }}</span></td>
                            @endif
                            @if(in_array('question', $visibleColumns, true))
                                <td class="px-4 py-4 overflow-hidden"><p class="text-slate-800 line-clamp-3" title="{{ $question->question_text }}">{{ $question->question_text }}</p></td>
                            @endif
                            @if(in_array('answer', $visibleColumns, true))
                                <td class="px-4 py-4 overflow-hidden"><p class="text-slate-600 line-clamp-3" title="{{ $question->answer_text }}">{{ $question->answer_text ?: 'Henüz yayınlanmış cevap yok.' }}</p></td>
                            @endif
                            @if(in_array('learning', $visibleColumns, true))
                                <td class="px-4 py-4 overflow-hidden space-y-2">
                                    @include('livewire.customer-care.partials.product-question-learning-badge', ['question' => $question])
                                    <p class="text-xs {{ $eligibility['eligible'] ? 'text-emerald-700' : 'text-amber-700' }} line-clamp-2" title="{{ $eligibility['reason'] }}">{{ $eligibility['reason'] }}</p>
                                </td>
                            @endif
                            @if(in_array('date', $visibleColumns, true))
                                <td class="px-4 py-4 overflow-hidden text-xs text-slate-500">{{ $question->asked_at?->format('d.m.Y H:i') ?: '—' }}</td>
                            @endif
                            <td class="px-4 py-4">@include('livewire.customer-care.partials.product-question-actions', ['question' => $question, 'eligibility' => $eligibility])</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-12 text-center text-sm text-slate-500">Filtrelere uygun ürün sorusu bulunamadı.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-100 px-4 py-3">{{ $questions->links() }}</div>
    </section>
</div>

@script
<script>
    Alpine.data('columnResize', () => ({
        resizing: false,
        startX: 0,
        startWidth: 0,
        currentTh: null,
        startResize(event) {
            event.preventDefault();
            event.stopPropagation();
            this.resizing = true;
            this.startX = event.clientX;
            this.currentTh = event.target.closest('th');
            this.startWidth = this.currentTh.offsetWidth;
            const move = (moveEvent) => {
                if (!this.resizing || !this.currentTh) return;
                this.currentTh.style.width = Math.max(96, this.startWidth + moveEvent.clientX - this.startX) + 'px';
            };
            const stop = () => {
                this.resizing = false;
                document.removeEventListener('mousemove', move);
                document.removeEventListener('mouseup', stop);
            };
            document.addEventListener('mousemove', move);
            document.addEventListener('mouseup', stop);
        },
    }));
</script>
@endscript
