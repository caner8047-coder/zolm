<div class="space-y-4 lg:space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl lg:text-2xl font-semibold text-slate-900 tracking-tight">Kurulum Sihirbazı</h1>
            <p class="text-sm text-slate-500 mt-1">ZOLM platformunu tam verimle kullanabilmeniz için adımları takip edin.</p>
        </div>
        <div class="flex items-center gap-4">
            <div class="text-right">
                <div class="text-sm font-medium text-slate-900">%{{ $onboardingData['readiness_percent'] ?? 0 }} Tamamlandı</div>
                <div class="text-xs text-slate-500">{{ $onboardingData['completed_steps'] ?? 0 }} / {{ $onboardingData['total_steps'] ?? 8 }} Adım</div>
            </div>
            <div class="w-32 h-2.5 bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full bg-slate-900 rounded-full transition-all duration-500" style="width: {{ $onboardingData['readiness_percent'] ?? 0 }}%"></div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 lg:gap-6">
        
        <!-- Sol: Adımlar (Stepper) -->
        <div class="lg:col-span-1">
            <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 sticky top-6">
                <nav class="space-y-2">
                    @foreach($onboardingData['steps'] ?? [] as $index => $step)
                        @php
                            $isActive = $currentStepId === $step['key'];
                            $isCompleted = $step['status'] === 'completed';
                            $isWaiting = $step['status'] === 'waiting';
                        @endphp
                        <button wire:click="selectStep('{{ $step['key'] }}')" 
                                class="w-full text-left px-3 py-3 rounded-[8px] flex items-start gap-3 transition-colors {{ $isActive ? 'bg-slate-50 ring-1 ring-slate-200' : 'hover:bg-slate-50/50' }}">
                            
                            <!-- Icon/Number -->
                            <div class="flex-shrink-0 mt-0.5">
                                @if($isCompleted)
                                    <div class="w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                    </div>
                                @elseif($isWaiting)
                                    <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 font-semibold text-xs border border-slate-200">
                                        {{ $index + 1 }}
                                    </div>
                                @else
                                    <div class="w-6 h-6 rounded-full bg-slate-900 flex items-center justify-center text-white font-semibold text-xs shadow-sm">
                                        {{ $index + 1 }}
                                    </div>
                                @endif
                            </div>
                            
                            <!-- Text -->
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium {{ $isActive ? 'text-slate-900' : ($isWaiting ? 'text-slate-500' : 'text-slate-700') }} truncate">
                                    {{ $step['title'] }}
                                </div>
                                @if($isActive && !empty($step['metric']))
                                    <div class="text-[11px] text-slate-500 mt-1 truncate">{{ $step['metric'] }}</div>
                                @endif
                            </div>
                        </button>
                    @endforeach
                </nav>
            </div>
        </div>

        <!-- Sağ: İçerik -->
        <div class="lg:col-span-3">
            @if($currentStepDetails)
                <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-6 lg:p-10 text-center min-h-[400px] flex flex-col items-center justify-center">
                    
                    @if($currentStepDetails['status'] === 'completed')
                        <div class="w-16 h-16 rounded-full bg-emerald-50 flex items-center justify-center mb-6 ring-8 ring-emerald-50/50">
                            <svg class="w-8 h-8 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <h2 class="text-2xl font-semibold text-slate-900 mb-2">{{ $currentStepDetails['title'] }} Tamamlandı</h2>
                        <p class="text-slate-500 max-w-md mx-auto mb-8">{{ $currentStepDetails['description'] }}</p>
                        
                        <div class="flex gap-3 justify-center">
                            @php
                                $nextStep = collect($onboardingData['steps'])->firstWhere(fn($s) => in_array($s['status'], ['action', 'waiting']));
                            @endphp
                            @if($nextStep)
                                <button wire:click="selectStep('{{ $nextStep['key'] }}')" class="px-6 py-2.5 bg-slate-900 text-white text-sm font-medium rounded-[6px] hover:bg-slate-800 transition-colors shadow-sm">
                                    Sıradaki Adıma Geç
                                </button>
                            @else
                                <a href="{{ route('mp.profit-center') }}" class="px-6 py-2.5 bg-slate-900 text-white text-sm font-medium rounded-[6px] hover:bg-slate-800 transition-colors shadow-sm">
                                    Kâr Merkezine Git
                                </a>
                            @endif
                        </div>

                    @else
                        <!-- Action Required -->
                        <div class="w-16 h-16 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center mb-6 shadow-sm mx-auto">
                            <svg class="w-8 h-8 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <h2 class="text-2xl font-semibold text-slate-900 mb-2">{{ $currentStepDetails['title'] }}</h2>
                        <p class="text-slate-500 max-w-md mx-auto mb-8">{{ $currentStepDetails['description'] }}</p>
                        
                        @if(!empty($currentStepDetails['metric']))
                            <div class="mb-8 inline-flex items-center px-3 py-1 rounded-full bg-slate-100 text-xs font-medium text-slate-600">
                                Mevcut Durum: {{ $currentStepDetails['metric'] }}
                            </div>
                        @endif

                        <div class="flex flex-col sm:flex-row gap-3 justify-center items-center">
                            <a href="{{ $currentStepDetails['action_url'] ?? '#' }}" target="_blank" class="px-6 py-2.5 bg-slate-900 text-white text-sm font-medium rounded-[6px] hover:bg-slate-800 transition-colors shadow-sm inline-flex items-center justify-center gap-2 w-full sm:w-auto">
                                <span>{{ $currentStepDetails['action_label'] ?? 'İşlemi Yap' }}</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                            </a>
                            <button wire:click="refreshState" class="px-6 py-2.5 bg-white border border-slate-200 text-slate-700 text-sm font-medium rounded-[6px] hover:bg-slate-50 transition-colors shadow-sm w-full sm:w-auto">
                                Durumu Yenile
                            </button>
                        </div>
                        
                        <p class="text-xs text-slate-400 mt-6 max-w-sm mx-auto">
                            İşlemi açılacak sayfada tamamladıktan sonra buraya dönüp "Durumu Yenile" butonuna basarak adımın algılanmasını sağlayabilirsiniz.
                        </p>
                    @endif

                </div>
            @else
                <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-6 flex flex-col items-center justify-center min-h-[400px]">
                    <p class="text-slate-500">Lütfen sol taraftan bir adım seçin.</p>
                </div>
            @endif
        </div>

    </div>
</div>
