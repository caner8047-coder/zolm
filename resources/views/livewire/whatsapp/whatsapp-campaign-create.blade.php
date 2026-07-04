<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl lg:text-2xl font-bold text-slate-900">Yeni Kampanya</h1>
            <p class="text-sm text-slate-500 mt-1">Adım {{ $step }}/5</p>
        </div>
        <a href="{{ route('whatsapp.campaigns') }}" class="text-sm text-slate-500 hover:text-slate-700">← Listeye Dön</a>
    </div>

    {{-- İlerleme Çubuğu --}}
    <div class="flex items-center gap-2">
        @foreach(['Mağaza', 'Segment', 'Şablon', 'Detaylar', 'Kupon'] as $i => $label)
            <div class="flex items-center gap-1">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold
                    {{ $step > $i+1 ? 'bg-emerald-500 text-white' : ($step == $i+1 ? 'bg-slate-900 text-white' : 'bg-slate-200 text-slate-500') }}">
                    {{ $i + 1 }}
                </div>
                <span class="text-xs text-slate-600 hidden sm:inline">{{ $label }}</span>
            </div>
            @if($i < 4)
                <div class="flex-1 h-0.5 bg-slate-200"></div>
            @endif
        @endforeach
    </div>

    @if(session('wa_success'))
        <div class="rounded-[10px] border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">{{ session('wa_success') }}</div>
    @endif
    @if(session('wa_error'))
        <div class="rounded-[10px] border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ session('wa_error') }}</div>
    @endif

    {{-- Adım 1: Mağaza --}}
    @if($step === 1)
        <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6 space-y-4">
            <div class="font-medium text-slate-900">WooCommerce Mağazası Seçin</div>
            <select wire:model="storeId" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                <option value="">— Mağaza seçin —</option>
                @foreach($this->availableStores as $store)
                    <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                @endforeach
            </select>
            <div class="flex justify-end">
                <button wire:click="nextStep" {{ !$storeId ? 'disabled' : '' }}
                    class="rounded-[6px] bg-slate-900 text-white px-4 py-2 text-sm font-medium hover:bg-slate-800 disabled:opacity-50">İleri →</button>
            </div>
        </div>
    @endif

    {{-- Adım 2: Segment --}}
    @if($step === 2)
        <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6 space-y-4">
            <div class="font-medium text-slate-900">Segment Seçin veya Oluşturun</div>
            <select wire:model="segmentId" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                <option value="">— Segment seçin —</option>
                @foreach($this->segments as $segment)
                    <option value="{{ $segment->id }}">{{ $segment->name }} ({{ $segment->estimated_count ?? '?' }} kişi)</option>
                @endforeach
            </select>
            @if($segmentId)
                <div class="rounded-[6px] bg-slate-50 border border-slate-200 p-3 text-sm">
                    <span class="font-medium">Tahmini alıcı:</span> {{ number_format($estimatedCount) }} kişi
                </div>
            @endif
            <div class="flex justify-between">
                <button wire:click="prevStep" class="rounded-[6px] border border-slate-300 text-slate-700 px-4 py-2 text-sm font-medium hover:bg-slate-50">← Geri</button>
                <button wire:click="nextStep" {{ !$segmentId ? 'disabled' : '' }}
                    class="rounded-[6px] bg-slate-900 text-white px-4 py-2 text-sm font-medium hover:bg-slate-800 disabled:opacity-50">İleri →</button>
            </div>
        </div>
    @endif

    {{-- Adım 3: Şablon --}}
    @if($step === 3)
        <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6 space-y-4">
            <div class="font-medium text-slate-900">Meta Marketing Şablonu Seçin</div>
            @if($this->templates->isEmpty())
                <div class="rounded-[6px] bg-amber-50 border border-amber-200 p-3 text-sm text-amber-700">
                    Onaylanmış marketing şablonu bulunmuyor. Önce Şablonlar sayfasından senkronize edin.
                </div>
            @else
                <select wire:model="templateId" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                    <option value="">— Şablon seçin —</option>
                    @foreach($this->templates as $tpl)
                        <option value="{{ $tpl->id }}">{{ $tpl->name }} ({{ $tpl->language }})</option>
                    @endforeach
                </select>
            @endif
            <div class="flex justify-between">
                <button wire:click="prevStep" class="rounded-[6px] border border-slate-300 text-slate-700 px-4 py-2 text-sm font-medium hover:bg-slate-50">← Geri</button>
                <button wire:click="nextStep" {{ !$templateId ? 'disabled' : '' }}
                    class="rounded-[6px] bg-slate-900 text-white px-4 py-2 text-sm font-medium hover:bg-slate-800 disabled:opacity-50">İleri →</button>
            </div>
        </div>
    @endif

    {{-- Adım 4: Detaylar --}}
    @if($step === 4)
        <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6 space-y-4">
            <div class="font-medium text-slate-900">Kampanya Detayları</div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Kampanya Adı <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="campaignName" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900" placeholder="Örn: Bahar İndirimi">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Açıklama</label>
                    <input type="text" wire:model="campaignDescription" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Gönderim Zamanı</label>
                    <input type="datetime-local" wire:model="scheduleAt" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                    <p class="text-xs text-slate-400 mt-1">Boş bırakırsanız hemen gönderilir</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Batch Boyutu</label>
                    <input type="number" wire:model="batchSize" min="10" max="500" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Attribution Penceresi (gün)</label>
                    <input type="number" wire:model="attributionWindowDays" min="1" max="30" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="quietHoursEnabled" class="w-4 h-4 rounded border-slate-300">
                        <span class="text-sm text-slate-700">Sessiz saatleri uygula</span>
                    </label>
                </div>
            </div>
            <div class="flex justify-between">
                <button wire:click="prevStep" class="rounded-[6px] border border-slate-300 text-slate-700 px-4 py-2 text-sm font-medium hover:bg-slate-50">← Geri</button>
                <button wire:click="nextStep" {{ !$campaignName ? 'disabled' : '' }}
                    class="rounded-[6px] bg-slate-900 text-white px-4 py-2 text-sm font-medium hover:bg-slate-800 disabled:opacity-50">İleri →</button>
            </div>
        </div>
    @endif

    {{-- Adım 5: Kupon --}}
    @if($step === 5)
        <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6 space-y-4">
            <div class="font-medium text-slate-900">Kampanya Kuponu (Opsiyonel)</div>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" wire:model="couponEnabled" class="w-4 h-4 rounded border-slate-300">
                <span class="text-sm text-slate-700">Kupon ekle</span>
            </label>

            @if($couponEnabled)
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Kupon Tipi</label>
                        <select wire:model="couponType" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm">
                            <option value="percent">Yüzde (%)</option>
                            <option value="fixed">Sabit Tutar (₺)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Değer</label>
                        <input type="number" wire:model="couponValue" min="0" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Minimum Sepet (₺)</label>
                        <input type="number" wire:model="couponMinimumSpend" min="0" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Son Kullanma (saat)</label>
                        <input type="number" wire:model="couponExpiryHours" min="1" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Kullanım Limiti</label>
                        <input type="number" wire:model="couponUsageLimit" min="1" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm">
                    </div>
                </div>
            @endif

            {{-- ÖzET --}}
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4 mt-4 space-y-2 text-sm">
                <div class="font-medium text-slate-900 mb-2">Kampanya Özeti</div>
                <div class="grid grid-cols-2 gap-2">
                    <div class="text-slate-500">Mağaza:</div>
                    <div class="text-slate-900">{{ $this->availableStores->firstWhere('id', $storeId)?->store_name ?? '-' }}</div>
                    <div class="text-slate-500">Segment:</div>
                    <div class="text-slate-900">{{ $this->segments->firstWhere('id', $segmentId)?->name ?? '-' }} ({{ number_format($estimatedCount) }} kişi)</div>
                    <div class="text-slate-500">Şablon:</div>
                    <div class="text-slate-900">{{ $this->templates->firstWhere('id', $templateId)?->name ?? '-' }}</div>
                    <div class="text-slate-500">Kampanya:</div>
                    <div class="text-slate-900">{{ $campaignName ?: '—' }}</div>
                    @if($couponEnabled)
                        <div class="text-slate-500">Kupon:</div>
                        <div class="text-slate-900">{{ $couponType === 'percent' ? '%'.' '.$couponValue : '₺'.$couponValue }}</div>
                    @endif
                </div>
            </div>

            <div class="flex justify-between pt-2">
                <button wire:click="prevStep" class="rounded-[6px] border border-slate-300 text-slate-700 px-4 py-2 text-sm font-medium hover:bg-slate-50">← Geri</button>
                <div class="flex gap-2">
                    <button wire:click="saveAsDraft" wire:loading.attr="disabled"
                        class="rounded-[6px] border border-slate-300 text-slate-700 px-4 py-2 text-sm font-medium hover:bg-slate-50 disabled:opacity-50">
                        Taslak Kaydet
                    </button>
                    <button wire:click="submitForApproval" wire:loading.attr="disabled"
                        class="rounded-[6px] bg-slate-900 text-white px-4 py-2 text-sm font-medium hover:bg-slate-800 disabled:opacity-50">
                        Onaya Gönder
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
