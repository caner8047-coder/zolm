<div class="space-y-6 p-4 lg:p-6 bg-slate-50/50 min-h-screen">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-xl lg:text-2xl font-semibold text-slate-900">Compliance Center v2</h1>
            <p class="text-sm text-slate-500">KVKK/GDPR uyumluluğu, müşteri talepleri (DSR), rıza yönetim defteri ve veri soy ağacı (lineage).</p>
        </div>
        <div class="w-full sm:w-auto">
            <select wire:model.live="selectedStoreId" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:outline-none">
                @foreach($stores as $st)
                    <option value="{{ $st->id }}">{{ $st->store_name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Feedback Messages --}}
    @if($errorMessage)
        <div class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-[8px] text-sm">
            {{ $errorMessage }}
        </div>
    @endif
    @if($successMessage)
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-[8px] text-sm">
            {{ $successMessage }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left: Forms (DSR & Legal Hold) --}}
        <div class="lg:col-span-1 space-y-6">
            {{-- DSR Form --}}
            <div class="bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm space-y-4">
                <h2 class="text-lg font-semibold text-slate-900">Yeni DSR Talebi Ekle</h2>
                <form wire:submit.prevent="createDsr" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Müşteri ID / Ref</label>
                        <input type="text" wire:model="customerId" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm" placeholder="Orn: cust_1234">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Talep Tipi</label>
                        <select wire:model="requestType" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm">
                            <option value="export">Veri Çıkarma (Access/Export)</option>
                            <option value="rectification">Düzeltme Notu (Rectification)</option>
                            <option value="anonymize">Anonimleştirme (Anonymize)</option>
                            <option value="delete">Tümünü Silme (Delete/Forget)</option>
                        </select>
                    </div>
                    <button type="submit" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 hover:bg-slate-800 text-white font-medium rounded-[6px] text-sm transition shadow-sm">
                        Talebi Oluştur
                    </button>
                </form>
            </div>

            {{-- Legal Hold Form --}}
            <div class="bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm space-y-4">
                <h2 class="text-lg font-semibold text-slate-900">Yasal Veri Koruma (Legal Hold) Ekle</h2>
                <form wire:submit.prevent="addLegalHold" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Müşteri ID / Ref</label>
                        <input type="text" wire:model="holdCustomerId" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm" placeholder="Orn: cust_1234">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Yasal Gerekçe</label>
                        <textarea wire:model="holdReason" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm" placeholder="Mahkeme kararı veya inceleme no..."></textarea>
                    </div>
                    <button type="submit" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 hover:bg-slate-800 text-white font-medium rounded-[6px] text-sm transition shadow-sm">
                        Legal Hold Uygula
                    </button>
                </form>
            </div>
        </div>

        {{-- Right: DSR Table, Holds & Lineage --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- DSR Queue --}}
            <div class="bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm space-y-4">
                <h2 class="text-lg font-semibold text-slate-900">DSR Talepleri Kuyruğu</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                <th class="pb-3 font-medium">Müşteri</th>
                                <th class="pb-3 font-medium">Talep Tipi</th>
                                <th class="pb-3 font-medium">Durum</th>
                                <th class="pb-3 font-medium">Tarih</th>
                                <th class="pb-3 font-medium">İşlem</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 text-sm">
                            @forelse($dsrs as $dsr)
                                <tr>
                                    <td class="py-3 font-medium text-slate-900">{{ $dsr->customer_id }}</td>
                                    <td class="py-3">
                                        <span class="px-2 py-0.5 text-xs font-mono rounded bg-slate-100 text-slate-700">
                                            {{ $dsr->request_type }}
                                        </span>
                                    </td>
                                    <td class="py-3">
                                        <span class="px-2 py-0.5 text-xs font-medium rounded {{ $dsr->status === 'completed' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                            {{ $dsr->status }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-xs text-slate-400">{{ $dsr->requested_at?->diffForHumans() }}</td>
                                    <td class="py-3">
                                        @if($dsr->request_type === 'export' && $dsr->status !== 'completed')
                                            <button wire:click="exportDsr({{ $dsr->id }})" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-xs bg-slate-900 hover:bg-slate-800 text-white rounded-[6px] transition">
                                                İndir
                                            </button>
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-4 text-center text-slate-400">Aktif talep bulunmuyor.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Legal Holds List --}}
            <div class="bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm space-y-4">
                <h2 class="text-lg font-semibold text-slate-900">Aktif Legal Holds (Veri Silme Engelleri)</h2>
                <div class="space-y-3">
                    @forelse($holds as $hold)
                        <div class="p-4 rounded-[8px] border border-red-100 bg-red-50/20 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">Müşteri: {{ $hold->customer_id }}</h3>
                                <p class="text-xs text-slate-500 mt-1">Gerekçe: {{ $hold->reason }}</p>
                            </div>
                            <button wire:click="releaseHold({{ $hold->id }})" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-medium rounded-[6px] transition">
                                Engeli Kaldır
                            </button>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Aktif yasal engel bulunmuyor.</p>
                    @endforelse
                </div>
            </div>

            {{-- Data Lineage Search --}}
            <div class="bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm space-y-4">
                <h2 class="text-lg font-semibold text-slate-900">Data Lineage (Veri Soy Ağacı) Sorgula</h2>
                <div class="flex gap-2">
                    <input type="text" wire:model="searchCustomerId" class="flex-1 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm" placeholder="Müşteri ID giriniz...">
                    <button wire:click="searchLineage" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 hover:bg-slate-800 text-white font-medium rounded-[6px] text-sm transition shadow-sm">
                        Sorgula
                    </button>
                </div>

                @if(!empty($lineageEvents))
                    <div class="space-y-3 mt-4 border-l-2 border-slate-200 pl-4 ml-2">
                        @foreach($lineageEvents as $ev)
                            <div class="relative">
                                <div class="absolute -left-[23px] top-1.5 w-2 h-2 rounded-full bg-slate-400"></div>
                                <p class="text-sm font-medium text-slate-900">{{ $ev['action_type'] }}</p>
                                <p class="text-xs text-slate-500">Hedef: {{ $ev['target_type'] }} (ID: {{ $ev['target_id'] }})</p>
                                <p class="text-xs text-slate-400">{{ \Carbon\Carbon::parse($ev['created_at'])->format('H:i d.m.Y') }}</p>
                            </div>
                        @endforeach
                    </div>
                @elseif($searchCustomerId)
                    <p class="text-sm text-slate-500 mt-2">Bu müşteri için lineage olayı bulunamadı.</p>
                @endif
            </div>
        </div>
    </div>
</div>
