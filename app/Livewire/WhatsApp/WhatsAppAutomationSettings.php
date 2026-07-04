<?php

namespace App\Livewire\WhatsApp;

use App\Models\WaAutomationConfig;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class WhatsAppAutomationSettings extends Component
{
    public array $cartRecovery = [];
    public array $stockAlert = [];
    public array $orderConfirmation = [];
    public array $returnNotifications = [];
    public array $birthday = [];
    public array $welcomeOnboarding = [];
    public array $firstPurchase = [];
    public array $frequencyCap = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $this->loadSettings();
    }

    public function loadSettings(): void
    {
        $this->cartRecovery = WaAutomationConfig::get('cart_recovery', ['enabled' => false]);
        $this->stockAlert = WaAutomationConfig::get('stock_alert', ['enabled' => false]);
        $this->orderConfirmation = WaAutomationConfig::get('order_confirmation', ['enabled' => false]);
        $this->returnNotifications = WaAutomationConfig::get('returns', ['enabled' => false]);
        $this->birthday = WaAutomationConfig::get('birthday', ['enabled' => false]);
        $this->welcomeOnboarding = WaAutomationConfig::get('onboarding.welcome', ['enabled' => false]);
        $this->firstPurchase = WaAutomationConfig::get('onboarding.first_purchase', ['enabled' => false]);
        $this->frequencyCap = WaAutomationConfig::get('frequency_cap', [
            'marketing_max_per_24h' => 2,
            'marketing_max_per_7d' => 5,
            'marketing_max_per_30d' => 15,
        ]);
    }

    public function saveSettings(): void
    {
        WaAutomationConfig::set('cart_recovery', $this->cartRecovery);
        WaAutomationConfig::set('stock_alert', $this->stockAlert);
        WaAutomationConfig::set('order_confirmation', $this->orderConfirmation);
        WaAutomationConfig::set('returns', $this->returnNotifications);
        WaAutomationConfig::set('birthday', $this->birthday);
        WaAutomationConfig::set('onboarding.welcome', $this->welcomeOnboarding);
        WaAutomationConfig::set('onboarding.first_purchase', $this->firstPurchase);
        WaAutomationConfig::set('frequency_cap', $this->frequencyCap);

        session()->flash('wa_success', 'Otomasyon ayarları kaydedildi.');
    }

    public function render()
    {
        return view('livewire.whatsapp.whatsapp-automation-settings');
    }
}
