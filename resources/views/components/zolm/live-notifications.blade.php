@php
    $notificationsAvailable = app(\App\Services\NotificationCenterService::class)->isAvailable();
    $notificationStreamEnabled = $notificationsAvailable && PHP_SAPI !== 'cli-server';
@endphp

@auth
    <div class="relative"
         x-data="zolmNotifications({
            available: @js($notificationsAvailable),
            streamEnabled: @js($notificationStreamEnabled),
            feedUrl: @js(route('notifications.feed')),
            streamUrl: @js(route('notifications.stream')),
            readUrl: @js(route('notifications.read', ['notification' => '__ID__'])),
            readAllUrl: @js(route('notifications.read-all')),
            preferencesUrl: @js(route('notifications.preferences')),
            csrfToken: @js(csrf_token())
         })"
         x-init="init()"
         @keydown.escape.window="open = false"
         @click.outside="open = false">
        <button type="button"
                @click="togglePanel()"
                class="relative inline-flex h-10 w-10 items-center justify-center rounded-[8px] border border-slate-200 bg-white text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900"
                :class="{ 'zolm-bell-ringing border-slate-900 text-slate-900': ringing }"
                aria-label="Bildirimler"
                :aria-expanded="open.toString()">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0a3 3 0 01-6 0m6 0H9" />
            </svg>
            <span x-show="unreadCount > 0"
                  x-cloak
                  x-text="unreadCount > 99 ? '99+' : unreadCount"
                  class="absolute -right-1.5 -top-1.5 inline-flex min-w-[18px] items-center justify-center rounded-full border border-white bg-rose-600 px-1 text-[10px] font-semibold leading-[18px] text-white shadow-sm"></span>
            <span x-show="connected"
                  x-cloak
                  class="absolute bottom-1 right-1 h-2 w-2 rounded-full border border-white bg-emerald-500"></span>
        </button>

        <div x-show="open"
             x-cloak
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 translate-y-1 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 translate-y-1 scale-95"
             class="absolute right-0 top-12 z-50 w-[calc(100vw-2rem)] max-w-[26rem] overflow-hidden rounded-[10px] border border-slate-200 bg-white text-left shadow-xl sm:w-[26rem]">
            <div class="border-b border-slate-200 bg-white px-4 py-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-semibold text-slate-900">Bildirimler</p>
                            <span class="inline-flex items-center gap-1 rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-medium text-slate-500">
                                <span class="h-1.5 w-1.5 rounded-full" :class="connected ? 'bg-emerald-500' : 'bg-slate-300'"></span>
                                Canlı
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500" x-text="unreadCount > 0 ? unreadCount + ' okunmamış kayıt' : 'Okunmamış kayıt yok'"></p>
                    </div>

                    <button type="button"
                            @click="toggleSound()"
                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[6px] border transition"
                            :class="soundEnabled ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'"
                            :title="soundEnabled ? 'Bildirim sesini kapat' : 'Bildirim sesini aç'"
                            aria-label="Bildirim sesi">
                        <svg x-show="soundEnabled" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M15.536 8.464a5 5 0 010 7.072M18.364 5.636a9 9 0 010 12.728M9 9H5v6h4l5 4V5L9 9z" />
                        </svg>
                        <svg x-show="!soundEnabled" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M5.586 15H5a2 2 0 01-2-2v-2a2 2 0 012-2h2l4-4v14l-2.293-2.293M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
                        </svg>
                    </button>
                </div>

                <div class="mt-3 flex gap-1 overflow-x-auto pb-0.5">
                    <template x-for="item in filters" :key="item.key">
                        <button type="button"
                                @click="filter = item.key"
                                class="shrink-0 rounded-[6px] border px-2.5 py-1.5 text-xs font-medium transition"
                                :class="filter === item.key ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                                x-text="item.label"></button>
                    </template>
                </div>
            </div>

            <div class="max-h-[min(32rem,calc(100vh-10rem))] overflow-y-auto bg-slate-50/60 p-2">
                <template x-if="loading">
                    <div class="space-y-2 p-2">
                        <div class="h-16 animate-pulse rounded-[8px] bg-white"></div>
                        <div class="h-16 animate-pulse rounded-[8px] bg-white"></div>
                        <div class="h-16 animate-pulse rounded-[8px] bg-white"></div>
                    </div>
                </template>

                <template x-if="!loading && filteredNotifications.length === 0">
                    <div class="rounded-[8px] border border-dashed border-slate-300 bg-white px-4 py-8 text-center">
                        <p class="text-sm font-medium text-slate-900">Bildirim yok</p>
                        <p class="mt-1 text-xs text-slate-500">Yeni sipariş, stok veya iade olayı burada görünecek.</p>
                    </div>
                </template>

                <div x-show="!loading && filteredNotifications.length > 0" class="space-y-1.5">
                    <template x-for="notification in filteredNotifications" :key="notification.id">
                        <button type="button"
                                @click="openNotification(notification)"
                                class="group w-full rounded-[8px] border bg-white px-3 py-3 text-left shadow-sm transition hover:border-slate-300 hover:bg-white"
                                :class="notification.unread ? 'border-slate-300' : 'border-slate-200 opacity-80'">
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-[8px]"
                                      :class="toneClasses(notification.tone).icon">
                                    <span class="h-2.5 w-2.5 rounded-full" :class="toneClasses(notification.tone).dot"></span>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-start justify-between gap-2">
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-semibold text-slate-900" x-text="notification.title"></span>
                                            <span class="mt-0.5 block line-clamp-2 text-xs leading-5 text-slate-500" x-text="notification.body || notification.store_name || ''"></span>
                                        </span>
                                        <span x-show="notification.unread" class="mt-1 h-2 w-2 shrink-0 rounded-full bg-slate-900"></span>
                                    </span>
                                    <span class="mt-2 flex min-w-0 items-center justify-between gap-2">
                                        <span class="truncate text-[11px] font-medium text-slate-400" x-text="notificationMeta(notification)"></span>
                                        <span class="shrink-0 text-[11px] text-slate-400" x-text="notification.created_at_label"></span>
                                    </span>
                                </span>
                            </div>
                        </button>
                    </template>
                </div>
            </div>

            <div class="flex items-center justify-between gap-3 border-t border-slate-200 bg-white px-4 py-3">
                <button type="button"
                        @click="markAllRead()"
                        class="text-xs font-medium text-slate-500 transition hover:text-slate-900"
                        :disabled="unreadCount === 0"
                        :class="{ 'opacity-50': unreadCount === 0 }">
                    Tümünü okundu yap
                </button>
                <button type="button"
                        @click="refreshFeed()"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-900"
                        title="Yenile"
                        aria-label="Yenile">
                    <svg class="h-4 w-4" :class="{ 'animate-spin': loading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0A8.003 8.003 0 018.064 13m11.355 2H15" />
                    </svg>
                </button>
            </div>
        </div>

        <div x-show="toast"
             x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-2 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 translate-y-2 scale-95"
             class="fixed right-4 top-20 z-[70] w-[calc(100vw-2rem)] max-w-sm rounded-[10px] border bg-white p-4 shadow-xl sm:right-6">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-[8px]"
                      :class="toast ? toneClasses(toast.tone).icon : ''">
                    <span class="h-2.5 w-2.5 rounded-full" :class="toast ? toneClasses(toast.tone).dot : ''"></span>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-slate-900" x-text="toast?.title"></p>
                    <p class="mt-1 line-clamp-2 text-sm leading-5 text-slate-600" x-text="toast?.body"></p>
                </div>
                <button type="button"
                        @click="toast = null"
                        class="rounded-[6px] p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                        aria-label="Bildirimi kapat">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
