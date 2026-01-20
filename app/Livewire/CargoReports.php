<?php

namespace App\Livewire;

use Livewire\Component;

class CargoReports extends Component
{
    public function render()
    {
        return view('livewire.cargo-reports')
            ->layout('layouts.app');
    }
}
