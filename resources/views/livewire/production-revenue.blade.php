<div class="w-full space-y-6">
    <style>
        .revenue-scrollbar::-webkit-scrollbar {
            height: 8px;
            width: 8px;
        }

        .revenue-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.45);
            border-radius: 999px;
        }
    </style>

    @unless($this->revenueTablesReady)
        <section class="rounded-[28px] border border-amber-200 bg-amber-50 p-4 lg:p-6 shadow-sm">
            <div class="flex flex-col sm:flex-row items-start sm:items-center sm:justify-between gap-3 lg:gap-4">
                <div>
                    <h2 class="text-xl lg:text-2xl font-bold text-amber-900">Veritabanı kurulumu eksik</h2>
                    <p class="mt-2 text-sm lg:text-base text-amber-800">
                        `production_revenue_entries` ve `production_revenue_imports` tabloları henüz oluşmamış. Bu nedenle sayfa korumalı modda açıldı.
                    </p>
                </div>
                <div class="w-full sm:w-auto rounded-2xl border border-amber-300 bg-white px-4 py-3 text-sm text-amber-900">
                    Önce migration çalıştırın.
                </div>
            </div>
            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 lg:gap-4">
                <div class="rounded-2xl bg-slate-900 px-4 py-3 text-sm text-slate-100">
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Sail</p>
                    <p class="mt-2 font-mono">./vendor/bin/sail artisan migrate</p>
                </div>
                <div class="rounded-2xl bg-slate-900 px-4 py-3 text-sm text-slate-100">
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">PHP</p>
                    <p class="mt-2 font-mono">php artisan migrate</p>
                </div>
            </div>
        </section>
    @endunless

    <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-5 shadow-sm">
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 lg:gap-6">
            <div class="min-w-0 xl:col-span-7 xl:py-1">
                <div class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium uppercase tracking-[0.24em] text-slate-500">
                    Üretim Ciro
                </div>

                <div class="mt-4 flex flex-col sm:flex-row items-stretch sm:items-center gap-3 lg:gap-4">
                    <label class="group flex-1 cursor-pointer rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 transition hover:border-slate-400 hover:bg-white">
                        <input type="file" wire:model="file" accept=".xlsx,.xls" class="hidden">
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-900 text-white">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-900">{{ $file ? $file->getClientOriginalName() : 'Ürün Ciro Takip.xlsx seçin' }}</p>
                                <p class="mt-1 text-xs sm:text-sm text-slate-500">Aylık sekmeleri olan güncel dosyayı her gün tekrar yükleyebilirsiniz.</p>
                            </div>
                        </div>
                    </label>

                    <div class="w-full sm:w-auto flex items-center">
                        <button
                            type="button"
                            wire:click="importWorkbook"
                            wire:loading.attr="disabled"
                            wire:target="importWorkbook,file"
                            @disabled(!$this->revenueTablesReady)
                            class="w-full sm:w-auto sm:min-w-[116px] px-4 py-3 sm:py-2 text-base sm:text-sm font-medium bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 transition-colors"
                        >
                            <span wire:loading.remove wire:target="importWorkbook">Güncelle</span>
                            <span wire:loading wire:target="importWorkbook">İşleniyor...</span>
                        </button>
                    </div>
                </div>

                @error('file')
                    <p class="mt-3 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid w-full auto-rows-fr grid-cols-1 sm:grid-cols-2 gap-3 lg:gap-4 xl:col-span-5 xl:self-start">
                <div class="flex h-full flex-col rounded-3xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Günlük ortalama</p>
                    <p class="mt-3 text-3xl font-bold text-slate-900">{{ $this->formatCurrency($this->monthSummary['average']) }}</p>
                    <p class="mt-2 text-sm text-slate-500">Üretim olan günlerdeki ortalama ciro.</p>
                </div>

                <div class="flex h-full flex-col rounded-3xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Seçili dönem</p>
                    <div class="mt-3 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xl lg:text-2xl font-bold text-slate-900">{{ $this->formatMonthLabel($this->currentMonth) }}</p>
                            <p class="mt-1 whitespace-nowrap text-sm text-slate-500">{{ $this->monthSummary['active_days'] }} aktif gün</p>
                        </div>
                        <div class="shrink-0 rounded-2xl bg-slate-900 px-3 py-2 text-white">
                            <p class="text-[10px] uppercase tracking-[0.2em] text-slate-300">Toplam</p>
                            <p class="mt-1 whitespace-nowrap text-sm lg:text-base font-bold leading-none">{{ $this->formatCurrency($this->monthSummary['total']) }}</p>
                        </div>
                    </div>
                    <div class="mt-auto pt-3">
                        <div class="flex items-center justify-between gap-2 text-sm text-slate-500">
                            <span>Kapsama</span>
                            <span class="font-semibold text-slate-900">%{{ $this->monthSummary['coverage'] }}</span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200">
                            <div class="h-full rounded-full bg-slate-900" style="width: {{ min(100, $this->monthSummary['coverage']) }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

        @if($importMessage)
            <div class="rounded-2xl border p-4 {{ $importMessageType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800' }}">
                {{ $importMessage }}
            </div>
        @endif

        @if(!empty($lastImportSummary))
            <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 lg:gap-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Eklenen</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ $lastImportSummary['created'] }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Güncellenen</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ $lastImportSummary['updated'] }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Aynı kalan</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ $lastImportSummary['unchanged'] }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Atlanan</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ $lastImportSummary['skipped'] }}</p>
                </div>
            </div>
        @endif

        <section class="space-y-4">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl lg:text-2xl font-bold text-slate-900">Aylık görünümler</h2>
                    <p class="mt-1 text-sm text-slate-500">Takvimden bir gün seçerek detayını, sağ panelden de yükleme geçmişini izleyin.</p>
                </div>
                @if($this->monthSummary['best_day'])
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 shadow-sm">
                        En güçlü gün:
                        <span class="font-semibold text-slate-900">{{ $this->monthSummary['best_day']->work_date->locale('tr')->translatedFormat('d F') }}</span>
                        <span class="ml-2 font-semibold text-emerald-600">{{ $this->formatCurrency($this->monthSummary['best_day']->revenue) }}</span>
                    </div>
                @endif
            </div>

            <div class="revenue-scrollbar flex gap-3 overflow-x-auto pb-2">
                @forelse($this->availableMonths as $month)
                    <button
                        type="button"
                        wire:click="selectMonth('{{ $month['key'] }}')"
                        class="min-w-[180px] rounded-3xl border px-4 py-4 text-left transition {{ $selectedMonth === $month['key'] ? 'border-slate-900 bg-slate-900 text-white shadow-lg shadow-slate-900/10' : 'border-slate-200 bg-white text-slate-900 hover:border-slate-300 hover:shadow-sm' }}"
                    >
                        <p class="text-sm font-semibold">{{ $month['label'] }}</p>
                        <p class="mt-3 text-2xl font-semibold">{{ $this->formatCurrency($month['total']) }}</p>
                        <div class="mt-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs {{ $selectedMonth === $month['key'] ? 'text-slate-300' : 'text-slate-500' }}">
                            <span>{{ $month['active_days'] }} aktif gün</span>
                            <span>{{ $month['alerts'] }} not</span>
                        </div>
                    </button>
                @empty
                    <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-5 py-6 text-sm text-slate-500">
                        Henüz üretim ciro verisi yok. İlk Excel yüklemesini yaptığınızda ay bazlı kartlar burada listelenecek.
                    </div>
                @endforelse
            </div>
        </section>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 lg:gap-8">
            <section class="xl:col-span-2 rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 lg:gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Takvim paneli</p>
                        <h2 class="mt-2 text-xl lg:text-2xl font-bold text-slate-900">{{ $this->formatMonthLabel($this->currentMonth) }}</h2>
                    </div>
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                        <div class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-1">
                            <button type="button" wire:click="previousMonth" class="rounded-xl px-3 py-2 text-sm text-slate-600 transition hover:bg-white hover:text-slate-900">Önceki</button>
                            <button type="button" wire:click="nextMonth" class="rounded-xl px-3 py-2 text-sm text-slate-600 transition hover:bg-white hover:text-slate-900">Sonraki</button>
                        </div>
                        <div class="relative min-w-[220px]">
                            <select wire:model.live="selectedMonth" class="w-full appearance-none rounded-2xl border border-slate-200 bg-white px-4 py-3 pr-10 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none">
                                @if(!$this->availableMonths->contains(fn ($month) => $month['key'] === $selectedMonth))
                                    <option value="{{ $selectedMonth }}">{{ $this->formatMonthLabel($this->currentMonth) }}</option>
                                @endif
                                @foreach($this->availableMonths as $month)
                                    <option value="{{ $month['key'] }}">{{ $month['label'] }}</option>
                                @endforeach
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 lg:gap-4">
                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Tatil</p>
                        <p class="mt-2 text-2xl font-semibold text-amber-600">{{ $this->monthSummary['holiday_days'] }}</p>
                    </div>
                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Çıkış yok</p>
                        <p class="mt-2 text-2xl font-semibold text-rose-600">{{ $this->monthSummary['no_output_days'] }}</p>
                    </div>
                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Ek notlu gün</p>
                        <p class="mt-2 text-2xl font-semibold text-sky-600">{{ $this->monthSummary['note_days'] }}</p>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-emerald-700">Kayıtlı ciro</span>
                    <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-amber-700">Tatil</span>
                    <span class="rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-rose-700">Çıkış yok</span>
                    <span class="rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-sky-700">Ek not</span>
                </div>

                @if($this->hasEntries)
                    <div class="mt-6 hidden sm:block">
                        <div class="grid grid-cols-7 gap-3 text-center text-xs font-medium uppercase tracking-[0.2em] text-slate-400">
                            @foreach(['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'] as $dayLabel)
                                <div class="rounded-2xl bg-slate-50 py-3">{{ $dayLabel }}</div>
                            @endforeach
                        </div>

                        <div class="mt-3 space-y-3">
                            @foreach($this->calendarWeeks as $weekIndex => $week)
                                <div class="grid grid-cols-7 gap-3" wire:key="calendar-week-{{ $weekIndex }}">
                                    @foreach($week as $day)
                                        <button
                                            type="button"
                                            wire:click="focusDate('{{ $day['key'] }}')"
                                            @disabled(!$day['is_current_month'])
                                            class="min-h-[136px] rounded-[24px] border p-3 text-left transition duration-200 {{ $this->dayToneClasses($day) }} {{ $day['is_focused'] ? 'ring-2 ring-slate-900 ring-offset-2' : 'hover:-translate-y-0.5 hover:shadow-md' }} {{ !$day['is_current_month'] ? 'cursor-default opacity-70' : '' }}"
                                        >
                                            <div class="flex items-start justify-between gap-2">
                                                <div>
                                                    <p class="text-xs uppercase tracking-[0.16em] {{ $day['is_current_month'] ? 'opacity-70' : 'opacity-40' }}">
                                                        {{ $day['date']->locale('tr')->translatedFormat('D') }}
                                                    </p>
                                                    <p class="mt-2 text-2xl font-semibold">{{ $day['date']->format('d') }}</p>
                                                </div>
                                                @if($day['is_today'])
                                                    <span class="shrink-0 whitespace-nowrap rounded-full bg-slate-900/10 px-1.5 py-0.5 text-[9px] font-medium leading-none {{ $day['intensity'] >= 4 ? 'bg-white/20 text-white' : 'text-slate-700' }}">Bugün</span>
                                                @endif
                                            </div>

                                            @if($day['entry'])
                                                <div class="mt-5 space-y-2">
                                                    <p class="text-lg font-semibold leading-tight">{{ $this->formatCurrency($day['entry']->revenue) }}</p>
                                                    <div class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium {{ $this->statusBadgeClasses($day['entry']->status) }}">
                                                        {{ $this->statusLabel($day['entry']->status) }}
                                                    </div>
                                                    @if($day['entry']->note)
                                                        <p class="line-clamp-2 text-xs {{ $day['intensity'] >= 4 ? 'text-white/80' : 'text-slate-500' }}">{{ $day['entry']->note }}</p>
                                                    @endif
                                                </div>
                                            @else
                                                <p class="mt-8 text-xs {{ $day['is_current_month'] ? 'text-slate-400' : 'text-slate-300' }}">Kayıt bulunmuyor</p>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-6 space-y-3 sm:hidden">
                        @forelse($this->monthEntries as $entry)
                            <button
                                type="button"
                                wire:click="focusDate('{{ $entry->work_date->toDateString() }}')"
                                wire:key="mobile-day-{{ $entry->id }}"
                                class="w-full rounded-3xl border border-slate-200 bg-white p-4 text-left shadow-sm transition {{ $focusedDate === $entry->work_date->toDateString() ? 'ring-2 ring-slate-900 ring-offset-2' : '' }}"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">{{ $entry->work_date->locale('tr')->translatedFormat('d F l') }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $entry->sheet_name }}</p>
                                    </div>
                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-medium {{ $this->statusBadgeClasses($entry->status) }}">{{ $this->statusLabel($entry->status) }}</span>
                                </div>
                                <p class="mt-4 text-2xl font-semibold text-slate-900">{{ $this->formatCurrency($entry->revenue) }}</p>
                                @if($entry->note)
                                    <p class="mt-2 text-sm text-slate-500">{{ $entry->note }}</p>
                                @endif
                            </button>
                        @empty
                            <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-6 text-sm text-slate-500">
                                Seçili ay için kayıt bulunmuyor.
                            </div>
                        @endforelse
                    </div>
                @else
                    <div class="mt-6 rounded-[28px] border border-dashed border-slate-300 bg-slate-50 p-6 lg:p-10 text-center">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-slate-900 text-white shadow-lg shadow-slate-900/10">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2v11a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-xl lg:text-2xl font-bold text-slate-900">Takvim boş</h3>
                        <p class="mt-2 text-sm text-slate-500">İlk üretim ciro Excel'ini yüklediğinizde gün bazlı tüm hareketler burada takvim olarak oluşacak.</p>
                    </div>
                @endif
            </section>

            <aside class="space-y-4 lg:space-y-5">
                <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
                    <div class="flex flex-col sm:flex-row items-start sm:items-start sm:justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Seçili gün</p>
                            <h3 class="mt-2 text-xl lg:text-2xl font-bold text-slate-900">
                                {{ $focusedDate ? \Carbon\Carbon::parse($focusedDate)->locale('tr')->translatedFormat('d F Y') : 'Gün seçin' }}
                            </h3>
                        </div>
                        @if($this->focusedEntry)
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-medium {{ $this->statusBadgeClasses($this->focusedEntry->status) }}">{{ $this->statusLabel($this->focusedEntry->status) }}</span>
                        @endif
                    </div>

                    @if($this->focusedEntry)
                        <div class="mt-5 rounded-3xl bg-slate-950 p-5 text-white">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-300">Günlük ciro</p>
                            <p class="mt-2 text-3xl font-semibold">{{ $this->formatCurrency($this->focusedEntry->revenue) }}</p>
                            <p class="mt-3 text-sm text-slate-300">{{ $this->focusedEntry->note ?: 'Bu gün için ek açıklama bulunmuyor.' }}</p>
                        </div>

                        <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-3 lg:gap-4">
                            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Hafta toplamı</p>
                                <p class="mt-2 text-xl font-semibold text-slate-900">{{ $this->formatCurrency($this->focusedInsights['week_total']) }}</p>
                            </div>
                            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Aylık sıralama</p>
                                <p class="mt-2 text-xl font-semibold text-slate-900">
                                    {{ $this->focusedInsights['rank'] ? '#' . $this->focusedInsights['rank'] : 'Yok' }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 space-y-3 rounded-3xl border border-slate-200 bg-white p-4">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-sm text-slate-600">
                                <span>Ortalamaya göre</span>
                                <span class="font-semibold {{ $this->focusedInsights['delta_average'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                    {{ $this->focusedInsights['delta_average'] >= 0 ? '+' : '-' }}{{ $this->formatCurrency(abs($this->focusedInsights['delta_average'])) }}
                                </span>
                            </div>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-sm text-slate-600">
                                <span>Bir önceki üretimli gün</span>
                                @if(!is_null($this->focusedInsights['delta_previous']))
                                    <span class="font-semibold {{ $this->focusedInsights['delta_previous'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                        {{ $this->focusedInsights['previous_label'] }}
                                        {{ $this->focusedInsights['delta_previous'] >= 0 ? '+' : '-' }}{{ $this->formatCurrency(abs($this->focusedInsights['delta_previous'])) }}
                                    </span>
                                @else
                                    <span class="font-semibold text-slate-900">İlk kayıt</span>
                                @endif
                            </div>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-sm text-slate-600">
                                <span>Son yükleme</span>
                                <span class="font-semibold text-slate-900">{{ $this->focusedEntry->import?->imported_at?->locale('tr')->diffForHumans() ?? 'Bilinmiyor' }}</span>
                            </div>
                        </div>
                    @else
                        <div class="mt-5 rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-500">
                            Takvimden bir gün seçerek o güne ait ciro, not ve karşılaştırma metriklerini görüntüleyin.
                        </div>
                    @endif
                </section>

                <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">En iyi günler</p>
                            <h3 class="mt-2 text-xl lg:text-2xl font-bold text-slate-900">Ayın liderleri</h3>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600">Top 5</span>
                    </div>

                    <div class="mt-5 space-y-3">
                        @forelse($this->topDays as $index => $entry)
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-900">#{{ $index + 1 }} {{ $entry->work_date->locale('tr')->translatedFormat('d F') }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $entry->sheet_name }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-slate-900">{{ $this->formatCurrency($entry->revenue) }}</p>
                                    <p class="mt-1 text-[11px] text-slate-500">{{ $this->statusLabel($entry->status) }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500">
                                Bu ay için sıralanacak ciro günü bulunmuyor.
                            </div>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Yükleme geçmişi</p>
                            <h3 class="mt-2 text-xl lg:text-2xl font-bold text-slate-900">Son yüklemeler</h3>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600">Audit</span>
                    </div>

                    <div class="mt-5 space-y-3">
                        @forelse($this->recentImports as $import)
                            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex flex-col sm:flex-row items-start sm:items-start sm:justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $import->filename }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $import->imported_at?->locale('tr')->diffForHumans() }}</p>
                                    </div>
                                    <span class="rounded-full bg-slate-900 px-2.5 py-1 text-[10px] font-medium text-white">{{ $import->sheet_count }} ay</span>
                                </div>
                                <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3 lg:gap-4 text-center text-xs text-slate-500">
                                    <div class="rounded-2xl bg-white px-2 py-2">
                                        <p>Yeni</p>
                                        <p class="mt-1 font-semibold text-slate-900">{{ $import->created_rows }}</p>
                                    </div>
                                    <div class="rounded-2xl bg-white px-2 py-2">
                                        <p>Güncel</p>
                                        <p class="mt-1 font-semibold text-slate-900">{{ $import->updated_rows }}</p>
                                    </div>
                                    <div class="rounded-2xl bg-white px-2 py-2">
                                        <p>Aynı</p>
                                        <p class="mt-1 font-semibold text-slate-900">{{ $import->unchanged_rows }}</p>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500">
                                Henüz kayıtlı yükleme bulunmuyor.
                            </div>
                        @endforelse
                    </div>
                </section>
            </aside>
        </div>
    </div>
</div>
