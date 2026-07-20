{{-- COGS Sihirbazı Modalı
     Tetikleyici: wire:click="openCogsWizard"
     Kategori bazında COGS eksik ürünleri listeler, tek adımda atama yapılmasını sağlar.
--}}
<div x-data="{ open: @entangle('showCogsWizard') }"
     x-show="open"
     x-cloak
     @keydown.escape.window="open = false; $wire.closeCogsWizard()"
     class="relative z-50"
     style="display: none;">

    {{-- Backdrop --}}
    <div x-show="open"
         x-transition.opacity
         class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm"
         wire:click="closeCogsWizard"></div>

    {{-- Modal panel --}}
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div x-show="open"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95"
                 class="relative w-full transform overflow-hidden rounded-[10px] bg-white text-left shadow-xl transition-all sm:my-8 sm:max-w-2xl">

                {{-- Başlık --}}
                <div class="flex items-start justify-between border-b border-slate-100 px-5 py-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-[6px] bg-amber-100">
                                <svg class="h-4 w-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </div>
                            <h3 class="text-base font-semibold text-slate-900">COGS Sihirbazı</h3>
                            @if(!empty($cogsWizardCategories))
                                @php $totalMissing = collect($cogsWizardCategories)->sum('count'); @endphp
                                <span class="rounded-[4px] bg-amber-100 px-2 py-0.5 text-xs font-mono font-semibold text-amber-700">{{ $totalMissing }} ürün eksik</span>
                            @endif
                        </div>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Maliyeti tanımsız ürünleri kategorilere göre gruplayarak hızlıca girebilirsiniz.
                            Boş bıraktığınız kategoriler atlanır.
                        </p>
                    </div>
                    <button type="button" wire:click="closeCogsWizard"
                            class="ml-3 shrink-0 rounded-[6px] p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Sonuç banner'ı (atama yapıldıktan sonra) --}}
                @if($cogsWizardResult)
                    <div class="mx-5 mt-4 rounded-[8px] border border-emerald-200 bg-emerald-50 px-4 py-3">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span class="text-sm font-medium text-emerald-800">
                                {{ $cogsWizardResult['updated'] }} ürün güncellendi
                                @if($cogsWizardResult['skipped'] > 0)
                                    · {{ $cogsWizardResult['skipped'] }} ürün atlandı (boş bırakılan kategoriler)
                                @endif
                            </span>
                        </div>
                    </div>
                @endif

                {{-- İçerik --}}
                <div class="max-h-[60vh] overflow-y-auto px-5 py-4">

                    @if(empty($cogsWizardCategories))
                        {{-- Tüm maliyetler girilmiş --}}
                        <div class="flex flex-col items-center justify-center py-10 text-center">
                            <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100">
                                <svg class="h-7 w-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <p class="text-base font-semibold text-slate-900">Tüm ürünlerin maliyeti tanımlı!</p>
                            <p class="mt-1 text-sm text-slate-500">Kâr hesabı %100 güvenilir durumda.</p>
                        </div>

                    @else
                        {{-- Kategori tablosu --}}
                        <div class="mb-3 grid grid-cols-12 gap-2 px-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                            <span class="col-span-4">Kategori</span>
                            <span class="col-span-1 text-right">Ürün</span>
                            <span class="col-span-2 text-right">Ort. Fiyat</span>
                            <span class="col-span-2.5">COGS (₺)</span>
                            <span class="col-span-2.5">Ambalaj (₺)</span>
                        </div>

                        <div class="space-y-2">
                            @foreach($cogsWizardCategories as $cat => $catData)
                                @php
                                    $hasCogs = filled($cogsWizardInputs[$cat]['cogs'] ?? '');
                                    $hasPackaging = filled($cogsWizardInputs[$cat]['packaging_cost'] ?? '');
                                    $rowHighlight = ($hasCogs || $hasPackaging) ? 'border-emerald-200 bg-emerald-50/60' : 'border-slate-200 bg-slate-50/60';
                                @endphp
                                <div class="grid grid-cols-12 items-center gap-2 rounded-[8px] border {{ $rowHighlight }} px-3 py-2.5 transition-colors">
                                    {{-- Kategori adı --}}
                                    <div class="col-span-4 min-w-0">
                                        <p class="truncate text-sm font-medium text-slate-800">{{ $cat }}</p>
                                    </div>

                                    {{-- Ürün sayısı --}}
                                    <div class="col-span-1 text-right">
                                        <span class="rounded-[4px] bg-slate-200 px-1.5 py-0.5 text-xs font-mono text-slate-700">{{ $catData['count'] }}</span>
                                    </div>

                                    {{-- Ortalama satış fiyatı (öneri) --}}
                                    <div class="col-span-2 text-right">
                                        <span class="text-xs text-slate-400">₺{{ number_format($catData['avg_price'], 0, ',', '.') }}</span>
                                    </div>

                                    {{-- COGS input --}}
                                    <div class="col-span-2.5">
                                        <input type="number"
                                               wire:model.live="cogsWizardInputs.{{ $cat }}.cogs"
                                               min="0"
                                               step="0.01"
                                               placeholder="0,00"
                                               class="w-full rounded-[6px] border border-slate-200 bg-white px-2 py-1.5 text-right text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                    </div>

                                    {{-- Ambalaj input --}}
                                    <div class="col-span-2.5">
                                        <input type="number"
                                               wire:model.live="cogsWizardInputs.{{ $cat }}.packaging_cost"
                                               min="0"
                                               step="0.01"
                                               placeholder="0,00"
                                               class="w-full rounded-[6px] border border-slate-200 bg-white px-2 py-1.5 text-right text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <p class="mt-3 text-xs text-slate-400">
                            💡 <strong>İpucu:</strong> Ortalama Fiyat sütunu kategorideki ürünlerin ortalama satış fiyatını gösterir.
                            COGS genellikle satış fiyatının %30-60'ı aralığındadır.
                            Boş bırakılan kategoriler güncellenmez.
                        </p>
                    @endif
                </div>

                {{-- Footer --}}
                <div class="flex items-center justify-between border-t border-slate-100 px-5 py-4">
                    <button type="button"
                            wire:click="closeCogsWizard"
                            class="rounded-[6px] border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50">
                        Kapat
                    </button>

                    @if(!empty($cogsWizardCategories))
                        <button type="button"
                                wire:click="applyCogsWizard"
                                wire:loading.attr="disabled"
                                wire:target="applyCogsWizard"
                                class="flex items-center gap-2 rounded-[6px] bg-slate-900 px-5 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60">
                            <span wire:loading.remove wire:target="applyCogsWizard">Maliyetleri Uygula</span>
                            <span wire:loading wire:target="applyCogsWizard">Güncelleniyor...</span>
                        </button>
                    @endif
                </div>

            </div>
        </div>
    </div>
</div>
