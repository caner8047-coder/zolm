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

    public function setTab(string $tab)
    {
        $this->activeTab = $tab;
    }

    public function render()
    {
        return view('livewire.cargo-reports')
            ->layout('layouts.app');
    }
}
