<div class="space-y-6 p-4 lg:p-6 max-w-[1600px] mx-auto">
    {{-- Header / Workspace Info --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-[10px] border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-xl lg:text-2xl font-semibold text-slate-900">Müşteri Hizmetleri Kurulum Sihirbazı</h1>
            <p class="text-sm text-slate-500 mt-1">ZOLM AI Müşteri İletişim Merkezini adım adım güvenle yapılandırın.</p>
        </div>

        {{-- Store Selector --}}
        <div class="flex flex-col sm:flex-row sm:items-center gap-2">
            <span class="text-xs font-semibold uppercase text-slate-400 font-mono">Yapılandırılan Mağaza:</span>
            <select wire:model.live="selectedStoreId" class="w-full sm:w-auto rounded-[6px] border border-slate-200 bg-white text-base sm:text-sm px-3 py-3 sm:py-2 text-slate-700 focus:border-slate-400 focus:outline-none sm:min-w-[200px]" id="onboarding-store-selector">
                @foreach($myStores as $store)
                    <option value="{{ $store->id }}">{{ $store->store_name }} ({{ strtoupper($store->marketplace) }})</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Feedback Messages --}}
    @if ($successMessage)
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-[8px] text-sm flex items-center gap-2" id="onboarding-success-msg">
            <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ $successMessage }}</span>
        </div>
    @endif

    @if ($errorMessage)
        <div class="p-4 bg-red-50 border border-red-200 text-red-800 rounded-[8px] text-sm flex items-center gap-2" id="onboarding-error-msg">
            <svg class="w-5 h-5 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ $errorMessage }}</span>
        </div>
    @endif

    <div class="flex flex-col lg:flex-row gap-6">
        {{-- Left: Stepper Menu --}}
        <div class="w-full lg:w-1/4 bg-white rounded-[10px] border border-slate-200 p-5 shadow-sm space-y-3">
            <span class="text-xs font-semibold uppercase text-slate-400 font-mono tracking-wider block mb-4">KURULUM ADIMLARI</span>

            @php
                $steps = [
                    1 => 'Mağaza Seçimi',
                    2 => 'Kanal Bağlantıları',
                    3 => 'Marka Sesi ve Üslup',
                    4 => 'Bilgi Merkezi Kontrolü',
                    5 => 'Pilot Güvenlik Kriterleri',
                    6 => 'Otomasyon Aktivasyonu'
                ];
            @endphp

            @foreach($steps as $stepNum => $stepLabel)
                <div class="flex items-center gap-3 p-3 rounded-[6px] transition-colors {{ $currentStep === $stepNum ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-50' }}">
                    <div class="w-6 h-6 rounded-full flex items-center justify-center font-semibold text-xs font-mono border
                        {{ $currentStep === $stepNum ? 'border-white text-white' : (in_array($stepNum, $stepsCompleted) ? 'border-emerald-500 bg-emerald-50 text-emerald-600' : 'border-slate-300 text-slate-400') }}">
                        @if(in_array($stepNum, $stepsCompleted) && $currentStep !== $stepNum)
                            ✓
                        @else
                            {{ $stepNum }}
                        @endif
                    </div>
                    <span class="text-sm font-medium">{{ $stepLabel }}</span>
                </div>
            @endforeach
        </div>

        {{-- Right: Current Step Working Surface --}}
        <div class="flex-1 bg-white rounded-[10px] border border-slate-200 p-6 shadow-sm min-w-0">
            {{-- Step 1: Mağaza Seçimi --}}
            @if($currentStep === 1)
                <div class="space-y-4" id="step-panel-1">
                    <h2 class="text-lg font-semibold text-slate-900">Adım 1: Mağaza Doğrulaması</h2>
                    <p class="text-sm text-slate-500">Müşteri İletişim Merkezini kurmak istediğiniz mağazanın aktifliğini kontrol edin.</p>

                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4 space-y-2">
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-slate-500">Seçilen Mağaza:</span>
                            <span class="font-semibold text-slate-900">{{ $myStores->firstWhere('id', $selectedStoreId)?->store_name }}</span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-slate-500">Pazaryeri / Altyapı:</span>
                            <span class="font-semibold text-slate-900 font-mono">{{ strtoupper($myStores->firstWhere('id', $selectedStoreId)?->marketplace) }}</span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-slate-500">Durum:</span>
                            <span class="px-2 py-0.5 text-xs font-mono rounded bg-emerald-50 text-emerald-700 font-semibold border border-emerald-200">AKTİF</span>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Step 2: Kanal Bağlantıları --}}
            @if($currentStep === 2)
                <div class="space-y-4" id="step-panel-2">
                    <h2 class="text-lg font-semibold text-slate-900">Adım 2: İletişim Kanalları Bağlantı Durumu</h2>
                    <p class="text-sm text-slate-500">Mağazanıza tanımlanmış entegre kanalları kontrol edin. Otomasyon için en az 1 aktif kanal gereklidir.</p>

                    @if($channels->isEmpty())
                        <div class="p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-[8px] text-sm">
                            Bu mağaza için tanımlanmış aktif bir destek kanalı (Trendyol, WhatsApp, Google GBP vb.) bulunamadı. Lütfen entegrasyonlar sayfasından bir kanal bağlayın.
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach($channels as $channel)
                                <div class="p-4 border border-slate-200 rounded-[8px] flex items-center justify-between">
                                    <div>
                                        <h3 class="font-semibold text-slate-900 text-sm">{{ $channel->name }}</h3>
                                        <p class="text-xs text-slate-500 mt-0.5">Anahtar: <span class="font-mono">{{ $channel->key }}</span></p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="px-2 py-0.5 text-xs font-mono rounded font-semibold border
                                            {{ $channel->is_enabled ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200' }}">
                                            {{ $channel->is_enabled ? 'AKTİF' : 'PASİF' }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4 space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold text-slate-900">Canlı kurulum doğrulaması</h3>
                                <p class="text-xs text-slate-500 mt-1">Kanal sağlığını, AI taslak yeteneğini ve ilk güvenli taslağı birlikte test eder.</p>
                            </div>
                            @if($onboardingState?->first_verified_draft_at)
                                <span class="shrink-0 px-2 py-0.5 text-xs font-mono rounded bg-emerald-50 text-emerald-700 border border-emerald-200">DOĞRULANDI</span>
                            @else
                                <span class="shrink-0 px-2 py-0.5 text-xs font-mono rounded bg-amber-50 text-amber-700 border border-amber-200">BEKLİYOR</span>
                            @endif
                        </div>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <input type="text" wire:model="sampleQuestion" maxlength="500" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-slate-800 focus:border-slate-400 focus:outline-none" placeholder="Örnek müşteri sorusu">
                            <button type="button" wire:click="verifySetup" wire:loading.attr="disabled" wire:target="verifySetup" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] bg-slate-900 text-white text-sm font-medium disabled:opacity-50">
                                <span wire:loading.remove wire:target="verifySetup">Bağlantıyı ve taslağı doğrula</span>
                                <span wire:loading wire:target="verifySetup">Doğrulanıyor…</span>
                            </button>
                        </div>
                        @if($onboardingState?->first_verified_draft_at)
                            <p class="text-xs text-emerald-700">İlk doğrulanmış taslak: {{ $onboardingState->first_verified_draft_at->format('d.m.Y H:i') }} · Süre: {{ $onboardingState->verification_duration_seconds ?? 0 }} sn · Güven: %{{ $onboardingState->sample_result_json['confidence'] ?? 0 }}</p>
                        @endif
                        @if($onboardingState?->catalog_dry_run_json)
                            <div class="rounded-[6px] border p-3 {{ $onboardingState->catalog_verified_at ? 'border-emerald-200 bg-emerald-50/60' : 'border-amber-200 bg-amber-50/60' }}">
                                <p class="text-xs font-semibold {{ $onboardingState->catalog_verified_at ? 'text-emerald-800' : 'text-amber-800' }}">Katalog / sipariş / geçmiş soru dry-run</p>
                                <p class="text-xs text-slate-600 mt-1">{{ $onboardingState->catalog_dry_run_json['message'] ?? '' }}</p>
                                <p class="text-[11px] text-slate-500 mt-1">Güncel ürün: {{ $onboardingState->catalog_dry_run_json['counts']['fresh_sellable_listings'] ?? 0 }} · Sipariş: {{ $onboardingState->catalog_dry_run_json['counts']['orders_scanned'] ?? 0 }} · Soru: {{ $onboardingState->catalog_dry_run_json['counts']['historical_questions_scanned'] ?? 0 }}</p>
                            </div>
                        @endif
                        @if($onboardingState?->support_bundle_json && !$onboardingState?->catalog_verified_at)
                            <button type="button" wire:click="requestTechnicalSupport" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] border border-slate-300 bg-white text-slate-700 text-sm font-medium">
                                Tanılama paketiyle destek iste
                            </button>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Step 3: Marka Sesi ve Üslup --}}
            @if($currentStep === 3)
                <div class="space-y-4" id="step-panel-3">
                    <h2 class="text-lg font-semibold text-slate-900">Adım 3: Marka Sesi ve Üslup Özelleştirme</h2>
                    <p class="text-sm text-slate-500">AI asistanının yanıt yazarken uyması gereken kuralları, hitap tarzını ve marka dilini özelleştirin.</p>

                    @if($channels->isEmpty())
                        <div class="p-4 bg-red-50 border border-red-200 text-red-800 rounded-[8px] text-sm">
                            Marka sesini özelleştirmek için en az bir kanal oluşturulmuş olmalıdır.
                        </div>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-xs font-semibold text-slate-600 block">Yazışma Tonu</label>
                                <input type="text" wire:model="tone" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-slate-800 focus:border-slate-400 focus:outline-none" placeholder="Örn: kibar ve yardımsever">
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-semibold text-slate-600 block">Hitap Tarzı</label>
                                <select wire:model="hitap" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-slate-800 focus:border-slate-400 focus:outline-none">
                                    <option value="siz">Siz (Kurumsal / Resmi)</option>
                                    <option value="sen">Sen (Samimi / Rahat)</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-semibold text-slate-600 block">Selamlama</label>
                                <input type="text" wire:model="greeting" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-slate-800 focus:border-slate-400 focus:outline-none">
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-semibold text-slate-600 block">İmza / Kapanış</label>
                                <input type="text" wire:model="signature" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-slate-800 focus:border-slate-400 focus:outline-none">
                            </div>
                            <div class="sm:col-span-2 space-y-1">
                                <label class="text-xs font-semibold text-slate-600 block">Sistem Rol Tanımı ve Kurallar</label>
                                <textarea wire:model="prompt_context" rows="3" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-slate-800 focus:border-slate-400 focus:outline-none"></textarea>
                            </div>
                            <div class="sm:col-span-2 space-y-1">
                                <label class="text-xs font-semibold text-slate-600 block">İade/Kargo Politikası Notları</label>
                                <textarea wire:model="return_policy" rows="2" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-slate-800 focus:border-slate-400 focus:outline-none"></textarea>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Step 4: Bilgi Merkezi Kontrolü --}}
            @if($currentStep === 4)
                <div class="space-y-4" id="step-panel-4">
                    <h2 class="text-lg font-semibold text-slate-900">Adım 4: Bilgi Merkezi Başlangıç Kontrolü</h2>
                    <p class="text-sm text-slate-500">AI asistanının cevap taslakları hazırlarken besleneceği soru/cevap veritabanının durumunu analiz edin.</p>

                    <div class="p-4 bg-slate-50 border border-slate-200 rounded-[8px] space-y-2 text-sm text-slate-600">
                        <p>ZOLM Bilgi Bankası, geçmiş konuşmaları otomatik tarayarak soru-cevap önerileri üretir.</p>
                        <p class="font-semibold text-slate-800 mt-2">Öneri Durumu:</p>
                        <ul class="list-disc pl-5 space-y-1 text-xs">
                            <li>Analiz kuyruğu çalışır durumda.</li>
                            <li>Temsilci onayına sunulacak taslak makaleler taranıyor.</li>
                        </ul>
                    </div>
                </div>
            @endif

            {{-- Step 5: Pilot Güvenlik Checklist --}}
            @if($currentStep === 5)
                <div class="space-y-4" id="step-panel-5">
                    <h2 class="text-lg font-semibold text-slate-900">Adım 5: Pilot Güvenlik ve Hazırlık Analizi (Readiness Checklist)</h2>
                    <p class="text-sm text-slate-500">Otomatik yanıt modunun (Automatic Mode) aktif edilebilmesi için gereken zorunlu pilot güvenlik kapılarını inceleyin.</p>

                    <div class="space-y-2">
                        @foreach($readiness['checks'] ?? [] as $key => $check)
                            <div class="p-3 border border-slate-200 rounded-[6px] flex items-center justify-between text-sm">
                                <div>
                                    <span class="font-semibold text-slate-800 block">{{ $check['label'] }}</span>
                                    <span class="text-xs text-slate-500">{{ $check['detail'] }}</span>
                                </div>
                                <div>
                                    @if($check['status'] === 'passed')
                                        <span class="px-2 py-0.5 text-xs font-mono rounded bg-emerald-50 text-emerald-700 font-semibold border border-emerald-200">BAŞARILI</span>
                                    @elseif($check['status'] === 'warning')
                                        <span class="px-2 py-0.5 text-xs font-mono rounded bg-amber-50 text-amber-700 font-semibold border border-amber-200">UYARI</span>
                                    @else
                                        <span class="px-2 py-0.5 text-xs font-mono rounded bg-red-50 text-red-700 font-semibold border border-red-200">KRİTİK HATA</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if(!$readiness['ready'])
                        <div class="p-3 bg-red-50 border border-red-200 text-red-800 text-xs rounded-[6px]">
                            <strong>Uyarı:</strong> Pilot güvenlik kapılarında kritik hata(lar) bulunmaktadır. Otomatik Yanıt (Automatic) modu bu mağaza için kilitli kalacaktır.
                        </div>
                    @else
                        <div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-[6px]">
                            <strong>Tebrikler:</strong> Tüm pilot güvenlik kriterleri başarıyla geçilmiştir! Otomatik yanıt özelliğini güvenle açabilirsiniz.
                        </div>
                    @endif
                </div>
            @endif

            {{-- Step 6: Otomasyon Aktivasyonu --}}
            @if($currentStep === 6)
                <div class="space-y-4" id="step-panel-6">
                    <h2 class="text-lg font-semibold text-slate-900">Adım 6: Önerilen Otomasyon Çalışma Modu</h2>
                    <p class="text-sm text-slate-500">Mağazanızın pilot readiness durumuna göre en uygun çalışma modunu seçin ve kurulumu tamamlayın.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        {{-- Mode: Manual --}}
                        <label class="p-4 border rounded-[8px] flex flex-col justify-between cursor-pointer transition-shadow hover:shadow-md
                            {{ $recommendedMode === 'manual' ? 'border-slate-900 bg-slate-50/50' : 'border-slate-200 bg-white' }}">
                            <div>
                                <input type="radio" wire:model="recommendedMode" value="manual" class="sr-only">
                                <h3 class="font-semibold text-slate-900 text-sm">Manuel</h3>
                                <p class="text-xs text-slate-500 mt-2">Yanıtlar tamamen temsilciler tarafından incelenerek gönderilir. AI yalnızca asistanlık yapar.</p>
                            </div>
                            <span class="text-xs font-bold text-slate-500 mt-4 block">Her Zaman İzinli</span>
                        </label>

                        {{-- Mode: Copilot --}}
                        <label class="p-4 border rounded-[8px] flex flex-col justify-between cursor-pointer transition-shadow hover:shadow-md
                            {{ $recommendedMode === 'copilot' ? 'border-slate-900 bg-slate-50/50' : 'border-slate-200 bg-white' }}">
                            <div>
                                <input type="radio" wire:model="recommendedMode" value="copilot" class="sr-only">
                                <h3 class="font-semibold text-slate-900 text-sm">Copilot (Taslak Öneri)</h3>
                                <p class="text-xs text-slate-500 mt-2">AI gelen sorulara yanıt taslağı hazırlar, temsilci onaylarsa gönderilir. (Önerilen Başlangıç)</p>
                            </div>
                            <span class="text-xs font-bold text-slate-500 mt-4 block">Her Zaman İzinli</span>
                        </label>

                        {{-- Mode: Automatic --}}
                        <label class="p-4 border rounded-[8px] flex flex-col justify-between cursor-pointer transition-shadow hover:shadow-md
                            {{ (!$readiness['ready'] || !$onboardingState?->first_verified_draft_at) ? 'opacity-50 cursor-not-allowed bg-slate-100/50 border-slate-200' : ($recommendedMode === 'automatic' ? 'border-slate-900 bg-slate-50/50' : 'border-slate-200 bg-white') }}">
                            <div>
                                <input type="radio" wire:model="recommendedMode" value="automatic" {{ (!$readiness['ready'] || !$onboardingState?->first_verified_draft_at) ? 'disabled' : '' }} class="sr-only">
                                <h3 class="font-semibold text-slate-900 text-sm">Otomatik (Automatic)</h3>
                                <p class="text-xs text-slate-500 mt-2">AI, yüksek güven skoru alan mesajlara doğrudan otomatik yanıt gönderir.</p>
                            </div>
                            @if(!$readiness['ready'] || !$onboardingState?->first_verified_draft_at)
                                <span class="text-xs font-bold text-red-600 mt-4 block">GÜVENLİK ENGELİ (KİLİTLİ)</span>
                            @else
                                <span class="text-xs font-bold text-emerald-600 mt-4 block">AKTİF EDİLEBİLİR</span>
                            @endif
                        </label>
                    </div>

                    @if($status === 'completed')
                        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-[8px] text-sm text-center">
                            Kurulum başarıyla tamamlandı. Otomasyon şu an <strong>{{ strtoupper($recommendedMode) }}</strong> modunda çalışıyor.
                        </div>
                    @endif
                </div>
            @endif

            {{-- Stepper Footer Control Buttons --}}
            <div class="mt-8 pt-4 border-t border-slate-100 flex items-center justify-between">
                <button type="button" wire:click="prevStep" {{ $currentStep === 1 ? 'disabled' : '' }}
                    class="w-full sm:w-auto px-4 py-3 sm:py-2 border border-slate-200 text-slate-700 rounded-[6px] text-sm font-medium hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">
                    Geri
                </button>

                @if($currentStep < 6)
                    <button type="button" wire:click="nextStep" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 text-white rounded-[6px] text-sm font-medium hover:bg-slate-800">
                        İleri
                    </button>
                @else
                    <button type="button" wire:click="completeOnboarding" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 text-white rounded-[6px] text-sm font-medium hover:bg-slate-800">
                        Sihirbazı Tamamla
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
