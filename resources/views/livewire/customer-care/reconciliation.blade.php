<div class="space-y-6 p-4 lg:p-6 bg-slate-50/50 min-h-screen">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-xl lg:text-2xl font-semibold text-slate-900">Projection Backfill & Reconciliation</h1>
            <p class="text-sm text-slate-500">Projeksiyon tutarsızlıkları (drift), webhook kaçırma analizleri ve kuyruk veritabanı onarım merkezi.</p>
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
        {{-- Cursor Health Status (Left Side) --}}
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm space-y-4">
                <div class="flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-slate-900">Kanal Senkronizasyon Durumu</h2>
                    <button wire:click="runReconciliation" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-medium rounded-[6px] transition">
                        Analiz Başlat
                    </button>
                </div>
                <div class="space-y-3">
                    @forelse($cursors as $cur)
                        <div class="p-3 rounded-[8px] border border-slate-100 bg-slate-50/50 space-y-2">
                            <div class="flex justify-between items-center text-xs">
                                <span class="font-semibold text-slate-800 uppercase">{{ $cur->channel_type }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase {{ $cur->status === 'synced' ? 'bg-emerald-100 text-emerald-800' : ($cur->status === 'unknown' ? 'bg-slate-200 text-slate-700' : 'bg-red-100 text-red-800') }}">
                                    {{ $cur->status }}
                                </span>
                            </div>
                            <div class="text-[10px] text-slate-500 space-y-0.5">
                                <p>Son Synced ID: <span class="font-mono">{{ $cur->last_seen_external_id ?? '-' }}</span></p>
                                <p>Son Eşleşme: {{ $cur->last_synced_at ? $cur->last_synced_at->diffForHumans() : '-' }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400">Aktif kanal cursor kaydı bulunmuyor.</p>
                    @endforelse
                </div>
            </div>

            {{-- Reconciliation Runs --}}
            <div class="bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm space-y-4">
                <h2 class="text-lg font-semibold text-slate-900">Son Analiz Raporları</h2>
                <div class="space-y-3">
                    @forelse($runs as $run)
                        <div class="p-3 rounded-[8px] border border-slate-100 bg-white text-xs space-y-1">
                            <div class="flex justify-between font-semibold">
                                <span class="text-slate-800">Analiz #{{ $run->id }}</span>
                                <span class="text-slate-400 text-[10px]">{{ $run->started_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-[10px] text-slate-500 font-mono">{{ json_encode($run->summary_json) }}</p>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400">Henüz herhangi bir analiz raporu üretilmedi.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Drift Findings & Repairs (Right Side) --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm space-y-4">
                <h2 class="text-lg font-semibold text-slate-900">Tespit Edilen Projeksiyon Tutarsızlıkları (Drifts)</h2>
                <div class="overflow-x-auto">
                    <table class="w-full table-layout-fixed text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 text-xs font-semibold text-slate-400">
                                <th class="py-2.5">Bulgu ID</th>
                                <th>Tip</th>
                                <th>Detaylar</th>
                                <th>Durum</th>
                                <th class="text-right">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($findings as $find)
                                <tr class="border-b border-slate-100 text-xs text-slate-700">
                                    <td class="py-3 font-semibold">#{{ $find->id }}</td>
                                    <td>
                                        <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase bg-amber-50 text-amber-800 border border-amber-100">
                                            {{ $find->finding_type }}
                                        </span>
                                    </td>
                                    <td class="font-mono text-[10px] whitespace-pre-wrap max-w-xs overflow-hidden text-ellipsis">{{ json_encode($find->details_json) }}</td>
                                    <td>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase {{ $find->status === 'repaired' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">
                                            {{ $find->status }}
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        @if($find->status !== 'repaired')
                                            <button wire:click="repairFinding({{ $find->id }})" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-[6px] text-[10px] transition">
                                                Onar (Repair)
                                            </button>
                                        @else
                                            <span class="text-slate-400 text-[10px]">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-4 text-center text-slate-400">Herhangi bir tutarsızlık bulgusu bulunamadı.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
