<div class="space-y-4 lg:space-y-6 p-4 lg:p-6 bg-slate-50/50 min-h-screen">
    <!-- Top Workspace Summary Card -->
    <div class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl lg:text-2xl font-semibold text-slate-900">Yapay Zeka Operasyon Paneli (Model Ops)</h1>
                <p class="text-sm text-slate-500 mt-1">Canlı AI operasyon durumunu, API sağlık kontrollerini, maliyetleri ve gecikme metriklerini izleyin.</p>
            </div>
            <div class="w-full sm:w-auto">
                <select wire:model.live="selectedStoreId" class="w-full sm:w-auto rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}">{{ $store->store_name }} ({{ strtoupper($store->marketplace) }})</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Health & Status KPI Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
        <!-- Gemini Provider Health -->
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Gemini API Durumu</h3>
            <div class="flex items-center gap-2 mt-2">
                @if($providerHealth['Gemini'] === 'Healthy')
                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                    <span class="text-lg font-semibold text-slate-900">Aktif</span>
                @else
                    <span class="h-2.5 w-2.5 rounded-full bg-red-500"></span>
                    <span class="text-lg font-semibold text-slate-900">API Anahtarı Eksik</span>
                @endif
            </div>
        </div>

        <!-- Groq Provider Health -->
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Groq API Durumu</h3>
            <div class="flex items-center gap-2 mt-2">
                @if($providerHealth['Groq'] === 'Healthy')
                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                    <span class="text-lg font-semibold text-slate-900">Aktif</span>
                @else
                    <span class="h-2.5 w-2.5 rounded-full bg-slate-400"></span>
                    <span class="text-lg font-semibold text-slate-900">Yapılandırılmamış</span>
                @endif
            </div>
        </div>

        <!-- Circuit Breaker Status -->
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Acil Durum Freni (Circuit Breaker)</h3>
            <div class="flex items-center gap-2 mt-2">
                @if($circuitBreakerStatus === 'open')
                    <span class="h-2.5 w-2.5 rounded-full bg-red-500"></span>
                    <span class="text-lg font-semibold text-red-700">AÇIK (Tetiklendi)</span>
                @elseif($circuitBreakerStatus === 'closed')
                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                    <span class="text-lg font-semibold text-emerald-700">KAPALI (İzleme Aktif)</span>
                @else
                    <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                    <span class="text-lg font-semibold text-amber-700">İZLEME KAPALI</span>
                @endif
            </div>
        </div>

        <!-- Dead Letter Queue Status -->
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Dead-Letter Kuyruğu (DLQ)</h3>
            <div class="flex items-center gap-2 mt-2">
                @if($deadLetterCount > 0)
                    <span class="h-2.5 w-2.5 rounded-full bg-red-500"></span>
                    <span class="text-lg font-semibold text-red-700">{{ $deadLetterCount }} Başarısız</span>
                @else
                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                    <span class="text-lg font-semibold text-slate-900">Temiz</span>
                @endif
            </div>
        </div>
    </div>

    <!-- More Detailed Metrics Card Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
        <!-- Costs Section -->
        <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6 space-y-4">
            <h2 class="text-base font-semibold text-slate-900">Tahmini AI Model Harcamaları</h2>
            <p class="text-sm text-slate-500 mt-1">İlişkili token kullanımına göre hesaplanan toplam harcama miktarı.</p>

            <div class="p-6 rounded-[8px] border border-slate-100 bg-slate-50/60 flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-500 uppercase tracking-wider">Toplam AI Maliyeti</span>
                    <div class="text-2xl font-bold text-slate-900 mt-1">
                        @if($hasCostData)
                            @if($knownCostsCount === 0)
                                <span class="text-slate-500 font-normal">Hesaplanamadı / Bilinmiyor</span>
                            @else
                                ${{ number_format($totalCost, 4) }}
                            @endif
                            @if($unknownCostsCount > 0)
                                <div class="text-xs text-amber-600 mt-1 font-medium">
                                    ⚠️ {{ $unknownCostsCount }} adet çalıştırmanın maliyeti hesaplanamadı (bilinmeyen model/hata).
                                </div>
                            @endif
                        @else
                            <span class="text-slate-400 font-normal">Bilinmiyor (Veri Yok)</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Latency Percentiles and Reliability Section -->
        <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6 space-y-4">
            <h2 class="text-base font-semibold text-slate-900">Güvenilirlik ve Yanıt Gecikmesi</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="p-4 rounded-[8px] border border-slate-100 bg-slate-50/40">
                    <span class="text-xs text-slate-500 uppercase">Dispatch Hata Oranı</span>
                    <div class="text-xl font-bold text-slate-900 mt-1">{{ $dispatchFailureRate === null ? 'Ölçüm yok' : '%' . $dispatchFailureRate }}</div>
                    <div class="text-[11px] text-slate-400 mt-1">Örnek: {{ $totalDispatches }}</div>
                </div>

                <div class="p-4 rounded-[8px] border border-slate-100 bg-slate-50/40">
                    <span class="text-xs text-slate-500 uppercase">AI Run Hata Oranı</span>
                    <div class="text-xl font-bold text-slate-900 mt-1">{{ $aiRunFailureRate === null ? 'Ölçüm yok' : '%' . $aiRunFailureRate }}</div>
                    <div class="text-[11px] text-slate-400 mt-1">Örnek: {{ $totalRunsCount }}</div>
                </div>

                <div class="p-4 rounded-[8px] border border-slate-100 bg-slate-50/40">
                    <span class="text-xs text-slate-500 uppercase">P50 Yanıt Süresi</span>
                    <div class="text-xl font-bold text-slate-900 mt-1">{{ $p50 === null ? 'Ölçüm yok' : $p50 . ' ms' }}</div>
                </div>

                <div class="p-4 rounded-[8px] border border-slate-100 bg-slate-50/40">
                    <span class="text-xs text-slate-500 uppercase">P95 Yanıt Süresi</span>
                    <div class="text-xl font-bold text-slate-900 mt-1">{{ $p95 === null ? 'Ölçüm yok' : $p95 . ' ms' }}</div>
                    <div class="text-[11px] text-slate-400 mt-1">Gecikme örneği: {{ $latencySampleCount }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Policy Block Trend & Incidents Summary -->
    <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6 space-y-4">
        <h2 class="text-base font-semibold text-slate-900">Güvenlik ve Politika Engelleri</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="p-4 rounded-[8px] border border-slate-100 bg-slate-50/40 flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-500 uppercase">Toplam Filtrelenen / Engellenen Mesaj</span>
                    <div class="text-xl font-bold text-slate-900 mt-1">{{ $policyBlockCount }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
