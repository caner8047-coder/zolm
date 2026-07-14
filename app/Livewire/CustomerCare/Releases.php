<?php

namespace App\Livewire\CustomerCare;

use Livewire\Component;
use App\Models\SupportReleasePackage;
use App\Models\SupportReleasePackageItem;
use App\Services\Support\CustomerCareReleaseService;
use App\Services\Support\Security\SupportRbacService;
use App\Livewire\CustomerCare\Concerns\ResolvesAccessibleStores;

class Releases extends Component
{
    use ResolvesAccessibleStores;

    public int $selectedStoreId = 0;
    public string $errorMessage = '';
    public string $successMessage = '';

    // Create Package Fields
    public string $title = '';
    public string $artifactType = 'prompt_template';
    public string $contentRaw = '';

    // Selected package for diff view & preflight results
    public ?int $activePackageId = null;
    public array $preflightResults = [];

    protected $queryString = ['selectedStoreId'];

    public function mount()
    {
        if (!config('customer-care.release_center_enabled', false)) {
            abort(404);
        }

        $user = auth()->user();
        if (!$user || !in_array($user->role, ['admin', 'operator'], true)) {
            abort(403);
        }

        $this->resolveAccessibleStores();
    }

    public function createPackage()
    {
        $this->enforceSelectedStoreAccess();
        $this->validate([
            'title' => 'required|string|min:5',
            'artifactType' => 'required|in:knowledge_article,brand_voice,policy_rule,prompt_template,answer_template',
            'contentRaw' => 'required|string',
        ]);

        $rbac = app(SupportRbacService::class);
        $user = auth()->user();

        try {
            $rbac->enforcePermission($user, $this->selectedStoreId, 'force_circuit_breaker');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            return;
        }

        try {
            // Decode raw content
            $newContent = json_decode($this->contentRaw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // If not valid json, treat as string in content array
                $newContent = ['text' => $this->contentRaw];
            }

            \DB::transaction(function () use ($user, $newContent) {
                $pkg = SupportReleasePackage::create([
                    'store_id' => $this->selectedStoreId,
                    'title' => $this->title,
                    'status' => 'draft',
                    'created_by' => $user->id,
                ]);

                SupportReleasePackageItem::create([
                    'package_id' => $pkg->id,
                    'artifact_type' => $this->artifactType,
                    'artifact_id' => 1, // sample generic reference ID
                    'action' => 'update',
                    'diff_json' => ['before' => 'N/A', 'after' => $newContent],
                    'new_content_json' => $newContent,
                ]);
            });

            $this->successMessage = 'Release paketi taslağı başarıyla oluşturuldu.';
            $this->title = '';
            $this->contentRaw = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function runPreflight(int $packageId)
    {
        $this->enforceSelectedStoreAccess();
        $package = SupportReleasePackage::where('store_id', $this->selectedStoreId)->find($packageId);
        if (!$package) {
            $this->errorMessage = 'Paket bulunamadı.';
            return;
        }

        $service = app(CustomerCareReleaseService::class);
        try {
            $result = $service->preflightCheck($package, auth()->user());
            $this->activePackageId = $packageId;
            $this->preflightResults = $result['checks'];

            if ($result['allowed']) {
                $package->update(['status' => 'review']);
                $this->successMessage = 'Preflight denetim testleri başarıyla TAMAMLANDI. Paket inceleme aşamasına alındı.';
            } else {
                $package->update(['status' => 'rejected']);
                $this->errorMessage = 'Preflight kontrolleri başarısız oldu (PII veya Prompt Injection tespit edildi). Paket reddedildi.';
            }
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function publishPackage(int $packageId)
    {
        $this->enforceSelectedStoreAccess();
        $package = SupportReleasePackage::where('store_id', $this->selectedStoreId)->find($packageId);
        if (!$package) {
            $this->errorMessage = 'Paket bulunamadı.';
            return;
        }

        $user = auth()->user();
        $service = app(CustomerCareReleaseService::class);
        try {
            $service->publishPackage($package, $user);
            $this->successMessage = 'Release paketi başarıyla yayınlandı. Tüm AI talimatları güncel versiyona çekildi.';
        } catch (\App\Exceptions\ApprovalRequiredException $e) {
            $this->successMessage = $e->getMessage() . ' Onaylandıktan sonra yayına alabilirsiniz.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function rollbackPackage(int $packageId)
    {
        $this->enforceSelectedStoreAccess();
        $package = SupportReleasePackage::where('store_id', $this->selectedStoreId)->find($packageId);
        if (!$package) {
            $this->errorMessage = 'Paket bulunamadı.';
            return;
        }

        $user = auth()->user();
        $service = app(CustomerCareReleaseService::class);
        try {
            $service->rollbackPackage($package, $user);
            $this->successMessage = 'Geri alma (Rollback) başarıyla uygulandı. Önceki aktif versiyonlara dönüldü.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        $stores = $this->resolveAccessibleStores();

        $packages = SupportReleasePackage::where('store_id', $this->selectedStoreId)
            ->with(['items', 'creator', 'approver'])
            ->latest()
            ->get();

        return view('livewire.customer-care.releases', [
            'stores' => $stores,
            'packages' => $packages,
        ])->layout('layouts.app');
    }
}
