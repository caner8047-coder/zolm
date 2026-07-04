<?php

namespace App\Livewire\WhatsApp;

use App\Models\WaAccount;
use App\Models\WaTemplate;
use App\Services\WhatsApp\MetaCloudApiService;
use App\Services\WhatsApp\AuditLogService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class WhatsAppTemplateManager extends Component
{
    public $templates = [];
    public bool $syncing = false;
    public string $syncMessage = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $this->loadTemplates();
    }

    public function loadTemplates(): void
    {
        $this->templates = WaTemplate::with('account')
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function syncFromMeta(): void
    {
        $account = WaAccount::active()->first();

        if (!$account) {
            session()->flash('wa_error', 'Aktif WhatsApp hesabı bulunamadı.');
            return;
        }

        $this->syncing = true;
        $this->syncMessage = '';

        try {
            $metaApi = app(MetaCloudApiService::class);
            $remoteTemplates = $metaApi->syncTemplates($account);

            $synced = 0;
            foreach ($remoteTemplates as $remote) {
                WaTemplate::updateOrCreate(
                    [
                        'wa_account_id' => $account->id,
                        'name' => $remote['name'],
                        'language' => $remote['language'],
                    ],
                    [
                        'category' => $remote['category'] ?? 'unknown',
                        'status' => $remote['status'] ?? 'pending',
                        'components_json' => $remote['components'] ?? null,
                        'rejection_reason' => $remote['rejection_reason'] ?? null,
                        'synced_at' => now(),
                    ]
                );
                $synced++;
            }

            $this->syncMessage = "{$synced} şablon senkronize edildi.";

            app(AuditLogService::class)->log(
                'whatsapp_templates_synced',
                'wa_account',
                $account->id,
                ['count' => $synced],
            );
        } catch (\Throwable $e) {
            session()->flash('wa_error', 'Senkronizasyon hatası: ' . $e->getMessage());
        }

        $this->syncing = false;
        $this->loadTemplates();
    }

    public function render()
    {
        return view('livewire.whatsapp.whatsapp-template-manager');
    }
}
