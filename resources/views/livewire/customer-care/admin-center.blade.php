<div class="space-y-6 p-4 lg:p-6 max-w-[1600px] mx-auto">
    {{-- Header --}}
    <div class="bg-white p-6 rounded-[10px] border border-slate-200 shadow-sm flex justify-between items-center">
        <div>
            <h1 class="text-xl lg:text-2xl font-semibold text-slate-900">Yönetici Kontrol Merkezi (Admin Center)</h1>
            <p class="text-sm text-slate-500 mt-1">Pilot mağazaların hazırlık durumunu izleyin, kalite denetimlerini yapın ve logları dışa aktarın.</p>
        </div>
    </div>

    {{-- Stores Dashboard Table --}}
    <div class="bg-white rounded-[10px] border border-slate-200 shadow-sm overflow-hidden" id="admin-center-dashboard">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
            <h2 class="font-semibold text-slate-900 text-base">Pilot Mağaza İzleme Matrisi</h2>
            <span class="text-xs text-slate-400 font-mono">Dönem: {{ now()->format('Y-m-d') }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse table-layout-fixed min-w-[1000px]">
                <thead>
                    <tr class="bg-slate-50 text-[11px] font-bold uppercase text-slate-400 font-mono border-b border-slate-100">
                        <th class="p-4 w-[220px]">Mağaza</th>
                        <th class="p-4 w-[130px] text-center">Hazırlık (Ready)</th>
                        <th class="p-4 w-[150px] text-center">Circuit Breaker</th>
                        <th class="p-4 w-[110px] text-center">Son Eval</th>
                        <th class="p-4 w-[280px] text-center">Son 24 Saat (Draft / Auto / Block / Handoff)</th>
                        <th class="p-4 w-[100px] text-center">Bekleyen</th>
                        <th class="p-4 w-[100px] text-center">Öneri Havuzu</th>
                        <th class="p-4 w-[130px] text-center">Aksiyonlar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @foreach($storesSummary as $store)
                        <tr class="hover:bg-slate-50/50">
                            <td class="p-4">
                                <span class="font-semibold text-slate-900 block truncate">{{ $store['name'] }}</span>
                                <span class="text-xs text-slate-400 font-mono mt-0.5">{{ strtoupper($store['marketplace']) }} (ID: {{ $store['id'] }})</span>
                            </td>
                            <td class="p-4 text-center">
                                @if($store['ready'])
                                    <span class="px-2 py-0.5 text-xs font-mono font-bold rounded bg-emerald-50 text-emerald-700 border border-emerald-200">HAZIR</span>
                                @else
                                    <span class="px-2 py-0.5 text-xs font-mono font-bold rounded bg-red-50 text-red-700 border border-red-200 font-semibold">EKSİK</span>
                                @endif
                            </td>
                            <td class="p-4 text-center">
                                @if($store['circuit_breaker'] === 'open')
                                    <span class="px-2 py-0.5 text-xs font-mono font-bold rounded bg-red-100 text-red-800 border border-red-200">TRIPPED (BLOKE)</span>
                                @else
                                    <span class="px-2 py-0.5 text-xs font-mono font-bold rounded bg-slate-100 text-slate-600 border border-slate-200">AKTİF (KAPALI)</span>
                                @endif
                            </td>
                            <td class="p-4 text-center font-semibold font-mono text-slate-800">
                                {{ $store['latest_eval_score'] === 'N/A' ? 'N/A' : '%' . $store['latest_eval_score'] }}
                            </td>
                            <td class="p-4">
                                <div class="flex justify-center items-center gap-3 text-xs font-mono">
                                    <span class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded" title="AI Taslakları">{{ $store['last_24h_drafts'] }}D</span>
                                    <span class="bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded" title="Otomatik Cevaplar">{{ $store['last_24h_auto_replies'] }}A</span>
                                    <span class="bg-red-50 text-red-700 px-2 py-0.5 rounded" title="Politika Engelleri">{{ $store['last_24h_policy_blocks'] }}P</span>
                                    <span class="bg-amber-50 text-amber-700 px-2 py-0.5 rounded" title="Handoff">{{ $store['last_24h_handoffs'] }}H</span>
                                </div>
                            </td>
                            <td class="p-4 text-center font-mono font-semibold text-slate-700">
                                {{ $store['pending_dispatches'] }}
                            </td>
                            <td class="p-4 text-center font-mono font-semibold text-slate-700">
                                {{ $store['suggestion_backlog'] }}
                            </td>
                            <td class="p-4 text-center">
                                <button type="button" wire:click="exportAuditCsv({{ $store['id'] }})" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 hover:bg-slate-800 text-white font-medium text-xs rounded-[6px] transition-colors whitespace-nowrap">
                                    Denetim Dışa Aktar (CSV)
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
