<?php

namespace App\Livewire;

use Livewire\Component;

class ApiDev extends Component
{
    public function render()
    {
        return view('livewire.api-dev')
            ->layout('layouts.app');
    }
}
