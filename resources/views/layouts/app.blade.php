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
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    
    <style>
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
                    zolm <span class="text-xs font-normal text-gray-400 ml-1">v.0.6</span>
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
                <div x-data="{ pazaryeriOpen: {{ request()->routeIs('mp.*', 'marketplace-accounting', 'reports') ? 'true' : 'false' }} }">
                    <button @click="pazaryeriOpen = !pazaryeriOpen"
                        class="w-full flex items-center justify-between px-4 py-3 text-sm font-medium rounded-lg transition-colors
                               {{ request()->routeIs('mp.*', 'marketplace-accounting', 'reports') ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
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
                        <a href="{{ route('mp.orders') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('mp.orders') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Siparişler
                        </a>
                        <a href="{{ route('mp.products') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('mp.products') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Ürünler
                        </a>
                        <a href="{{ route('marketplace-accounting') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('marketplace-accounting') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Muhasebe
                        </a>
                    </div>
                </div>

                {{-- Araçlar Dropdown --}}
                <div x-data="{ araclarOpen: {{ request()->routeIs('production', 'operation', 'custom-motors*', 'profiles*') ? 'true' : 'false' }} }">
                    <button @click="araclarOpen = !araclarOpen"
                        class="w-full flex items-center justify-between px-4 py-3 text-sm font-medium rounded-lg transition-colors
                               {{ request()->routeIs('production', 'operation', 'custom-motors*', 'profiles*') ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
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
                <div x-data="{ fabrikaOpen: {{ request()->routeIs('recipe.*') ? 'true' : 'false' }} }">
                    <button @click="fabrikaOpen = !fabrikaOpen"
                        class="w-full flex items-center justify-between px-4 py-3 text-sm font-medium rounded-lg transition-colors
                               {{ request()->routeIs('recipe.*') ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
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
                        <a href="{{ route('recipe.materials') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('recipe.*') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Reçete
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
                    <button @click="campaignsOpen = !campaignsOpen"
                        class="w-full flex items-center justify-between px-4 py-3 text-sm font-medium rounded-lg transition-colors
                               {{ request()->routeIs('campaigns.*') ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            Kampanyalar
                        </span>
                        <svg :class="campaignsOpen ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="campaignsOpen" x-collapse class="ml-8 mt-1 space-y-1">
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
                    </div>
                </div>

                <a href="{{ route('cargo-reports') }}" 
                   @click="sidebarOpen = false"
                   class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors
                          {{ request()->routeIs('cargo-reports') ? 'bg-gray-900 text-white' : 'text-gray-700 hover:bg-gray-100' }}">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    Kargo Raporu
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
                    @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="px-2 sm:px-3 py-1 text-xs sm:text-sm font-medium text-gray-700 border border-gray-300 rounded hover:bg-gray-50">
                        Admin
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

    @livewireScripts
</body>
</html>