@endauth

@once
    <style>
        @keyframes zolm-bell-ring {
            0%, 100% { transform: rotate(0deg); }
            12% { transform: rotate(12deg); }
            24% { transform: rotate(-10deg); }
            36% { transform: rotate(8deg); }
            48% { transform: rotate(-6deg); }
            60% { transform: rotate(4deg); }
        }

        .zolm-bell-ringing svg {
            animation: zolm-bell-ring 0.75s ease both;
            transform-origin: 50% 8%;
        }
    </style>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('zolmNotifications', (config) => ({
                available: Boolean(config.available ?? true),
                streamEnabled: Boolean(config.streamEnabled ?? true),
                open: false,
                loading: true,
                connected: false,
                ringing: false,
                soundEnabled: false,
                notifications: [],
                unreadCount: 0,
                latestId: 0,
                filter: 'all',
                toast: null,
                toastTimer: null,
                source: null,
                fallbackTimer: null,
                reconnectTimer: null,
                audioContext: null,
                filters: [
                    { key: 'all', label: 'Tümü' },
                    { key: 'critical', label: 'Kritik' },
                    { key: 'orders', label: 'Sipariş' },
                    { key: 'stock', label: 'Stok' },
                    { key: 'questions', label: 'Sorular' },
                    { key: 'returns', label: 'İade' },
                ],
                get filteredNotifications() {
                    if (this.filter === 'critical') {
                        return this.notifications.filter((item) => ['critical', 'warning'].includes(item.severity));
                    }

                    if (this.filter === 'orders') {
                        return this.notifications.filter((item) => ['new_order', 'order_cancelled'].includes(item.type));
                    }

                    if (this.filter === 'stock') {
                        return this.notifications.filter((item) => ['stock_out', 'stock_critical'].includes(item.type));
                    }

                    if (this.filter === 'questions') {
                        return this.notifications.filter((item) => item.type === 'question_received');
                    }

                    if (this.filter === 'returns') {
                        return this.notifications.filter((item) => item.type === 'order_returned');
                    }

                    return this.notifications;
                },
                init() {
                    if (!this.available) {
                        this.loading = false;
                        return;
                    }

                    this.refreshFeed().then(() => {
                        if (this.available) {
                            this.streamEnabled ? this.startStream() : this.startPolling();
                        }
                    });

                    document.addEventListener('visibilitychange', () => {
                        if (!document.hidden && this.available) {
                            this.refreshFeed();
                        }
                    });
                },
                togglePanel() {
                    this.open = !this.open;

                    if (this.open && this.available) {
                        this.refreshFeed();
                    }
                },
                async refreshFeed() {
                    this.loading = true;

                    try {
                        const response = await fetch(config.feedUrl, {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin',
                        });

                        if (!response.ok) {
                            throw new Error('Request failed');
                        }

                        const payload = await response.json();
                        this.available = Boolean(payload.available ?? this.available);
                        this.streamEnabled = Boolean(payload.stream_enabled ?? this.streamEnabled);

                        if (!this.available) {
                            this.notifications = [];
                            this.unreadCount = 0;
                            this.latestId = 0;
                            this.soundEnabled = false;
                            this.closeStream();
                            return;
                        }

                        this.notifications = Array.isArray(payload.notifications) ? payload.notifications : [];
                        this.unreadCount = Number(payload.unread_count || 0);
                        this.latestId = Number(payload.latest_id || this.notifications[0]?.id || 0);
                        this.soundEnabled = Boolean(payload.preferences?.sound_enabled);
                    } catch (error) {
                        this.connected = false;
                    } finally {
                        this.loading = false;
                    }
                },
                startPolling() {
                    this.closeStream();
                    this.connected = false;

                    if (this.fallbackTimer) {
                        window.clearInterval(this.fallbackTimer);
                    }

                    this.fallbackTimer = window.setInterval(() => {
                        if (!document.hidden && this.available) {
                            this.refreshFeed();
                        }
                    }, 15000);
                },
                startStream() {
                    if (!this.streamEnabled || !window.EventSource) {
                        this.startPolling();
                        return;
                    }

                    this.closeStream();

                    const streamUrl = new URL(config.streamUrl, window.location.origin);
                    streamUrl.searchParams.set('last_id', this.latestId);

                    this.source = new EventSource(streamUrl.toString());

                    this.source.addEventListener('connected', (event) => {
                        this.connected = true;
                        this.mergeStreamMeta(event);
                    });

                    this.source.addEventListener('heartbeat', (event) => {
                        this.connected = true;
                        this.mergeStreamMeta(event);
                    });

                    this.source.addEventListener('notification', (event) => {
                        this.connected = true;
                        const payload = JSON.parse(event.data || '{}');
                        this.mergeStreamMeta(event, payload);

                        if (payload.notification) {
                            this.pushNotification(payload.notification);
                        }
                    });

                    this.source.onerror = () => {
                        this.connected = false;
                        this.closeStream();
                        window.clearTimeout(this.reconnectTimer);
                        this.reconnectTimer = window.setTimeout(() => this.startStream(), 3500);
                    };
                },
                closeStream() {
                    if (this.source) {
                        this.source.close();
                        this.source = null;
                    }

                    if (this.fallbackTimer) {
                        window.clearInterval(this.fallbackTimer);
                        this.fallbackTimer = null;
                    }
                },
                mergeStreamMeta(event, parsed = null) {
                    const payload = parsed || JSON.parse(event.data || '{}');

                    if (payload.latest_id !== undefined) {
                        this.latestId = Math.max(this.latestId, Number(payload.latest_id || 0));
                    }

                    if (payload.unread_count !== undefined) {
                        this.unreadCount = Number(payload.unread_count || 0);
                    }
                },
                pushNotification(notification) {
                    const exists = this.notifications.some((item) => Number(item.id) === Number(notification.id));

                    if (!exists) {
                        this.notifications.unshift(notification);
                        this.notifications = this.notifications.slice(0, 35);
                    }

                    this.triggerArrival(notification);
                },
                triggerArrival(notification) {
                    this.ringing = true;
                    window.setTimeout(() => this.ringing = false, 900);

                    this.toast = notification;
                    window.clearTimeout(this.toastTimer);
                    this.toastTimer = window.setTimeout(() => this.toast = null, 5200);

                    if (this.soundEnabled) {
                        this.playBeep(notification.tone);
                    }
                },
                async toggleSound() {
                    const next = !this.soundEnabled;
                    this.soundEnabled = next;

                    if (next) {
                        this.playBeep('info', true);
                    }

                    try {
                        const response = await this.postJson(config.preferencesUrl, {
                            sound_enabled: next,
                        });

                        this.soundEnabled = Boolean(response.preferences?.sound_enabled);
                    } catch (error) {
                        this.soundEnabled = !next;
                    }
                },
                async openNotification(notification) {
                    if (notification.unread) {
                        notification.unread = false;
                        this.unreadCount = Math.max(0, this.unreadCount - 1);

                        try {
                            await this.postJson(config.readUrl.replace('__ID__', notification.id), {});
                        } catch (error) {
                            this.refreshFeed();
                        }
                    }

                    if (notification.action_url) {
                        window.location.assign(notification.action_url);
                    }
                },
                async markAllRead() {
                    if (this.unreadCount === 0) {
                        return;
                    }

                    this.notifications = this.notifications.map((item) => ({ ...item, unread: false }));
                    this.unreadCount = 0;

                    try {
                        await this.postJson(config.readAllUrl, {});
                    } catch (error) {
                        this.refreshFeed();
                    }
                },
                async postJson(url, body) {
                    const response = await fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': config.csrfToken,
                        },
                        body: JSON.stringify(body),
                    });

                    if (!response.ok) {
                        throw new Error('Request failed');
                    }

                    return response.json();
                },
                playBeep(tone = 'info', preview = false) {
                    try {
                        const AudioContext = window.AudioContext || window.webkitAudioContext;
                        this.audioContext = this.audioContext || new AudioContext();

                        if (this.audioContext.state === 'suspended') {
                            this.audioContext.resume();
                        }

                        const oscillator = this.audioContext.createOscillator();
                        const gain = this.audioContext.createGain();
                        const now = this.audioContext.currentTime;
                        const frequency = tone === 'danger' ? 880 : (tone === 'warning' ? 720 : 640);

                        oscillator.type = 'sine';
                        oscillator.frequency.setValueAtTime(frequency, now);
                        gain.gain.setValueAtTime(0.0001, now);
                        gain.gain.exponentialRampToValueAtTime(preview ? 0.08 : 0.055, now + 0.018);
                        gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.18);

                        oscillator.connect(gain);
                        gain.connect(this.audioContext.destination);
                        oscillator.start(now);
                        oscillator.stop(now + 0.2);
                    } catch (error) {}
                },
                toneClasses(tone) {
                    if (tone === 'danger') {
                        return { icon: 'bg-rose-50 text-rose-700', dot: 'bg-rose-600' };
                    }

                    if (tone === 'warning') {
                        return { icon: 'bg-amber-50 text-amber-700', dot: 'bg-amber-500' };
                    }

                    if (tone === 'success') {
                        return { icon: 'bg-emerald-50 text-emerald-700', dot: 'bg-emerald-500' };
                    }

                    return { icon: 'bg-sky-50 text-sky-700', dot: 'bg-sky-500' };
                },
                notificationMeta(notification) {
                    if (notification.context_label) {
                        return notification.context_label;
                    }

                    return [notification.store_name, notification.marketplace_label, notification.type_label]
                        .filter((value, index, items) => value && items.indexOf(value) === index)
                        .join(' · ');
                },
            }));
        });
    </script>
@endonce
