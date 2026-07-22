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
            
            <!-- Tab Headers -->
            <div class="border-b border-slate-200">
                <nav class="-mb-px flex space-x-6 px-6" aria-label="Tabs">
                    <button wire:click="$set('activeTab', 'analysis')" class="shrink-0 border-b-2 py-4 px-1 text-sm font-medium {{ $activeTab === 'analysis' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }}">
                        📋 Öneriler ve Analiz
                    </button>
                    <button wire:click="$set('activeTab', 'pilot')" class="shrink-0 border-b-2 py-4 px-1 text-sm font-medium {{ $activeTab === 'pilot' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }}">
                        🛡️ Pilot & Gölge Mod (Shadow)
                    </button>
                    <button wire:click="$set('activeTab', 'actions')" class="shrink-0 border-b-2 py-4 px-1 text-sm font-medium {{ $activeTab === 'actions' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }}">
                        📜 Aksiyon Geçmişi
                    </button>
                </nav>
            </div>

            @if($activeTab === 'analysis')
                <!-- Filters & Command Bar -->
                <div class="p-4 lg:p-6 border-b border-slate-200 flex flex-col gap-4 bg-slate-50/50">
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
            @endif

            @if($activeTab === 'pilot')
                <!-- Pilot & Shadow Mode Panel -->
                <div class="p-6 space-y-6">
                    
                    @if($emergencyStopActive)
                        <div class="rounded-lg bg-rose-50 border border-rose-200 p-4 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="text-2xl">⚠️</span>
                                <div>
                                    <h3 class="text-sm font-bold text-rose-800">ACİL DURDURMA (EMERGENCY STOP) AKTİF</h3>
                                    <p class="text-xs text-rose-700 mt-0.5">Tüm fiyat aksiyonları ve otomatik gönderimler şu an bloke edilmiştir.</p>
                                </div>
                            </div>
                            <button wire:click="clearStoreEmergencyStop" class="rounded-[6px] bg-rose-600 px-4 py-2 text-xs font-semibold text-white hover:bg-rose-700 transition">
                                Kilidi Kaldır (Normal Mod)
                            </button>
                        </div>
                    @else
                        <div class="flex justify-between items-center bg-slate-50 border border-slate-200 rounded-lg p-4">
                            <div class="text-sm text-slate-600">
                                <span class="font-bold text-slate-900">Güvenlik Kontrolü:</span> Herhangi bir kural dışı durumda veya beklenmeyen fiyat hareketlerinde sistem akışını anında kesebilirsiniz.
                            </div>
                            <button @click="$wire.showEmergencyStopModal = true" class="rounded-[6px] bg-rose-600 px-4 py-2 text-xs font-semibold text-white hover:bg-rose-700 transition">
                                🛑 Acil Durdurma (Emergency Stop)
                            </button>
                        </div>
                    @endif

                    <!-- Canary Readiness & Approval Section -->
                    @if($canaryReadiness)
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Readiness Card -->
                            <div class="rounded-[10px] border border-slate-200 bg-white p-5 shadow-sm space-y-4 lg:col-span-2">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                                        🛡️ Canary Hazırlık Durumu (Readiness)
                                    </h3>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $canaryReadiness['ready'] ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                        {{ $canaryReadiness['ready'] ? 'UYGUN' : 'UYGUN DEĞİL' }}
                                    </span>
                                </div>

                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 bg-slate-50/60 rounded-lg p-3 text-xs">
                                    <div>
                                        <div class="text-slate-500">Gözlem Süresi</div>
                                        <div class="font-semibold text-slate-900">{{ $canaryReadiness['shadow_duration_hours'] }} Saat</div>
                                    </div>
                                    <div>
                                        <div class="text-slate-500">API Başarı Oranı</div>
                                        <div class="font-semibold text-slate-900">%{{ $canaryReadiness['api_success_rate'] }}</div>
                                    </div>
                                    <div>
                                        <div class="text-slate-500">Gölge Doğruluk Oranı</div>
                                        <div class="font-semibold text-slate-900">%{{ $canaryReadiness['shadow_accuracy_rate'] }}</div>
                                    </div>
                                    <div>
                                        <div class="text-slate-500">Uygun Ürün</div>
                                        <div class="font-semibold text-slate-900">{{ $canaryReadiness['eligible_product_count'] }} Ürün</div>
                                    </div>
                                </div>

                                <div class="space-y-1">
                                    <div class="text-xs font-bold text-slate-700">Kriter Geçiş Raporu:</div>
                                    <div class="max-h-32 overflow-y-auto space-y-1 text-xs">
                                        @foreach($canaryReadiness['passed_criteria'] as $c)
                                            <div class="text-emerald-700 flex items-center gap-1.5">
                                                <span>✓</span> {{ $c }}
                                            </div>
                                        @endforeach
                                        @foreach($canaryReadiness['failed_criteria'] as $c)
                                            <div class="text-rose-700 flex items-center gap-1.5 font-semibold">
                                                <span>✗</span> {{ $c }}
                                            </div>
                                        @endforeach
                                        @foreach($canaryReadiness['warning_criteria'] as $c)
                                            <div class="text-amber-700 flex items-center gap-1.5">
                                                <span>!</span> {{ $c }}
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="border-t border-slate-100 pt-3 mt-3">
                                    <h4 class="text-xs font-bold text-slate-700 mb-2">📊 Gölge Kanıt İlerleme Göstergesi (Shadow Progress)</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2 text-xs">
                                        <!-- Duration -->
                                        <div>
                                            <div class="flex justify-between text-slate-500 mb-1">
                                                <span>Gölge Çalışma Süresi</span>
                                                <span class="font-bold text-slate-800">{{ $canaryReadiness['shadow_duration_hours'] }} / 24 Saat</span>
                                            </div>
                                            <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                                <div class="bg-indigo-600 h-full rounded-full" style="width: {{ min(100, ($canaryReadiness['shadow_duration_hours'] / 24) * 100) }}%"></div>
                                            </div>
                                        </div>
                                        <!-- Records -->
                                        <div>
                                            <div class="flex justify-between text-slate-500 mb-1">
                                                <span>Gölge Önerisi</span>
                                                <span class="font-bold text-slate-800">{{ $canaryReadiness['total_shadow_records'] ?? 0 }} / 20</span>
                                            </div>
                                            <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                                <div class="bg-indigo-600 h-full rounded-full" style="width: {{ min(100, (($canaryReadiness['total_shadow_records'] ?? 0) / 20) * 100) }}%"></div>
                                            </div>
                                        </div>
                                        <!-- Evaluations -->
                                        <div>
                                            <div class="flex justify-between text-slate-500 mb-1">
                                                <span>Gölge Değerlendirme</span>
                                                <span class="font-bold text-slate-800">{{ $canaryReadiness['total_evaluations'] ?? 0 }} / 20</span>
                                            </div>
                                            <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                                <div class="bg-indigo-600 h-full rounded-full" style="width: {{ min(100, (($canaryReadiness['total_evaluations'] ?? 0) / 20) * 100) }}%"></div>
                                            </div>
                                        </div>
                                        <!-- Cycles -->
                                        <div>
                                            <div class="flex justify-between text-slate-500 mb-1">
                                                <span>Buybox Döngüsü (Cycle)</span>
                                                <span class="font-bold text-slate-800">{{ $canaryReadiness['buybox_cycles'] ?? 0 }} / 3</span>
                                            </div>
                                            <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                                <div class="bg-indigo-600 h-full rounded-full" style="width: {{ min(100, (($canaryReadiness['buybox_cycles'] ?? 0) / 3) * 100) }}%"></div>
                                            </div>
                                        </div>
                                        <!-- API Samples -->
                                        <div>
                                            <div class="flex justify-between text-slate-500 mb-1">
                                                <span>API Örneklemi</span>
                                                <span class="font-bold text-slate-800">{{ $canaryReadiness['api_sample_count'] ?? 0 }} / 20</span>
                                            </div>
                                            <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                                <div class="bg-indigo-600 h-full rounded-full" style="width: {{ min(100, (($canaryReadiness['api_sample_count'] ?? 0) / 20) * 100) }}%"></div>
                                            </div>
                                        </div>
                                        <!-- Queue Samples -->
                                        <div>
                                            <div class="flex justify-between text-slate-500 mb-1">
                                                <span>Kuyruk Örneklemi</span>
                                                <span class="font-bold text-slate-800">{{ $canaryReadiness['queue_sample_count'] ?? 0 }} / 20</span>
                                            </div>
                                            <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                                <div class="bg-indigo-600 h-full rounded-full" style="width: {{ min(100, (($canaryReadiness['queue_sample_count'] ?? 0) / 20) * 100) }}%"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-[10px] text-slate-400 mt-2 pt-2 border-t border-slate-50">
                                        <div>Son Eşitleme: <span class="font-medium text-slate-600">{{ $lastSyncTime ? $lastSyncTime->format('d.m.Y H:i') : 'Yapılmadı' }}</span></div>
                                        <div>Son Değerlendirme: <span class="font-medium text-slate-600">{{ $lastEvaluationTime ? $lastEvaluationTime->format('d.m.Y H:i') : 'Yapılmadı' }}</span></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Approval Card -->
                            <div class="rounded-[10px] border border-slate-200 bg-white p-5 shadow-sm flex flex-col justify-between">
                                <div class="space-y-3">
                                    <h3 class="text-sm font-bold text-slate-900">
                                        🔑 Aktif Canary Onayı
                                    </h3>
                                    
                                    @if($activeApproval)
                                        <div class="bg-indigo-50 border border-indigo-100 rounded-lg p-3 text-xs space-y-2">
                                            <div>
                                                <span class="text-slate-500">Kapsam:</span> 
                                                <span class="font-bold text-indigo-900 uppercase">{{ $activeApproval->approval_scope }}</span>
                                            </div>
                                            <div>
                                                <span class="text-slate-500">Barkodlar:</span> 
                                                <span class="font-mono bg-indigo-100/50 px-1.5 py-0.5 rounded text-indigo-900">
                                                    {{ implode(', ', $activeApproval->approved_product_ids ?? []) }}
                                                </span>
                                            </div>
                                            <div>
                                                <span class="text-slate-500">Süre Sonu:</span> 
                                                <span class="font-semibold text-slate-700">{{ $activeApproval->expires_at->diffForHumans() }}</span>
                                            </div>
                                        </div>
                                        
                                        <button wire:click="revokeCanaryUI" class="w-full rounded-[6px] border border-rose-200 text-rose-700 bg-white px-3 py-2 text-xs font-semibold hover:bg-rose-50 transition">
                                            Onayı Geri Çek (İptal Et)
                                        </button>
                                    @else
                                        <div class="text-xs text-slate-500">
                                            Şu anda aktif bir Canary fiyatlama onayı bulunmuyor. Otomatik fiyat aksiyonu gönderimi devre dışıdır.
                                        </div>
                                        
                                        <div class="flex flex-col gap-2 mt-4">
                                            <button wire:click="approveCanaryUI('single_product')" @if(!$canaryReadiness['ready']) disabled @endif class="w-full rounded-[6px] bg-slate-900 text-white px-3 py-2 text-xs font-semibold hover:bg-slate-800 disabled:opacity-40 disabled:cursor-not-allowed transition">
                                                Tek Ürün İçin Onayla
                                            </button>
                                            <button wire:click="approveCanaryUI('three_products')" @if(!$canaryReadiness['ready'] || $canaryReadiness['shadow_duration_hours'] < 72) disabled @endif class="w-full rounded-[6px] border border-slate-200 text-slate-700 bg-white px-3 py-2 text-xs font-semibold hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition">
                                                Üç Ürün İçin Onayla (72 Saat Gölge Gerekli)
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Dry-Run Sertifikasyon Kartı --}}
                    @if($latestCertification)
                        <div class="rounded-[10px] border border-slate-200 bg-white p-5 shadow-sm mt-4">
                            <h3 class="text-sm font-bold text-slate-900 mb-3">🛡️ Son Dry-Run Sertifikasyonu</h3>

                            @php
                                $certResult = $latestCertification->certification_result;
                                $certColors = [
                                    'certified_zero_write'         => ['bg-emerald-50 border-emerald-200', 'text-emerald-700', '✅'],
                                    'blocked_insufficient_evidence' => ['bg-amber-50 border-amber-200', 'text-amber-700', '⚠️'],
                                    'blocked_readiness'            => ['bg-rose-50 border-rose-200', 'text-rose-700', '🔴'],
                                    'blocked_approval'             => ['bg-rose-50 border-rose-200', 'text-rose-700', '🔴'],
                                    'blocked_write_guard'          => ['bg-red-50 border-red-200', 'text-red-700', '🚫'],
                                    'failed'                       => ['bg-slate-50 border-slate-200', 'text-slate-700', '❌'],
                                ];
                                [$cardClass, $textClass, $icon] = $certColors[$certResult] ?? ['bg-slate-50 border-slate-200', 'text-slate-700', '—'];
                            @endphp

                            <div class="rounded-lg border {{ $cardClass }} px-3 py-2.5 text-xs space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="{{ $textClass }} font-bold">{{ $icon }} {{ strtoupper(str_replace('_', ' ', $certResult)) }}</span>
                                    <span class="text-slate-400 text-[10px]">{{ $latestCertification->certified_at?->format('d.m.Y H:i') ?? $latestCertification->created_at->format('d.m.Y H:i') }}</span>
                                </div>
                                <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-slate-600 pt-1">
                                    <div>
                                        <span class="text-slate-400">Readiness:</span>
                                        <span class="font-medium {{ $latestCertification->readiness_passed ? 'text-emerald-700' : 'text-rose-600' }}">
                                            {{ $latestCertification->readiness_passed ? '🟢 UYGUN' : '🔴 UYGUN DEĞİL' }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400">Write Guard:</span>
                                        <span class="font-medium text-slate-700">{{ $latestCertification->write_guard_result ?? '—' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400">Gerçek Push:</span>
                                        <span class="font-mono font-bold {{ $latestCertification->real_price_push_count === 0 ? 'text-emerald-700' : 'text-red-700' }}">
                                            {{ $latestCertification->real_price_push_count }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400">Listing Değişti:</span>
                                        <span class="font-medium {{ $latestCertification->listing_price_changed ? 'text-red-700' : 'text-emerald-700' }}">
                                            {{ $latestCertification->listing_price_changed ? 'EVET' : 'HAYIR' }}
                                        </span>
                                    </div>
                                </div>
                                @if($latestCertification->correlation_id)
                                    <div class="text-[10px] text-slate-400 font-mono truncate pt-0.5 border-t border-slate-100">
                                        ID: {{ $latestCertification->correlation_id }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Add to Pilot Whitelist Form --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 border p-4 rounded-lg bg-slate-50/50">
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Eklenecek Ürün Barkodu</label>
                            <input type="text" wire:model="pilotSearchBarcode" placeholder="Barkod girin..." class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Çalışma Modu</label>
                            <select wire:model="pilotMode" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm">
                                <option value="shadow">Shadow (Sadece Gölge Öneri)</option>
                                <option value="manual_pilot">Manual Pilot (Kullanıcı Onaylı)</option>
                                <option value="canary_auto">Canary (Kontrollü Otomatik)</option>
                            </select>
                        </div>
                        <div class="flex items-end gap-2">
                            <div class="grow">
                                <label class="block text-xs font-medium text-slate-500 mb-1">Eklenme Gerekçesi</label>
                                <input type="text" wire:model="pilotReason" placeholder="Örn: Buybox kazanım takibi" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm">
                            </div>
                            <button wire:click="addToPilotList" class="rounded-[6px] bg-slate-900 text-white px-4 py-2 text-sm font-semibold hover:bg-slate-800 transition">
                                Ekle
                            </button>
                        </div>
                    </div>

                    <!-- Whitelist Table -->
                    <div class="border rounded-lg overflow-hidden bg-white">
                        <div class="p-4 border-b border-slate-200 flex justify-between items-center bg-slate-50/70">
                            <h3 class="text-sm font-bold text-slate-900">Aktif Pilot ve Gölge Ürünler ({{ $pilotProducts->count() }} / {{ min(10, max(1, ceil($summary['total'] * 0.01))) }})</h3>
                            <button wire:click="exportPilotExcelReport" class="text-xs text-indigo-600 hover:text-indigo-900 font-semibold underline">
                                📥 Raporu Excel Olarak İndir
                            </button>
                        </div>

                        <table class="min-w-full text-left text-sm text-slate-600">
                            <thead class="bg-slate-50 border-b border-slate-200 text-xs font-medium text-slate-500 uppercase">
                                <tr>
                                    <th class="px-4 py-3">Barkod</th>
                                    <th class="px-4 py-3">Çalışma Modu</th>
                                    <th class="px-4 py-3">Risk Skoru</th>
                                    <th class="px-4 py-3">Eklenme Gerekçesi</th>
                                    <th class="px-4 py-3">Fiyat Durumu</th>
                                    <th class="px-4 py-3">Fiyat Kilidi</th>
                                    <th class="px-4 py-3 text-right">İşlem</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @forelse($pilotProducts as $p)
                                    @php
                                        $rec = \App\Models\MpPriceRecommendation::where('store_id', $store->id)->where('barcode', $p->barcode)->first();
                                        $isLocked = app(\App\Services\Marketplace\MarketplacePriceLockService::class)->isLocked($store->id, $p->barcode);
                                    @endphp
                                    <tr class="hover:bg-slate-50/50">
                                        <td class="px-4 py-3 font-semibold text-slate-900">
                                            {{ $p->barcode }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <select @change="$wire.updatePilotMode('{{ $p->barcode }}', $event.target.value)" class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs text-slate-900">
                                                <option value="shadow" {{ $p->mode === 'shadow' ? 'selected' : '' }}>Shadow Mod (Gölge)</option>
                                                <option value="manual_pilot" {{ $p->mode === 'manual_pilot' ? 'selected' : '' }}>Manual Pilot (Onaylı)</option>
                                                <option value="canary_auto" {{ $p->mode === 'canary_auto' ? 'selected' : '' }}>Canary (Otomatik)</option>
                                                <option value="paused" {{ $p->mode === 'paused' ? 'selected' : '' }}>Duraklatıldı (Paused)</option>
                                            </select>
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($p->risk_score === 'low')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800 font-mono">LOW</span>
                                            @elseif($p->risk_score === 'medium')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 font-mono">MEDIUM</span>
                                            @elseif($p->risk_score === 'high')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800 font-mono">HIGH</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-rose-100 text-rose-800 font-mono" title="{{ $p->exclusion_reason }}">BLOCKED</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-xs text-slate-500">
                                            {{ $p->inclusion_reason }}
                                        </td>
                                        <td class="px-4 py-3 text-xs">
                                            @if($rec)
                                                Mevcut: ₺{{ number_format($rec->current_price, 2, ',', '.') }} | Önerilen: <span class="font-bold text-indigo-600">₺{{ number_format($rec->recommended_price, 2, ',', '.') }}</span>
                                            @else
                                                <span class="text-slate-400">Analiz yapılmadı</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($isLocked)
                                                <button @click="$wire.toggleManualLock('{{ $p->barcode }}', false)" class="px-2 py-1 rounded text-xs bg-rose-100 text-rose-800 hover:bg-rose-200 transition font-mono">
                                                    🔒 KİLİTLİ
                                                </button>
                                            @else
                                                <button @click="$wire.toggleManualLock('{{ $p->barcode }}', true)" class="px-2 py-1 rounded text-xs bg-slate-100 text-slate-600 hover:bg-slate-200 transition font-mono">
                                                    🔓 KİLİTSİZ
                                                </button>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <button @click="$wire.removeFromPilotList('{{ $p->barcode }}')" class="text-xs text-rose-600 hover:text-rose-950 underline font-medium">Kapsam Dışı Yap</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="100%" class="px-4 py-8 text-center text-slate-500">
                                            Pilot kapsamına alınmış ürün bulunmamaktadır.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if($activeTab === 'actions')
                <!-- Action History Panel -->
                <div class="overflow-x-auto rounded-b-[10px]">
                    <table class="min-w-full text-left text-sm text-slate-600">
                        <thead class="bg-slate-50 border-b border-slate-200 text-xs font-medium text-slate-500 uppercase">
                            <tr>
                                <th class="px-4 py-3">Zaman</th>
                                <th class="px-4 py-3">Barkod</th>
                                <th class="px-4 py-3">Tetikleme</th>
                                <th class="px-4 py-3">Eski Fiyat</th>
                                <th class="px-4 py-3">Yeni Fiyat</th>
                                <th class="px-4 py-3">Trendyol Batch ID</th>
                                <th class="px-4 py-3">Doğrulama</th>
                                <th class="px-4 py-3">Durum</th>
                                <th class="px-4 py-3 text-right">Geri Al</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse($recentActions as $act)
                                <tr class="hover:bg-slate-50/50">
                                    <td class="px-4 py-3 text-xs text-slate-500">
                                        {{ $act->created_at->format('d.m.Y H:i:s') }}
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-slate-900">
                                        {{ $act->barcode }}
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        <span class="px-2 py-0.5 rounded font-mono text-[10px] {{ $act->trigger_type === 'automatic' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-800' }}">
                                            {{ strtoupper($act->trigger_type) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        ₺{{ number_format($act->old_price, 2, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-indigo-700">
                                        ₺{{ number_format($act->requested_price, 2, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-xs font-mono text-slate-500">
                                        {{ $act->batch_request_id ?: '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        @switch($act->verification_status)
                                            @case('verified_success')
                                                <span class="text-emerald-600 font-semibold">✓ Fiyat Doğrulandı</span>
                                                @break
                                            @case('verification_failed')
                                                <span class="text-rose-600 font-semibold">✗ Uyuşmazlık Tespit Edildi</span>
                                                @break
                                            @default
                                                <span class="text-slate-400">Bekliyor</span>
                                        @endswitch
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 text-xs font-mono rounded {{ $act->status === 'success' ? 'bg-emerald-100 text-emerald-800' : ($act->status === 'failed' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700') }}">
                                            {{ strtoupper($act->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if($act->canRollback() && $flags['rollback'])
                                            <button wire:click="rollbackAction({{ $act->id }})" class="text-xs text-rose-600 hover:text-rose-800 font-semibold underline">Geri Yükle</button>
                                        @else
                                            <span class="text-xs text-slate-400">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="100%" class="px-4 py-8 text-center text-slate-500">
                                        Aksiyon kaydı bulunamadı.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
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

        <!-- Emergency Stop Modal -->
        @if($showEmergencyStopModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
                <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-4">
                    <div class="text-rose-600 text-xl font-bold flex items-center gap-2">
                        <span>🛑</span> Acil Durdurma Onayı
                    </div>
                    <p class="text-sm text-slate-600">
                        Bu mağaza için tüm otomatik ve manuel fiyat aksiyonlarını anında durdurmak istediğinize emin misiniz? Kuyruktaki tüm bekleyen fiyatlar iptal edilecektir.
                    </p>
                    
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Durdurma Nedeni/Gerekçesi (Zorunlu)</label>
                        <input type="text" wire:model="emergencyStopReason" placeholder="Örn: Trendyol API bağlantı hatası veya yanlış maliyet tespiti" class="w-full rounded-[6px] border border-slate-300 px-3 py-2 text-sm">
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button @click="$wire.showEmergencyStopModal = false" class="rounded-[6px] border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">İptal</button>
                        <button wire:click="triggerStoreEmergencyStop" class="rounded-[6px] bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">🛑 Durdur</button>
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
