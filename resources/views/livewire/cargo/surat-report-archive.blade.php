@php
    $summaries = $this->summaries;
    $selectedSummary = $this->selectedSummary;
    $lines = $this->lines;
    $activeAccount = $this->activeAccount;
    $summaryTotals = $this->summaryTotals;
    $summaryRangeLabel = $this->summaryRangeLabel;
    $amountSourceStats = $this->selectedAmountSourceStats;
    $estimatedAmountRows = (int) ($amountSourceStats->get('estimated_from_vat')->row_count ?? 0);

    $messageClasses = match ($messageTone) {
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
        default => 'border-sky-200 bg-sky-50 text-sky-800',
    };

    $formatCount = fn ($value) => number_format((float) $value, 0, ',', '.');
    $formatDecimal = fn ($value) => number_format((float) $value, 2, ',', '.');
    $formatMoney = fn ($value) => number_format((float) $value, 2, ',', '.') . ' TL';
@endphp

<div class="space-y-4 lg:space-y-6">
    @if(!$this->tableReady)
        <div class="rounded-[10px] border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Sürat rapor arşiv tabloları henüz hazır değil. Migration çalıştıktan sonra günlük raporlar kaydedilebilir.
        </div>
    @endif

    @if($message)
        <div class="rounded-[8px] border px-4 py-3 text-sm {{ $messageClasses }}">
            {{ $message }}
        </div>
    @endif

    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-4 lg:p-5">
            <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="text-lg font-semibold text-slate-900">Sürat günlük rapor arşivi</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Seçtiğiniz tarih aralığındaki gönderileri Sürat servisinden çekin; müşteri, parça, desi, tutar ve ölçüm farkını gün gün saklayın.
                    </p>
                    <p class="mt-2 text-xs text-slate-500">
                        Aktif hesap: <span class="font-medium text-slate-700">{{ $activeAccount?->account_name ?: 'Tanımlı aktif hesap yok' }}</span>
                    </p>
                </div>

                <div class="grid w-full grid-cols-1 gap-3 sm:grid-cols-[160px_160px_auto] xl:w-auto">
                    <div>
                        <label class="text-sm font-medium text-slate-700">Başlangıç</label>
                        <input type="date" wire:model.defer="startDate" class="mt-1 min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm">
                        @error('startDate') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Bitiş</label>
                        <input type="date" wire:model.defer="endDate" class="mt-1 min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm">
                        @error('endDate') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <button
                        type="button"
                        wire:click="fetchDateRangeReport"
                        wire:loading.attr="disabled"
                        wire:target="fetchDateRangeReport"
                        class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60 sm:w-auto sm:self-end sm:py-2"
                    >
                        <span wire:loading.remove wire:target="fetchDateRangeReport">Sürat’ten Çek</span>
                        <span wire:loading wire:target="fetchDateRangeReport">Çekiliyor...</span>
                    </button>
                </div>
            </div>
        </div>

        @if($lastFetchCompleted)
            <div class="grid grid-cols-2 gap-3 border-b border-slate-200 p-4 lg:grid-cols-5 lg:p-5">
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-xs font-medium text-slate-500">Çekilen gönderi</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900">{{ $formatCount($lastTotals['row_count'] ?? 0) }}</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-xs font-medium text-slate-500">Parça</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900">{{ $formatCount($lastTotals['pieces'] ?? 0) }}</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-xs font-medium text-slate-500">Desi</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900">{{ $formatDecimal($lastTotals['desi'] ?? 0) }}</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-xs font-medium text-slate-500">Tutar</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900">{{ $formatMoney($lastTotals['amount'] ?? 0) }}</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-xs font-medium text-slate-500">Ölçüm dahil</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900">{{ $formatMoney($lastTotals['total_amount'] ?? 0) }}</p>
                </div>
            </div>
        @endif
    </section>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_340px] gap-4 lg:gap-6">
        <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-4 lg:p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold text-slate-900">
                            {{ $selectedDate ? \Illuminate\Support\Carbon::parse($selectedDate)->format('d.m.Y') . ' gönderileri' : 'Gün seçin' }}
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">Kayıtlı günlük raporu filtreleyin, Excel’e aktarın veya tek dokunuşla check modülüne gönderin.</p>
                    </div>
                    <div class="flex w-full flex-col gap-2 sm:flex-row lg:w-auto">
                        <button
                            type="button"
                            wire:click="sendSelectedDateToCheck"
                            class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 sm:w-auto sm:py-2"
                        >
                            Check’e Gönder
                        </button>
                        <button
                            type="button"
                            wire:click="exportSelectedDate"
                            wire:loading.attr="disabled"
                            wire:target="exportSelectedDate"
                            class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60 sm:w-auto sm:py-2"
                        >
                            Excel İndir
                        </button>
                    </div>
                </div>

                @if($selectedSummary)
                    <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-5">
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-xs font-medium text-slate-500">Gönderi</p>
                            <p class="mt-1 text-lg font-semibold text-slate-900">{{ $formatCount($selectedSummary->row_count ?? 0) }}</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-xs font-medium text-slate-500">Parça</p>
                            <p class="mt-1 text-lg font-semibold text-slate-900">{{ $formatCount($selectedSummary->pieces ?? 0) }}</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-xs font-medium text-slate-500">Desi</p>
                            <p class="mt-1 text-lg font-semibold text-slate-900">{{ $formatDecimal($selectedSummary->desi ?? 0) }}</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-xs font-medium text-slate-500">Tutar</p>
                            <p class="mt-1 text-lg font-semibold text-slate-900">{{ $formatMoney($selectedSummary->amount ?? 0) }}</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-xs font-medium text-slate-500">Toplam</p>
                            <p class="mt-1 text-lg font-semibold text-slate-900">{{ $formatMoney($selectedSummary->total_amount ?? 0) }}</p>
                        </div>
                    </div>
                @endif

                @if($estimatedAmountRows > 0)
                    <div class="mt-4 rounded-[8px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Bu günde {{ $formatCount($estimatedAmountRows) }} satırda Sürat `Tutar` alanı 0 geldiği için tutar KDV alanından yaklaşık brüt bedel olarak hesaplandı. Gönderi faturalaştıkça Sürat `Tutar` alanı gelirse kayıt tekrar çekildiğinde kesinleşir.
                    </div>
                @endif

                <div class="mt-4">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Müşteri, takip no, web sipariş kodu veya durum ara..."
                        class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm"
                    >
                </div>
            </div>

            <div class="p-4 lg:p-5">
                <div class="md:hidden space-y-3">
                    @forelse($lines as $line)
                        <div wire:key="surat-report-card-{{ $line->id }}" class="rounded-[8px] border border-slate-200 bg-white p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $line->customer_name ?: 'Alıcı yok' }}</p>
                                    <p class="mt-1 font-mono text-xs text-slate-500">{{ $line->tracking_number ?: 'Takip no yok' }}</p>
                                </div>
                                <span class="shrink-0 rounded-[6px] bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">{{ $line->status ?: 'Durum yok' }}</span>
                            </div>
                            <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                                <div>
                                    <p class="text-slate-500">Parça</p>
                                    <p class="font-semibold text-slate-900">{{ $formatCount($line->pieces) }}</p>
                                </div>
                                <div>
                                    <p class="text-slate-500">Desi</p>
                                    <p class="font-semibold text-slate-900">{{ $formatDecimal($line->desi) }}</p>
                                </div>
                                <div>
                                    <p class="text-slate-500">Toplam</p>
                                    <p class="font-semibold text-slate-900">{{ $formatMoney($line->total_amount) }}</p>
                                    @if($line->amount_source === 'estimated_from_vat')
                                        <p class="mt-0.5 text-[10px] font-medium text-amber-600">KDV tahmini</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                            Seçili güne ait kayıt bulunamadı.
                        </div>
                    @endforelse
                </div>

                <div class="hidden overflow-hidden rounded-lg border border-slate-200 md:block">
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-fixed divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50/80">
                                <tr class="text-left text-xs font-semibold uppercase tracking-normal text-slate-500">
                                    <th class="w-36 px-3 py-3">Takip No</th>
                                    <th class="w-60 px-3 py-3">Müşteri</th>
                                    <th class="w-28 px-3 py-3">Web Sipariş</th>
                                    <th class="w-32 px-3 py-3">Durum</th>
                                    <th class="w-20 px-3 py-3 text-right">Parça</th>
                                    <th class="w-24 px-3 py-3 text-right">Desi</th>
                                    <th class="w-28 px-3 py-3 text-right">Tutar</th>
                                    <th class="w-28 px-3 py-3 text-right">Ölçüm</th>
                                    <th class="w-32 px-3 py-3 text-right">Toplam</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @forelse($lines as $line)
                                    <tr wire:key="surat-report-row-{{ $line->id }}" class="text-slate-700">
                                        <td class="truncate px-3 py-3 font-mono text-xs">{{ $line->tracking_number ?: '-' }}</td>
                                        <td class="truncate px-3 py-3 font-medium text-slate-900" title="{{ $line->customer_name }}">{{ $line->customer_name ?: '-' }}</td>
                                        <td class="truncate px-3 py-3 font-mono text-xs">{{ $line->web_order_code ?: '-' }}</td>
                                        <td class="truncate px-3 py-3">{{ $line->status ?: '-' }}</td>
                                        <td class="px-3 py-3 text-right">{{ $formatCount($line->pieces) }}</td>
                                        <td class="px-3 py-3 text-right">{{ $formatDecimal($line->desi) }}</td>
                                        <td class="px-3 py-3 text-right">{{ $formatDecimal($line->amount) }}</td>
                                        <td class="px-3 py-3 text-right">{{ $formatDecimal($line->measurement_amount) }}</td>
                                        <td class="px-3 py-3 text-right">
                                            <span class="font-semibold text-slate-900">{{ $formatDecimal($line->total_amount) }}</span>
                                            @if($line->amount_source === 'estimated_from_vat')
                                                <span class="mt-1 block text-[10px] font-medium text-amber-600">KDV tahmini</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-4 py-10 text-center text-sm text-slate-500">Seçili güne ait kayıt bulunamadı.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4">
                    {{ $lines->links() }}
                </div>
            </div>
        </section>

        <aside class="space-y-4">
            <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Günlük kayıtlar</h3>
                        <p class="mt-1 text-xs text-slate-500">{{ $summaryRangeLabel }}</p>
                    </div>
                    <span class="rounded-[6px] bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">{{ $summaries->count() }} gün</span>
                </div>

                <div class="mt-3 space-y-3 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                    <select wire:model.live="summaryPreset" class="min-h-[40px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                        <option value="last_7">Son 7 gün</option>
                        <option value="last_14">Son 14 gün</option>
                        <option value="last_30">Son 30 gün</option>
                        <option value="this_week">Bu hafta</option>
                        <option value="this_month">Bu ay</option>
                        <option value="custom">Özel aralık</option>
                    </select>

                    @if($summaryPreset === 'custom')
                        <div class="grid grid-cols-1 gap-2">
                            <input type="date" wire:model.live="summaryStartDate" class="min-h-[40px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm">
                            <input type="date" wire:model.live="summaryEndDate" class="min-h-[40px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm">
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-[6px] border border-slate-200 bg-white p-2">
                            <p class="text-slate-500">Gönderi</p>
                            <p class="mt-1 font-semibold text-slate-900">{{ $formatCount($summaryTotals['row_count'] ?? 0) }}</p>
                        </div>
                        <div class="rounded-[6px] border border-slate-200 bg-white p-2">
                            <p class="text-slate-500">Toplam</p>
                            <p class="mt-1 font-semibold text-slate-900">{{ $formatMoney($summaryTotals['total_amount'] ?? 0) }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-2">
                    <p class="text-xs text-slate-500">Haftalık ve aylık rapor seçili güne göre hazırlanır.</p>
                    <button
                        type="button"
                        wire:click="exportSelectedWeek"
                        wire:loading.attr="disabled"
                        wire:target="exportSelectedWeek"
                        class="inline-flex min-h-[40px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60"
                    >
                        Haftalık toplu rapor indir
                    </button>
                    <button
                        type="button"
                        wire:click="exportSelectedMonth"
                        wire:loading.attr="disabled"
                        wire:target="exportSelectedMonth"
                        class="inline-flex min-h-[40px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60"
                    >
                        Aylık toplu rapor indir
                    </button>
                    <button
                        type="button"
                        wire:click="exportSummaryRange"
                        wire:loading.attr="disabled"
                        wire:target="exportSummaryRange"
                        class="inline-flex min-h-[40px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-3 py-2 text-xs font-medium text-white transition hover:bg-slate-800 disabled:opacity-60"
                    >
                        Filtre aralığını indir
                    </button>
                </div>

                <div class="mt-3 max-h-[520px] space-y-2 overflow-y-auto pr-1">
                    @forelse($summaries as $summary)
                        @php
                            $summaryDate = \Illuminate\Support\Carbon::parse($summary->report_date)->toDateString();
                            $isSelected = $selectedDate === $summaryDate;
                        @endphp
                        <button
                            type="button"
                            wire:key="surat-summary-{{ $summaryDate }}"
                            wire:click="selectDate('{{ $summaryDate }}')"
                            class="w-full rounded-[8px] border p-3 text-left transition {{ $isSelected ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-slate-50/60 text-slate-700 hover:bg-white' }}"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold {{ $isSelected ? 'text-white' : 'text-slate-900' }}">{{ \Illuminate\Support\Carbon::parse($summary->report_date)->format('d.m.Y') }}</p>
                                    <p class="mt-1 text-xs {{ $isSelected ? 'text-white/70' : 'text-slate-500' }}">{{ $formatCount($summary->row_count) }} gönderi · {{ $formatCount($summary->pieces) }} parça</p>
                                </div>
                                <span class="shrink-0 rounded-[6px] px-2 py-0.5 text-[10px] font-semibold {{ $isSelected ? 'bg-white/15 text-white' : 'bg-white text-slate-700' }}">
                                    {{ $formatMoney($summary->total_amount) }}
                                </span>
                            </div>
                            <div class="mt-2 grid grid-cols-2 gap-2 text-xs {{ $isSelected ? 'text-white/70' : 'text-slate-500' }}">
                                <span>Desi: <strong class="{{ $isSelected ? 'text-white' : 'text-slate-700' }}">{{ $formatDecimal($summary->desi) }}</strong></span>
                                <span>Ölçüm: <strong class="{{ $isSelected ? 'text-white' : 'text-slate-700' }}">{{ $formatMoney($summary->measurement_amount) }}</strong></span>
                            </div>
                        </button>
                    @empty
                        <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                            Seçili filtre aralığında kayıtlı günlük rapor yok.
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="rounded-[10px] border border-slate-200 bg-slate-50/70 p-4">
                <h3 class="text-sm font-semibold text-slate-900">Kontrol mantığı</h3>
                <p class="mt-2 text-sm text-slate-500">
                    Check modülü seçili günün kayıtlı Sürat satırlarını kullanır. Kesin tutar için Sürat’in Tutar + OlcumTutar toplamı alınır; Tutar 0 gelip yalnız KdvTutar doluysa geçici olarak KDV’den brüt tahmin gösterilir.
                </p>
            </section>
        </aside>
    </div>
</div>
