<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'ZOLM' }} - XLS Dönüşüm Platformu</title>

    <!-- Tailwind CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                }
            }
        }
    </script>

    <!-- Alpine.js Livewire 3 ile birlikte geliyor, harici eklemeye gerek yok -->

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @livewireStyles

    <style>
        [x-cloak] {
            display: none !important;
        }

        /* Mobile sidebar transition */
        .sidebar-overlay {
            transition: opacity 0.3s ease-in-out;
        }
        .sidebar-panel {
            transition: transform 0.3s ease-in-out;
        }
        /* Touch-friendly buttons */
        @media (max-width: 768px) {
            button, .btn, a.btn {
                min-height: 44px;
            }
            input, select, textarea {
                min-height: 44px;
                font-size: 16px; /* Prevents iOS zoom */
            }
        }
    </style>
</head>
<body class="h-full bg-gray-50 overflow-x-hidden" x-data="{ sidebarOpen: false }">
    @php
        $marketplaceFeatures = config('marketplace.features', []);
        $showMarketplaceV2 = (bool) ($marketplaceFeatures['v2_enabled'] ?? true);
        $marketplaceNavigationActive = request()->routeIs('mp.*', 'marketplace-messages', 'reports')
            && ! request()->routeIs('mp.trendyol-booster');
        $activeBoosterModule = (string) request()->query('booster', 'analysis');
        $boosterFavoritesOnly = $activeBoosterModule === 'tracking' && request()->boolean('favorites');
        $boosterMenuGroups = \App\Services\Marketplace\TrendyolBoosterModuleConfig::getGroups();
    @endphp
    <div class="min-h-full flex">

        <!-- Mobile Sidebar Overlay -->
        <div
            x-show="sidebarOpen"
            x-transition:enter="transition-opacity ease-linear duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-linear duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-gray-900/50 z-40 lg:hidden"
            @click="sidebarOpen = false"
        ></div>

        <!-- Sidebar -->
        <aside
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            class="fixed inset-y-0 left-0 w-64 bg-white border-r border-gray-200 flex flex-col z-50 transform transition-transform duration-300 ease-in-out lg:translate-x-0"
        >
            <!-- Logo -->
            <div class="h-16 flex items-center justify-between px-6 border-b border-gray-200">
                <a href="{{ route('dashboard') }}" class="text-2xl font-bold text-gray-900 tracking-tight flex items-baseline">
                    zolm <span class="text-xs font-normal text-gray-400 ml-1">v.{{ config('version.version', '0.7.0') }}</span>
                </a>
                <!-- Mobile close button -->
                <button
                    @click="sidebarOpen = false"
                    class="lg:hidden p-2 -mr-2 text-gray-500 hover:text-gray-900"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
                {{-- Pazaryeri Dropdown --}}
                <div x-data="{ pazaryeriOpen: {{ $marketplaceNavigationActive ? 'true' : 'false' }} }" data-testid="marketplace-sidebar-menu">
                    <button @click="pazaryeriOpen = !pazaryeriOpen"
                        class="w-full flex items-center justify-between px-4 py-3 text-sm font-medium rounded-lg transition-colors
                               {{ $marketplaceNavigationActive ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            Pazaryeri
                        </span>
                        <svg :class="pazaryeriOpen ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="pazaryeriOpen" x-collapse class="ml-8 mt-1 space-y-1">
                        @if($showMarketplaceV2 && ($marketplaceFeatures['overview_enabled'] ?? true))
                            <a href="{{ route('mp.overview') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('mp.overview') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                Özet
                            </a>
                        @endif
                        @if($showMarketplaceV2 && ($marketplaceFeatures['profit_center_enabled'] ?? true))
                            <a href="{{ route('mp.profit-center') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('mp.profit-center') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                Sipariş Kârlılığı
                            </a>
                        @endif
                        @if($showMarketplaceV2 && ($marketplaceFeatures['pricing_simulator_enabled'] ?? false))
                            <a href="{{ route('mp.pricing-simulator') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('mp.pricing-simulator') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                Kâr Hesaplayıcı
                            </a>
                        @endif
                        @if($showMarketplaceV2 && ($marketplaceFeatures['settlement_audit_enabled'] ?? false))
                            <a href="{{ route('mp.settlement-audit') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('mp.settlement-audit') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                Eksik Ödeme Takibi
                            </a>
                        @endif
                        @if($showMarketplaceV2 && ($marketplaceFeatures['risk_center_enabled'] ?? false))
                            @php
                                $openRiskCount = \App\Models\MarketplaceRiskSignalState::query()
                                    ->where('user_id', auth()->id() ?? 1)
                                    ->where('status', \App\Models\MarketplaceRiskSignalState::STATUS_OPEN)
                                    ->count();
                            @endphp
                            <a href="{{ route('mp.risk-center') }}" @click="sidebarOpen = false"
                               class="flex items-center justify-between px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('mp.risk-center') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                <span>Günlük Görevler</span>
                                @if($openRiskCount > 0)
                                    <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-[10px] font-bold text-white bg-rose-500 rounded-full">
                                        {{ $openRiskCount > 99 ? '99+' : $openRiskCount }}
                                    </span>
                                @endif
                            </a>
                        @endif
                        @if($showMarketplaceV2 && ($marketplaceFeatures['report_digest_enabled'] ?? false))
                            <a href="{{ route('mp.report-digests') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('mp.report-digests') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                Otomatik Raporlar
                            </a>
                        @endif
                        @if($showMarketplaceV2 && ($marketplaceFeatures['integrations_enabled'] ?? true))
                            <a href="{{ route('mp.integrations') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('mp.integrations') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                Entegrasyonlar
                            </a>
                        @endif
                        @if($showMarketplaceV2 && ($marketplaceFeatures['orders_v2_enabled'] ?? true))
                            <a href="{{ route('mp.orders') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('mp.orders') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                Siparişler
                            </a>
                        @endif
                        @if($showMarketplaceV2 && ($marketplaceFeatures['questions_enabled'] ?? true))
                            <a href="{{ route('marketplace-messages') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('marketplace-messages') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                Sorular
                            </a>
                        @endif
                        @if($showMarketplaceV2 && ($marketplaceFeatures['products_v2_enabled'] ?? true))
                            <a href="{{ route('mp.products') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('mp.products') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                Ürünler
                            </a>
                        @endif
                        @if($showMarketplaceV2 && ($marketplaceFeatures['matching_center_enabled'] ?? true))
                            <a href="{{ route('mp.matching') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('mp.matching') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                Eşleştirme
                            </a>
                        @endif
                        @if($showMarketplaceV2 && ($marketplaceFeatures['finance_v2_enabled'] ?? true))
                            <a href="{{ route('mp.finance') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('mp.finance') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                Finans
                            </a>
                        @endif
                        <a href="{{ route('mp.settings') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('mp.settings') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Ayarlar
                        </a>
                    </div>
                </div>

                @if($showMarketplaceV2 && ($marketplaceFeatures['trendyol_booster_enabled'] ?? true))
                    @include('layouts.partials.trendyol-booster-menu')
                @endif

                @if(($marketplaceFeatures['crm_enabled'] ?? true) && auth()->user()->canAccessCrm())
                    <div x-data="{ crmOpen: {{ request()->routeIs('crm.*') ? 'true' : 'false' }} }">
                        <button @click="crmOpen = !crmOpen"
                            class="w-full flex items-center justify-between px-4 py-3 text-sm font-medium rounded-lg transition-colors
                                   {{ request()->routeIs('crm.*') ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                            <span class="flex items-center">
                                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m8-4a4 4 0 10-8 0 4 4 0 008 0zm-8 0a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                                CRM
                            </span>
                            <svg :class="crmOpen ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="crmOpen" x-collapse class="ml-8 mt-1 space-y-1">
                            <a href="{{ route('crm.workspace') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('crm.workspace') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                Müşteri 360
                            </a>
                            <a href="{{ route('crm.customer-ledger') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('crm.customer-ledger') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                Müşteri Cari
                            </a>
                        </div>
                    </div>
                @endif

                @if(config('marketplace.features.accounting_enabled', false) && auth()->user()?->roleSlug() === 'admin')
                    <div x-data="{ accountingOpen: {{ request()->routeIs('accounting.*') ? 'true' : 'false' }} }">
                        <button @click="accountingOpen = !accountingOpen"
                            class="w-full flex items-center justify-between px-4 py-3 text-sm font-medium rounded-lg transition-colors
                                   {{ request()->routeIs('accounting.*') ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                            <span class="flex items-center">
                                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M9 7h6m-6 4h6m-7 4h.01M12 15h.01M16 15h.01M7 3h10a2 2 0 012 2v14a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z" />
                                </svg>
                                Muhasebe (ERP)
                            </span>
                            <svg :class="accountingOpen ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="accountingOpen" x-collapse class="ml-8 mt-1 space-y-1">
                            <a href="{{ route('accounting.dashboard') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('accounting.dashboard') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                Muhasebe Paneli
                            </a>
                            <a href="{{ route('accounting.pilot-center') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('accounting.pilot-center') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                Pilot Merkezi
                            </a>
                            <a href="{{ route('accounting.parties') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('accounting.parties') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                Cariler
                            </a>
                            <a href="{{ route('accounting.party-ledger') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('accounting.party-ledger') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                Cari Açık Hesap
                            </a>
                            <a href="{{ route('accounting.chart-of-accounts') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('accounting.chart-of-accounts') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                Hesap Planı
                            </a>
                            <a href="{{ route('accounting.journal') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('accounting.journal') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                Yevmiye Defteri
                            </a>
                            <a href="{{ route('accounting.cash-bank') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('accounting.cash-bank') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                Kasa & Banka
                            </a>
                            <a href="{{ route('accounting.stock') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('accounting.stock') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                Depo & Stok
                            </a>
                            <a href="{{ route('accounting.products') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('accounting.products') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                Ürün Kartları
                            </a>
                            <a href="{{ route('accounting.sales') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('accounting.sales') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                Satışlar
                            </a>
                            <a href="{{ route('accounting.purchases') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('accounting.purchases') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                Satın Alma
                            </a>
                            <a href="{{ route('accounting.pos') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('accounting.pos') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                Hızlı Satış (POS)
                            </a>
                            <a href="{{ route('accounting.e-documents') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('accounting.e-documents') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                e-Fatura
                            </a>
                            <a href="{{ route('accounting.reports') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('accounting.reports') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                Finansal Raporlar
                            </a>
                            <a href="{{ route('accounting.assistant') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('accounting.assistant') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                AI Asistan
                            </a>
                            <a href="{{ route('accounting.marketplace-bridge') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('accounting.marketplace-bridge') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                Pazaryeri Köprüsü
                            </a>
                            <a href="{{ route('accounting.audit-logs') }}" @click="sidebarOpen = false"
                               class="block px-4 py-2 text-sm rounded-lg transition-colors
                                      {{ request()->routeIs('accounting.audit-logs') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                Denetim Günlüğü
                            </a>
                        </div>
                    </div>
                @else
                    <a href="{{ route('marketplace-accounting') }}"
                       @click="sidebarOpen = false"
                       class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors
                              {{ request()->routeIs('marketplace-accounting') ? 'bg-gray-900 text-white' : 'text-gray-700 hover:bg-gray-100' }}">
                        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M9 7h6m-6 4h6m-7 4h.01M12 15h.01M16 15h.01M7 3h10a2 2 0 012 2v14a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z" />
                        </svg>
                        Muhasebe
                    </a>
                @endif

                {{-- Araçlar Dropdown --}}
                <div x-data="{ araclarOpen: {{ request()->routeIs('production', 'operation', 'custom-motors*', 'profiles*', 'returns.*') ? 'true' : 'false' }} }">
                    <button @click="araclarOpen = !araclarOpen"
                        class="w-full flex items-center justify-between px-4 py-3 text-sm font-medium rounded-lg transition-colors
                               {{ request()->routeIs('production', 'operation', 'custom-motors*', 'profiles*', 'returns.*') ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            </svg>
                            Araçlar
                        </span>
                        <svg :class="araclarOpen ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="araclarOpen" x-collapse class="ml-8 mt-1 space-y-1">
                        @if(auth()->user()->canAccessProduction())
                        <a href="{{ route('production') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('production') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Üretim Motoru
                        </a>
                        @endif
                        @if(auth()->user()->canAccessOperation())
                        <a href="{{ route('operation') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('operation') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Operasyon Motoru
                        </a>
                        @endif
                        @if(auth()->user()->canAccessCustomMotor())
                        <a href="{{ route('custom-motors') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('custom-motors*') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Özel Motor
                        </a>
                        @endif
                        @if(auth()->user()->canAccessReports())
                        <a href="{{ route('reports') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('reports') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Motor Çıktıları
                        </a>
                        @endif
                        @if(auth()->user()->canAccessReturnsIntake() || auth()->user()->canAccessReturnsReview())
                        <a href="{{ route('returns.workspace') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('returns.*') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            İade Merkezi
                        </a>
                        @endif
                        @if(auth()->user()->isAdmin())
                        <a href="{{ route('profiles') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('profiles*') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Profiller
                        </a>
                        @endif
                    </div>
                </div>

                {{-- Fabrika Dropdown --}}
                <div x-data="{ fabrikaOpen: {{ request()->routeIs('recipe.*', 'factory.production-revenue') ? 'true' : 'false' }} }">
                    <button @click="fabrikaOpen = !fabrikaOpen"
                        class="w-full flex items-center justify-between px-4 py-3 text-sm font-medium rounded-lg transition-colors
                               {{ request()->routeIs('recipe.*', 'factory.production-revenue') ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            Fabrika
                        </span>
                        <svg :class="fabrikaOpen ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="fabrikaOpen" x-collapse class="ml-8 mt-1 space-y-1">
                        <a href="#" @click.prevent="" class="flex items-center justify-between px-4 py-2 text-sm rounded-lg transition-colors text-gray-400 cursor-not-allowed">
                            Üretim Online
                            <span class="text-[10px] bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded ml-2">YAKINDA</span>
                        </a>
                        @if(auth()->user()->canAccessProduction())
                        <a href="{{ route('factory.production-revenue') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('factory.production-revenue') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Üretim Ciro
                        </a>
                        @endif
                        <a href="{{ route('recipe.materials') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('recipe.*') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Reçete
                        </a>
                        <a href="{{ route('production.planner') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('production.planner') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Üretim Planlama
                        </a>
                        <a href="#" @click.prevent="" class="flex items-center justify-between px-4 py-2 text-sm rounded-lg transition-colors text-gray-400 cursor-not-allowed">
                            Mamül / Yarı Mamül
                            <span class="text-[10px] bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded ml-2">YAKINDA</span>
                        </a>
                        <a href="#" @click.prevent="" class="flex items-center justify-between px-4 py-2 text-sm rounded-lg transition-colors text-gray-400 cursor-not-allowed">
                            Personel / Makine / Hammadde
                            <span class="text-[10px] bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded ml-2">YAKINDA</span>
                        </a>
                    </div>
                </div>

                {{-- Kampanyalar Dropdown --}}
                <div x-data="{ campaignsOpen: {{ request()->routeIs('campaigns.*') ? 'true' : 'false' }} }">
                    <div class="flex items-center justify-between px-2 py-1 rounded-lg transition-colors {{ request()->routeIs('campaigns.*') ? 'bg-gray-100' : 'hover:bg-gray-100' }}">
                        <a href="{{ route('campaigns.index') }}"
                           @click="sidebarOpen = false"
                           class="flex-1 flex items-center py-2 px-2 text-sm font-medium {{ request()->routeIs('campaigns.*') ? 'text-gray-900' : 'text-gray-700' }}">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            Kampanyalar
                        </a>
                        <button @click="campaignsOpen = !campaignsOpen" class="p-2 text-gray-500 hover:text-gray-700 rounded-md focus:outline-none">
                            <svg :class="campaignsOpen ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                    </div>
                    <div x-show="campaignsOpen" x-collapse class="ml-8 mt-1 space-y-1">
                        @if($marketplaceFeatures['campaign_decision_center_enabled'] ?? false)
                        <a href="{{ route('campaigns.decision-center') }}"
                           @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('campaigns.decision-center') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Karar Merkezi
                        </a>
                        @endif
                        <a href="{{ route('campaigns.product-commission') }}"
                           @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('campaigns.product-commission') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Ürün Komisyon Tarifeleri
                        </a>
                        <a href="{{ route('campaigns.plus-commission') }}"
                           @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('campaigns.plus-commission') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Plus Komisyon Tarifeleri
                        </a>
                        <a href="{{ route('campaigns.badge-pricing') }}"
                           @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('campaigns.badge-pricing') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Avantajlı Ürün Tarifeleri
                        </a>
                        <a href="{{ route('campaigns.flash-products') }}"
                           @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('campaigns.flash-products') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Flaş Ürünler
                        </a>
                        <a href="{{ route('campaigns.basket-discount') }}"
                           @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('campaigns.basket-discount') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Sepet İndirimi
                        </a>
                    </div>
                </div>

                <a href="{{ route('cargo-reports') }}"
                   @click="sidebarOpen = false"
                   class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors
                          {{ request()->routeIs('cargo-reports') ? 'bg-gray-900 text-white' : 'text-gray-700 hover:bg-gray-100' }}">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    Kargo Operasyon
                </a>

                <a href="{{ route('supply-reports') }}"
                   @click="sidebarOpen = false"
                   class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors
                          {{ request()->routeIs('supply-reports') ? 'bg-gray-900 text-white' : 'text-gray-700 hover:bg-gray-100' }}">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                    Tedarik Raporu
                </a>

                {{-- Reklam Zekâsı Dropdown --}}
                @if(auth()->user()->canAccessAds())
                <div x-data="{ adsOpen: {{ request()->routeIs('ads.*') ? 'true' : 'false' }} }">
                    <button @click="adsOpen = !adsOpen"
                        class="w-full flex items-center justify-between px-4 py-3 text-sm font-medium rounded-lg transition-colors
                               {{ request()->routeIs('ads.*') ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                            </svg>
                            Reklam Zekâsı
                        </span>
                        <svg :class="adsOpen ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="adsOpen" x-collapse class="ml-8 mt-1 space-y-1">
                        <a href="{{ route('ads.dashboard') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('ads.dashboard') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Genel Bakış
                        </a>
                        <a href="{{ route('ads.import') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('ads.import') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Veri İçe Aktarma
                        </a>
                        <a href="{{ route('ads.product-ads') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('ads.product-ads*') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Ürün Reklamları
                        </a>
                        <a href="{{ route('ads.store-ads') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('ads.store-ads*') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Mağaza Reklamları
                        </a>
                        <a href="{{ route('ads.influencer-ads') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('ads.influencer-ads*') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Influencer Reklamları
                        </a>
                        <a href="{{ route('ads.profitability') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('ads.profitability*') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Kârlılık Merkezi
                        </a>
                        <a href="{{ route('ads.action-center') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('ads.action-center*') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            AI Aksiyon Merkezi
                        </a>
                        <a href="{{ route('ads.settings') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('ads.settings*') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Ayarlar
                        </a>
                    </div>
                </div>
                @endif

                {{-- WhatsApp Dropdown --}}
                @if(config('whatsapp.features.whatsapp_enabled', false))
                <div x-data="{ whatsappOpen: {{ request()->routeIs('whatsapp.*') ? 'true' : 'false' }} }">
                    <button @click="whatsappOpen = !whatsappOpen"
                        class="w-full flex items-center justify-between px-4 py-3 text-sm font-medium rounded-lg transition-colors
                               {{ request()->routeIs('whatsapp.*') ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                            WhatsApp
                        </span>
                        <svg :class="whatsappOpen ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="whatsappOpen" x-collapse class="ml-8 mt-1 space-y-1">
                        <a href="{{ route('whatsapp.overview') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('whatsapp.overview') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Genel Bakış
                        </a>
                        <a href="{{ route('whatsapp.account') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('whatsapp.account') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Hesap Ayarları
                        </a>
                        <a href="{{ route('whatsapp.templates') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('whatsapp.templates') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Şablonlar
                        </a>
                        <a href="{{ route('whatsapp.shipping') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('whatsapp.shipping') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Kargo Bildirimleri
                        </a>
                        <a href="{{ route('whatsapp.inbox') }}" @click="sidebarOpen = false"
                            class="block px-4 py-2 text-sm rounded-lg transition-colors
                                   {{ request()->routeIs('whatsapp.inbox') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Gelen Kutusu
                        </a>
                        <a href="{{ route('whatsapp.campaigns') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('whatsapp.campaigns*') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Kampanyalar
                        </a>
                        <a href="{{ route('whatsapp.segments') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('whatsapp.segments') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Segmentler
                        </a>
                        <a href="{{ route('whatsapp.customer-profile') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('whatsapp.customer-profile*') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Müşteri Profili
                        </a>
                        <a href="{{ route('whatsapp.audit-logs') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('whatsapp.audit-logs') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Denetim Kayıtları
                        </a>
                        <a href="{{ route('whatsapp.automation') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('whatsapp.automation') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Otomasyon Ayarları
                        </a>
                    </div>
                </div>
                @endif

                {{-- AI Müşteri Merkezi Dropdown --}}
                @if(config('customer-care.enabled', false))
                @php
                    $customerCareActive = request()->routeIs('customer-care.*');
                    $customerCareGroups = [
                        'Operasyon' => [
                            ['label' => 'Genel Bakış', 'route' => 'customer-care.home', 'flag' => 'inbox_enabled'],
                            ['label' => 'Gelen Kutusu', 'route' => 'customer-care.inbox', 'flag' => 'inbox_enabled'],
                            ['label' => 'Temsilci Çalışma Alanı', 'route' => 'customer-care.agent-workspace', 'flag' => 'agent_workspace_enabled'],
                            ['label' => 'Ayarlar', 'route' => 'customer-care.settings', 'flag' => 'settings_enabled'],
                        ],
                        'Bilgi ve Kalite' => [
                            ['label' => 'Ürün Soruları ve Eğitim', 'route' => 'customer-care.product-questions', 'flag' => 'knowledge_enabled'],
                            ['label' => 'Bilgi Bankası Önerileri', 'route' => 'customer-care.suggestions', 'flag' => 'knowledge_enabled'],
                            ['label' => 'Kalite Denetimi', 'route' => 'customer-care.quality', 'flag' => 'quality_center_enabled'],
                            ['label' => 'Deney Laboratuvarı', 'route' => 'customer-care.experiments', 'flag' => 'experiments_enabled'],
                            ['label' => 'Yayın Paketleri', 'route' => 'customer-care.releases', 'flag' => 'release_center_enabled'],
                        ],
                        'Pilot ve Üretim' => [
                            ['label' => 'Onboarding', 'route' => 'customer-care.onboarding', 'flag' => 'onboarding_enabled'],
                            ['label' => 'Pilot Merkezi', 'route' => 'customer-care.pilot', 'flag' => 'pilot_dashboard_enabled'],
                            ['label' => 'Lansman Merkezi', 'route' => 'customer-care.launch', 'flag' => 'launch_center_enabled'],
                            ['label' => 'Canlı Üretim', 'route' => 'customer-care.production', 'flag' => 'production_center_enabled'],
                            ['label' => 'Konnektör Sertifikasyonu', 'route' => 'customer-care.certification', 'flag' => 'connector_certification_enabled'],
                        ],
                        'Yönetim ve Güvenlik' => [
                            ['label' => 'Analitik', 'route' => 'customer-care.analytics', 'flag' => 'analytics_enabled'],
                            ['label' => 'Entegrasyonlar', 'route' => 'customer-care.integrations', 'flag' => 'integration_hub_enabled'],
                            ['label' => 'Organizasyon', 'route' => 'customer-care.organization', 'flag' => 'org_center_enabled'],
                            ['label' => 'Enterprise API', 'route' => 'customer-care.api', 'flag' => 'enterprise_api_enabled'],
                            ['label' => 'Ticari Paketler', 'route' => 'customer-care.commercial', 'flag' => 'commercial_center_enabled'],
                            ['label' => 'Admin Merkezi', 'route' => 'customer-care.admin', 'flag' => 'admin_center_enabled'],
                            ['label' => 'Governance', 'route' => 'customer-care.governance', 'flag' => 'governance_enabled'],
                            ['label' => 'Compliance', 'route' => 'customer-care.compliance', 'flag' => 'compliance_enabled'],
                            ['label' => 'Reliability', 'route' => 'customer-care.reliability', 'flag' => 'reliability_enabled'],
                            ['label' => 'Ops Center', 'route' => 'customer-care.ops', 'flag' => 'ops_center_enabled'],
                            ['label' => 'Security', 'route' => 'customer-care.security', 'flag' => 'security_center_enabled'],
                            ['label' => 'Reconciliation', 'route' => 'customer-care.reconciliation', 'flag' => 'reconciliation_enabled'],
                            ['label' => 'Customer Success', 'route' => 'customer-care.success', 'flag' => 'success_center_enabled'],
                        ],
                    ];
                @endphp
                <div x-data="{ customerCareOpen: {{ $customerCareActive ? 'true' : 'false' }} }" data-testid="customer-care-sidebar-menu">
                    <button @click="customerCareOpen = !customerCareOpen"
                        class="w-full flex items-center justify-between px-4 py-3 text-sm font-medium rounded-lg transition-colors
                               {{ $customerCareActive ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                            </svg>
                            AI Müşteri Merkezi
                        </span>
                        <svg :class="customerCareOpen ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="customerCareOpen" x-collapse class="ml-8 mt-1 space-y-2">
                        @foreach($customerCareGroups as $groupLabel => $items)
                            @php
                                $visibleItems = collect($items)
                                    ->filter(fn ($item) => config('customer-care.' . $item['flag'], false));
                            @endphp

                            @if($visibleItems->isNotEmpty())
                                <div class="space-y-1" data-testid="customer-care-sidebar-group-{{ \Illuminate\Support\Str::slug($groupLabel) }}">
                                    <div class="px-4 pt-2 text-[10px] font-semibold uppercase tracking-[0.16em] text-gray-400">
                                        {{ $groupLabel }}
                                    </div>

                                    @foreach($visibleItems as $item)
                                        <a href="{{ route($item['route']) }}" @click="sidebarOpen = false"
                                           data-testid="customer-care-sidebar-link-{{ \Illuminate\Support\Str::slug($item['label']) }}"
                                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                                  {{ request()->routeIs($item['route']) ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                            {{ $item['label'] }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Divider -->
                <div class="border-t border-gray-200 my-4"></div>

                <!-- Coming Soon Items -->
                <a href="{{ route('api-dev') }}"
                   @click="sidebarOpen = false"
                   class="flex items-center justify-between px-4 py-3 text-sm font-medium rounded-lg transition-colors
                          {{ request()->routeIs('api-dev') ? 'bg-gray-900 text-white' : 'text-gray-400 hover:bg-gray-100 hover:text-gray-700' }}">
                    <span class="flex items-center">
                        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                        </svg>
                        Api/Dev Yakında
                    </span>
                    <span class="text-xs bg-yellow-100 text-yellow-600 px-1.5 py-0.5 rounded">🚧</span>
                </a>
            </nav>

            <!-- AI Chat Link -->
            <div class="px-4 pb-6">
                <a href="{{ route('ai-chat') }}"
                   @click="sidebarOpen = false"
                   class="flex items-center px-4 py-3 text-sm font-medium rounded-lg border-2 border-dashed transition-colors
                          {{ request()->routeIs('ai-chat') ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300 text-gray-700 hover:border-gray-900 hover:bg-gray-50' }}">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                    </svg>
                    ai chat
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 lg:ml-64 min-h-screen w-full max-w-full overflow-x-hidden">
            <!-- Top Bar -->
            <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-4 lg:px-8 sticky top-0 z-30">
                <!-- Mobile menu button -->
                <button
                    @click="sidebarOpen = true"
                    class="lg:hidden p-2 -ml-2 text-gray-600 hover:text-gray-900"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>

                <!-- Desktop spacer -->
                <div class="hidden lg:block"></div>

                <!-- Right side -->
                <div class="flex items-center space-x-2 sm:space-x-4">
                    @if($showMarketplaceV2 && ($marketplaceFeatures['notifications_enabled'] ?? true))
                        <x-zolm.live-notifications />
                    @endif
                    @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="px-2 sm:px-3 py-1 text-xs sm:text-sm font-medium text-gray-700 border border-gray-300 rounded hover:bg-gray-50">
                        Yönetim
                    </a>
                    @endif
                    <span class="hidden sm:inline text-sm text-gray-600">{{ auth()->user()->name }}</span>
                    <span class="px-2 py-1 text-xs font-medium bg-gray-900 text-white rounded">
                        {{ auth()->user()->role_label ?? auth()->user()->role?->name ?? 'User' }}
                    </span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-xs sm:text-sm text-gray-500 hover:text-gray-700">
                            Çıkış
                        </button>
                    </form>
                </div>
            </header>

            <!-- Page Content -->
            <div class="p-4 lg:p-8 w-full max-w-full">
                {{ $slot }}
            </div>
        </main>
    </div>

    <script>
        window.columnResize = window.columnResize || function () {
            return {
                listeners: [],
                init() {
                    const table = this.$el.tagName === 'TABLE'
                        ? this.$el
                        : this.$el.querySelector('table');

                    if (!table) {
                        return;
                    }

                    table.querySelectorAll('thead th').forEach((header) => {
                        if (header.querySelector('[data-column-resizer]')) {
                            return;
                        }

                        header.style.position = 'relative';
                        const handle = document.createElement('button');
                        handle.type = 'button';
                        handle.dataset.columnResizer = 'true';
                        handle.title = 'Kolonu yeniden boyutlandır';
                        handle.setAttribute('aria-label', 'Kolonu yeniden boyutlandır');
                        handle.className = 'absolute inset-y-0 right-0 z-10 w-2 cursor-col-resize touch-none border-0 bg-transparent p-0';

                        const start = (event) => {
                            event.preventDefault();
                            event.stopPropagation();

                            const startX = event.clientX;
                            const startWidth = header.getBoundingClientRect().width;
                            const move = (moveEvent) => {
                                const width = Math.max(72, startWidth + moveEvent.clientX - startX);
                                header.style.width = width + 'px';
                                header.style.minWidth = width + 'px';
                            };
                            const stop = () => {
                                document.removeEventListener('pointermove', move);
                                document.removeEventListener('pointerup', stop);
                            };

                            document.addEventListener('pointermove', move);
                            document.addEventListener('pointerup', stop, { once: true });
                        };

                        handle.addEventListener('pointerdown', start);
                        handle.addEventListener('click', (event) => event.stopPropagation());
                        header.appendChild(handle);
                        this.listeners.push([handle, start]);
                    });
                },
                destroy() {
                    this.listeners.forEach(([handle, start]) => {
                        handle.removeEventListener('pointerdown', start);
                    });
                },
            };
        };
    </script>
    @livewireScripts
</body>
</html>
