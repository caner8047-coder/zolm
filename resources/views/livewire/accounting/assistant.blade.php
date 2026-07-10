<div class="w-full space-y-4 lg:space-y-6">
    {{-- Mesaj Paneli --}}
    @if($message !== '')
        <div class="rounded-[8px] border p-4 text-sm {{ $messageType === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
            {{ $message }}
        </div>
    @endif

    {{-- Üst Section --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                    Yapay Zekâ Analiz
                </div>
                <h1 class="mt-3 text-xl font-semibold tracking-tight text-slate-950 lg:text-2xl">ZOLM AI Finans Asistanı</h1>
                <p class="mt-2 text-sm text-slate-500">
                    Nakit akışı, alacak yaşlandırma, kârlılık durumları veya envanter değerlerinize dair soruları doğal dilde sorun ve anlık finansal özetler alın.
                </p>
            </div>
            @if(count($this->chatHistory) > 0)
                <div class="shrink-0">
                    <button wire:click="clearHistory" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-rose-600 bg-rose-50 border border-rose-200 rounded-[6px] hover:bg-rose-100 transition-colors min-h-[44px]">
                        Sohbet Geçmişini Temizle
                    </button>
                </div>
            @endif
        </div>
    </section>

    {{-- Ana Arayüz: İki Kolon --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Sol Kısım: Sohbet Penceresi (2 Kolon) --}}
        <div class="lg:col-span-2 space-y-4">
            <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 flex flex-col justify-between min-h-[500px]">

                {{-- Chat Akışı --}}
                <div class="space-y-4 overflow-y-auto max-h-[400px] flex-1 pr-1 pb-4">
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
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                </svg>
                            </span>
                            <div class="max-w-[80%] bg-slate-50 border border-slate-200 text-slate-800 rounded-[10px] p-4 text-sm space-y-2">
                                <p class="leading-relaxed">{{ $chat->response_text }}</p>
                                <div class="flex justify-end pt-1 border-t border-slate-200/50">
                                    <button wire:click="saveQuestion('{{ $chat->query_text }}')" class="inline-flex items-center gap-1 text-[11px] font-semibold text-indigo-600 hover:text-indigo-900 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                                        </svg>
                                        Sık Kullanılanlara Ekle
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-slate-400 py-24 text-center">
                            Sohbet geçmişi boş. Merak ettiğiniz finansal durumları sormak için aşağıdaki alanı kullanın ya da sağdaki hazır sorulardan birini tıklayın.
                        </div>
                    @endforelse
                </div>

                {{-- Giriş Formu --}}
                <form wire:submit.prevent="askQuestion()" class="border-t border-slate-100 pt-4 flex gap-2">
                    <input type="text" wire:model="questionText" placeholder="Sorunuzu buraya yazın... (Örn: Karlılık durumum nasıl?)" class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                    <button type="submit" class="inline-flex items-center justify-center px-5 py-2 text-sm font-semibold text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[44px]">
                        Gönder
                    </button>
                </form>

            </section>
        </div>

        {{-- Sağ Kısım: Hazır & Kaydedilen Sorular (1 Kolon) --}}
        <div class="space-y-6">

            {{-- Hazır Sorular --}}
            <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-3">
                <h3 class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-2">Hazır Sorular</h3>
                <div class="space-y-2">
                    @foreach($suggestedQuestions as $q)
                        <button type="button" wire:click="askQuestion('{{ $q }}')" class="w-full text-left p-3 text-xs border border-slate-100 rounded-lg hover:border-slate-300 bg-slate-50/50 hover:bg-white text-slate-700 transition-all font-medium">
                            {{ $q }}
                        </button>
                    @endforeach
                </div>
            </section>

            {{-- Kaydedilen Sorular --}}
            <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-3">
                <h3 class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-2">Sık Kullanılan Sorular</h3>
                <div class="divide-y divide-slate-100 max-h-[250px] overflow-y-auto pr-1">
                    @forelse($this->savedQuestions as $sq)
                        <div class="py-2.5 flex justify-between items-center text-xs gap-2">
                            <button type="button" wire:click="askQuestion('{{ $sq->query_text }}')" class="text-left font-medium text-slate-700 hover:text-slate-950 flex-1 truncate">
                                {{ $sq->title }}
                            </button>
                            <button type="button" wire:click="deleteSavedQuestion({{ $sq->id }})" class="text-rose-600 hover:text-rose-900 p-1 rounded hover:bg-rose-50 flex items-center justify-center shrink-0 min-w-[32px] min-h-[32px]">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    @empty
                        <div class="text-xs text-slate-400 py-6 text-center">Henüz sık kullanılan soru eklenmedi.</div>
                    @endforelse
                </div>
            </section>

        </div>
    </div>
</div>
