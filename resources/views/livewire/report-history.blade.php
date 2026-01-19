<div>
    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Geçmiş Raporlar</h1>
        <p class="text-gray-500 mt-1">Daha önce oluşturulan raporları görüntüleyin ve indirin</p>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg border border-gray-200 p-6 mb-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Report Type -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Rapor Türü</label>
                <select wire:model.live="reportType" class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="all">Tümü</option>
                    <option value="production">Üretim</option>
                    <option value="operation">Operasyon</option>
                </select>
            </div>

            <!-- Period -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Periyot</label>
                <select wire:model.live="period" class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="daily">Günlük</option>
                    <option value="weekly">Haftalık</option>
                    <option value="monthly">Aylık</option>
                    <option value="sixmonth">6 Aylık</option>
                    <option value="yearly">Yıllık</option>
                </select>
            </div>

            <!-- Start Date -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Başlangıç</label>
                <input type="date" wire:model.live="startDate" class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm">
            </div>

            <!-- End Date -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Bitiş</label>
                <input type="date" wire:model.live="endDate" class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
        </div>

        <p class="text-xs text-gray-400 mt-4">
            #kullanıcı isterse 1 yıllık rapor istesin. sayfada listelenen raporlarki xls dosyalarını gün gün dosyalayıp tek bir dosya olarak zip olarak dışarı aktarılır
        </p>
    </div>

    <!-- Reports List -->
    <div class="space-y-4">
        @forelse($this->reports as $date => $dateReports)
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <!-- Date Header -->
            <button 
                wire:click="toggleDate('{{ $date }}')"
                class="w-full flex items-center justify-between p-4 hover:bg-gray-50 transition-colors"
            >
                <div class="flex items-center space-x-3">
                    <svg class="w-5 h-5 text-gray-400 transform transition-transform {{ in_array($date, $expandedDates) ? 'rotate-90' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                    <span class="font-medium text-gray-900">{{ \Carbon\Carbon::parse($date)->format('d.m.Y') }}</span>
                    <span class="text-sm text-gray-500">({{ $dateReports->count() }} rapor)</span>
                </div>
                <button 
                    wire:click.stop="downloadAllForDate('{{ $date }}')"
                    class="px-3 py-1 text-xs font-medium text-gray-600 border border-gray-300 rounded hover:bg-gray-50"
                >
                    toplu indir
                </button>
            </button>

            <!-- Expanded Content -->
            @if(in_array($date, $expandedDates))
            <div class="border-t border-gray-200 divide-y divide-gray-100">
                @foreach($dateReports as $report)
                <div class="p-4 bg-gray-50">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <span class="text-sm font-medium text-gray-900">{{ $report->original_filename }}</span>
                            <span class="ml-2 px-2 py-0.5 text-xs rounded {{ $report->profile?->type === 'production' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700' }}">
                                {{ $report->profile?->type === 'production' ? 'Üretim' : 'Operasyon' }}
                            </span>
                        </div>
                        <span class="text-xs text-gray-400">{{ $report->created_at->format('H:i') }}</span>
                    </div>
                    
                    <div class="space-y-2">
                        @foreach($report->files as $file)
                        <div class="flex items-center justify-between py-2 px-3 bg-white rounded border border-gray-200">
                            <span class="text-sm text-gray-700">{{ $file->filename }}</span>
                            <button 
                                wire:click="downloadFile({{ $file->id }})"
                                class="text-sm text-gray-600 hover:text-gray-900"
                            >
                                xls dışarı aktar
                            </button>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @empty
        <div class="bg-white rounded-lg border border-gray-200 p-8 text-center">
            <p class="text-gray-500">Seçilen kriterlere uygun rapor bulunamadı.</p>
        </div>
        @endforelse
    </div>
</div>
