<?php

namespace App\Livewire\CustomerCare;

use Livewire\Component;
use App\Models\MarketplaceStore;
use App\Models\LegalEntity;
use App\Models\SupportApiClient;
use App\Models\SupportApiToken;
use App\Models\SupportApiAccessLog;
use App\Services\Support\CustomerCareEnterpriseApiService;
use App\Services\Support\CustomerCareOrganizationContext;
use App\Services\Support\Security\SupportRbacService;
use Illuminate\Support\Facades\DB;

class Api extends Component
{
    public int $selectedOrgId = 0;
    public string $errorMessage = '';
    public string $successMessage = '';

    // Yeni Client & Token alanları
    public string $newClientName = '';
    public string $newPrefix = 'app';
    public array $newScopes = ['conversations:read'];
    public array $newStoreIds = [];
    public ?int $expiresInDays = 30;

    public string $generatedPlainToken = '';

    protected $queryString = ['selectedOrgId'];

    public function mount(): void
    {
        if (!config('customer-care.enterprise_api_enabled', false)) {
            abort(404);
        }

        $user = auth()->user();
        if (!$user || !in_array($user->role, ['admin', 'operator'], true)) {
            abort(403);
        }

        $orgs = CustomerCareOrganizationContext::getAccessibleOrganizations($user)->get();
        if ($orgs->isEmpty()) {
            $this->selectedOrgId = 0;
        } else {
            if ($this->selectedOrgId && $orgs->contains('id', $this->selectedOrgId)) {
                // keep it
            } else {
                $this->selectedOrgId = $orgs->first()->id;
            }
        }
    }

    public function createClientAndToken(): void
    {
        $user = auth()->user();
        if (!$user) { abort(403); }

        try {
            CustomerCareOrganizationContext::enforceOrganizationAccess($this->selectedOrgId, $user);

            if (empty(trim($this->newClientName))) {
                throw new \RuntimeException('Client adı zorunludur.');
            }

            $tokenRes = DB::transaction(function () use ($user): array {
                $client = SupportApiClient::create([
                    'legal_entity_id' => $this->selectedOrgId,
                    'name'            => $this->newClientName,
                    'client_id'       => 'cli_' . \Illuminate\Support\Str::random(16),
                    'is_active'       => true,
                ]);

                return app(CustomerCareEnterpriseApiService::class)->createToken(
                    $client->id,
                    $this->newPrefix,
                    $this->newScopes,
                    $this->newStoreIds,
                    $this->expiresInDays,
                    $user
                );
            });

            $this->generatedPlainToken = $tokenRes['plain_token'];
            $this->newClientName = '';
            $this->newStoreIds = [];
            $this->successMessage = 'API Client ve Erişim Tokenı başarıyla oluşturuldu.';
            $this->errorMessage = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->successMessage = '';
        }
    }

    public function revokeToken(int $tokenId): void
    {
        $user = auth()->user();
        if (!$user) { abort(403); }

        try {
            $token = SupportApiToken::findOrFail($tokenId);
            CustomerCareOrganizationContext::enforceOrganizationAccess($token->apiClient->legal_entity_id, $user);
            foreach ($token->store_ids ?? [] as $storeId) {
                app(SupportRbacService::class)->enforcePermission($user, (int) $storeId, 'manage_webhooks');
            }

            $token->update(['revoked_at' => now()]);
            $this->successMessage = 'API Token yetkisi iptal edildi.';
            $this->errorMessage = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->successMessage = '';
        }
    }

    public function render()
    {
        $user = auth()->user();
        $clients = collect();
        $tokens = collect();
        $logs = collect();
        $stores = collect();

        if ($this->selectedOrgId && $user) {
            try {
                CustomerCareOrganizationContext::enforceOrganizationAccess($this->selectedOrgId, $user);

                $clients = SupportApiClient::where('legal_entity_id', $this->selectedOrgId)->get();
                $clientIds = $clients->pluck('id')->toArray();

                $tokens = SupportApiToken::whereIn('api_client_id', $clientIds)
                    ->with('apiClient')
                    ->get();

                $logs = SupportApiAccessLog::whereIn('api_client_id', $clientIds)
                    ->with(['apiClient', 'store'])
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get();

                $stores = MarketplaceStore::where('legal_entity_id', $this->selectedOrgId)->get();
            } catch (\Throwable) {
                // yetki dışı durum - listeleri boşaltıp selectedOrgId'yi sıfırla
                $this->selectedOrgId = 0;
                $clients = collect();
                $tokens = collect();
                $logs = collect();
                $stores = collect();
            }
        }

        return view('livewire.customer-care.api', [
            'organizations' => $user ? CustomerCareOrganizationContext::getAccessibleOrganizations($user)->get() : collect(),
            'clients'       => $clients,
            'tokens'        => $tokens,
            'logs'          => $logs,
            'stores'        => $stores,
        ])->layout('layouts.app');
    }
}
