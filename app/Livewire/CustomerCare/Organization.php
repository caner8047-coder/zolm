<?php

namespace App\Livewire\CustomerCare;

use Livewire\Component;
use App\Models\MarketplaceStore;
use App\Models\LegalEntity;
use App\Models\SupportOrganizationSetting;
use App\Models\SupportOrganizationMembership;
use App\Models\SupportServiceAccount;
use App\Services\Support\CustomerCareOrganizationContext;

class Organization extends Component
{
    public int $selectedOrgId = 0;
    public string $errorMessage = '';
    public string $successMessage = '';
    public string $diagnosticsOutput = '';

    // Yeni üye ekleme alanları
    public string $newMemberEmail = '';
    public string $newMemberRole = 'member';

    // Yeni servis hesabı alanları
    public string $newSaName = '';
    public string $newSaEmail = '';

    protected $queryString = ['selectedOrgId'];

    public function mount(): void
    {
        if (!config('customer-care.org_center_enabled', false)) {
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

    public function runDiagnostics(): void
    {
        $user = auth()->user();
        if (!$user) { abort(403); }

        try {
            CustomerCareOrganizationContext::enforceOrganizationAccess($this->selectedOrgId, $user);

            $org = LegalEntity::findOrFail($this->selectedOrgId);
            $membershipsCount = SupportOrganizationMembership::where('legal_entity_id', $org->id)->count();
            $serviceAccountsCount = SupportServiceAccount::where('legal_entity_id', $org->id)->count();

            $output = [];
            $output[] = "=== ZOLM Organizasyon Sınır Teşhisi ===";
            $output[] = "Organizasyon ID: [MASKELENDİ]";
            $output[] = "Organizasyon Adı: {$org->name}";
            $output[] = "Üye Sayısı: {$membershipsCount}";
            $output[] = "Servis Hesabı Sayısı: {$serviceAccountsCount}";

            try {
                $actor = CustomerCareOrganizationContext::getSystemActor($org->id);
                $output[] = "System Actor: Yapılandırılmış [Email MASKELENDİ]";
            } catch (\Throwable $e) {
                $output[] = "System Actor HATA: " . $e->getMessage();
            }

            $stores = MarketplaceStore::where('legal_entity_id', $org->id)->get();
            $output[] = "Bağlı Mağazalar:";
            foreach ($stores as $store) {
                $output[] = "  - {$store->store_name} (ID: {$store->id})";
            }

            $this->diagnosticsOutput = implode("\n", $output);
            $this->successMessage = 'Teşhis raporu üretildi.';
            $this->errorMessage = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->successMessage = '';
        }
    }

    public function addMember(): void
    {
        $user = auth()->user();
        if (!$user) { abort(403); }

        try {
            CustomerCareOrganizationContext::enforceOrganizationAccess($this->selectedOrgId, $user);

            $targetUser = \App\Models\User::where('email', $this->newMemberEmail)->first();
            if (!$targetUser) {
                throw new \RuntimeException('Belirtilen e-posta adresine sahip kullanıcı bulunamadı.');
            }

            SupportOrganizationMembership::updateOrCreate(
                [
                    'legal_entity_id' => $this->selectedOrgId,
                    'user_id'         => $targetUser->id,
                ],
                [
                    'role' => $this->newMemberRole,
                ]
            );

            $this->newMemberEmail = '';
            $this->successMessage = 'Yeni üye başarıyla eklendi.';
            $this->errorMessage = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->successMessage = '';
        }
    }

    public function addServiceAccount(): void
    {
        $user = auth()->user();
        if (!$user) { abort(403); }

        try {
            CustomerCareOrganizationContext::enforceOrganizationAccess($this->selectedOrgId, $user);

            if (empty(trim($this->newSaName)) || empty(trim($this->newSaEmail))) {
                throw new \RuntimeException('Ad ve e-posta alanları zorunludur.');
            }

            SupportServiceAccount::create([
                'legal_entity_id' => $this->selectedOrgId,
                'name'            => $this->newSaName,
                'email'           => $this->newSaEmail,
                'is_active'       => true,
            ]);

            $this->newSaName = '';
            $this->newSaEmail = '';
            $this->successMessage = 'Yeni servis hesabı başarıyla oluşturuldu.';
            $this->errorMessage = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->successMessage = '';
        }
    }

    public function render()
    {
        $user = auth()->user();
        $members = collect();
        $serviceAccounts = collect();
        $stores = collect();

        if ($this->selectedOrgId && $user) {
            try {
                CustomerCareOrganizationContext::enforceOrganizationAccess($this->selectedOrgId, $user);

                $members = SupportOrganizationMembership::where('legal_entity_id', $this->selectedOrgId)
                    ->with('user')
                    ->get();

                $serviceAccounts = SupportServiceAccount::where('legal_entity_id', $this->selectedOrgId)->get();
                $stores = MarketplaceStore::where('legal_entity_id', $this->selectedOrgId)->get();
            } catch (\Throwable) {
                // yetki dışı durum - listeleri boşaltıp selectedOrgId'yi sıfırla
                $this->selectedOrgId = 0;
                $members = collect();
                $serviceAccounts = collect();
                $stores = collect();
            }
        }

        return view('livewire.customer-care.organization', [
            'organizations'   => $user ? CustomerCareOrganizationContext::getAccessibleOrganizations($user)->get() : collect(),
            'members'         => $members,
            'serviceAccounts' => $serviceAccounts,
            'stores'          => $stores,
        ])->layout('layouts.app');
    }
}
