@if($this->activeReport)
    @once
        <style>
            [x-cloak] {
                display: none !important;
            }
        </style>
    @endonce

    @php
        $report = $this->activeReport;
        $reportLabel = \Illuminate\Support\Str::limit($report->original_filename ?: $report->name, 38);
    @endphp

    <div
        x-data="{
            isAIPanelOpen: false,
            activeTab: 'campaign',
            init() {
                window.addEventListener('openAiPanel', (event) => {
                    this.isAIPanelOpen = true;
                    this.activeTab = event.detail?.tab || 'campaign';
                });
            }
        }"
        x-on:keydown.escape.window="isAIPanelOpen = false"
    >
        <div
            x-show="isAIPanelOpen"
            x-cloak
            x-transition.opacity
            class="fixed inset-0 z-40 bg-slate-900/20"
            x-on:click="isAIPanelOpen = false"
        ></div>

        <aside
            x-show="isAIPanelOpen"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-x-full opacity-80"
            x-transition:enter-end="translate-x-0 opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0 opacity-100"
            x-transition:leave-end="translate-x-full opacity-80"
            class="fixed inset-y-0 right-0 z-50 flex w-full flex-col border-l border-slate-200 bg-white shadow-xl sm:max-w-xl"
        >
            <header class="border-b border-slate-200 bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="inline-flex rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-semibold text-slate-600">AI karar merkezi</div>
                        <h2 class="mt-2 text-lg font-semibold text-slate-900">Rapor asistanı</h2>
                        <p class="mt-1 truncate text-xs text-slate-500">{{ $reportLabel }}</p>
                    </div>
                    <button
                        type="button"
                        x-on:click="isAIPanelOpen = false"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-900"
                        aria-label="AI panelini kapat"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="mt-4 grid grid-cols-3 gap-2 rounded-[8px] border border-slate-200 bg-slate-50/70 p-1">
                    <button
                        type="button"
                        x-on:click="activeTab = 'campaign'"
                        class="rounded-[6px] px-3 py-2 text-xs font-semibold transition"
                        :class="activeTab === 'campaign' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900'"
                    >
                        Strateji
                    </button>
                    <button
                        type="button"
                        x-on:click="activeTab = 'loss'"
                        class="rounded-[6px] px-3 py-2 text-xs font-semibold transition"
                        :class="activeTab === 'loss' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900'"
                    >
                        Zarar
                    </button>
                    <button
                        type="button"
                        wire:click="toggleChat"
                        x-on:click="activeTab = 'chat'"
                        class="rounded-[6px] px-3 py-2 text-xs font-semibold transition"
                        :class="activeTab === 'chat' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900'"
                    >
                        Sohbet
                    </button>
                </div>
            </header>

            <div class="min-h-0 flex-1 overflow-y-auto bg-slate-50/60">
                <section x-show="activeTab === 'campaign'" x-cloak class="p-4">
                    <div class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold text-slate-900">Stratejik analiz</h3>
                                <p class="mt-1 text-xs text-slate-500">Fırsatları, fiyat kararlarını ve aksiyon sırasını rapor verisinden çıkarır.</p>
                            </div>
                            <button
                                type="button"
                                wire:click="generateAIAnalysis"
                                wire:loading.attr="disabled"
                                class="inline-flex min-h-[40px] items-center justify-center rounded-[6px] bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:opacity-60"
                            >
                                <span wire:loading.remove wire:target="generateAIAnalysis">{{ $report->ai_analysis ? 'Yenile' : 'Başlat' }}</span>
                                <span wire:loading wire:target="generateAIAnalysis">İşleniyor...</span>
                            </button>
                        </div>

                        <div class="relative mt-4">
                            <div wire:loading wire:target="generateAIAnalysis" class="absolute inset-0 z-10 flex items-center justify-center rounded-[8px] bg-white/80">
                                <div class="h-8 w-8 animate-spin rounded-full border-2 border-slate-200 border-t-slate-900"></div>
                            </div>

                            @if($report->ai_analysis)
                                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4 text-sm leading-6 text-slate-700">
                                    {!! \Illuminate\Support\Str::markdown($report->ai_analysis) !!}
                                </div>
                            @else
                                <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                                    <p class="text-sm font-semibold text-slate-900">Henüz AI stratejisi yok</p>
                                    <p class="mt-1 text-sm text-slate-500">Başlattığınızda AI, en yüksek kâr fırsatlarını ve dikkat edilmesi gereken ürünleri özetler.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </section>

                <section x-show="activeTab === 'loss'" x-cloak class="p-4">
                    <div class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold text-slate-900">Zarar denetimi</h3>
                                <p class="mt-1 text-xs text-slate-500">Zarar eden satırlarda kök neden ve fiyat/maliyet aksiyonu üretir.</p>
                            </div>
                            <button
                                type="button"
                                wire:click="analyzeLosses"
                                wire:loading.attr="disabled"
                                class="inline-flex min-h-[40px] items-center justify-center rounded-[6px] bg-rose-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-rose-700 disabled:opacity-60"
                            >
                                <span wire:loading.remove wire:target="analyzeLosses">{{ $report->loss_analysis ? 'Yenile' : 'Denetle' }}</span>
                                <span wire:loading wire:target="analyzeLosses">Denetleniyor...</span>
                            </button>
                        </div>

                        <div class="relative mt-4">
                            <div wire:loading wire:target="analyzeLosses" class="absolute inset-0 z-10 flex items-center justify-center rounded-[8px] bg-white/80">
                                <div class="h-8 w-8 animate-spin rounded-full border-2 border-rose-200 border-t-rose-600"></div>
                            </div>

                            @if($report->loss_analysis)
                                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4 text-sm leading-6 text-slate-700">
                                    {!! \Illuminate\Support\Str::markdown($report->loss_analysis) !!}
                                </div>
                            @else
                                <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                                    <p class="text-sm font-semibold text-slate-900">Zarar denetimi bekliyor</p>
                                    <p class="mt-1 text-sm text-slate-500">Denetim, negatif kâr yapan ürünlerin ana nedenini ve çıkış aksiyonunu gösterir.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </section>

                <section x-show="activeTab === 'chat'" x-cloak class="flex min-h-full flex-col">
                    <div id="chat-messages" class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4">
                        @if($this->chatConversation && count($this->chatConversation->messages ?? []) > 0)
                            @foreach($this->chatConversation->messages as $msg)
                                @if(($msg['role'] ?? '') === 'user')
                                    <div class="flex justify-end">
                                        <div class="max-w-[86%] rounded-[10px] bg-slate-900 px-3 py-2 text-sm leading-6 text-white">
                                            {{ $msg['content'] }}
                                        </div>
                                    </div>
                                @else
                                    <div class="flex justify-start">
                                        <div class="max-w-[90%] rounded-[10px] border border-slate-200 bg-white px-3 py-2 text-sm leading-6 text-slate-700 shadow-sm">
                                            {!! \Illuminate\Support\Str::markdown($msg['content'] ?? '') !!}
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        @else
                            <div class="rounded-[10px] border border-dashed border-slate-300 bg-white p-6 text-center">
                                <p class="text-sm font-semibold text-slate-900">Rapor hakkında soru sorun</p>
                                <p class="mt-1 text-sm text-slate-500">Örneğin: “En riskli 5 ürün hangisi?” veya “Bu tarife seçimleri export için güvenli mi?”</p>
                            </div>
                        @endif

                        @if($isChatting)
                            <div class="flex justify-start">
                                <div class="rounded-[10px] border border-slate-200 bg-white px-3 py-2 text-sm text-slate-500 shadow-sm">
                                    Yanıt hazırlanıyor...
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="border-t border-slate-200 bg-white p-3">
                        <div class="flex gap-2">
                            <input
                                type="text"
                                wire:model="chatMessage"
                                wire:keydown.enter="sendMessage"
                                placeholder="Rapor, ürün veya fiyat kararı sorun"
                                class="min-h-[44px] flex-1 rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-slate-900 sm:py-2 sm:text-sm"
                            >
                            <button
                                type="button"
                                wire:click="sendMessage"
                                wire:loading.attr="disabled"
                                class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:opacity-60"
                            >
                                <span wire:loading.remove wire:target="sendMessage">Gönder</span>
                                <span wire:loading wire:target="sendMessage">...</span>
                            </button>
                        </div>
                    </div>
                </section>
            </div>
        </aside>
    </div>
@endif
