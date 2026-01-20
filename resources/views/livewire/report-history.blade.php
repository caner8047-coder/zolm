<div>
    <!-- Flash Messages -->
    @if(session('success'))
    <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg flex items-center justify-between">
        <span>{{ session('success') }}</span>
        <button onclick="this.parentElement.remove()" class="text-green-500 hover:text-green-700">&times;</button>
    </div>
    @endif

    @if(session('error'))
    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg flex items-center justify-between">
        <span>{{ session('error') }}</span>
        <button onclick="this.parentElement.remove()" class="text-red-500 hover:text-red-700">&times;</button>
    </div>
    @endif

    <!-- Page Header -->
    <div class="mb-6 lg:mb-8 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-xl lg:text-2xl font-bold text-gray-900">Geçmiş Raporlar</h1>
            <p class="text-gray-500 mt-1 text-sm lg:text-base">Daha önce oluşturulan raporları görüntüleyin, indirin veya silin</p>
        </div>
        
        <!-- İstatistikler -->
        <div class="flex flex-wrap items-center gap-2 text-xs sm:text-sm">
            <div class="px-2 sm:px-3 py-1 bg-gray-100 rounded-lg">
                <span class="text-gray-500">Toplam:</span>
                <span class="font-medium text-gray-900">{{ $this->totalReports }} rapor</span>
            </div>
            <div class="px-2 sm:px-3 py-1 bg-gray-100 rounded-lg">
                <span class="text-gray-500">Tahmini boyut:</span>
                <span class="font-medium text-gray-900">{{ $this->totalSize }}</span>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg border border-gray-200 p-4 lg:p-6 mb-6">
        <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
            <!-- Report Type -->
            <div>
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2">Rapor Türü</label>
                <select wire:model.live="reportType" class="w-full px-3 py-2 sm:px-4 border border-gray-300 rounded-lg text-sm">
                    <option value="all">Tümü</option>
                    <option value="production">Üretim</option>
                    <option value="operation">Operasyon</option>
                </select>
            </div>

            <!-- Period -->
            <div>
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2">Periyot</label>
                <select wire:model.live="period" class="w-full px-3 py-2 sm:px-4 border border-gray-300 rounded-lg text-sm">
                    <option value="daily">Günlük</option>
                    <option value="weekly">Haftalık</option>
                    <option value="monthly">Aylık</option>
                </select>
            </div>

            <!-- Start Date -->
            <div>
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2">Başlangıç</label>
                <input type="date" wire:model.live="startDate" class="w-full px-3 py-2 sm:px-4 border border-gray-300 rounded-lg text-sm">
            </div>

            <!-- End Date -->
            <div>
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2">Bitiş</label>
                <input type="date" wire:model.live="endDate" class="w-full px-3 py-2 sm:px-4 border border-gray-300 rounded-lg text-sm">
            </div>
        </div>
    </div>

    <!-- Bulk Actions Bar -->
    @if(count($selectedReports) > 0)
    <div class="bg-red-50 border border-red-200 rounded-lg p-3 lg:p-4 mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center space-x-3">
            <span class="text-red-700 font-medium text-sm">{{ count($selectedReports) }} rapor seçildi</span>
        </div>
        <div class="flex items-center gap-3">
            <button wire:click="$set('selectedReports', [])" class="text-sm text-gray-600 hover:text-gray-900">
                Temizle
            </button>
            <button 
                wire:click="deleteSelected"
                wire:confirm="Seçili {{ count($selectedReports) }} raporu silmek istediğinize emin misiniz? Bu işlem geri alınamaz."
                class="px-3 sm:px-4 py-2 bg-red-600 text-white text-xs sm:text-sm font-medium rounded-lg hover:bg-red-700"
            >
                Seçilenleri Sil
            </button>
        </div>
    </div>
    @endif

    <!-- Cleanup Button -->
    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center space-x-2">
            <input type="checkbox" wire:model.live="selectAll" id="selectAll" class="rounded border-gray-300 text-gray-900 focus:ring-gray-500">
            <label for="selectAll" class="text-sm text-gray-600">Tümünü Seç</label>
        </div>
        
        <button 
            wire:click="cleanupOldReports"
            wire:confirm="30 günden eski tüm raporları silmek istediğinize emin misiniz?"
            class="text-sm text-gray-500 hover:text-red-600 flex items-center space-x-1"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
            <span>Eski Raporları Temizle (30+ gün)</span>
        </button>
    </div>

    <!-- Reports List -->
    <div class="space-y-4">
        @forelse($this->reports as $date => $dateReports)
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <!-- Date Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between p-3 lg:p-4 hover:bg-gray-50 gap-2 sm:gap-0">
                <button 
                    wire:click="toggleDate('{{ $date }}')"
                    class="flex-1 flex items-center space-x-2 sm:space-x-3 text-left"
                >
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-gray-400 transform transition-transform flex-shrink-0 {{ in_array($date, $expandedDates) ? 'rotate-90' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                    <span class="font-medium text-gray-900 text-sm sm:text-base">{{ \Carbon\Carbon::parse($date)->format('d.m.Y') }}</span>
                    <span class="text-xs sm:text-sm text-gray-500">({{ $dateReports->count() }} rapor)</span>
                </button>
                
                <div class="flex items-center gap-2 ml-6 sm:ml-0">
                    <button 
                        wire:click.stop="downloadAllForDate('{{ $date }}')"
                        class="px-2 sm:px-3 py-1 text-xs font-medium text-gray-600 border border-gray-300 rounded hover:bg-gray-50"
                    >
                        toplu indir
                    </button>
                    <button 
                        wire:click.stop="deleteByDate('{{ $date }}')"
                        wire:confirm="{{ $date }} tarihli tüm raporları silmek istediğinize emin misiniz?"
                        class="px-2 sm:px-3 py-1 text-xs font-medium text-red-600 border border-red-300 rounded hover:bg-red-50"
                    >
                        tarihi sil
                    </button>
                </div>
            </div>

            <!-- Expanded Content -->
            @if(in_array($date, $expandedDates))
            <div class="border-t border-gray-200 divide-y divide-gray-100">
                @foreach($dateReports as $report)
                <div class="p-4 bg-gray-50">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center space-x-3">
                            <!-- Checkbox -->
                            <input 
                                type="checkbox" 
                                wire:model.live="selectedReports" 
                                value="{{ $report->id }}"
                                class="rounded border-gray-300 text-gray-900 focus:ring-gray-500"
                            >
                            <span class="text-sm font-medium text-gray-900">{{ $report->original_filename }}</span>
                            <span class="ml-2 px-2 py-0.5 text-xs rounded {{ $report->profile?->type === 'production' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700' }}">
                                {{ $report->profile?->type === 'production' ? 'Üretim' : 'Operasyon' }}
                            </span>
                            @if($report->profile?->is_ai_generated)
                            <span class="px-2 py-0.5 text-xs rounded bg-purple-100 text-purple-700">AI</span>
                            @endif
                        </div>
                        <div class="flex items-center space-x-3">
                            <span class="text-xs text-gray-400">{{ $report->created_at->format('H:i') }}</span>
                            <button 
                                wire:click="confirmDelete({{ $report->id }})"
                                class="text-red-400 hover:text-red-600"
                                title="Sil"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <div class="space-y-2 ml-7">
                        @foreach($report->files as $file)
                        <div class="flex items-center justify-between py-2 px-3 bg-white rounded border border-gray-200">
                            <span class="text-sm text-gray-700">{{ $file->filename }}</span>
                            <a 
                                href="{{ route('download', $file->id) }}"
                                class="text-sm text-gray-600 hover:text-gray-900"
                            >
                                xls dışarı aktar
                            </a>
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
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="text-gray-500">Seçilen kriterlere uygun rapor bulunamadı.</p>
        </div>
        @endforelse
    </div>

    <!-- Delete Confirmation Modal -->
    @if($showDeleteModal)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <div class="flex items-center space-x-3 mb-4">
                <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-medium text-gray-900">Raporu Sil</h3>
                    <p class="text-sm text-gray-500">Bu işlem geri alınamaz</p>
                </div>
            </div>
            
            <p class="text-gray-600 mb-6">Bu raporu ve tüm dosyalarını silmek istediğinize emin misiniz?</p>
            
            <div class="flex justify-end space-x-3">
                <button 
                    wire:click="cancelDelete"
                    class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                >
                    İptal
                </button>
                <button 
                    wire:click="deleteReport"
                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
                >
                    Sil
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
