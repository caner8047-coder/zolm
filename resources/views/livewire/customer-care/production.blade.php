<div class="space-y-6 p-4 lg:p-6 bg-slate-50/60 min-h-screen">
    <!-- Üst Workspace Kartı -->
    <div class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl lg:text-2xl font-bold text-slate-900">Production Go-Live Center</h1>
                <p class="text-sm text-slate-500">Hazırlık kontrol listesi denetimleri, konfigürasyon kilitleme (freeze), iki aşamalı onay ve acil durum tatbikat merkezi.</p>
            </div>
            <!-- Mağaza Seçimi -->
            <div class="w-full sm:w-64">
                <label for="store-select" class="sr-only">Mağaza Seç</label>
                <select id="store-select" wire:model.live="selectedStoreId" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900">
                    @foreach($accessibleStores as $store)
                        <option value="{{ $store->id }}">{{ $store->store_name }} ({{ strtoupper($store->marketplace) }})</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Sol Panel: Hazırlık Denetimleri (Score & Checklist) -->
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm space-y-4">
                <div class="flex justify-between items-center">
                    <h2 class="text-lg font-bold text-slate-900">Canlı Hazırlık Denetimleri</h2>
                    <button wire:click="checkReadiness" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 text-white text-xs font-semibold rounded-[6px] hover:bg-slate-800 transition">
                        Hazırlık Kontrolünü Çalıştır
                    </button>
                </div>

                @if (session()->has('readiness_success'))
                    <div class="text-sm text-emerald-600 font-medium">{{ session('readiness_success') }}</div>
                @endif

                <div class="space-y-4 max-h-[350px] overflow-y-auto pr-1">
                    @forelse($readinessRuns as $run)
                        <div class="border border-slate-100 rounded-[8px] p-4 flex flex-col sm:flex-row justify-between sm:items-center gap-4">
                            <div>
                                <span class="text-xs text-slate-400">Denetleyen ID: {{ $run->run_by ?? 'Sistem' }} • {{ $run->created_at->format('d.m.Y H:i') }}</span>
                                <div class="text-sm font-semibold text-slate-800">Hazırlık Skoru: {{ $run->readiness_score }}/100</div>
                                @php
                                    $failedReadinessChecks = collect($run->check_results_json ?? [])
                                        ->filter(fn ($check) => ($check['status'] ?? 'failed') === 'failed');
                                @endphp
                                @if($failedReadinessChecks->isNotEmpty())
                                    <div class="mt-2 space-y-1">
                                        @foreach($failedReadinessChecks as $check)
                                            <p class="text-xs text-red-600">
                                                {{ $check['label'] ?? 'Hazırlık kontrolü' }}: {{ $check['detail'] ?? 'Başarısız' }}
                                            </p>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-xs px-2.5 py-1 rounded font-semibold {{ $run->status === 'ready' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                    {{ $run->status === 'ready' ? 'CANLIYA HAZIR' : 'HAZIR DEĞİL' }}
                                </span>
                                @if($run->status === 'ready')
                                    <button wire:click="freezeConfiguration({{ $run->id }})" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold rounded-[6px] transition">
                                        Freeze Snapshot Al
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-xs text-slate-400 py-6 text-center">Henüz hazırlık denetimi çalıştırılmamış.</div>
                    @endforelse
                </div>
            </div>

            <!-- Freeze Snapshot Geçmişi ve Onay -->
            <div class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm space-y-4">
                <h2 class="text-lg font-bold text-slate-900">Dondurulmuş Konfigürasyonlar (Freeze Snapshots)</h2>

                @if (session()->has('freeze_success'))
                    <div class="text-sm text-emerald-600 font-medium mb-2">{{ session('freeze_success') }}</div>
                @endif
                @if (session()->has('approve_success'))
                    <div class="text-sm text-emerald-600 font-medium mb-2">{{ session('approve_success') }}</div>
                @endif
                @if (session()->has('approve_error'))
                    <div class="text-sm text-red-600 font-medium mb-2">{{ session('approve_error') }}</div>
                @endif

                <div class="space-y-4">
                    @forelse($freezeSnapshots as $snap)
                        <div class="border border-slate-100 rounded-[8px] p-4 space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-bold text-slate-800">Snapshot ID: #{{ $snap->id }}</span>
                                <span class="text-[10px] text-slate-400">Tarih: {{ $snap->created_at->format('d.m.Y H:i') }}</span>
                            </div>
                            <div class="text-xs text-slate-600 bg-slate-50 p-2.5 rounded-[6px] font-mono break-all max-h-[100px] overflow-y-auto">
                                {{ $snap->snapshot_data_encrypted }}
                            </div>
                            <div class="flex justify-between items-center pt-2">
                                <span class="text-xs font-medium text-slate-500">
                                    Durum:
                                    @if($snap->approved_at)
                                        <span class="text-emerald-600">Onaylandı (Onaylayan ID: {{ $snap->approved_by }})</span>
                                    @else
                                        <span class="text-amber-600">Onay Bekliyor</span>
                                    @endif
                                </span>
                                @if(!$snap->approved_at)
                                    <button wire:click="approveFreeze({{ $snap->id }})" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold rounded-[6px] transition">
                                        Onayla (İki Aşamalı Governance)
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-xs text-slate-400 py-6 text-center">Kayıtlı dondurulmuş konfigürasyon bulunamadı.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Sağ Panel: Rollback Drill Tatbikat Kontrolü -->
        <div class="space-y-6">
            <div class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm space-y-4">
                <h2 class="text-lg font-bold text-slate-900">Geri Alma Tatbikatı (Rollback Drill)</h2>
                <p class="text-xs text-slate-500">Acil durumlarda otomasyonun nasıl kapatılacağı, askıya alınacak kanallar ve bekleyen mesajların durumu analiz edilir.</p>

                <button wire:click="runRollbackDrill" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 text-white text-xs font-semibold rounded-[6px] hover:bg-slate-800 transition">
                    Geri Alma Tatbikatını Başlat (Dry-run)
                </button>

                @if(!empty($drillResult))
                    <div class="pt-4 border-t border-slate-100 space-y-3">
                        <span class="block text-sm font-semibold text-slate-700">Tatbikat Sonuç Raporu</span>
                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Geri Dönüş Yolu:</span>
                                <span class="font-bold text-slate-800">{{ strtoupper($drillResult['rollback_path']) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Kuyruktaki Mesajlar:</span>
                                <span class="font-bold text-slate-800">{{ $drillResult['pending_dispatches'] }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Devre Kesici (CB):</span>
                                <span class="font-bold {{ $drillResult['automation_circuit_breaker_active'] ? 'text-emerald-600' : 'text-slate-800' }}">
                                    {{ $drillResult['automation_circuit_breaker_active'] ? 'AKTİF' : 'PASİF' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Zaman Damgası:</span>
                                <span class="text-slate-800 font-mono">{{ $drillResult['drill_timestamp'] }}</span>
                            </div>
                        </div>
                        <div class="text-[10px] text-slate-400 bg-slate-50 p-2.5 rounded-[6px] border border-slate-100">
                            Drill dry-run olarak çalıştırılmış olup herhangi bir otomasyon kesintisine sebep olmamıştır.
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
