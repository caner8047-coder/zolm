<div class="space-y-6 p-4 lg:p-6 bg-slate-50/60 min-h-screen">
    <!-- Üst Workspace Kartı -->
    <div class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl lg:text-2xl font-bold text-slate-900">Müşteri Temsilcisi Çalışma Alanı</h1>
                <p class="text-sm text-slate-500">Makrolar, dahili notlar ve çoklu temsilci çakışma takibi ile verimli konuşma yönetimi.</p>
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

    <!-- Ana Arayüz (Responsive Flex Grid) -->
    <div class="flex flex-col lg:flex-row gap-6">
        <!-- Sol Panel: Filtreler ve Kuyruk -->
        <div class="w-full lg:w-1/4 space-y-4">
            <!-- Kayıtlı Görünümler ve Filtreleme -->
            <div class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm space-y-4">
                <h2 class="text-base font-semibold text-slate-900">Görünüm Filtreleri</h2>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Durum</label>
                        <select wire:model.live="statusFilter" class="w-full rounded-[6px] border border-slate-200 bg-white px-2 py-1.5 text-base sm:text-sm text-slate-900 focus:outline-none">
                            <option value="all">Tümü</option>
                            <option value="open">Açık</option>
                            <option value="pending">Beklemede</option>
                            <option value="resolved">Çözüldü</option>
                        </select>
                    </div>
                </div>

                <!-- Yeni Filtre Kaydet -->
                <div class="pt-3 border-t border-slate-100 space-y-2">
                    <input type="text" wire:model="newViewName" placeholder="Görünüm adı..." class="w-full rounded-[6px] border border-slate-200 px-3 py-1.5 text-base sm:text-sm text-slate-900 focus:outline-none">
                    <button wire:click="saveCustomView" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 text-white text-xs font-semibold rounded-[6px] hover:bg-slate-800 transition">Filtreyi Kaydet</button>
                    @if (session()->has('view_success'))
                        <div class="text-xs text-emerald-600 font-medium">{{ session('view_success') }}</div>
                    @endif
                </div>

                <!-- Kayıtlı Görünümler Listesi -->
                @if($savedViews->isNotEmpty())
                    <div class="pt-3 border-t border-slate-100 space-y-1">
                        <span class="block text-xs font-medium text-slate-500 mb-1">Kayıtlı Görünümler</span>
                        @foreach($savedViews as $view)
                            <button class="w-full text-left px-2 py-1.5 rounded-[6px] hover:bg-slate-50 text-xs text-slate-700 font-medium flex justify-between items-center">
                                <span>{{ $view->name }}</span>
                                <span class="bg-slate-100 px-1.5 py-0.5 rounded text-[10px]">Kayıtlı</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Konuşma Listesi (Kuyruk) -->
            <div class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                <h2 class="text-base font-semibold text-slate-900">Konuşmalar ({{ $conversations->count() }})</h2>
                <div class="divide-y divide-slate-100 max-h-[400px] overflow-y-auto">
                    @forelse($conversations as $conv)
                        <button wire:click="selectConversation({{ $conv->id }})" class="w-full text-left py-3 px-2 rounded-[8px] hover:bg-slate-50 transition flex flex-col gap-1 {{ $selectedConversationId === $conv->id ? 'bg-slate-50/80 border-l-4 border-slate-900' : '' }}">
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-mono bg-slate-100 text-slate-700 px-2 py-0.5 rounded">ID: {{ $conv->id }}</span>
                                <span class="text-xs px-2 py-0.5 rounded font-medium {{ $conv->status === 'open' ? 'bg-red-50 text-red-700' : ($conv->status === 'resolved' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700') }}">
                                    {{ strtoupper($conv->status) }}
                                </span>
                            </div>
                            <span class="text-sm font-semibold text-slate-900 truncate">Müşteri: {{ $conv->external_customer_id }}</span>
                            <span class="text-xs text-slate-500">Güncelleme: {{ $conv->updated_at->diffForHumans() }}</span>
                        </button>
                    @empty
                        <div class="text-xs text-slate-400 py-4 text-center">Filtreye uygun konuşma bulunamadı.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Orta Panel: Aktif Konuşma Detayı ve Mesaj Alanı -->
        <div class="w-full lg:w-2/4 space-y-4">
            @if($selectedConversation)
                <div class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm space-y-6">
                    <!-- Konuşma Başlığı & Çakışma Uyarısı -->
                    <div class="flex justify-between items-center pb-4 border-b border-slate-100">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">Konuşma Detayı #{{ $selectedConversation->id }}</h3>
                            <p class="text-xs text-slate-500">Dış Referans: {{ $selectedConversation->external_conversation_id }}</p>
                        </div>
                        <!-- Diğer Temsilciler Varlığı -->
                        @if($activeOtherAgents->isNotEmpty())
                            <div class="flex items-center gap-1.5 bg-amber-50 border border-amber-200 text-amber-800 text-xs px-2.5 py-1 rounded-[6px]">
                                <span class="relative flex h-2 w-2">
                                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                  <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                                </span>
                                <span>Aynı anda çalışanlar var!</span>
                            </div>
                        @endif
                    </div>

                    <!-- Mesaj Akışı -->
                    <div class="space-y-4 max-h-[350px] overflow-y-auto pr-2">
                        @foreach($messages as $msg)
                            <div class="flex flex-col {{ $msg->direction === 'outbound' ? 'items-end' : 'items-start' }}">
                                <div class="max-w-[85%] rounded-[8px] p-3 text-sm {{ $msg->direction === 'outbound' ? 'bg-slate-900 text-white' : 'bg-slate-50 border border-slate-200 text-slate-800' }}">
                                    {!! nl2br(e(Crypt::decryptString($msg->body_encrypted))) !!}
                                </div>
                                <span class="text-[10px] text-slate-400 mt-1">
                                    {{ $msg->direction === 'outbound' ? 'Yanıtlayan (Sistem/Temsilci)' : 'Müşteri' }} • {{ $msg->created_at->format('H:i') }}
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <!-- Yanıt Taslağı Hazırlama Alanı (Sadece arayüze yapıştırır, outbox ile gönderilir) -->
                    <div class="pt-4 border-t border-slate-100 space-y-3">
                        <label for="draft-reply" class="block text-sm font-semibold text-slate-700">Müşteri Yanıt Taslağı</label>
                        <textarea id="draft-reply" wire:model="draftReply" rows="4" placeholder="Cevap taslağını buraya yazın veya sağ panelden makro seçin..." class="w-full rounded-[6px] border border-slate-200 p-3 text-base sm:text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900"></textarea>
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-slate-400">Taslak metin direkt olarak müşteriye gönderilmez. Gönderim outbox entegrasyonu ile tetiklenir.</span>
                            <button class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 text-white text-xs font-semibold rounded-[6px] hover:bg-slate-800 transition">Taslağı Gönderime Hazırla</button>
                        </div>
                    </div>
                </div>
            @else
                <div class="rounded-[10px] border border-slate-200 bg-white p-12 shadow-sm text-center">
                    <span class="text-slate-400 text-sm">Detaylarını görmek için soldaki kuyruktan bir konuşma seçin.</span>
                </div>
            @endif
        </div>

        <!-- Sağ Panel: Makrolar ve Dahili Notlar -->
        <div class="w-full lg:w-1/4 space-y-4">
            <!-- Makro Seçici -->
            @if($selectedConversation)
                <div class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                    <h2 class="text-base font-semibold text-slate-900">Hazır Yanıt Makroları</h2>
                    <div class="space-y-2 max-h-[200px] overflow-y-auto">
                        @forelse($macros as $macro)
                            <button wire:click="applyMacro({{ $macro->id }})" class="w-full text-left p-2.5 rounded-[8px] bg-slate-50 border border-slate-100 hover:bg-slate-100 transition flex flex-col gap-1">
                                <span class="text-xs font-semibold text-slate-800">{{ $macro->title }}</span>
                                <span class="text-[10px] text-slate-500 truncate">{{ $macro->body }}</span>
                            </button>
                        @empty
                            <div class="text-xs text-slate-400 text-center py-4">Kayıtlı makro bulunamadı.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Dahili Notlar (Internal Notes) -->
                <div class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                    <h2 class="text-base font-semibold text-slate-900">Dahili Notlar (Özel)</h2>
                    <div class="space-y-3 divide-y divide-slate-100 max-h-[200px] overflow-y-auto pr-1">
                        @forelse($internalNotes as $note)
                            <div class="pt-2 text-xs">
                                <div class="flex justify-between text-slate-400 mb-1">
                                    <span>{{ $note->user->name ?? 'Temsilci' }}</span>
                                    <span>{{ $note->created_at->format('d.m H:i') }}</span>
                                </div>
                                <p class="text-slate-700 bg-slate-50/70 p-2 rounded-[6px] border border-slate-100">{{ $note->note_encrypted }}</p>
                            </div>
                        @empty
                            <div class="text-xs text-slate-400 text-center py-4">Bu konuşma için yazılmış dahili not bulunmamaktadır.</div>
                        @endforelse
                    </div>

                    <!-- Not Ekleme Formu -->
                    <div class="pt-3 border-t border-slate-100 space-y-2">
                        <textarea wire:model="newNote" rows="2" placeholder="Sadece temsilcilerin görebileceği not ekle..." class="w-full rounded-[6px] border border-slate-200 p-3 text-base sm:text-sm text-slate-900 focus:outline-none"></textarea>
                        <button wire:click="addInternalNote" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 text-white text-xs font-semibold rounded-[6px] hover:bg-slate-800 transition">Notu Kaydet</button>
                        @if (session()->has('note_success'))
                            <div class="text-xs text-emerald-600 font-medium">{{ session('note_success') }}</div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
