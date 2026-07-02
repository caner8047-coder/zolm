<?php

namespace App\Livewire;

use App\Services\Marketplace\MarketplaceOnboardingGuideService;
use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class OnboardingWizard extends Component
{
    public $onboardingData = [];
    public $currentStepId = null;

    public function mount()
    {
        $this->loadData();
        
        // Find the first action or waiting step to focus on
        if (empty($this->currentStepId)) {
            $firstIncomplete = collect($this->onboardingData['steps'] ?? [])
                ->firstWhere(fn ($step) => in_array($step['status'], ['action', 'waiting']));
                
            if ($firstIncomplete) {
                $this->currentStepId = $firstIncomplete['key'];
            } else {
                // If everything is completed, just focus on the first step
                $this->currentStepId = $this->onboardingData['steps'][0]['key'] ?? null;
            }
        }
    }

    public function loadData()
    {
        $service = app(MarketplaceOnboardingGuideService::class);
        $this->onboardingData = $service->summaryForUser(auth()->id() ?? 1);
    }

    public function selectStep($stepId)
    {
        $this->currentStepId = $stepId;
    }
    
    public function refreshState()
    {
        $this->loadData();
    }

    public function render()
    {
        $currentStepDetails = collect($this->onboardingData['steps'] ?? [])
            ->firstWhere('key', $this->currentStepId);

        return view('livewire.onboarding-wizard', [
            'currentStepDetails' => $currentStepDetails,
        ]);
    }
}
