<div class="space-y-6 w-full max-w-full overflow-x-hidden">
    {{-- Mesaj --}}
    @if($message)
        <div class="rounded-lg p-4 {{ 
            $messageType === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 
            ($messageType === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : 
            'bg-blue-50 text-blue-800 border border-blue-200') 
        }}">
            {{ $message }}
        </div>
    @endif

    {{-- Özet İstatistikler --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 lg:gap-3">
        <div class="bg-white rounded-xl p-2 lg:p-3 border border-gray-200 shadow-sm">
            <div class="text-gray-500 text-xs">Toplam Rapor</div>
            <div class="text-base lg:text-xl font-bold text-gray-900">{{ number_format($this->totalStats['total_reports']) }}</div>
        </div>
        <div class="bg-white rounded-xl p-2 lg:p-3 border border-gray-200 shadow-sm">
            <div class="text-gray-500 text-xs">Toplam Sipariş</div>
            <div class="text-base lg:text-xl font-bold text-blue-600">{{ number_format($this->totalStats['total_orders']) }}</div>
        </div>
        <div class="bg-white rounded-xl p-2 lg:p-3 border border-gray-200 shadow-sm">
            <div class="text-gray-500 text-xs">Toplam Hata</div>
            <div class="text-base lg:text-xl font-bold text-red-600">{{ number_format($this->totalStats['total_errors']) }}</div>
        </div>
        <div class="bg-white rounded-xl p-2 lg:p-3 border border-gray-200 shadow-sm">
            <div class="text-gray-500 text-xs">Desi Farkı</div>
            <div class="text-base lg:text-xl font-bold {{ $this->totalStats['total_desi_diff'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                {{ number_format($this->totalStats['total_desi_diff'], 0) }}
            </div>
        </div>
        <div class="bg-white rounded-xl p-2 lg:p-3 border border-gray-200 shadow-sm">
            <div class="text-gray-500 text-xs">Tutar Farkı</div>
            <div class="text-base lg:text-xl font-bold {{ $this->totalStats['total_tutar_diff'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                {{ number_format($this->totalStats['total_tutar_diff'], 0) }} ₺
            </div>
        </div>
    </div>

    {{-- Filtreler --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 lg:gap-4 bg-gray-50 rounded-lg p-3 lg:p-4">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Başlangıç</label>
            <input 
                type="date" 
                wire:model.live="filterDate"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-base sm:text-sm min-h-[44px]"
            >
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Bitiş</label>
            <input 
                type="date" 
                wire:model.live="filterDateEnd"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-base sm:text-sm min-h-[44px]"
            >
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Kargo Firması</label>
            <select 
                wire:model.live="filterCompany"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-base sm:text-sm min-h-[44px]"
            >
                <option value="">Tümü</option>
                @foreach($this->cargoCompanies as $company)
                    <option value="{{ $company }}">{{ $company }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Rapor Tablosu --}}
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden max-w-full">
        <div class="overflow-x-auto max-w-full">
            <table class="w-full divide-y divide-gray-200 table-fixed">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider cursor-pointer hover:bg-gray-100 whitespace-nowrap w-24"
                            wire:click="sortBy('report_date')">
                            Tarih
                            @if($sortField === 'report_date')
                                <svg class="w-3 h-3 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="{{ $sortDirection === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/>
                                </svg>
                            @endif
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider cursor-pointer hover:bg-gray-100 w-[25%]"
                            wire:click="sortBy('name')">
                            Rapor Adı
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider whitespace-nowrap hidden lg:table-cell w-[10%]">
                            Kargo
                        </th>
                        <th class="px-2 py-3 text-center text-xs font-bold uppercase tracking-wider whitespace-nowrap hidden sm:table-cell w-[8%]">
                            Sipariş
                        </th>
                        <th class="px-2 py-3 text-center text-xs font-bold uppercase tracking-wider whitespace-nowrap w-[8%]">
                            Hata
                        </th>
                        <th class="px-2 py-3 text-right text-xs font-bold uppercase tracking-wider whitespace-nowrap hidden md:table-cell w-[12%]">
                            Desi Farkı
                        </th>
                        <th class="px-2 py-3 text-right text-xs font-bold uppercase tracking-wider whitespace-nowrap w-[15%]">
                            Tutar Farkı
                        </th>
                        <th class="px-3 py-3 text-right text-xs font-bold uppercase tracking-wider whitespace-nowrap w-28">
                            İşlem
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($this->reports as $report)
                        <tr class="hover:bg-gray-50 transition border-b border-gray-100">
                            <td class="px-3 py-3 whitespace-nowrap text-xs text-gray-900 font-medium">
                                {{ $report->report_date->format('d.m.Y') }}
                            </td>
                            <td class="px-3 py-3 truncate max-w-0">
                                <div class="truncate" title="{{ $report->name }}">
                                    <span class="font-bold text-gray-800 text-xs">{{ $report->name }}</span>
                                </div>
                                <div class="truncate mt-0.5">
                                    <span class="text-[10px] text-gray-500 flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                        {{ $report->user?->name }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap hidden lg:table-cell">
                                <span class="inline-flex items-center px-2 py-1 rounded text-[10px] font-bold bg-gray-100 text-gray-600 border border-gray-200">
                                    {{ $report->cargo_company ?? '-' }}
                                </span>
                            </td>
                            <td class="px-2 py-3 text-center text-xs whitespace-nowrap text-gray-600 hidden sm:table-cell">
                                {{ number_format($report->total_orders) }}
                            </td>
                            <td class="px-2 py-3 text-center whitespace-nowrap">
                                @if($report->error_count > 0)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-[10px] font-bold bg-red-100 text-red-700">
                                        {{ $report->error_count }}
                                    </span>
                                @else
                                    <span class="text-green-500 font-bold text-xs">OK</span>
                                @endif
                            </td>
                            <td class="px-2 py-3 text-right text-xs whitespace-nowrap font-mono hidden md:table-cell {{ $report->total_desi_diff > 0 ? 'text-red-600' : 'text-green-600' }}">
                                {{ $report->total_desi_diff > 0 ? '+' : '' }}{{ number_format($report->total_desi_diff, 0) }}
                            </td>
                            <td class="px-2 py-3 text-right text-xs font-mono whitespace-nowrap font-bold {{ $report->total_tutar_diff > 0 ? 'text-red-700' : 'text-green-700' }}">
                                {{ $report->total_tutar_diff > 0 ? '+' : '' }}{{ number_format($report->total_tutar_diff, 0) }} ₺
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1">
                                    <button 
                                        wire:click="viewReport({{ $report->id }})"
                                        class="text-blue-600 hover:text-blue-800 p-1.5 hover:bg-blue-50 rounded transition-colors"
                                        title="İncele"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </button>
                                    <button 
                                        wire:click="downloadReport({{ $report->id }})"
                                        class="text-green-600 hover:text-green-800 p-1.5 hover:bg-green-50 rounded transition-colors"
                                        title="İndir"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    </button>
                                    <button 
                                        wire:click="confirmDelete({{ $report->id }})"
                                        class="text-red-600 hover:text-red-800 p-1.5 hover:bg-red-50 rounded transition-colors"
                                        title="Sil"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                <svg class="w-12 h-12 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <p class="text-lg font-medium">Rapor bulunamadı</p>
                                <p class="text-sm">Seçili tarih aralığında kayıtlı rapor yok.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($this->reports->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $this->reports->links() }}
            </div>
        @endif
    </div>

    {{-- Detay Modal --}}
    @if($showDetailModal && $viewingReportId)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeDetailModal"></div>
                
                <div class="relative bg-white rounded-lg max-w-6xl w-full shadow-xl max-h-[90vh] overflow-hidden">
                    @php $report = \App\Models\CargoReport::find($viewingReportId); @endphp
                    
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">{{ $report?->name }}</h3>
                            <p class="text-sm text-gray-500">{{ $report?->report_date?->format('d.m.Y') }} - {{ $report?->cargo_company }}</p>
                        </div>
                        <button wire:click="closeDetailModal" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Filtreler --}}
                    <div class="px-6 py-3 bg-gray-50 border-b flex flex-col sm:flex-row gap-4">
                        <select 
                            wire:model.live="itemFilterErrorType"
                            class="w-full sm:w-auto px-3 py-2 border border-gray-300 rounded-lg text-sm text-base sm:text-sm min-h-[44px]"
                        >
                            <option value="all">Tüm Kayıtlar</option>
                            <option value="errors">Sadece Hatalar</option>
                            <option value="desi_fazla">Desi Fazla</option>
                            <option value="tutar_fazla">Tutar Fazla</option>
                        </select>
                        <select 
                            wire:model.live="itemFilterType"
                            class="w-full sm:w-auto px-3 py-2 border border-gray-300 rounded-lg text-sm text-base sm:text-sm min-h-[44px]"
                        >
                            <option value="all">Tümü</option>
                            <option value="siparis">📦 Siparişler</option>
                            <option value="iade">↩️ İadeler</option>
                            <option value="parca">🔧 Parça Gönderileri</option>
                        </select>
                    </div>

                    <div class="overflow-auto max-h-[60vh]">
                        <table class="w-full divide-y divide-gray-200 table-fixed text-xs">
                            <thead class="bg-gray-800 text-white sticky top-0">
                                <tr>
                                    <th class="px-2 py-2 text-left whitespace-nowrap w-24">Tarih</th>
                                    <th class="px-2 py-2 text-left whitespace-nowrap w-[15%]">Müşteri</th>
                                    <th class="px-2 py-2 text-left whitespace-nowrap w-[12%] hidden lg:table-cell">Stok Kodu</th>
                                    <th class="px-2 py-2 text-left whitespace-nowrap w-[12%] hidden md:table-cell">Takip</th>
                                    <th class="px-2 py-2 text-left whitespace-nowrap w-[30%]">Ürün</th>
                                    <th class="px-1 py-1 text-center whitespace-nowrap w-12 text-[10px] uppercase font-bold text-gray-400">B.Desi</th>
                                    <th class="px-1 py-1 text-center whitespace-nowrap w-12 text-[10px] uppercase font-bold text-white">G.Desi</th>
                                    <th class="px-1 py-1 text-center whitespace-nowrap w-14 text-[10px] uppercase font-bold text-gray-400 hidden sm:table-cell">B.Tutar</th>
                                    <th class="px-1 py-1 text-center whitespace-nowrap w-14 text-[10px] uppercase font-bold text-white">G.Tutar</th>
                                    <th class="px-2 py-2 text-center whitespace-nowrap w-20">Durum</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($viewingItems as $item)
                                    @php
                                        $rowClass = '';
                                        if ($item['is_iade'] ?? false) {
                                            $rowClass = 'bg-purple-50';
                                        } elseif ($item['is_parca_gonderi'] ?? false) {
                                            $rowClass = 'bg-orange-50';
                                        } elseif ($item['has_error'] ?? false) {
                                            $rowClass = 'bg-red-50';
                                        }
                                    @endphp
                                    <tr class="{{ $rowClass }}">
                                        <td class="px-2 py-2 whitespace-nowrap truncate">{{ isset($item['tarih']) ? \Carbon\Carbon::parse($item['tarih'])->format('d.m.Y') : '-' }}</td>
                                        <td class="px-2 py-2 whitespace-nowrap truncate" title="{{ $item['musteri_adi'] ?? '-' }}">{{ Str::limit($item['musteri_adi'] ?? '-', 15) }}</td>
                                        <td class="px-2 py-2 font-mono text-gray-600 whitespace-nowrap truncate hidden lg:table-cell" title="{{ $item['stok_kodu'] ?? '-' }}">{{ Str::limit($item['stok_kodu'] ?? '-', 12) }}</td>
                                        <td class="px-2 py-2 font-mono whitespace-nowrap truncate hidden md:table-cell" title="{{ $item['takip_kodu'] ?? '-' }}">{{ Str::limit($item['takip_kodu'] ?? '-', 12) }}</td>
                                        <td class="px-2 py-2 truncate max-w-0" title="{{ $item['urun_adi'] ?? '-' }}">{{ $item['urun_adi'] ?? '-' }}</td>
                                        <td class="px-1 py-1 text-center whitespace-nowrap text-gray-500">{{ number_format($item['beklenen_desi'] ?? 0, 1) }}</td>
                                        <td class="px-1 py-1 text-center whitespace-nowrap font-bold {{ ($item['desi_fark'] ?? 0) > 2 ? 'text-red-600' : '' }}">
                                            {{ number_format($item['gercek_desi'] ?? 0, 1) }}
                                        </td>
                                        <td class="px-1 py-1 text-center whitespace-nowrap text-gray-500 hidden sm:table-cell">{{ number_format($item['beklenen_tutar'] ?? 0, 0) }}</td>
                                        <td class="px-1 py-1 text-center whitespace-nowrap font-bold {{ ($item['tutar_fark'] ?? 0) > 5 ? 'text-red-600' : '' }}">
                                            {{ number_format($item['gercek_tutar'] ?? 0, 0) }}
                                        </td>
                                        <td class="px-2 py-2 text-center">
                                            @php 
                                                $errorType = $item['error_type'] ?? 'none';
                                                if ($item['is_iade'] ?? false) {
                                                    $error = ['label' => '↩️ İade', 'class' => 'bg-purple-100 text-purple-800'];
                                                } elseif ($item['is_parca_gonderi'] ?? false) {
                                                    $error = ['label' => '🔧 Parça', 'class' => 'bg-orange-100 text-orange-800'];
                                                } else {
                                                    $errorLabels = [
                                                        'none' => ['label' => 'OK', 'class' => 'bg-green-100 text-green-800'],
                                                        'desi_fazla' => ['label' => 'Desi ↑', 'class' => 'bg-red-100 text-red-800'],
                                                        'tutar_fazla' => ['label' => 'Tutar ↑', 'class' => 'bg-red-100 text-red-800'],
                                                        'desi_eksik' => ['label' => 'Desi ↓', 'class' => 'bg-yellow-100 text-yellow-800'],
                                                        'eslesmedi' => ['label' => '?', 'class' => 'bg-gray-100 text-gray-800'],
                                                    ];
                                                    $error = $errorLabels[$errorType] ?? $errorLabels['none'];
                                                }
                                            @endphp
                                            <span class="inline-flex px-1.5 py-0.5 rounded-full text-[10px] font-medium {{ $error['class'] }}">
                                                {{ $error['label'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-200 flex justify-end">
                        <button 
                            wire:click="downloadReport({{ $viewingReportId }})"
                            class="w-full sm:w-auto px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 min-h-[44px]"
                        >
                            Excel İndir
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Silme Onay Modal --}}
    @if($showDeleteModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showDeleteModal', false)"></div>
                
                <div class="relative bg-white rounded-lg max-w-md w-full shadow-xl">
                    <div class="p-6 text-center">
                        <svg class="w-16 h-16 mx-auto text-red-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Raporu Sil</h3>
                        <p class="text-gray-500 mb-6">Bu raporu ve tüm detaylarını silmek istediğinizden emin misiniz?</p>
                        
                        <div class="flex flex-col sm:flex-row justify-center gap-3">
                            <button 
                                wire:click="$set('showDeleteModal', false)"
                                class="w-full sm:w-auto px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50"
                            >
                                İptal
                            </button>
                            <button 
                                wire:click="deleteReport"
                                class="w-full sm:w-auto px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
                            >
                                Sil
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
