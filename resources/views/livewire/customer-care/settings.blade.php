<div class="space-y-6 p-4 lg:p-6 max-w-[1600px] mx-auto">
    {{-- Header / Workspace info --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-[10px] border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-xl lg:text-2xl font-semibold text-slate-900">Müşteri Hizmetleri Ayarları</h1>
            <p class="text-sm text-slate-500 mt-1">Marka sesi, otomasyon seviyesi ve pilot aktivasyon adımlarını yönetin.</p>
        </div>

        <div class="flex flex-col sm:flex-row gap-3">
            {{-- Store Select --}}
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold uppercase text-slate-400 font-mono">Mağaza:</span>
                <select wire:model.live="selectedStoreId" class="w-full sm:w-auto rounded-[6px] border border-slate-200 bg-white text-base sm:text-sm px-3 py-3 sm:py-2 text-slate-700 focus:border-slate-400 focus:outline-none sm:min-w-[200px]">
                    @foreach($myStores as $store)
                        <option value="{{ $store->id }}">{{ $store->store_name }} ({{ strtoupper($store->marketplace) }})</option>
                    @endforeach
                </select>
            </div>

            {{-- Channel Select --}}
            @if(count($channels) > 0)
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold uppercase text-slate-400 font-mono">Kanal:</span>
                    <select wire:model.live="selectedChannelId" class="w-full sm:w-auto rounded-[6px] border border-slate-200 bg-white text-base sm:text-sm px-3 py-3 sm:py-2 text-slate-700 focus:border-slate-400 focus:outline-none sm:min-w-[180px]">
                        @foreach($channels as $chan)
                            <option value="{{ $chan->id }}">{{ $chan->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>
    </div>

    {{-- Feedback messages --}}
    @if ($successMessage)
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-[8px] text-sm flex items-center gap-2">
            <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ $successMessage }}</span>
        </div>
    @endif

    @if ($errorMessage)
        <div class="p-4 bg-red-50 border border-red-200 text-red-800 rounded-[8px] text-sm flex items-center gap-2">
            <svg class="w-5 h-5 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ $errorMessage }}</span>
        </div>
    @endif

    @if(!$selectedChannelId)
        <div class="bg-white rounded-[10px] border border-slate-200 shadow-sm p-8">
            <div class="max-w-md mx-auto text-center space-y-4">
                <div class="w-14 h-14 mx-auto rounded-full bg-slate-100 flex items-center justify-center">
                    <svg class="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                              d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                    </svg>
                </div>

                @if($availableToProvision->isNotEmpty())
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Kanal Oluşturmaya Hazır</h3>
                        <p class="text-xs text-slate-500 mt-1">
                            Bu mağaza için aşağıdaki entegrasyon kanalları oluşturulabilir.
                            Oluşturulan kanallar varsayılan olarak <strong>kapalı</strong> ve <strong>manuel modda</strong> başlatılır.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2 justify-center">
                        @foreach($availableToProvision as $ch)
                            <span class="inline-flex items-center gap-1 px-3 py-1 bg-slate-100 text-slate-700 text-xs font-medium rounded-full">
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                {{ $ch['name'] }}
                            </span>
                        @endforeach
                    </div>

                    <button
                        wire:click="provisionChannels"
                        wire:loading.attr="disabled"
                        wire:loading.class="w-full sm:w-auto px-4 py-3 sm:py-2 opacity-60 cursor-not-allowed"
                        id="provision-channels-btn"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium rounded-[6px] transition-colors">
                        <span wire:loading.remove wire:target="provisionChannels">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                        </span>
                        <span wire:loading wire:target="provisionChannels">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                        </span>
                        <span wire:loading.remove wire:target="provisionChannels">Kanalı Oluştur</span>
                        <span wire:loading wire:target="provisionChannels">Oluşturuluyor…</span>
                    </button>
                @else
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Aktif Kanal Bulunamadı</h3>
                        <p class="text-xs text-slate-500 mt-1">
                            Bu mağaza için provizyon edilebilecek entegrasyon bulunamadı.
                            Önce Entegrasyonlar ekranından bir bağlantı kurduğunuzdan emin olun.
                        </p>
                    </div>
                    <a href="{{ route('customer-care.integrations') }}"
                       class="inline-flex items-center gap-2 px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 text-sm font-medium rounded-[6px] transition-colors">
                        Entegrasyonlara Git
                    </a>
                @endif
            </div>
        </div>

    @else
        {{-- Layout: 2 Columns on desktop, Stack on mobile --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

            {{-- Column 1 & 2: Settings Form --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Form Section: Brand Voice Settings --}}
                <div class="bg-white rounded-[10px] border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50">
                        <h2 class="font-semibold text-slate-900 text-base">Marka Sesi Özelleştirme</h2>
                        <p class="text-xs text-slate-500 mt-0.5">AI asistanının müşterilere yanıt verirken benimseyeceği üslup, selamlama ve imza ayarları.</p>
                    </div>

                    <div class="p-5 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            {{-- Tone --}}
                            <div class="space-y-1">
                                <div class="flex items-center gap-1.5">
                                    <label class="text-xs font-semibold text-slate-600">Yazışma Tonu</label>
                                    <div class="group relative inline-block cursor-help">
                                        <svg class="w-3.5 h-3.5 text-slate-400 hover:text-slate-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <div class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 w-64 -translate-x-1/2 rounded-[6px] bg-slate-900 p-2.5 text-xs text-white opacity-0 transition-opacity group-hover:opacity-100 shadow-lg text-left leading-normal font-normal">
                                            AI asistanının konuşurken benimseyeceği üsluptur. Örneğin: 'Kibar ve profesyonel' veya 'Samimi ve enerjik'.
                                        </div>
                                    </div>
                                </div>
                                <input type="text" wire:model="tone" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-slate-800 focus:border-slate-400 focus:outline-none" placeholder="Örn: kibar ve yardımsever">
                                <span class="text-[11px] text-slate-400">Üslup ve tonlama sıfatları. (max 100 karakter)</span>
                            </div>

                            {{-- Hitap --}}
                            <div class="space-y-1">
                                <div class="flex items-center gap-1.5">
                                    <label class="text-xs font-semibold text-slate-600">Hitap Tarzı</label>
                                    <div class="group relative inline-block cursor-help">
                                        <svg class="w-3.5 h-3.5 text-slate-400 hover:text-slate-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <div class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 w-64 -translate-x-1/2 rounded-[6px] bg-slate-900 p-2.5 text-xs text-white opacity-0 transition-opacity group-hover:opacity-100 shadow-lg text-left leading-normal font-normal">
                                            Müşteriye hitap yöntemi. 'Siz' seçeneği kurumsal ve resmi, 'Sen' seçeneği ise daha samimi markalar için uygundur.
                                        </div>
                                    </div>
                                </div>
                                <select wire:model="hitap" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-slate-800 focus:border-slate-400 focus:outline-none">
                                    <option value="siz">Siz (Kurumsal / Resmi)</option>
                                    <option value="sen">Sen (Samimi / Rahat)</option>
                                </select>
                                <span class="text-[11px] text-slate-400">Müşteriye hitap yöntemi.</span>
                            </div>

                            {{-- Greeting --}}
                            <div class="space-y-1">
                                <div class="flex items-center gap-1.5">
                                    <label class="text-xs font-semibold text-slate-600">Selamlama Cümlesi</label>
                                    <div class="group relative inline-block cursor-help">
                                        <svg class="w-3.5 h-3.5 text-slate-400 hover:text-slate-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <div class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 w-64 -translate-x-1/2 rounded-[6px] bg-slate-900 p-2.5 text-xs text-white opacity-0 transition-opacity group-hover:opacity-100 shadow-lg text-left leading-normal font-normal">
                                            Mesajın en başında yer alacak giriş/selamlama ifadesidir. Örneğin: 'Merhaba,'.
                                        </div>
                                    </div>
                                </div>
                                <input type="text" wire:model="greeting" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-slate-800 focus:border-slate-400 focus:outline-none" placeholder="Örn: Merhaba,">
                                <span class="text-[11px] text-slate-400">Mesaj başlangıcı.</span>
                            </div>

                            {{-- Signature --}}
                            <div class="space-y-1">
                                <div class="flex items-center gap-1.5">
                                    <label class="text-xs font-semibold text-slate-600">İmza / Kapanış</label>
                                    <div class="group relative inline-block cursor-help">
                                        <svg class="w-3.5 h-3.5 text-slate-400 hover:text-slate-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <div class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 w-64 -translate-x-1/2 rounded-[6px] bg-slate-900 p-2.5 text-xs text-white opacity-0 transition-opacity group-hover:opacity-100 shadow-lg text-left leading-normal font-normal">
                                            Mesajın sonuna eklenecek kapatma/imza ifadesidir. Örneğin: 'ZOLM Destek Ekibi'.
                                        </div>
                                    </div>
                                </div>
                                <input type="text" wire:model="signature" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-slate-800 focus:border-slate-400 focus:outline-none" placeholder="Örn: ZOLM Destek Ekibi">
                                <span class="text-[11px] text-slate-400">Cevap sonuna eklenecek imza metni.</span>
                            </div>
                        </div>

                        {{-- Emoji Option --}}
                        <div class="flex items-center gap-2 py-2">
                            <input type="checkbox" id="use_emoji" wire:model="use_emoji" class="rounded border-slate-200 text-slate-900 focus:ring-slate-900 h-4 w-4">
                            <label for="use_emoji" class="text-xs font-semibold text-slate-700 cursor-pointer">Cevaplarda emoji kullanılmasına izin ver</label>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-xs font-semibold text-slate-600 block">Cevap Uzunluğu</label>
                                <select wire:model="responseLength" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2">
                                    <option value="short">Kısa (en fazla 300 karakter)</option>
                                    <option value="medium">Orta (en fazla 700 karakter)</option>
                                    <option value="long">Uzun (en fazla 1200 karakter)</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-semibold text-slate-600 block">Emoji Seviyesi</label>
                                <select wire:model="emojiLevel" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2">
                                    <option value="none">Yok</option><option value="low">Düşük</option><option value="normal">Normal</option><option value="high">Yüksek</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-semibold text-slate-600 block">Tercih Edilen İfadeler</label>
                                <textarea wire:model="preferredExpressions" rows="2" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2" placeholder="Virgül veya yeni satırla ayırın"></textarea>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-semibold text-slate-600 block">Yasaklı İfadeler</label>
                                <textarea wire:model="forbiddenExpressions" rows="2" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2" placeholder="Virgül veya yeni satırla ayırın"></textarea>
                            </div>
                            <div class="space-y-1"><label class="text-xs font-semibold text-slate-600 block">Şikâyet Tonu</label><input wire:model="complaintTone" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2"></div>
                            <div class="space-y-1"><label class="text-xs font-semibold text-slate-600 block">Satış Tonu</label><input wire:model="salesTone" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2"></div>
                            <div class="space-y-1 sm:col-span-2"><label class="text-xs font-semibold text-slate-600 block">Kriz Tonu</label><input wire:model="crisisTone" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2"></div>
                            <div class="space-y-1 sm:col-span-2">
                                <label class="text-xs font-semibold text-slate-600 block">Dil Bazlı Kurallar (JSON)</label>
                                <textarea wire:model="languageRulesJson" rows="5" class="w-full font-mono text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2" placeholder='{"tr":{"forbidden_expressions":[],"examples":[]}}'></textarea>
                            </div>
                        </div>

                        {{-- Sample response --}}
                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-slate-600 block">Örnek Kısa Yanıt / Şablon</label>
                            <textarea wire:model="sample_response" rows="2" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-slate-800 focus:border-slate-400 focus:outline-none" placeholder="Müşteriye referans gösterilebilecek ideal bir örnek cevap metni yazın..."></textarea>
                            <span class="text-[11px] text-slate-400">AI için kılavuz niteliğindeki örnek cevap kalıbı.</span>
                        </div>

                        {{-- Return policy --}}
                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-slate-600 block">Özel İade Koşulları / Kuralları</label>
                            <textarea wire:model="return_policy" rows="3" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-slate-800 focus:border-slate-400 focus:outline-none" placeholder="Örn: Mobilyalarda kurulum yapıldıktan sonra iade kabul edilmemektedir."></textarea>
                            <span class="text-[11px] text-slate-400">Kanal bazlı iade politikası prompt eklemesi.</span>
                        </div>

                        {{-- Prompt context --}}
                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-slate-600 block">AI Sistem Prompt / Davranış Talimatı</label>
                            <textarea wire:model="prompt_context" rows="4" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-slate-800 focus:border-slate-400 focus:outline-none" placeholder="AI'ın uyması gereken diğer sistem kuralları..."></textarea>
                            <span class="text-[11px] text-slate-400">AI asistanın rolü, uyması gereken kurallar ve davranış sınırları. (Prompt injection denetimine tabidir)</span>
                        </div>
                    </div>
                </div>

                {{-- Form Section: Automation Level & Safety Limits --}}
                <div class="bg-white rounded-[10px] border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50">
                        <h2 class="font-semibold text-slate-900 text-base">Çalışma Seviyesi ve Güvenlik Limitleri</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Kanalın otomasyon derecesini ve AI yanıtlarının güven eşiklerini belirleyin.</p>
                    </div>

                    <div class="p-5 space-y-4">
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-sm font-semibold text-slate-900">Kanal Aktifliği</h3>
                                        <span class="px-2 py-0.5 text-[11px] font-mono font-bold rounded border {{ $channelEnabled ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200' }}">
                                            {{ $channelEnabled ? 'AKTİF' : 'PASİF' }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-1">
                                        Pasif kanallar konuşma bağlamında kalır fakat dış kanala yanıt göndermez. İlk kurulumda güvenli varsayılan pasiftir.
                                    </p>
                                </div>

                                <label class="inline-flex items-center gap-3 rounded-[6px] border border-slate-200 bg-white px-3 py-2 cursor-pointer select-none w-full sm:w-auto justify-between sm:justify-start">
                                    <span class="text-sm font-medium text-slate-700">Kanalı etkinleştir</span>
                                    <input type="checkbox" wire:model.live="channelEnabled" class="sr-only peer">
                                    <span class="relative h-6 w-11 rounded-full bg-slate-200 transition peer-checked:bg-slate-900">
                                        <span class="absolute left-1 top-1 h-4 w-4 rounded-full bg-white transition peer-checked:translate-x-5"></span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            {{-- AI Mode --}}
                            <div class="space-y-1">
                                <div class="flex items-center gap-1.5">
                                    <label class="text-xs font-semibold text-slate-600">Kanal Otomasyon Modu</label>
                                    <div class="group relative inline-block cursor-help">
                                        <svg class="w-3.5 h-3.5 text-slate-400 hover:text-slate-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <div class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 w-64 -translate-x-1/2 rounded-[6px] bg-slate-900 p-2.5 text-xs text-white opacity-0 transition-opacity group-hover:opacity-100 shadow-lg text-left leading-normal font-normal">
                                            Manual: AI yanıt vermez. Copilot: AI taslak hazırlar, temsilci onaylayınca gider. Automatic: AI doğrudan yanıtı gönderir.
                                        </div>
                                    </div>
                                </div>
                                <select wire:model="aiMode" class="w-full text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-slate-800 focus:border-slate-400 focus:outline-none">
                                    <option value="manual">Manual (Yalnızca el ile temsilci yanıtı)</option>
                                    <option value="copilot">Copilot (Temsilciye taslak önerileri üretilir)</option>
                                    <option value="automatic">Automatic (AI doğrudan otomatik yanıt yollar)</option>
                                </select>
                                <span class="text-[11px] text-slate-400">
                                    Automatic modu seçmek için tüm pilot checklist kriterlerinin başarıyla geçilmiş olması şarttır.
                                </span>
                            </div>

                            {{-- Min Confidence --}}
                            <div class="space-y-1">
                                <div class="flex items-center gap-1.5">
                                    <label class="text-xs font-semibold text-slate-600">Min. Otomasyon Güven Eşiği (Threshold)</label>
                                    <div class="group relative inline-block cursor-help">
                                        <svg class="w-3.5 h-3.5 text-slate-400 hover:text-slate-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <div class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 w-64 -translate-x-1/2 rounded-[6px] bg-slate-900 p-2.5 text-xs text-white opacity-0 transition-opacity group-hover:opacity-100 shadow-lg text-left leading-normal font-normal">
                                            Yapay zeka yanıtının otomatik gönderilmesi için gereken minimum güven oranıdır. Altındaki yanıtlarda onay istenir.
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <input type="range" wire:model="minConfidence" min="80" max="100" class="w-full accent-slate-900">
                                    <span class="font-bold text-slate-900 text-sm w-12 text-right font-mono">%{{ $minConfidence }}</span>
                                </div>
                                <span class="text-[11px] text-slate-400">AI yanıt güven skoru bu değerin altındaysa otomatik gönderim engellenir (min %80).</span>
                            </div>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-slate-600 block">Intent Bazlı Modlar (JSON)</label>
                            <textarea wire:model.defer="intentModesJson" rows="7" class="w-full text-base sm:text-sm font-mono rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-slate-800 focus:border-slate-400 focus:outline-none"></textarea>
                            <span class="text-[11px] text-slate-400">Daha dar intent modu kanal ayarını geçersiz kılar; sağlık/hukuk için manual önerilir.</span>
                        </div>
                    </div>
                </div>

                {{-- Action surface --}}
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" wire:click="saveSettings" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] bg-slate-900 hover:bg-slate-800 text-white font-medium text-sm transition-all focus:outline-none">
                        Ayarları Kaydet
                    </button>
                </div>

            </div>

            {{-- Column 3: Pilot Readiness Checklist --}}
            <div class="lg:col-span-1 space-y-6">

                {{-- Pilot Activation Center --}}
                <div class="bg-white rounded-[10px] border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50">
                        <h2 class="font-semibold text-slate-900 text-base">Pilot Aktivasyon Kontrolü</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Mağazanın otomatik AI yanıtlarına (Automatic Mode) geçişe hazır olup olmadığını gösteren güvenlik listesi.</p>
                    </div>

                    @if ($readiness)
                        <div class="p-5 space-y-4">
                            {{-- Overall Status Indicator --}}
                            <div class="p-4 rounded-[8px] border flex items-center justify-between gap-3 min-w-0 {{ $readiness['ready'] ? 'bg-emerald-50/70 border-emerald-200' : 'bg-amber-50/60 border-amber-200' }}">
                                <div class="min-w-0">
                                    <span class="text-xs font-bold font-mono text-slate-400 block">GENEL DURUM</span>
                                    <span class="text-sm font-bold truncate {{ $readiness['ready'] ? 'text-emerald-800' : 'text-amber-800' }}">
                                        {{ $readiness['ready'] ? 'Pilot Otomasyonuna Hazır' : 'Hazır Değil' }}
                                    </span>
                                </div>
                                @if ($readiness['ready'])
                                    <span class="px-2 py-1 text-xs font-bold font-mono rounded bg-emerald-100 text-emerald-800 shrink-0">HAZIR</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-bold font-mono rounded bg-amber-100 text-amber-800 shrink-0">EKSİK VAR</span>
                                @endif
                            </div>

                            {{-- Checklist Items --}}
                            <div class="divide-y divide-slate-100">
                                @foreach($readiness['checks'] as $key => $check)
                                    <div class="py-3 flex items-start justify-between gap-3 text-sm">
                                        <div class="min-w-0">
                                            <span class="font-medium text-slate-900 block truncate">{{ $check['label'] }}</span>
                                            <span class="text-xs text-slate-500 block truncate">{{ $check['detail'] }}</span>
                                        </div>
                                        <div>
                                            @if ($check['status'] === 'passed')
                                                <span class="px-2 py-0.5 text-[11px] font-mono font-bold rounded bg-emerald-50 text-emerald-700 border border-emerald-200">PASS</span>
                                            @elseif ($check['status'] === 'failed')
                                                <span class="px-2 py-0.5 text-[11px] font-mono font-bold rounded bg-red-50 text-red-700 border border-red-200">FAIL</span>
                                            @else
                                                <span class="px-2 py-0.5 text-[11px] font-mono font-bold rounded bg-amber-50 text-amber-700 border border-amber-200">WARN</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Activation Button for Automatic Mode --}}
                            @if ($aiMode !== 'automatic')
                                @if ($readiness['ready'])
                                    <div class="pt-3 border-t border-slate-100">
                                        <button type="button" wire:click="$set('aiMode', 'automatic')" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs transition-all uppercase tracking-wider">
                                            Otomatik Modu Aktifleştir
                                        </button>
                                        <p class="text-[10px] text-slate-400 text-center mt-1.5">Tüm güvenlik kriterleri sağlandığı için otomatik moda geçebilirsiniz.</p>
                                    </div>
                                @else
                                    <div class="pt-3 border-t border-slate-100">
                                        <button type="button" disabled class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] bg-slate-100 text-slate-400 font-semibold text-xs cursor-not-allowed uppercase tracking-wider">
                                            Otomatik Mod Kilitli
                                        </button>
                                        <p class="text-[10px] text-slate-400 text-center mt-1.5">Yukarıdaki eksikleri gidererek otomatik moda geçiş kilidini açabilirsiniz.</p>
                                    </div>
                                @endif
                            @else
                                <div class="pt-3 border-t border-slate-100">
                                    <button type="button" wire:click="$set('aiMode', 'manual')" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] bg-red-600 hover:bg-red-700 text-white font-semibold text-xs transition-all uppercase tracking-wider">
                                        Otomasyonu Devre Dışı Bırak (Kapat)
                                    </button>
                                    <p class="text-[10px] text-red-500 text-center mt-1.5 font-medium">Şu an AI otomatik yanıt veriyor. Kapatmak için tıklayın.</p>
                                </div>
                            @endif

                            {{-- Circuit Breaker Info --}}
                            @php
                                $monitor = app(\App\Services\Support\CustomerCarePilotMonitorService::class);
                                $metrics = $monitor->getStoreMetrics($selectedStoreId);
                                $isTripped = ($metrics['circuit_breaker_status'] ?? 'closed') === 'open';
                            @endphp
                            @if ($isTripped)
                                <div class="p-3 bg-red-50 border border-red-100 rounded-[8px] space-y-1">
                                    <div class="flex items-center gap-1.5 text-xs font-bold text-red-800">
                                        <svg class="w-4 h-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        <span>ACİL DURUM BLOKAJI</span>
                                    </div>
                                    <p class="text-[11px] text-red-700">Devre kesici (Circuit Breaker) otomatik modülü bloke etti. Lütfen temsilci kontrolüne geçin.</p>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Kullanım ve Limitler Kartı --}}
                @if($selectedStoreId && !empty($usageData))
                    <div class="bg-white rounded-[10px] border border-slate-200 shadow-sm p-5 space-y-4 mt-6" id="settings-usage-limits-card">
                        <div>
                            <h3 class="font-semibold text-slate-900 text-sm">Kullanım ve Limitler</h3>
                            <p class="text-xs text-slate-500 mt-0.5">SaaS paketinizin kota ve harcanan limit bilgileri.</p>
                        </div>

                        <div class="space-y-3">
                            @foreach($usageData as $metric => $data)
                                <div class="space-y-1">
                                    <div class="flex justify-between text-xs font-semibold">
                                        <span class="text-slate-600">{{ ucfirst(str_replace('_', ' ', $metric)) }}</span>
                                        <span class="text-slate-900 font-mono">{{ $data['current'] }} / {{ $data['limit'] === PHP_INT_MAX ? 'Sınırsız' : $data['limit'] }}</span>
                                    </div>
                                    <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                                        @php
                                            $pct = ($data['limit'] > 0 && $data['limit'] !== PHP_INT_MAX) ? min(100, round(($data['current'] / $data['limit']) * 100)) : 0;
                                            $color = $pct >= 90 ? 'bg-red-500' : ($pct >= 75 ? 'bg-amber-500' : 'bg-slate-900');
                                        @endphp
                                        <div class="{{ $color }} h-1.5 rounded-full" style="width: {{ $pct }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

            </div>

        </div>
    @endif
</div>
