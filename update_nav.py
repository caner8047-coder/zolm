import re

with open('resources/views/layouts/app.blade.php', 'r', encoding='utf-8') as f:
    content = f.read()

new_nav = """            <!-- Navigation -->
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
                        @if(auth()->user()->canAccessReports())
                        <a href="{{ route('reports') }}" @click="sidebarOpen = false"
                           class="block px-4 py-2 text-sm rounded-lg transition-colors
                                  {{ request()->routeIs('reports') ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            Motor Çıktıları
                        </a>
                        @endif
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

                {{-- Trendyol Kampanyalar Dropdown --}}
                <div x-data="{ campaignsOpen: {{ request()->routeIs('campaigns.*') ? 'true' : 'false' }} }">
                    <button @click="campaignsOpen = !campaignsOpen"
                        class="w-full flex items-center justify-between px-4 py-3 text-sm font-medium rounded-lg transition-colors
                               {{ request()->routeIs('campaigns.*') ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            Trendyol Kampanyalar
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
            </nav>"""

pattern = r'            <!-- Navigation -->\n            <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">.*?</nav>'
new_content = re.sub(pattern, new_nav, content, flags=re.DOTALL)

with open('resources/views/layouts/app.blade.php', 'w', encoding='utf-8') as f:
    f.write(new_content)
    
print("Successfully updated app layout.")
