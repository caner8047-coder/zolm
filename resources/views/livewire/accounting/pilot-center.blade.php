@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    
    $severityBadge = fn ($sev) => match ($sev) {
        'low' => 'bg-slate-100 text-slate-700',
        'medium' => 'bg-blue-50 text-blue-700 border border-blue-100',
        'high' => 'bg-amber-50 text-amber-700 border border-amber-100',
        'critical' => 'bg-rose-50 text-rose-700 border border-rose-100',
        default => 'bg-slate-100 text-slate-700',
    };
    
    $severityLabel = fn ($sev) => match ($sev) {
        'low' => 'Düşük',
        'medium' => 'Orta',
        'high' => 'Yüksek',
        'critical' => 'Kritik',
        default => $sev,
    };

    $typeLabel = fn ($t) => match ($t) {
        'bug' => 'Hata (Bug)',
        'ux' => 'UX / Arayüz',
        'data' => 'Veri Tutarsızlığı',
        'question' => 'Soru',
        'risk' => 'Risk Bildirimi',
        default => $t,
    };
@endphp

<div class="w-full space-y-4 lg:space-y-6">
    <!-- Üst Başlık & Workspace Section -->
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                    Pilot Yönetimi
                </div>
                <h1 class="mt-3 text-xl font-semibold tracking-tight text-slate-950 lg:text-2xl">Pilot Operasyon Merkezi</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
                    Sistem sağlık taramalarını (Health Check) çalıştırın, kullanıcı geri bildirimlerini izleyin ve pilot risk Sicilini yönetin.
                </p>
            </div>
            <button wire:click="runHealthCheck" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-slate-800 sm:w-auto">
                ⚡ Health Check Çalıştır
            </button>
        </div>

        <!-- Üst KPI Kartları -->
        <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Health Score</p>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-slate-900">{{ $latestSnapshot ? $latestSnapshot->score : 'N/A' }}</span>
                    <span class="text-xs text-slate-400">/ 100</span>
                </div>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Açık Geri Bildirim</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ $summary['open'] }}</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Kritik Risk / High Bug</p>
                <p class="mt-2 text-2xl font-bold {{ $summary['critical'] > 0 ? 'text-rose-600' : 'text-slate-900' }}">
                    {{ $summary['critical'] }}
                </p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Son Tarama Zamanı</p>
                <p class="mt-2 text-sm font-semibold text-slate-900">
                    {{ $latestSnapshot ? $latestSnapshot->created_at->timezone('Europe/Istanbul')->format('H:i - d.m.Y') : 'Tarama Yapılmadı' }}
                </p>
            </div>
        </div>
    </section>

    <!-- Bilgi Mesajı -->
    @if($message !== '')
        <div class="rounded-[8px] border px-4 py-3 text-sm {{ $messageType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800' }}">
            {{ $message }}
        </div>
    @endif

    <!-- Sekmeler ve Ana Kontrol Paneli -->
    <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-100 bg-slate-50/50 p-1">
            <div class="flex flex-wrap gap-1">
                <button wire:click="$set('activeTab', 'health')" class="min-h-[44px] px-4 py-2 text-xs font-semibold rounded-[6px] transition {{ $activeTab === 'health' ? 'bg-white text-slate-900 shadow-sm border border-slate-200/50' : 'text-slate-600 hover:text-slate-900' }}">
                    🔍 Health Check
                </button>
                <button wire:click="$set('activeTab', 'uat')" class="min-h-[44px] px-4 py-2 text-xs font-semibold rounded-[6px] transition {{ $activeTab === 'uat' ? 'bg-white text-slate-900 shadow-sm border border-slate-200/50' : 'text-slate-600 hover:text-slate-900' }}">
                    📋 UAT Checklist
                </button>
                <button wire:click="$set('activeTab', 'feedback')" class="min-h-[44px] px-4 py-2 text-xs font-semibold rounded-[6px] transition {{ $activeTab === 'feedback' ? 'bg-white text-slate-900 shadow-sm border border-slate-200/50' : 'text-slate-600 hover:text-slate-900' }}">
                    💬 Geri Bildirimler
                </button>
                <button wire:click="$set('activeTab', 'risks')" class="min-h-[44px] px-4 py-2 text-xs font-semibold rounded-[6px] transition {{ $activeTab === 'risks' ? 'bg-white text-slate-900 shadow-sm border border-slate-200/50' : 'text-slate-600 hover:text-slate-900' }}">
                    ⚠️ Risk Register
                </button>
            </div>
        </div>

        <!-- 1. HEALTH CHECK SEKME İÇERİĞİ -->
        @if($activeTab === 'health')
            <div class="p-4 lg:p-6 space-y-4">
                <h2 class="text-sm font-semibold text-slate-900">Health Check Raporu</h2>
                @if(!$latestSnapshot)
                    <div class="p-6 text-center text-sm text-slate-500 bg-slate-50 rounded-[8px] border border-dashed border-slate-200">
                        Henüz sağlık taraması çalıştırılmamış. Sağlık durumunu kontrol etmek için yukarıdaki butona tıklayın.
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        @foreach($latestSnapshot->checks_json as $key => $check)
                            <div class="flex items-start justify-between rounded-[8px] border border-slate-200 bg-white p-4 shadow-sm">
                                <div class="space-y-1">
                                    <h3 class="text-xs font-semibold text-slate-900">{{ $check['title'] }}</h3>
                                    <p class="text-xs text-slate-500">{{ $check['message'] }}</p>
                                </div>
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded uppercase tracking-wider
                                    {{ $check['status'] === 'passed' ? 'bg-emerald-50 text-emerald-700' : '' }}
                                    {{ $check['status'] === 'failed' ? 'bg-rose-50 text-rose-700' : '' }}
                                    {{ $check['status'] === 'warning' ? 'bg-amber-50 text-amber-700' : '' }}
                                ">
                                    {{ $check['status'] === 'passed' ? 'Tamam' : '' }}
                                    {{ $check['status'] === 'failed' ? 'Hata' : '' }}
                                    {{ $check['status'] === 'warning' ? 'Uyarı' : '' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        <!-- 2. UAT CHECKLIST SEKME İÇERİĞİ -->
        @if($activeTab === 'uat')
            <div class="p-4 lg:p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-900">Kullanıcı Kabul Senaryoları (UAT Checklist)</h2>
                    <a href="/docs/accounting-user-acceptance-scenarios.md" target="_blank" class="text-xs text-slate-500 hover:text-slate-900">UAT Senaryo Kılavuzunu Gör ↗</a>
                </div>
                <div class="space-y-2">
                    <div class="flex items-center justify-between p-3 rounded-[6px] border border-slate-200 bg-slate-50/50">
                        <span class="text-xs font-medium text-slate-800">Senaryo 1: İlk Kurulum ve Dashboard Kontrolü</span>
                        <span class="text-[10px] bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded font-bold uppercase">Hazır</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-[6px] border border-slate-200 bg-slate-50/50">
                        <span class="text-xs font-medium text-slate-800">Senaryo 2: Cari Kart Oluşturma ve Açık Hesap İzleme</span>
                        <span class="text-[10px] bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded font-bold uppercase">Hazır</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-[6px] border border-slate-200 bg-slate-50/50">
                        <span class="text-xs font-medium text-slate-800">Senaryo 3: Satış Siparişi ve Yevmiye Etkisi</span>
                        <span class="text-[10px] bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded font-bold uppercase">Hazır</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-[6px] border border-slate-200 bg-slate-50/50">
                        <span class="text-xs font-medium text-slate-800">Senaryo 4: Satın Alma ve Stok Girişi</span>
                        <span class="text-[10px] bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded font-bold uppercase">Hazır</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-[6px] border border-slate-200 bg-slate-50/50">
                        <span class="text-xs font-medium text-slate-800">Senaryo 5: Depo ve Stok Hareketleri</span>
                        <span class="text-[10px] bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded font-bold uppercase">Hazır</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-[6px] border border-slate-200 bg-slate-50/50">
                        <span class="text-xs font-medium text-slate-800">Senaryo 6: Kasa/Banka ve Virman İşlemleri</span>
                        <span class="text-[10px] bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded font-bold uppercase">Hazır</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-[6px] border border-slate-200 bg-slate-50/50">
                        <span class="text-xs font-medium text-slate-800">Senaryo 7: Tahsilat ve Ödeme (Fatura Kapatma)</span>
                        <span class="text-[10px] bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded font-bold uppercase">Hazır</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-[6px] border border-slate-200 bg-slate-50/50">
                        <span class="text-xs font-medium text-slate-800">Senaryo 8: e-Fatura / e-Arşiv MVP Akışı</span>
                        <span class="text-[10px] bg-amber-50 text-amber-700 px-2 py-0.5 rounded font-bold uppercase">MVP Limitli</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-[6px] border border-slate-200 bg-slate-50/50">
                        <span class="text-xs font-medium text-slate-800">Senaryo 9: Finansal Raporlar ve Yönetici Özeti</span>
                        <span class="text-[10px] bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded font-bold uppercase">Hazır</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-[6px] border border-slate-200 bg-slate-50/50">
                        <span class="text-xs font-medium text-slate-800">Senaryo 10: AI Asistan Güvenlik ve Raporlama</span>
                        <span class="text-[10px] bg-amber-50 text-amber-700 px-2 py-0.5 rounded font-bold uppercase">MVP Limitli</span>
                    </div>
                </div>
            </div>
        @endif

        <!-- 3. GERİ BİLDİRİM SEKME İÇERİĞİ -->
        @if($activeTab === 'feedback')
            <div class="p-4 lg:p-6 space-y-6">
                <!-- Geri Bildirim Form Kartı -->
                <div class="rounded-[8px] border border-slate-100 bg-slate-50/40 p-4">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-3">Yeni Geri Bildirim Kaydı</h2>
                    <form wire:submit.prevent="createFeedback" class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500">İlişkili Modül</label>
                            <select wire:model="module" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 outline-none sm:text-sm">
                                <option value="">Modül Seçin</option>
                                <option value="Dashboard">Dashboard</option>
                                <option value="Cariler">Cariler</option>
                                <option value="Stok">Stok</option>
                                <option value="Satışlar">Satışlar</option>
                                <option value="Satın Alma">Satın Alma</option>
                                <option value="Kasa/Banka">Kasa/Banka</option>
                                <option value="POS">POS</option>
                                <option value="e-Belge">e-Belge</option>
                                <option value="Raporlar">Raporlar</option>
                                <option value="Asistan">AI Asistan</option>
                            </select>
                            @error('module') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Tip</label>
                            <select wire:model="feedbackType" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 outline-none sm:text-sm">
                                <option value="bug">Hata (Bug)</option>
                                <option value="ux">UX / Arayüz</option>
                                <option value="data">Veri Hatası</option>
                                <option value="question">Soru</option>
                                <option value="risk">Risk Bildirimi</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Önem Derecesi</label>
                            <select wire:model="severity" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 outline-none sm:text-sm">
                                <option value="low">Düşük</option>
                                <option value="medium">Orta</option>
                                <option value="high">Yüksek</option>
                                <option value="critical">Kritik</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Başlık</label>
                            <input wire:model="title" type="text" placeholder="Kısa hata özeti" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 outline-none sm:text-sm">
                            @error('title') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Detaylı Açıklama</label>
                            <textarea wire:model="description" rows="2" placeholder="Hatanın adımları veya detaylar..." class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 outline-none sm:text-sm"></textarea>
                        </div>
                        <div class="flex items-end sm:col-span-2">
                            <button type="submit" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 transition">
                                Geri Bildirimi Gönder
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Geri Bildirim Tablosu ve Araçlar -->
                <div class="space-y-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between bg-slate-50/50 p-3 rounded-[8px] border border-slate-200">
                        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Geri bildirimlerde ara..." class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 outline-none sm:text-sm sm:w-80">
                        
                        <!-- Kolon Seçici Dropdown -->
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open" class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                                ⚙️ Kolonlar
                            </button>
                            <div x-show="open" @click.away="open = false" class="absolute right-0 mt-1 w-48 bg-white border border-slate-200 rounded-[6px] shadow-lg p-2 z-50">
                                @foreach($visibleColumns as $col => $visible)
                                    <label class="flex items-center gap-2 p-1.5 hover:bg-slate-50 rounded cursor-pointer text-xs">
                                        <input type="checkbox" wire:click="toggleColumn('{{ $col }}')" {{ $visible ? 'checked' : '' }} class="rounded border-slate-300">
                                        {{ $col === 'module' ? 'Modül' : '' }}
                                        {{ $col === 'type' ? 'Tip' : '' }}
                                        {{ $col === 'severity' ? 'Önem' : '' }}
                                        {{ $col === 'status' ? 'Durum' : '' }}
                                        {{ $col === 'title' ? 'Başlık' : '' }}
                                        {{ $col === 'created_at' ? 'Tarih' : '' }}
                                        {{ $col === 'actions' ? 'İşlemler' : '' }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Tablo & Mobil Kart Görünümü -->
                    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                        <!-- Desktop Görünüm -->
                        <table class="hidden md:table w-full text-left text-xs border-collapse">
                            <thead class="bg-slate-50 text-slate-500 uppercase font-mono border-b border-slate-200">
                                <tr>
                                    @if($visibleColumns['module'])
                                        <th class="p-3 cursor-pointer" wire:click="sortTable('module')">Modül</th>
                                    @endif
                                    @if($visibleColumns['type'])
                                        <th class="p-3 cursor-pointer" wire:click="sortTable('type')">Tip</th>
                                    @endif
                                    @if($visibleColumns['severity'])
                                        <th class="p-3 cursor-pointer" wire:click="sortTable('severity')">Önem</th>
                                    @endif
                                    @if($visibleColumns['status'])
                                        <th class="p-3 cursor-pointer" wire:click="sortTable('status')">Durum</th>
                                    @endif
                                    @if($visibleColumns['title'])
                                        <th class="p-3 cursor-pointer" wire:click="sortTable('title')">Başlık</th>
                                    @endif
                                    @if($visibleColumns['created_at'])
                                        <th class="p-3 cursor-pointer" wire:click="sortTable('created_at')">Kayıt Tarihi</th>
                                    @endif
                                    @if($visibleColumns['actions'])
                                        <th class="p-3 text-right">İşlemler</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                @forelse($feedbacks as $fb)
                                    <tr class="hover:bg-slate-50/50">
                                        @if($visibleColumns['module'])
                                            <td class="p-3 font-semibold">{{ $fb->module }}</td>
                                        @endif
                                        @if($visibleColumns['type'])
                                            <td class="p-3">{{ $typeLabel($fb->type) }}</td>
                                        @endif
                                        @if($visibleColumns['severity'])
                                            <td class="p-3">
                                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold {{ $severityBadge($fb->severity) }}">
                                                    {{ $severityLabel($fb->severity) }}
                                                </span>
                                            </td>
                                        @endif
                                        @if($visibleColumns['status'])
                                            <td class="p-3">
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase
                                                    {{ $fb->status === 'open' ? 'bg-amber-50 text-amber-700 border border-amber-100' : 'bg-emerald-50 text-emerald-700 border border-emerald-100' }}
                                                ">
                                                    {{ $fb->status === 'open' ? 'Açık' : 'Çözüldü' }}
                                                </span>
                                            </td>
                                        @endif
                                        @if($visibleColumns['title'])
                                            <td class="p-3">
                                                <div class="font-medium text-slate-900">{{ $fb->title }}</div>
                                                <div class="text-slate-400 mt-0.5">{{ $fb->description }}</div>
                                            </td>
                                        @endif
                                        @if($visibleColumns['created_at'])
                                            <td class="p-3 text-slate-500">{{ $fb->created_at->timezone('Europe/Istanbul')->format('H:i - d.m.Y') }}</td>
                                        @endif
                                        @if($visibleColumns['actions'])
                                            <td class="p-3 text-right">
                                                @if($fb->isOpen())
                                                    <button wire:click="resolveFeedback({{ $fb->id }})" class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] font-semibold text-emerald-700 hover:bg-emerald-100 transition">
                                                        ✓ Çözüldü Yap
                                                    </button>
                                                @else
                                                    <span class="text-slate-400 text-xs">Tamamlandı</span>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="p-6 text-center text-slate-500">Geri bildirim bulunamadı.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>

                        <!-- Mobil Kart Görünüm -->
                        <div class="md:hidden divide-y divide-slate-100">
                            @forelse($feedbacks as $fb)
                                <div class="p-4 space-y-3 bg-white">
                                    <div class="flex items-center justify-between">
                                        <span class="font-semibold text-xs text-slate-900">{{ $fb->module }}</span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold {{ $severityBadge($fb->severity) }}">
                                            {{ $severityLabel($fb->severity) }}
                                        </span>
                                    </div>
                                    <div class="space-y-1">
                                        <div class="font-medium text-slate-900 text-xs">{{ $fb->title }}</div>
                                        <p class="text-xs text-slate-500">{{ $fb->description }}</p>
                                    </div>
                                    <div class="flex items-center justify-between text-[11px] text-slate-500">
                                        <span>{{ $fb->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase
                                            {{ $fb->status === 'open' ? 'bg-amber-50 text-amber-700 border border-amber-100' : 'bg-emerald-50 text-emerald-700 border border-emerald-100' }}
                                        ">
                                            {{ $fb->status === 'open' ? 'Açık' : 'Çözüldü' }}
                                        </span>
                                    </div>
                                    @if($fb->isOpen())
                                        <div class="pt-2">
                                            <button wire:click="resolveFeedback({{ $fb->id }})" class="w-full inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-emerald-200 bg-emerald-50 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 transition">
                                                ✓ Çözüldü Olarak İşaretle
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <div class="p-6 text-center text-slate-500 text-xs">Geri bildirim bulunamadı.</div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div class="mt-4">
                        {{ $feedbacks->links() }}
                    </div>
                </div>
            </div>
        @endif

        <!-- 4. RİSK REGISTER SEKME İÇERİĞİ -->
        @if($activeTab === 'risks')
            <div class="p-4 lg:p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-900">Pilot Risk Sicili (Pilot Risk Register)</h2>
                    <a href="/docs/accounting-pilot-risk-register.md" target="_blank" class="text-xs text-slate-500 hover:text-slate-900">Risk Dokümanını Gör ↗</a>
                </div>
                <div class="space-y-3">
                    <div class="p-4 rounded-[8px] border border-slate-200 bg-slate-50/50">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-xs font-bold text-slate-900">Risk 1: Gerçek e-Fatura/e-Arşiv Entegratörü Yok</h3>
                            <span class="px-2 py-0.5 bg-rose-50 text-rose-700 rounded text-[10px] font-bold">Kritik</span>
                        </div>
                        <p class="text-xs text-slate-500 mt-2">GİB veya özel entegratör entegrasyonu bulunmamaktadır. Akışlar simüledir.</p>
                        <p class="text-[11px] text-slate-600 mt-1"><strong>Mitigasyon:</strong> Pilot kullanıcılara bu ekranın simülasyon amaçlı olduğu açık uyarılarla gösterilmelidir.</p>
                    </div>
                    <div class="p-4 rounded-[8px] border border-slate-200 bg-slate-50/50">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-xs font-bold text-slate-900">Risk 2: POS Donanım Entegrasyonu Yok</h3>
                            <span class="px-2 py-0.5 bg-amber-50 text-amber-700 rounded text-[10px] font-bold">Orta</span>
                        </div>
                        <p class="text-xs text-slate-500 mt-2">Barkod okuyucu, fiş yazıcı veya temassız ödeme cihazı bağlantısı mevcut değildir.</p>
                        <p class="text-[11px] text-slate-600 mt-1"><strong>Mitigasyon:</strong> POS modülünün donanımsız "Web POS" olarak lanse edilmesi.</p>
                    </div>
                    <div class="p-4 rounded-[8px] border border-slate-200 bg-slate-50/50">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-xs font-bold text-slate-900">Risk 3: Kasiyer ve Muhasebeci Rollerindeki Kısıtlar</h3>
                            <span class="px-2 py-0.5 bg-amber-50 text-amber-700 rounded text-[10px] font-bold">Orta</span>
                        </div>
                        <p class="text-xs text-slate-500 mt-2">Detaylı ara rol yetkilendirmesi (kasiyer/muhasebe) mevcut değildir; ekranlar sadece admin rolündeki kullanıcılara açıktır.</p>
                        <p class="text-[11px] text-slate-600 mt-1"><strong>Mitigasyon:</strong> Pilot sürecinde tek kullanıcılı / admin profilli küçük işletmeler seçilmelidir.</p>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
