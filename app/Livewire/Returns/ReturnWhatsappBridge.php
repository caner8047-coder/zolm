<?php

namespace App\Livewire\Returns;

use App\Jobs\AnalyzeReturnIntakeItemJob;
use App\Models\ReturnWhatsappThread;
use App\Models\User;
use App\Services\Returns\ReturnBridgeSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ReturnWhatsappBridge extends Component
{
    use WithPagination;

    public bool $embedded = false;
    public string $searchQuery = '';
    public string $statusFilter = 'all';
    public ?int $selectedThreadId = null;
    public string $message = '';
    public string $messageType = 'info';
    public array $settingsForm = [
        'enabled' => false,
        'system_user_id' => '',
        'verify_token' => '',
        'access_token' => '',
        'app_secret' => '',
        'graph_version' => 'v23.0',
        'message_window_minutes' => 8,
    ];

    public function mount(bool $embedded = false): void
    {
        $this->embedded = $embedded;
        abort_unless(auth()->user()?->canAccessReturnsReview(), 403);
        $this->loadSettingsForm();

        $requestedThreadId = request()->integer('thread');

        if ($requestedThreadId > 0) {
            $this->selectedThreadId = $requestedThreadId;
        }
    }

    public function updatedSearchQuery(): void
    {
        $this->resetPage($this->threadsPageName());
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage($this->threadsPageName());
    }

    public function selectThread(int $threadId): void
    {
        $this->selectedThreadId = $threadId;
    }

    public function saveBridgeSettings(ReturnBridgeSettingsService $settingsService): void
    {
        abort_unless(auth()->user()?->isManager(), 403);

        $enabled = filter_var(data_get($this->settingsForm, 'enabled', false), FILTER_VALIDATE_BOOL);

        $validated = $this->validate([
            'settingsForm.enabled' => ['boolean'],
            'settingsForm.system_user_id' => [$enabled ? 'required' : 'nullable', 'integer', 'exists:users,id'],
            'settingsForm.verify_token' => [$enabled ? 'required' : 'nullable', 'string', 'max:191'],
            'settingsForm.access_token' => [$enabled ? 'required' : 'nullable', 'string', 'max:4000'],
            'settingsForm.app_secret' => ['nullable', 'string', 'max:1000'],
            'settingsForm.graph_version' => ['required', 'string', 'max:32'],
            'settingsForm.message_window_minutes' => ['required', 'integer', 'min:1', 'max:120'],
        ], [], [
            'settingsForm.system_user_id' => 'sistem kullanıcısı',
            'settingsForm.verify_token' => 'verify token',
            'settingsForm.access_token' => 'access token',
            'settingsForm.app_secret' => 'app secret',
            'settingsForm.graph_version' => 'graph sürümü',
            'settingsForm.message_window_minutes' => 'oturum penceresi',
        ]);

        $settingsService->save([
            'enabled' => (bool) data_get($validated, 'settingsForm.enabled', false),
            'system_user_id' => data_get($validated, 'settingsForm.system_user_id') ?: null,
            'verify_token' => (string) data_get($validated, 'settingsForm.verify_token', ''),
            'access_token' => (string) data_get($validated, 'settingsForm.access_token', ''),
            'app_secret' => (string) data_get($validated, 'settingsForm.app_secret', ''),
            'graph_version' => (string) data_get($validated, 'settingsForm.graph_version', 'v23.0'),
            'message_window_minutes' => (int) data_get($validated, 'settingsForm.message_window_minutes', 8),
        ]);

        $this->loadSettingsForm();
        unset($this->bridgeConfig, $this->bridgeKpis);
        $this->showMessage('WhatsApp köprü ayarları kaydedildi.', 'success');
    }

    public function markCompleted(): void
    {
        $thread = $this->selectedThread;

        if (!$thread) {
            return;
        }

        $thread->update(['status' => 'completed']);
        $this->showMessage('WhatsApp oturumu tamamlandı olarak işaretlendi.', 'success');
    }

    public function reopenThread(): void
    {
        $thread = $this->selectedThread;

        if (!$thread) {
            return;
        }

        $thread->update(['status' => 'collecting']);
        $this->showMessage('WhatsApp oturumu yeniden açıldı.', 'success');
    }

    public function archiveThread(): void
    {
        $thread = $this->selectedThread;

        if (!$thread) {
            return;
        }

        $thread->update(['status' => 'archived']);
        $this->showMessage('WhatsApp oturumu arşive taşındı.', 'success');
    }

    public function dispatchAnalysisNow(): void
    {
        $thread = $this->selectedThread;

        if (!$thread?->intakeItem) {
            $this->showMessage('Bağlı iade kaydı bulunamadı.', 'error');
            return;
        }

        AnalyzeReturnIntakeItemJob::dispatchAfterResponse($thread->intakeItem->id);

        $thread->update([
            'status' => 'queued',
            'analysis_requested_at' => now(),
        ]);

        $thread->intakeItem->update([
            'intake_status' => 'queued',
            'analysis_started_at' => null,
            'analysis_completed_at' => null,
            'last_error' => null,
        ]);

        $this->showMessage('Analiz tekrar kuyruğa alındı.', 'success');
    }

    #[Computed]
    public function threads(): LengthAwarePaginator
    {
        $query = ReturnWhatsappThread::query()
            ->with([
                'messages.intakeMedia',
                'intakeItem.media',
                'intakeItem.latestAnalysis',
                'intakeItem.latestDecision',
            ])
            ->when($this->statusFilter !== 'all', fn ($builder) => $builder->where('status', $this->statusFilter))
            ->when($this->searchQuery !== '', function ($builder) {
                $search = '%' . $this->searchQuery . '%';

                $builder->where(function ($query) use ($search) {
                    $query->where('sender_phone', 'like', $search)
                        ->orWhere('sender_name', 'like', $search)
                        ->orWhere('external_chat_id', 'like', $search)
                        ->orWhereHas('intakeItem', function ($itemQuery) use ($search) {
                            $itemQuery->where('manual_reference', 'like', $search)
                                ->orWhere('detected_tracking_number', 'like', $search)
                                ->orWhere('detected_order_number', 'like', $search)
                                ->orWhere('operator_barcode', 'like', $search);
                        });
                });
            })
            ->orderByRaw("case when status = 'collecting' then 0 when status = 'queued' then 1 when status = 'completed' then 2 else 3 end")
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        $threads = $query->paginate(12, ['*'], $this->threadsPageName());

        if (!$this->selectedThreadId && $threads->count() > 0) {
            $this->selectedThreadId = (int) $threads->first()->id;
        }

        return $threads;
    }

    #[Computed]
    public function selectedThread(): ?ReturnWhatsappThread
    {
        if (!$this->selectedThreadId) {
            return null;
        }

        return ReturnWhatsappThread::query()
            ->with([
                'messages.intakeMedia',
                'intakeItem.media',
                'intakeItem.latestAnalysis',
                'intakeItem.latestDecision',
            ])
            ->find($this->selectedThreadId);
    }

    #[Computed]
    public function bridgeConfig(): array
    {
        $resolved = app(ReturnBridgeSettingsService::class)->resolved();

        return [
            'enabled' => (bool) ($resolved['enabled'] ?? false),
            'source' => (string) ($resolved['source'] ?? 'env'),
            'verify_token_present' => filled($resolved['verify_token'] ?? null),
            'access_token_present' => filled($resolved['access_token'] ?? null),
            'app_secret_present' => filled($resolved['app_secret'] ?? null),
            'system_user' => $resolved['system_user_name'] ?? null,
            'system_user_id' => $resolved['system_user_id'] ?? null,
            'verify_url' => route('returns.whatsapp.verify'),
            'receive_url' => route('returns.whatsapp.receive'),
            'graph_version' => (string) ($resolved['graph_version'] ?? 'v23.0'),
            'window_minutes' => (int) ($resolved['message_window_minutes'] ?? 8),
            'is_ready' => (bool) ($resolved['enabled'] ?? false)
                && filled($resolved['verify_token'] ?? null)
                && filled($resolved['access_token'] ?? null)
                && filled($resolved['system_user_id'] ?? null),
        ];
    }

    #[Computed]
    public function bridgeKpis(): array
    {
        return [
            'threads' => ReturnWhatsappThread::query()->count(),
            'collecting' => ReturnWhatsappThread::query()->where('status', 'collecting')->count(),
            'queued' => ReturnWhatsappThread::query()->where('status', 'queued')->count(),
            'completed' => ReturnWhatsappThread::query()->where('status', 'completed')->count(),
            'todayMessages' => \App\Models\ReturnWhatsappMessage::query()->where('received_at', '>=', today())->count(),
        ];
    }

    protected function showMessage(string $message, string $type): void
    {
        $this->message = $message;
        $this->messageType = $type;
    }

    protected function threadsPageName(): string
    {
        return 'returnThreadsPage';
    }

    protected function loadSettingsForm(): void
    {
        $resolved = app(ReturnBridgeSettingsService::class)->resolved();

        $this->settingsForm = [
            'enabled' => (bool) ($resolved['enabled'] ?? false),
            'system_user_id' => (string) ($resolved['system_user_id'] ?? ''),
            'verify_token' => (string) ($resolved['verify_token'] ?? ''),
            'access_token' => (string) ($resolved['access_token'] ?? ''),
            'app_secret' => (string) ($resolved['app_secret'] ?? ''),
            'graph_version' => (string) ($resolved['graph_version'] ?? 'v23.0'),
            'message_window_minutes' => (int) ($resolved['message_window_minutes'] ?? 8),
        ];
    }

    public function render(): View
    {
        $bridgeConfig = $this->bridgeConfig;
        
        $setupChecks = [
            ['label' => 'Köprü açık', 'done' => $bridgeConfig['enabled']],
            ['label' => 'Sistem kullanıcısı seçildi', 'done' => filled($bridgeConfig['system_user_id'])],
            ['label' => 'Verify token var', 'done' => $bridgeConfig['verify_token_present']],
            ['label' => 'Access token var', 'done' => $bridgeConfig['access_token_present']],
        ];

        $view = view('livewire.returns.return-whatsapp-bridge', [
            'threads' => $this->threads,
            'selectedThread' => $this->selectedThread,
            'bridgeConfig' => $bridgeConfig,
            'bridgeKpis' => $this->bridgeKpis,
            'setupChecks' => $setupChecks,
            'completedChecks' => collect($setupChecks)->where('done', true)->count(),
            'systemUsers' => User::query()
                ->where('is_active', true)
                ->whereIn('role', ['admin', 'manager', 'operator', 'operasyon_sorumlusu'])
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);

        if ($this->embedded) {
            return $view;
        }

        return $view->layout('layouts.app', [
            'title' => 'WhatsApp İade Köprüsü',
        ]);
    }
}
