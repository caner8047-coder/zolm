@if($showOrderLabelManager)
    <div class="fixed inset-0 z-[120] flex items-center justify-center bg-slate-900/45 p-4"
         wire:click.self="closeOrderLabelManager">
        <div class="w-full max-w-3xl overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-2xl">
            <div class="border-b border-slate-200 px-5 py-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-lg font-semibold text-slate-900">Sipariş Renk Etiketleri</p>
                        <p class="mt-1 text-sm text-slate-500">Sipariş aksiyon menüsünde, filtrelerde ve tabloda görünen etiket adlarını ve renklerini özelleştirin.</p>
                    </div>
                    <button type="button"
                            wire:click="closeOrderLabelManager"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-400 transition hover:bg-slate-50 hover:text-slate-600">
                        <span class="sr-only">Kapat</span>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="max-h-[70vh] overflow-y-auto px-5 py-5">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @foreach($orderLabelForm as $labelKey => $label)
                        @php
                            $previewColor = strtoupper((string) ($label['color'] ?? '#64748B'));
                        @endphp
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                            <div class="flex items-start gap-3">
                                <div class="shrink-0">
                                    <label class="mb-1 block text-xs font-medium uppercase tracking-[0.14em] text-slate-500">Renk</label>
                                    <input type="color"
                                           wire:model.live="orderLabelForm.{{ $labelKey }}.color"
                                           class="h-11 w-11 cursor-pointer rounded-[6px] border border-slate-200 bg-white p-1">
                                </div>
                                <div class="min-w-0 flex-1">
                                    <label class="mb-1 block text-xs font-medium uppercase tracking-[0.14em] text-slate-500">Etiket adı</label>
                                    <input type="text"
                                           wire:model.live="orderLabelForm.{{ $labelKey }}.name"
                                           maxlength="24"
                                           class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                                    @error("orderLabelForm.$labelKey.name")
                                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                    @enderror
                                    @error("orderLabelForm.$labelKey.color")
                                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="mt-4 flex items-center justify-between gap-3 rounded-[8px] border border-slate-200 bg-white px-3 py-2">
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Önizleme</p>
                                    <div class="mt-2 inline-flex max-w-full items-center gap-1.5 rounded-[6px] border px-2 py-0.5 text-[11px] font-semibold"
                                         style="background-color: rgba({{ hexdec(substr(ltrim($previewColor, '#'), 0, 2)) }}, {{ hexdec(substr(ltrim($previewColor, '#'), 2, 2)) }}, {{ hexdec(substr(ltrim($previewColor, '#'), 4, 2)) }}, 0.12); border-color: rgba({{ hexdec(substr(ltrim($previewColor, '#'), 0, 2)) }}, {{ hexdec(substr(ltrim($previewColor, '#'), 2, 2)) }}, {{ hexdec(substr(ltrim($previewColor, '#'), 4, 2)) }}, 0.24); color: {{ $previewColor }};">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full" style="background-color: {{ $previewColor }};"></span>
                                        <span class="truncate">{{ $label['name'] ?: 'Etiket adı' }}</span>
                                    </div>
                                </div>
                                <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-medium text-slate-500">
                                    {{ $previewColor }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-slate-200 bg-white px-5 py-4 sm:flex-row sm:items-center sm:justify-end">
                <button type="button"
                        wire:click="closeOrderLabelManager"
                        class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:py-2">
                    İptal
                </button>
                <button type="button"
                        wire:click="saveOrderLabelSettings"
                        wire:loading.attr="disabled"
                        class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60 sm:py-2">
                    Kaydet
                </button>
            </div>
        </div>
    </div>
@endif
