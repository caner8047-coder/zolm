<div class="space-y-4 lg:space-y-6">
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                ['Fiyat dağılımı', 'Minimum, ortalama ve maksimum fiyat aralığı'],
                ['Talep yoğunluğu', 'Favori, yorum ve değerlendirme payları'],
                ['Pazar lideri', 'Fiyat ve ilgi bileşik skorunda öne çıkan ürün'],
                ['Takip grubu', 'Seçilen rakipleri aynı radar grubunda izleme'],
            ] as [$title, $description])
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                    <p class="text-sm font-semibold text-slate-900">{{ $title }}</p>
                    <p class="mt-1 text-xs leading-5 text-slate-500">{{ $description }}</p>
                </div>
            @endforeach
        </div>
    </section>

    @include('livewire.partials.trendyol-booster-comparison', ['researchKind' => 'market'])

    @if($marketResults !== [])
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase text-slate-500">Pazar yorumu</p>
                    <h2 class="mt-1 text-lg font-semibold text-slate-900">Grup yoğunluğu ve fiyat boşluğu</h2>
                </div>
                <span class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-1 font-mono text-xs text-slate-600">Grup {{ Str::limit($marketGroupKey, 8, '') }}</span>
            </div>
            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3"><p class="text-xs text-slate-500">Lider favori payı</p><p class="mt-1 text-lg font-semibold text-slate-900">{{ data_get($marketSummary, 'top_favorite_share') !== null ? '%' . number_format((float) data_get($marketSummary, 'top_favorite_share'), 1, ',', '.') : '-' }}</p></div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3"><p class="text-xs text-slate-500">Ortalama puan</p><p class="mt-1 text-lg font-semibold text-slate-900">{{ data_get($marketSummary, 'average_rating') !== null ? number_format((float) data_get($marketSummary, 'average_rating'), 2, ',', '.') : '-' }}</p></div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3"><p class="text-xs text-slate-500">Toplam favori</p><p class="mt-1 text-lg font-semibold text-slate-900">{{ data_get($marketSummary, 'total_favorites') !== null ? number_format((int) data_get($marketSummary, 'total_favorites'), 0, ',', '.') : '-' }}</p></div>
            </div>
        </section>
    @endif
</div>
