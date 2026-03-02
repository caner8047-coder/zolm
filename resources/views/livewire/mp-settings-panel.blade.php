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
                <span class="text-2xl">📊</span>
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

    {{-- ═══════════════ BÖLÜM 5: DENETİM TOLERANSLARI ═══════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <button wire:click="toggleSettingsSection('audit')" type="button"
                class="w-full px-6 py-4 flex items-center justify-between bg-gradient-to-r from-gray-50 to-white hover:from-gray-100 transition-all">
            <div class="flex items-center gap-3">
                <span class="text-2xl">🛡️</span>
                <div class="text-left">
                    <h3 class="text-base font-bold text-gray-900">Denetim Toleransları</h3>
                    <p class="text-xs text-gray-500">Audit Engine kurallarının hassasiyet eşikleri</p>
                </div>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform {{ $settingsActiveSection === 'audit' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </button>
        @if($settingsActiveSection === 'audit')
        <div class="p-6 border-t border-gray-100">
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-5 text-xs text-amber-700">
                ⚠️ Tolerans değerlerini düşürmek daha fazla uyarı üretir, yükseltmek daha az uyarı üretir. Dikkatli ayarlayın.
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @php
                    $toleranceFields = [
                        ['model' => 'settingsStopajTolerance', 'label' => 'Stopaj Toleransı', 'unit' => 'TL', 'hint' => 'Stopaj hesaplama farkı eşiği'],
                        ['model' => 'settingsCommissionMismatchTolerance', 'label' => 'Komisyon Uyuşmazlık', 'unit' => 'TL', 'hint' => 'Komisyon oranı hesaplama farkı'],
                        ['model' => 'settingsBaremExcessTolerance', 'label' => 'Barem Aşım', 'unit' => 'TL', 'hint' => 'Barem fiyat aşımı eşiği'],
                        ['model' => 'settingsCommissionRefundTolerance', 'label' => 'Komisyon İadesi', 'unit' => 'TL', 'hint' => 'İade komisyon geri ödeme farkı'],
                        ['model' => 'settingsHakedisTolerance', 'label' => 'Hakediş Farkı', 'unit' => 'TL', 'hint' => 'Hesaplanan vs rapor hakediş farkı'],
                        ['model' => 'settingsHeavyCargoTolerance', 'label' => 'Ağır Kargo', 'unit' => 'TL', 'hint' => 'Ağır kargo ceza eşleştirme toleransı'],
                        ['model' => 'settingsCommissionRefundTrackingTolerance', 'label' => 'Komisyon İade Takip', 'unit' => 'TL', 'hint' => 'Komisyon iadesi takip eşiği'],
                        ['model' => 'settingsMissingPaymentTolerance', 'label' => 'Eksik Ödeme', 'unit' => 'TL', 'hint' => 'Eksik ödeme tespit eşiği'],
                        ['model' => 'settingsDelayedPaymentDays', 'label' => 'Geciken Ödeme', 'unit' => 'gün', 'hint' => 'Kaç gün sonra alarm tetiklensin?'],
                    ];
                @endphp
                @foreach($toleranceFields as $field)
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-gray-600">{{ $field['label'] }}</label>
                    <div class="relative">
                        <input type="number" step="0.01" wire:model="{{ $field['model'] }}" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-1 focus:ring-blue-400">
                        <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] text-gray-400 pointer-events-none">{{ $field['unit'] }}</span>
                    </div>
                    <p class="text-[10px] text-gray-400">{{ $field['hint'] }}</p>
                </div>
                @endforeach
            </div>

            {{-- Kritik Eşikler (severity belirleyiciler) --}}
            <div class="mt-6 pt-4 border-t border-gray-100">
                <h4 class="text-sm font-bold text-gray-700 mb-3">Kritik Alarm Eşikleri <span class="text-xs font-normal text-gray-400">(bu değeri aşan farklar "critical" olarak işaretlenir)</span></h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-gray-600">Yanık Maliyet Eşiği</label>
                        <div class="relative">
                            <input type="number" step="1" wire:model="settingsSunkCostCriticalThreshold" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                            <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] text-gray-400 pointer-events-none">TL</span>
                        </div>
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-gray-600">Hakediş Fark Eşiği</label>
                        <div class="relative">
                            <input type="number" step="1" wire:model="settingsHakedisCriticalThreshold" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                            <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] text-gray-400 pointer-events-none">TL</span>
                        </div>
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-gray-600">Operasyonel Ceza Eşiği</label>
                        <div class="relative">
                            <input type="number" step="1" wire:model="settingsOperationalPenaltyCriticalThreshold" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                            <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] text-gray-400 pointer-events-none">TL</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Çoklu Sepet Ayarları --}}
            <div class="mt-6 pt-4 border-t border-gray-100">
                <h4 class="text-sm font-bold text-gray-700 mb-3">Çoklu Sepet Parametreleri</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-gray-600">Çoklu Sepet Çarpanı</label>
                        <input type="number" step="0.1" wire:model="settingsMultipleCartFactor" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                        <p class="text-[10px] text-gray-400">Kargo fiyatı bu çarpanla barem fiyatını aşarsa çoklu sepet kontrol edilir</p>
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-gray-600">Desi Fiyat Yakınlık Toleransı</label>
                        <div class="relative">
                            <input type="number" step="0.5" wire:model="settingsMultipleCartDesiTolerance" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                            <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] text-gray-400 pointer-events-none">TL</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- ═══════════════ BÖLÜM 6: MUTABAKAT & FATURA ═══════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <button wire:click="toggleSettingsSection('reconciliation')" type="button"
                class="w-full px-6 py-4 flex items-center justify-between bg-gradient-to-r from-gray-50 to-white hover:from-gray-100 transition-all">
            <div class="flex items-center gap-3">
                <span class="text-2xl">📋</span>
                <div class="text-left">
                    <h3 class="text-base font-bold text-gray-900">Mutabakat & Fatura Ayarları</h3>
                    <p class="text-xs text-gray-500">Fatura doğrulama toleransları ve KDV hesaplama parametreleri</p>
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

    {{-- ═══════════════ ANA KAYDET / SIFIRLA BUTONLARI ═══════════════ --}}
    <div class="flex items-center justify-between pt-2">
        <button wire:click="resetToDefaults" wire:loading.attr="disabled"
                wire:confirm="Tüm ayarlar fabrika değerlerine sıfırlanacak. Emin misiniz?"
                class="px-5 py-2.5 bg-gray-100 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors flex items-center gap-2 border border-gray-200">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            Fabrika Ayarlarına Sıfırla
        </button>
        <button wire:click="saveSettings" wire:loading.attr="disabled"
                class="px-8 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-bold hover:bg-blue-700 transition-colors shadow-sm flex items-center gap-2">
            <span wire:loading.remove wire:target="saveSettings">
                <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                Tüm Ayarları Kaydet
            </span>
            <span wire:loading wire:target="saveSettings">Kaydediliyor...</span>
        </button>
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
