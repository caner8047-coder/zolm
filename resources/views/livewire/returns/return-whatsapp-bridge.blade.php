<div class="{{ $embedded ? 'flex w-full flex-col space-y-4 lg:space-y-6' : 'mx-auto flex w-full max-w-[1600px] flex-col space-y-4 lg:space-y-6' }}">
    <x-zolm.section-card
        variant="orders"
        eyebrow="Araçlar"
        title="WhatsApp İade Köprüsü"
        description="WhatsApp Business numarasından gelen iade fotoğraflarını ZOLM içine al, eşleştir ve tek ekrandan takip et."
        body-class="space-y-4"
    >
        <div class="rounded-[8px] border border-sky-200 bg-sky-50/70 p-4 text-sm text-sky-900">
            Kullanıcının <code>.env</code> dosyasına erişmesi gerekmiyor. Bu sayfaya yazdığın bilgiler veritabanına güvenli şekilde kaydedilir. <code>.env</code> sadece teknik fallback olarak kalır.
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1.2fr)_380px] lg:gap-6">
            <div class="rounded-[10px] border border-slate-200 bg-slate-50/60 p-4 lg:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">1 dakikada bağla</h2>
                        <p class="mt-1 text-sm text-slate-500">Meta panelindeki bilgileri buraya yapıştır, kaydet ve webhook’u aktif et.</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600">
                        Kurulum: <span class="font-semibold text-slate-900">{{ $completedChecks }}/{{ count($setupChecks) }}</span>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                    <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">1. Meta App</p>
                        <p class="mt-2 text-sm text-slate-700">Meta Developers içinde WhatsApp ürünü ekle ve bir Business numarası bağla.</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">2. Webhook</p>
                        <p class="mt-2 text-sm text-slate-700">Callback URL olarak sağdaki adresi gir. Subscribe alanında en az <code>messages</code> açık olsun.</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">3. Tokenlar</p>
                        <p class="mt-2 text-sm text-slate-700">Verify token, permanent access token ve istersen app secret bilgisini aşağıya yapıştır.</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">4. Test</p>
                        <p class="mt-2 text-sm text-slate-700">Depocu fotoğrafı Business numaraya atsın. Burada thread oluşur, ardından aynı İade Merkezi workspace’inde takip edilir.</p>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">Köprü durumu</label>
                        <label class="flex min-h-[44px] items-center gap-3 rounded-[8px] border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                            <input type="checkbox" wire:model.live="settingsForm.enabled" class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                            WhatsApp’tan gelen mesajları almaya başla
                        </label>
                        @error('settingsForm.enabled') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">Sistem kullanıcısı</label>
                        <select wire:model.live="settingsForm.system_user_id" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:text-sm">
                            <option value="">Kullanıcı seç</option>
                            @foreach($systemUsers as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-xs text-slate-500">WhatsApp’tan gelen kayıtlar sistemde bu kullanıcı üzerinden açılır.</p>
                        @error('settingsForm.system_user_id') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">Verify token</label>
                        <input
                            type="text"
                            wire:model="settingsForm.verify_token"
                            class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:text-sm"
                            placeholder="Meta webhook verify token"
                        >
                        @error('settingsForm.verify_token') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">Graph sürümü</label>
                        <input
                            type="text"
                            wire:model="settingsForm.graph_version"
                            class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:text-sm"
                            placeholder="v23.0"
                        >
                        @error('settingsForm.graph_version') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-4">
                    <label class="mb-2 block text-sm font-medium text-slate-700">Access token</label>
                    <textarea
                        wire:model="settingsForm.access_token"
                        rows="3"
                        class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:text-sm"
                        placeholder="Meta permanent access token"
                    ></textarea>
                    @error('settingsForm.access_token') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">App secret</label>
                        <input
                            type="password"
                            wire:model="settingsForm.app_secret"
                            class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:text-sm"
                            placeholder="Opsiyonel ama önerilir"
                        >
                        @error('settingsForm.app_secret') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">Aynı oturum penceresi</label>
                        <input
                            type="number"
                            min="1"
                            max="120"
                            wire:model="settingsForm.message_window_minutes"
                            class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:text-sm"
                        >
                        <p class="mt-2 text-xs text-slate-500">Aynı depocu tarafından art arda gelen mesajlar bu süre içinde tek thread altında toplanır.</p>
                        @error('settingsForm.message_window_minutes') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-5 flex flex-col gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-sm text-slate-500">
                        Şu an <span class="font-medium text-slate-900">{{ $bridgeConfig['source'] === 'database' ? 'panel ayarları' : '.env fallback' }}</span> kullanılıyor.
                    </div>
                    @if(auth()->user()->isManager())
                        <x-zolm.primary-button wire:click="saveBridgeSettings" wire:loading.attr="disabled" wire:target="saveBridgeSettings">
                            <span wire:loading.remove wire:target="saveBridgeSettings">Ayarları kaydet</span>
                            <span wire:loading wire:target="saveBridgeSettings">Kaydediliyor...</span>
                        </x-zolm.primary-button>
                    @else
                        <div class="rounded-[6px] border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700">
                            Ayarları sadece yönetici veya müdür kaydedebilir.
                        </div>
                    @endif
                </div>
            </div>

            <div class="space-y-4">
                <div class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 class="text-base font-semibold text-slate-900">Meta paneline gireceğin bilgiler</h2>
                    <div class="mt-4 space-y-3">
                        <div>
                            <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Callback URL</label>
                            <input readonly onclick="this.select()" value="{{ $bridgeConfig['receive_url'] }}" class="w-full rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-900 focus:outline-none">
                        </div>
                        <div>
                            <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Verify token</label>
                            <input readonly onclick="this.select()" value="{{ $settingsForm['verify_token'] }}" class="w-full rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-900 focus:outline-none">
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3 text-sm text-slate-700">
                            Meta tarafında aynı callback URL hem doğrulama hem mesaj alma için kullanılır. Subscribe alanında en az <code>messages</code> seçili olsun.
                        </div>
                    </div>
                </div>

                <div class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-semibold text-slate-900">Bağlantı durumu</h2>
                        <x-zolm.status-badge :tone="$bridgeConfig['is_ready'] ? 'success' : 'warning'" size="sm">
                            {{ $bridgeConfig['is_ready'] ? 'Hazır' : 'Kurulum eksik' }}
                        </x-zolm.status-badge>
                    </div>
                    <div class="mt-4 space-y-2">
                        @foreach($setupChecks as $check)
                            <div class="flex items-center justify-between gap-3 rounded-[8px] border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm">
                                <span class="text-slate-700">{{ $check['label'] }}</span>
                                <span class="font-medium {{ $check['done'] ? 'text-emerald-700' : 'text-amber-700' }}">
                                    {{ $check['done'] ? 'Tamam' : 'Eksik' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4 rounded-[8px] border border-sky-200 bg-sky-50/70 p-3 text-sm text-sky-900">
                        En stabil kullanım: depocu mevcut gruba değil, doğrudan sizin WhatsApp Business numaranıza fotoğraf atsın.
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-[8px] border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Açık thread</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($bridgeKpis['collecting'] + $bridgeKpis['queued']) }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Bugün mesaj</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($bridgeKpis['todayMessages']) }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Toplam thread</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($bridgeKpis['threads']) }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Tamamlanan</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($bridgeKpis['completed']) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </x-zolm.section-card>

    @if($message !== '')
        <div class="rounded-[10px] border p-4 text-sm {{ $messageType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800' }}">
            {{ $message }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1.25fr)_420px] lg:gap-6">
        <x-zolm.section-card
            title="Gelen oturumlar"
            description="WhatsApp’tan gelen iade mesajlarını burada filtreleyip açabilirsin."
            body-class="space-y-4"
        >
            <section class="rounded-[10px] border border-slate-200 bg-white">
                <div class="border-b border-slate-200 bg-slate-50/50 p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                        <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="relative">
                                <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="searchQuery"
                                    class="w-full rounded-[6px] border border-slate-200 bg-white py-3 pl-9 pr-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:text-sm"
                                    placeholder="Telefon, isim, takip no..."
                                >
                            </div>
                            <select wire:model.live="statusFilter" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:text-sm">
                                <option value="all">Tüm thread durumları</option>
                                @foreach(\App\Models\ReturnWhatsappThread::STATUS_LABELS as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="text-sm text-slate-500">
                            Toplam <span class="font-semibold text-slate-900">{{ $threads->total() }}</span> thread
                        </div>
                    </div>
                </div>

                <div class="hidden overflow-x-auto xl:block">
                    <table class="w-full table-fixed text-left text-sm text-slate-600">
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Gönderen</th>
                                <th class="px-4 py-3">Durum</th>
                                <th class="px-4 py-3">Mesajlar</th>
                                <th class="px-4 py-3">Bağlı iade</th>
                                <th class="px-4 py-3">Son aktivite</th>
                                <th class="px-4 py-3 text-right">Detay</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($threads as $thread)
                                <tr class="{{ $selectedThread?->id === $thread->id ? 'bg-slate-50/70' : 'hover:bg-slate-50/50' }} transition">
                                    <td class="px-4 py-3">
                                        <p class="truncate font-medium text-slate-900">{{ $thread->sender_name ?: 'İsimsiz' }}</p>
                                        <p class="mt-1 truncate text-xs text-slate-500">{{ $thread->sender_phone ?: '-' }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-zolm.status-badge
                                            :tone="match ($thread->status) {
                                                'collecting' => 'warning',
                                                'queued' => 'info',
                                                'completed' => 'success',
                                                default => 'default',
                                            }"
                                            size="sm"
                                        >
                                            {{ $thread->statusLabel() }}
                                        </x-zolm.status-badge>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-slate-900">{{ $thread->messages->count() }} mesaj</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $thread->messages->whereNotNull('return_intake_media_id')->count() }} medya import</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="truncate font-medium text-slate-900">{{ $thread->intakeItem?->detected_tracking_number ?: ($thread->intakeItem?->manual_reference ?: ('INTAKE-' . ($thread->intakeItem?->id ?? '-'))) }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $thread->intakeItem?->statusLabel() ?: 'Henüz oluşmadı' }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-slate-900">{{ optional($thread->last_message_at)->format('d.m.Y') ?: '-' }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ optional($thread->last_message_at)->format('H:i') ?: '-' }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button wire:click="selectThread({{ $thread->id }})" class="inline-flex items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                            İncele
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-12 text-center text-sm text-slate-500">Henüz WhatsApp thread kaydı yok.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-slate-100 xl:hidden">
                    @forelse($threads as $thread)
                        <div class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $thread->sender_name ?: 'İsimsiz' }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ $thread->sender_phone ?: '-' }}</p>
                                </div>
                                <x-zolm.status-badge
                                    :tone="match ($thread->status) {
                                        'collecting' => 'warning',
                                        'queued' => 'info',
                                        'completed' => 'success',
                                        default => 'default',
                                    }"
                                    size="sm"
                                >
                                    {{ $thread->statusLabel() }}
                                </x-zolm.status-badge>
                            </div>
                            <div class="mt-3 flex flex-col gap-1 text-xs text-slate-500">
                                <span>{{ $thread->messages->count() }} mesaj</span>
                                <span>{{ optional($thread->last_message_at)->format('d.m.Y H:i') ?: '-' }}</span>
                            </div>
                            <button wire:click="selectThread({{ $thread->id }})" class="mt-4 w-full rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                Detayı aç
                            </button>
                        </div>
                    @empty
                        <div class="p-8 text-center text-sm text-slate-500">Henüz WhatsApp thread kaydı yok.</div>
                    @endforelse
                </div>

                @if($threads->hasPages())
                    <div class="border-t border-slate-200 bg-slate-50/40 p-4">
                        {{ $threads->links('vendor.pagination.tailwind') }}
                    </div>
                @endif
            </section>
        </x-zolm.section-card>

        <x-zolm.section-card
            title="{{ $selectedThread ? 'Thread detayı' : 'Detay paneli' }}"
            description="{{ $selectedThread ? 'Mesajları, import edilen medyayı ve bağlı iade kaydını buradan yönet.' : 'Listeden bir thread seçtiğinde detaylar burada açılır.' }}"
            body-class="space-y-4"
        >
            @if($selectedThread)
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Durum</p>
                        <p class="mt-2 text-sm font-medium text-slate-900">{{ $selectedThread->statusLabel() }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">İade tipi</p>
                        <p class="mt-2 text-sm font-medium text-slate-900">{{ $selectedThread->intake_type === 'damaged' ? 'Hasarlı iade' : 'Hasarsız iade' }}</p>
                    </div>
                </div>

                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Bağlı intake</p>
                    @if($selectedThread->intakeItem)
                        <div class="mt-3 space-y-3">
                            <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                                <p class="font-medium text-slate-900">{{ $selectedThread->intakeItem->statusLabel() }}</p>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ $selectedThread->intakeItem->detected_tracking_number ?: ($selectedThread->intakeItem->manual_reference ?: ('INTAKE-' . $selectedThread->intakeItem->id)) }}
                                </p>
                                <a href="{{ route('returns.workspace', ['item' => $selectedThread->intakeItem->id, 'thread' => $selectedThread->id, 'tab' => 'havuz']) }}" class="mt-3 inline-flex text-sm font-medium text-slate-900 underline underline-offset-4">
                                    İade havuzunda aç
                                </a>
                            </div>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <button wire:click="dispatchAnalysisNow" class="rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800">
                                    Analizi tekrar çalıştır
                                </button>
                                @if($selectedThread->status !== 'completed')
                                    <button wire:click="markCompleted" class="rounded-[6px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 transition hover:bg-emerald-100">
                                        Tamamlandı işaretle
                                    </button>
                                @endif
                                @if($selectedThread->status !== 'collecting')
                                    <button wire:click="reopenThread" class="rounded-[6px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-700 transition hover:bg-amber-100">
                                        Thread’i yeniden aç
                                    </button>
                                @endif
                                @if($selectedThread->status !== 'archived')
                                    <button wire:click="archiveThread" class="rounded-[6px] border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-100">
                                        Arşive taşı
                                    </button>
                                @endif
                            </div>
                        </div>
                    @else
                        <p class="mt-3 text-sm text-slate-500">Bu thread için henüz intake kaydı oluşmamış.</p>
                    @endif
                </div>

                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Mesaj akışı</p>
                    <div class="mt-3 space-y-3">
                        @forelse($selectedThread->messages->sortByDesc('received_at') as $messageItem)
                            @php $mediaUrl = $messageItem->mediaUrl(); @endphp
                            <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-slate-900">{{ strtoupper($messageItem->message_type) }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ optional($messageItem->received_at)->format('d.m.Y H:i') ?: '-' }}</p>
                                    </div>
                                    @if($messageItem->return_intake_media_id)
                                        <x-zolm.status-badge tone="success" size="sm">İçe alındı</x-zolm.status-badge>
                                    @else
                                        <x-zolm.status-badge size="sm">Ham mesaj</x-zolm.status-badge>
                                    @endif
                                </div>
                                @if($messageItem->caption)
                                    <p class="mt-3 text-sm text-slate-700">{{ $messageItem->caption }}</p>
                                @endif
                                @if($messageItem->text_content)
                                    <p class="mt-3 text-sm text-slate-700">{{ $messageItem->text_content }}</p>
                                @endif
                                @if($mediaUrl)
                                    <a href="{{ $mediaUrl }}" target="_blank" class="mt-3 block rounded-[8px] border border-slate-200 bg-slate-50 p-1 transition hover:border-slate-300">
                                        <img src="{{ $messageItem->intakeMedia?->thumbnailUrl() ?: $mediaUrl }}" alt="WhatsApp medya" class="h-40 w-full rounded-[6px] object-cover">
                                    </a>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">Bu thread içinde henüz mesaj yok.</p>
                        @endforelse
                    </div>
                </div>
            @else
                <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50/70 p-6 text-sm text-slate-500">
                    Soldan bir WhatsApp thread seçtiğinde mesaj akışı ve aksiyonlar burada açılır.
                </div>
            @endif
        </x-zolm.section-card>
    </div>
</div>
