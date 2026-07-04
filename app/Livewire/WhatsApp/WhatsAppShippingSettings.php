<?php

namespace App\Livewire\WhatsApp;

use App\Models\WaAccount;
use App\Models\WaSetting;
use App\Models\WaTemplate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class WhatsAppShippingSettings extends Component
{
    public bool $shippingEnabled = false;
    public array $allowedStages = [];
    public array $templateIds = [];
    public bool $trackingUpdateEnabled = false;

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $this->loadSettings();
    }

    public function loadSettings(): void
    {
        $this->shippingEnabled = (bool) WaSetting::get('shipping.enabled', config('whatsapp.shipping.enabled', true));
        $this->allowedStages = WaSetting::get('shipping.stages', config('whatsapp.shipping.stages', []));
        $this->templateIds = WaSetting::get('shipping.template_ids', config('whatsapp.shipping.template_ids', []));
        $this->trackingUpdateEnabled = (bool) WaSetting::get('shipping.tracking_update_enabled', false);
    }

    public function getAvailableTemplatesProperty()
    {
        $account = WaAccount::active()->first();
        if (!$account) {
            return collect();
        }

        return WaTemplate::forAccount($account)
            ->approved()
            ->where('category', 'utility')
            ->orderBy('name')
            ->get();
    }

    public function saveSettings(): void
    {
        $this->validate([
            'allowedStages' => 'array',
            'allowedStages.*' => 'in:shipped,out_for_delivery,delivered',
            'templateIds' => 'array',
            'trackingUpdateEnabled' => 'boolean',
        ]);

        // Template validasyonu: seçili template'ler approved ve doğru hesaba ait olmalı
        $account = WaAccount::active()->first();
        if ($account) {
            foreach ($this->templateIds as $stage => $templateId) {
                if ($templateId) {
                    $valid = WaTemplate::where('id', $templateId)
                        ->where('wa_account_id', $account->id)
                        ->approved()
                        ->exists();

                    if (!$valid) {
                        $this->addError("templateIds.{$stage}", 'Geçersiz veya onaylanmamış şablon.');
                        return;
                    }
                }
            }
        }

        WaSetting::set('shipping.enabled', $this->shippingEnabled);
        WaSetting::set('shipping.stages', $this->allowedStages);
        WaSetting::set('shipping.template_ids', $this->templateIds);
        WaSetting::set('shipping.tracking_update_enabled', $this->trackingUpdateEnabled);

        session()->flash('wa_success', 'Kargo bildirim ayarları kaydedildi.');
    }

    public function render()
    {
        return view('livewire.whatsapp.whatsapp-shipping-settings');
    }
}
