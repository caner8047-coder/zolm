<div class="space-y-6 p-4 lg:p-6 bg-slate-50/60 min-h-screen">
    <!-- Üst Workspace Kartı -->
    <div class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl lg:text-2xl font-bold text-slate-900">Connector Certification & Sandbox</h1>
                <p class="text-sm text-slate-500">Entegrasyon kanallarının ve webhook'ların canlıya alınmadan önce uyumluluk, yetki ve güvenlik denetim merkezi.</p>
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

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Sol Panel: Kanallar ve Sertifikasyon Geçmişi -->
        <div class="space-y-6">
            <!-- Aktif Kanallar Matrisi -->
            <div class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm space-y-4">
                <h2 class="text-lg font-bold text-slate-900">Kanal Sertifikasyon Matrisi</h2>
                <div class="divide-y divide-slate-100">
                    @forelse($channels as $channel)
                        <div class="py-3 flex justify-between items-center gap-4">
                            <div>
                                <span class="text-sm font-semibold text-slate-900">{{ $channel->name }}</span>
                                <div class="text-xs text-slate-500">Kanal Anahtarı: {{ $channel->key }} | Durum: {{ strtoupper($channel->status) }}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button wire:click="runCertification('{{ $channel->key }}')" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 text-white text-xs font-semibold rounded-[6px] hover:bg-slate-800 transition">
                                    Denetle
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="text-xs text-slate-400 py-4 text-center">Tanımlı kanal bulunamadı.</div>
                    @endforelse
                </div>
                @if (session()->has('cert_success'))
                    <div class="text-sm text-emerald-600 font-medium">{{ session('cert_success') }}</div>
                @endif
            </div>

            <!-- Son Sertifikasyon Sonuçları -->
            <div class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm space-y-4">
                <h2 class="text-lg font-bold text-slate-900">Sertifikasyon Denetim Geçmişi</h2>
                <div class="space-y-4 max-h-[300px] overflow-y-auto pr-1">
                    @forelse($certificationRuns as $run)
                        <div class="border border-slate-100 rounded-[8px] p-3 space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-semibold text-slate-800">Kanal: {{ strtoupper($run->channel_key) }}</span>
                                <span class="text-[10px] text-slate-400">{{ $run->certified_at->format('d.m.Y H:i') }}</span>
                                <span class="text-xs px-2 py-0.5 rounded font-medium {{ $run->status === 'pass' ? 'bg-emerald-50 text-emerald-700' : ($run->status === 'warn' ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-700') }}">
                                    {{ strtoupper($run->status) }}
                                </span>
                            </div>
                            <div class="space-y-1 pl-2 border-l border-slate-200">
                                @foreach($run->checks as $check)
                                    <div class="text-xs flex justify-between gap-4">
                                        <span class="text-slate-600 font-mono">{{ $check->check_name }}:</span>
                                        <span class="font-medium {{ $check->status === 'pass' ? 'text-emerald-600' : ($check->status === 'warn' ? 'text-amber-600' : 'text-red-600') }}">
                                            {{ strtoupper($check->status) }}
                                        </span>
                                    </div>
                                    <div class="text-[10px] text-slate-400 pl-2">{{ $check->details }}</div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="text-xs text-slate-400 py-4 text-center">Henüz sertifikasyon denetimi çalıştırılmamış.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Sağ Panel: Sandbox Olay Simülatörü -->
        <div class="space-y-6">
            <div class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm space-y-4">
                <h2 class="text-lg font-bold text-slate-900">Sandbox Olay Simülatörü (Webhook Tester)</h2>

                <div class="space-y-4">
                    <div>
                        <label for="channel-select" class="block text-sm font-semibold text-slate-700 mb-1">Simüle Edilecek Kanal</label>
                        <select id="channel-select" wire:model.live="selectedChannelKey" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm text-slate-900 focus:outline-none">
                            <option value="web_chat">Web Chat (HMAC Signature Required)</option>
                            <option value="whatsapp">WhatsApp Inbound Webhook</option>
                            <option value="trendyol">Trendyol Customer Question Inbound</option>
                        </select>
                    </div>

                    <div>
                        <label for="payload-json" class="block text-sm font-semibold text-slate-700 mb-1">Simülasyon Payload'u (JSON Formatında)</label>
                        <textarea id="payload-json" wire:model="fixturePayloadJson" rows="8" placeholder='{"raw_json": "...", "signature": "...", "store_id": 1}' class="w-full rounded-[6px] border border-slate-200 p-3 text-base sm:text-sm font-mono text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900"></textarea>
                    </div>

                    <button wire:click="runSandboxSimulation" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 text-white text-xs font-semibold rounded-[6px] hover:bg-slate-800 transition">
                        Webhook Olayını Simüle Et (Sandboxed)
                    </button>
                </div>

                @if($simulationResult)
                    <div class="pt-4 border-t border-slate-100 space-y-2">
                        <span class="block text-sm font-semibold text-slate-700">Simülasyon Çıktısı</span>
                        <pre class="p-3 bg-slate-50 border border-slate-200 rounded-[8px] text-xs font-mono overflow-x-auto max-h-[250px] {{ $simulationSuccess ? 'text-slate-800' : 'text-red-700' }}">{{ $simulationResult }}</pre>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
