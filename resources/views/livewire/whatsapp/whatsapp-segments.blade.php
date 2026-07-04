<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl lg:text-2xl font-bold text-slate-900">Segmentler</h1>
            <p class="text-sm text-slate-500 mt-1">Müşteri segmentasyonu yönetimi</p>
        </div>
        <button wire:click="$set('showCreateForm', true)"
            class="rounded-[6px] bg-slate-900 text-white px-4 py-2 text-sm font-medium hover:bg-slate-800 transition-colors">
            + Yeni Segment
        </button>
    </div>

    @if(session('wa_success'))
        <div class="rounded-[10px] border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">{{ session('wa_success') }}</div>
    @endif

    {{-- Yeni Segment Formu --}}
    @if($showCreateForm)
        <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6 space-y-4">
            <div class="font-medium text-slate-900">Yeni Segment Oluştur</div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Segment Adı <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="newName" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900" placeholder="Örn: Son 30 Günde Alışveriş Yapanlar">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Mağaza <span class="text-red-500">*</span></label>
                    <select wire:model="newStoreId" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="0">— Seçin —</option>
                        @foreach($this->availableStores as $store)
                            <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Açıklama</label>
                <input type="text" wire:model="newDescription" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm">
            </div>
            <div class="flex justify-between">
                <button wire:click="$set('showCreateForm', false)" class="text-sm text-slate-500 hover:text-slate-700">İptal</button>
                <button wire:click="createSegment" wire:loading.attr="disabled"
                    class="rounded-[6px] bg-slate-900 text-white px-4 py-2 text-sm font-medium hover:bg-slate-800 disabled:opacity-50">
                    Oluştur
                </button>
            </div>
        </div>
    @endif

    {{-- Segment Listesi --}}
    <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/70">
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Segment</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Mağaza</th>
                    <th class="text-right px-4 py-3 font-medium text-slate-600">Tahmini</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600 hidden sm:table-cell">Son Hesaplama</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Durum</th>
                    <th class="text-right px-4 py-3 font-medium text-slate-600">Aksiyon</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->segments as $segment)
                    <tr class="border-b border-slate-100 hover:bg-slate-50/50">
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-900">{{ $segment->name }}</div>
                            @if($segment->description)
                                <div class="text-xs text-slate-400">{{ Str::limit($segment->description, 60) }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $segment->store->store_name ?? '-' }}</td>
                        <td class="px-4 py-3 text-right text-slate-900 font-medium">{{ number_format($segment->estimated_count ?? 0) }}</td>
                        <td class="px-4 py-3 text-slate-500 text-xs hidden sm:table-cell">
                            {{ $segment->last_calculated_at ? $segment->last_calculated_at->diffForHumans() : 'Hiç hesaplanmadı' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 text-xs font-medium rounded {{ $segment->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                {{ $segment->status === 'active' ? 'Aktif' : 'Arşiv' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <button wire:click="calculateSegment({{ $segment->id }})"
                                class="text-xs text-blue-600 hover:text-blue-800">Hesapla</button>
                            @if($segment->status === 'active')
                                <button wire:click="archiveSegment({{ $segment->id }})"
                                    class="text-xs text-slate-500 hover:text-slate-700">Arşivle</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                            Segment bulunamadı.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
