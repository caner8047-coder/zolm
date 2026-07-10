<div class="w-full space-y-4 lg:space-y-6">

    {{-- Mesaj Paneli --}}
    @if($message !== '')
        <div class="rounded-[8px] border p-4 text-sm {{ $messageType === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
            {{ $message }}
        </div>
    @endif

    {{-- Üst Özet Kartı --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                    AI Asistan
                </div>
                <h1 class="mt-3 text-xl font-semibold tracking-tight text-slate-950 lg:text-2xl">AI Ön Muhasebe Asistanı</h1>
                <p class="mt-2 text-sm text-slate-500">Salt-okunur finansal analiz ve güvenli öneriler. Asistan işlem yapmaz.</p>
            </div>
            @if(count($this->chatHistory) > 0)
                <div class="shrink-0 flex gap-2">
                    <button wire:click="clearHistory"
                            class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-rose-600 bg-rose-50 border border-rose-200 rounded-[6px] hover:bg-rose-100 transition-colors min-h-[44px]">
                        Geçmişi Temizle
                    </button>
                </div>
            @endif
        </div>
    </section>

    {{-- Ana Layout: 2 + 1 grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">

        {{-- Sol / Ana: Sohbet Paneli --}}
        <div class="lg:col-span-2 space-y-4">
            <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm flex flex-col min-h-[560px]">

                {{-- Filtre Çubuğu --}}
                <div class="flex items-center gap-2 px-4 pt-4 flex-wrap">
                    <input type="text" wire:model.live="searchHistory"
                           placeholder="Geçmişte ara…"
                           class="rounded-[6px] border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-slate-400 min-h-[36px] w-44" />
                    <select wire:model.live="selectedIntent"
                            class="rounded-[6px] border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 focus:outline-none focus:border-slate-400 min-h-[36px]">
                        <option value="">Tüm intent'ler</option>
                        <option value="executive_summary">Genel Özet</option>
                        <option value="cash_flow">Nakit Akışı</option>
                        <option value="receivables_aging">Alacak</option>
                        <option value="payables_aging">Borç</option>
                        <option value="income_expense">Gelir-Gider</option>
                        <option value="stock_inventory">Stok</option>
                        <option value="party_balances">Cari Bakiye</option>
                        <option value="unknown">Bilinmeyen</option>
                        <option value="blocked">Engellendi</option>
                    </select>
                </div>

                {{-- Sohbet Akışı --}}
                <div class="flex-1 overflow-y-auto max-h-[420px] space-y-4 p-4 pr-2">
                    @forelse($this->chatHistory as $chat)
                        {{-- Kullanıcı Sorusu --}}
                        <div class="flex justify-end">
                            <div class="max-w-[85%] bg-slate-900 text-white rounded-[10px] px-4 py-2.5 text-sm shadow-sm">
                                {{ $chat->query_text }}
                            </div>
                        </div>

                        {{-- Asistan Cevabı --}}
                        <div class="flex justify-start items-start gap-2.5">
                            <span class="p-1.5 bg-indigo-50 text-indigo-600 rounded-lg shrink-0 mt-0.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                </svg>
                            </span>
                            <div class="max-w-[80%] bg-slate-50 border border-slate-200 text-slate-800 rounded-[10px] p-4 text-sm space-y-3">

                                {{-- Cevap Metni --}}
                                <p class="leading-relaxed">{{ $chat->response_text }}</p>

                                {{-- Intent Badge + Veri Tarihi --}}
                                <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-slate-100">
                                    @if($chat->intent)
                                        <span class="px-2 py-0.5 text-[11px] font-mono rounded bg-slate-100 text-slate-600 border border-slate-200">
                                            {{ $chat->intent }}
                                        </span>
                                    @endif
                                    @if($chat->answered_at)
                                        <span class="text-[11px] text-slate-400">
                                            {{ $chat->answered_at->format('d.m.Y H:i') }}
                                        </span>
                                    @endif
                                    @if($chat->status === 'failed')
                                        <span class="px-2 py-0.5 text-[11px] font-mono rounded bg-rose-50 text-rose-600 border border-rose-100">hata</span>
                                    @endif
                                    @if($chat->status === 'blocked')
                                        <span class="px-2 py-0.5 text-[11px] font-mono rounded bg-amber-50 text-amber-600 border border-amber-100">engellendi</span>
                                    @endif
                                </div>

                                {{-- Kaynak Bilgisi --}}
                                @if(!empty($chat->sources_json))
                                    <div class="pt-1 border-t border-slate-100">
                                        <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wide mb-1">Kaynak</p>
                                        @foreach($chat->sources_json as $src)
                                            <p class="text-[11px] text-slate-500">
                                                {{ $src['service'] ?? '' }}::{{ $src['method'] ?? '' }}
                                                @if(!empty($src['generated_at']))
                                                    — {{ $src['generated_at'] }}
                                                @endif
                                            </p>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Öneriler --}}
                                @if(!empty($chat->suggestions_json))
                                    <div class="pt-1 border-t border-slate-100 space-y-1.5">
                                        <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wide">Öneriler</p>
                                        @foreach($chat->suggestions_json as $sug)
                                            <div class="rounded-[6px] px-3 py-2 text-xs
                                                {{ $sug['severity'] === 'critical' ? 'bg-rose-50 border border-rose-100 text-rose-700' :
                                                   ($sug['severity'] === 'warning'  ? 'bg-amber-50 border border-amber-100 text-amber-700' :
                                                                                       'bg-slate-50 border border-slate-200 text-slate-600') }}">
                                                <span class="font-semibold">{{ $sug['title'] ?? '' }}</span>
                                                @if(!empty($sug['description']))
                                                    — {{ $sug['description'] }}
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Alt Butonlar --}}
                                <div class="flex justify-between items-center pt-1 border-t border-slate-100">
                                    <button wire:click="repeatQuestion({{ $chat->id }})"
                                            class="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-500 hover:text-slate-800 transition-colors">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                        </svg>
                                        Tekrar Sor
                                    </button>
                                    <button wire:click="saveQuestion('{{ addslashes($chat->query_text) }}')"
                                            class="inline-flex items-center gap-1 text-[11px] font-semibold text-indigo-600 hover:text-indigo-900 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>
                                        </svg>
                                        Kaydet
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-slate-400 py-24 text-center">
                            Sohbet geçmişi boş. Merak ettiğiniz finansal durumları sormak için<br>
                            aşağıdaki alanı kullanın ya da sağdaki hazır sorulardan birini tıklayın.
                        </div>
                    @endforelse
                </div>

                {{-- Giriş Formu --}}
                <div class="border-t border-slate-100 p-4">
                    <form wire:submit.prevent="askQuestion()" class="flex gap-2">
                        <input type="text" wire:model="questionText"
                               id="assistant-question-input"
                               placeholder="Sorunuzu yazın… (Örn: Bu ay nakit durumum nasıl?)"
                               class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                        <button type="submit"
                                class="inline-flex items-center justify-center px-5 py-2 text-sm font-semibold text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[44px] shrink-0"
                                wire:loading.attr="disabled" wire:loading.class="opacity-60">
                            <span wire:loading.remove wire:target="askQuestion">Gönder</span>
                            <span wire:loading wire:target="askQuestion">…</span>
                        </button>
                    </form>
                </div>

            </section>
        </div>

        {{-- Sağ Panel: Hazır & Kaydedilen Sorular --}}
        <div class="space-y-4">

            {{-- Hazır Sorular --}}
            <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                <h3 class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-2">Hazır Sorular</h3>
                <div class="space-y-1.5">
                    @foreach($this->suggestedQuestions as $q)
                        <button type="button"
                                wire:click="askQuestion('{{ addslashes($q) }}')"
                                class="w-full text-left p-3 text-xs border border-slate-100 rounded-[6px] hover:border-slate-300 bg-slate-50/50 hover:bg-white text-slate-700 transition-all font-medium min-h-[44px]">
                            {{ $q }}
                        </button>
                    @endforeach
                </div>
            </section>

            {{-- Kaydedilen Sorular --}}
            <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                <h3 class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-2">Sık Kullanılanlar</h3>
                <div class="divide-y divide-slate-100 max-h-[280px] overflow-y-auto pr-1">
                    @forelse($this->savedQuestions as $sq)
                        <div class="py-2.5 flex justify-between items-center text-xs gap-2">
                            <button type="button"
                                    wire:click="askQuestion('{{ addslashes($sq->query_text) }}')"
                                    class="text-left font-medium text-slate-700 hover:text-slate-950 flex-1 truncate min-h-[36px] flex items-center">
                                {{ $sq->title }}
                            </button>
                            <button type="button"
                                    wire:click="deleteSavedQuestion({{ $sq->id }})"
                                    class="text-rose-500 hover:text-rose-700 p-1 rounded hover:bg-rose-50 shrink-0 min-w-[32px] min-h-[32px] flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    @empty
                        <div class="text-xs text-slate-400 py-6 text-center">Henüz kaydedilmiş soru yok.</div>
                    @endforelse
                </div>
            </section>

            {{-- Asistan Notu --}}
            <div class="rounded-[8px] border border-slate-100 bg-slate-50/60 p-4 text-xs text-slate-500 space-y-1">
                <p class="font-semibold text-slate-600">Güvenlik Notu</p>
                <p>Bu asistan yalnızca okuma yapar. Hiçbir finansal işlem başlatmaz.</p>
                <p>Tüm cevaplar yalnızca sizin verilerinizden üretilir.</p>
            </div>

        </div>
    </div>
</div>
