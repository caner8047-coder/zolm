<?php

namespace App\Livewire\CustomerCare;

use Livewire\Component;
use App\Models\MarketplaceStore;
use App\Models\SupportChannel;
use App\Models\SupportConnectorCertificationRun;
use App\Models\SupportConnectorCertificationCheck;
use App\Services\Support\CustomerCareConnectorCertificationService;
use App\Services\Support\CustomerCareOrganizationContext;
use App\Services\Support\TenantContext;
use Illuminate\Support\Facades\Auth;

class Certification extends Component
{
    public ?int $selectedStoreId = null;
    public string $selectedChannelKey = 'web_chat';
    public string $fixturePayloadJson = '';
    public string $simulationResult = '';
    public bool $simulationSuccess = false;

    public function mount(): void
    {
        $accessibleStores = CustomerCareOrganizationContext::getAccessibleStores(Auth::user())->get();
        if ($accessibleStores->isNotEmpty()) {
            $this->selectedStoreId = $accessibleStores->first()->id;
        }
    }

    public function runCertification(string $channelKey): void
    {
        if (!$this->selectedStoreId) return;

        $user = Auth::user();
        TenantContext::enforceStoreAccess($this->selectedStoreId, $user);

        app(CustomerCareConnectorCertificationService::class)->certifyChannel($this->selectedStoreId, $channelKey, $user);
        session()->flash('cert_success', "{$channelKey} sertifikasyon denetimi başarıyla tamamlandı.");
    }

    public function runSandboxSimulation(): void
    {
        if (!$this->selectedStoreId) return;

        $payload = json_decode($this->fixturePayloadJson, true);
        if (!is_array($payload)) {
            $this->simulationSuccess = false;
            $this->simulationResult = 'Geçersiz JSON formatı. Lütfen kontrol edin.';
            return;
        }

        $user = Auth::user();
        TenantContext::enforceStoreAccess($this->selectedStoreId, $user);

        $res = app(CustomerCareConnectorCertificationService::class)->simulateWebhookEvent($this->selectedStoreId, $this->selectedChannelKey, $payload, $user);

        $this->simulationSuccess = $res['success'] ?? false;
        $this->simulationResult = json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function render()
    {
        $user = Auth::user();
        $accessibleStores = CustomerCareOrganizationContext::getAccessibleStores($user)->get();

        if ($this->selectedStoreId && !$accessibleStores->contains('id', $this->selectedStoreId)) {
            $this->selectedStoreId = $accessibleStores->first()?->id;
        }

        $channels = collect();
        $certificationRuns = collect();

        if ($this->selectedStoreId) {
            $channels = SupportChannel::where('store_id', $this->selectedStoreId)->get();
            $certificationRuns = SupportConnectorCertificationRun::where('store_id', $this->selectedStoreId)
                ->with('checks')
                ->latest()
                ->get();
        }

        return view('livewire.customer-care.certification', [
            'accessibleStores'  => $accessibleStores,
            'channels'          => $channels,
            'certificationRuns' => $certificationRuns,
        ])->layout('layouts.app');
    }
}
