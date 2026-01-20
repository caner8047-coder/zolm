<?php

namespace App\Livewire;

use Livewire\Component;

class MarketplaceAccounting extends Component
{
    public function render()
    {
        return view('livewire.marketplace-accounting')
            ->layout('layouts.app');
    }
}
