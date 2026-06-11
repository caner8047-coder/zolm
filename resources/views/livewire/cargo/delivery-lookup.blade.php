<div class="space-y-4 lg:space-y-6">
    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="p-4 lg:p-6 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 lg:gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Teslimat kontrol</p>
                    <h2 class="mt-2 text-xl lg:text-2xl font-bold text-slate-900">Kargo konu arama</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Satıcı anlaşmalı kargo kodu ile müşteri, adres, telefon ve Sürat teslimat sinyalini kontrol edin.
                    </p>
                </div>

                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 px-3 py-2 text-xs text-slate-500 sm:max-w-xs">
                    Sürat API adres uygunluğunu ayrı alan olarak vermezse sonuç takip hareketleri ve devir sebebinden yorumlanır.
                </div>
            </div>

            <form wire:submit.prevent="lookup" class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_auto] gap-3 lg:gap-4">
                <div>
                    <label for="delivery-reference" class="sr-only">Kargo kodu</label>
                    <input
                        id="delivery-reference"
                        type="text"
                        wire:model.live="reference"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="Örn. 7270032513716860"
                        class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-base sm:text-sm text-slate-900 placeholder:text-slate-400 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                    @error('reference')
                        <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="lookup"
                    class="min-h-[44px] w-full sm:w-auto inline-flex items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 sm:py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-wait disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="lookup">Sorgula</span>
                    <span wire:loading wire:target="lookup">Sorgulanıyor...</span>
                </button>
            </form>

            @if($message)
                @php
                    $messageClasses = match ($messageTone) {
                        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                        'warning' => 'border-amber-200 bg-amber-50 text-amber-700',
                        'danger' => 'border-rose-200 bg-rose-50 text-rose-700',
                        default => 'border-slate-200 bg-slate-50 text-slate-600',
                    };
                @endphp
                <div class="rounded-[8px] border px-3 py-2 text-sm {{ $messageClasses }}">
                    {{ $message }}
                </div>
            @endif
        </div>
    </section>

    @if($result)
        @php
            $customer = data_get($result, 'local.customer', []);
            $order = data_get($result, 'local.order', []);
            $package = data_get($result, 'local.package', []);
            $store = data_get($result, 'local.store', []);
            $items = data_get($result, 'local.items', []);
            $surat = data_get($result, 'surat', []);
            $distribution = data_get($result, 'distribution', []);
            $tone = data_get($distribution, 'tone', 'slate');
            $badgeClasses = match ($tone) {
                'emerald' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                'rose' => 'bg-rose-50 text-rose-700 border-rose-200',
                'amber' => 'bg-amber-50 text-amber-700 border-amber-200',
                default => 'bg-slate-50 text-slate-700 border-slate-200',
            };
        @endphp

        <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
            <div class="p-4 lg:p-6 space-y-4">
                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Sonuç</p>
                        <div class="mt-2 flex flex-col sm:flex-row sm:items-center gap-2">
                            <h3 class="text-lg font-bold text-slate-900 break-words">
                                {{ data_get($customer, 'name') ?: 'Müşteri bulunamadı' }}
                            </h3>
                            <span class="inline-flex w-fit items-center rounded-[6px] border px-2 py-0.5 text-xs font-semibold {{ $badgeClasses }}">
                                {{ data_get($distribution, 'label') }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ data_get($distribution, 'reason') }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-2 xl:grid-cols-4 gap-2 min-w-0 lg:min-w-[440px]">
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3 min-w-0">
                            <p class="text-[11px] uppercase tracking-[0.14em] text-slate-500">Güven</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ data_get($distribution, 'confidence') === 'high' ? 'Yüksek' : (data_get($distribution, 'confidence') === 'medium' ? 'Orta' : 'Düşük') }}</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3 min-w-0">
                            <p class="text-[11px] uppercase tracking-[0.14em] text-slate-500">Durum</p>
                            <p class="mt-1 truncate text-sm font-semibold text-slate-900">{{ data_get($surat, 'status_label') ?: '-' }}</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3 min-w-0">
                            <p class="text-[11px] uppercase tracking-[0.14em] text-slate-500">Şube</p>
                            <p class="mt-1 truncate text-sm font-semibold text-slate-900">{{ data_get($surat, 'delivery_branch') ?: '-' }}</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3 min-w-0">
                            <p class="text-[11px] uppercase tracking-[0.14em] text-slate-500">Kod</p>
                            <p class="mt-1 truncate text-sm font-semibold text-slate-900">{{ data_get($surat, 'queried_reference') ?: data_get($result, 'reference') }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 lg:gap-4">
                    <div class="rounded-lg border border-slate-200 bg-white p-4 min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Müşteri</p>
                        <dl class="mt-3 space-y-3 text-sm">
                            <div>
                                <dt class="text-slate-500">Telefon</dt>
                                <dd class="mt-1 font-semibold text-slate-900">{{ data_get($customer, 'phone_display') ?: 'Telefon verisi yok' }}</dd>
                                @if(data_get($customer, 'phone_source'))
                                    <p class="mt-1 text-xs text-slate-500">{{ data_get($customer, 'phone_source') }}</p>
                                @elseif(data_get($customer, 'phone_missing_reason'))
                                    <p class="mt-1 text-xs text-amber-700">{{ data_get($customer, 'phone_missing_reason') }}</p>
                                @endif
                            </div>
                            <div>
                                <dt class="text-slate-500">Konum</dt>
                                <dd class="mt-1 font-semibold text-slate-900">{{ collect([data_get($customer, 'district'), data_get($customer, 'city')])->filter()->implode(' / ') ?: '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Adres</dt>
                                <dd class="mt-1 whitespace-pre-line text-slate-900">{{ data_get($customer, 'address') ?: '-' }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-white p-4 min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Sipariş</p>
                        <dl class="mt-3 space-y-3 text-sm">
                            <div>
                                <dt class="text-slate-500">Pazaryeri</dt>
                                <dd class="mt-1 font-semibold text-slate-900">{{ collect([data_get($store, 'marketplace'), data_get($store, 'name')])->filter()->implode(' / ') ?: '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Sipariş no</dt>
                                <dd class="mt-1 font-semibold text-slate-900">{{ data_get($order, 'order_number') ?: '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Paket / kargo</dt>
                                <dd class="mt-1 font-semibold text-slate-900 break-words">
                                    {{ collect([data_get($package, 'package_number'), data_get($package, 'cargo_tracking_number'), data_get($package, 'cargo_barcode')])->filter()->implode(' / ') ?: '-' }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-white p-4 min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Sürat</p>
                        <dl class="mt-3 space-y-3 text-sm">
                            <div>
                                <dt class="text-slate-500">Teslimat şubesi</dt>
                                <dd class="mt-1 font-semibold text-slate-900">{{ data_get($surat, 'delivery_branch') ?: '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Şube telefonu</dt>
                                <dd class="mt-1 font-semibold text-slate-900">{{ data_get($surat, 'delivery_branch_phone') ?: '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Son hareket</dt>
                                <dd class="mt-1 font-semibold text-slate-900">{{ data_get($surat, 'last_event_at') ?: '-' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_420px] gap-4 lg:gap-6">
            <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
                <div class="p-4 lg:p-6 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Ürün ve hareket</p>
                            <h3 class="mt-2 text-lg font-bold text-slate-900">Operasyon özeti</h3>
                        </div>
                        <span class="inline-flex w-fit rounded-[6px] bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">
                            {{ count($items) }} ürün satırı
                        </span>
                    </div>

                    <div class="overflow-hidden rounded-lg border border-slate-200">
                        <div class="hidden md:grid grid-cols-[minmax(0,1fr)_120px_140px] bg-slate-50/80 px-4 py-3 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">
                            <span>Ürün</span>
                            <span>Adet</span>
                            <span>Kimlik</span>
                        </div>
                        <div class="divide-y divide-slate-200">
                            @forelse($items as $item)
                                <div class="grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_120px_140px] gap-2 px-4 py-3 text-sm">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-slate-900">{{ data_get($item, 'name') }}</p>
                                        @if(data_get($item, 'is_corner'))
                                            <p class="mt-1 text-xs text-amber-700">Köşe yönü teyidi önerilir.</p>
                                        @endif
                                    </div>
                                    <div class="text-slate-700">{{ data_get($item, 'quantity') }}</div>
                                    <div class="truncate text-slate-500">{{ data_get($item, 'stock_code') ?: data_get($item, 'barcode') ?: '-' }}</div>
                                </div>
                            @empty
                                <div class="px-4 py-6 text-sm text-slate-500">Ürün satırı bulunamadı.</div>
                            @endforelse
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-lg border border-slate-200">
                        <div class="bg-slate-50/80 px-4 py-3 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">
                            Sürat hareketleri
                        </div>
                        <div class="divide-y divide-slate-200">
                            @forelse(collect(data_get($surat, 'events', []))->take(6) as $event)
                                <div class="grid grid-cols-1 md:grid-cols-[170px_minmax(0,1fr)_180px] gap-2 px-4 py-3 text-sm">
                                    <div class="text-slate-500">{{ data_get($event, 'event_at') ?: '-' }}</div>
                                    <div class="font-semibold text-slate-900">{{ data_get($event, 'event_description') ?: data_get($event, 'event_status') ?: '-' }}</div>
                                    <div class="truncate text-slate-500">{{ data_get($event, 'branch_name') ?: '-' }}</div>
                                </div>
                            @empty
                                <div class="px-4 py-6 text-sm text-slate-500">{{ data_get($surat, 'message') ?: 'Sürat hareket kaydı yok.' }}</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
                <div class="p-4 lg:p-6 space-y-4" x-data="{ copied: false }">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">WhatsApp</p>
                        <h3 class="mt-2 text-lg font-bold text-slate-900">Mesaj hazırlığı</h3>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @foreach(data_get($result, 'templates', []) as $templateKey => $template)
                            <button
                                type="button"
                                wire:click="selectTemplate('{{ $templateKey }}')"
                                class="min-h-[36px] rounded-[6px] border px-3 py-2 text-xs font-semibold transition {{ $selectedTemplate === $templateKey ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}"
                            >
                                {{ data_get($template, 'label') }}
                            </button>
                        @endforeach
                    </div>

                    <textarea
                        x-ref="templateText"
                        readonly
                        rows="10"
                        class="w-full resize-none rounded-[8px] border border-slate-200 bg-slate-50/60 p-3 text-base sm:text-sm leading-6 text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >{{ $this->selectedTemplateText }}</textarea>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <button
                            type="button"
                            x-on:click="navigator.clipboard.writeText($refs.templateText.value); copied = true; setTimeout(() => copied = false, 1400)"
                            class="min-h-[44px] w-full inline-flex items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            <span x-show="!copied">Metni kopyala</span>
                            <span x-show="copied" x-cloak>Kopyalandı</span>
                        </button>

                        @if(data_get($customer, 'whatsapp_url'))
                            <a
                                href="{{ data_get($customer, 'whatsapp_url') }}"
                                target="_blank"
                                rel="noreferrer"
                                class="min-h-[44px] w-full inline-flex items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 sm:py-2 text-sm font-semibold text-white hover:bg-slate-800"
                            >
                                WhatsApp aç
                            </a>
                        @else
                            <button
                                type="button"
                                disabled
                                class="min-h-[44px] w-full inline-flex items-center justify-center rounded-[6px] bg-slate-100 px-4 py-3 sm:py-2 text-sm font-semibold text-slate-400"
                            >
                                Telefon yok
                            </button>
                        @endif
                    </div>
                </div>
            </section>
        </div>
    @else
        <section class="rounded-[10px] border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-500">
            Kargo kodu girildiğinde müşteri ve teslimat bilgileri burada görünecek.
        </section>
    @endif
</div>
