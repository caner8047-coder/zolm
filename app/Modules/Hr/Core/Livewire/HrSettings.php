<?php

namespace App\Modules\Hr\Core\Livewire;

use App\Models\HrHoliday;
use App\Modules\Hr\Core\Services\TenantContext;
use Livewire\Component;

class HrSettings extends Component
{
    public $activeTab = 'general';
    public $holidays = [];
    public $newHolidayName = '';
    public $newHolidayDate = '';
    public $newHolidayType = 'national';

    public function render()
    {
        $tenant = app(TenantContext::class)->get();

        return view('livewire.hr.settings', [
            'tenant' => $tenant,
        ])->layout('layouts.app');
    }

    public function loadHolidays(): void
    {
        $this->holidays = HrHoliday::where('legal_entity_id', app(TenantContext::class)->getId())
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    public function addHoliday(): void
    {
        $this->validate([
            'newHolidayName' => 'required|string|max:255',
            'newHolidayDate' => 'required|date',
            'newHolidayType' => 'required|in:national,religious,special',
        ]);

        $date = $this->newHolidayDate;

        HrHoliday::create([
            'legal_entity_id' => app(TenantContext::class)->getId(),
            'name' => $this->newHolidayName,
            'date' => $date,
            'year' => date('Y', strtotime($date)),
            'type' => $this->newHolidayType,
            'is_recurring' => true,
        ]);

        $this->reset(['newHolidayName', 'newHolidayDate', 'newHolidayType']);
        $this->newHolidayType = 'national';
        $this->loadHolidays();

        session()->flash('success', 'Tatil başarıyla eklendi.');
    }

    public function deleteHoliday(int $id): void
    {
        HrHoliday::where('id', $id)
            ->where('legal_entity_id', app(TenantContext::class)->getId())
            ->delete();

        $this->loadHolidays();

        session()->flash('success', 'Tatil başarıyla silindi.');
    }
}
