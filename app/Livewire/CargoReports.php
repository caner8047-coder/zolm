<?php

namespace App\Livewire;

use Illuminate\Support\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Kargo operasyon merkezi ana bileşeni.
 *
 * Eski Excel karşılaştırma akışını korur; çoklu taşıyıcı altyapısını,
 * gönderi defterini ve tazmin takibini aynı operasyon yüzeyinde toplar.
 */
class CargoReports extends Component
{
    public string $activeTab = 'shipments';

    public ?string $sourceReportDate = null;

    public int $checkRunKey = 0;

    protected $queryString = [
        'activeTab' => ['except' => 'shipments'],
    ];

    public function mount(): void
    {
        if ($this->activeTab === 'surat') {
            $this->activeTab = 'carriers';
        }

        if ($this->sourceReportDate) {
            $this->activeTab = 'check';
        }

        if (! array_key_exists($this->activeTab, $this->tabs)) {
            $this->activeTab = 'dashboard';
        }
    }

    public function getTabsProperty(): array
    {
        return [
            'shipments' => [
                'label' => 'Gönderi Defteri',
                'summary' => 'Canlı kargo operasyonu',
                'description' => 'Pazaryeri, iade/değişim ve tedarik gönderilerini taşıyıcı bazlı tek defterde yönetin.',
            ],
            'delivery-lookup' => [
                'label' => 'Teslimat Kontrol',
                'summary' => 'Kargo konu arama',
                'description' => 'Satıcı anlaşmalı kargo koduyla müşteri, adres, telefon ve Sürat dağıtım sinyalini tek ekranda görün.',
            ],
            'carriers' => [
                'label' => 'Taşıyıcılar',
                'summary' => 'API hazırlık ve hesaplar',
                'description' => 'Kargo firmalarının canlı sürücü, sözleşme ve geliştirici erişimi durumunu tek yüzeyden izleyin.',
            ],
            'surat-reports' => [
                'label' => 'Sürat Raporları',
                'summary' => 'Günlük tutar defteri',
                'description' => 'Sürat servisinden tarih aralığı raporu çekin; müşteri, parça, desi ve tutar bilgilerini gün gün arşivleyin.',
            ],
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
        return $this->tabs[$this->activeTab] ?? $this->tabs['shipments'];
    }

    public function setTab(string $tab): void
    {
        if ($tab === 'surat') {
            $tab = 'carriers';
        }

        if (! array_key_exists($tab, $this->tabs)) {
            return;
        }

        if ($tab === 'check') {
            $this->sourceReportDate = null;
            $this->checkRunKey++;
        } else {
            $this->sourceReportDate = null;
        }

        $this->activeTab = $tab;
    }

    #[On('cargo-check-from-surat-report')]
    public function openCheckFromSuratReport(string $reportDate): void
    {
        $this->sourceReportDate = Carbon::parse($reportDate)->toDateString();
        $this->activeTab = 'check';
        $this->checkRunKey++;
    }

    #[On('cargo-open-tab')]
    public function openCargoTab(string $tab): void
    {
        $this->setTab($tab);
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
            ->layout('layouts.app', ['title' => 'Kargo Operasyon Merkezi']);
    }
}
