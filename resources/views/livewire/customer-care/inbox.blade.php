<div class="w-full space-y-4 lg:space-y-6 p-4 lg:p-6">
    <!-- Üst Workspace / Başlık Kartı -->
    <div class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="min-w-0">
                <div class="inline-flex items-center rounded px-2 py-0.5 text-xs font-mono bg-slate-100 text-slate-600 uppercase tracking-wider mb-2">
                    Müşteri İlişkileri
                </div>
                <h1 id="inbox-page-title" class="text-xl lg:text-2xl font-bold text-slate-900 tracking-tight">
                    Temsilci Çalışma Ekranı / Inbox
                </h1>
                <p class="mt-1 text-sm text-slate-500 max-w-2xl">
                    Aktif müşteri konuşmalarını inceleyin, AI taslaklarını onaylayın veya temsilci olarak doğrudan yanıt gönderin.
                </p>
            </div>
            <div class="shrink-0 flex items-center gap-2">
                <span class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700">
                    Toplam Konuşma: {{ count($conversations) }}
                </span>
            </div>
        </div>
    </div>

    <!-- Kanal sağlık yüzeyi: sahte başarı yerine son gerçek kontrol ve sync kanıtı -->
    <div class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between gap-3 mb-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Kanal Sağlığı</h2>
                <p class="text-xs text-slate-500">Son bağlantı kontrolü, senkronizasyon ve hata durumu.</p>
            </div>
            <span class="px-2 py-0.5 text-xs font-mono rounded bg-slate-50 text-slate-600 border border-slate-200">{{ $channelHealth->count() }} KANAL</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 lg:gap-4">
            @forelse($channelHealth as $healthChannel)
                @php($healthy = $healthChannel->is_enabled && $healthChannel->last_health_status === 'ok')
                <div class="min-w-0 rounded-[8px] border {{ $healthy ? 'border-emerald-200 bg-emerald-50/60' : 'border-slate-200 bg-slate-50/60' }} p-3">
                    <div class="flex items-center justify-between gap-2">
                        <span class="truncate text-sm font-semibold text-slate-900">{{ $healthChannel->name }}</span>
                        <span class="shrink-0 px-2 py-0.5 text-[10px] font-mono rounded {{ $healthy ? 'bg-emerald-100 text-emerald-700' : ($healthChannel->last_health_status === 'error' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
                            {{ $healthy ? 'SAĞLIKLI' : strtoupper($healthChannel->last_health_status ?? $healthChannel->status ?? 'BİLİNMİYOR') }}
                        </span>
                    </div>
                    <div class="mt-2 grid grid-cols-2 gap-2 text-[11px] text-slate-500">
                        <span>Kontrol: {{ $healthChannel->last_health_check_at?->diffForHumans() ?? 'yapılmadı' }}</span>
                        <span>Sync: {{ $healthChannel->last_sync_at?->diffForHumans() ?? 'yapılmadı' }}</span>
                    </div>
                    @if($healthChannel->last_health_error)
                        <p class="mt-2 truncate text-xs text-red-700" title="{{ $healthChannel->last_health_error }}">{{ $healthChannel->last_health_error }}</p>
                    @endif
                </div>
            @empty
                <p class="text-sm text-slate-500 sm:col-span-2 xl:col-span-3">Henüz bağlı kanal bulunmuyor.</p>
            @endforelse
        </div>
    </div>

    <!-- Hata ve Başarı Mesajları -->
    @if ($errorMessage)
        <div class="rounded-[6px] border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <strong>Hata:</strong> {{ $errorMessage }}
        </div>
    @endif
    @if ($successMessage)
        <div class="rounded-[6px] border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
            {{ $successMessage }}
        </div>
    @endif

    <!-- Ana Çalışma Yüzeyi (Split Layout) -->
    <div class="flex flex-col lg:flex-row gap-4 lg:gap-6 min-h-[600px]">

        <!-- SOL PANEL: Konuşma Listesi ve Filtreler -->
        <div class="w-full lg:w-1/3 flex flex-col gap-3 rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">

            <!-- Arama Kutusu -->
            <div class="relative">
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Konuşma veya müşteri ara..."
                       class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-400 focus:outline-none placeholder-slate-400" />
            </div>

            <!-- Filtreleme Seçenekleri (Grid) -->
            <div class="grid grid-cols-2 gap-2 text-xs font-medium text-slate-600">
                <div>
                    <label class="block text-[10px] font-mono uppercase text-slate-400 mb-1">Durum</label>
                    <select wire:model.live="filterStatus" class="w-full rounded-[6px] border border-slate-200 bg-white px-2 py-2 text-base sm:text-sm focus:outline-none">
                        <option value="all">Tümü</option>
                        <option value="open">Açık</option>
                        <option value="resolved">Çözüldü</option>
                        <option value="closed">Kapatıldı</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-mono uppercase text-slate-400 mb-1">Kanal</label>
                    <select wire:model.live="filterChannel" class="w-full rounded-[6px] border border-slate-200 bg-white px-2 py-2 text-base sm:text-sm focus:outline-none">
                        <option value="all">Tümü</option>
                        <option value="trendyol">Trendyol</option>
                        <option value="hepsiburada">Hepsiburada</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="n11">N11</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-mono uppercase text-slate-400 mb-1">Sahiplik</label>
                    <select wire:model.live="filterOwnership" class="w-full rounded-[6px] border border-slate-200 bg-white px-2 py-2 text-base sm:text-sm focus:outline-none">
                        <option value="all">Tümü</option>
                        <option value="human">Temsilci</option>
                        <option value="ai">Yapay Zeka (AI)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-mono uppercase text-slate-400 mb-1">AI Modu</label>
                    <select wire:model.live="filterAiMode" class="w-full rounded-[6px] border border-slate-200 bg-white px-2 py-2 text-base sm:text-sm focus:outline-none">
                        <option value="all">Tümü</option>
                        <option value="manual">Manual</option>
                        <option value="copilot">Copilot</option>
                        <option value="automatic">Otomatik</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-mono uppercase text-slate-400 mb-1">Ekip</label>
                    <select wire:model.live="filterTeamId" class="w-full rounded-[6px] border border-slate-200 bg-white px-2 py-2 text-base sm:text-sm focus:outline-none">
                        <option value="all">Tüm Ekipler</option>
                        @foreach ($teams as $team)
                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-mono uppercase text-slate-400 mb-1">Atanan</label>
                    <select wire:model.live="filterAssignee" class="w-full rounded-[6px] border border-slate-200 bg-white px-2 py-2 text-base sm:text-sm focus:outline-none">
                        <option value="all">Tümü</option>
                        <option value="me">Bana Atanan</option>
                        <option value="unassigned">Atanmamış</option>
                    </select>
                </div>
            </div>

            <!-- Aktif Filtre Bilgisi -->
            <div class="rounded-[6px] bg-slate-50 p-2 text-xs text-slate-500 flex justify-between items-center">
                <span>
                    Aktif Filtre:
                    <span class="font-semibold text-slate-800">
                        {{ $filterStatus === 'all' ? 'Tüm Durumlar' : ucfirst($filterStatus) }},
                        {{ $filterChannel === 'all' ? 'Tüm Kanallar' : ucfirst($filterChannel) }}
                    </span>
                </span>
                @if($search)
                    <span class="bg-indigo-50 text-indigo-700 px-1.5 py-0.5 rounded font-mono text-[10px]">Arama Aktif</span>
                @endif
            </div>

            <hr class="border-slate-100" />

            <!-- Liste (Scrollable) -->
            <div class="flex-1 overflow-y-auto space-y-2 max-h-[500px] pr-1">
                @forelse ($conversations as $conv)
                    <button wire:key="conv-{{ $conv->id }}" wire:click="selectConversation({{ $conv->id }})"
                            class="w-full text-left p-3 rounded-[8px] border transition-all duration-150 flex flex-col gap-1.5 {{ $selectedConversationId === $conv->id ? 'border-slate-900 bg-slate-50 shadow-sm' : 'border-slate-200 bg-white hover:bg-slate-50/50' }}">

                        <div class="flex justify-between items-center">
                            <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-bold rounded uppercase tracking-wider
                                       {{ $conv->source_type === 'trendyol' ? 'bg-orange-100 text-orange-800' : '' }}
                                       {{ $conv->source_type === 'hepsiburada' ? 'bg-red-100 text-red-800' : '' }}
                                       {{ $conv->source_type === 'whatsapp' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                       {{ $conv->source_type === 'n11' ? 'bg-blue-100 text-blue-800' : '' }}">
                                {{ $conv->source_type }}
                            </span>
                            <span class="text-[11px] font-mono text-slate-400">
                                {{ $conv->last_message_at ? $conv->last_message_at->diffForHumans() : '' }}
                            </span>
                        </div>

                        <div class="flex justify-between items-start gap-1">
                            <span class="text-sm font-bold text-slate-800 truncate">
                                #{{ $conv->external_conversation_id }}
                            </span>
                            <span class="text-xs font-medium px-2 py-0.5 rounded-[6px] border
                                       {{ $conv->status === 'open' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : '' }}
                                       {{ $conv->status === 'resolved' ? 'border-slate-200 bg-slate-100 text-slate-800' : '' }}
                                       {{ $conv->status === 'closed' ? 'border-red-200 bg-red-50 text-red-800' : '' }}">
                                {{ $conv->status === 'open' ? 'Açık' : ($conv->status === 'resolved' ? 'Çözüldü' : 'Kapatıldı') }}
                            </span>
                        </div>

                        <div class="text-xs text-slate-500 truncate">
                            {{ $conv->messages->last()->body_preview ?? 'Mesaj yok' }}
                        </div>

                        <div class="flex justify-between items-center text-[10px] text-slate-400 font-mono pt-1 border-t border-slate-100/50">
                            <span>Sahiplik: {{ $conv->ownership_status === 'human' ? 'Temsilci' : 'AI' }}</span>
                            <span>Mod: {{ ucfirst($conv->ai_mode) }}</span>
                        </div>
                        @if($conv->supportTeam || $conv->assignedUser)
                            <div class="flex justify-between items-center text-[10px] text-slate-500 font-medium">
                                <span>Ekip: {{ $conv->supportTeam->name ?? 'Yok' }}</span>
                                <span>Temsilci: {{ $conv->assignedUser->name ?? 'Atanmamış' }}</span>
                            </div>
                        @endif

                    </button>
                @empty
                    <div class="text-center py-6 text-sm text-slate-400">
                        Kriterlere uygun konuşma bulunamadı.
                    </div>
                @endforelse
            </div>

        </div>

        <!-- SAĞ PANEL: Konuşma Detayı ve Aksiyon Alanı -->
        <div class="w-full lg:w-2/3 flex flex-col rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm min-h-[500px]">
            @if ($selectedConversation)

                <!-- Detay Header -->
                <div class="border-b border-slate-200 pb-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-bold text-slate-900">
                                Konuşma #{{ $selectedConversation->external_conversation_id }}
                            </h2>
                            <span class="text-xs text-slate-500 font-mono">({{ $selectedConversation->store->store_name }})</span>
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Müşteri ID: {{ $selectedConversation->external_customer_id }} | Son Aktivite: {{ $selectedConversation->last_message_at ? $selectedConversation->last_message_at->format('H:i:s') : 'Bilinmiyor' }}
                        </p>
                    </div>

                    <!-- Aksiyon Butonları -->
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($selectedConversation->status === 'open')
                            <button wire:click="resolveConversation" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] border border-slate-200 bg-white hover:bg-slate-50 text-xs font-semibold text-slate-700">
                                Çözüldü İşaretle
                            </button>
                        @else
                            <button wire:click="reopenConversation" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] border border-slate-200 bg-white hover:bg-slate-50 text-xs font-semibold text-slate-700">
                                Yeniden Aç
                            </button>
                        @endif

                        @if ($selectedConversation->ownership_status === 'human')
                            <button wire:click="releaseConversation" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] border border-amber-200 bg-amber-50 hover:bg-amber-100 text-xs font-semibold text-amber-800">
                                AI'a Geri Bırak
                            </button>
                        @else
                            <button wire:click="claimConversation" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] bg-slate-900 hover:bg-slate-800 text-xs font-semibold text-white">
                                Sahiplen (Claim)
                            </button>
                        @endif
                    </div>
                </div>

                <!-- AI Otomasyon Mod Kontrolleri -->
                <div class="bg-slate-50/70 border border-slate-200 rounded-[8px] p-3 my-3 flex flex-col sm:flex-row items-center justify-between gap-3 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-xs text-slate-500 uppercase tracking-wider">Otomasyon Modu:</span>
                        <span class="inline-flex items-center font-bold px-2 py-0.5 rounded text-xs
                                   {{ $selectedConversation->ai_mode === 'manual' ? 'bg-slate-100 text-slate-800' : '' }}
                                   {{ $selectedConversation->ai_mode === 'copilot' ? 'bg-blue-100 text-blue-800' : '' }}
                                   {{ $selectedConversation->ai_mode === 'automatic' ? 'bg-emerald-100 text-emerald-800' : '' }}">
                            {{ strtoupper($selectedConversation->ai_mode) }}
                        </span>
                    </div>
                    <div class="flex items-center gap-1">
                        <button wire:click="changeAiMode('manual')"
                                class="w-full sm:w-auto px-4 py-3 sm:py-2 px-2.5 py-1 text-xs font-medium rounded-[6px] border {{ $selectedConversation->ai_mode === 'manual' ? 'bg-slate-900 border-slate-900 text-white' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50' }}">
                            Manual
                        </button>
                        <button wire:click="changeAiMode('copilot')"
                                class="w-full sm:w-auto px-4 py-3 sm:py-2 px-2.5 py-1 text-xs font-medium rounded-[6px] border {{ $selectedConversation->ai_mode === 'copilot' ? 'bg-slate-900 border-slate-900 text-white' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50' }}">
                            Copilot
                        </button>
                        <button wire:click="changeAiMode('automatic')"
                                class="w-full sm:w-auto px-4 py-3 sm:py-2 px-2.5 py-1 text-xs font-medium rounded-[6px] border {{ $selectedConversation->ai_mode === 'automatic' ? 'bg-slate-900 border-slate-900 text-white' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50' }}">
                            Automatic
                        </button>
                    </div>
                </div>

                <!-- Split Workspace Detay Alanı (Chat ve Copilot Paneli) -->
                <div class="flex flex-col md:flex-row gap-4 lg:gap-6 flex-1 mt-2">

                    <!-- SOL: Chat ve Mesajlaşma Alanı (w-full md:w-2/3) -->
                    <div class="w-full md:w-2/3 flex flex-col gap-3 min-w-0">
                        <!-- Mesaj Zaman Tüneli (Timeline) -->
                        <div class="flex-1 overflow-y-auto min-h-[250px] max-h-[350px] border border-slate-200 rounded-[8px] bg-slate-50/20 p-4 space-y-4">
                            @forelse ($messages as $msg)
                                <div class="flex {{ $msg->direction === 'outbound' ? 'justify-end' : 'justify-start' }}">
                                    <div class="max-w-[80%] rounded-[8px] p-3 text-sm border shadow-sm
                                               {{ $msg->direction === 'outbound'
                                                    ? ($msg->sender_type === 'ai'
                                                        ? 'bg-indigo-50 border-indigo-200 text-indigo-900'
                                                        : 'bg-white border-slate-200 text-slate-900')
                                                    : 'bg-slate-100 border-slate-200 text-slate-800' }}">

                                        <div class="flex justify-between items-center text-[10px] text-slate-400 font-mono mb-1">
                                            <span>
                                                {{ $msg->direction === 'inbound' ? 'Müşteri' : ($msg->sender_type === 'ai' ? 'Yapay Zeka (AI)' : 'Temsilci') }}
                                            </span>
                                            <span>
                                                {{ $msg->created_at ? $msg->created_at->format('H:i') : '' }}
                                            </span>
                                        </div>
                                        <div class="whitespace-pre-wrap leading-relaxed">{{ $msg->body_encrypted }}</div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-12 text-sm text-slate-400">
                                    Geçmiş mesaj kaydı bulunamadı.
                                </div>
                            @endforelse
                        </div>

                        <!-- Giriş Alanı / Yanıt Formu -->
                        <div class="pt-2 border-t border-slate-200 space-y-3">
                            <div class="flex justify-between items-center">
                                <label class="block text-xs font-bold text-slate-700">Cevabınız</label>

                                @if ($selectedConversation->ai_mode === 'copilot')
                                    <button wire:click="generateAiDraft" wire:loading.attr="disabled"
                                            class="w-full sm:w-auto px-4 py-3 sm:py-2 inline-flex items-center rounded-[6px] border border-indigo-200 bg-indigo-50 hover:bg-indigo-100 text-xs font-semibold text-indigo-800">
                                        <span wire:loading wire:target="generateAiDraft" class="mr-1 h-3 w-3 animate-spin border-2 border-indigo-600 rounded-full border-t-transparent"></span>
                                        AI Taslak Oluştur
                                    </button>
                                @endif
                            </div>

                            <textarea wire:model="replyText" rows="3" placeholder="Müşteriye iletilecek mesajı yazın..."
                                      class="w-full rounded-[6px] border border-slate-200 bg-white p-3 text-base sm:text-sm focus:border-slate-400 focus:outline-none placeholder-slate-400"></textarea>

                            <div class="flex justify-end gap-2">
                                <button wire:click="sendReply" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] bg-slate-900 hover:bg-slate-800 text-sm font-semibold text-white flex justify-center items-center">
                                    Yanıtı Gönder
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- SAĞ: Satış ve Ürün Copilot Yardımcı Paneli (w-full md:w-1/3) -->
                    <div class="w-full md:w-1/3 border-t md:border-t-0 md:border-l border-slate-200 pt-4 md:pt-0 md:pl-4 flex flex-col gap-4">
                        <div>
                            <h3 class="text-xs font-mono uppercase tracking-wider text-slate-400 mb-2">Konuşma Özellikleri</h3>
                            <div class="space-y-2 text-xs font-medium text-slate-600 bg-slate-50/60 rounded-[8px] p-3 border border-slate-200">
                                <div><span class="text-slate-400 font-normal">Atanan Ekip:</span> {{ $selectedConversation->supportTeam->name ?? 'Atanmamış' }}</div>
                                <div><span class="text-slate-400 font-normal">Temsilci:</span> {{ $selectedConversation->assignedUser->name ?? 'Atanmamış' }}</div>
                                <div><span class="text-slate-400 font-normal">Öncelik:</span>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] uppercase font-bold
                                        {{ $selectedConversation->priority === 'high' ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-800' }}">
                                        {{ $selectedConversation->priority ?? 'normal' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-xs font-mono uppercase tracking-wider text-slate-400 mb-2">Satış Copilot Önerileri</h3>
                            @if (!empty($salesSuggestions))
                                <div class="space-y-3">
                                    @foreach ($salesSuggestions as $sug)
                                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/40 p-3 flex flex-col gap-2 shadow-sm">
                                            <div class="flex justify-between items-start">
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-indigo-50 text-indigo-700">
                                                    {{ str_replace('_', ' ', $sug['type']) }}
                                                </span>
                                            </div>
                                            <div class="text-xs font-bold text-slate-800">{{ $sug['title'] }}</div>
                                            <div class="text-xs text-slate-600 italic">"{{ $sug['suggested_draft'] }}"</div>
                                            <div class="text-[10px] text-slate-400 font-mono">Kaynak: {{ $sug['citation'] }}</div>
                                            <button wire:click="insertSalesSuggestion('{{ addslashes($sug['suggested_draft']) }}')"
                                                    class="w-full text-center rounded-[6px] border border-slate-200 bg-white hover:bg-slate-50 px-2 py-1 text-xs font-semibold text-slate-700">
                                                Yanıt Kutusuna Ekle
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-6 text-xs text-slate-400 bg-slate-50/50 rounded-[8px] border border-dashed border-slate-200">
                                    Aktif bir satış veya alternatif önerisi bulunamadı.
                                </div>
                            @endif
                        </div>

                        <div>
                            <h3 class="text-xs font-mono uppercase tracking-wider text-slate-400 mb-2">AI Kaynak ve İddia Defteri</h3>
                            @if($latestAiRun)
                                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3 space-y-2 min-w-0">
                                    <div class="flex items-center justify-between gap-2 text-xs">
                                        <span class="text-slate-500">Güven / durum</span>
                                        <span class="font-mono font-semibold text-slate-800">%{{ $latestAiRun->confidence_score }} · {{ strtoupper($latestAiRun->status) }}</span>
                                    </div>
                                    @forelse((array) $latestAiRun->sources_used_json as $source)
                                        <div class="rounded-[6px] border border-slate-200 bg-white p-2 text-[11px] min-w-0">
                                            <div class="font-semibold text-slate-800 truncate" title="{{ $source['name'] ?? 'Kaynak' }}">{{ $source['name'] ?? 'Kaynak' }}</div>
                                            <div class="text-slate-500 mt-0.5 break-words">Tür: {{ $source['type'] ?? '-' }} · Kayıt: {{ $source['record_id'] ?? '-' }}</div>
                                            <div class="text-slate-400 mt-0.5">Sürüm: {{ $source['version'] ?? '-' }} · Güncellik: {{ !empty($source['freshness_at']) ? \Carbon\Carbon::parse($source['freshness_at'])->format('d.m.Y H:i') : 'Yok' }}</div>
                                        </div>
                                    @empty
                                        <div class="text-xs text-red-700">Bu AI çalışması kaynak içermiyor; otomatik gönderilemez.</div>
                                    @endforelse
                                </div>
                            @else
                                <div class="rounded-[8px] border border-dashed border-slate-200 bg-slate-50/50 p-3 text-xs text-slate-400">Henüz AI kaynak kaydı yok.</div>
                            @endif
                        </div>
                    </div>

                </div>

            @else
                <!-- Seçilmemiş Durum (Empty State) -->
                <div class="flex-1 flex flex-col justify-center items-center py-20 text-center">
                    <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-semibold text-slate-900">Konuşma Seçilmedi</h3>
                    <p class="mt-1 text-xs text-slate-500 max-w-xs">
                        Lütfen sol taraftaki listeden incelemek, sahiplenmek veya yanıtlamak istediğiniz bir müşteri konuşması seçin.
                    </p>
                </div>
            @endif
        </div>

    </div>
</div>
