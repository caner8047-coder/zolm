<div class="w-full space-y-6">
    {{-- Başlık --}}
    <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row items-start sm:items-center sm:justify-between gap-3 lg:gap-4">
            <div>
                <div class="inline-flex items-center rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-medium uppercase tracking-[0.24em] text-purple-600">
                    AI Aksiyon Merkezi
                </div>
                <h1 class="mt-3 text-xl lg:text-2xl font-bold text-slate-900">Öneriler ve Aksiyonlar</h1>
                <p class="mt-1 text-sm text-slate-500">Kural motoru tarafından üretilen önerileri inceleyin ve aksiyon alın.</p>
            </div>
            <button wire:click="runRuleEngine" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-base sm:text-sm font-medium bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                Kural Motorunu Çalıştır
            </button>
        </div>
    </section>

    {{-- Özet Kartları --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Toplam Öneri</p>
            <p class="mt-3 text-2xl lg:text-3xl font-bold text-slate-900">{{ $stats['total_recommendations'] }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Yeni Öneri</p>
            <p class="mt-3 text-2xl lg:text-3xl font-bold text-purple-600">{{ $stats['new_recommendations'] }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Kritik / Yüksek</p>
            <p class="mt-3 text-2xl lg:text-3xl font-bold">
                <span class="text-rose-600">{{ $stats['critical_count'] }}</span>
                <span class="text-slate-400 mx-1">/</span>
                <span class="text-amber-600">{{ $stats['high_count'] }}</span>
            </p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Kabul / Red</p>
            <p class="mt-3 text-2xl lg:text-3xl font-bold">
                <span class="text-emerald-600">{{ $stats['accepted_count'] }}</span>
                <span class="text-slate-400 mx-1">/</span>
                <span class="text-slate-600">{{ $stats['rejected_count'] }}</span>
            </p>
        </div>
    </div>

    {{-- Filtreler --}}
    <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 lg:gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Öncelik</label>
                <select wire:model.live="priorityFilter" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-base sm:text-sm text-slate-900 focus:border-slate-400 focus:outline-none">
                    <option value="">Tümü</option>
                    <option value="critical">Kritik</option>
                    <option value="high">Yüksek</option>
                    <option value="medium">Orta</option>
                    <option value="low">Düşük</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Durum</label>
                <select wire:model.live="statusFilter" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-base sm:text-sm text-slate-900 focus:border-slate-400 focus:outline-none">
                    <option value="">Tümü</option>
                    <option value="new">Yeni</option>
                    <option value="viewed">Görüntülendi</option>
                    <option value="accepted">Kabul Edildi</option>
                    <option value="rejected">Reddedildi</option>
                    <option value="snoozed">Ertelendi</option>
                </select>
            </div>
            <div class="flex items-end">
                <button wire:click="loadStats" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm font-medium border border-slate-200 bg-white text-slate-700 rounded-lg hover:bg-slate-50 transition-colors">
                    Yenile
                </button>
            </div>
        </div>
    </section>

    {{-- Öneri Listesi --}}
    <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Öneriler</h2>

        @php
            $priorityColors = [
                'critical' => 'bg-rose-100 text-rose-700 border-rose-200',
                'high' => 'bg-amber-100 text-amber-700 border-amber-200',
                'medium' => 'bg-blue-100 text-blue-700 border-blue-200',
                'low' => 'bg-slate-100 text-slate-600 border-slate-200',
            ];
            $statusColors = [
                'new' => 'bg-purple-100 text-purple-700',
                'viewed' => 'bg-slate-100 text-slate-600',
                'accepted' => 'bg-emerald-100 text-emerald-700',
                'rejected' => 'bg-rose-100 text-rose-700',
                'snoozed' => 'bg-amber-100 text-amber-700',
            ];
            $categoryLabels = [
                'budget' => 'Bütçe',
                'profitability' => 'Kârlılık',
                'stock' => 'Stok',
                'keyword' => 'Kelime',
                'creator' => 'Creator',
                'data_quality' => 'Veri Kalitesi',
            ];
        @endphp

        <div class="mt-4 space-y-3">
            @forelse($this->recommendations as $rec)
                <div class="rounded-2xl border {{ $priorityColors[$rec->priority] ?? 'border-slate-200 bg-slate-50' }} p-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $priorityColors[$rec->priority] ?? 'bg-slate-100 text-slate-600' }}">
                                    {{ ucfirst($rec->priority) }}
                                </span>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $statusColors[$rec->status] ?? 'bg-slate-100 text-slate-600' }}">
                                    {{ ucfirst($rec->status) }}
                                </span>
                                <span class="text-xs text-slate-500">{{ $categoryLabels[$rec->category] ?? $rec->category }}</span>
                            </div>
                            <h3 class="mt-2 text-sm font-semibold text-slate-900">{{ $rec->title }}</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ $rec->description }}</p>
                            <p class="mt-1 text-sm text-slate-700 font-medium">{{ $rec->recommended_action }}</p>
                            @if($rec->evidence)
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach($rec->evidence as $key => $value)
                                        <span class="inline-flex items-center rounded-full bg-white border border-slate-200 px-2 py-0.5 text-xs text-slate-600">
                                            {{ $key }}: {{ is_numeric($value) ? number_format($value, 0, ',', '.') : $value }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                            <p class="mt-2 text-xs text-slate-500">Güven: {{ number_format($rec->confidence_score * 100, 0) }}%</p>
                        </div>
                        @if($rec->status === 'new' || $rec->status === 'viewed')
                            <div class="flex gap-2 shrink-0">
                                <button wire:click="acceptRecommendation({{ $rec->id }})"
                                    class="px-3 py-1.5 text-xs font-medium bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors">
                                    Kabul Et
                                </button>
                                <button wire:click="rejectRecommendation({{ $rec->id }})"
                                    class="px-3 py-1.5 text-xs font-medium border border-slate-200 bg-white text-slate-700 rounded-lg hover:bg-slate-50 transition-colors">
                                    Reddet
                                </button>
                                <button wire:click="snoozeRecommendation({{ $rec->id }})"
                                    class="px-3 py-1.5 text-xs font-medium border border-slate-200 bg-white text-slate-700 rounded-lg hover:bg-slate-50 transition-colors">
                                    Ertele
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-center py-8">
                    <p class="text-sm text-slate-500">Henüz öneri bulunmuyor. "Kural Motorunu Çalıştır" butonunu tıklayın.</p>
                </div>
            @endforelse
        </div>

        {{-- Sayfalama --}}
        @if($this->recommendations->hasPages())
            <div class="mt-4">
                {{ $this->recommendations->links() }}
            </div>
        @endif
    </section>
</div>
