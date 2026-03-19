<?php

namespace App\Livewire;

class CustomMotorWizard extends ProfileWizard
{
    public function mount(): void
    {
        $this->type = 'custom';
        $this->lockType = true;
        $this->returnRoute = 'custom-motors';

        parent::mount();
    }
}
