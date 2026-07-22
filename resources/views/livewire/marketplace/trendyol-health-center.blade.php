<div class="w-full space-y-4 lg:space-y-6">
    <!-- Header & Workspace Select -->
    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl lg:text-2xl font-bold text-slate-900">Trendyol Sağlık Merkezi</h1>
                <p class="mt-1 text-sm text-slate-500">Trendyol mağazanızın entegrasyon durumunu ve API kotalarını takip edin.</p>
            </div>
            
            <div class="flex items-center gap-3">
                <select wire:model.live="selectedStoreId" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-slate-500 focus:ring-slate-500">
                    <option value="0">Mağaza Seçin</option>
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}">{{ $store->name }}</option>
                    @endforeach
                </select>
                <button wire:click="$refresh" class="rounded-[6px] bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 transition">
                    Yenile
                </button>
            </div>
        </div>
    </section>

    @if($selectedStoreId)
        <!-- Metrics Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6">
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <h3 class="text-sm font-medium text-slate-500">API Bağlantısı</h3>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-2xl font-semibold text-emerald-600">Başarılı</span>
                    <span class="px-2 py-0.5 text-xs font-mono rounded bg-emerald-100 text-emerald-700">200 OK</span>
                </div>
            </div>

            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <h3 class="text-sm font-medium text-slate-500">Son Senkronizasyon</h3>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-2xl font-semibold text-slate-900">
                        @if($recentRuns->count() > 0)
                            {{ $recentRuns->first()->created_at->diffForHumans() }}
                        @else
                            Yok
                        @endif
                    </span>
                </div>
            </div>

            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <h3 class="text-sm font-medium text-slate-500">Bekleyen Batch</h3>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-2xl font-semibold text-slate-900">0</span>
                    <span class="px-2 py-0.5 text-xs font-mono rounded bg-slate-200 text-slate-700">İşlem</span>
                </div>
            </div>

            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <h3 class="text-sm font-medium text-slate-500">Hata Oranı (24s)</h3>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-2xl font-semibold text-emerald-600">%0.0</span>
                </div>
            </div>
        </div>

        <!-- Sync Runs Table -->
        <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
            <div class="p-4 lg:p-6 border-b border-slate-200">
                <h2 class="text-base font-semibold text-slate-900">Son Senkronizasyon İşlemleri</h2>
            </div>
            
            <div class="overflow-x-auto rounded-b-[10px]">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 border-b border-slate-200 text-xs font-medium text-slate-500 uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-3">Tarih</th>
                            <th class="px-4 py-3">Tür</th>
                            <th class="px-4 py-3">Durum</th>
                            <th class="px-4 py-3 text-right">İşlenen Kayıt</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($recentRuns as $run)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3 whitespace-nowrap">{{ $run->created_at->format('d.m.Y H:i:s') }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="px-2 py-0.5 text-xs font-mono rounded bg-slate-100 text-slate-700">
                                        {{ $run->type }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($run->status === 'completed')
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-medium bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                                            <svg class="h-1.5 w-1.5 fill-emerald-500" viewBox="0 0 6 6" aria-hidden="true"><circle cx="3" cy="3" r="3" /></svg>
                                            Başarılı
                                        </span>
                                    @elseif($run->status === 'failed')
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-medium bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/10">
                                            <svg class="h-1.5 w-1.5 fill-red-500" viewBox="0 0 6 6" aria-hidden="true"><circle cx="3" cy="3" r="3" /></svg>
                                            Hata
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-medium bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20">
                                            <svg class="h-1.5 w-1.5 fill-amber-500" viewBox="0 0 6 6" aria-hidden="true"><circle cx="3" cy="3" r="3" /></svg>
                                            {{ ucfirst($run->status) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right font-medium text-slate-900">
                                    {{ $run->items_processed }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-slate-500">
                                    Bu mağaza için henüz senkronizasyon kaydı bulunmuyor.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @else
        <div class="rounded-[10px] border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
            <p class="text-slate-500">Sağlık verilerini görüntülemek için lütfen yukarıdan bir mağaza seçin.</p>
        </div>
    @endif
</div>
