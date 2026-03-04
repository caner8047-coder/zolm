@if($showOrderModal && !empty($selectedOrderDetails))
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        
        {{-- Arka plan karartması --}}
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" 
                 aria-hidden="true" 
                 wire:click="closeOrderModal"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            {{-- Modal Paneli --}}
            <div class="inline-block align-bottom bg-gray-50 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl w-full border border-gray-200"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">

                @php
                    $order = $selectedOrderDetails;
                    $basic = $order['basic'];
                    $fin   = $order['financials'];
                    $set   = $order['settlement'];
                    $sum   = $order['summary'];
                @endphp

                <!-- HEADER -->
                <div class="bg-white border-b border-gray-200 px-6 py-5 flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-3 mb-1">
                            <h3 class="text-xl leading-6 font-bold text-gray-900" id="modal-title">
                                Sipariş Detayı: #{{ $basic['order_number'] }}
                            </h3>
                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-{{ $basic['status_color'] }}-100 text-{{ $basic['status_color'] }}-800 border border-{{ $basic['status_color'] }}-200">
                                {{ $basic['status'] }}
                            </span>
                            @if($basic['is_flagged'])
                                <span class="px-2 py-1 flex items-center gap-1 text-xs font-bold bg-red-100 text-red-700 rounded-lg border border-red-200 animate-pulse">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                                    Riskli İşlem
                                </span>
                            @endif
                            @if(isset($set['has_partial_refund']) && $set['has_partial_refund'])
                                <span class="px-2 py-1 flex items-center gap-1 text-xs font-bold bg-orange-100 text-orange-700 rounded-lg border border-orange-200">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                    Kısmi İade
                                </span>
                            @endif
                        </div>
                        
                        @if(!empty($basic['sibling_items']))
                            {{-- Çoklu Ürünlü Sipariş: Tüm ürünleri listele --}}
                            <div class="mt-2 space-y-1.5">
                                <div class="flex items-center gap-2 text-xs text-gray-500">
                                    <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                    <span class="font-semibold">Sepet İçeriği ({{ 1 + count($basic['sibling_items']) }} farklı ürün)</span>
                                </div>
                                {{-- Bu ürün --}}
                                <div class="flex items-center gap-2 pl-5">
                                    <span class="px-1.5 py-0.5 bg-emerald-100 text-emerald-700 rounded text-[10px] font-bold">{{ $basic['quantity'] }}×</span>
                                    <span class="text-sm text-gray-700 font-medium truncate max-w-xs">{{ $basic['product_name'] ?: 'İsimsiz Ürün' }}</span>
                                    <span class="text-[10px] text-gray-400 font-mono">{{ $basic['barcode'] ?: '' }}</span>
                                </div>
                                {{-- Diğer ürünler --}}
                                @foreach($basic['sibling_items'] as $sib)
                                    <div class="flex items-center gap-2 pl-5">
                                        <span class="px-1.5 py-0.5 bg-gray-100 text-gray-600 rounded text-[10px] font-bold">{{ $sib['quantity'] }}×</span>
                                        <span class="text-sm text-gray-600 truncate max-w-xs">{{ $sib['product_name'] ?: 'İsimsiz Ürün' }}</span>
                                        <span class="text-[10px] text-gray-400 font-mono">{{ $sib['barcode'] ?: '' }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            {{-- Tekli Ürün --}}
                            <p class="text-sm text-gray-500 line-clamp-1 font-medium mt-1">
                                <span class="px-2 py-0.5 bg-gray-100 rounded text-gray-700 mr-2">{{ $basic['quantity'] ?? 1 }} Adet</span>
                                {{ $basic['product_name'] ?: 'Ürün Bilgisi Excel\'de Yok (Sadece Finansal Kayıt)' }}
                            </p>
                            <p class="text-xs text-gray-400 mt-1">
                                Barkod: <span class="font-mono">{{ $basic['barcode'] ?: 'Belirtilmedi' }}</span> 
                                | Stok Kodu: <span class="font-mono">{{ $basic['stock_code'] ?: 'Belirtilmedi' }}</span>
                            </p>
                        @endif
                    </div>
                    <button wire:click="closeOrderModal" type="button" class="bg-white rounded-md text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 transition-colors">
                        <span class="sr-only">Kapat</span>
                        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-6 pb-8 overflow-y-auto max-h-[75vh]">
                    
                    {{-- İPTAL/İADE DURUMUNDA VERGİ / FATURA UYARISI --}}
                    @if($basic['status'] === 'İade Edildi' || $basic['status'] === 'İptal Edildi')
                    <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg shadow-sm flex items-start gap-3">
                        <div class="mt-0.5 text-red-500">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-red-800">Mali / Muhasebe Uyarısı: E-Arşiv & Fatura İptali Uyarısı</h4>
                            <p class="text-xs text-red-700 mt-1">
                                Bu sipariş iptal / iade edilmiştir. Müşteriye kesilen satış faturasının <strong>e-Arşiv veya GİB portalı üzerinden İPTAL edildiğinden</strong> (veya Gider Pusulası düzenlendiğinden) emin olunuz. Aksi takdirde gerçekleşmeyen satışın KDV ve Gelir Vergisini ödemek zorunda kalırsınız!
                            </p>
                        </div>
                    </div>
                    @endif

                    {{-- GÖVDE: 2 Kolon Grid --}}
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        
                        {{-- SOL KOLON: Timeline ve Finansal Parçalanma --}}
                        <div class="lg:col-span-2 space-y-6">
                            
                            <!-- 1. ZAMAN ÇİZELGESİ (TIMELINE) -->
                            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4 border-b pb-2">Paranın Yolculuğu (Zaman Çizelgesi)</h4>
                                <div class="relative">
                                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                                        <div class="w-full border-t-2 border-dashed border-gray-200"></div>
                                    </div>
                                    <div class="relative flex justify-between">
                                        
                                        <!-- Sipariş Alındı -->
                                        <div>
                                            <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center ring-4 ring-white shadow text-blue-600">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                                            </div>
                                            <div class="mt-3 hidden sm:block">
                                                <span class="text-xs font-semibold text-gray-900 block">Sipariş Alındı</span>
                                                <span class="text-xs text-gray-500">{{ $basic['order_date'] ?? 'Belirsiz' }}</span>
                                            </div>
                                        </div>

                                        <!-- Teslim Edildi -->
                                        <div>
                                            <div class="h-8 w-8 rounded-full {{ ($basic['delivery_date'] || $basic['status'] === 'Teslim Edildi') ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-400' }} flex items-center justify-center ring-4 ring-white shadow">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                            </div>
                                            <div class="mt-3 hidden sm:block text-center">
                                                <span class="text-xs font-semibold text-gray-900 block">Teslimat</span>
                                                <span class="text-xs text-gray-500">
                                                    @if($basic['delivery_date'])
                                                        {{ $basic['delivery_date'] }}
                                                    @elseif($basic['status'] === 'Teslim Edildi')
                                                        Tarih Yok
                                                    @else
                                                        Bekliyor
                                                    @endif
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Vade/Hakediş -->
                                        <div>
                                            <div class="h-8 w-8 rounded-full {{ $set['has_settlement'] && $set['due_date'] ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-400' }} flex items-center justify-center ring-4 ring-white shadow" title="{{ !$set['has_settlement'] ? 'Ödeme Detay Exceli Yüklenmemiş' : '' }}">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            </div>
                                            <div class="mt-3 hidden sm:block text-center">
                                                <span class="text-xs font-semibold text-gray-900 block">Vade Tarihi</span>
                                                @if($set['has_settlement'] && $set['due_date'])
                                                    <span class="text-xs text-gray-500">{{ $set['due_date'] }}</span>
                                                @else
                                                    <span class="text-[10px] text-red-500 block leading-tight mt-1" title="Vade tarihini görmek için Ödeme Detay Excel'ini sisteme yükleyin.">Ödeme Excel'i<br>Eksik</span>
                                                @endif
                                            </div>
                                        </div>

                                        <!-- Bankaya Yatan -->
                                        <div>
                                            <div class="h-8 w-8 rounded-full {{ $set['has_settlement'] && $set['settlement_date'] ? 'bg-emerald-500 text-white' : ($set['is_paid'] ? 'bg-emerald-300 text-emerald-700' : ($set['expected_date'] ? 'bg-amber-100 text-amber-500' : 'bg-gray-100 text-gray-400')) }} flex items-center justify-center ring-4 ring-white shadow">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </div>
                                            <div class="mt-3 hidden sm:block text-right">
                                                @if($set['has_settlement'] && $set['settlement_date'])
                                                    {{-- Durum 1: Gerçek ödeme kaydı var --}}
                                                    <span class="text-xs font-semibold text-gray-900 block">Tahsil Edildi</span>
                                                    <span class="text-xs text-emerald-600 font-medium">{{ $set['settlement_date'] }}</span>
                                                @elseif($set['is_paid'])
                                                    {{-- Durum 2: Tahmini, vade geçmiş --}}
                                                    <span class="text-xs font-semibold text-gray-900 block">Yatmış Olmalı</span>
                                                    <span class="text-[10px] text-emerald-600 block leading-tight mt-1 font-medium" title="Vade tarihi geçti, Excel yüklenmedi.">Tahmini:<br>{{ $set['expected_date'] }}</span>
                                                @elseif($set['expected_date'])
                                                    {{-- Durum 3: Gelecekte ödenecek --}}
                                                    <span class="text-xs font-semibold text-gray-900 block">Tahsil Edilecek</span>
                                                    <span class="text-[10px] text-amber-600 block leading-tight mt-1 font-medium" title="Trendyol ödeme gününe göre beklenen tahsilat tarihi.">Öngörülen:<br>{{ $set['expected_date'] }}</span>
                                                @else
                                                    <span class="text-xs font-semibold text-gray-900 block">Tahsilat Durumu</span>
                                                    <span class="text-[10px] text-red-500 block leading-tight mt-1">Tarih Yok</span>
                                                @endif
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <!-- 2. KESİNTİ AĞACI (FINANCIAL BREAKDOWN) -->
                            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4 border-b pb-2 flex justify-between">
                                    <span>Pazaryeri Kesintileri (Temel)</span>
                                    <span class="text-gray-900 font-bold">{{ number_format($fin['gross_amount'], 2, ',', '.') }} ₺ (Brüt Satış)</span>
                                </h4>
                                
                                <div class="space-y-3">
                                    <!-- Ana Kalemler -->
                                    @if($fin['discount_amount'] > 0 || $fin['campaign_discount'] > 0)
                                        <div class="pb-2 border-b border-gray-100 space-y-2">
                                            @if($fin['discount_amount'] > 0)
                                            <div class="flex justify-between items-center">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-2 h-2 rounded-full bg-blue-400"></div>
                                                    <span class="text-sm font-medium text-gray-700">Satıcı İndirimi</span>
                                                </div>
                                                <span class="text-sm font-semibold text-blue-600">-{{ number_format($fin['discount_amount'], 2, ',', '.') }} ₺</span>
                                            </div>
                                            @endif
                                            @if($fin['campaign_discount'] > 0)
                                            <div class="flex justify-between items-center">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-2 h-2 rounded-full bg-indigo-400"></div>
                                                    <span class="text-sm font-medium text-gray-700">Kampanya İndirimi</span>
                                                </div>
                                                <span class="text-sm font-semibold text-indigo-600">-{{ number_format($fin['campaign_discount'], 2, ',', '.') }} ₺</span>
                                            </div>
                                            @endif
                                        </div>
                                    @endif

                                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full bg-red-400"></div>
                                            <span class="text-sm font-medium text-gray-700">Komisyon Bedeli</span>
                                        </div>
                                        <span class="text-sm font-semibold text-red-600">-{{ number_format($fin['commission_amount'], 2, ',', '.') }} ₺</span>
                                    </div>
                                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full bg-orange-400"></div>
                                            <span class="text-sm font-medium text-gray-700">Kargo Kesintisi <span class="text-xs text-gray-400 font-normal ml-1">({{ $fin['cargo_company'] ?? 'Sistem/Firma Belirsiz' }})</span></span>
                                        </div>
                                        <span class="text-sm font-semibold text-orange-600">-{{ number_format($fin['cargo_amount'], 2, ',', '.') }} ₺</span>
                                    </div>
                                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full bg-yellow-500"></div>
                                            <span class="text-sm font-medium text-gray-700">Hizmet / İşlem Bedeli</span>
                                        </div>
                                        <span class="text-sm font-semibold text-yellow-600">-{{ number_format($fin['service_fee'], 2, ',', '.') }} ₺</span>
                                    </div>
                                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full bg-purple-400"></div>
                                            <span class="text-sm font-medium text-gray-700">E-Ticaret Stopajı</span>
                                        </div>
                                        <span class="text-sm font-semibold text-purple-600">-{{ number_format($fin['withholding_tax'], 2, ',', '.') }} ₺</span>
                                    </div>

                                    <!-- EKSTRA CEZALAR VE İADELER (Transactions'tan beslenir) -->
                                    @if(count($order['extra_deductions']) > 0 || count($order['refunds']) > 0)
                                        <div class="mt-6 pt-4 border-t-2 border-dashed border-gray-200">
                                            <h5 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Ekstra Cezalar ve İadeler (Cari'den)</h5>
                                            
                                            @foreach($order['extra_deductions'] as $tx)
                                                <div class="flex justify-between items-center py-2 bg-red-50 px-3 rounded-lg border border-red-100 mb-2">
                                                    <div>
                                                        <span class="text-sm font-bold text-red-800">{{ $tx['type'] }}</span><br>
                                                        <span class="text-xs text-red-600">{{ $tx['desc'] }} ({{ $tx['date'] }})</span>
                                                    </div>
                                                    <span class="text-sm font-bold text-red-700">-{{ number_format($tx['amount'], 2, ',', '.') }} ₺</span>
                                                </div>
                                            @endforeach

                                            @foreach($order['refunds'] as $tx)
                                                <div class="flex justify-between items-center py-2 bg-green-50 px-3 rounded-lg border border-green-100 mb-2">
                                                    <div>
                                                        <span class="text-sm font-bold text-green-800">{{ $tx['type'] }} (Geri Alım)</span><br>
                                                        <span class="text-xs text-green-600">{{ $tx['desc'] }} ({{ $tx['date'] }})</span>
                                                    </div>
                                                    <span class="text-sm font-bold text-green-700">+{{ number_format($tx['amount'], 2, ',', '.') }} ₺</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                </div>

                                <!-- Alt Toplam Sınırı -->
                                <div class="mt-5 bg-gray-50 p-4 rounded-lg border border-gray-200">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-bold text-gray-700">Tahmini Net Hakediş (Trendyol Beyanı):</span>
                                        <span class="text-lg font-black text-gray-900">{{ number_format($fin['expected_net'], 2, ',', '.') }} ₺</span>
                                    </div>
                                    @if(isset($set['has_partial_refund']) && $set['has_partial_refund'])
                                        <p class="text-[10px] text-orange-600 mt-2 leading-tight">
                                            ⚠️ Bu tutar siparişin orijinal (iade öncesi) beyanıdır. Kısmi iade sonrası gerçek banka tahsilatı farklıdır — sağdaki panele bakınız.
                                        </p>
                                    @endif
                                </div>
                            </div>
                            
                            <!-- 3. DENETİM BİLDİRİMLERİ (Audit Logs) -->
                            @if(count($order['audits']) > 0)
                                @php
                                    $hasRealError = collect($order['audits'])->contains(fn($a) => in_array($a['severity'], ['critical', 'warning']));
                                    $panelBg = $hasRealError ? 'bg-red-50 border-red-200' : 'bg-blue-50/50 border-blue-200';
                                    $panelTitleColor = $hasRealError ? 'text-red-800' : 'text-blue-800';
                                @endphp
                                <div class="{{ $panelBg }} p-5 rounded-xl border shadow-sm">
                                    <h4 class="text-xs font-bold {{ $panelTitleColor }} uppercase tracking-wider mb-3 flex items-center gap-2">
                                        @if($hasRealError)
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                        @else
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        @endif
                                        Denetim Motoru Bulguları
                                    </h4>
                                    <ul class="space-y-3">
                                        @foreach($order['audits'] as $audit)
                                            @php
                                                $borderColor = 'border-orange-400';
                                                $bgClass = 'bg-white';
                                                $titleColor = 'text-gray-900';
                                                $descColor = 'text-gray-600';
                                                
                                                if ($audit['severity'] === 'critical') {
                                                    $borderColor = 'border-red-600';
                                                } elseif (in_array($audit['rule_code'], ['HAKEDIS_ILLUZYON', 'KISMI_IADE']) || $audit['severity'] === 'info') {
                                                    $borderColor = 'border-blue-400';
                                                    $bgClass = 'bg-blue-50/70';
                                                    $titleColor = 'text-blue-800';
                                                    $descColor = 'text-blue-700';
                                                }
                                            @endphp
                                            <li class="{{ $bgClass }} p-3 rounded shadow-sm border-l-4 {{ $borderColor }}">
                                                <p class="text-sm font-bold {{ $titleColor }}">{{ $audit['title'] }}</p>
                                                <p class="text-xs {{ $descColor }} mt-1">{{ $audit['description'] }}</p>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                        </div>

                        {{-- SAĞ KOLON: Kârlılık Özeti ve Banka Tahsilatı --}}
                        <div class="space-y-6">
                            
                            <!-- A. BANKA TAHSİLATI (GERÇEKLEŞEN) -->
                            <div class="bg-gradient-to-br from-gray-900 to-gray-800 p-5 rounded-xl shadow-lg border border-gray-700 text-white relative overflow-hidden">
                                <!-- Dekoratif arka plan -->
                                <div class="absolute -right-6 -top-6 opacity-10">
                                    <svg class="h-32 w-32" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path></svg>
                                </div>
                                
                                <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 z-10 relative">Banka Tahsilatı (Gerçekleşen)</h4>
                                
                                @if($set['has_settlement'] && $set['settlement_date'])
                                    {{-- Durum 1: Gerçek ödeme Trendyol Excel'iyle doğrulandı --}}
                                    <div class="flex items-baseline gap-1 my-3 z-10 relative">
                                        <span class="text-4xl font-black text-emerald-400">{{ number_format($set['seller_hakedis'], 2, ',', '.') }}</span>
                                        <span class="text-lg font-medium text-emerald-200">₺</span>
                                    </div>
                                    <div class="text-sm text-gray-300 z-10 relative">
                                        Kayıt: <strong>{{ $set['settlement_date'] }}</strong>
                                    </div>
                                    @if($set['is_reconciled'])
                                        <div class="mt-3 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-emerald-500/20 border border-emerald-500/30 text-emerald-300 text-xs font-medium z-10 relative"
                                             title="Banka yatışı: {{ number_format($set['seller_hakedis'], 2, ',', '.') }} ₺ | Sipariş beyanı: {{ number_format($set['expected_net'] ?? 0, 2, ',', '.') }} ₺&#10;Not: {{ $set['variance'] > 0 ? 'Fark, Hizmet Bedeli\'nin Cari Hesap üzerinden ayrıca kesildiğini gösterir — normaldir.' : 'Beyanla örtüşüyor.' }}">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                            Ödeme Alındı
                                        </div>
                                    @else
                                        {{-- Gerçek eksik ödeme: beklentinin %90'ından az yatmış --}}
                                        <div class="mt-3 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-red-500/20 border border-red-500/30 text-rose-300 text-xs font-medium z-10 relative"
                                             title="Beklenen: {{ number_format($set['expected_net'] ?? 0, 2, ',', '.') }} ₺ | Yatan: {{ number_format($set['seller_hakedis'], 2, ',', '.') }} ₺ | Fark: {{ number_format(abs($set['variance'] ?? 0), 2, ',', '.') }} ₺">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                            Eksik Ödeme — {{ number_format(abs($set['variance'] ?? 0), 2, ',', '.') }} ₺ Açık
                                        </div>
                                    @endif

                                    @if(isset($set['settlement_details']) && count($set['settlement_details']) > 1)
                                        <div class="mt-4 pt-3 border-t border-gray-700/50 z-10 relative">
                                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-2">Ekstre İşlem Detayı (Banka Cüzdanı)</p>
                                            <ul class="space-y-1">
                                                @foreach($set['settlement_details'] as $sd)
                                                    <li class="flex justify-between items-center text-xs">
                                                        <span class="text-gray-300 truncate w-36" title="{{ $sd['type'] }}">
                                                            @if(str_contains(mb_strtolower($sd['type']), 'iade'))
                                                                <span class="text-rose-400 font-bold">Kısmi İade Kesintisi</span>
                                                            @else
                                                                {{ $sd['type'] }}
                                                            @endif
                                                        </span>
                                                        <span class="{{ $sd['amount'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }} font-mono tracking-tight">
                                                            {{ $sd['amount'] > 0 ? '+' : '' }}{{ number_format($sd['amount'], 2, ',', '.') }} ₺
                                                        </span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif

                                @elseif($set['is_paid'])
                                    {{-- Durum 2: Vade tarihi geçti, muhtemelen yatmıştır (Excel yüklenmemiş) --}}
                                    <div class="py-6 text-center z-10 relative">
                                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-emerald-500/20 text-emerald-400 mb-3 border border-emerald-500/30">
                                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        </div>
                                        <p class="text-emerald-300 text-sm font-medium">Yatmış Olmalı</p>
                                        <p class="text-emerald-400 text-xs mt-1">Tahmini: <strong>{{ $set['expected_date'] }}</strong></p>
                                        <p class="text-gray-500 text-[10px] mt-2">Teyit için Ödeme Excel'i yükleyin.</p>
                                    </div>
                                @elseif($set['expected_date'])
                                    {{-- Durum 3: Gelecekte ödenecek --}}
                                    <div class="py-6 text-center z-10 relative">
                                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-amber-500/20 text-amber-400 mb-3 border border-amber-500/30">
                                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        </div>
                                        <p class="text-gray-300 text-sm font-medium">Ödeme Bekliyor</p>
                                        <p class="text-amber-400 text-xs mt-1">Öngörülen: <strong>{{ $set['expected_date'] }}</strong></p>
                                    </div>
                                @else
                                    {{-- Durum 4: Hiç veri yok --}}
                                    <div class="py-6 text-center z-10 relative">
                                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-700 text-gray-400 mb-3">
                                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        </div>
                                        <p class="text-gray-300 text-sm font-medium">Banka Kaydı Yok / Bekliyor</p>
                                    </div>
                                @endif
                            </div>

                            <!-- B. NİHAİ KÂR / ZARAR (ZOLM METRİKLERİ) -->
                            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4 border-b pb-2">Gerçek Kârlılık Özeti</h4>
                                
                                <div class="space-y-4">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-medium text-gray-600">
                                            Ürün Maliyeti (COGS)
                                            @if(($basic['quantity'] ?? 1) > 1)
                                                <span class="text-xs text-gray-400 font-normal ml-1">({{ $basic['quantity'] }} adet)</span>
                                            @endif
                                        </span>
                                        <span class="text-sm font-bold text-gray-900">{{ number_format($sum['cost_of_goods'], 2, ',', '.') }} ₺</span>
                                    </div>
                                    
                                    @if($sum['vat_advantage'] > 0)
                                        <div class="flex justify-between items-center text-green-700 bg-green-50 p-2 rounded">
                                            <span class="text-xs font-semibold">+ KDV Avantajı</span>
                                            <span class="text-xs border border-green-200 bg-white px-2 rounded">{{ number_format($sum['vat_advantage'], 2, ',', '.') }} ₺</span>
                                        </div>
                                    @endif

                                    @if($order['return_loss_sum'] > 0)
                                        <div class="flex flex-col text-red-700 bg-red-50 p-2 rounded">
                                            <div class="flex justify-between items-center mb-1">
                                                <span class="text-xs font-semibold">İade Lojistik Zararı</span>
                                                <span class="text-xs font-bold">{{ number_format($order['return_loss_sum'], 2, ',', '.') }} ₺</span>
                                            </div>
                                            <span class="text-[10px] opacity-75">Gidiş kargo bedeli + Dönüş kargo faturası</span>
                                        </div>
                                    @endif

                                    <div class="pt-3 border-t-2 border-dashed border-gray-200">
                                        <div class="flex justify-between items-end">
                                            <span class="text-sm font-bold text-gray-900">Net Kâr Durumu</span>
                                            <span class="text-2xl font-black {{ $sum['is_loss'] ? 'text-red-600' : 'text-green-600' }}">
                                                {{ $sum['is_loss'] ? '-' : '+' }}{{ number_format(abs($sum['absolute_net_profit']), 2, ',', '.') }} ₺
                                            </span>
                                        </div>
                                        
                                        {{-- Soru İşareti / Kâr Motoru Matematiği (Profesyonel Görünüm) --}}
                                        <div class="mt-4 pt-4 border-t border-gray-100">
                                            <h5 class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-3">Kâr Motoru Matematiği</h5>
                                            <div class="space-y-2.5 text-sm">
                                                
                                                <div class="flex justify-between items-center text-gray-700">
                                                    <span class="flex items-center gap-2">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                                        Başlangıç (Hakediş)
                                                    </span>
                                                    <span class="font-semibold">{{ number_format($sum['base_revenue'], 2, ',', '.') }} ₺</span>
                                                </div>

                                                @if(isset($sum['stopaj_deduction']) && $sum['stopaj_deduction'] > 0)
                                                <div class="flex justify-between items-center text-gray-500">
                                                    <span class="flex items-center gap-2">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>
                                                        E-Ticaret Stopajı
                                                    </span>
                                                    <span class="text-red-500 font-medium">-{{ number_format($sum['stopaj_deduction'], 2, ',', '.') }} ₺</span>
                                                </div>
                                                @endif

                                                @if(isset($sum['vat_payable']) && $sum['vat_payable'] > 0)
                                                <div class="flex justify-between items-center text-gray-500">
                                                    <span class="flex items-center gap-2">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>
                                                        Net KDV Yükü
                                                    </span>
                                                    <span class="text-red-500 font-medium">-{{ number_format($sum['vat_payable'], 2, ',', '.') }} ₺</span>
                                                </div>
                                                @endif

                                                @if(isset($sum['vat_advantage']) && $sum['vat_advantage'] > 0)
                                                <div class="flex justify-between items-center text-gray-600">
                                                    <span class="flex items-center gap-2">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-green-400"></span>
                                                        KDV Avantajı
                                                    </span>
                                                    <span class="text-green-600 font-medium">+{{ number_format($sum['vat_advantage'], 2, ',', '.') }} ₺</span>
                                                </div>
                                                @endif
                                                
                                                @php $toplamMaliyet = $sum['cost_of_goods'] + $sum['total_extra_debt']; @endphp
                                                @if($toplamMaliyet > 0)
                                                <div class="flex justify-between items-center text-gray-500">
                                                    <span class="flex items-center gap-2">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>
                                                        Ürün & Ek Maliyetler
                                                    </span>
                                                    <span class="text-red-500 font-medium">-{{ number_format($toplamMaliyet, 2, ',', '.') }} ₺</span>
                                                </div>
                                                @else
                                                <div class="flex justify-between items-center text-gray-400">
                                                    <span class="flex items-center gap-2 shrink-0">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-300"></span>
                                                        Ürün & Ek Maliyetler
                                                    </span>
                                                    <span class="font-medium">0,00 ₺</span>
                                                </div>
                                                @endif

                                            </div>
                                        </div>

                                        @if($sum['is_loss'])
                                            <p class="text-[11px] text-red-500 font-medium text-right mt-2">Bu siparişten zarar ettiğiniz tespit edilmiştir.</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- FOOTER -->
                <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex flex-row-reverse">
                    <button type="button" wire:click="closeOrderModal" class="w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-5 py-2.5 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto transition-colors">
                        Kapat
                    </button>
                    <!-- TODO: PDF İndir Butonu Buraya Gelebilir -->
                </div>

            </div>
        </div>
    </div>
@endif
