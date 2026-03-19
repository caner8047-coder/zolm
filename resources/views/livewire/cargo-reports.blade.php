@php
    $tabs = $this->tabs;
    $tabKeys = array_keys($tabs);
    $tabPositions = array_flip($tabKeys);
@endphp

<div class="space-y-4 lg:space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl lg:text-2xl font-bold text-gray-900">Kargo Raporları</h1>
            <p class="mt-1 text-sm lg:text-base text-gray-700">
                Referans ürünleri yönetin, kargo farklarını kontrol edin, rapor arşivini inceleyin ve tazmin sürecini tek modülde takip edin.
            </p>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3">
            <button
                type="button"
                wire:click="setTab('check')"
                class="min-h-[44px] w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 transition-colors"
            >
                Yeni Karşılaştırma
            </button>
            <button
                type="button"
                wire:click="setTab('reports')"
                class="min-h-[44px] w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors"
            >
                Rapor Arşivi
            </button>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg border border-gray-200 p-2">
        <nav class="grid grid-cols-2 lg:grid-cols-5 gap-2" aria-label="Kargo raporları sekmeleri">
            @foreach($tabs as $tabKey => $tab)
                @php
                    $isActive = $activeTab === $tabKey;
                    $tabStep = ($tabPositions[$tabKey] ?? 0) + 1;
                @endphp

                <button
                    type="button"
                    wire:click="setTab('{{ $tabKey }}')"
                    class="min-h-[44px] inline-flex items-center justify-center gap-2 px-3 py-2 text-sm font-medium rounded-md transition-colors whitespace-nowrap {{ $isActive ? 'bg-gray-900 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50' }}"
                >
                    <span>{{ $tab['label'] }}</span>
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs {{ $isActive ? 'bg-white/15 text-white' : 'bg-gray-100 text-gray-500' }}">
                        {{ $tabStep }}
                    </span>
                </button>
            @endforeach
        </nav>
    </div>

    @if($activeTab === 'dashboard')
        @livewire('cargo.cargo-dashboard', key('cargo-dashboard'))
    @elseif($activeTab === 'products')
        @livewire('cargo.product-manager', key('cargo-products'))
    @elseif($activeTab === 'check')
        @livewire('cargo.cargo-checker', key('cargo-check'))
    @elseif($activeTab === 'reports')
        @livewire('cargo.report-list', key('cargo-reports-list'))
    @elseif($activeTab === 'compensation')
        @livewire('cargo.compensation-dashboard', key('cargo-compensation'))
    @endif
</div>
