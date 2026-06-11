{{-- Maliyet güncelleme modalı --}}
<div x-data="{ open: @entangle('showCostUpdateModal') }"
     x-show="open"
     x-cloak
     @keydown.escape.window="open = false; $wire.closeCostUpdateModal()"
     class="relative z-50"
     style="display: none;">
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="closeCostUpdateModal"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div x-show="open"
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95"
                 class="relative w-full transform overflow-hidden rounded-xl bg-white text-left shadow-xl transition-all sm:my-8 sm:max-w-lg">

                <div class="border-b border-slate-100 bg-white px-4 pb-4 pt-5 sm:px-6">
                    <h3 class="text-lg font-semibold text-slate-900">Excel ile Maliyet Güncelle</h3>
                    <p class="mt-1 text-sm text-slate-500">Stok Kodu veya Barkod eşleşen ürünlerde yalnızca birim maliyet alanı güncellenir.</p>
                </div>

                <div class="px-4 py-5 sm:px-6">
                    @if(!$costUpdateResult)
                        <div class="rounded-lg border-2 border-dashed border-slate-300 p-6 text-center transition-colors hover:border-slate-400">
                            <input type="file" wire:model="costUpdateFile" accept=".xlsx,.xls" class="hidden" id="costUpdateFileInput">
                            <label for="costUpdateFileInput" class="cursor-pointer">
                                <svg class="mx-auto mb-3 h-10 w-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                                <p class="text-sm text-slate-600">Dosya seçmek için tıklayın</p>
                                <p class="mt-1 text-xs text-slate-400">.xlsx veya .xls (max 10MB)</p>
                            </label>
                        </div>

                        @if($costUpdateFile)
                            <div class="mt-3 flex items-center gap-2 rounded-lg bg-slate-50 p-3">
                                <svg class="h-5 w-5 flex-shrink-0 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span class="truncate text-sm font-medium text-slate-800">{{ $costUpdateFile->getClientOriginalName() }}</span>
                            </div>
                        @endif

                        @error('costUpdateFile') <p class="mt-2 text-xs text-red-500">{{ $message }}</p> @enderror

                        <label class="mt-4 flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50/70 p-3">
                            <input type="checkbox"
                                   wire:model.live="costUpdateApplyZeroValues"
                                   class="mt-1 h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-200">
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-slate-800">0 maliyetleri de uygula</span>
                                <span class="mt-0.5 block text-xs leading-5 text-slate-500">Kapalıyken boş ve 0 maliyet satırları atlanır.</span>
                            </span>
                        </label>

                        <div class="mt-4 rounded-lg border border-slate-200 bg-white p-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Beklenen kolonlar</p>
                            <div class="mt-2 grid grid-cols-1 gap-2 text-xs text-slate-600 sm:grid-cols-2">
                                <span class="rounded-[6px] bg-slate-50 px-2 py-1">Stok Kodu veya Barkod</span>
                                <span class="rounded-[6px] bg-slate-50 px-2 py-1">Maliyet veya MF Fiyatı</span>
                            </div>
                        </div>
                    @else
                        <div class="py-4 text-center">
                            @if($costUpdateResult['success'])
                                <div class="mb-4 inline-flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100">
                                    <svg class="h-7 w-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                                <h4 class="mb-2 text-lg font-semibold text-slate-900">Maliyet Güncelleme Tamamlandı</h4>
                            @else
                                <div class="mb-4 inline-flex h-14 w-14 items-center justify-center rounded-full bg-red-100">
                                    <svg class="h-7 w-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </div>
                                <h4 class="mb-2 text-lg font-semibold text-slate-900">Hata Oluştu</h4>
                            @endif

                            <p class="mb-4 text-sm text-slate-600">{{ $costUpdateResult['message'] }}</p>

                            @if($costUpdateResult['success'])
                                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                    <div class="rounded-lg bg-emerald-50 p-3">
                                        <p class="text-2xl font-bold text-emerald-700">{{ $costUpdateResult['updated'] }}</p>
                                        <p class="text-xs text-emerald-600">Güncellenen</p>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 p-3">
                                        <p class="text-2xl font-bold text-slate-700">{{ $costUpdateResult['skipped'] }}</p>
                                        <p class="text-xs text-slate-500">Atlanan</p>
                                    </div>
                                    <div class="rounded-lg bg-amber-50 p-3">
                                        <p class="text-2xl font-bold text-amber-700">{{ $costUpdateResult['zero_cost'] ?? 0 }}</p>
                                        <p class="text-xs text-amber-600">0 Maliyet</p>
                                    </div>
                                    <div class="rounded-lg bg-red-50 p-3">
                                        <p class="text-2xl font-bold text-red-700">{{ $costUpdateResult['not_found'] ?? 0 }}</p>
                                        <p class="text-xs text-red-600">Bulunamadı</p>
                                    </div>
                                </div>
                            @endif

                            @if(!empty($costUpdateResult['errors']))
                                <div class="mt-4 max-h-32 overflow-y-auto rounded-lg bg-red-50 p-3 text-left">
                                    <p class="mb-1 text-xs font-medium text-red-700">Notlar:</p>
                                    @foreach(array_slice($costUpdateResult['errors'], 0, 6) as $err)
                                        <p class="text-xs text-red-600">{{ $err }}</p>
                                    @endforeach
                                    @if(count($costUpdateResult['errors']) > 6)
                                        <p class="mt-1 text-xs text-red-400">... ve {{ count($costUpdateResult['errors']) - 6 }} not daha</p>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="border-t border-slate-100 bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                    @if(!$costUpdateResult)
                        <button wire:click="updateCostsFromExcel" {{ !$costUpdateFile ? 'disabled' : '' }}
                                wire:loading.attr="disabled"
                                wire:loading.class="cursor-wait opacity-60"
                                wire:target="updateCostsFromExcel"
                                class="flex w-full items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-3 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 sm:ml-3 sm:w-auto sm:py-2">
                            @if($costUpdating)
                                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                </svg>
                                Güncelleniyor...
                            @else
                                Maliyetleri Güncelle
                            @endif
                        </button>
                    @endif
                    <button type="button" wire:click="closeCostUpdateModal"
                            wire:loading.attr="disabled"
                            wire:target="updateCostsFromExcel"
                            class="mt-3 w-full rounded-lg bg-white px-4 py-3 text-sm font-semibold text-slate-900 ring-1 ring-inset ring-slate-300 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60 sm:mt-0 sm:w-auto sm:py-2">
                        {{ $costUpdateResult ? 'Kapat' : 'İptal' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
