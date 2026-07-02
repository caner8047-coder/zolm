{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- PAZARYERI MUHASEBE — PROFESYONEL AYARLAR PANELİ --}}
{{-- 6 Bölümlük Accordion / Collapsible Yapı --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}

<div class="max-w-4xl mx-auto space-y-4">

    {{-- Başarı / Hata Mesajları --}}
    @if (session()->has('settings_success'))
        <div class="bg-emerald-50 text-emerald-700 p-4 rounded-xl flex items-center gap-2 border border-emerald-200 shadow-sm animate-fadeIn">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <span class="font-medium">{{ session('settings_success') }}</span>
        </div>
    @endif
    @if (session()->has('settings_error'))
        <div class="bg-red-50 text-red-700 p-4 rounded-xl flex items-center gap-2 border border-red-200 shadow-sm">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            <span class="font-medium">{{ session('settings_error') }}</span>
        </div>
    @endif

    {{-- ═══════════════ BÖLÜM 0: FİRMA PROFİLİ ═══════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <button wire:click="toggleSettingsSection('company')" type="button"
                class="w-full px-6 py-4 flex items-center justify-between bg-gradient-to-r from-gray-50 to-white hover:from-gray-100 transition-all">
            <div class="flex items-center gap-3">
                <span class="text-2xl">🏢</span>
                <div class="text-left">
                    <h3 class="text-base font-bold text-gray-900">Firma Profili</h3>
                    <p class="text-xs text-gray-500">Şirket bilgileri, vergi numarası ve iletişim</p>
                </div>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform {{ $settingsActiveSection === 'company' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </button>
        @if($settingsActiveSection === 'company')
        <div class="p-6 border-t border-gray-100 space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="space-y-1.5 md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700">Firma / Şirket Adı</label>
                    <input type="text" wire:model="settingsCompanyName" placeholder="Örn: ABC Tekstil Ltd. Şti." class="px-4 py-2.5 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                </div>
                <div class="space-y-1.5">
                    <label class="block text-sm font-semibold text-gray-700">Vergi Numarası / T.C. Kimlik</label>
                    <input type="text" wire:model="settingsCompanyTaxNumber" placeholder="10 haneli vergi no veya 11 haneli TC" class="px-4 py-2.5 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm font-mono tracking-wider">
                </div>
                <div class="space-y-1.5">
                    <label class="block text-sm font-semibold text-gray-700">Vergi Dairesi</label>
                    <input type="text" wire:model="settingsCompanyTaxOffice" placeholder="Örn: Kadıköy" class="px-4 py-2.5 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                </div>
                <div class="space-y-1.5">
                    <label class="block text-sm font-semibold text-gray-700">Telefon</label>
                    <input type="tel" wire:model="settingsCompanyPhone" placeholder="05XX XXX XX XX" class="px-4 py-2.5 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                </div>
                <div class="space-y-1.5">
                    <label class="block text-sm font-semibold text-gray-700">E-posta</label>
                    <input type="email" wire:model="settingsCompanyEmail" placeholder="muhasebe@firma.com" class="px-4 py-2.5 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                </div>
                <div class="space-y-1.5 md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700">Firma Açık Adresi</label>
                    <textarea wire:model="settingsCompanyAddress" rows="2" placeholder="Firma tam adresi..." class="px-4 py-2.5 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"></textarea>
                </div>
                <div class="space-y-1.5 md:col-span-2 text-sm font-bold text-gray-800 border-b pb-2 mt-2">Banka ve Yetkili Bilgileri (Tazmin vb. süreçler için)</div>
                <div class="space-y-1.5">
                    <label class="block text-sm font-semibold text-gray-700">IBAN</label>
                    <input type="text" wire:model="settingsCompanyIban" placeholder="TR..." class="px-4 py-2.5 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm font-mono tracking-wider">
                </div>
                <div class="space-y-1.5">
                    <label class="block text-sm font-semibold text-gray-700">Banka Adı</label>
                    <input type="text" wire:model="settingsCompanyBank" placeholder="Örn: Garanti BBVA" class="px-4 py-2.5 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                </div>
                <div class="space-y-1.5">
                    <label class="block text-sm font-semibold text-gray-700">Şube Adı</label>
                    <input type="text" wire:model="settingsCompanyBranch" placeholder="Örn: Kadıköy Şubesi" class="px-4 py-2.5 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                </div>
                <div class="space-y-1.5">
                    <label class="block text-sm font-semibold text-gray-700">Yetkili Adı Soyadı</label>
                    <input type="text" wire:model="settingsCompanyManager" placeholder="Örn: Ali Yılmaz" class="px-4 py-2.5 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                </div>
                <div class="space-y-1.5">
                    <label class="block text-sm font-semibold text-gray-700">MERSİS Numarası</label>
                    <input type="text" wire:model="settingsCompanyMersis" placeholder="Örn: 0123456789000015" class="px-4 py-2.5 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm font-mono tracking-wider">
                </div>
            </div>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-xs text-blue-700 flex items-start gap-2">
                <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>Firma bilgileri Excel raporlarının kapak sayfasında, e-Arşiv fatura hatırlatmalarında ve ERP entegrasyonunda kullanılır.</span>
            </div>
        </div>
        @endif
    </div>

    {{-- ═══════════════ BÖLÜM 0B: KÂRLILIK HEDEFLERİ ═══════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <button wire:click="toggleSettingsSection('profitability')" type="button"
                class="w-full px-6 py-4 flex items-center justify-between bg-gradient-to-r from-gray-50 to-white hover:from-gray-100 transition-all">
            <div class="flex items-center gap-3">
                <span class="text-2xl">📊</span>
                <div class="text-left">
                    <h3 class="text-base font-bold text-gray-900">Kârlılık & Maliyet Hedefleri</h3>
                    <p class="text-xs text-gray-500">Hedef kâr marjı, minimum eşik ve varsayılan ambalaj maliyeti</p>
                </div>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform {{ $settingsActiveSection === 'profitability' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </button>
        @if($settingsActiveSection === 'profitability')
        <div class="p-6 border-t border-gray-100 space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div class="space-y-1.5">
                    <label class="block text-sm font-semibold text-gray-700">Hedef Kâr Marjı</label>
                    <div class="relative">
                        <input type="number" step="0.5" wire:model="settingsTargetProfitMargin" class="px-4 py-2.5 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none">%</span>
                    </div>
                    <p class="text-xs text-gray-400">Bu marjın altındaki ürünler denetim raporunda "uyarı" olarak işaretlenir</p>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-sm font-semibold text-gray-700">Minimum Kâr Eşiği</label>
                    <div class="relative">
                        <input type="number" step="0.5" wire:model="settingsMinProfitMargin" class="px-4 py-2.5 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 text-sm">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none">%</span>
                    </div>
                    <p class="text-xs text-gray-400">Bu eşiğin altındaki ürünler "kritik" uyarı alır — fiyat güncelleme gerekir</p>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-sm font-semibold text-gray-700">Varsayılan Ambalaj Maliyeti</label>
                    <div class="relative">
                        <input type="number" step="0.50" wire:model="settingsDefaultPackagingCost" class="px-4 py-2.5 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none">TL / sipariş</span>
                    </div>
                    <p class="text-xs text-gray-400">Her siparişe uygulanacak varsayılan paketleme + kutu maliyeti</p>
                </div>
            </div>

            {{-- Hedef Gösterge --}}
            <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="p-4 rounded-xl border-2 border-emerald-200 bg-emerald-50/50 text-center">
                    <p class="text-3xl font-bold text-emerald-600">%{{ number_format($settingsTargetProfitMargin, 1) }}</p>
                    <p class="text-xs text-emerald-600 font-medium mt-1">🎯 Hedef Kâr Marjı</p>
                </div>
                <div class="p-4 rounded-xl border-2 border-red-200 bg-red-50/50 text-center">
                    <p class="text-3xl font-bold text-red-600">%{{ number_format($settingsMinProfitMargin, 1) }}</p>
                    <p class="text-xs text-red-600 font-medium mt-1">⚠️ Minimum Eşik</p>
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- ═══════════════ BÖLÜM 1: VERGİ & KDV ═══════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <button wire:click="toggleSettingsSection('tax')" type="button"
                class="w-full px-6 py-4 flex items-center justify-between bg-gradient-to-r from-gray-50 to-white hover:from-gray-100 transition-all">
            <div class="flex items-center gap-3">
                <span class="text-2xl">🏛️</span>
                <div class="text-left">
                    <h3 class="text-base font-bold text-gray-900">Vergi & KDV Ayarları</h3>
                    <p class="text-xs text-gray-500">Stopaj, ürün KDV ve gider KDV oranları</p>
                </div>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform {{ $settingsActiveSection === 'tax' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </button>
        @if($settingsActiveSection === 'tax')
        <div class="p-6 border-t border-gray-100 space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div class="space-y-1.5">
                    <label class="block text-sm font-semibold text-gray-700">E-Ticaret Stopaj Oranı</label>
                    <div class="relative">
                        <input type="number" step="0.001" wire:model="settingsStopajRate" class="px-4 py-2.5 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none">oran</span>
                    </div>
                    <p class="text-xs text-gray-400">Şahıs şirketleri için. Örn: %1 → 0.01</p>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-sm font-semibold text-gray-700">Ürün / Satış KDV Oranı</label>
                    <div class="relative">
                        <input type="number" step="0.001" wire:model="settingsDefaultProductVatRate" class="px-4 py-2.5 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none">oran</span>
                    </div>
                    <p class="text-xs text-gray-400">Varsayılan ürün KDV. Örn: %10 → 0.10</p>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-sm font-semibold text-gray-700">Gider / Hizmet KDV Oranı</label>
                    <div class="relative">
                        <input type="number" step="0.001" wire:model="settingsExpenseVatRate" class="px-4 py-2.5 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none">oran</span>
                    </div>
                    <p class="text-xs text-gray-400">Komisyon/kargo faturası KDV. Örn: %20 → 0.20</p>
                </div>
            </div>

            {{-- KDV Hesaplama Toggle --}}
            <div class="mt-6 p-5 rounded-xl border-2 transition-all {{ $settingsKdvHesaplamaAktif ? 'border-emerald-300 bg-emerald-50/50' : 'border-gray-200 bg-gray-50/50' }}">
                <label class="flex items-center justify-between cursor-pointer">
                    <div class="flex items-center gap-4">
                        <div class="text-2xl">{{ $settingsKdvHesaplamaAktif ? '✅' : '⬜' }}</div>
                        <div>
                            <p class="font-bold text-gray-900 text-sm">Net KDV Yükü Hesaplaması</p>
                            <p class="text-xs text-gray-500 mt-0.5">Kâr hesaplarında KDV yükümlülüğünü dahil et</p>
                        </div>
                    </div>
                    <div class="relative">
                        <input type="checkbox" wire:model.live="settingsKdvHesaplamaAktif" class="sr-only peer">
                        <div class="w-14 h-7 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:start-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-emerald-500 shadow-inner"></div>
                    </div>
                </label>

                @if(!$settingsKdvHesaplamaAktif)
                <div class="mt-3 bg-blue-50 border border-blue-200 rounded-lg p-3 text-xs text-blue-700 flex items-start gap-2">
                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>KDV hesaplaması şu anda <strong>kapalı</strong>. Kâr hesaplarınızda Net KDV Yükü/Avantajı dikkate alınmıyor. Eğer KDV'nizi yerel veya başka kanallardan dengeliyorsanız kapalı bırakabilirsiniz.</span>
                </div>
                @else
                <div class="mt-3 bg-emerald-50 border border-emerald-200 rounded-lg p-3 text-xs text-emerald-700 flex items-start gap-2">
                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span>KDV hesaplaması <strong>aktif</strong>. Satış KDV'si ve Gider KDV'si (komisyon + kargo) mahsuplaşması yapılarak Net KDV Yükü kâr hesaplarınızdan düşülmektedir.</span>
                </div>
                @endif
            </div>

            {{-- Teorik Stopaj Tahmini Toggle --}}
            <div class="p-5 rounded-xl border-2 transition-all {{ $settingsEstimatedWithholdingEnabled ? 'border-amber-300 bg-amber-50/60' : 'border-gray-200 bg-gray-50/50' }}">
                <label class="flex items-center justify-between cursor-pointer gap-4">
                    <div class="flex items-center gap-4 min-w-0">
                        <div class="text-2xl">{{ $settingsEstimatedWithholdingEnabled ? '✅' : '⬜' }}</div>
                        <div class="min-w-0">
                            <p class="font-bold text-gray-900 text-sm">Teorik Stopaj Tahmini</p>
                            <p class="text-xs text-gray-500 mt-0.5">Gerçek stopaj finans hareketi yoksa KDV hariç matrah üzerinden tahmini kesinti üret</p>
                        </div>
                    </div>
                    <div class="relative flex-shrink-0">
                        <input type="checkbox" wire:model.live="settingsEstimatedWithholdingEnabled" class="sr-only peer">
                        <div class="w-14 h-7 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-amber-200 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:start-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-amber-500 shadow-inner"></div>
                    </div>
                </label>

                @if(!$settingsEstimatedWithholdingEnabled)
                <div class="mt-3 bg-blue-50 border border-blue-200 rounded-lg p-3 text-xs text-blue-700 flex items-start gap-2">
                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Teorik stopaj şu anda <strong>kapalı</strong>. Kâr snapshot'larında yalnızca pazaryerinden gelen gerçek stopaj hareketi dikkate alınır.</span>
                </div>
                @else
                <div class="mt-3 bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-800 flex items-start gap-2">
                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l2 2 4-6M12 3l8 4v5c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V7l8-4z"></path></svg>
                    <span>Teorik stopaj <strong>aktif</strong>. Gerçek stopaj hareketi yoksa brüt satış KDV hariç matraha çevrilir ve stopaj oranına göre tahmini kesinti hesaplanır.</span>
                </div>
                @endif
            </div>
        </div>
        @endif
    </div>

    {{-- ═══════════════ BÖLÜM 2: KARGO & BAREM ═══════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <button wire:click="toggleSettingsSection('cargo')" type="button"
                class="w-full px-6 py-4 flex items-center justify-between bg-gradient-to-r from-gray-50 to-white hover:from-gray-100 transition-all">
            <div class="flex items-center gap-3">
                <span class="text-2xl">🚚</span>
                <div class="text-left">
                    <h3 class="text-base font-bold text-gray-900">Kargo & Barem Ayarları</h3>
                    <p class="text-xs text-gray-500">Barem limiti, kargo firmaları ve ağır kargo ceza tutarları</p>
                </div>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform {{ $settingsActiveSection === 'cargo' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </button>
        @if($settingsActiveSection === 'cargo')
        <div class="p-6 border-t border-gray-100 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="space-y-1.5">
                    <label class="block text-sm font-semibold text-gray-700">Pazaryeri</label>
                    <select wire:model="settingsMarketplace" class="px-4 py-2.5 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm bg-white">
                        <option value="Trendyol">🟠 Trendyol</option>
                        <option value="Hepsiburada">🟣 Hepsiburada</option>
                        <option value="N11">🔵 N11</option>
                        <option value="Amazon">🟡 Amazon TR</option>
                        <option value="Çiçeksepeti">🌸 Çiçeksepeti</option>
                    </select>
                    <p class="text-xs text-gray-400">Birden fazla pazaryeri desteği yakında eklenecek</p>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-sm font-semibold text-gray-700">Kargo Barem Limiti (TL)</label>
                    <input type="number" step="1" wire:model="settingsBaremLimit" class="px-4 py-2.5 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    <p class="text-xs text-gray-400">Bu tutarın altındaki siparişlere barem fiyatı uygulanır</p>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-sm font-semibold text-gray-700">Varsayılan Kargo Firması</label>
                    <select wire:model="settingsDefaultCargoCompany" class="px-4 py-2.5 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm bg-white">
                        @foreach($settingsCargoCompanies as $c)
                            <option value="{{ $c }}">{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Kendi Kargo Toggle --}}
            <div class="mt-4 p-5 rounded-xl border-2 transition-all {{ $settingsUsesOwnCargo ? 'border-emerald-300 bg-emerald-50/50' : 'border-gray-200 bg-gray-50/50' }}">
                <label class="flex items-center justify-between cursor-pointer">
                    <div class="flex items-center gap-4">
                        <div class="text-2xl">{{ $settingsUsesOwnCargo ? '✅' : '⬜' }}</div>
                        <div>
                            <p class="font-bold text-gray-900 text-sm">Kendi Kargo Anlaşmam Var</p>
                            <p class="text-xs text-gray-500 mt-0.5">Trendyol kargo kullanmıyorum, kendi anlaşmamla gönderiyorum</p>
                        </div>
                    </div>
                    <div class="relative">
                        <input type="checkbox" wire:model.live="settingsUsesOwnCargo" class="sr-only peer">
                        <div class="w-14 h-7 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:start-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-emerald-500 shadow-inner"></div>
                    </div>
                </label>

                @if(!$settingsUsesOwnCargo)
                <div class="mt-3 bg-blue-50 border border-blue-200 rounded-lg p-3 text-xs text-blue-700 flex items-start gap-2">
                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Kendi kargo <strong>kapalı</strong>. Kâr hesaplarında ve <strong>Kargo Maliyeti Aşımı</strong> denetiminde Pazaryeri Ürünlerim'deki kendi kargo maliyeti dikkate alınmaz; yalnızca Trendyol kesintileri baz alınır.</span>
                </div>
                @else
                <div class="mt-3 bg-emerald-50 border border-emerald-200 rounded-lg p-3 text-xs text-emerald-700 flex items-start gap-2">
                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span>Kendi kargo <strong>aktif</strong>. Pazaryeri Ürünlerim'de tanımlı kargo maliyeti (ürün başına) sipariş kâr hesaplarına ve <strong>Kargo Maliyeti Aşımı</strong> denetimine dahil edilir. <strong>Maliyetleri Senkronize Et</strong> butonuyla mevcut siparişlere de uygulanır.</span>
                </div>
                @endif
            </div>

            {{-- Kargo Firmaları Listesi --}}
            <div class="space-y-3">
                <h4 class="text-sm font-bold text-gray-700 border-b pb-2">Kargo Firmaları</h4>
                <div class="flex flex-wrap gap-2">
                    @foreach($settingsCargoCompanies as $company)
                        <span class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-50 text-blue-700 rounded-full text-sm font-medium">
                            {{ $company }}
                            <button wire:click="removeCargoCompany('{{ $company }}')" class="ml-1 text-blue-400 hover:text-red-500 transition-colors" title="Kaldır">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </span>
                    @endforeach
                </div>
                <div class="flex gap-2">
                    <input type="text" wire:model="newCargoCompany" placeholder="Yeni kargo firması adı..." class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <button wire:click="addCargoCompany" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                        + Ekle
                    </button>
                </div>
            </div>

            {{-- Ağır Kargo Ceza Tutarları --}}
            <div class="space-y-3">
                <h4 class="text-sm font-bold text-gray-700 border-b pb-2">Ağır Kargo Ceza Tutarları (100+ Desi)</h4>
                @if(!empty($settingsHeavyCargoPenalties))
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="bg-gray-50">
                            <th class="px-3 py-2 text-left font-semibold text-gray-600">Kargo Firması</th>
                            <th class="px-3 py-2 text-right font-semibold text-gray-600">Ceza Tutarı (TL)</th>
                            <th class="px-3 py-2 text-center font-semibold text-gray-600 w-16">İşlem</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($settingsHeavyCargoPenalties as $company => $amount)
                            <tr>
                                <td class="px-3 py-2 font-medium">{{ $company }}</td>
                                <td class="px-3 py-2 text-right">
                                    <input type="number" step="1" wire:model="settingsHeavyCargoPenalties.{{ $company }}" class="w-32 px-2 py-1 border border-gray-300 rounded text-right text-sm">
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <button wire:click="removeHeavyCargoPenalty('{{ $company }}')" class="text-red-400 hover:text-red-600" title="Kaldır">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
                <div class="flex gap-2">
                    <input type="text" wire:model="newPenaltyCompany" placeholder="Firma adı..." class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <input type="number" wire:model="newPenaltyAmount" placeholder="Tutar (TL)" class="w-32 px-3 py-2 border border-gray-300 rounded-lg text-sm text-right">
                    <button wire:click="addHeavyCargoPenalty" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">+ Ekle</button>
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- ═══════════════ BÖLÜM 3: DESİ FİYAT TABLOSU ═══════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <button wire:click="toggleSettingsSection('desi')" type="button"
                class="w-full px-6 py-4 flex items-center justify-between bg-gradient-to-r from-gray-50 to-white hover:from-gray-100 transition-all">
            <div class="flex items-center gap-3">
                <span class="text-2xl">📦</span>
                <div class="text-left">
                    <h3 class="text-base font-bold text-gray-900">Desi Fiyat Tablosu</h3>
                    <p class="text-xs text-gray-500">Kargo firması bazında desi aralıkları ve fiyatları</p>
                </div>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform {{ $settingsActiveSection === 'desi' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </button>
        @if($settingsActiveSection === 'desi')
        <div class="p-6 border-t border-gray-100">
            @php
                $desiLabels = [
                    'desi_0_2' => '0-2 Desi', 'desi_3' => '3 Desi', 'desi_4' => '4 Desi',
                    'desi_5' => '5 Desi', 'desi_10' => '10 Desi', 'desi_15' => '15 Desi',
                    'desi_20' => '20 Desi', 'desi_25' => '25 Desi', 'desi_30' => '30 Desi',
                ];
            @endphp
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="bg-gray-50">
                        <th class="px-3 py-2 text-left font-semibold text-gray-600 sticky left-0 bg-gray-50">Firma</th>
                        @foreach($desiLabels as $key => $label)
                            <th class="px-2 py-2 text-center font-semibold text-gray-600 whitespace-nowrap text-xs">{{ $label }}</th>
                        @endforeach
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($settingsCargoCompanies as $company)
                        <tr>
                            <td class="px-3 py-2 font-medium text-gray-800 sticky left-0 bg-white whitespace-nowrap">{{ $company }}</td>
                            @foreach($desiLabels as $key => $label)
                            <td class="px-1 py-1.5">
                                <input type="number" step="0.01"
                                       wire:model="settingsDesiPrices.{{ $company }}.{{ $key }}"
                                       placeholder="-"
                                       class="w-16 px-1 py-1 border border-gray-200 rounded text-center text-xs focus:ring-1 focus:ring-blue-400 focus:border-blue-400">
                            </td>
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex justify-end">
                <button wire:click="saveDesiPrices" wire:loading.attr="disabled" class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors flex items-center gap-2">
                    <span wire:loading.remove wire:target="saveDesiPrices">💾 Desi Fiyatları Kaydet</span>
                    <span wire:loading wire:target="saveDesiPrices">Kaydediliyor...</span>
                </button>
            </div>
        </div>
        @endif
    </div>

    {{-- ═══════════════ BÖLÜM 4: BAREM FİYAT TABLOSU ═══════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <button wire:click="toggleSettingsSection('barem')" type="button"
                class="w-full px-6 py-4 flex items-center justify-between bg-gradient-to-r from-gray-50 to-white hover:from-gray-100 transition-all">
            <div class="flex items-center gap-3">
                <span class="text-2xl">💰</span>
                <div class="text-left">
                    <h3 class="text-base font-bold text-gray-900">Barem Fiyat Tablosu</h3>
                    <p class="text-xs text-gray-500">Sipariş tutarı aralıklarına göre barem kargo fiyatları</p>
                </div>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform {{ $settingsActiveSection === 'barem' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </button>
        @if($settingsActiveSection === 'barem')
        <div class="p-6 border-t border-gray-100">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="bg-gray-50">
                        <th class="px-4 py-2 text-left font-semibold text-gray-600">Kargo Firması</th>
                        <th class="px-4 py-2 text-center font-semibold text-gray-600">0 — 150 TL Arası (TL)</th>
                        <th class="px-4 py-2 text-center font-semibold text-gray-600">150 — 300 TL Arası (TL)</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($settingsCargoCompanies as $company)
                        <tr>
                            <td class="px-4 py-2 font-medium text-gray-800">{{ $company }}</td>
                            <td class="px-4 py-1.5">
                                <input type="number" step="0.01" wire:model="settingsBaremPrices.{{ $company }}.barem_0_150" placeholder="-" class="w-28 px-3 py-1.5 border border-gray-200 rounded text-center text-sm">
                            </td>
                            <td class="px-4 py-1.5">
                                <input type="number" step="0.01" wire:model="settingsBaremPrices.{{ $company }}.barem_150_300" placeholder="-" class="w-28 px-3 py-1.5 border border-gray-200 rounded text-center text-sm">
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex justify-end">
                <button wire:click="saveBaremPrices" wire:loading.attr="disabled" class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors flex items-center gap-2">
                    <span wire:loading.remove wire:target="saveBaremPrices">💾 Barem Fiyatları Kaydet</span>
                    <span wire:loading wire:target="saveBaremPrices">Kaydediliyor...</span>
                </button>
            </div>
        </div>
        @endif
    </div>

    {{-- ═══════════════ BÖLÜM 5: DENETİM LİMİTLERİ ═══════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <button wire:click="toggleSettingsSection('audit')" type="button"
                class="w-full px-6 py-4 flex items-center justify-between bg-gradient-to-r from-gray-50 to-white hover:from-gray-100 transition-all">
            <div class="flex items-center gap-3">
                <span class="text-2xl">🛡️</span>
                <div class="text-left">
                    <h3 class="text-base font-bold text-gray-900">Denetim Limitleri</h3>
                    <p class="text-xs text-gray-500">Audit Engine kurallarının hassasiyet eşikleri</p>
                </div>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform {{ $settingsActiveSection === 'audit' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </button>
        @if($settingsActiveSection === 'audit')
        <div class="p-4 lg:p-6 border-t border-gray-100 space-y-6">
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800">
                Limitleri düşürmek daha fazla alarm üretir, örneklem eşiklerini yükseltmek gürültüyü azaltır. Kural aç/kapa ve bilgi logu tercihleri anlık kaydedilir; sayısal eşikler alttaki kaydet butonuyla saklanır.
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 lg:gap-4">
                <label class="rounded-xl border border-gray-200 p-4 flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Bilgi Loglarını Dahil Et</p>
                        <p class="text-xs text-gray-500 mt-1">E-Arşiv uyarısı, çoklu sepet, kısmi iade gibi hata dışı bilgilendirme kayıtlarını da rapora ekler.</p>
                    </div>
                    <input type="checkbox" wire:model.live="settingsLogInfoRules" class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-200 w-4 h-4">
                </label>
                <label class="rounded-xl border border-gray-200 p-4 flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Cari-Komisyon Kontrolü</p>
                        <p class="text-xs text-gray-500 mt-1">Cari-Ödeme Uyumu denetiminde komisyon toplamını da karşılaştırır.</p>
                    </div>
                    <input type="checkbox" wire:model.live="settingsTransactionCheckCommissionEnabled" class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-200 w-4 h-4">
                </label>
                <label class="rounded-xl border border-gray-200 p-4 flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Cari-Kargo Kontrolü</p>
                        <p class="text-xs text-gray-500 mt-1">Cari-Ödeme Uyumu denetiminde kargo toplamını da karşılaştırır.</p>
                    </div>
                    <input type="checkbox" wire:model.live="settingsTransactionCheckCargoEnabled" class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-200 w-4 h-4">
                </label>
            </div>

            <div class="rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                    <h4 class="text-sm font-bold text-gray-800">Kural Yöneticisi</h4>
                    <p class="text-xs text-gray-500 mt-1">Bir kuralı kapattığınızda audit ekranında da kapalı kalır. Bilgi logları kapalıysa bilgi seviyesi kurallar ayrıca pasifleşir.</p>
                </div>
                @php
                    $auditCategories = collect(\App\Services\AuditEngine::RULE_META)->groupBy('category');
                @endphp
                <div class="p-4 lg:p-6 grid grid-cols-1 xl:grid-cols-2 gap-3 lg:gap-4">
                    @foreach($auditCategories as $category => $rules)
                        <div class="rounded-xl border border-gray-200 overflow-hidden">
                            <div class="px-4 py-2.5 bg-white border-b border-gray-100">
                                <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">{{ $category }}</p>
                            </div>
                            <div class="divide-y divide-gray-100">
                                @foreach($rules as $method => $meta)
                                    @php
                                        $isDisabled = in_array($method, $disabledAuditRules);
                                        $isImplicitlySuppressed = !$settingsLogInfoRules && ($meta['severity'] === 'info');
                                    @endphp
                                    <label class="px-4 py-3 flex items-start gap-3 cursor-pointer {{ ($isDisabled || $isImplicitlySuppressed) ? 'opacity-60' : '' }}">
                                        <input type="checkbox"
                                               wire:click="toggleAuditRule('{{ $method }}')"
                                               {{ !$isDisabled ? 'checked' : '' }}
                                               class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-200 w-4 h-4">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-sm">{{ $meta['icon'] }}</span>
                                                <span class="text-sm font-semibold text-gray-900">{{ $meta['title'] }}</span>
                                                <span class="px-2 py-0.5 text-[10px] font-mono rounded {{ $meta['severity'] === 'critical' ? 'bg-red-100 text-red-700' : ($meta['severity'] === 'warning' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700') }}">
                                                    {{ $meta['code'] }}
                                                </span>
                                                @if($isImplicitlySuppressed)
                                                    <span class="px-2 py-0.5 text-[10px] font-mono rounded bg-slate-100 text-slate-600">
                                                        Bilgi logu kapalı
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="text-xs text-gray-500 mt-1 leading-relaxed">{{ $meta['tooltip'] }}</p>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="pt-1">
                <h4 class="text-sm font-bold text-gray-700 mb-3">Temel Kabul Edilebilir Farklar</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 lg:gap-4">
                    @php
                        $toleranceFields = [
                            ['model' => 'settingsStopajTolerance', 'label' => 'Stopaj Farkı', 'unit' => 'TL', 'hint' => 'Brüt×oran hesabı ile kesilen stopaj arasındaki kabul edilebilir fark', 'step' => '0.01'],
                            ['model' => 'settingsCommissionMismatchTolerance', 'label' => 'Komisyon Farkı', 'unit' => 'TL', 'hint' => 'Komisyon kuruş farkları için alarm eşiği', 'step' => '0.01'],
                            ['model' => 'settingsBaremExcessTolerance', 'label' => 'Barem Aşımı', 'unit' => 'TL', 'hint' => 'Barem üzeri kargo farkı bu değeri geçerse alarm üretir', 'step' => '0.01'],
                            ['model' => 'settingsCommissionRefundTolerance', 'label' => 'Komisyon İadesi', 'unit' => 'TL', 'hint' => 'İade siparişte eksik komisyon iadesi eşiği', 'step' => '0.01'],
                            ['model' => 'settingsCommissionRefundTrackingTolerance', 'label' => 'Komisyon İade Takip', 'unit' => 'TL', 'hint' => 'Toplu iade takibinde sipariş bazlı açık fark eşiği', 'step' => '0.01'],
                            ['model' => 'settingsHakedisTolerance', 'label' => 'Ödeme Farkı', 'unit' => 'TL', 'hint' => 'Beklenen ödeme ile raporlanan ödeme farkı', 'step' => '0.01'],
                            ['model' => 'settingsMissingPaymentTolerance', 'label' => 'Eksik Ödeme', 'unit' => 'TL', 'hint' => 'Beklenen ödeme ile yatan ödeme farkı bu değeri aşarsa alarm', 'step' => '0.01'],
                            ['model' => 'settingsDelayedPaymentDays', 'label' => 'Geciken Ödeme', 'unit' => 'gün', 'hint' => 'Teslimattan sonra kaç gün geçince kayıp ödeme sayılacağı', 'step' => '1'],
                            ['model' => 'settingsHeavyCargoTolerance', 'label' => 'Ağır Kargo', 'unit' => 'TL', 'hint' => 'Bilinen ağır kargo ceza tutarına yakınlık eşiği', 'step' => '0.01'],
                        ];
                    @endphp
                    @foreach($toleranceFields as $field)
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-gray-600">{{ $field['label'] }}</label>
                            <div class="relative">
                                <input type="number" step="{{ $field['step'] }}" wire:model="{{ $field['model'] }}" class="w-full px-3 py-3 sm:py-2 border border-gray-200 rounded-lg text-base sm:text-sm focus:ring-1 focus:ring-blue-400">
                                <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] text-gray-400 pointer-events-none">{{ $field['unit'] }}</span>
                            </div>
                            <p class="text-[10px] text-gray-400">{{ $field['hint'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="pt-1 border-t border-gray-100">
                <h4 class="text-sm font-bold text-gray-700 mb-3">Kritik Eşikler ve Kârlılık Guardrail'leri</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 lg:gap-4">
                    @php
                        $criticalFields = [
                            ['model' => 'settingsSunkCostCriticalThreshold', 'label' => 'Yanık Maliyet Kritik Eşiği', 'unit' => 'TL', 'hint' => 'Bu toplam lojistik zarar üstü kritik sayılır', 'step' => '1'],
                            ['model' => 'settingsHakedisCriticalThreshold', 'label' => 'Kritik Ödeme Eşiği', 'unit' => 'TL', 'hint' => 'Ödeme farkı bu değeri aşarsa kritik işaretlenir', 'step' => '1'],
                            ['model' => 'settingsMissingPaymentCriticalThreshold', 'label' => 'Eksik Ödeme Kritik Eşiği', 'unit' => 'TL', 'hint' => 'Eksik ödeme farkı bu değeri aşarsa kritik olur', 'step' => '1'],
                            ['model' => 'settingsOperationalPenaltyCriticalThreshold', 'label' => 'Operasyonel Ceza Kritik Eşiği', 'unit' => 'TL', 'hint' => 'Ceza tutarı bu değeri aşarsa kritik işaretlenir', 'step' => '1'],
                            ['model' => 'settingsCargoOverCostRatio', 'label' => 'Kargo/Brüt Kâr Oranı', 'unit' => 'oran', 'hint' => 'Kendi kargo maliyeti, brüt kârın bu oranını aşarsa alarm üretir. %50 için 0.50', 'step' => '0.05'],
                            ['model' => 'settingsNegativeHakedisThreshold', 'label' => 'Negatif Ödeme Eşiği', 'unit' => 'TL', 'hint' => 'Bu değerin altındaki net ödemeler alarm üretir. Her negatif için 0 bırakın', 'step' => '0.50'],
                            ['model' => 'settingsExtremeMarginPositiveThreshold', 'label' => 'Aşırı Pozitif Marj', 'unit' => '%', 'hint' => 'Bu marj üstü siparişler veri hatası şüphesiyle işaretlenir', 'step' => '1'],
                            ['model' => 'settingsExtremeMarginNegativeThreshold', 'label' => 'Aşırı Negatif Marj', 'unit' => '%', 'hint' => 'Bu marj altı siparişler veri hatası şüphesiyle işaretlenir', 'step' => '1'],
                            ['model' => 'settingsCampaignLossMinTotalLoss', 'label' => 'Kampanya Zarar Min.', 'unit' => 'TL', 'hint' => 'Toplam zarar bu tutarın altındaysa kampanya alarmı üretmez', 'step' => '1'],
                            ['model' => 'settingsCampaignLossMinOrderCount', 'label' => 'Kampanya Zarar Min. Sipariş', 'unit' => 'adet', 'hint' => 'Alarm için en az kaç zarar yazan kampanyalı sipariş gerektiği', 'step' => '1'],
                            ['model' => 'settingsMultipleCartFactor', 'label' => 'Çoklu Sepet Çarpanı', 'unit' => 'kat', 'hint' => 'Kargo baremi bu çarpanı aşarsa çoklu sepet ihtimali kontrol edilir', 'step' => '0.1'],
                            ['model' => 'settingsMultipleCartDesiTolerance', 'label' => 'Çoklu Sepet Desi Yakınlığı', 'unit' => 'TL', 'hint' => 'Gerçek kargo tutarı standart desi fiyatına bu kadar yakınsa bilgi alarmı üretir', 'step' => '0.5'],
                        ];
                    @endphp
                    @foreach($criticalFields as $field)
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-gray-600">{{ $field['label'] }}</label>
                            <div class="relative">
                                <input type="number" step="{{ $field['step'] }}" wire:model="{{ $field['model'] }}" class="w-full px-3 py-3 sm:py-2 border border-gray-200 rounded-lg text-base sm:text-sm focus:ring-1 focus:ring-blue-400">
                                <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] text-gray-400 pointer-events-none">{{ $field['unit'] }}</span>
                            </div>
                            <p class="text-[10px] text-gray-400">{{ $field['hint'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="pt-1 border-t border-gray-100">
                <h4 class="text-sm font-bold text-gray-700 mb-3">Trend ve SKU Bazlı Gürültü Kontrolleri</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 lg:gap-4">
                    @php
                        $trendFields = [
                            ['model' => 'settingsPriceDropPercentage', 'label' => 'Fiyat Düşüş Oranı', 'unit' => '%', 'hint' => 'Önceki döneme göre bu yüzde kadar düşüş alarm üretir', 'step' => '0.5'],
                            ['model' => 'settingsPriceDropMinOrders', 'label' => 'Fiyat Düşüş Min. Sipariş', 'unit' => 'adet', 'hint' => 'Her iki dönemde de en az bu kadar sipariş yoksa kıyas yapılmaz', 'step' => '1'],
                            ['model' => 'settingsCommissionRateChangeThreshold', 'label' => 'Komisyon Artış Eşiği', 'unit' => 'puan', 'hint' => 'Komisyon oranı bu kadar puan artınca alarm üretir', 'step' => '0.1'],
                            ['model' => 'settingsCommissionRateChangeMinOrders', 'label' => 'Komisyon Artış Min. Sipariş', 'unit' => 'adet', 'hint' => 'Komisyon karşılaştırması için iki dönemde de minimum örneklem', 'step' => '1'],
                            ['model' => 'settingsServiceFeeIncreaseThreshold', 'label' => 'Hizmet Bedeli Artış Eşiği', 'unit' => 'puan', 'hint' => 'Aylık service fee oranı bu kadar puan artarsa alarm üretir', 'step' => '0.05'],
                            ['model' => 'settingsServiceFeeIncreaseMinOrders', 'label' => 'Hizmet Bedeli Min. Sipariş', 'unit' => 'adet', 'hint' => 'Küçük örneklemde yanlış alarmı azaltmak için minimum sipariş sayısı', 'step' => '1'],
                            ['model' => 'settingsHighReturnRateThreshold', 'label' => 'Yüksek İade Oranı', 'unit' => '%', 'hint' => 'SKU iade oranı bu eşik üstünde alarm verir', 'step' => '0.5'],
                            ['model' => 'settingsHighReturnRateMinQuantity', 'label' => 'İade Min. Adet', 'unit' => 'adet', 'hint' => 'Toplam teslim+iade adedi bu sayının altındaysa alarm üretmez', 'step' => '1'],
                            ['model' => 'settingsHighCancellationRateThreshold', 'label' => 'Yüksek İptal Oranı', 'unit' => '%', 'hint' => 'SKU iptal oranı bu eşik üstünde alarm verir', 'step' => '0.5'],
                            ['model' => 'settingsHighCancellationRateMinOrders', 'label' => 'İptal Min. Sipariş', 'unit' => 'adet', 'hint' => 'Toplam sipariş bu sayının altındaysa iptal alarmı üretmez', 'step' => '1'],
                        ];
                    @endphp
                    @foreach($trendFields as $field)
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-gray-600">{{ $field['label'] }}</label>
                            <div class="relative">
                                <input type="number" step="{{ $field['step'] }}" wire:model="{{ $field['model'] }}" class="w-full px-3 py-3 sm:py-2 border border-gray-200 rounded-lg text-base sm:text-sm focus:ring-1 focus:ring-blue-400">
                                <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] text-gray-400 pointer-events-none">{{ $field['unit'] }}</span>
                            </div>
                            <p class="text-[10px] text-gray-400">{{ $field['hint'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- ═══════════════ BÖLÜM 6: FATURA KONTROL ═══════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <button wire:click="toggleSettingsSection('reconciliation')" type="button"
                class="w-full px-6 py-4 flex items-center justify-between bg-gradient-to-r from-gray-50 to-white hover:from-gray-100 transition-all">
            <div class="flex items-center gap-3">
                <span class="text-2xl">📋</span>
                <div class="text-left">
                    <h3 class="text-base font-bold text-gray-900">Fatura Kontrol Ayarları</h3>
                    <p class="text-xs text-gray-500">Fatura doğrulama sapmaları ve KDV hesaplama parametreleri</p>
                </div>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform {{ $settingsActiveSection === 'reconciliation' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </button>
        @if($settingsActiveSection === 'reconciliation')
        <div class="p-6 border-t border-gray-100">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div class="space-y-1.5">
                    <label class="block text-sm font-semibold text-gray-700">Komisyon Eşleştirme Toleransı</label>
                    <div class="relative">
                        <input type="number" step="0.5" wire:model="settingsCommissionMatchTolerance" class="px-4 py-2.5 w-full border border-gray-300 rounded-lg text-sm">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none">TL</span>
                    </div>
                    <p class="text-xs text-gray-400">Fatura ile sipariş komisyonu arasında kabul edilebilir fark</p>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-sm font-semibold text-gray-700">Kargo Eşleştirme Toleransı</label>
                    <div class="relative">
                        <input type="number" step="0.5" wire:model="settingsCargoMatchTolerance" class="px-4 py-2.5 w-full border border-gray-300 rounded-lg text-sm">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none">TL</span>
                    </div>
                    <p class="text-xs text-gray-400">Fatura ile sipariş kargo tutarı arasında kabul edilebilir fark</p>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-sm font-semibold text-gray-700">KDV Böleni</label>
                    <div class="relative">
                        <input type="number" step="0.01" wire:model="settingsInvoiceVatDivisor" class="px-4 py-2.5 w-full border border-gray-300 rounded-lg text-sm">
                    </div>
                    <p class="text-xs text-gray-400">KDV dahil tutarı matrah çıkarmak için bölen. %20 KDV → 1.20</p>
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- ═══════════════ BÖLÜM 6B: ARAYÜZ & YARDIM ═══════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <button wire:click="toggleSettingsSection('ui_help')" type="button"
                class="w-full px-6 py-4 flex items-center justify-between bg-gradient-to-r from-gray-50 to-white hover:from-gray-100 transition-all">
            <div class="flex items-center gap-3">
                <span class="text-2xl">🧭</span>
                <div class="text-left">
                    <h3 class="text-base font-bold text-gray-900">Genel Arayüz Ayarları</h3>
                    <p class="text-xs text-gray-500">Tooltip ve uygulama genelindeki tercihler artık ayrı ayar modülünde</p>
                </div>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform {{ $settingsActiveSection === 'ui_help' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </button>
        @if($settingsActiveSection === 'ui_help')
        <div class="p-6 border-t border-gray-100 space-y-5">
            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex items-start gap-4">
                        <div class="text-2xl">{{ $settingsHelpTipsEnabled ? '💡' : '🙈' }}</div>
                        <div>
                            <p class="font-bold text-gray-900 text-sm">Bilgilendirici yardım ipuçları {{ $settingsHelpTipsEnabled ? 'aktif' : 'kapalı' }}</p>
                            <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                                Bu tercih artık muhasebe sekmesinden bağımsız, tüm pazaryeri modüllerini etkileyen ayrı bir ayar ekranından yönetiliyor.
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('mp.settings') }}"
                       class="inline-flex min-h-[44px] items-center justify-center rounded-lg bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800">
                        Genel ayarlara git
                    </a>
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- ═══════════════ ANA KAYDET / SIFIRLA BUTONLARI ═══════════════ --}}
    <div class="space-y-3 pt-2">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <button wire:click="resetToDefaults" wire:loading.attr="disabled"
                        wire:confirm="Tüm ayarlar fabrika değerlerine sıfırlanacak. Emin misiniz?"
                        class="w-full sm:w-auto px-4 py-3 sm:py-2.5 bg-gray-100 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors flex items-center justify-center gap-2 border border-gray-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    Fabrika Ayarlarına Sıfırla
                </button>

                <button wire:click="resetAllData" wire:loading.attr="disabled"
                        wire:confirm="DİKKAT! Tüm pazaryeri muhasebe verileri (Siparişler, Ödemeler, Faturalar, Dönemler, Denetim Logları) kalıcı olarak silinecektir. Devam etmek istiyor musunuz?"
                        class="w-full sm:w-auto px-4 py-3 sm:py-2.5 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors flex items-center justify-center gap-2 shadow-sm focus:ring-2 focus:ring-red-500 focus:outline-none focus:ring-offset-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    Tüm Verileri Sıfırla
                </button>
            </div>

            <button wire:click="saveSettings" wire:loading.attr="disabled"
                    class="w-full sm:w-auto px-8 py-3 sm:py-2.5 bg-blue-600 text-white rounded-lg text-sm font-bold hover:bg-blue-700 transition-colors shadow-sm flex items-center justify-center gap-2">
                <span wire:loading.remove wire:target="saveSettings">
                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                    Tüm Ayarları Kaydet
                </span>
                <span wire:loading wire:target="saveSettings">Kaydediliyor...</span>
            </button>
        </div>

        <p class="text-xs text-gray-500">
            Fabrika ayarlarını sıfırlamak yalnızca parametreleri geri alır. Tüm verileri sıfırla işlemi ise dönem, sipariş ve finans kayıtlarını kalıcı olarak siler.
        </p>
    </div>

    {{-- ═══════════════ ERP ENTEGRASYON (Mevcut) ═══════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mt-4">
        <button wire:click="toggleSettingsSection('erp')" type="button"
                class="w-full px-6 py-4 flex items-center justify-between bg-gradient-to-r from-gray-50 to-white hover:from-gray-100 transition-all">
            <div class="flex items-center gap-3">
                <span class="text-2xl">🔗</span>
                <div class="text-left">
                    <h3 class="text-base font-bold text-gray-900">Kurumsal ERP / Webhook Entegrasyonu</h3>
                    <p class="text-xs text-gray-500">Logo, Mikro, Zirve vb. kurumsal yazılımlarınıza veri gönderim ayarları</p>
                </div>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform {{ $settingsActiveSection === 'erp' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </button>
        @if($settingsActiveSection === 'erp')
        <div class="p-6 border-t border-gray-100 space-y-6">
            @if (session()->has('success_erp'))
                <div class="bg-emerald-50 text-emerald-700 p-4 rounded-lg flex items-center gap-2 border border-emerald-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    {{ session('success_erp') }}
                </div>
            @endif
            <form wire:submit="saveErpSettings" class="space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="space-y-1.5">
                        <label class="block text-sm font-semibold text-gray-700">Ağ Sağlayıcısı (Opsiyonel)</label>
                        <input type="text" wire:model="erpProvider" placeholder="Örn: Logo Tiger, Make.com" class="px-4 py-2.5 w-full border border-gray-300 rounded-lg text-sm bg-white">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-sm font-semibold text-gray-700">Webhook / Hedef URL <span class="text-red-500">*</span></label>
                        <input type="url" wire:model="erpWebhookUrl" required placeholder="https://..." class="px-4 py-2.5 w-full border border-gray-300 rounded-lg text-sm bg-white">
                    </div>
                    <div class="space-y-1.5 col-span-2">
                        <label class="block text-sm font-semibold text-gray-700">API Key / Bearer Token (Opsiyonel)</label>
                        <input type="text" wire:model="erpApiKey" placeholder="Bağlantı şifresi veya Token..." class="px-4 py-2.5 w-full border border-gray-300 rounded-lg text-sm bg-white">
                    </div>
                    <div class="space-y-4 col-span-2">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" wire:model="erpAutoPush" class="w-5 h-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <div>
                                <p class="font-semibold text-gray-800 text-sm">🚀 Mutabakat Sağlandığında Anında Gönder</p>
                                <p class="text-xs text-gray-500">Siparişler kilitlendiğinde otomatik JSON fırlatılır.</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" wire:model="erpIsActive" class="w-5 h-5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            <div>
                                <p class="font-semibold text-gray-800 text-sm">✅ Entegrasyonu Aktifleştir</p>
                                <p class="text-xs text-gray-500">Kapalıysa gönderim yapılamaz.</p>
                            </div>
                        </label>
                    </div>
                </div>
                <div class="pt-4 border-t border-gray-200 flex justify-end">
                    <button type="submit" wire:loading.attr="disabled" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-bold hover:bg-indigo-700 transition-colors shadow-sm flex items-center gap-2">
                        <span wire:loading.remove wire:target="saveErpSettings">💾 ERP Bağlantısını Kaydet</span>
                        <span wire:loading wire:target="saveErpSettings">Kaydediliyor...</span>
                    </button>
                </div>
            </form>
        </div>
        @endif
    </div>

    {{-- ═══════════════ BÖLÜM 7: VERİ BAKIMI ═══════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mt-4">
        <button wire:click="toggleSettingsSection('maintenance')" type="button"
                class="w-full px-6 py-4 flex items-center justify-between bg-gradient-to-r from-gray-50 to-white hover:from-gray-100 transition-all">
            <div class="flex items-center gap-3">
                <span class="text-2xl">🧹</span>
                <div class="text-left">
                    <h3 class="text-base font-bold text-gray-900">Veri Bakımı</h3>
                    <p class="text-xs text-gray-500">Mükerrer kayıt temizleme ve veri sağlığı kontrolleri</p>
                </div>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform {{ $settingsActiveSection === 'maintenance' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </button>
        @if($settingsActiveSection === 'maintenance')
        <div class="p-6 border-t border-gray-100 space-y-5">
            {{-- Mükerrer Sipariş Temizleme --}}
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-5">
                <div class="flex items-start gap-4">
                    <div class="text-3xl flex-shrink-0">🔍</div>
                    <div class="flex-1">
                        <h4 class="text-sm font-bold text-gray-900">Mükerrer Sipariş Temizleme</h4>
                        <p class="text-xs text-gray-600 mt-1 leading-relaxed">
                            Aynı sipariş numarası + barkod + dönem kombinasyonuna sahip çift kayıtları tespit edip siler.
                            Her mükerrer gruptan en eski kaydı saklar, fazlasını kaldırır.
                        </p>
                        <div class="mt-3">
                            <button wire:click="cleanDuplicateOrders"
                                    wire:loading.attr="disabled"
                                    wire:confirm="Mükerrer sipariş kayıtları taranıp temizlenecek. Devam etmek istiyor musunuz?"
                                    class="px-5 py-2.5 bg-amber-600 text-white rounded-lg text-sm font-semibold hover:bg-amber-700 transition-colors shadow-sm flex items-center gap-2">
                                <span wire:loading.remove wire:target="cleanDuplicateOrders">
                                    🧹 Mükerrer Siparişleri Temizle
                                </span>
                                <span wire:loading wire:target="cleanDuplicateOrders" class="flex items-center gap-2">
                                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    Taranıyor...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Bilgi Notu --}}
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-xs text-blue-700 flex items-start gap-2">
                <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>Veritabanında UNIQUE INDEX koruması mevcuttur. Aynı sipariş+barkod+dönem kombinasyonu artık otomatik olarak çiftlenemez. Bu buton sadece eski verilerdeki olası mükerrer kayıtları temizlemek içindir.</span>
            </div>
        </div>
        @endif
    </div>

</div>
