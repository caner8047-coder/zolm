<div class="space-y-4 lg:space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="flex-1">
            <h2 class="text-xl font-bold text-slate-900 tracking-tight lg:text-2xl">Kargo Fiyatları</h2>
            <p class="mt-1 text-sm text-slate-500">Trendyol Kargo firmaları desi bazlı fiyatları ve Barem Destek tutarları</p>
        </div>
        <div class="flex items-center gap-3">
            @if($this->shippingDashboard['last_update'])
                <span class="text-xs text-slate-500">Son güncelleme: {{ $this->shippingDashboard['last_update'] }}</span>
            @endif
            
            <div class="relative" x-data="{ open: false }">
                <button type="button" @click="open = !open" class="inline-flex items-center gap-2 rounded-[6px] bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50">
                    <x-lucide.icon name="upload-cloud" class="h-4 w-4" />
                    PDF'ten Güncelle
                </button>
                <div x-show="open" @click.outside="open = false" style="display:none;" class="absolute right-0 z-10 mt-2 w-72 origin-top-right rounded-[8px] bg-white p-4 shadow-lg ring-1 ring-black ring-opacity-5">
                    <h3 class="text-sm font-medium text-slate-900 mb-2">Güncel PDF Yükle</h3>
                    <p class="text-xs text-slate-500 mb-3">Trendyol "Kargo Fiyatları ve Desi" ekranından indirdiğiniz PDF dosyasını seçin.</p>
                    
                    <input type="file" wire:model="shippingPdfFile" accept=".pdf" class="block w-full text-xs text-slate-500 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-indigo-600 hover:file:bg-indigo-100">
                    
                    <div wire:loading wire:target="shippingPdfFile" class="mt-3 text-xs text-indigo-600 font-medium">
                        <span class="inline-flex items-center gap-1"><x-lucide.icon name="loader-2" class="h-3 w-3 animate-spin"/> Dosya yükleniyor ve işleniyor...</span>
                    </div>

                    @if($shippingImportStatus)
                        <div class="mt-3 rounded p-2 text-xs {{ $shippingImportStatus['status'] === 'ok' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">
                            {{ $shippingImportStatus['message'] }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Barem Destek Bilgi Kartı -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 lg:gap-6">
        <div class="rounded-[8px] border border-emerald-200 bg-emerald-50/50 p-4">
            <h3 class="text-sm font-bold text-emerald-900 mb-2 flex items-center gap-2">
                <x-lucide.icon name="check-circle" class="h-4 w-4 text-emerald-600"/>
                Avantajlı Barem Fiyatları (Termini 1 Gün / Başarılı)
            </h3>
            <div class="text-xs text-slate-600 space-y-1">
                <p><strong>0 - 199 TL arası:</strong> TEX, PTT: 34.16 ₺ | Aras: 42.91 ₺ | Sürat: 48.74 ₺ | YK: 74.58 ₺</p>
                <p><strong>200 - 349 TL arası:</strong> TEX, PTT: 65.83 ₺ | Aras: 73.74 ₺ | Sürat: 79.58 ₺ | YK: 104.58 ₺</p>
                <p class="mt-2 text-[11px] text-slate-500">* ZOLM kâr zarar hesabı yaparken termin kuralına uyduğunuzu varsayarak bu tarifeyi önerir.</p>
            </div>
        </div>
        <div class="rounded-[8px] border border-amber-200 bg-amber-50/50 p-4">
            <h3 class="text-sm font-bold text-amber-900 mb-2 flex items-center gap-2">
                <x-lucide.icon name="alert-circle" class="h-4 w-4 text-amber-600"/>
                Standart Barem Fiyatları (Geç Teslimat / Termin > 1)
            </h3>
            <div class="text-xs text-slate-600 space-y-1">
                <p><strong>0 - 199 TL arası:</strong> TEX, PTT: 64.58 ₺ | Aras: 71.66 ₺ | Sürat: 77.49 ₺ | YK: 101.24 ₺</p>
                <p><strong>200 - 349 TL arası:</strong> TEX, PTT: 72.91 ₺ | Aras: 79.99 ₺ | Sürat: 85.83 ₺ | YK: 109.58 ₺</p>
                <p class="mt-2 text-[11px] text-slate-500">* 350 TL ve üzeri siparişler Barem Destek kapsamı dışındadır, desi bazlı tarife uygulanır.</p>
            </div>
        </div>
    </div>

    <!-- Desi Fiyatları Tablosu -->
    <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-200 bg-slate-50/50 px-4 py-4 flex items-center justify-between">
            <h3 class="text-base font-semibold text-slate-900">Desi Bazlı Taşıma Bedelleri (KDV Hariç)</h3>
            <div class="text-sm text-slate-500">Toplam: {{ $this->shippingDashboard['total'] }} kayıt</div>
        </div>
        
        @if(empty($this->shippingDashboard['pivot']))
            <div class="p-8 text-center text-slate-500 text-sm">
                Henüz kargo fiyatı yüklenmemiş. PDF yükleyerek oranları güncelleyebilirsiniz.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-3 sticky left-0 bg-slate-50 border-r border-slate-200 z-10 w-24">Desi</th>
                            @foreach($this->shippingDashboard['companies'] as $company)
                                <th class="px-4 py-3 text-right">{{ $company }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($this->shippingDashboard['pivot'] as $desi => $prices)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-4 py-2 font-medium text-slate-900 sticky left-0 bg-white border-r border-slate-100 z-10">{{ $desi }} Desi</td>
                                @foreach($this->shippingDashboard['companies'] as $company)
                                    <td class="px-4 py-2 text-right tabular-nums">
                                        {{ isset($prices[$company]) ? number_format($prices[$company], 2, ',', '.') . ' ₺' : '—' }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
