<div class="flex flex-col sm:flex-row min-h-0 bg-slate-50">
    @php
        $preview = $this->preview;
        $summary = $preview['summary'] ?? [];
        $risk = $preview['risk'] ?? [];
        $campaign = $preview['campaign'] ?? [];
        $sections = $this->sectionDefinitions();
        $frequencies = $this->frequencyDefinitions();
        $nextRun = $this->subscription->next_run_at;
        $lastRun = $this->subscription->last_sent_at;
    @endphp

    <div class="w-full space-y-4 p-4 lg:space-y-6 lg:p-6">
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-mono text-slate-500">
                        ZOLM Otomatik Raporlar
                    </div>
                    <h1 class="mt-3 text-xl font-bold tracking-tight text-slate-900 lg:text-2xl">Günlük ve haftalık kâr özetleri</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
                        Sipariş Kârlılığı, Günlük Görevler ve Kampanya Merkezi özetlerini yönetici maili olarak paketleyin.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 lg:min-w-[560px]">
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Durum</p>
                        <p class="mt-2 text-lg font-bold {{ $enabled ? 'text-emerald-700' : 'text-slate-900' }}">{{ $enabled ? 'Aktif' : 'Kapalı' }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $this->subscription->last_status ? $this->statusLabel($this->subscription->last_status) : 'İlk kurulum' }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Son gönderim</p>
                        <p class="mt-2 text-sm font-bold text-slate-900">{{ $lastRun ? $lastRun->format('d.m.Y H:i') : 'Henüz yok' }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $this->subscription->last_error ?: 'Hata kaydı yok' }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sıradaki çalışma</p>
                        <p class="mt-2 text-sm font-bold text-slate-900">{{ $nextRun ? $nextRun->format('d.m.Y H:i') : 'Planlanmadı' }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $frequencies[$frequency]['label'] ?? 'Periyodik' }} · {{ $sendTime }}</p>
                    </div>
                </div>
            </div>
        </section>

        @if($notice)
            <div class="rounded-[8px] border px-4 py-3 text-sm font-medium {{ $noticeTone === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">
                {{ $notice }}
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_380px]">
            <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
                <form wire:submit.prevent="save" class="space-y-5 p-4 lg:p-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">Abonelik ayarları</h2>
                            <p class="mt-1 text-sm text-slate-500">Alıcıları, sıklığı ve rapora dahil edilecek bölümleri yönetin.</p>
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <button type="button" wire:click="sendNow" wire:loading.attr="disabled" class="w-full rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2">
                                Şimdi gönder
                            </button>
                            <button type="submit" wire:loading.attr="disabled" class="w-full rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 sm:w-auto sm:py-2">
                                Ayarları kaydet
                            </button>
                        </div>
                    </div>

                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                        <label class="flex items-start gap-3">
                            <input type="checkbox" wire:model.live="enabled" class="mt-1 h-5 w-5 rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                            <span>
                                <span class="block text-sm font-semibold text-slate-900">Otomatik gönderim aktif</span>
                                <span class="mt-1 block text-xs leading-5 text-slate-500">Kapalı olduğunda manuel “Şimdi gönder” haricinde planlı mail çıkışı yapılmaz.</span>
                            </span>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <label class="block">
                            <span class="text-xs font-semibold text-slate-500">Rapor adı</span>
                            <input type="text" wire:model.defer="name" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-400 focus:outline-none sm:py-2 sm:text-sm">
                            @error('name') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="text-xs font-semibold text-slate-500">Sıklık</span>
                            <select wire:model.live="frequency" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-400 focus:outline-none sm:py-2 sm:text-sm">
                                @foreach($frequencies as $key => $definition)
                                    <option value="{{ $key }}">{{ $definition['label'] }}</option>
                                @endforeach
                            </select>
                            @error('frequency') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="text-xs font-semibold text-slate-500">Gönderim saati</span>
                            <input type="time" wire:model.defer="sendTime" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-400 focus:outline-none sm:py-2 sm:text-sm">
                            @error('sendTime') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="block sm:col-span-2 xl:col-span-1">
                            <span class="text-xs font-semibold text-slate-500">Mağaza kapsamı</span>
                            <select wire:model.live="storeId" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-400 focus:outline-none sm:py-2 sm:text-sm">
                                <option value="">Tüm mağazalar</option>
                                @foreach($this->storeOptions as $store)
                                    <option value="{{ $store->id }}">{{ $store->store_name }} · {{ \Illuminate\Support\Str::headline($store->marketplace) }}</option>
                                @endforeach
                            </select>
                            @error('storeId') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="block sm:col-span-2">
                            <span class="text-xs font-semibold text-slate-500">Alıcılar (E-Posta)</span>
                            <textarea wire:model.defer="recipientsText" rows="2" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-400 focus:outline-none sm:text-sm" placeholder="yonetim@firma.com&#10;finans@firma.com"></textarea>
                            @error('recipientsText') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <div class="block sm:col-span-2 rounded-[8px] border border-slate-200 bg-slate-50 p-4">
                            <label class="flex items-center gap-3 mb-3">
                                <input type="checkbox" wire:model.live="webhookEnabled" class="h-5 w-5 rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                                <span class="text-sm font-semibold text-slate-900">Dış Kanallara Gönder (Webhook / Slack / API)</span>
                            </label>
                            
                            @if($webhookEnabled)
                                <label class="block">
                                    <span class="text-xs font-semibold text-slate-500">Webhook URL</span>
                                    <input type="url" wire:model.defer="webhookUrl" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 focus:border-slate-400 focus:outline-none sm:text-sm" placeholder="https://hooks.slack.com/services/... veya Zapier URL">
                                    <span class="mt-1 block text-xs text-slate-500">Rapor özeti JSON formatında bu adrese POST edilir.</span>
                                    @error('webhookUrl') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                </label>
                            @endif
                        </div>

                        <div class="block sm:col-span-2 rounded-[8px] border border-slate-200 bg-slate-50 p-4">
                            <label class="flex items-center gap-3 mb-3">
                                <input type="checkbox" wire:model.live="telegramEnabled" class="h-5 w-5 rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                                <span class="text-sm font-semibold text-slate-900">Telegram'a Gönder</span>
                            </label>
                            
                            @if($telegramEnabled)
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <label class="block">
                                        <span class="text-xs font-semibold text-slate-500">Bot Token</span>
                                        <input type="password" wire:model.defer="telegramBotToken" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 focus:border-slate-400 focus:outline-none sm:text-sm" placeholder="123456789:ABCdefGHIjklMNOpqrSTUvwxYZ">
                                        @error('telegramBotToken') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                    </label>
                                    <label class="block">
                                        <span class="text-xs font-semibold text-slate-500">Chat ID</span>
                                        <input type="text" wire:model.defer="telegramChatId" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 focus:border-slate-400 focus:outline-none sm:text-sm" placeholder="-100123456789">
                                        @error('telegramChatId') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                    </label>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-bold text-slate-900">Rapor bölümleri</h3>
                            <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-mono text-slate-500">{{ count($selectedSections) }} aktif</span>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach($sections as $key => $section)
                                <label class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-4 transition hover:bg-white">
                                    <div class="flex items-start gap-3">
                                        <input type="checkbox" value="{{ $key }}" wire:model.live="selectedSections" class="mt-1 h-5 w-5 rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                                        <span class="min-w-0">
                                            <span class="block text-sm font-semibold text-slate-900">{{ $section['label'] }}</span>
                                            <span class="mt-1 block text-xs leading-5 text-slate-500">{{ $section['description'] }}</span>
                                        </span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        @error('selectedSections') <span class="block text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>
                </form>
            </section>

            <aside class="space-y-4">
                <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">Mail ön izlemesi</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $preview['period']['label'] ?? 'Dönem hazırlanıyor' }}</p>
                        </div>
                        <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-mono text-slate-500">{{ $frequencies[$frequency]['label'] ?? 'Rapor' }}</span>
                    </div>

                    @if(! ($preview['available'] ?? false))
                        <div class="mt-4 rounded-[8px] border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                            {{ $preview['message'] ?? 'Ön izleme hazırlanamadı.' }}
                        </div>
                    @else
                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                                <p class="text-xs text-slate-500">Ciro</p>
                                <p class="mt-1 truncate text-sm font-bold text-slate-900">{{ $this->formatMoney($summary['gross_revenue'] ?? 0) }}</p>
                            </div>
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                                <p class="text-xs text-slate-500">Kâr</p>
                                <p class="mt-1 truncate text-sm font-bold text-emerald-700">{{ $this->formatMoney($summary['profit_value'] ?? 0) }}</p>
                            </div>
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                                <p class="text-xs text-slate-500">Marj</p>
                                <p class="mt-1 text-sm font-bold text-slate-900">{{ $this->formatPercent($summary['profit_margin_percent'] ?? 0) }}</p>
                            </div>
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                                <p class="text-xs text-slate-500">Açık risk</p>
                                <p class="mt-1 text-sm font-bold text-rose-700">{{ $this->formatNumber($summary['risk_open_count'] ?? 0) }}</p>
                            </div>
                        </div>

                        <div class="mt-4 rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-xs font-semibold text-slate-500">Risk skoru</span>
                                <span class="text-sm font-bold text-slate-900">{{ $risk['risk_score_label'] ?? 'Hazır' }}</span>
                            </div>
                            <div class="mt-3 h-2 rounded-full bg-slate-200">
                                <div class="h-2 rounded-full bg-slate-900" style="width: {{ max(0, min(100, (float) ($risk['risk_score'] ?? 0))) }}%"></div>
                            </div>
                            <p class="mt-2 text-xs text-slate-500">{{ $this->formatMoney($summary['risk_impact_total'] ?? 0) }} finansal risk baskısı.</p>
                        </div>

                        <div class="mt-4 rounded-[8px] border border-slate-200 bg-white p-4">
                            <p class="text-xs font-semibold text-slate-500">Kampanya fırsatı</p>
                            <p class="mt-2 text-lg font-bold text-emerald-700">{{ $this->formatMoney($campaign['potential_profit'] ?? 0) }}</p>
                            <p class="mt-1 text-xs text-slate-500">Risk maruziyeti {{ $this->formatMoney($campaign['risk_exposure'] ?? 0) }}</p>
                        </div>
                    @endif
                </section>

                <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-5">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-lg font-bold text-slate-900">Son gönderimler</h2>
                        <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-mono text-slate-500">{{ $this->recentRuns->count() }} kayıt</span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse($this->recentRuns as $run)
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $run->recipient_email }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $run->period_start?->format('d.m.Y') }} - {{ $run->period_end?->format('d.m.Y') }}</p>
                                    </div>
                                    <span class="rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ $this->statusTone($run->status) }}">
                                        {{ $this->statusLabel($run->status) }}
                                    </span>
                                </div>
                                <p class="mt-2 text-xs text-slate-500">{{ $run->sent_at ? $run->sent_at->format('d.m.Y H:i') : $run->created_at->format('d.m.Y H:i') }}</p>
                            </div>
                        @empty
                            <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50/70 p-4 text-sm text-slate-500">
                                Henüz otomatik rapor gönderimi yok.
                            </div>
                        @endforelse
                    </div>
                </section>
            </aside>
        </div>
    </div>
</div>
