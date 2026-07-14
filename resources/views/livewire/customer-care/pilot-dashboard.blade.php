<div class="flex flex-col space-y-4 lg:space-y-6 p-4 lg:p-6 bg-slate-50/40 min-h-screen">
    <!-- Workspace / Özet Kartı -->
    <div class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
            <div>
                <h1 class="text-xl lg:text-2xl font-semibold text-slate-900">ZOLM AI İletişim Pilot İzleme Paneli</h1>
                <p class="text-sm text-slate-500">Copilot AI kararlarını, shadow mode doğruluğunu ve golden dataset durumunu izleyin.</p>
            </div>
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full sm:w-auto">
                @if(isset($myStores) && count($myStores) > 0)
                    <select wire:model.live="selectedStoreId" class="px-3 py-2 text-base sm:text-sm font-medium bg-white border border-slate-200 rounded-[6px] min-h-[44px]">
                        @foreach($myStores as $store)
                            <option value="{{ $store->id }}">{{ $store->store_name }} ({{ strtoupper($store->marketplace) }})</option>
                        @endforeach
                    </select>
                @endif
                <button wire:click="runGoldenEval" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-base sm:text-sm font-medium bg-slate-900 text-white rounded-[6px] hover:bg-slate-800 transition duration-150 shadow-sm min-h-[44px]">
                    Golden Dataset Değerlendir
                </button>
            </div>
        </div>
    </div>

    <!-- Canary & Circuit Breaker Otomasyon Güvenlik Monitörü -->
    @if($selectedStoreId && isset($metrics))
        <div class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-lg font-medium text-slate-900">Canary İzleme & Devre Kesici (Circuit Breaker)</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Otomasyon hata sınırlarını ve anlık çalışma durumunu denetleyin.</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-1 rounded text-xs font-semibold
                               {{ $metrics['circuit_breaker_status'] === 'open' ? 'bg-red-100 text-red-800' : ($metrics['circuit_breaker_status'] === 'closed' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800') }}">
                        <span class="mr-1.5 h-2 w-2 rounded-full {{ $metrics['circuit_breaker_status'] === 'open' ? 'bg-red-500 animate-pulse' : ($metrics['circuit_breaker_status'] === 'closed' ? 'bg-emerald-500' : 'bg-amber-500') }}"></span>
                        Devre: {{ $metrics['circuit_breaker_status'] === 'open' ? 'AÇIK (Otomasyon Bloke)' : ($metrics['circuit_breaker_status'] === 'closed' ? 'KAPALI (İzleme Aktif)' : 'İZLEME KAPALI') }}
                    </span>
                    <button wire:click="toggleCircuitBreaker" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-xs font-semibold rounded-[6px] border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 min-h-[36px]">
                        {{ $metrics['manual_override'] ? 'Kilidi Kaldır (Reset)' : 'Manuel Otomasyonu Durdur (Trip)' }}
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                    <span class="text-xs text-slate-400 font-mono">SON 15 DK HATA</span>
                    <div class="text-lg font-bold text-slate-900 mt-1">
                        {{ $metrics['dispatch_failures_15m'] }} / {{ $metrics['max_dispatch_failures_15m'] }}
                    </div>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                    <span class="text-xs text-slate-400 font-mono">SON 15 DK POLİTİKA ENGELİ</span>
                    <div class="text-lg font-bold text-slate-900 mt-1">
                        {{ $metrics['policy_blocks_15m'] }} / {{ $metrics['max_policy_blocks_15m'] }}
                    </div>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                    <span class="text-xs text-slate-400 font-mono">SON 1 SAAT OTOMATİK CEVAP</span>
                    <div class="text-lg font-bold text-slate-900 mt-1">
                        {{ $metrics['auto_reply_count_1h'] }} / {{ $metrics['auto_reply_max_per_hour'] }}
                    </div>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                    <span class="text-xs text-slate-400 font-mono">KUYRUK BACKLOG (OUTBOX)</span>
                    <div class="text-lg font-bold text-slate-900 mt-1">
                        {{ $metrics['outbox_backlog'] }}
                    </div>
                </div>
            </div>

            @if($metrics['trip_reason'])
                <div class="mt-3 p-2.5 rounded bg-red-50 border border-red-100 text-xs text-red-800 font-mono">
                    <strong>Neden:</strong> {{ $metrics['trip_reason'] }}
                </div>
            @endif
        </div>
    @endif

    <!-- Pilot Readiness Hazırlık Kontrol Paneli -->
    @if($selectedStoreId && isset($readiness))
        <div class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-medium text-slate-900">Pilot Hazırlık Analizi (Readiness Status)</h2>
                @if($readiness['ready'])
                    <span class="px-2.5 py-1 text-xs font-semibold rounded bg-emerald-100 text-emerald-800">Canlı Pilota Hazır</span>
                @else
                    <span class="px-2.5 py-1 text-xs font-semibold rounded bg-rose-100 text-rose-800">Hazır Değil</span>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 lg:gap-4">
                @foreach($readiness['checks'] as $key => $check)
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4 flex flex-col justify-between">
                        <div>
                            <span class="text-xs text-slate-400 font-mono">[{{ strtoupper($key) }}]</span>
                            <h3 class="text-sm font-semibold text-slate-800 mt-1">{{ $check['label'] }}</h3>
                        </div>
                        <div class="flex justify-between items-center mt-3">
                            <span class="text-xs font-medium text-slate-600">{{ $check['detail'] }}</span>
                            <span class="px-2 py-0.5 text-[10px] font-mono rounded font-semibold @if($check['status'] === 'passed') bg-emerald-100 text-emerald-800 @elseif($check['status'] === 'warning') bg-amber-100 text-amber-800 @else bg-rose-100 text-rose-800 @endif">
                                {{ strtoupper($check['status']) }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>

            @if(!empty($readiness['latest_errors']))
                <div class="mt-4 p-3 rounded bg-rose-50 border border-rose-100 text-xs text-rose-800">
                    <h4 class="font-bold mb-1">Son Outbox Gönderim Hataları:</h4>
                    <ul class="list-disc pl-4 space-y-1">
                        @foreach($readiness['latest_errors'] as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    <!-- Son Politika Engelleme Kayıtları -->
    @if(isset($policyBlocks) && count($policyBlocks) > 0)
        <div class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
            <h2 class="text-lg font-medium text-slate-900 mb-4">Son Politika İhlali Engelleme Kayıtları (Policy Blocks)</h2>
            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="w-full text-left border-collapse table-layout-fixed">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-xs font-medium text-slate-500">
                            <th class="p-3 w-1/4">Tarih</th>
                            <th class="p-3 w-1/4">Kullanıcı</th>
                            <th class="p-3 w-1/6">Konuşma</th>
                            <th class="p-3 w-1/3">Engelleme Nedeni</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-slate-100">
                        @foreach($policyBlocks as $block)
                            <tr class="hover:bg-slate-50/50">
                                <td class="p-3 text-slate-500 font-mono text-xs">{{ $block->created_at->format('Y-m-d H:i:s') }}</td>
                                <td class="p-3 text-slate-950 font-semibold">{{ $block->user->name ?? 'Sistem / AI' }}</td>
                                <td class="p-3 text-indigo-600 font-mono text-xs">Conv #{{ $block->conversation_id }}</td>
                                <td class="p-3 text-rose-700 text-xs italic">{{ $block->details_json['reason'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Golden Eval Sonuç Metrikleri -->
    @if($avgEvalScore > 0)
        <div class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
            <h2 class="text-lg font-medium text-slate-900 mb-4">Golden Dataset Değerlendirme Çıktısı</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 lg:gap-4 mb-4">
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                    <span class="text-xs text-slate-500 uppercase tracking-wider">Durum</span>
                    <div class="text-lg font-semibold mt-1 @if($avgEvalScore >= 80) text-emerald-600 @else text-rose-600 @endif">
                        {{ $evalStatus }}
                    </div>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                    <span class="text-xs text-slate-500 uppercase tracking-wider">Ortalama Başarı Skoru</span>
                    <div class="text-2xl font-bold mt-1 text-slate-900">{{ $avgEvalScore }} / 100</div>
                </div>
            </div>

            <!-- Detay Listesi -->
            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="w-full text-left border-collapse table-layout-fixed">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-xs font-medium text-slate-500">
                            <th class="p-3 w-1/4">Kategori</th>
                            <th class="p-3 w-1/3">Soru</th>
                            <th class="p-3 w-1/3">AI Taslak / Yanıt</th>
                            <th class="p-3 w-20 text-right">Skor</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-slate-100">
                        @foreach($evalDetails as $detail)
                            <tr class="hover:bg-slate-50/50">
                                <td class="p-3 font-mono text-xs text-slate-700">{{ $detail['category'] }}</td>
                                <td class="p-3 text-slate-900">{{ $detail['question'] }}</td>
                                <td class="p-3 text-slate-500 truncate">{{ $this->maskPii($detail['response'] ?? 'HATA') }}</td>
                                <td class="p-3 text-right font-semibold @if($detail['score'] >= 80) text-emerald-600 @else text-rose-600 @endif">
                                    {{ $detail['score'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 lg:gap-6">
        <!-- AI Taslakları -->
        <div class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
            <h2 class="text-lg font-medium text-slate-900 mb-4">Aktif AI Taslakları (Copilot Mode)</h2>
            <div class="space-y-3">
                @forelse($activeDrafts as $draft)
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/30 p-4">
                        <div class="flex justify-between items-start gap-2 mb-2">
                            <span class="px-2 py-0.5 text-xs font-mono rounded bg-slate-100 text-slate-700">
                                Conv #{{ $draft->conversation_id }}
                            </span>
                            <span class="text-xs text-slate-400">Taslak</span>
                        </div>
                        <p class="text-sm text-slate-800 italic">"{{ $this->maskPii($draft->body_encrypted) }}"</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500 text-center py-4">Bekleyen AI taslağı bulunmuyor.</p>
                @endforelse
            </div>
        </div>

        <!-- AI Runs Ledger -->
        <div class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
            <h2 class="text-lg font-medium text-slate-900 mb-4">Son AI Karar Defteri (Runs Ledger)</h2>
            <div class="space-y-3">
                @forelse($aiRuns as $run)
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/30 p-4">
                        <div class="flex justify-between items-start gap-2 mb-2">
                            <span class="px-2 py-0.5 text-xs font-mono rounded @if($run->status === 'draft') bg-indigo-100 text-indigo-700 @elseif($run->status === 'handoff') bg-amber-100 text-amber-700 @else bg-slate-100 text-slate-700 @endif">
                                {{ strtoupper($run->status) }}
                            </span>
                            <span class="text-xs font-mono text-slate-400">{{ $run->latency_ms }} ms</span>
                        </div>
                        <div class="text-xs text-slate-500 mb-1">Prompt: <span class="text-slate-700 font-medium">{{ $this->maskPii($run->prompt_raw) }}</span></div>
                        <div class="text-xs text-slate-500 mb-2">Yanıt: <span class="text-slate-700 italic">"{{ $this->maskPii($run->response_raw ?? 'N/A') }}"</span></div>
                        <div class="flex justify-between items-center text-[10px] text-slate-400 font-mono">
                            <div>Güven Skoru: {{ $run->confidence_score }}</div>
                            @if($run->shadow_match_score !== null)
                                <div class="text-indigo-600 font-semibold">Shadow Benzerlik: {{ $run->shadow_match_score }}%</div>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500 text-center py-4">AI Karar logu bulunmuyor.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
