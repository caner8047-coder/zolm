<div class="space-y-6 p-4 lg:p-6 bg-slate-50/50 min-h-screen">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-xl lg:text-2xl font-semibold text-slate-900">Production Reliability Center</h1>
            <p class="text-sm text-slate-500">Kuyruk durumları, backpressure limit kontrolleri, dead-letter replay ve kanal rate limit göstergeleri.</p>
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

    {{-- Backpressure Status Banner --}}
    <div class="p-4 rounded-[10px] border {{ ($backpressure['status'] ?? '') === 'backpressure' ? 'bg-amber-50 border-amber-200 text-amber-900' : (($backpressure['status'] ?? '') === 'unknown' ? 'bg-slate-50 border-slate-200 text-slate-800' : 'bg-emerald-50 border-emerald-200 text-emerald-900') }} shadow-sm">
        <div class="flex items-center gap-3">
            <span class="w-2.5 h-2.5 rounded-full {{ ($backpressure['status'] ?? '') === 'backpressure' ? 'bg-amber-500 animate-pulse' : (($backpressure['status'] ?? '') === 'unknown' ? 'bg-slate-400' : 'bg-emerald-500') }}"></span>
            <div>
                <h3 class="font-semibold text-sm">
                    Backpressure Durumu:
                    @if(($backpressure['status'] ?? '') === 'backpressure')
                        AKTİF (Aşırı Yük)
                    @elseif(($backpressure['status'] ?? '') === 'unknown')
                        Bilinmiyor (Veri Yok)
                    @else
                        Normal
                    @endif
                </h3>
                <p class="text-xs {{ ($backpressure['status'] ?? '') === 'backpressure' ? 'text-amber-700' : (($backpressure['status'] ?? '') === 'unknown' ? 'text-slate-500' : 'text-emerald-700') }} mt-0.5">
                    {{ $backpressure['reason'] ?? 'Kuyruklar normal durumda.' }}
                </p>
            </div>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div class="bg-white p-4 rounded-[10px] border border-slate-200 shadow-sm">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Bekleyen Gönderimler</span>
            <div class="text-2xl font-bold text-slate-900 mt-1">{{ $pendingDispatches }}</div>
            <p class="text-xs text-slate-400 mt-1">Outbox kuyruğunda bekleyen mesajlar.</p>
        </div>
        <div class="bg-white p-4 rounded-[10px] border border-slate-200 shadow-sm">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Terminal Başarısız (Exhausted)</span>
            <div class="text-2xl font-bold text-slate-900 mt-1">{{ $exhaustedDispatches }}</div>
            <p class="text-xs text-slate-400 mt-1">Deneme sınırı aşılmış terminal kayıtlar.</p>
        </div>
        <div class="bg-white p-4 rounded-[10px] border border-slate-200 shadow-sm">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Dead-Letter Webhook</span>
            <div class="text-2xl font-bold text-slate-900 mt-1">{{ $deadLetters }}</div>
            <p class="text-xs text-slate-400 mt-1">İletilemeyen dış webhook bildirimleri.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Replay Actions (Left) --}}
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm space-y-4">
                <h2 class="text-lg font-semibold text-slate-900">Operasyonel Kurtarma (Replay)</h2>
                <div class="space-y-4">
                    <div class="p-4 rounded-[8px] border border-slate-100 bg-slate-50/50 space-y-3">
                        <h3 class="text-sm font-semibold text-slate-900">Exhausted Dispatches Replay</h3>
                        <p class="text-xs text-slate-500">Maksimum deneme sınırına ulaştığı için bekletilen tüm giden mesajları sıfırlayıp tekrar gönderir.</p>
                        <button wire:click="replayAllExhausted" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 hover:bg-slate-800 text-white font-medium rounded-[6px] text-xs transition">
                            Gönderimleri Yeniden Dene
                        </button>
                    </div>

                    <div class="p-4 rounded-[8px] border border-slate-100 bg-slate-50/50 space-y-3">
                        <h3 class="text-sm font-semibold text-slate-900">Dead-Letter Webhook Replay</h3>
                        <p class="text-xs text-slate-500">Üçüncü taraf sistemlere gönderilemeyen ve DLQ kuyruğuna düşen tüm webhook'ları yeniden imzalar ve iletir.</p>
                        <button wire:click="replayAllWebhooks" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 hover:bg-slate-800 text-white font-medium rounded-[6px] text-xs transition">
                            Webhook'ları Yeniden Gönder
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Rate Limits (Right) --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm space-y-4">
                <h2 class="text-lg font-semibold text-slate-900">Kanal Rate Limit Doluluk Durumları</h2>
                <div class="space-y-4">
                    @foreach($channelUsage as $chan => $usage)
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="font-medium text-slate-700 uppercase">{{ $chan }}</span>
                                <span class="text-slate-500">{{ $usage['sent'] }} / {{ $usage['max'] }} gönderim</span>
                            </div>
                            <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-500 {{ $usage['percentage'] > 80 ? 'bg-red-500' : ($usage['percentage'] > 50 ? 'bg-amber-500' : 'bg-slate-900') }}"
                                     style="width: {{ $usage['percentage'] }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
