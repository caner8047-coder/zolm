<?php

namespace App\Livewire;

use Livewire\Component;

/**
 * Kargo Raporları Ana Bileşeni
 * 
 * 4 Tab yapısı:
 * 1. Ürün ve Desi Bilgileri (ProductManager)
 * 2. Kargo Desi ve Tutar Check (CargoChecker)
 * 3. Raporlar (ReportList)
 * 4. Tazmin (CompensationDashboard)
 */
class CargoReports extends Component
{
    public string $activeTab = 'dashboard';

    protected $queryString = [
        'activeTab' => ['except' => 'dashboard'],
    ];

    public function mount(): void
    {
        if (!array_key_exists($this->activeTab, $this->tabs)) {
            $this->activeTab = 'dashboard';
        }
    }

    public function getTabsProperty(): array
    {
        return [
            'dashboard' => [
                'label' => 'Genel Görünüm',
                'summary' => 'Özet metrikler ve dağılım',
                'description' => 'Kargo operasyonunda sipariş hacmi, hata yoğunluğu ve toplam farkları tek panelden izleyin.',
            ],
            'products' => [
                'label' => 'Ürün ve Desi',
                'summary' => 'Referans ürün verileri',
                'description' => 'Ürün, desi ve tutar referanslarını düzenleyerek kargo karşılaştırma akışının veri temelini yönetin.',
            ],
            'check' => [
                'label' => 'Desi ve Tutar Check',
                'summary' => 'Excel karşılaştırma akışı',
                'description' => 'Kargo Excel’ini yükleyin, sipariş ve referans ürün verileriyle farkları otomatik olarak kontrol edin.',
            ],
            'reports' => [
                'label' => 'Rapor Arşivi',
                'summary' => 'Geçmiş karşılaştırmalar',
                'description' => 'Kaydedilmiş kargo raporlarını tarih, firma ve sonuç bazında filtreleyip detaylarını inceleyin.',
            ],
            'compensation' => [
                'label' => 'Tazmin Takibi',
                'summary' => 'Talep ve belge yönetimi',
                'description' => 'Tazmin taleplerini, belge akışını ve onay durumlarını aynı standart panel içinde takip edin.',
            ],
        ];
    }

    public function getActiveTabMetaProperty(): array
    {
        return $this->tabs[$this->activeTab] ?? $this->tabs['dashboard'];
    }

    public function setTab(string $tab): void
    {
        if (!array_key_exists($tab, $this->tabs)) {
            return;
        }

        $this->activeTab = $tab;
    }

    public function openCreateModal(?int $errorItemId = null): void
    {
        $this->activeTab = 'compensation';
        $this->dispatch('cargo-comp-open-create', errorItemId: $errorItemId);
    }

    public function openStatusModal(int $id): void
    {
        $this->activeTab = 'compensation';
        $this->dispatch('cargo-comp-open-status', id: $id);
    }

    public function openPetitionModal(int $id): void
    {
        $this->activeTab = 'compensation';
        $this->dispatch('cargo-comp-open-petition', id: $id);
    }

    public function viewAttachments(int $id): void
    {
        $this->activeTab = 'compensation';
        $this->dispatch('cargo-comp-view-attachments', id: $id);
    }

    public function showAllErrors(): void
    {
        $this->activeTab = 'compensation';
        $this->dispatch('cargo-comp-show-all-errors');
    }

    public function showAllCompensations(): void
    {
        $this->activeTab = 'compensation';
        $this->dispatch('cargo-comp-show-all-compensations');
    }

    public function backToDashboard(): void
    {
        $this->activeTab = 'compensation';
        $this->dispatch('cargo-comp-back-to-dashboard');
    }

    public function render()
    {
        return view('livewire.cargo-reports')
            ->layout('layouts.app', ['title' => 'Kargo Raporları']);
    }
}
