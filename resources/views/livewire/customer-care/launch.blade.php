<div class="space-y-6 p-4 lg:p-6 bg-slate-50/50 min-h-screen">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-xl lg:text-2xl font-semibold text-slate-900">Production Launch Orchestrator</h1>
            <p class="text-sm text-slate-500">Pilot açılış kararları, canary rollout yönetimi, go/no-go checklist analizi ve acil durum emergency stop paneli.</p>
        </div>
        <div class="w-full sm:w-auto">
            <select wire:model.live="selectedStoreId" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:outline-none">
                @foreach($stores as $st)
                    <option value="{{ $st->id }}">{{ $st->store_name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Feedback Messages --}}
    @if($errorMessage)
        <div class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-[8px] text-sm">
            {{ $errorMessage }}
        </div>
    @endif
    @if($successMessage)
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-[8px] text-sm">
            {{ $successMessage }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Checklist (Left / Top) --}}
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm space-y-4">
                <h2 class="text-lg font-semibold text-slate-900">Go / No-Go Ön Checklist</h2>
                <div class="p-3 rounded-[8px] border {{ $checklist['allowed'] ? 'bg-emerald-50 border-emerald-200 text-emerald-950' : 'bg-red-50/50 border-red-200 text-red-950' }} text-xs font-semibold">
                    Durum: {{ $checklist['allowed'] ? 'AÇILIŞA UYGUN' : 'UYGUN DEĞİL (Fail-Closed)' }}
                </div>
                <div class="space-y-3">
                    @foreach($checklist['checks'] as $key => $check)
                        <div class="p-3 rounded-[8px] border border-slate-100 bg-slate-50/50 space-y-1">
                            <div class="flex justify-between items-center text-xs">
                                <span class="font-semibold text-slate-800">{{ $check['label'] }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase {{ $check['status'] === 'passed' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $check['status'] }}
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-500">{{ $check['detail'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- New Launch Plan Form --}}
            <div class="bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm space-y-4">
                <h2 class="text-lg font-semibold text-slate-900">Yeni Lansman Planı Başlat</h2>
                <form wire:submit.prevent="createPlan" class="space-y-4">
                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-slate-700">Hedef İletişim Kanalları (Virgülle ayırın)</label>
                        <div class="flex gap-2">
                            <label class="flex items-center gap-1.5 text-xs text-slate-700">
                                <input type="checkbox" wire:model="targetChannels" value="whatsapp"> WhatsApp
                            </label>
                            <label class="flex items-center gap-1.5 text-xs text-slate-700">
                                <input type="checkbox" wire:model="targetChannels" value="trendyol"> Trendyol
                            </label>
                            <label class="flex items-center gap-1.5 text-xs text-slate-700">
                                <input type="checkbox" wire:model="targetChannels" value="web_chat"> Web Chat
                            </label>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-slate-700">Başlangıç Modu</label>
                        <select wire:model="initialMode" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-base sm:text-sm">
                            <option value="manual">Manual (Temsilci Tarafından Gönderim)</option>
                            <option value="copilot">Copilot (Temsilci Onaylı AI Taslakları)</option>
                            <option value="automatic">Automatic (Otomatik AI Yanıtları)</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-slate-700">Canary Yüzdesi (%)</label>
                        <input type="number" wire:model="canaryPercentage" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-base sm:text-sm">
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-slate-700">Canary Konuşma Limiti (Opsiyonel)</label>
                        <input type="number" wire:model="conversationLimit" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-base sm:text-sm">
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-slate-700">İzin Verilen Kategoriler (Virgülle ayırın)</label>
                        <input type="text" wire:model="allowedCategoriesRaw" placeholder="iade, kargo" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-base sm:text-sm">
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-slate-700">Rollback Kuralları (Virgülle ayırın)</label>
                        <input type="text" wire:model="rollbackRulesRaw" placeholder="avg_score_below_80" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-base sm:text-sm">
                    </div>

                    <button type="submit" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 hover:bg-slate-800 text-white font-medium rounded-[6px] text-sm transition">
                        Lansman Planı Oluştur
                    </button>
                </form>
            </div>
        </div>

        {{-- Main Area (Plans & Timeline) --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Launch Plans Table --}}
            <div class="bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm space-y-4">
                <h2 class="text-lg font-semibold text-slate-900">Aktif Lansman Planları</h2>
                <div class="overflow-x-auto">
                    <table class="w-full table-layout-fixed text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 text-xs font-semibold text-slate-400">
                                <th class="py-2.5">Plan ID</th>
                                <th>Hedef / Canary</th>
                                <th>Durum</th>
                                <th>Adımlar</th>
                                <th class="text-right">Aksiyonlar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($plans as $plan)
                                <tr class="border-b border-slate-100 text-xs text-slate-700">
                                    <td class="py-3 font-semibold">
                                        #{{ $plan->id }}
                                        <p class="text-[10px] text-slate-400">Başlangıç: {{ $plan->initial_mode }}</p>
                                    </td>
                                    <td>
                                        <p>{{ implode(', ', $plan->target_channels) }}</p>
                                        <p class="text-[10px] text-slate-400">Yüzde: {{ $plan->canary_percentage }}% (Limit: {{ $plan->conversation_limit ?? 'Sınırsız' }})</p>
                                    </td>
                                    <td>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase {{ in_array($plan->status, ['approved', 'completed'], true) ? 'bg-emerald-50 text-emerald-700' : ($plan->status === 'rolled_back' ? 'bg-red-50 text-red-700' : 'bg-slate-100 text-slate-700') }}">
                                            {{ $plan->status }}
                                        </span>
                                        @if($plan->approver)
                                            <p class="text-[9px] text-slate-400">Onay: {{ $plan->approver->name }}</p>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="space-y-1">
                                            @foreach($plan->steps as $step)
                                                <div class="flex items-center gap-1 text-[9px] text-slate-500">
                                                    <span class="w-1.5 h-1.5 rounded-full {{ $step->status === 'completed' ? 'bg-emerald-500' : ($step->status === 'failed' ? 'bg-red-500' : 'bg-slate-300') }}"></span>
                                                    <span>{{ $step->title }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="text-right space-y-1">
                                        @if(in_array($plan->status, ['draft', 'readiness_failed'], true))
                                            <button wire:click="transitionPlan({{ $plan->id }}, 'approved')" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-[6px] text-[10px] transition">
                                                Onaya Gönder (Approve)
                                            </button>
                                        @elseif($plan->status === 'approved')
                                            <button wire:click="transitionPlan({{ $plan->id }}, 'canary')" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-[6px] text-[10px] transition">
                                                Canary Başlat
                                            </button>
                                        @elseif($plan->status === 'canary')
                                            <div class="flex flex-col gap-1 items-end">
                                                <button wire:click="transitionPlan({{ $plan->id }}, 'completed')" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-[6px] text-[10px] transition">
                                                    Full Rollout (Complete)
                                                </button>
                                                <button wire:click="triggerRollback({{ $plan->id }})" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-red-600 hover:bg-red-500 text-white rounded-[6px] text-[10px] transition">
                                                    Emergency Rollback
                                                </button>
                                            </div>
                                        @elseif($plan->status === 'completed')
                                            <button wire:click="triggerRollback({{ $plan->id }})" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-red-600 hover:bg-red-500 text-white rounded-[6px] text-[10px] transition">
                                                Emergency Rollback
                                            </button>
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-4 text-center text-slate-400">Herhangi bir lansman planı bulunamadı.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Event Timeline --}}
            <div class="bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm space-y-4">
                <h2 class="text-lg font-semibold text-slate-900">Lansman Olay Geçmişi</h2>
                <div class="space-y-4 relative border-l border-slate-100 pl-4 ml-2">
                    @forelse($events as $event)
                        <div class="relative space-y-1">
                            <span class="absolute -left-[21px] top-1.5 w-2 h-2 rounded-full {{ $event->event_type === 'rollback_triggered' ? 'bg-red-500' : 'bg-slate-400' }}"></span>
                            <div class="flex justify-between text-xs">
                                <span class="font-semibold text-slate-800">{{ $event->event_type }}</span>
                                <span class="text-slate-400">{{ $event->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-[11px] text-slate-500 font-mono">{{ json_encode($event->details_json) }}</p>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400">Henüz herhangi bir lansman olayı kaydedilmedi.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
