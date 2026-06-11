@php
    $tabs = $this->tabs;
    $tabKeys = array_keys($tabs);
    $tabPositions = array_flip($tabKeys);
@endphp

<div class="space-y-4 lg:space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl lg:text-2xl font-bold text-slate-900">Kargo Operasyon Merkezi</h1>
            <p class="mt-1 text-sm lg:text-base text-slate-700">
                Sürat Kargo gönderilerini, pazaryeri paketlerini, iade/değişim akışlarını, masraf mutabakatını ve eski Excel kontrollerini tek modülde yönetin.
            </p>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3">
            <button
                type="button"
                wire:click="setTab('shipments')"
                class="min-h-[44px] w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-slate-900 hover:bg-slate-800 transition-colors"
            >
                Gönderi Defteri
            </button>
            <button
                type="button"
                wire:click="setTab('check')"
                class="min-h-[44px] w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-medium rounded-md text-slate-700 bg-white hover:bg-slate-50 transition-colors"
            >
                Yeni Karşılaştırma
            </button>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg border border-slate-200 p-2">
        <nav class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-9 gap-2" aria-label="Kargo operasyon sekmeleri">
            @foreach($tabs as $tabKey => $tab)
                @php
                    $isActive = $activeTab === $tabKey;
                    $tabStep = ($tabPositions[$tabKey] ?? 0) + 1;
                @endphp

                <button
                    type="button"
                    wire:click="setTab('{{ $tabKey }}')"
                    class="min-h-[44px] inline-flex items-center justify-center gap-2 px-3 py-2 text-sm font-medium rounded-md transition-colors whitespace-nowrap {{ $isActive ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50' }}"
                >
                    <span>{{ $tab['label'] }}</span>
                    <span class="inline-flex items-center rounded-[6px] px-2 py-0.5 text-xs {{ $isActive ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-500' }}">
                        {{ $tabStep }}
                    </span>
                </button>
            @endforeach
        </nav>
    </div>

    @if($activeTab === 'shipments')
        @livewire('cargo.shipment-ledger', key('cargo-shipment-ledger'))
    @elseif($activeTab === 'delivery-lookup')
        @livewire('cargo.delivery-lookup', key('cargo-delivery-lookup'))
    @elseif($activeTab === 'surat')
        @livewire('cargo.surat-integration-settings', key('cargo-surat-integration'))
    @elseif($activeTab === 'surat-reports')
        @livewire('cargo.surat-report-archive', key('cargo-surat-report-archive'))
    @elseif($activeTab === 'dashboard')
        @livewire('cargo.cargo-dashboard', key('cargo-dashboard'))
    @elseif($activeTab === 'products')
        @livewire('cargo.product-manager', key('cargo-products'))
    @elseif($activeTab === 'check')
        @livewire('cargo.cargo-checker', ['sourceReportDate' => $sourceReportDate], key('cargo-check-' . ($sourceReportDate ?: 'manual') . '-' . $checkRunKey))
    @elseif($activeTab === 'reports')
        @livewire('cargo.report-list', key('cargo-reports-list'))
    @elseif($activeTab === 'compensation')
        @livewire('cargo.compensation-dashboard', key('cargo-compensation'))
    @endif
</div>
