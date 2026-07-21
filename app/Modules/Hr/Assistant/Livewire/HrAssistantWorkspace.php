<?php

namespace App\Modules\Hr\Assistant\Livewire;

use App\Modules\Hr\Assistant\Models\HrAssistantQuery;
use App\Modules\Hr\Assistant\Services\HrAssistantService;
use App\Modules\Hr\Core\Services\TenantContext;
use Livewire\Component;

class HrAssistantWorkspace extends Component
{
    public string $question = '';

    public ?int $selectedQueryId = null;

    public function ask(HrAssistantService $assistant): void
    {
        $query = $assistant->ask($this->question);
        $this->selectedQueryId = $query->id;
        $this->reset('question');
    }

    public function useExample(string $question): void
    {
        abort_if(mb_strlen($question) > 200, 422);
        $this->question = $question;
    }

    public function select(int $id): void
    {
        $this->selectedQueryId = $this->queryHistory()->findOrFail($id)->id;
    }

    public function render()
    {
        $history = $this->queryHistory()->latest()->limit(30)->get();
        $selected = $this->selectedQueryId ? $this->queryHistory()->findOrFail($this->selectedQueryId) : $history->first();
        if ($selected) {
            $this->selectedQueryId = $selected->id;
        }

        return view('livewire.hr.assistant.hr-assistant-workspace', [
            'history' => $history,
            'selected' => $selected,
            'examples' => [
                'Aktif çalışan sayısını özetle',
                'Kaç izin talebi onay bekliyor?',
                'Açık destek ve İSG riskleri nedir?',
                'Kadro planındaki FTE durumu nedir?',
                'Aylık ücret maliyeti nedir?',
            ],
        ])->layout('layouts.app');
    }

    private function queryHistory()
    {
        return HrAssistantQuery::withoutGlobalScope('tenant')
            ->where('legal_entity_id', app(TenantContext::class)->getId())
            ->where('user_id', auth()->id());
    }
}
