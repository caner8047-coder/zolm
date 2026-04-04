@php
    $align = $align ?? 'right';
    $actionPackage = $actionPackage ?? $order->packages->first();
    $latestActionRun = $latestActionRun ?? $order->actionRuns->sortByDesc('created_at')->first();
    $summary = $latestActionRun ? $this->actionResponseSummary($latestActionRun) : null;
    $menuPositionClass = $align === 'left' ? 'left-0 origin-top-left' : 'right-0 origin-top-right';
    $orderLabelDefinitions = $orderLabelDefinitions ?? $this->orderLabelDefinitions();
    $orderColorLabel = $orderLabelDefinitions[$order->color_label_key] ?? null;

    $orderActions = [
        [
            'type' => 'refresh_order',
            'title' => 'Siparişi yenile',
            'description' => 'Sipariş satırı, durum ve kalemleri tekrar çek.',
            'icon_class' => 'text-slate-400',
            'hover_class' => 'hover:bg-slate-50',
            'path' => 'M4 4v5h.582m14.356 2A8 8 0 106.582 9m0 0H9m11 2h-2m2 4h-4',
        ],
        [
            'type' => 'refresh_cargo',
            'title' => 'Kargoyu yenile',
            'description' => 'Takip, barkod ve lojistik alanlarını güncelle.',
            'icon_class' => 'text-amber-500',
            'hover_class' => 'hover:bg-amber-50',
            'path' => 'M8 17h8m-8 0a2 2 0 11-4 0m4 0a2 2 0 104 0m4 0a2 2 0 114 0m-4 0H8m8-5V9a1 1 0 00-1-1h-4V5H7a1 1 0 00-1 1v6m10 0h2l1.5-2.5A1 1 0 0018.64 8H16',
        ],
        [
            'type' => 'refresh_finance',
            'title' => 'Finansı yenile',
            'description' => 'Hakediş ve finans olaylarını yeniden topla.',
            'icon_class' => 'text-emerald-500',
            'hover_class' => 'hover:bg-emerald-50',
            'path' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V6m0 12v-2m8-4a8 8 0 11-16 0 8 8 0 0116 0z',
        ],
        [
            'type' => 'recalculate_profit',
            'title' => 'Kârı hesapla',
            'description' => 'Profit snapshot ve marj görünümünü güncelle.',
            'icon_class' => 'text-indigo-500',
            'hover_class' => 'hover:bg-indigo-50',
            'path' => 'M3 17l6-6 4 4 7-7m0 0h-4m4 0v4',
        ],
    ];

    $packageActions = [];

    if ($actionPackage && $this->orderSupportsCapability($order, 'package_picking')) {
        $packageActions[] = [
            'type' => 'package_picking',
            'title' => 'Picking bildir',
            'description' => 'Paket toplama bilgisini pazaryerine ilet.',
            'icon_class' => 'text-emerald-500',
            'hover_class' => 'hover:bg-emerald-50',
            'path' => 'M5 13l4 4L19 7',
        ];
    }

    if ($actionPackage && $this->orderSupportsCapability($order, 'package_common_label_get')) {
        $packageActions[] = [
            'type' => 'package_common_label_get',
            'title' => 'Ortak barkod getir',
            'description' => 'Paket için son barkod cevabını tekrar al.',
            'icon_class' => 'text-sky-500',
            'hover_class' => 'hover:bg-sky-50',
            'path' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        ];
    }
@endphp

