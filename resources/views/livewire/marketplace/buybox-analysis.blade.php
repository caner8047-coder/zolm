<div class="w-full space-y-4 lg:space-y-6">
    <style>
        .col-resize-handle { position: absolute; right: 0; top: 0; bottom: 0; width: 4px; cursor: col-resize; background: transparent; z-index: 10; transition: background 0.15s; }
        .col-resize-handle:hover, .col-resize-handle.active { background: #6366f1; }
        .sortable-th { cursor: pointer; user-select: none; position: relative; }
        .sortable-th:hover { background: #f8fafc; }
        #buyboxTable .text-xs { font-size: 11px !important; }
        #buyboxTable .text-sm { font-size: 13px !important; }
        #buyboxTable { table-layout: fixed; width: 100%; }
        #buyboxTable th, #buyboxTable td { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    </style>

    <!-- Header & Workspace Select -->
    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl lg:text-2xl font-bold text-slate-900">Buybox Fiyat Aksiyon Merkezi</h1>
                    <span class="px-2 py-0.5 text-xs font-mono rounded bg-slate-100 text-slate-600 border border-slate-200">PROD PILOT</span>
                </div>
                <p class="mt-1 text-sm text-slate-500">Maliyet, komisyon, kargo ve KDV korumalı akıllı fiyat önerileri ve kontrollü aksiyon altyapısı.</p>
            </div>
            
            <div class="flex flex-wrap items-center gap-3">
                <select wire:model.live="selectedStoreId" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-slate-500 focus:ring-slate-500 w-full sm:w-auto">
                    <option value="0">Mağaza Seçin</option>
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}">{{ $store->name }}</option>
                    @endforeach
                </select>

                @if($selectedStoreId)
                    <button wire:click="generateRecommendations" wire:loading.attr="disabled" class="rounded-[6px] bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50 transition flex items-center gap-2">
                        <span wire:loading.remove wire:target="generateRecommendations">🔄 Önerileri Hesapla</span>
                        <span wire:loading wire:target="generateRecommendations">Hesaplanıyor...</span>
                    </button>

                    <button @click="$wire.showPolicyModal = true" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                        ⚙️ Fiyat Politikası
                    </button>
                @endif
            </div>
        </div>
    </section>

    @if($selectedStoreId)
        <!-- KPI Summary Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-8 gap-3">
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                <div class="text-xs font-medium text-slate-500">Takip Edilen</div>
                <div class="mt-1 text-xl font-bold text-slate-900">{{ number_format($summary['total']) }}</div>
            </div>

            <div class="rounded-[8px] border border-emerald-200 bg-emerald-50/50 p-3">
                <div class="text-xs font-medium text-emerald-700">Buybox Kazanan</div>
                <div class="mt-1 text-xl font-bold text-emerald-800">{{ number_format($summary['winners']) }}</div>
            </div>

            <div class="rounded-[8px] border border-rose-200 bg-rose-50/50 p-3">
                <div class="text-xs font-medium text-rose-700">Buybox Kaybeden</div>
                <div class="mt-1 text-xl font-bold text-rose-800">{{ number_format($summary['losers']) }}</div>
            </div>

            <div class="rounded-[8px] border border-indigo-200 bg-indigo-50/50 p-3">
                <div class="text-xs font-medium text-indigo-700">Fiyat Düşüş Fırsatı</div>
                <div class="mt-1 text-xl font-bold text-indigo-900">{{ number_format($summary['safe_drops']) }}</div>
            </div>

            <div class="rounded-[8px] border border-emerald-200 bg-emerald-50/50 p-3">
                <div class="text-xs font-medium text-emerald-700">Fiyat Artış Fırsatı</div>
                <div class="mt-1 text-xl font-bold text-emerald-900">{{ number_format($summary['safe_raises']) }}</div>
            </div>

            <div class="rounded-[8px] border border-amber-200 bg-amber-50/50 p-3">
                <div class="text-xs font-medium text-amber-700">Marj Engelli</div>
                <div class="mt-1 text-xl font-bold text-amber-900">{{ number_format($summary['protect_margin']) }}</div>
            </div>

            <div class="rounded-[8px] border border-rose-200 bg-rose-50/30 p-3">
                <div class="text-xs font-medium text-rose-600">Eksik Maliyet</div>
                <div class="mt-1 text-xl font-bold text-rose-900">{{ number_format($summary['missing_cost']) }}</div>
            </div>

            <div class="rounded-[8px] border border-slate-200 bg-slate-100/70 p-3">
                <div class="text-xs font-medium text-slate-500">Bekleyen Push</div>
                <div class="mt-1 text-xl font-bold text-slate-800">{{ number_format($summary['pending_push']) }}</div>
            </div>
        </div>

        <!-- Main Section -->
        <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm flex flex-col h-full">
            
            <!-- Filters & Command Bar -->
            <div class="p-4 lg:p-6 border-b border-slate-200 flex flex-col gap-4 bg-slate-50/50 rounded-t-[10px]">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <!-- Filters Left -->
                    <div class="flex flex-wrap items-center gap-2">
                        <input type="text" wire:model.live.debounce.300ms="filterBarcode" placeholder="Barkod / SKU ara..." class="rounded-[6px] border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-900 w-44 focus:ring-slate-500">

                        <select wire:model.live="filterRecommendationType" class="rounded-[6px] border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-900">
                            <option value="">Tüm Öneri Türleri</option>
                            <option value="LOWER_TO_WIN">Fiyat Düşür (Kazan)</option>
                            <option value="RAISE_WHILE_KEEPING_BUYBOX">Fiyat Yükselt (Koru)</option>
                            <option value="MATCH_BUYBOX">Buybox'a Eşitle</option>
                            <option value="PROTECT_MARGIN">Marj Korumalı (Aksiyon Yok)</option>
                            <option value="MISSING_COST">Maliyet Eksik</option>
                            <option value="STALE_BUYBOX_DATA">Veri Eski</option>
                        </select>

                        <select wire:model.live="filterRiskLevel" class="rounded-[6px] border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-900">
                            <option value="">Tüm Riskler</option>
                            <option value="low">Düşük Risk</option>
                            <option value="medium">Orta Risk</option>
                            <option value="high">Yüksek Risk</option>
                            <option value="blocked">Engellendi</option>
                        </select>

                        <select wire:model.live="filterActionable" class="rounded-[6px] border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-900">
                            <option value="">Tüm Durumlar</option>
                            <option value="actionable">Aksiyon Alınabilir</option>
                            <option value="blocked">Engellenmiş / Kısıtlı</option>
                        </select>

                        <button wire:click="clearFilters" class="text-xs text-slate-500 hover:text-slate-900 underline px-2 py-1">Filtreleri Temizle</button>
                    </div>

                    <!-- Actions Right -->
                    <div class="flex items-center gap-2">
                        @if(count($selectedRecommendationIds) > 0)
                            <button wire:click="openBulkPreviewModal" class="rounded-[6px] bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 transition">
                                Toplu Aksiyon Uygula ({{ count($selectedRecommendationIds) }})
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Table Container Desktop -->
            <div class="hidden md:block overflow-x-auto rounded-b-[10px]">
                <table id="buyboxTable" class="min-w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 border-b border-slate-200 text-xs font-medium text-slate-500 uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-3 w-10">
                                <input type="checkbox" @change="
                                    let ids = {{ json_encode($recommendations->pluck('id')->toArray()) }};
                                    if ($event.target.checked) {
                                        $wire.selectedRecommendationIds = [...new Set([...$wire.selectedRecommendationIds, ...ids])];
                                    } else {
                                        $wire.selectedRecommendationIds = $wire.selectedRecommendationIds.filter(id => !ids.includes(id));
                                    }
                                " class="rounded border-slate-300 text-slate-600">
                            </th>
                            @foreach(self::$allColumnDefs as $key => $label)
                                @if(in_array($key, $visibleColumns))
                                    <th class="px-4 py-3 {{ isset(self::$sortableColumns[$key]) ? 'sortable-th' : '' }}" 
                                        @if(isset(self::$sortableColumns[$key])) wire:click="sortTable('{{ $key }}')" @endif>
                                        <div class="flex items-center justify-between">
                                            <span>{{ $label }}</span>
                                            @if(isset(self::$sortableColumns[$key]))
                                                <span class="text-[10px] text-slate-400 ml-1">
                                                    @if($sortBy === self::$sortableColumns[$key])
                                                        {{ $sortDir === 'asc' ? '▲' : '▼' }}
                                                    @else
                                                        ⇅
                                                    @endif
                                                </span>
                                            @endif
                                        </div>
                                    </th>
                                @endif
                            @endforeach
                            <th class="px-4 py-3 text-right">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($recommendations as $rec)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3">
                                    <input type="checkbox" value="{{ $rec->id }}" wire:model.live="selectedRecommendationIds" class="rounded border-slate-300 text-slate-600">
                                </td>

                                @if(in_array('barcode', $visibleColumns))
                                    <td class="px-4 py-3 font-medium text-slate-900">
                                        {{ $rec->barcode }}
                                    </td>
                                @endif

                                @if(in_array('buybox_price', $visibleColumns))
                                    <td class="px-4 py-3 text-slate-900">
                                        ₺{{ number_format($rec->buybox_price, 2, ',', '.') }}
                                    </td>
                                @endif

                                @if(in_array('my_price', $visibleColumns))
                                    <td class="px-4 py-3 text-slate-900">
                                        ₺{{ number_format($rec->current_price, 2, ',', '.') }}
                                    </td>
                                @endif

                                @if(in_array('minimum_safe_price', $visibleColumns))
                                    <td class="px-4 py-3 font-semibold text-slate-700">
                                        ₺{{ number_format($rec->minimum_safe_price, 2, ',', '.') }}
                                    </td>
                                @endif

                                @if(in_array('recommended_price', $visibleColumns))
                                    <td class="px-4 py-3 font-bold text-indigo-700">
                                        @if($rec->recommended_price)
                                            ₺{{ number_format($rec->recommended_price, 2, ',', '.') }}
                                        @else
                                            <span class="text-slate-400 font-normal">-</span>
                                        @endif
                                    </td>
                                @endif

                                @if(in_array('price_diff', $visibleColumns))
                                    <td class="px-4 py-3">
                                        @if($rec->price_difference < 0)
                                            <span class="text-rose-600 font-medium">₺{{ number_format($rec->price_difference, 2, ',', '.') }}</span>
                                        @elseif($rec->price_difference > 0)
                                            <span class="text-emerald-600 font-medium">+₺{{ number_format($rec->price_difference, 2, ',', '.') }}</span>
                                        @else
                                            <span class="text-slate-500">₺0,00</span>
                                        @endif
                                    </td>
                                @endif

                                @if(in_array('recommendation_type', $visibleColumns))
                                    <td class="px-4 py-3">
                                        @switch($rec->recommendation_type)
                                            @case('LOWER_TO_WIN')
                                                <span class="px-2 py-0.5 text-xs font-mono rounded bg-indigo-100 text-indigo-800">Fiyat Düşür (Kazan)</span>
                                                @break
                                            @case('RAISE_WHILE_KEEPING_BUYBOX')
                                                <span class="px-2 py-0.5 text-xs font-mono rounded bg-emerald-100 text-emerald-800">Fiyat Yükselt</span>
                                                @break
                                            @case('MATCH_BUYBOX')
                                                <span class="px-2 py-0.5 text-xs font-mono rounded bg-blue-100 text-blue-800">Buybox'a Eşitle</span>
                                                @break
                                            @case('PROTECT_MARGIN')
                                                <span class="px-2 py-0.5 text-xs font-mono rounded bg-amber-100 text-amber-800">Marj Korumalı</span>
                                                @break
                                            @case('MISSING_COST')
                                                <span class="px-2 py-0.5 text-xs font-mono rounded bg-rose-100 text-rose-800">Maliyet Eksik</span>
                                                @break
                                            @case('STALE_BUYBOX_DATA')
                                                <span class="px-2 py-0.5 text-xs font-mono rounded bg-slate-100 text-slate-700">Veri Eski</span>
                                                @break
                                            @default
                                                <span class="px-2 py-0.5 text-xs font-mono rounded bg-slate-100 text-slate-600">{{ $rec->recommendation_type }}</span>
                                        @endswitch
                                    </td>
                                @endif

                                @if(in_array('risk_level', $visibleColumns))
                                    <td class="px-4 py-3">
                                        @switch($rec->risk_level)
                                            @case('low')
                                                <span class="text-xs text-emerald-600 font-medium">● Düşük</span>
                                                @break
                                            @case('medium')
                                                <span class="text-xs text-amber-600 font-medium">● Orta</span>
                                                @break
                                            @case('high')
                                                <span class="text-xs text-rose-600 font-medium">● Yüksek</span>
                                                @break
                                            @case('blocked')
                                                <span class="text-xs text-slate-400 font-medium">🚫 Engellendi</span>
                                                @break
                                        @endswitch
                                    </td>
                                @endif

                                @if(in_array('status', $visibleColumns))
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 text-xs font-mono rounded border bg-slate-50 text-slate-700">
                                            {{ strtoupper($rec->status) }}
                                        </span>
                                    </td>
                                @endif

                                <td class="px-4 py-3 text-right space-x-2">
                                    <button wire:click="openDetailModal({{ $rec->id }})" class="text-xs text-slate-700 hover:text-slate-900 font-medium underline">Detay</button>

                                    @if($rec->isActionable() && $flags['manual_push'])
                                        <button wire:click="applySingleAction({{ $rec->id }})" class="rounded bg-slate-900 px-2.5 py-1 text-xs text-white hover:bg-slate-800 transition font-medium">
                                            Uygula
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="100%" class="px-4 py-8 text-center text-slate-500">
                                    Filtrelere uygun öneri bulunamadı. "Önerileri Hesapla" butonuna basarak güncel analizleri alabilirsiniz.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-200">
                @if(method_exists($recommendations, 'links'))
                    {{ $recommendations->links() }}
                @endif
            </div>
        </section>

        <!-- Detail Slide-Over Modal -->
        @if($detailRec)
            <div class="fixed inset-0 z-50 overflow-hidden" aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
                <div class="absolute inset-0 bg-slate-900/50 transition-opacity" wire:click="closeDetailModal"></div>

                <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
                    <div class="w-screen max-w-md bg-white shadow-xl flex flex-col justify-between">
                        <div class="p-6 overflow-y-auto space-y-6">
                            <div class="flex items-center justify-between border-b pb-4">
                                <div>
                                    <h2 class="text-lg font-bold text-slate-900">Fiyat Önerisi Detayı</h2>
                                    <p class="text-xs text-slate-500 font-mono mt-0.5">Barkod: {{ $detailRec->barcode }}</p>
                                </div>
                                <button wire:click="closeDetailModal" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                            </div>

                            <!-- Price Comparison Cards -->
                            <div class="grid grid-cols-2 gap-3">
                                <div class="p-3 rounded-lg bg-slate-50 border border-slate-200">
                                    <span class="text-xs text-slate-500">Mevcut Fiyat</span>
                                    <div class="text-lg font-bold text-slate-900">₺{{ number_format($detailRec->current_price, 2, ',', '.') }}</div>
                                </div>
                                <div class="p-3 rounded-lg bg-indigo-50 border border-indigo-200">
                                    <span class="text-xs text-indigo-700">Buybox Fiyatı</span>
                                    <div class="text-lg font-bold text-indigo-900">₺{{ number_format($detailRec->buybox_price, 2, ',', '.') }}</div>
                                </div>
                            </div>

                            <!-- Minimum Safe Price Warning -->
                            <div class="p-4 rounded-lg bg-amber-50 border border-amber-200">
                                <div class="text-xs font-semibold text-amber-800">Minimum Güvenli Fiyat Sınırı</div>
                                <div class="text-xl font-bold text-amber-900 mt-1">₺{{ number_format($detailRec->minimum_safe_price, 2, ',', '.') }}</div>
                                <p class="text-xs text-amber-700 mt-1">ZOLM Güvenlik Protokolü gereği bu fiyatın altında hiçbir fiyat pazaryerine push edilemez.</p>
                            </div>

                            <!-- Cost Breakdown -->
                            <div class="space-y-2 text-sm border-t pt-4">
                                <h3 class="font-bold text-slate-900">Maliyet Kırılımı</h3>
                                <div class="flex justify-between py-1 border-b border-dashed">
                                    <span class="text-slate-500">Ürün Maliyeti (COGS)</span>
                                    <span class="font-medium">₺{{ number_format($detailRec->unit_cost, 2, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between py-1 border-b border-dashed">
                                    <span class="text-slate-500">Tahmini Kargo Gideri</span>
                                    <span class="font-medium">₺{{ number_format($detailRec->cargo_cost, 2, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between py-1 border-b border-dashed">
                                    <span class="text-slate-500">Pazaryeri Komisyonu</span>
                                    <span class="font-medium">₺{{ number_format($detailRec->commission_amount, 2, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between py-1 border-b border-dashed">
                                    <span class="text-slate-500">Hesaplanan KDV</span>
                                    <span class="font-medium">₺{{ number_format($detailRec->vat_amount, 2, ',', '.') }}</span>
                                </div>
                            </div>

                            <!-- Custom Price Simulation Input -->
                            <div class="space-y-2 border-t pt-4">
                                <label class="block text-sm font-bold text-slate-900">Canlı Fiyat Simülasyonu</label>
                                <input type="number" step="0.10" wire:model.live.debounce.300ms="customRequestedPrice" class="w-full rounded-[6px] border border-slate-300 px-3 py-2 text-sm font-bold text-slate-900 focus:ring-indigo-500">

                                @if($detailSimulation)
                                    <div class="p-3 rounded bg-slate-50 text-xs space-y-1">
                                        <div class="flex justify-between">
                                            <span>Hesaplanan Net Kâr:</span>
                                            <span class="font-bold {{ $detailSimulation['net_profit'] > 0 ? 'text-emerald-600' : 'text-rose-600' }}">₺{{ number_format($detailSimulation['net_profit'], 2, ',', '.') }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Kâr Marjı:</span>
                                            <span class="font-bold">%{{ number_format($detailSimulation['profit_margin_percent'], 2, ',', '.') }}</span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Footer Action -->
                        <div class="p-4 border-t bg-slate-50 flex gap-3">
                            <button wire:click="closeDetailModal" class="w-1/2 rounded-[6px] border border-slate-300 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Kapat</button>
                            <button wire:click="applySingleAction({{ $detailRec->id }}, {{ $customRequestedPrice }})" class="w-1/2 rounded-[6px] bg-slate-900 py-2 text-sm font-medium text-white hover:bg-slate-800">Fiyatı Gönder</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Bulk Action Preview Modal -->
        @if($showBulkPreviewModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
                <div class="bg-white rounded-xl shadow-xl max-w-lg w-full p-6 space-y-4">
                    <h2 class="text-lg font-bold text-slate-900">Toplu Fiyat Gönderimi Önizlemesi</h2>
                    <p class="text-sm text-slate-600">Seçtiğiniz {{ count($selectedRecommendationIds) }} adet ürün için fiyat güncellemeleri kuyruğa alınacaktır.</p>

                    <div class="p-4 rounded-lg bg-indigo-50 border border-indigo-200 text-xs space-y-2">
                        <div class="font-bold text-indigo-900">Güvenlik Kontrolü:</div>
                        <p class="text-indigo-700">Maliyeti eksik olan veya minimum güvenli fiyatın altında kalan ürünler otomatik olarak filtrelenecektir.</p>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button wire:click="closeBulkPreviewModal" class="rounded-[6px] border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">İptal</button>
                        <button wire:click="confirmBulkActions" class="rounded-[6px] bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Onayla ve Gönder</button>
                    </div>
                </div>
            </div>
        @endif

        <!-- Price Policy Modal -->
        @if($showPolicyModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
                <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-4">
                    <h2 class="text-lg font-bold text-slate-900">Mağaza Fiyat Politikası Ayarları</h2>

                    <div class="space-y-3 text-sm">
                        <div>
                            <label class="block text-xs font-medium text-slate-500">Minimum Kâr Tutarı (₺)</label>
                            <input type="number" step="1" wire:model="policyForm.min_profit_amount" class="w-full rounded-[6px] border border-slate-300 px-3 py-1.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500">Minimum Kâr Marjı (%)</label>
                            <input type="number" step="0.5" wire:model="policyForm.min_profit_margin" class="w-full rounded-[6px] border border-slate-300 px-3 py-1.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500">Fiyat Düşürme Adımı (₺)</label>
                            <input type="number" step="0.05" wire:model="policyForm.price_step" class="w-full rounded-[6px] border border-slate-300 px-3 py-1.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500">Maksimum Tek Seferlik Düşüş (%)</label>
                            <input type="number" step="1" wire:model="policyForm.max_single_drop_percent" class="w-full rounded-[6px] border border-slate-300 px-3 py-1.5 text-sm">
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button @click="$wire.showPolicyModal = false" class="rounded-[6px] border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">İptal</button>
                        <button wire:click="savePolicySettings" class="rounded-[6px] bg-slate-900 px-4 py-2 text-sm font-medium text-white">Kaydet</button>
                    </div>
                </div>
            </div>
        @endif

    @else
        <div class="rounded-[10px] border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
            <p class="text-slate-500">Analiz verilerini görüntülemek için lütfen yukarıdan bir mağaza seçin.</p>
        </div>
    @endif
</div>
