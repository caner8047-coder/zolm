<div class="{{ $embedded ? 'space-y-4 lg:space-y-6' : 'space-y-4 lg:space-y-6 p-4 lg:p-6' }}">
    {{-- Sayfa Başlığı --}}
    @unless($embedded)
        <div>
            <p class="text-xs font-semibold tracking-widest text-slate-400 uppercase">Araçlar</p>
            <h1 class="text-xl lg:text-2xl font-bold text-slate-900 mt-1">İade Kabul</h1>
            <p class="text-sm text-slate-500 mt-0.5">Depodan gelen iadeleri tek kayıt altında topla, kanıtları sakla ve sipariş eşleştirmesini arka planda otomatik başlat.</p>
        </div>
    @endunless

    {{-- Flash Mesaj --}}
    @if($message)
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="rounded-[8px] px-4 py-3 text-sm font-medium flex items-center justify-between
                    {{ $messageType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : '' }}
                    {{ $messageType === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : '' }}
                    {{ $messageType === 'info' ? 'bg-blue-50 text-blue-800 border border-blue-200' : '' }}">
            <span>{{ $message }}</span>
            <button @click="show = false" class="ml-4 text-current opacity-60 hover:opacity-100">&times;</button>
        </div>
    @endif

    {{-- KPI Kartları --}}
    @unless($embedded)
        @php $stats = $this->todayStats; @endphp
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 lg:gap-4">
            <div class="rounded-[8px] border bg-blue-50/80 border-blue-100 p-3 lg:p-4">
                <p class="text-[10px] font-semibold tracking-widest text-blue-600 uppercase">Bugün Açılan</p>
                <p class="text-2xl font-bold text-blue-600 mt-1">{{ $stats['today'] ?? 0 }}</p>
            </div>
            <div class="rounded-[8px] border bg-emerald-50/80 border-emerald-100 p-3 lg:p-4">
                <p class="text-[10px] font-semibold tracking-widest text-emerald-600 uppercase">Karar Hazır</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">{{ $stats['ready'] ?? 0 }}</p>
            </div>
            <div class="rounded-[8px] border bg-amber-50/80 border-amber-100 p-3 lg:p-4">
                <p class="text-[10px] font-semibold tracking-widest text-amber-600 uppercase">İnceleme Gereken</p>
                <p class="text-2xl font-bold text-amber-600 mt-1">{{ $stats['review'] ?? 0 }}</p>
            </div>
            <div class="rounded-[8px] border bg-slate-50/80 border-slate-100 p-3 lg:p-4">
                <p class="text-[10px] font-semibold tracking-widest text-slate-600 uppercase">Karara Bağlanan</p>
                <p class="text-2xl font-bold text-slate-600 mt-1">{{ $stats['decisioned'] ?? 0 }}</p>
            </div>
        </div>
    @endunless

    {{-- Ana İçerik --}}
    <div class="flex flex-col xl:flex-row gap-4 lg:gap-6">
        {{-- Sol: Form --}}
        <div class="flex-1 min-w-0">
            <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
                <div class="p-4 lg:p-5 border-b border-slate-200">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">Yeni İade Kaydı</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Önce iadenin hasar durumunu seç. Ürün doğrulamasını güçlendirmek için etiket hariç ürün kanıtı da ekleyebilirsin.</p>
                        </div>

                        {{-- Mod Şalterleri --}}
                        <div class="flex items-center gap-4">
                            {{-- Toplu Yükleme Modu --}}
                            <label class="flex items-center gap-2 cursor-pointer" title="Seçilen etiketlerin her biri farklı kargo sayılarak sıraya alınır.">
                                <span class="text-xs font-semibold text-purple-700 hidden sm:inline">Toplu At (Batch)</span>
                                <div class="relative" x-data>
                                    <input type="checkbox" wire:model.live="bulkMode" class="sr-only peer">
                                    <div class="w-9 h-5 bg-slate-200 rounded-full peer-checked:bg-purple-600 transition-colors"></div>
                                    <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow peer-checked:translate-x-4 transition-transform"></div>
                                </div>
                            </label>

                            {{-- Ardışık Tarama Modu --}}
                            <label class="flex items-center gap-2 cursor-pointer" title="Kayıt atıldıktan sonra listeye atmadan form temizlenir.">
                                <span class="text-xs text-slate-500 hidden sm:inline">Ardışık</span>
                                <div class="relative" x-data>
                                    <input type="checkbox" wire:model.live="continuousMode" class="sr-only peer">
                                    <div class="w-9 h-5 bg-slate-200 rounded-full peer-checked:bg-slate-900 transition-colors"></div>
                                    <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow peer-checked:translate-x-4 transition-transform"></div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Uyarı Mesajı --}}
                @if($bulkMode)
                    <div class="px-4 lg:px-5 py-3 bg-purple-50 border-b border-purple-100">
                        <p class="text-xs font-medium text-purple-700 flex items-center gap-1.5">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                            Toplu Yükleme Modu Aktif: Yükleyeceğiniz her etiket görseli 1 adet kargo sayılarak arka planda işlenecektir.
                        </p>
                    </div>
                @else
                    <div class="px-4 lg:px-5 py-3 bg-slate-50 border-b border-slate-100">
                        <p class="text-xs text-slate-500 flex items-center gap-1.5">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Tekli moddasınız: Lütfen tek seferde yalnızca 1 kargo paketine ait görselleri yükleyin. Veya <strong class="text-purple-600">Toplu At</strong> modunu kullanın.
                        </p>
                    </div>
                @endif

                <div class="p-4 lg:p-5 space-y-5">
                    {{-- Hasar Durumu Seçimi --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" wire:model.live="intakeType" value="undamaged" class="sr-only peer">
                            <div class="rounded-[8px] border-2 p-4 transition-all peer-checked:border-emerald-500 peer-checked:bg-emerald-50/50 border-slate-200 hover:border-slate-300">
                                <p class="text-sm font-semibold text-slate-900">Hasarsız İade</p>
                                <p class="text-xs text-slate-500 mt-0.5">Paket etiketi ve temel geliş kaydı yeterli.</p>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" wire:model.live="intakeType" value="damaged" class="sr-only peer">
                            <div class="rounded-[8px] border-2 p-4 transition-all peer-checked:border-red-500 peer-checked:bg-red-50/50 border-slate-200 hover:border-slate-300">
                                <p class="text-sm font-semibold text-slate-900">Hasarlı İade</p>
                                <p class="text-xs text-slate-500 mt-0.5">Etiketten sonra mutlaka hasar fotoğraflarını da ekle.</p>
                            </div>
                        </label>
                    </div>

                    {{-- Görsel Yükleme Alanları --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Etiket Görseli (Zorunlu) --}}
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <p class="text-sm font-medium text-slate-900">İade etiketi / paket barkodu</p>
                                <span class="px-1.5 py-0.5 text-[10px] font-semibold rounded bg-red-50 text-red-600 border border-red-200">Zorunlu</span>
                            </div>
                            <p class="text-xs text-slate-500 mb-2">Kargo etiketi, iade barkodu veya takip numarasını gösteren görseller.</p>
                            <label class="flex flex-col items-center justify-center w-full min-h-[120px] rounded-[8px] border-2 border-dashed border-slate-300 bg-slate-50/50 cursor-pointer hover:border-slate-400 hover:bg-slate-50 transition-colors relative">
                                <input type="file" wire:model="labelImages" accept="image/*" capture="environment" multiple class="sr-only">
                                <div wire:loading.remove wire:target="labelImages" class="text-center p-4">
                                    <svg class="h-8 w-8 text-slate-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <p class="text-xs text-slate-500">Etiket görsellerini ekle</p>
                                </div>
                                <div wire:loading wire:target="labelImages" class="text-center p-4">
                                    <svg class="animate-spin h-6 w-6 text-slate-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    <p class="text-xs text-slate-500">Yükleniyor...</p>
                                </div>
                            </label>
                            @if(count($labelImages ?? []))
                                <div class="grid grid-cols-4 gap-2 mt-2">
                                    @foreach($labelImages as $img)
                                        <div class="aspect-square rounded-[6px] overflow-hidden border border-slate-200 bg-white">
                                            <img src="{{ $img->temporaryUrl() }}" alt="Etiket" class="w-full h-full object-cover">
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            @error('labelImages') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            @error('labelImages.*') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Ürün Kanıtı (Opsiyonel) --}}
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <p class="text-sm font-medium text-slate-900">Ürün kanıtı</p>
                                <span class="px-1.5 py-0.5 text-[10px] font-semibold rounded bg-slate-100 text-slate-500 border border-slate-200">Opsiyonel</span>
                            </div>
                            <p class="text-xs text-slate-500 mb-2">İç ürün, ürün barkodu veya adet kontrolünü destekleyen ek görseller.</p>
                            <label class="flex flex-col items-center justify-center w-full min-h-[120px] rounded-[8px] border-2 border-dashed border-slate-300 bg-slate-50/50 cursor-pointer hover:border-slate-400 hover:bg-slate-50 transition-colors">
                                <input type="file" wire:model="productImages" accept="image/*" capture="environment" multiple class="sr-only">
                                <div wire:loading.remove wire:target="productImages" class="text-center p-4">
                                    <svg class="h-8 w-8 text-slate-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <p class="text-xs text-slate-500">Ürün görsellerini ekle</p>
                                </div>
                                <div wire:loading wire:target="productImages" class="text-center p-4">
                                    <svg class="animate-spin h-6 w-6 text-slate-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    <p class="text-xs text-slate-500">Yükleniyor...</p>
                                </div>
                            </label>
                            @if(count($productImages ?? []))
                                <div class="grid grid-cols-4 gap-2 mt-2">
                                    @foreach($productImages as $img)
                                        <div class="aspect-square rounded-[6px] overflow-hidden border border-slate-200 bg-white">
                                            <img src="{{ $img->temporaryUrl() }}" alt="Ürün" class="w-full h-full object-cover">
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Hasar Görselleri (sadece hasarlı ise) --}}
                    @if($intakeType === 'damaged')
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <p class="text-sm font-medium text-slate-900">Hasar görselleri</p>
                                <span class="px-1.5 py-0.5 text-[10px] font-semibold rounded bg-red-50 text-red-600 border border-red-200">Hasarlı iade</span>
                            </div>
                            <p class="text-xs text-slate-500 mb-2">Ürünün hasarlı kısmının yakın plan fotoğrafları. Pazaryeri itirazı için kanıt olarak kullanılır.</p>
                            <label class="flex flex-col items-center justify-center w-full min-h-[100px] rounded-[8px] border-2 border-dashed border-red-300 bg-red-50/30 cursor-pointer hover:border-red-400 hover:bg-red-50/50 transition-colors">
                                <input type="file" wire:model="damageImages" accept="image/*" capture="environment" multiple class="sr-only">
                                <div wire:loading.remove wire:target="damageImages" class="text-center p-4">
                                    <svg class="h-8 w-8 text-red-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                    <p class="text-xs text-red-500">Hasar fotoğraflarını ekle</p>
                                </div>
                                <div wire:loading wire:target="damageImages" class="text-center p-4">
                                    <svg class="animate-spin h-6 w-6 text-red-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    <p class="text-xs text-red-500">Yükleniyor...</p>
                                </div>
                            </label>
                            @if(count($damageImages ?? []))
                                <div class="grid grid-cols-4 gap-2 mt-2">
                                    @foreach($damageImages as $img)
                                        <div class="aspect-square rounded-[6px] overflow-hidden border border-red-200 bg-white">
                                            <img src="{{ $img->temporaryUrl() }}" alt="Hasar" class="w-full h-full object-cover">
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Ek Bilgiler --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-slate-900">Ek referans</label>
                            <input wire:model="manualReference" type="text" placeholder="Sipariş no, barkod, takip no..."
                                   class="w-full mt-1.5 px-3 py-2 text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white focus:ring-2 focus:ring-slate-200 focus:border-slate-300 outline-none">
                            <p class="text-[11px] text-slate-400 mt-1">OCR zayıf kalırsa eşleştirmeye destek olur.</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-900">Ürün barkodu</label>
                            <input wire:model="operatorBarcode" type="text" placeholder="Ürün barkodu varsa tara veya yaz"
                                   class="w-full mt-1.5 px-3 py-2 text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white focus:ring-2 focus:ring-slate-200 focus:border-slate-300 outline-none">
                        </div>
                    </div>

                    {{-- Depo Notu --}}
                    <div>
                        <label class="text-sm font-medium text-slate-900">Depo notu</label>
                        <textarea wire:model="warehouseNote" rows="2" placeholder="Kısa not bırakabilirsin..."
                                  class="w-full mt-1.5 px-3 py-2 text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white focus:ring-2 focus:ring-slate-200 focus:border-slate-300 outline-none resize-none"></textarea>
                    </div>

                    {{-- Kayıt Butonu --}}
                    <div class="flex items-center justify-between pt-2">
                        <p class="text-xs text-slate-400">Kayıt oluşturulduktan sonra analiz otomatik başlar ve ofis ekranına düşer.</p>
                        <button wire:click="saveIntake" wire:loading.attr="disabled" wire:target="saveIntake"
                                class="px-6 py-2.5 sm:py-2 rounded-[6px] bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition-colors disabled:opacity-50 flex items-center gap-2">
                            <span wire:loading.remove wire:target="saveIntake">İade kaydını oluştur</span>
                            <span wire:loading wire:target="saveIntake" class="flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                Analiz ediliyor...
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Son Analiz Sonucu --}}
            @if($lastAnalysisResult)
                <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm mt-4">
                    <div class="p-4 lg:p-5">
                        <h3 class="text-sm font-semibold text-slate-900 mb-3">Son Analiz Sonucu</h3>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            @if($lastAnalysisResult['tracking'])
                                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-2.5">
                                    <p class="text-[10px] font-semibold tracking-widest text-slate-400 uppercase">Takip No</p>
                                    <p class="text-xs font-mono font-medium text-slate-900 mt-0.5 truncate">{{ $lastAnalysisResult['tracking'] }}</p>
                                </div>
                            @endif
                            @if($lastAnalysisResult['customer'])
                                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-2.5">
                                    <p class="text-[10px] font-semibold tracking-widest text-slate-400 uppercase">Müşteri</p>
                                    <p class="text-xs font-medium text-slate-900 mt-0.5 truncate">{{ $lastAnalysisResult['customer'] }}</p>
                                </div>
                            @endif
                            @if($lastAnalysisResult['cargo'])
                                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-2.5">
                                    <p class="text-[10px] font-semibold tracking-widest text-slate-400 uppercase">Kargo</p>
                                    <p class="text-xs font-medium text-slate-900 mt-0.5 truncate">{{ $lastAnalysisResult['cargo'] }}</p>
                                </div>
                            @endif
                            <div class="rounded-[8px] border p-2.5 {{ $lastAnalysisResult['matched'] ? 'border-emerald-200 bg-emerald-50/70' : 'border-amber-200 bg-amber-50/70' }}">
                                <p class="text-[10px] font-semibold tracking-widest uppercase {{ $lastAnalysisResult['matched'] ? 'text-emerald-400' : 'text-amber-400' }}">Eşleşme</p>
                                <p class="text-xs font-medium mt-0.5 {{ $lastAnalysisResult['matched'] ? 'text-emerald-700' : 'text-amber-700' }}">
                                    {{ $lastAnalysisResult['matched'] ? '✓ Sipariş eşleşti' : '⚠ Eşleşme yok' }}
                                </p>
                            </div>
                            @if($lastAnalysisResult['suggestion'])
                                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-2.5">
                                    <p class="text-[10px] font-semibold tracking-widest text-slate-400 uppercase">Öneri</p>
                                    <p class="text-xs font-medium text-slate-900 mt-0.5">{{ $lastAnalysisResult['suggestion'] }}</p>
                                </div>
                            @endif
                            @if($lastAnalysisResult['error'])
                                <div class="rounded-[8px] border border-red-200 bg-red-50/70 p-2.5 sm:col-span-2">
                                    <p class="text-[10px] font-semibold tracking-widest text-red-400 uppercase">Hata</p>
                                    <p class="text-xs font-medium text-red-700 mt-0.5">{{ $lastAnalysisResult['error'] }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Sağ: Kullanım Notu + Son Kayıtlar --}}
        <div class="w-full xl:w-[320px] flex-shrink-0 space-y-4">
            {{-- Kullanım Notu --}}
            <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
                <div class="p-4">
                    <h3 class="text-sm font-semibold text-slate-900 mb-3">Kullanım notu</h3>
                    <div class="space-y-2.5">
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-xs font-semibold text-slate-700">Hasarsız iade</p>
                            <p class="text-xs text-slate-500 mt-0.5">Etiket zorunlu, ürün barkodu ve ürün kanıtı tavsiye edilir.</p>
                        </div>
                        <div class="rounded-[8px] border border-red-100 bg-red-50/50 p-3">
                            <p class="text-xs font-semibold text-red-700">Hasarlı iade</p>
                            <p class="text-xs text-red-600/80 mt-0.5">Etiketten sonra hasar görselleri mutlaka eklenmelidir.</p>
                        </div>
                        <div class="rounded-[8px] border border-blue-100 bg-blue-50/50 p-3">
                            <p class="text-xs font-semibold text-blue-700">Anlık analiz</p>
                            <p class="text-xs text-blue-600/80 mt-0.5">Kayıt oluştur dediğinizde Gemini AI etiketi okur ve sipariş eşleştirmesini anında yapar.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Son Kayıtlarım --}}
            <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
                <div class="p-4">
                    <h3 class="text-sm font-semibold text-slate-900 mb-3">Son kayıtlarım</h3>
                    <p class="text-xs text-slate-500 mb-3">Depodan açtığın son iadeler burada görünür.</p>
                    <div class="space-y-2">
                        @forelse($this->recentItems as $recent)
                            <a href="{{ route('returns.workspace', ['item' => $recent->id, 'tab' => 'havuz']) }}"
                               class="block rounded-[8px] border border-slate-200 bg-slate-50/70 p-3 hover:bg-slate-50 hover:border-slate-300 transition-colors">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-medium text-slate-900">{{ $recent->intakeLabel() }}</p>
                                        <p class="text-xs text-slate-500 font-mono truncate mt-0.5">
                                            {{ $recent->detected_tracking_number ?: $recent->manual_reference ?: $recent->operator_barcode ?: 'Henüz referans tespit edilmedi' }}
                                        </p>
                                        <p class="text-[10px] text-slate-400 mt-0.5">{{ $recent->arrived_at?->format('d.m.Y H:i') }}</p>
                                    </div>
                                    @php
                                        $dotColor = match($recent->intake_status) {
                                            'matched', 'ready_for_decision', 'decisioned' => 'bg-emerald-500',
                                            'queued', 'analyzing' => 'bg-blue-500',
                                            'needs_review' => 'bg-amber-500',
                                            'failed' => 'bg-red-500',
                                            default => 'bg-slate-400',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-[10px] font-mono rounded border border-slate-200 bg-white text-slate-600 whitespace-nowrap">
                                        <span class="inline-block h-1.5 w-1.5 rounded-full {{ $dotColor }}"></span>
                                        {{ $recent->statusLabel() }}
                                    </span>
                                </div>
                            </a>
                        @empty
                            <p class="text-xs text-slate-400 text-center py-4">Henüz kayıt yok.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
