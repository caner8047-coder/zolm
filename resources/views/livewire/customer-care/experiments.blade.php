<div class="space-y-6 p-4 lg:p-6 bg-slate-50/50 min-h-screen">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-xl lg:text-2xl font-semibold text-slate-900">Experimentation Lab</h1>
            <p class="text-sm text-slate-500">AI prompt, policy ve bilgi paketi varyantlarını shadow/offline ortamda karşılaştırın.</p>
        </div>
        <div class="w-full sm:w-auto">
            <select wire:model.live="selectedStoreId" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:outline-none">
                @foreach($stores as $st)
                    <option value="{{ $st->id }}">{{ $st->store_name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if($errorMessage)
        <div class="bg-red-50 border border-red-200 rounded-[8px] p-3 text-sm text-red-700">{{ $errorMessage }}</div>
    @endif
    @if($successMessage)
        <div class="bg-emerald-50 border border-emerald-200 rounded-[8px] p-3 text-sm text-emerald-700">{{ $successMessage }}</div>
    @endif

    {{-- Deney Listesi --}}
    <div class="bg-white rounded-[10px] border border-slate-200 shadow-sm p-4 lg:p-6">
        <h2 class="text-base font-semibold text-slate-900 mb-4">Deneyler</h2>
        @if($experiments->isEmpty())
            <div class="text-sm text-slate-400 py-8 text-center">Bu mağaza için henüz deney tanımlanmamış.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse" style="table-layout:fixed">
                    <thead>
                        <tr class="border-b border-slate-200">
                            <th class="py-2 px-3 text-left text-xs font-medium text-slate-500">Ad</th>
                            <th class="py-2 px-3 text-left text-xs font-medium text-slate-500">Tür</th>
                            <th class="py-2 px-3 text-left text-xs font-medium text-slate-500">Durum</th>
                            <th class="py-2 px-3 text-right text-xs font-medium text-slate-500">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($experiments as $exp)
                            <tr>
                                <td class="py-2 px-3 text-slate-800 overflow-hidden text-ellipsis">{{ $exp->name }}</td>
                                <td class="py-2 px-3"><span class="px-2 py-0.5 text-xs font-mono rounded bg-slate-100 text-slate-600">{{ $exp->type }}</span></td>
                                <td class="py-2 px-3">
                                    <span class="px-2 py-0.5 text-xs font-mono rounded
                                        {{ $exp->status === 'ready' ? 'bg-blue-100 text-blue-800' :
                                           ($exp->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600') }}">
                                        {{ $exp->status }}
                                    </span>
                                </td>
                                <td class="py-2 px-3 text-right">
                                    @if(in_array($exp->status, ['ready', 'running']))
                                        <button wire:click="runExperiment({{ $exp->id }}, true)"
                                            id="btn-dry-run-{{ $exp->id }}"
                                            class="w-full sm:w-auto px-4 py-3 sm:py-2 text-xs bg-slate-900 text-white rounded-[6px] hover:bg-slate-700 transition">
                                            Dry-Run
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Uyarı --}}
    <div class="bg-amber-50 border border-amber-200 rounded-[8px] p-4 text-sm text-amber-800">
        <strong>Önemli:</strong> Deney sonuçları hiçbir zaman otomatik olarak canlıya alınmaz.
        Winner candidate yayına almak için AM Release workflow ve governance onayı gerekir.
    </div>
</div>
