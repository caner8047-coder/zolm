<div class="space-y-4 lg:space-y-6 p-4 lg:p-6 bg-slate-50/50 min-h-screen">
    <!-- Top Workspace Summary Card -->
    <div class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl lg:text-2xl font-semibold text-slate-900">Kalite Denetim & Coaching Merkezi</h1>
                <p class="text-sm text-slate-500 mt-1">AI ve müşteri temsilcisi cevaplarını denetleyin, kalite skor kartlarını doldurun ve kişiselleştirilmiş coaching geri bildirimlerini inceleyin.</p>
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

    <!-- Main Workspace Grid -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 lg:gap-6 items-start">

        <!-- Left Side: Review Queue -->
        <div class="xl:col-span-1 space-y-4">
            <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="p-4 border-b border-slate-150 bg-slate-50/50 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-900">İnceleme Kuyruğu</h2>
                    <select wire:model.live="filterType" class="w-full sm:w-auto rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-base sm:text-sm text-slate-700">
                        <option value="ai_run">AI Cevapları (Auto/Draft)</option>
                        <option value="agent_reply">Temsilci Cevapları</option>
                    </select>
                </div>

                <div class="divide-y divide-slate-100 max-h-[600px] overflow-y-auto">
                    @forelse($reviewQueue as $item)
                        <div wire:click="selectItem({{ $item->id }}, '{{ $filterType }}')"
                             class="p-4 hover:bg-slate-50 cursor-pointer transition flex flex-col gap-2 {{ $selectedItemId === $item->id ? 'bg-slate-50 border-l-4 border-slate-900' : '' }}">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-mono bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded">
                                    @if($filterType === 'ai_run')
                                        ID: #{{ $item->id }} | {{ strtoupper($item->status) }}
                                    @else
                                        MSG: #{{ $item->id }}
                                    @endif
                                </span>
                                <span class="text-slate-400">{{ $item->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-sm text-slate-800 line-clamp-2">
                                @if($filterType === 'ai_run')
                                    {{ $item->prompt_raw }}
                                @else
                                    {{ $item->body_encrypted }}
                                @endif
                            </p>
                        </div>
                    @empty
                        <div class="p-8 text-center text-sm text-slate-500">
                            Kuyrukta inceleme adayı kayıt bulunamadı.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Coaching / Feedbacks Section -->
            <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6 space-y-4">
                <h3 class="text-sm font-semibold text-slate-900">Temsilci Gelişim & Coaching Paneli</h3>

                <div class="space-y-3">
                    <div class="p-3 rounded-[8px] bg-emerald-50/60 border border-emerald-100">
                        <h4 class="text-xs font-bold text-emerald-800 uppercase tracking-wider">Güçlü Yönler</h4>
                        <ul class="text-xs text-emerald-950 mt-1 list-disc pl-4 space-y-1">
                            @forelse($coachingData['strong_points'] as $pt)
                                <li>{{ $pt }}</li>
                            @empty
                                <li>Henüz yeterli değerlendirme verisi yok.</li>
                            @endforelse
                        </ul>
                    </div>

                    <div class="p-3 rounded-[8px] bg-amber-50/60 border border-amber-100">
                        <h4 class="text-xs font-bold text-amber-800 uppercase tracking-wider">Dikkat Edilmesi Gerekenler</h4>
                        <ul class="text-xs text-amber-950 mt-1 list-disc pl-4 space-y-1">
                            @forelse($coachingData['attention_points'] as $pt)
                                <li>{{ $pt }}</li>
                            @empty
                                <li>Henüz olumsuz geri bildirim bulunmuyor.</li>
                            @endforelse
                        </ul>
                    </div>

                    <div class="p-3 rounded-[8px] bg-slate-50 border border-slate-100">
                        <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Örnek İyi Cevaplar</h4>
                        <div class="mt-2 space-y-2">
                            @forelse($coachingData['best_replies'] as $reply)
                                <div class="text-xs border-b border-slate-100 pb-2 last:border-0 last:pb-0">
                                    <div class="flex items-center justify-between text-slate-500 mb-1">
                                        <span>Skor: <strong class="text-slate-800">%{{ $reply['score'] }}</strong></span>
                                    </div>
                                    <p class="italic text-slate-600">"{{ $reply['body'] }}"</p>
                                </div>
                            @empty
                                <p class="text-xs text-slate-500">Kayıtlı örnek cevap bulunmuyor.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Quality Scorecard Form -->
        <div class="xl:col-span-2 space-y-4">
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

            <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6 space-y-6">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-lg font-semibold text-slate-900">Değerlendirme Skor Kartı</h2>
                    <p class="text-sm text-slate-500 mt-1">Seçili cevabın doğruluğunu, uyumunu ve güvenliğini puanlayın.</p>
                </div>

                @if($selectedItemId)
                    <!-- Content Preview -->
                    <div class="p-4 rounded-[8px] border border-slate-100 bg-slate-50/70">
                        <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">İncelenen Cevap İçeriği</h3>
                        <pre class="text-sm text-slate-800 font-sans whitespace-pre-wrap leading-relaxed">{{ $itemPreviewText }}</pre>
                    </div>

                    <!-- Scores Inputs -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($scores as $category => $score)
                            <div class="space-y-1">
                                <div class="flex items-center justify-between">
                                    <label class="text-xs font-bold text-slate-700 capitalize">
                                        @switch($category)
                                            @case('accuracy') Doğruluk / Kaynak Uyumu @break
                                            @case('brand_voice') Marka Sesi Uyum @break
                                            @case('channel_policy') Kanal Politika Uyum @break
                                            @case('pii_safety') PII / KVKK Güvenliği @break
                                            @case('clarity') Çözüm Netliği @break
                                            @case('sales_alignment') Satış Uygunluğu @break
                                            @case('promise_risk') Vaat / Uydurma Riski @break
                                            @default {{ $category }}
                                        @endswitch
                                    </label>
                                    <span class="text-xs text-slate-500 font-mono">{{ $score === null ? 'Puanlanmadı' : '%' . $score }}</span>
                                </div>
                                <input type="range" min="0" max="100" step="5" wire:model.live="scores.{{ $category }}"
                                       aria-label="{{ $category }} kalite puanı"
                                       class="w-full h-1.5 bg-slate-100 rounded-lg appearance-none cursor-pointer accent-slate-900">
                                <input type="text" wire:model.defer="comments.{{ $category }}" placeholder="Kategori notu (isteğe bağlı)..."
                                       class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-base sm:text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-900/10">
                            </div>
                        @endforeach
                    </div>

                    <!-- General Decision & Feedback -->
                    <div class="space-y-4 pt-4 border-t border-slate-100">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-slate-700">Değerlendirme Kararı</label>
                                <select wire:model="decision" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-base sm:text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900/10">
                                    <option value="approved">Onayla / Kabul Et</option>
                                    <option value="correction_required">Düzeltme Gerekli</option>
                                    <option value="golden_candidate">Golden Dataset Adayı</option>
                                    <option value="kb_candidate">Bilgi Bankası Öneri Adayı</option>
                                </select>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-bold text-slate-700">Genel Geri Bildirim & Değerlendirme</label>
                            <textarea wire:model.defer="feedback" rows="4" placeholder="Cevaba ait güçlü veya zayıf yönleri buraya yazın..."
                                      class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-base sm:text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-900/10"></textarea>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-4">
                        <p class="text-xs text-slate-500">Kaydetmeden önce yedi kriterin tamamını puanlayın.</p>
                        <button wire:click="submitReview" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 text-white rounded-[6px] text-sm font-semibold hover:bg-slate-800 transition">
                            Değerlendirmeyi Tamamla ve Kaydet
                        </button>
                    </div>

                @else
                    <div class="p-8 text-center text-sm text-slate-500">
                        Lütfen değerlendirmek istediğiniz bir kaydı sol kuyruktan seçin.
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
