<div class="space-y-6 overflow-x-hidden">
    {{-- ViewMode Başlık (all_errors veya all_compensations) --}}
    @if($viewMode !== 'dashboard')
        <div class="flex flex-col sm:flex-row items-center gap-4 mb-4 flex-1">
            <div class="flex items-center gap-4 w-full sm:w-auto">
                <button wire:click="backToDashboard" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </button>
                <h2 class="text-xl font-bold text-gray-900 whitespace-nowrap">
                    @if($viewMode === 'all_errors')
                        Tüm Desi Hataları
                    @else
                        Tüm Tazmin Talepleri
                    @endif
                </h2>
            </div>
            <div class="flex-1 w-full max-w-md sm:ml-4">
                <div class="relative">
                    <input type="text" wire:model.live.debounce.300ms="search" 
                        class="w-full pl-10 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 text-base sm:text-sm min-h-[44px]" 
                        placeholder="Müşteri, takip kodu veya ürün ara...">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tüm Hatalar Listesi --}}
        @if($viewMode === 'all_errors')
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                {{-- Desktop: Tablo Görünümü --}}
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 text-gray-500">
                        <tr>
                                <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider whitespace-nowrap">Tarih</th>
                                <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider">Müşteri</th>
                                <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider">Ürün</th>
                                <th class="px-2 py-3 text-center text-xs font-bold uppercase tracking-wider whitespace-nowrap w-20">Yanlış</th>
                                <th class="px-2 py-3 text-center text-xs font-bold uppercase tracking-wider whitespace-nowrap w-20">Doğru</th>
                                <th class="px-2 py-3 text-right text-xs font-bold uppercase tracking-wider whitespace-nowrap w-24">Fark</th>
                                <th class="px-3 py-3 text-center text-xs font-bold uppercase tracking-wider whitespace-nowrap w-32">İşlem</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($this->allErrors as $error)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-3 text-xs text-gray-500 whitespace-nowrap font-medium">{{ $error->tarih?->format('d.m.Y') }}</td>
                                    <td class="px-3 py-3">
                                        <span class="text-xs font-bold text-gray-800 line-clamp-1" title="{{ $error->musteri_adi }}">{{ $error->musteri_adi }}</span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <span class="text-xs text-gray-600 line-clamp-1" title="{{ $error->urun_adi }}">{{ $error->urun_adi }}</span>
                                    </td>
                                    <td class="px-2 py-3 text-center text-xs text-red-600 font-bold whitespace-nowrap">{{ number_format($error->gercek_desi, 0) }}</td>
                                    <td class="px-2 py-3 text-center text-xs text-green-600 font-bold whitespace-nowrap">{{ number_format($error->beklenen_desi, 0) }}</td>
                                    <td class="px-2 py-3 text-right text-xs font-bold {{ $error->tutar_fark > 0 ? 'text-red-600' : 'text-green-600' }} whitespace-nowrap font-mono">
                                        {{ $error->tutar_fark > 0 ? '+' : '' }}{{ number_format($error->tutar_fark, 0) }} ₺
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <button wire:click="openCreateModal({{ $error->id }})" 
                                            class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-blue-700 bg-blue-100 hover:bg-blue-200 transition-colors">
                                            Tazmin Oluştur
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-400">Hata bulunamadı</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Mobile: Kart Görünümü --}}
                <div class="lg:hidden divide-y divide-gray-200">
                    @forelse($this->allErrors as $error)
                        <div class="p-4 hover:bg-gray-50">
                            {{-- Üst: Tarih ve Fark --}}
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-medium text-gray-500">{{ $error->tarih?->format('d.m.Y') }}</span>
                                <span class="text-sm font-bold font-mono {{ $error->tutar_fark > 0 ? 'text-red-600' : 'text-green-600' }}">
                                    {{ $error->tutar_fark > 0 ? '+' : '' }}{{ number_format($error->tutar_fark, 0) }} ₺
                                </span>
                            </div>

                            {{-- Müşteri ve Ürün --}}
                            <p class="text-sm font-bold text-gray-800 line-clamp-1 mb-1">{{ $error->musteri_adi }}</p>
                            <p class="text-xs text-gray-600 line-clamp-2 mb-3">{{ $error->urun_adi }}</p>

                            {{-- Desi Bilgileri --}}
                            <div class="grid grid-cols-2 gap-3 text-center bg-gray-50 rounded-lg p-2 mb-3">
                                <div>
                                    <p class="text-[10px] text-gray-400">Yanlış Desi</p>
                                    <p class="text-sm font-bold text-red-600">{{ number_format($error->gercek_desi, 0) }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-gray-400">Doğru Desi</p>
                                    <p class="text-sm font-bold text-green-600">{{ number_format($error->beklenen_desi, 0) }}</p>
                                </div>
                            </div>

                            {{-- Aksiyon --}}
                            <button wire:click="openCreateModal({{ $error->id }})" 
                                class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 text-sm font-medium">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Tazmin Oluştur
                            </button>
                        </div>
                    @empty
                        <div class="p-8 text-center text-gray-400">Hata bulunamadı</div>
                    @endforelse
                </div>

                <div class="px-4 py-3 bg-gray-50 border-t">
                    {{ $this->allErrors->links() }}
                </div>
            </div>
        @endif


        {{-- Tüm Tazminler Listesi --}}
        @if($viewMode === 'all_compensations')
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                {{-- Desktop: Tablo Görünümü --}}
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider whitespace-nowrap">Tarih</th>
                                <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider">Müşteri</th>
                                <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider whitespace-nowrap">Sebep</th>
                                <th class="px-3 py-3 text-right text-xs font-bold uppercase tracking-wider whitespace-nowrap w-24">Talep</th>
                                <th class="px-3 py-3 text-right text-xs font-bold uppercase tracking-wider whitespace-nowrap w-24">Onaylanan</th>
                                <th class="px-3 py-3 text-center text-xs font-bold uppercase tracking-wider whitespace-nowrap w-28">Durum</th>
                                <th class="px-3 py-3 text-center text-xs font-bold uppercase tracking-wider whitespace-nowrap">Belgeler</th>
                                <th class="px-3 py-3 text-center text-xs font-bold uppercase tracking-wider whitespace-nowrap w-20">İşlem</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($this->allCompensations as $comp)
                                @php $sebepInfo = $comp->sebep_info; $durumInfo = $comp->durum_info; @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-3 text-xs text-gray-900 font-medium whitespace-nowrap">{{ $comp->tarih->format('d.m.Y') }}</td>
                                    <td class="px-3 py-3">
                                        <span class="text-xs font-bold text-gray-800 line-clamp-1" title="{{ $comp->musteri_adi }}">{{ $comp->musteri_adi }}</span>
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap">
                                        <span class="text-xs text-gray-500">{{ $sebepInfo['icon'] }} {{ $sebepInfo['label'] }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-right text-xs font-medium text-gray-900 whitespace-nowrap font-mono">{{ number_format($comp->talep_tutari, 0) }} ₺</td>
                                    <td class="px-3 py-3 text-right text-xs font-bold text-green-600 whitespace-nowrap font-mono">{{ number_format($comp->onaylanan_tutar, 0) }} ₺</td>
                                    <td class="px-3 py-3 text-center whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide
                                            {{ $durumInfo['color'] === 'green' ? 'bg-green-100 text-green-800' : 
                                               ($durumInfo['color'] === 'yellow' ? 'bg-yellow-100 text-yellow-800' : 
                                               ($durumInfo['color'] === 'red' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')) }}">
                                            {{ $durumInfo['icon'] }} {{ $durumInfo['label'] }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-center whitespace-nowrap">
                                        <div class="flex justify-center gap-1">
                                            <a href="{{ route('compensation.petition', $comp->id) }}" class="text-[10px] bg-white hover:bg-gray-50 text-gray-700 px-1.5 py-1 rounded border border-gray-300 transition-colors" title="Dilekçe İndir">
                                                📄 Dilekçe
                                            </a>
                                            <a href="{{ route('compensation.form', $comp->id) }}" class="text-[10px] bg-white hover:bg-gray-50 text-gray-700 px-1.5 py-1 rounded border border-gray-300 transition-colors" title="Form İndir">
                                                📝 Form
                                            </a>
                                            <a href="{{ route('compensation.download-all', $comp->id) }}" class="text-[10px] bg-blue-50 hover:bg-blue-100 text-blue-700 px-1.5 py-1 rounded border border-blue-200 transition-colors" title="Tümünü İndir (ZIP)">
                                                📦 ZIP
                                            </a>
                                            <button wire:click="openPetitionModal({{ $comp->id }})" class="inline-flex items-center gap-1 text-[10px] bg-purple-50 hover:bg-purple-100 text-purple-700 px-1.5 py-1 rounded border border-purple-200 transition-colors" title="Dilekçe İçeriği Düzenle">
                                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M16.0001 4.00003L17.0858 7.31422L20.4001 8.40003L17.0858 9.48584L16.0001 12.8L14.9142 9.48584L11.6001 8.40003L14.9142 7.31422L16.0001 4.00003Z" fill="currentColor"/><path d="M8.00006 4.00003L9.08581 7.31422L12.4001 8.40003L9.08581 9.48584L8.00006 12.8L6.91425 9.48584L3.60006 8.40003L6.91425 7.31422L8.00006 4.00003Z" fill="currentColor" fill-opacity="0.5"/><path d="M12.0001 14L13.0858 17.3142L16.4001 18.4L13.0858 19.4858L12.0001 22.8L10.9142 19.4858L7.60006 18.4L10.9142 17.3142L12.0001 14Z" fill="currentColor"/></svg>
                                                AI
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-center whitespace-nowrap">
                                        <button wire:click="openStatusModal({{ $comp->id }})" class="text-gray-400 hover:text-blue-600 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-gray-400">Tazmin talebi bulunamadı</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Mobile: Kart Görünümü --}}
                <div class="lg:hidden divide-y divide-gray-200">
                    @forelse($this->allCompensations as $comp)
                        @php $sebepInfo = $comp->sebep_info; $durumInfo = $comp->durum_info; @endphp
                        <div class="p-4 hover:bg-gray-50">
                            {{-- Üst: Tarih ve Durum --}}
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-medium text-gray-500">{{ $comp->tarih->format('d.m.Y') }}</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold
                                    {{ $durumInfo['color'] === 'green' ? 'bg-green-100 text-green-800' : 
                                       ($durumInfo['color'] === 'yellow' ? 'bg-yellow-100 text-yellow-800' : 
                                       ($durumInfo['color'] === 'red' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')) }}">
                                    {{ $durumInfo['icon'] }} {{ $durumInfo['label'] }}
                                </span>
                            </div>

                            {{-- Müşteri ve Sebep --}}
                            <p class="text-sm font-bold text-gray-800 line-clamp-1 mb-1">{{ $comp->musteri_adi }}</p>
                            <p class="text-xs text-gray-500 mb-3">{{ $sebepInfo['icon'] }} {{ $sebepInfo['label'] }}</p>

                            {{-- Tutar Bilgileri --}}
                            <div class="grid grid-cols-2 gap-3 text-center bg-gray-50 rounded-lg p-2 mb-3">
                                <div>
                                    <p class="text-[10px] text-gray-400">Talep Edilen</p>
                                    <p class="text-sm font-bold text-gray-900">{{ number_format($comp->talep_tutari, 0) }} ₺</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-gray-400">Onaylanan</p>
                                    <p class="text-sm font-bold text-green-600">{{ number_format($comp->onaylanan_tutar, 0) }} ₺</p>
                                </div>
                            </div>

                            {{-- Belgeler --}}
                            <div class="flex flex-wrap gap-2 mb-3">
                                <a href="{{ route('compensation.petition', $comp->id) }}" class="text-xs bg-white text-gray-700 px-2 py-1.5 rounded border border-gray-300">
                                    📄 Dilekçe
                                </a>
                                <a href="{{ route('compensation.form', $comp->id) }}" class="text-xs bg-white text-gray-700 px-2 py-1.5 rounded border border-gray-300">
                                    📝 Form
                                </a>
                                <a href="{{ route('compensation.download-all', $comp->id) }}" class="text-xs bg-blue-50 text-blue-700 px-2 py-1.5 rounded border border-blue-200">
                                    📦 ZIP
                                </a>
                                <button wire:click="openPetitionModal({{ $comp->id }})" class="text-xs bg-purple-50 text-purple-700 px-2 py-1.5 rounded border border-purple-200">
                                    ✨ AI
                                </button>
                            </div>

                            {{-- Düzenle Butonu --}}
                            <button wire:click="openStatusModal({{ $comp->id }})" 
                                class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm font-medium">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                Durum Güncelle
                            </button>
                        </div>
                    @empty
                        <div class="p-8 text-center text-gray-400">Tazmin talebi bulunamadı</div>
                    @endforelse
                </div>

                <div class="px-4 py-3 bg-gray-50 border-t">
                    {{ $this->allCompensations->links() }}
                </div>
            </div>
        @endif
    @else
    {{-- Dashboard View --}}
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
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3 lg:gap-4">
        <div class="bg-white rounded-xl p-3 lg:p-4 border border-gray-200 shadow-sm">
            <div class="text-gray-500 text-xs">Toplam Tazmin</div>
            <div class="text-lg lg:text-2xl font-bold text-gray-900">{{ $this->stats['total'] }}</div>
        </div>
        <div class="bg-white rounded-xl p-3 lg:p-4 border border-gray-200 shadow-sm">
            <div class="text-gray-500 text-xs">Bekleyen</div>
            <div class="text-lg lg:text-2xl font-bold text-yellow-600">{{ $this->stats['pending'] }}</div>
        </div>
        <div class="bg-white rounded-xl p-3 lg:p-4 border border-gray-200 shadow-sm">
            <div class="text-gray-500 text-xs">Tamamlanan</div>
            <div class="text-lg lg:text-2xl font-bold text-green-600">{{ $this->stats['completed'] }}</div>
        </div>
        <div class="bg-white rounded-xl p-3 lg:p-4 border border-gray-200 shadow-sm">
            <div class="text-gray-500 text-xs">Talep Edilen</div>
            <div class="text-lg lg:text-2xl font-bold text-blue-600">{{ number_format($this->stats['total_claimed'], 0) }} ₺</div>
        </div>
        <div class="bg-white rounded-xl p-3 lg:p-4 border border-gray-200 shadow-sm">
            <div class="text-gray-500 text-xs">Onaylanan</div>
            <div class="text-lg lg:text-2xl font-bold text-green-600">{{ number_format($this->stats['total_approved'], 0) }} ₺</div>
        </div>
        <div class="bg-white rounded-xl p-3 lg:p-4 border border-gray-200 shadow-sm">
            <div class="text-gray-500 text-xs">Başarı Oranı</div>
            <div class="text-lg lg:text-2xl font-bold text-purple-600">%{{ $this->stats['success_rate'] }}</div>
        </div>
    </div>

    {{-- Grafik Paneli --}}


    {{-- Alt Paneller --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
        {{-- Son Tespit Edilen Desi Hataları --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-red-50 to-red-100 border-b border-red-200">
                <h3 class="font-medium text-red-900 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    En Son Tespit Edilen Desi Hataları
                </h3>
            </div>
            
            <div class="divide-y divide-gray-100">
                @forelse($this->recentErrors as $error)
                    <div class="px-6 py-3 hover:bg-gray-50 flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-gray-500">{{ $error->tarih?->format('d.m.Y') }}</span>
                                <span class="font-medium text-gray-900 truncate">{{ $error->musteri_adi }}</span>
                            </div>
                            <div class="text-sm text-gray-500 truncate">{{ $error->urun_adi }}</div>
                        </div>
                        <div class="flex items-center gap-4 flex-shrink-0">
                            <div class="text-center">
                                <div class="text-xs text-gray-400">Yanlış</div>
                                <div class="text-red-600 font-bold">{{ number_format($error->gercek_desi, 0) }}</div>
                            </div>
                            <div class="text-center">
                                <div class="text-xs text-gray-400">Doğru</div>
                                <div class="text-green-600 font-bold">{{ number_format($error->beklenen_desi, 0) }}</div>
                            </div>
                            <button 
                                wire:click="openCreateModal({{ $error->id }})"
                                class="text-blue-600 hover:text-blue-800 p-1 hover:bg-blue-50 rounded"
                                title="Tazmin Oluştur"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-8 text-center text-gray-400">
                        Henüz tespit edilen hata yok
                    </div>
                @endforelse
            </div>

            @if($this->recentErrors->count() > 0)
                <div class="px-6 py-3 bg-gray-50 border-t">
                    <button wire:click="showAllErrors" class="text-sm text-blue-600 hover:text-blue-800">Tümünü Gör →</button>
                </div>
            @endif
        </div>

        {{-- Son Oluşturulan Tazminler --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-green-50 to-green-100 border-b border-green-200 flex justify-between items-center">
                <h3 class="font-medium text-green-900 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    En Son Oluşturulan Tazminler
                </h3>
                <button 
                    wire:click="openCreateModal"
                    class="text-sm text-green-700 hover:text-green-900 flex items-center gap-1"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Yeni
                </button>
            </div>
            
            <div class="divide-y divide-gray-100">
                @forelse($this->recentCompensations as $comp)
                    @php $sebepInfo = $comp->sebep_info; $durumInfo = $comp->durum_info; @endphp
                    <div class="px-6 py-3 hover:bg-gray-50">
                        <div class="flex items-center justify-between mb-1">
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-gray-500">{{ $comp->tarih->format('d.m.Y') }}</span>
                                <span class="font-medium text-gray-900">{{ $comp->musteri_adi }}</span>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                {{ $durumInfo['color'] === 'green' ? 'bg-green-100 text-green-800' : 
                                   ($durumInfo['color'] === 'yellow' ? 'bg-yellow-100 text-yellow-800' : 
                                   ($durumInfo['color'] === 'red' ? 'bg-red-100 text-red-800' : 
                                   ($durumInfo['color'] === 'blue' ? 'bg-blue-100 text-blue-800' : 
                                   'bg-gray-100 text-gray-800'))) }}">
                                {{ $durumInfo['icon'] }} {{ $durumInfo['label'] }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-500 flex items-center gap-2">
                                <span>{{ $sebepInfo['icon'] }} {{ $sebepInfo['label'] }}</span>
                                @if($comp->tracking_url)
                                    <a href="{{ $comp->tracking_url }}" target="_blank" 
                                       class="text-blue-600 hover:underline text-xs">
                                        {{ $comp->takip_kodu }}
                                    </a>
                                @endif
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-medium text-gray-900">{{ number_format($comp->talep_tutari, 0) }} ₺</span>
                                <button 
                                    wire:click="openStatusModal({{ $comp->id }})"
                                    class="text-gray-400 hover:text-gray-600 p-1 hover:bg-gray-100 rounded"
                                    title="Durum Güncelle"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-8 text-center text-gray-400">
                        Henüz tazmin talebi yok
                    </div>
                @endforelse
            </div>

            @if($this->recentCompensations->count() > 0)
                <div class="px-6 py-3 bg-gray-50 border-t">
                    <button wire:click="showAllCompensations" class="text-sm text-blue-600 hover:text-blue-800">Tümünü Gör →</button>
                </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Yeni Tazmin Modalı --}}
    @if($showCreateModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showCreateModal', false)"></div>
                
                <div class="relative bg-white rounded-lg max-w-lg w-full shadow-xl sm:my-8">
                    <form wire:submit="createCompensation" class="flex flex-col max-h-[85vh]">
                        {{-- Header --}}
                        <div class="flex-none px-5 py-4 border-b border-gray-200 bg-gray-50 rounded-t-lg">
                            <h3 class="text-lg font-bold text-gray-900">Yeni Tazmin Talebi</h3>
                        </div>

                        {{-- Scrollable Content --}}
                        <div class="flex-1 overflow-y-auto px-5 py-4">
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">TARİH</label>
                                        <input type="date" wire:model="newCompensation.tarih"
                                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">KARGO FİRMASI</label>
                                        <select wire:model="newCompensation.cargo_company"
                                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                            <option value="Sürat Kargo">Sürat Kargo</option>
                                            <option value="MNG Kargo">MNG Kargo</option>
                                            <option value="Yurtiçi Kargo">Yurtiçi Kargo</option>
                                            <option value="Aras Kargo">Aras Kargo</option>
                                            <option value="PTT Kargo">PTT Kargo</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">MÜŞTERİ ADI</label>
                                    <input type="text" wire:model="newCompensation.musteri_adi"
                                           class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 text-sm"
                                           placeholder="Müşteri adını giriniz">
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">TAKİP KODU</label>
                                        <input type="text" wire:model="newCompensation.takip_kodu"
                                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 text-sm"
                                               placeholder="Opsiyonel">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">TALEP TUTARI (₺)</label>
                                        <input type="number" wire:model="newCompensation.talep_tutari" step="0.01"
                                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">SEBEP</label>
                                    <select wire:model="newCompensation.sebep"
                                            class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                        @foreach(\App\Models\Compensation::SEBEPLER as $key => $info)
                                            <option value="{{ $key }}">{{ $info['icon'] }} {{ $info['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">ÜRÜN ADI</label>
                                    <input type="text" wire:model="newCompensation.urun_adi"
                                           class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 text-sm"
                                           placeholder="Opsiyonel - Ürün adını giriniz">
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">AÇIKLAMA</label>
                                    <textarea wire:model="newCompensation.aciklama" rows="2"
                                              class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 text-sm"
                                              placeholder="Detaylı açıklama (opsiyonel)"></textarea>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">KANIT GÖRSELLERİ (MAX 5)</label>
                                    <div class="mt-1 flex justify-center px-4 py-4 border-2 border-gray-300 border-dashed rounded-md hover:border-blue-400 transition-colors bg-gray-50">
                                        <div class="space-y-1 text-center">
                                            <svg class="mx-auto h-8 w-8 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                            <div class="flex text-xs text-gray-600 justify-center">
                                                <label for="file-upload" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus:underline px-1">
                                                    <span>Dosya Seç</span>
                                                    <input id="file-upload" wire:model="attachments" name="file-upload" type="file" class="sr-only" multiple accept="image/*">
                                                </label>
                                            </div>
                                            <p class="text-[10px] text-gray-500">PNG, JPG, GIF (Max 5MB)</p>
                                        </div>
                                    </div>
                                    @if($attachments)
                                        <div class="mt-2 grid grid-cols-4 sm:grid-cols-5 gap-2">
                                            @foreach($attachments as $file)
                                                <div class="relative group">
                                                    <img src="{{ $file->temporaryUrl() }}" class="h-12 w-full object-cover rounded border border-gray-200">
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Footer --}}
                        <div class="flex-none px-5 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-3 rounded-b-lg">
                            <button type="button" wire:click="$set('showCreateModal', false)"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                İptal
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-sm">
                                Oluştur
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Petition Edit Modal --}}
    @if($showPetitionModal)
        <div class="fixed inset-0 z-10 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showPetitionModal', false)"></div>
                
                <div class="relative bg-white rounded-lg max-w-2xl w-full shadow-xl sm:my-8">
                    <div class="flex-none px-6 py-4 border-b border-gray-200 bg-gray-50 rounded-t-lg flex justify-between items-center">
                        <h3 class="text-lg font-bold text-gray-900">Dilekçe İçeriği Düzenle</h3>
                        <button wire:click="$set('showPetitionModal', false)" class="text-gray-400 hover:text-gray-500">
                            <span class="sr-only">Kapat</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <div class="p-6 space-y-4">
                        <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-blue-700">
                                        Bu alanda dilekçenin <strong>gövde metnini</strong> düzenleyebilirsiniz. Başlık, imza ve diğer standart alanlar PDF oluşturulurken otomatik eklenir.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <textarea wire:model="editingPetitionText" rows="12" 
                                class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md p-3 font-mono"
                                placeholder="Dilekçe metni buraya gelecek..."></textarea>
                        </div>
                    </div>

                    <div class="flex-none px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-between rounded-b-lg">
                        <div class="flex gap-2">
                             <button type="button" wire:click="generateAiPetition({{ $updatingCompensationId }})" wire:loading.attr="disabled"
                                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 disabled:opacity-50 transition-colors">
                                <svg wire:loading.remove wire:target="generateAiPetition" class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                <svg wire:loading wire:target="generateAiPetition" class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                AI ile Oluştur
                            </button>
                        </div>
                        <div class="flex gap-3">
                            <button type="button" wire:click="$set('showPetitionModal', false)"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                İptal
                            </button>
                            <button type="button" wire:click="savePetitionText"
                                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-sm">
                                Kaydet
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Durum Güncelleme Modalı --}}
    @if($showStatusModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showStatusModal', false)"></div>
                
                <div class="relative bg-white rounded-lg max-w-md w-full shadow-xl">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Durum Güncelle</h3>
                    </div>

                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Durum</label>
                            <select wire:model="newStatus"
                                    class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 min-h-[44px]">
                                @foreach(\App\Models\Compensation::DURUMLAR as $key => $info)
                                    <option value="{{ $key }}">{{ $info['icon'] }} {{ $info['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Onaylanan Tutar (₺)</label>
                            <input type="number" wire:model="onaylananTutar" step="0.01"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 min-h-[44px]">
                        </div>

                        <div class="flex justify-end gap-3 pt-4">
                            <button wire:click="$set('showStatusModal', false)"
                                    class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">
                                İptal
                            </button>
                            <button wire:click="updateStatus"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                Güncelle
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
