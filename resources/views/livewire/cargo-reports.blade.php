<div class="space-y-6 w-full max-w-full overflow-x-hidden">


    {{-- Tab Navigation --}}
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden max-w-full">
        <div class="border-b border-gray-200">
            <nav class="flex -mb-px overflow-x-auto pb-1" aria-label="Tabs">
                {{-- Tab 0: Dashboard --}}
                <button
                    wire:click="setTab('dashboard')"
                    class="whitespace-nowrap py-3 px-4 border-b-2 font-medium text-sm transition-colors flex-shrink-0
                        {{ $activeTab === 'dashboard' 
                            ? 'border-blue-500 text-blue-600' 
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                >
                    <span class="flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        Dashboard
                    </span>
                </button>

                {{-- Tab 1: Ürün ve Desi Bilgileri --}}
                <button
                    wire:click="setTab('products')"
                    class="whitespace-nowrap py-3 px-4 border-b-2 font-medium text-sm transition-colors flex-shrink-0
                        {{ $activeTab === 'products' 
                            ? 'border-blue-500 text-blue-600' 
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                >
                    <span class="flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                        Ürün & Desi
                    </span>
                </button>

                {{-- Tab 2: Kargo Desi ve Tutar Check --}}
                <button
                    wire:click="setTab('check')"
                    class="whitespace-nowrap py-3 px-4 border-b-2 font-medium text-sm transition-colors flex-shrink-0
                        {{ $activeTab === 'check' 
                            ? 'border-blue-500 text-blue-600' 
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                >
                    <span class="flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                        Check
                    </span>
                </button>

                {{-- Tab 3: Raporlar --}}
                <button
                    wire:click="setTab('reports')"
                    class="whitespace-nowrap py-3 px-4 border-b-2 font-medium text-sm transition-colors flex-shrink-0
                        {{ $activeTab === 'reports' 
                            ? 'border-blue-500 text-blue-600' 
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                >
                    <span class="flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Raporlar
                    </span>
                </button>

                {{-- Tab 4: Tazmin --}}
                <button
                    wire:click="setTab('compensation')"
                    class="whitespace-nowrap py-3 px-4 border-b-2 font-medium text-sm transition-colors flex-shrink-0
                        {{ $activeTab === 'compensation' 
                            ? 'border-blue-500 text-blue-600' 
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                >
                    <span class="flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Tazmin
                    </span>
                </button>
            </nav>
        </div>

        {{-- Tab Content --}}
        <div class="p-3 sm:p-6 overflow-hidden max-w-full">
            @if($activeTab === 'dashboard')
                @livewire('cargo.cargo-dashboard')
            @elseif($activeTab === 'products')
                @livewire('cargo.product-manager')
            @elseif($activeTab === 'check')
                @livewire('cargo.cargo-checker')
            @elseif($activeTab === 'reports')
                @livewire('cargo.report-list')
            @elseif($activeTab === 'compensation')
                @livewire('cargo.compensation-dashboard')
            @endif
        </div>
    </div>
</div>
