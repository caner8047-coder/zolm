<div class="space-y-6 p-4 lg:p-6 bg-slate-50/50 min-h-screen">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-xl lg:text-2xl font-semibold text-slate-900">Güvenlik Merkezi</h1>
            <p class="text-sm text-slate-500">Teknik güvenlik denetimleri, bulgular ve PII-redacted kanıt paketi üretimi.</p>
        </div>
        <div class="flex items-center gap-3">
            <select wire:model.live="selectedStoreId" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:outline-none">
                @foreach($stores as $st)
                    <option value="{{ $st->id }}">{{ $st->store_name }}</option>
                @endforeach
            </select>
            <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
                <input type="checkbox" wire:model.live="isDryRun" id="chk-dry-run" class="rounded border-slate-300">
                Dry-Run
            </label>
            <button wire:click="runAudit" id="btn-run-audit"
                class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 text-white text-sm rounded-[6px] hover:bg-slate-700 transition">
                Denetim Başlat
            </button>
        </div>
    </div>

    @if($errorMessage)
        <div class="bg-red-50 border border-red-200 rounded-[8px] p-3 text-sm text-red-700">{{ $errorMessage }}</div>
    @endif
    @if($successMessage)
        <div class="bg-emerald-50 border border-emerald-200 rounded-[8px] p-3 text-sm text-emerald-700">{{ $successMessage }}</div>
    @endif

    {{-- Denetim Geçmişi --}}
    <div class="bg-white rounded-[10px] border border-slate-200 shadow-sm p-4 lg:p-6">
        <h2 class="text-base font-semibold text-slate-900 mb-4">Son Denetimler</h2>
        @if($runs->isEmpty())
            <div class="text-sm text-slate-400 py-8 text-center">Henüz denetim çalıştırılmamış.</div>
        @else
            @foreach($runs as $run)
                <div class="mb-4 border border-slate-200 rounded-[8px] p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-semibold text-slate-800">Denetim #{{ $run->id }}</span>
                            @if($run->overall_severity)
                                <span class="px-2 py-0.5 text-xs font-mono rounded
                                    {{ $run->overall_severity === 'critical' ? 'bg-red-100 text-red-800' :
                                       ($run->overall_severity === 'high' ? 'bg-orange-100 text-orange-800' :
                                       ($run->overall_severity === 'clean' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600')) }}">
                                    {{ strtoupper($run->overall_severity) }}
                                </span>
                            @endif
                            @if($run->is_dry_run)
                                <span class="px-2 py-0.5 text-xs font-mono rounded bg-blue-100 text-blue-700">DRY-RUN</span>
                            @endif
                        </div>
                        <span class="text-xs text-slate-400">{{ $run->completed_at?->diffForHumans() }}</span>
                    </div>

                    @if($run->findings->isNotEmpty())
                        <div class="divide-y divide-slate-100">
                            @foreach($run->findings as $finding)
                                <div class="py-2">
                                    <div class="flex items-center gap-2">
                                        <span class="px-1.5 py-0.5 text-xs rounded font-mono
                                            {{ $finding->severity === 'critical' ? 'bg-red-100 text-red-800' :
                                               ($finding->severity === 'high' ? 'bg-orange-100 text-orange-800' :
                                               ($finding->severity === 'medium' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600')) }}">
                                            {{ strtoupper($finding->severity) }}
                                        </span>
                                        <span class="text-sm text-slate-700">{{ $finding->title }}</span>
                                    </div>
                                    @if($finding->description)
                                        <p class="text-xs text-slate-500 mt-1 ml-1">{{ $finding->description }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-slate-400">Bulgu yok.</p>
                    @endif
                </div>
            @endforeach
        @endif
    </div>

    {{-- Kanıt Paketi --}}
    <div class="bg-white rounded-[10px] border border-slate-200 shadow-sm p-4 lg:p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-slate-900">Kanıt Paketi</h2>
            <button wire:click="generateEvidencePack" id="btn-generate-evidence"
                class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm bg-slate-900 text-white rounded-[6px] hover:bg-slate-700 transition">
                Oluştur
            </button>
        </div>
        @if($evidencePack)
            <pre id="evidence-pack-content" class="text-xs bg-slate-50 p-4 rounded-[6px] border border-slate-200 overflow-x-auto whitespace-pre-wrap">{{ $evidencePack }}</pre>
        @else
            <div class="text-sm text-slate-400 py-4 text-center">Kanıt paketi oluşturmak için yukarıdaki butona tıklayın.</div>
        @endif
    </div>
</div>
