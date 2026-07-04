<?php

namespace App\Livewire\WhatsApp;

use App\Models\WaCampaign;
use App\Models\WaCampaignEvent;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class WhatsAppCampaigns extends Component
{
    use WithPagination;

    public string $statusFilter = 'all';
    public ?int $selectedCampaignId = null;
    public array $campaignStats = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $this->loadStats();
    }

    public function loadData(): void
    {
        $query = WaCampaign::with(['segment', 'account', 'template'])
            ->orderByDesc('created_at');

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $this->campaignStats = $query->limit(50)->get()->toArray();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
        $this->loadData();
    }

    public function selectCampaign(int $campaignId): void
    {
        $this->selectedCampaignId = $campaignId;
    }

    public function loadStats(): void
    {
        $this->loadData();
    }

    public function pauseCampaign(int $campaignId): void
    {
        $campaign = WaCampaign::findOrFail($campaignId);
        $service = app(\App\Services\WhatsApp\CampaignService::class);

        try {
            $service->pause($campaign, auth()->id());
            session()->flash('wa_success', 'Kampanya duraklatıldı.');
        } catch (\Throwable $e) {
            session()->flash('wa_error', $e->getMessage());
        }

        $this->loadData();
    }

    public function resumeCampaign(int $campaignId): void
    {
        $campaign = WaCampaign::findOrFail($campaignId);
        $service = app(\App\Services\WhatsApp\CampaignService::class);

        try {
            $service->resume($campaign, auth()->id());
            session()->flash('wa_success', 'Kampanya devam ettirildi.');
        } catch (\Throwable $e) {
            session()->flash('wa_error', $e->getMessage());
        }

        $this->loadData();
    }

    public function cancelCampaign(int $campaignId): void
    {
        $campaign = WaCampaign::findOrFail($campaignId);
        $service = app(\App\Services\WhatsApp\CampaignService::class);

        try {
            $service->cancel($campaign, 'Manuel iptal', auth()->id());
            session()->flash('wa_success', 'Kampanya iptal edildi.');
        } catch (\Throwable $e) {
            session()->flash('wa_error', $e->getMessage());
        }

        $this->loadData();
    }

    public function render()
    {
        return view('livewire.whatsapp.whatsapp-campaigns');
    }
}
