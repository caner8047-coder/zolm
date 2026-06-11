<?php

namespace App\Livewire\Cargo;

use App\Services\Cargo\CargoDeliveryLookupService;
use Livewire\Component;

class DeliveryLookup extends Component
{
    public string $reference = '';
    public ?array $result = null;
    public string $selectedTemplate = 'corner_direction';
    public ?string $message = null;
    public string $messageTone = 'info';

    public function lookup(): void
    {
        $this->validate([
            'reference' => ['required', 'string', 'min:4', 'max:120'],
        ], [
            'reference.required' => 'Kargo kodu girin.',
            'reference.min' => 'Kargo kodu en az 4 karakter olmalı.',
        ]);

        try {
            $this->result = app(CargoDeliveryLookupService::class)->lookup($this->reference);
            $state = data_get($this->result, 'distribution.state');
            $this->selectedTemplate = match ($state) {
                'yes' => 'delivery_yes',
                'no', 'warning' => 'delivery_issue',
                default => 'corner_direction',
            };
            $this->message = data_get($this->result, 'local.found')
                ? 'Müşteri ve kargo bilgisi getirildi.'
                : 'Yerel sipariş bulunamadı; varsa Sürat takip sonucu gösterildi.';
            $this->messageTone = data_get($this->result, 'distribution.state') === 'no' ? 'warning' : 'success';
        } catch (\Throwable $exception) {
            $this->result = null;
            $this->message = $exception->getMessage();
            $this->messageTone = 'danger';
        }
    }

    public function updatedReference(): void
    {
        $this->resetValidation('reference');
        $this->message = null;
    }

    public function selectTemplate(string $key): void
    {
        if (!data_get($this->result, 'templates.' . $key)) {
            return;
        }

        $this->selectedTemplate = $key;
    }

    public function getSelectedTemplateTextProperty(): string
    {
        return (string) data_get($this->result, 'templates.' . $this->selectedTemplate . '.body', '');
    }

    public function render()
    {
        return view('livewire.cargo.delivery-lookup');
    }
}
