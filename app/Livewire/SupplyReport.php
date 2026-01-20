<?php

namespace App\Livewire;

use Livewire\Component;

class SupplyReport extends Component
{
    public function render()
    {
        return view('livewire.supply-report')
            ->layout('layouts.app');
    }
}
