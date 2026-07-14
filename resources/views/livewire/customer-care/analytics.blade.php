<div class="space-y-4 lg:space-y-6 p-4 lg:p-6 bg-slate-50/50 min-h-screen">
    {{-- Header Section --}}
    <div class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl lg:text-2xl font-semibold text-slate-900 tracking-tight">Müşteri Hizmetleri Operasyon Analitiği</h1>
                <p class="text-sm text-slate-500 mt-1">AI otomasyon performansı, SLA ihlalleri ve temsilci iş yükü analiz paneli.</p>
                @if($metrics ?? null)
                    <p class="text-xs text-slate-400 mt-1">Dönem: {{ \Carbon\Carbon::parse($metrics['period']['start'])->format('d.m.Y') }}–{{ \Carbon\Carbon::parse($metrics['period']['end'])->format('d.m.Y') }} · Güvenilir oran eşiği: n≥{{ $metrics['minimum_rate_sample'] }}</p>
                @endif
            </div>
            <div class="flex flex-col sm:flex-row gap-3">
                {{-- Store Selector --}}
                <div class="w-full sm:w-64">
                    <label for="store_selector" class="sr-only">Mağaza Seçin</label>
                    <select id="store_selector" wire:model.live="selectedStoreId" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-slate-900 focus:border-slate-400 focus:outline-none min-h-[44px]">
                        <option value="">Mağaza Seçiniz</option>
                        @foreach($myStores as $store)
                            <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- Export Button --}}
                @if($selectedStoreId)
                    <button wire:click="exportReport" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 hover:bg-slate-800 text-white font-medium text-sm rounded-[6px] transition-colors min-h-[44px] sm:min-h-[38px] flex items-center justify-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Excel Raporu İndir
                    </button>
                @endif
            </div>
        </div>
    </div>

    @if($metrics)
        {{-- KPI Cards Row --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            {{-- Card 1: Toplam Konuşma --}}
            <div class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm space-y-2">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Toplam Konuşma</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl lg:text-3xl font-bold text-slate-900">{{ $metrics['total_conversations'] }}</span>
                    <span class="px-2 py-0.5 text-xs font-mono rounded bg-slate-100 text-slate-700">Canlı & Arşiv</span>
                </div>
            </div>

            {{-- Card 2: AI Otomasyon Performansı --}}
            <div class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm space-y-2">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">AI Yanıt Dağılımı</span>
                <div class="space-y-1">
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-600">Otomatik Yanıt:</span>
                        <span class="font-semibold text-slate-900">{{ $metrics['ai_auto_count'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-600">Taslak Öneri:</span>
                        <span class="font-semibold text-slate-900">{{ $metrics['ai_draft_count'] }}</span>
                    </div>
                </div>
            </div>

            {{-- Card 3: Temsilci Yanıtları & Handoff --}}
            <div class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm space-y-2">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">İnsan Gücü Devir Oranı</span>
                <div class="flex items-baseline justify-between">
                    @if($metrics['metric_meta']['handoff_rate']['reliable'])
                        <span class="text-2xl lg:text-3xl font-bold text-slate-900">%{{ $metrics['handoff_rate'] }}</span>
                    @else
                        <span class="text-sm font-semibold text-amber-700">Yetersiz örnek (n={{ $metrics['metric_meta']['handoff_rate']['sample_size'] }})</span>
                    @endif
                    <span class="text-sm text-slate-500">Cevap: {{ $metrics['human_reply_count'] }}</span>
                </div>
            </div>

            {{-- Card 4: Çözüm Oranı & Güvenlik --}}
            <div class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm space-y-2">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Kanal Çözüm Oranı</span>
                <div class="flex items-baseline justify-between">
                    @if($metrics['metric_meta']['resolution_rate']['reliable'])
                        <span class="text-2xl lg:text-3xl font-bold text-slate-900">%{{ $metrics['resolution_rate'] }}</span>
                    @else
                        <span class="text-sm font-semibold text-amber-700">Yetersiz örnek (n={{ $metrics['metric_meta']['resolution_rate']['sample_size'] }})</span>
                    @endif
                    <span class="text-xs text-red-600 font-medium">Bloke: {{ $metrics['policy_block_count'] }}</span>
                </div>
            </div>
        </div>

        {{-- SLA & Response Times Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Column 1: SLA Performance Card --}}
            <div class="lg:col-span-1 rounded-[10px] border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                <h2 class="font-semibold text-slate-900 text-base">SLA Durum Performansı</h2>
                <div class="space-y-3">
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4 flex justify-between items-center">
                        <div>
                            <p class="text-xs font-semibold text-slate-500 uppercase">İlk Yanıt SLA İhlali</p>
                            <p class="text-xs text-slate-400 mt-0.5">Süre barajı: 30 dakika</p>
                        </div>
                        <span class="text-xl font-bold {{ $metrics['breached_first_response_count'] > 0 ? 'text-rose-600' : 'text-slate-900' }}">
                            {{ $metrics['breached_first_response_count'] }}
                        </span>
                    </div>

                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4 flex justify-between items-center">
                        <div>
                            <p class="text-xs font-semibold text-slate-500 uppercase">Çözümleme SLA İhlali</p>
                            <p class="text-xs text-slate-400 mt-0.5">Süre barajı: 24 saat</p>
                        </div>
                        <span class="text-xl font-bold {{ $metrics['breached_resolution_count'] > 0 ? 'text-rose-600' : 'text-slate-900' }}">
                            {{ $metrics['breached_resolution_count'] }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Column 2: Ortalama Yanıt & Çözüm Süreleri --}}
            <div class="lg:col-span-1 rounded-[10px] border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                <h2 class="font-semibold text-slate-900 text-base">Ortalama İşlem Süreleri</h2>
                <div class="space-y-4 pt-1">
                    <div class="space-y-1">
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-600">Ortalama İlk Yanıt Süresi:</span>
                            <span class="font-semibold text-slate-900">
                                @if($metrics['avg_first_response_time'] === null)
                                    Ölçüm yok
                                @elseif($metrics['avg_first_response_time'] > 60)
                                    {{ round($metrics['avg_first_response_time'] / 60) }} dk
                                @else
                                    {{ $metrics['avg_first_response_time'] }} sn
                                @endif
                            </span>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-2">
                            <div class="{{ $metrics['avg_first_response_time'] === null ? 'bg-slate-300' : 'bg-slate-900' }} h-2 rounded-full" style="width: {{ $metrics['avg_first_response_time'] === null ? 0 : min(100, max(10, $metrics['avg_first_response_time'] / 18)) }}%"></div>
                        </div>
                        <p class="text-[11px] text-slate-400">Örnek: {{ $metrics['first_response_sample_size'] }}</p>
                    </div>

                    <div class="space-y-1">
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-600">Ortalama Çözüm Süresi:</span>
                            <span class="font-semibold text-slate-900">
                                @if($metrics['avg_resolution_time'] === null)
                                    Ölçüm yok
                                @elseif($metrics['avg_resolution_time'] > 3600)
                                    {{ round($metrics['avg_resolution_time'] / 3600, 1) }} saat
                                @elseif($metrics['avg_resolution_time'] > 60)
                                    {{ round($metrics['avg_resolution_time'] / 60) }} dk
                                @else
                                    {{ $metrics['avg_resolution_time'] }} sn
                                @endif
                            </span>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-2">
                            <div class="{{ $metrics['avg_resolution_time'] === null ? 'bg-slate-300' : 'bg-slate-900' }} h-2 rounded-full" style="width: {{ $metrics['avg_resolution_time'] === null ? 0 : min(100, max(10, $metrics['avg_resolution_time'] / 864)) }}%"></div>
                        </div>
                        <p class="text-[11px] text-slate-400">Örnek: {{ $metrics['resolution_sample_size'] }}</p>
                    </div>
                </div>
            </div>

            {{-- Column 3: Konu / Kategori Başarı Dağılımı --}}
            <div class="lg:col-span-1 rounded-[10px] border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                <h2 class="font-semibold text-slate-900 text-base">Konu Başarı Oranları</h2>
                <div class="space-y-3">
                    @forelse($metrics['topics'] as $key => $topic)
                        <div class="flex items-center justify-between text-sm">
                            <div class="min-w-0">
                                <span class="font-medium text-slate-900 block truncate">{{ $topic['name'] }}</span>
                                <span class="text-xs text-slate-500 font-mono">Çalışma: {{ $topic['total_runs'] }}</span>
                            </div>
                            @if($topic['reliable'])
                                <span class="px-2.5 py-1 text-xs font-bold font-mono rounded bg-slate-100 text-slate-800">%{{ $topic['success_rate'] }}</span>
                            @else
                                <span class="px-2.5 py-1 text-[10px] font-semibold rounded bg-amber-50 text-amber-700">Yetersiz örnek</span>
                            @endif
                        </div>
                    @empty
                        <div class="text-sm text-slate-500 text-center py-4">
                            Henüz yeterli AI çalışma verisi yok
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            <div class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm space-y-3">
                <h2 class="font-semibold text-slate-900">İş Sonucu Kanıtları</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                        <span class="text-xs text-slate-500">Mesai içi ilk yanıt</span>
                        <div class="font-semibold text-slate-900 mt-1">{{ $metrics['avg_first_response_business_hours'] === null ? 'Ölçüm yok' : $metrics['avg_first_response_business_hours'] . ' sn' }}</div>
                        <div class="text-[11px] text-slate-400 mt-1">Örnek: {{ $metrics['first_response_business_hours_sample_size'] }}</div>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                        <span class="text-xs text-slate-500">Mesai dışı ilk yanıt</span>
                        <div class="font-semibold text-slate-900 mt-1">{{ $metrics['avg_first_response_after_hours'] === null ? 'Ölçüm yok' : $metrics['avg_first_response_after_hours'] . ' sn' }}</div>
                        @if($metrics['metric_meta']['after_hours_ai_response_rate']['reliable'])
                            <div class="text-[11px] text-emerald-700 mt-1">AI yanıt oranı %{{ $metrics['after_hours_ai_response_rate'] }} · AI çözüm {{ $metrics['after_hours_ai_resolved_count'] }}</div>
                        @else
                            <div class="text-[11px] text-amber-700 mt-1">Mesai dışı otomasyon iddiası için yetersiz örnek (n={{ $metrics['after_hours_inbound_count'] }})</div>
                        @endif
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                        <span class="text-xs text-slate-500">Doğrulanmış satış katkısı</span>
                        @if($metrics['verified_sales_attribution']['available'])
                            <div class="font-semibold text-slate-900 mt-1">{{ $metrics['verified_sales_attribution']['count'] }} sipariş · {{ $metrics['verified_sales_attribution']['revenue'] }} {{ $metrics['verified_sales_attribution']['currency'] }}</div>
                        @else
                            <div class="text-xs font-semibold text-slate-500 mt-1">Attribution kanıtı yok; satış etkisi gösterilmiyor.</div>
                        @endif
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                        <span class="text-xs text-slate-500">İnsan süresi pilot değişimi</span>
                        @if($metrics['human_time_change']['available'])
                            <div class="font-semibold text-slate-900 mt-1">%{{ $metrics['human_time_change']['change_percent'] }}</div>
                        @else
                            <div class="text-xs font-semibold text-slate-500 mt-1">Onaylı baseline veya yeterli örnek yok.</div>
                        @endif
                    </div>
                </div>
                <div class="text-xs text-slate-500">Çözüm dağılımı: AI {{ $metrics['resolved_by']['ai'] }} · Copilot {{ $metrics['resolved_by']['copilot'] }} · İnsan {{ $metrics['resolved_by']['human'] }}</div>
            </div>

            <div class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm space-y-3">
                <h2 class="font-semibold text-slate-900">Metrik Tanımları ve Örneklem</h2>
                @foreach($metrics['metric_meta'] as $metricKey => $definition)
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3 text-xs">
                        <div class="flex justify-between gap-2"><span class="font-semibold text-slate-800">{{ str_replace('_', ' ', ucfirst($metricKey)) }}</span><span class="font-mono {{ $definition['reliable'] ? 'text-emerald-700' : 'text-amber-700' }}">{{ $definition['reliable'] ? 'GÜVENİLİR' : 'DÜŞÜK ÖRNEKLEM' }}</span></div>
                        <p class="text-slate-500 mt-1">{{ $definition['formula'] }}</p>
                        <p class="text-slate-400 mt-1">Pay {{ $definition['numerator'] }} / Payda {{ $definition['denominator'] }} · Minimum {{ $definition['minimum_sample'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- SLA Breached Conversations Table --}}
        <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-4 py-4 lg:px-6 border-b border-slate-100 bg-white">
                <h2 class="font-semibold text-slate-900 text-base">SLA İhlali Olan Aktif Konuşmalar</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse table-layout: fixed">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-slate-500 text-xs font-semibold uppercase tracking-wider">
                            <th class="px-4 py-3 sm:px-6">Konuşma ID</th>
                            <th class="px-4 py-3 sm:px-6">Kanal</th>
                            <th class="px-4 py-3 sm:px-6">Öncelik</th>
                            <th class="px-4 py-3 sm:px-6">Son Gelen Mesaj</th>
                            <th class="px-4 py-3 sm:px-6 text-right">Aksiyon</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                        @forelse($metrics['breached_conversations'] as $conv)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3 sm:px-6 font-mono font-medium text-slate-900">#{{ $conv->id }}</td>
                                <td class="px-4 py-3 sm:px-6 uppercase font-semibold text-slate-600 text-xs">{{ $conv->channel->key }}</td>
                                <td class="px-4 py-3 sm:px-6">
                                    <span class="px-2 py-0.5 text-xs font-bold font-mono rounded bg-amber-100 text-amber-800">
                                        {{ strtoupper($conv->priority ?? 'medium') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 sm:px-6 text-slate-500">
                                    {{ $conv->last_inbound_at ? $conv->last_inbound_at->diffForHumans() : 'Yok' }}
                                </td>
                                <td class="px-4 py-3 sm:px-6 text-right">
                                    <a href="{{ route('customer-care.inbox', ['selectedConversationId' => $conv->id]) }}" class="px-3 py-1 border border-slate-200 rounded-[6px] hover:bg-slate-50 text-xs font-medium text-slate-700 transition-colors inline-block min-h-[32px] flex items-center justify-center">
                                        Inbox'ta Aç
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 sm:px-6 text-center text-slate-400">
                                    Aktif SLA ihlali olan konuşma bulunmamaktadır. Tebrikler!
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        {{-- Empty State --}}
        <div class="rounded-[10px] border border-slate-200 bg-white p-12 text-center shadow-sm">
            <svg class="w-12 h-12 text-slate-300 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 002 2h2a2 2 0 002-2z" />
            </svg>
            <h3 class="text-base font-semibold text-slate-900">Analiz Yapılacak Mağaza Bulunamadı</h3>
            <p class="text-sm text-slate-500 mt-1">Lütfen analiz verilerini görüntülemek için yukarıdan bir mağaza seçin.</p>
        </div>
    @endif
</div>