<div x-data="{
        actionMenuOpen: false,
        labelSectionOpen: false,
        toggleActionMenu() {
            this.actionMenuOpen = !this.actionMenuOpen;

            if (!this.actionMenuOpen) {
                this.labelSectionOpen = false;
            }
        },
        closeActionMenu() {
            this.actionMenuOpen = false;
            this.labelSectionOpen = false;
        },
     }"
     @keydown.escape.window="closeActionMenu()"
     :class="{ 'z-[90]': actionMenuOpen }"
     class="relative flex items-start justify-end">
    <button type="button"
            @click.stop="toggleActionMenu()"
            :aria-expanded="actionMenuOpen.toString()"
            aria-haspopup="menu"
            class="inline-flex h-8 w-8 items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-700">
        <span class="sr-only">Sipariş işlem menüsünü aç</span>
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5h.01M12 12h.01M12 19h.01" />
        </svg>
    </button>

    <div x-show="actionMenuOpen"
         x-cloak
         x-transition
         @click.outside="closeActionMenu()"
         class="absolute {{ $menuPositionClass }} top-10 z-40 w-60 rounded-[8px] border border-slate-200 bg-white p-2 shadow-xl">
        <div>
            <button type="button"
                    @click="labelSectionOpen = !labelSectionOpen"
                    :aria-expanded="labelSectionOpen.toString()"
                    class="flex w-full items-center justify-between gap-3 rounded-[6px] px-2 py-2 text-left transition hover:bg-slate-50">
                <span class="min-w-0">
                    <span class="block text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Renk etiketi</span>
                    @if($orderColorLabel)
                        <span class="mt-1 inline-flex max-w-full items-center gap-1.5 rounded-[6px] border px-2 py-0.5 text-[10px] font-semibold"
                              style="background-color: {{ $orderColorLabel['bg_color'] }}; border-color: {{ $orderColorLabel['border_color'] }}; color: {{ $orderColorLabel['color'] }};">
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full" style="background-color: {{ $orderColorLabel['color'] }};"></span>
                            <span class="truncate">{{ $orderColorLabel['name'] }}</span>
                        </span>
                    @else
                        <span class="mt-1 block text-[11px] text-slate-500">Etiket seç</span>
                    @endif
                </span>
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-400 transition"
                      :class="{ 'rotate-180': labelSectionOpen }">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </span>
            </button>

            <div x-show="labelSectionOpen"
                 x-transition.opacity.duration.150ms
                 class="space-y-2 px-2 pb-1">
                <div class="flex items-center justify-end">
                    <button type="button"
                            @click="closeActionMenu()"
                            wire:click="openOrderLabelManager"
                            class="text-[10px] font-medium text-slate-500 transition hover:text-slate-700">
                        Özelleştir
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-1">
                    @foreach($orderLabelDefinitions as $label)
                        @php
                            $isSelectedOrderLabel = $order->color_label_key === $label['key'];
                        @endphp
                        <button type="button"
                                @click="closeActionMenu()"
                                wire:click="assignOrderColorLabel({{ $order->id }}, '{{ $label['key'] }}')"
                                wire:loading.attr="disabled"
                                wire:loading.class="cursor-wait opacity-60"
                                wire:target="assignOrderColorLabel({{ $order->id }}, '{{ $label['key'] }}')"
                                class="flex min-h-[38px] items-center gap-2 rounded-[6px] border px-2.5 py-2 text-left transition disabled:cursor-not-allowed"
                                style="background-color: {{ $label['bg_color'] }}; border-color: {{ $isSelectedOrderLabel ? $label['color'] : $label['border_color'] }}; color: {{ $label['color'] }};">
                            <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ $label['color'] }};"></span>
                            <span class="min-w-0 truncate text-[11px] font-semibold">
                                {{ $label['name'] }}
                            </span>
                        </button>
                    @endforeach
                </div>

                @if($order->color_label_key)
                    <button type="button"
                            @click="closeActionMenu()"
                            wire:click="clearOrderColorLabel({{ $order->id }})"
                            wire:loading.attr="disabled"
                            wire:loading.class="cursor-wait opacity-60"
                            wire:target="clearOrderColorLabel({{ $order->id }})"
                            class="flex w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-[11px] font-medium text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed">
                        Etiketi kaldır
                    </button>
                @endif
            </div>
        </div>

        <div class="mt-2 space-y-1 border-t border-slate-200 pt-2">
            <p class="px-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Belge çıktıları</p>

            <a href="{{ $this->documentDownloadUrl('label', $order->id) }}"
               target="_blank"
               @click="closeActionMenu()"
               title="Siparişe ait etiket PDF’ini yeni sekmede aç."
               class="flex w-full items-center gap-2.5 rounded-[6px] px-3 py-2 text-left transition hover:bg-slate-50">
                <span class="text-sky-500">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 7h10M7 11h10M7 15h6m5 6H6a2 2 0 01-2-2V5a2 2 0 012-2h8l6 6v10a2 2 0 01-2 2z" />
                    </svg>
                </span>
                <span class="min-w-0 truncate text-xs font-medium text-slate-700">Kargo etiketi indir</span>
            </a>

            <a href="{{ $this->documentDownloadUrl('dispatch', $order->id) }}"
               target="_blank"
               @click="closeActionMenu()"
               title="Paket veya sipariş bazlı sevk fişini PDF olarak oluştur."
               class="flex w-full items-center gap-2.5 rounded-[6px] px-3 py-2 text-left transition hover:bg-slate-50">
                <span class="text-indigo-500">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V9m-4-4H9m6 0v4m0-4l-4 4" />
                    </svg>
                </span>
                <span class="min-w-0 truncate text-xs font-medium text-slate-700">İrsaliye indir</span>
            </a>
        </div>

        <div class="mt-2 space-y-1">
            <p class="px-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Genel işlemler</p>
            <button type="button"
                    @click="closeActionMenu()"
                    wire:click="openEditOrder({{ $order->id }})"
                    wire:loading.attr="disabled"
                    wire:loading.class="cursor-wait opacity-60"
                    wire:target="openEditOrder({{ $order->id }})"
                    title="Müşteri, durum ve teslim bilgilerini düzenle."
                    class="flex w-full items-center gap-2.5 rounded-[6px] px-3 py-2 text-left transition hover:bg-slate-50 disabled:cursor-not-allowed">
                <span class="text-slate-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5M16.5 3.5a2.121 2.121 0 113 3L12 14l-4 1 1-4 7.5-7.5z" />
                    </svg>
                </span>
                <span class="min-w-0 truncate text-xs font-medium text-slate-700">Siparişi düzenle</span>
            </button>

            <button type="button"
                    @click.stop="expanded.includes({{ $order->id }}) ? expanded = expanded.filter(i => i !== {{ $order->id }}) : expanded.push({{ $order->id }}); closeActionMenu()"
                    :title="expanded.includes({{ $order->id }}) ? 'Finans, paket ve ürün satırlarını gizle' : 'Finans, paket ve ürün satırlarını göster'"
                    class="flex w-full items-center gap-2.5 rounded-[6px] px-3 py-2 text-left transition hover:bg-slate-50">
                <span class="text-slate-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12H9m12 0A9 9 0 113 12a9 9 0 0118 0z" x-show="expanded.includes({{ $order->id }})"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v6m3-3H9m12 0A9 9 0 113 12a9 9 0 0118 0z" x-show="!expanded.includes({{ $order->id }})"></path>
                    </svg>
                </span>
                <span class="min-w-0 truncate text-xs font-medium text-slate-700" x-text="expanded.includes({{ $order->id }}) ? 'Detayı kapat' : 'Detayı aç'"></span>
            </button>

            <button type="button"
                    @click="closeActionMenu()"
                    wire:click="duplicateOrder({{ $order->id }})"
                    wire:loading.attr="disabled"
                    wire:loading.class="cursor-wait opacity-60"
                    wire:target="duplicateOrder({{ $order->id }})"
                    title="Kalem ve paketleriyle kopya kayıt oluştur."
                    class="flex w-full items-center gap-2.5 rounded-[6px] px-3 py-2 text-left transition hover:bg-slate-50 disabled:cursor-not-allowed">
                <span class="text-slate-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-2 10h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                </span>
                <span class="min-w-0 truncate text-xs font-medium text-slate-700">Siparişi çoğalt</span>
            </button>

            <button type="button"
                    @click="closeActionMenu()"
                    wire:click="deleteOrder({{ $order->id }})"
                    wire:loading.attr="disabled"
                    wire:loading.class="cursor-wait opacity-60"
                    wire:target="deleteOrder({{ $order->id }})"
                    wire:confirm="Bu siparişi silmek istediğinize emin misiniz?"
                    title="Kayıt ve bağlı paketleri kaldır."
                    class="flex w-full items-center gap-2.5 rounded-[6px] px-3 py-2 text-left transition hover:bg-rose-50 disabled:cursor-not-allowed">
                <span class="text-rose-500">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16" />
                    </svg>
                </span>
                <span class="min-w-0 truncate text-xs font-medium text-rose-700">Siparişi sil</span>
            </button>
        </div>

        <div class="mt-2 space-y-1 border-t border-slate-200 pt-2">
            <p class="px-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Operasyon aksiyonları</p>
            @foreach($orderActions as $action)
                <button type="button"
                        @click="closeActionMenu()"
                        wire:click="runOrderAction({{ $order->id }}, '{{ $action['type'] }}')"
                        wire:loading.attr="disabled"
                        wire:loading.class="cursor-wait opacity-60"
                        wire:target="runOrderAction({{ $order->id }}, '{{ $action['type'] }}')"
                        title="{{ $action['description'] }}"
                        class="flex w-full items-center gap-2.5 rounded-[6px] px-3 py-2 text-left transition {{ $action['hover_class'] }} disabled:cursor-not-allowed">
                    <span class="{{ $action['icon_class'] }}">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $action['path'] }}" />
                        </svg>
                    </span>
                    <span class="min-w-0 truncate text-xs font-medium text-slate-700">{{ $action['title'] }}</span>
                </button>
            @endforeach
        </div>

        @if($actionPackage && count($packageActions) > 0)
            <div class="mt-2 space-y-1 border-t border-slate-200 pt-2">
                <p class="px-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Paket aksiyonları</p>
                @foreach($packageActions as $action)
                    <button type="button"
                            @click="closeActionMenu()"
                            wire:click="runPackageAction({{ $actionPackage->id }}, '{{ $action['type'] }}')"
                            wire:loading.attr="disabled"
                            wire:loading.class="cursor-wait opacity-60"
                            wire:target="runPackageAction({{ $actionPackage->id }}, '{{ $action['type'] }}')"
                            title="{{ $action['description'] }}"
                            class="flex w-full items-center gap-2.5 rounded-[6px] px-3 py-2 text-left transition {{ $action['hover_class'] }} disabled:cursor-not-allowed">
                        <span class="{{ $action['icon_class'] }}">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $action['path'] }}" />
                            </svg>
                        </span>
                        <span class="min-w-0 truncate text-xs font-medium text-slate-700">{{ $action['title'] }}</span>
                    </button>
                @endforeach
            </div>
        @endif

        @if($latestActionRun)
            <div class="mt-2 rounded-[6px] border border-slate-200 bg-slate-50/80 p-3">
                <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Son işlem</p>
                        <p class="mt-1 truncate text-xs font-semibold text-slate-700">{{ $this->orderActionLabel($latestActionRun->action_type) }}</p>
                    </div>
                    <x-zolm.status-badge :tone="$this->orderActionStatusTone($latestActionRun->status)">
                        {{ $this->orderActionStatusLabel($latestActionRun->status) }}
                    </x-zolm.status-badge>
                </div>
                @if($summary)
                    <p class="mt-2 text-[11px] leading-5 text-slate-500">{{ $summary }}</p>
                @endif

                @if($this->actionCanRetry($latestActionRun->status))
                    <button type="button"
                            @click="closeActionMenu()"
                            wire:click="retryActionRun({{ $latestActionRun->id }})"
                            wire:loading.attr="disabled"
                            wire:loading.class="cursor-wait opacity-60"
                            wire:target="retryActionRun({{ $latestActionRun->id }})"
                            class="mt-3 inline-flex w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-100 disabled:opacity-60">
                        Son işlemi tekrar dene
                    </button>
                @endif
            </div>
        @endif
    </div>
</div>
