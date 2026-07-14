<div class="space-y-6 p-4 lg:p-6 bg-slate-50/50 min-h-screen">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-xl lg:text-2xl font-semibold text-slate-900">Kurumsal API Yönetimi</h1>
            <p class="text-sm text-slate-500">Scope-based token üretimi, Client yönetimi ve entegrasyon denetim logları.</p>
        </div>
        <div class="w-full sm:w-auto">
            <select wire:model.live="selectedOrgId" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:outline-none">
                @foreach($organizations as $org)
                    <option value="{{ $org->id }}">{{ $org->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if($errorMessage)
        <div class="bg-red-50 border border-red-200 rounded-[8px] p-3 text-sm text-red-700">{{ $errorMessage }}</div>
    @endif
    @if($successMessage)
        <div class="bg-emerald-50 border border-emerald-200 rounded-[8px] p-3 text-sm text-emerald-700">{{ $successMessage }}</div>
    @endif

    {{-- Yeni Üretilen Token --}}
    @if($generatedPlainToken)
        <div class="bg-amber-50 border border-amber-200 rounded-[10px] p-4 lg:p-6">
            <h2 class="text-sm font-semibold text-amber-800 mb-2">⚠ Token Sadece Bir Kez Gösterilir!</h2>
            <p class="text-xs text-amber-700 mb-3">Lütfen bu tokenı güvenli bir yere kopyalayın. Kaybolursa yeniden oluşturulması gerekir.</p>
            <div class="flex items-center gap-3">
                <input type="text" readonly value="{{ $generatedPlainToken }}" id="input-plain-token"
                    class="flex-1 rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-base sm:text-sm font-mono focus:outline-none" />
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Token Oluşturma Formu --}}
        <div class="bg-white rounded-[10px] border border-slate-200 shadow-sm p-4 lg:p-6 lg:col-span-1">
            <h2 class="text-base font-semibold text-slate-900 mb-4">Yeni Client ve Token</h2>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Client Adı</label>
                    <input wire:model="newClientName" type="text" id="input-client-name" placeholder="Örn: ERP Entegrasyonu"
                        class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:outline-none" />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Token Prefix</label>
                    <input wire:model="newPrefix" type="text" id="input-token-prefix" placeholder="Örn: erp"
                        class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:outline-none" />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Scopes</label>
                    <div class="space-y-1.5">
                        <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                            <input type="checkbox" wire:model="newScopes" value="conversations:read" class="rounded border-slate-300">
                            conversations:read
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                            <input type="checkbox" wire:model="newScopes" value="messages:read" class="rounded border-slate-300">
                            messages:read
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                            <input type="checkbox" wire:model="newScopes" value="replies:create" class="rounded border-slate-300">
                            replies:create
                        </label>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Erişilebilir Mağazalar</label>
                    <div class="space-y-1.5 max-h-32 overflow-y-auto border border-slate-200 rounded-[6px] p-2">
                        @foreach($stores as $store)
                            <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                                <input type="checkbox" wire:model="newStoreIds" value="{{ $store->id }}" class="rounded border-slate-300">
                                {{ $store->store_name }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <button wire:click="createClientAndToken" id="btn-create-token"
                    class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 text-white text-sm rounded-[6px] hover:bg-slate-700 transition">
                    Client & Token Üret
                </button>
            </div>
        </div>

        {{-- Token Listesi ve Loglar --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-[10px] border border-slate-200 shadow-sm p-4 lg:p-6">
                <h2 class="text-base font-semibold text-slate-900 mb-4">Aktif Erişim Tokenları</h2>
                @if($tokens->isEmpty())
                    <p class="text-sm text-slate-400 text-center py-8">Tanımlı token bulunmuyor.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm border-collapse" style="table-layout:fixed">
                            <thead>
                                <tr class="border-b border-slate-200">
                                    <th class="py-2 px-3 text-left text-xs font-medium text-slate-500">Client</th>
                                    <th class="py-2 px-3 text-left text-xs font-medium text-slate-500">Prefix</th>
                                    <th class="py-2 px-3 text-left text-xs font-medium text-slate-500">Scopes</th>
                                    <th class="py-2 px-3 text-right text-xs font-medium text-slate-500">İşlem</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($tokens as $tok)
                                    <tr>
                                        <td class="py-2 px-3 text-slate-800 overflow-hidden text-ellipsis">{{ $tok->apiClient->name ?? '' }}</td>
                                        <td class="py-2 px-3 font-mono text-xs text-slate-600">{{ $tok->token_prefix }}</td>
                                        <td class="py-2 px-3 overflow-hidden text-ellipsis text-xs text-slate-500">{{ implode(', ', $tok->scopes ?? []) }}</td>
                                        <td class="py-2 px-3 text-right">
                                            @if(!$tok->revoked_at)
                                                <button wire:click="revokeToken({{ $tok->id }})"
                                                    id="btn-revoke-token-{{ $tok->id }}"
                                                    class="w-full sm:w-auto px-4 py-3 sm:py-2 text-xs bg-red-50 text-red-700 border border-red-200 rounded-[6px] hover:bg-red-100 transition">
                                                    Revoke
                                                </button>
                                            @else
                                                <span class="text-xs text-slate-400">İPTAL EDİLDİ</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="bg-white rounded-[10px] border border-slate-200 shadow-sm p-4 lg:p-6">
                <h2 class="text-base font-semibold text-slate-900 mb-4">Son API Erişim Logları</h2>
                @if($logs->isEmpty())
                    <p class="text-sm text-slate-400 text-center py-4">Henüz API erişimi gerçekleşmemiş.</p>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach($logs as $log)
                            <div class="py-2 flex items-center justify-between text-xs">
                                <div>
                                    <span class="font-bold text-slate-700 mr-2">{{ $log->method }}</span>
                                    <span class="font-mono text-slate-500">{{ $log->uri }}</span>
                                    <span class="text-slate-400 ml-2">({{ $log->ip_address }})</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="px-1.5 py-0.5 rounded font-mono
                                        {{ $log->response_status >= 400 ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800' }}">
                                        {{ $log->response_status }}
                                    </span>
                                    <span class="text-slate-400">{{ $log->created_at->diffForHumans() }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
