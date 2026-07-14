<div class="space-y-4 lg:space-y-6 p-4 lg:p-6 bg-slate-50/50 min-h-screen">
    {{-- Header Section --}}
    <div class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl lg:text-2xl font-semibold text-slate-900 tracking-tight">Kendi Kendine Gelişme Döngüsü</h1>
                <p class="text-sm text-slate-500 mt-1">Yanıtlanamayan veya düşük puanlı konuşmalardan üretilen bilgi bankası önerileri.</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3">
                {{-- Store Selector --}}
                <div class="w-full sm:w-64">
                    <label for="store_filter" class="sr-only">Mağaza Seçin</label>
                    <select id="store_filter" wire:model.live="selectedStoreId" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-slate-900 focus:border-slate-400 focus:outline-none min-h-[44px]">
                        @foreach($myStores as $store)
                            <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- Status Filter --}}
                <div class="w-full sm:w-48">
                    <label for="status_filter" class="sr-only">Durum Filtresi</label>
                    <select id="status_filter" wire:model.live="selectedStatus" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-slate-900 focus:border-slate-400 focus:outline-none min-h-[44px]">
                        <option value="pending">Bekleyen (Pending)</option>
                        <option value="applied">Kabul Edilen (Applied)</option>
                        <option value="rejected">Reddedilen (Rejected)</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Messages/Notifications --}}
    @if ($successMessage)
        <div class="p-4 rounded-[8px] bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium flex items-center gap-2">
            <svg class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            {{ $successMessage }}
        </div>
    @endif

    @if ($errorMessage)
        <div class="p-4 rounded-[8px] bg-red-50 border border-red-200 text-red-800 text-sm font-medium flex items-center gap-2">
            <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            {{ $errorMessage }}
        </div>
    @endif

    {{-- Main Workspace Layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        {{-- Left: Suggestions List --}}
        <div class="lg:col-span-2 space-y-4">
            @forelse($suggestions as $suggestion)
                <div class="rounded-[10px] border border-slate-200 bg-white p-5 shadow-sm space-y-4 hover:border-slate-300 transition-colors">
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="px-2 py-0.5 text-xs font-semibold font-mono rounded bg-slate-100 text-slate-700">
                                    {{ strtoupper($suggestion->category) }}
                                </span>
                                <span class="px-2 py-0.5 text-xs font-semibold font-mono rounded bg-slate-100 text-slate-700">
                                    SKOR: %{{ $suggestion->confidence }}
                                </span>
                                @if($suggestion->status === 'applied')
                                    <span class="px-2 py-0.5 text-xs font-semibold font-mono rounded bg-emerald-100 text-emerald-800">
                                        EKLENDİ
                                    </span>
                                @elseif($suggestion->status === 'rejected')
                                    <span class="px-2 py-0.5 text-xs font-semibold font-mono rounded bg-red-100 text-red-800">
                                        REDDEDİLDİ
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 text-xs font-semibold font-mono rounded bg-amber-100 text-amber-800">
                                        BEKLEMEDE
                                    </span>
                                @endif
                            </div>
                            <h2 class="text-lg font-semibold text-slate-900 mt-2">{{ $suggestion->title }}</h2>
                        </div>
                        <span class="text-xs text-slate-400 font-mono">{{ $suggestion->created_at->diffForHumans() }}</span>
                    </div>

                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4 text-slate-700 text-sm leading-relaxed whitespace-pre-line">
                        {{ $suggestion->proposed_answer }}
                    </div>

                    @if($suggestion->status === 'pending')
                        <div class="flex flex-wrap items-center gap-2 pt-2">
                            <button wire:click="approve({{ $suggestion->id }})" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 hover:bg-slate-800 text-white font-medium text-sm rounded-[6px] transition-colors min-h-[44px] sm:min-h-[38px] flex items-center justify-center gap-1">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Onayla ve Yayınla
                            </button>
                            <button wire:click="editSuggestion({{ $suggestion->id }})" class="w-full sm:w-auto px-4 py-3 sm:py-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-medium text-sm rounded-[6px] transition-colors min-h-[44px] sm:min-h-[38px] flex items-center justify-center gap-1">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                                Düzenle
                            </button>
                            <button wire:click="reject({{ $suggestion->id }})" class="w-full sm:w-auto px-4 py-3 sm:py-2 border border-slate-200 bg-white hover:bg-red-50 hover:text-red-700 font-medium text-sm rounded-[6px] transition-colors min-h-[44px] sm:min-h-[38px] flex items-center justify-center gap-1">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                Reddet
                            </button>
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-[10px] border border-slate-200 bg-white p-12 text-center shadow-sm">
                    <svg class="w-12 h-12 text-slate-300 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                    </svg>
                    <h3 class="text-base font-semibold text-slate-900">Öneri Bulunamadı</h3>
                    <p class="text-sm text-slate-500 mt-1">Bu durum ve mağaza filtresine uygun bekleyen öneri bulunmamaktadır.</p>
                </div>
            @endforelse

            {{-- Pagination Links --}}
            <div class="pt-2">
                {{ $suggestions->links() }}
            </div>
        </div>

        {{-- Right: Editor Panel (Conditionally Shown) --}}
        <div class="lg:col-span-1">
            @if($editingSuggestionId)
                <div class="rounded-[10px] border border-slate-200 bg-white p-5 shadow-sm space-y-4 sticky top-6">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h2 class="font-semibold text-slate-900 flex items-center gap-1.5">
                            <svg class="w-5 h-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Öneriyi Düzenle
                        </h2>
                        <button wire:click="cancelEdit" class="text-slate-400 hover:text-slate-600 transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label for="edit_title" class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Makale Başlığı</label>
                            <input id="edit_title" type="text" wire:model="editTitle" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-slate-900 focus:border-slate-400 focus:outline-none min-h-[44px]" placeholder="Örn: Kargo Süreci">
                        </div>

                        <div>
                            <label for="edit_category" class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Kategori</label>
                            <input id="edit_category" type="text" wire:model="editCategory" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-slate-900 focus:border-slate-400 focus:outline-none min-h-[44px]" placeholder="Örn: kargo">
                        </div>

                        <div>
                            <label for="edit_answer" class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Önerilen Cevap İçeriği</label>
                            <textarea id="edit_answer" rows="8" wire:model="editProposedAnswer" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-slate-900 focus:border-slate-400 focus:outline-none leading-relaxed" placeholder="Bilgi bankasında saklanacak detaylı açıklama..."></textarea>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
                        <button wire:click="saveEdit" class="w-full sm:w-auto px-4 py-3 sm:py-2 flex-1 bg-slate-900 hover:bg-slate-800 text-white font-medium text-sm rounded-[6px] transition-colors min-h-[44px] sm:min-h-[38px]">
                            Öneriyi Güncelle
                        </button>
                        <button wire:click="cancelEdit" class="w-full sm:w-auto px-4 py-3 sm:py-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-medium text-sm rounded-[6px] transition-colors min-h-[44px] sm:min-h-[38px]">
                            İptal
                        </button>
                    </div>
                </div>
            @else
                <div class="rounded-[10px] border border-slate-200 bg-white p-5 shadow-sm text-center text-slate-400 space-y-2 sticky top-6">
                    <svg class="w-8 h-8 mx-auto text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <p class="text-sm font-medium text-slate-600">Öneri Düzenleyici</p>
                    <p class="text-xs text-slate-400">Soldaki listeden bir önerinin "Düzenle" butonuna tıklayarak içeriğini güncelleyebilirsiniz.</p>
                </div>
            @endif
        </div>
    </div>
</div>
