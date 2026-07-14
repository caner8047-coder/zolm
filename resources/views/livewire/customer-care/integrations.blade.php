<div class="space-y-4 lg:space-y-6 p-4 lg:p-6 bg-slate-50/50 min-h-screen">
    <!-- Top Workspace Summary Card -->
    <div class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl lg:text-2xl font-semibold text-slate-900">Enterprise Integration Hub</h1>
                <p class="text-sm text-slate-500 mt-1">CRM, ERP ve BI sistemleriniz ile entegrasyonlar kurun, outbound webhook akışlarını ve teslimat kuyruğunu izleyin.</p>
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

    <!-- Integration Settings Form Card -->
    <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6 space-y-6">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Outbound Webhook Ayarları</h2>
            <p class="text-sm text-slate-500 mt-1">ZOLM olaylarını gerçek zamanlı olarak üçüncü parti sistemlerinize gönderin.</p>
        </div>

        @if($successMessage)
            <div class="p-4 rounded-[8px] bg-emerald-50 border border-emerald-200 text-sm text-emerald-800">
                {{ $successMessage }}
            </div>
        @endif

        @if($errorMessage)
            <div class="p-4 rounded-[8px] bg-red-50 border border-red-200 text-sm text-red-800">
                {{ $errorMessage }}
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-1">
                <label class="text-xs font-bold text-slate-700">Webhook URL</label>
                <input type="url" wire:model.defer="webhookUrl" placeholder="https://api.domain.com/webhooks"
                       class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-base sm:text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-900/10">
            </div>

            <div class="space-y-1">
                <label class="text-xs font-bold text-slate-700">Webhook Secret Key (HMAC İmzalama İçin)</label>
                <div class="flex items-center gap-2">
                    <input type="password" wire:model.defer="webhookSecret" placeholder="••••••••••••••••••••••••"
                           class="flex-1 rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-base sm:text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-900/10">

                    @if($isConfigured)
                        <span class="inline-flex items-center px-2.5 py-1.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                            Anahtar Tanımlı
                        </span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-1.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">
                            Tanımlı Değil
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <button wire:click="saveWebhook" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 text-white rounded-[6px] text-sm font-semibold hover:bg-slate-800 transition">
                Ayarları Kaydet
            </button>
        </div>
    </div>

    <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">CRM / ERP Doğrudan Bağlantı</h2>
                <p class="text-sm text-slate-500 mt-1">HTTPS API, şifreli kimlik bilgisi, sağlık kontrolü ve idempotent aktarım sözleşmesi.</p>
            </div>
            <div class="flex gap-2">
                @foreach(['crm' => 'CRM', 'erp' => 'ERP'] as $providerKey => $providerLabel)
                    @php($connectionState = $externalConnections->get($providerKey))
                    <span class="px-2 py-0.5 text-xs font-mono rounded border {{ $connectionState?->status === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-50 text-slate-600 border-slate-200' }}">
                        {{ $providerLabel }}: {{ strtoupper($connectionState?->status ?? 'YOK') }}
                    </span>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 lg:gap-4">
            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-600">Bağlantı türü</label>
                <select wire:model.live="externalProvider" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2">
                    <option value="crm">CRM</option>
                    <option value="erp">ERP</option>
                </select>
            </div>
            <div class="space-y-1 sm:col-span-1 xl:col-span-2">
                <label class="text-xs font-semibold text-slate-600">API temel adresi</label>
                <input type="url" wire:model.defer="externalBaseUrl" placeholder="https://api.sirketiniz.com" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2">
            </div>
            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-600">Kimlik doğrulama</label>
                <select wire:model.defer="externalAuthType" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2">
                    <option value="bearer">Bearer token</option>
                    <option value="api_key">X-API-Key</option>
                </select>
            </div>
            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-600">Erişim anahtarı</label>
                <input type="password" wire:model.defer="externalAccessToken" placeholder="Kayıtlı anahtarı korumak için boş bırakın" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2">
            </div>
            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-600">Sağlık endpoint’i</label>
                <input type="text" wire:model.defer="externalHealthPath" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2">
            </div>
            <div class="space-y-1 sm:col-span-2 xl:col-span-1">
                <label class="text-xs font-semibold text-slate-600">Kaynak endpoint’i</label>
                <input type="text" wire:model.defer="externalResourcePath" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2">
            </div>
        </div>
        <div class="flex flex-col sm:flex-row justify-end gap-3">
            <button type="button" wire:click="saveExternalConnection" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] border border-slate-200 bg-white text-slate-700 text-sm font-medium">Bağlantıyı kaydet</button>
            <button type="button" wire:click="testExternalConnection" wire:loading.attr="disabled" wire:target="testExternalConnection" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] bg-slate-900 text-white text-sm font-medium disabled:opacity-50">Sağlık testini çalıştır</button>
        </div>
    </div>

    <!-- Webhook Deliveries List -->
    <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-150 bg-slate-50/50 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-900">Webhook Gönderim Günlüğü</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse table-layout: fixed">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200">
                        <th class="p-4 font-semibold">Event ID</th>
                        <th class="p-4 font-semibold">Event Tipi</th>
                        <th class="p-4 font-semibold">Endpoint</th>
                        <th class="p-4 font-semibold">Durum</th>
                        <th class="p-4 font-semibold">Deneme</th>
                        <th class="p-4 font-semibold">Son Deneme</th>
                        <th class="p-4 font-semibold text-right">İşlem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($deliveries as $del)
                        <tr class="hover:bg-slate-50/50">
                            <td class="p-4 font-mono text-xs text-slate-600">{{ $del->event->event_id ?? 'N/A' }}</td>
                            <td class="p-4 font-semibold text-slate-800">{{ $del->event->event_type ?? 'N/A' }}</td>
                            <td class="p-4 text-slate-600 truncate max-w-[200px]" title="{{ $del->webhook_url }}">{{ $del->webhook_url }}</td>
                            <td class="p-4">
                                @switch($del->status)
                                    @case('success')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-50 text-emerald-700">Başarılı</span>
                                        @break
                                    @case('failed')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-50 text-amber-700" title="{{ $del->last_error }}">Hata</span>
                                        @break
                                    @case('dead_letter')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-50 text-red-700 font-mono" title="{{ $del->last_error }}">Dead-Letter</span>
                                        @break
                                    @default
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-50 text-slate-700">Kuyrukta</span>
                                @endswitch
                            </td>
                            <td class="p-4 text-slate-600">{{ $del->attempts }} / 3</td>
                            <td class="p-4 text-slate-500 text-xs">{{ $del->last_attempt_at ? $del->last_attempt_at->diffForHumans() : '-' }}</td>
                            <td class="p-4 text-right">
                                @if($del->status !== 'success')
                                    <button wire:click="retryDelivery({{ $del->id }})" class="w-full sm:w-auto px-4 py-3 sm:py-2 border border-slate-200 bg-white rounded-[6px] text-xs font-medium text-slate-700 hover:bg-slate-50 transition">
                                        Tekrar Dene
                                    </button>
                                @else
                                    <span class="text-slate-400 text-xs">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-sm text-slate-500">
                                Kayıtlı gönderim denemesi bulunmamaktadır.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
