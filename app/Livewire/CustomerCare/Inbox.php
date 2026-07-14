<?php

namespace App\Livewire\CustomerCare;

use Livewire\Component;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportTeam;
use App\Services\Support\SupportConversationService;
use App\Services\Support\SupportReplyService;
use App\Services\Support\TenantContext;
use App\Services\Support\CustomerCareRoutingService;
use App\Services\Support\CustomerCareSalesAssistService;
use App\Services\Support\CustomerCareOrganizationContext;

class Inbox extends Component
{
    public string $search = '';
    public string $filterStatus = 'open'; // default to open conversations
    public string $filterChannel = 'all';
    public string $filterOwnership = 'all';
    public string $filterAiMode = 'all';

    // New Filters (Dalga AC)
    public string $filterTeamId = 'all';
    public string $filterAssignee = 'all'; // all, me, unassigned

    public ?int $selectedConversationId = null;
    public string $replyText = '';
    public string $errorMessage = '';
    public string $successMessage = '';
    public bool $isLoadingDraft = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStatus' => ['except' => 'open'],
        'filterChannel' => ['except' => 'all'],
        'filterOwnership' => ['except' => 'all'],
        'filterAiMode' => ['except' => 'all'],
        'filterTeamId' => ['except' => 'all'],
        'filterAssignee' => ['except' => 'all'],
        'selectedConversationId' => ['except' => null],
    ];

    public function mount()
    {
        // Enforce basic Customer Care module enabled
        if (!config('customer-care.enabled', false) || !config('customer-care.inbox_enabled', false)) {
            abort(404);
        }

        if ($this->selectedConversationId) {
            $this->selectedConversationForCurrentUser();
        }
    }

    /**
     * Resolves the selected conversation safely ensuring tenant isolation.
     */
    private function selectedConversationForCurrentUser(): ?SupportConversation
    {
        if (!$this->selectedConversationId) {
            return null;
        }

        $conv = SupportConversation::find($this->selectedConversationId);
        if (!$conv) {
            $this->selectedConversationId = null;
            return null;
        }

        try {
            TenantContext::enforceConversationAccess($conv->id, auth()->user());
            return $conv;
        } catch (\Throwable $e) {
            $this->selectedConversationId = null;
            return null;
        }
    }

    public function selectConversation(int $id)
    {
        $this->errorMessage = '';
        $this->successMessage = '';

        $conv = SupportConversation::find($id);
        if ($conv) {
            try {
                TenantContext::enforceConversationAccess($conv->id, auth()->user());
                $this->selectedConversationId = $id;
                $this->replyText = '';

                // Load active AI draft if exists
                $draft = $conv->messages()
                    ->where('sender_type', 'ai')
                    ->where('delivery_status', 'draft')
                    ->latest()
                    ->first();
                if ($draft) {
                    $this->replyText = $draft->body_encrypted;
                }
            } catch (\Throwable $e) {
                $this->errorMessage = $e->getMessage();
            }
        }
    }

    public function claimConversation()
    {
        $this->errorMessage = '';
        $this->successMessage = '';
        $conv = $this->selectedConversationForCurrentUser();
        if ($conv) {
            try {
                $service = app(CustomerCareRoutingService::class);
                if ($service->claim($conv, auth()->user())) {
                    $this->successMessage = 'Konuşma başarıyla sahiplenildi.';
                } else {
                    $this->errorMessage = 'Konuşma sahiplenilemedi (Eşzamanlılık çakışması veya zaten başka bir temsilciye atanmış).';
                }
            } catch (\Throwable $e) {
                $this->errorMessage = $e->getMessage();
            }
        }
    }

    public function releaseConversation()
    {
        $this->errorMessage = '';
        $this->successMessage = '';
        $conv = $this->selectedConversationForCurrentUser();
        if ($conv) {
            try {
                $service = app(CustomerCareRoutingService::class);
                if ($service->release($conv, auth()->user())) {
                    $this->successMessage = 'Konuşma sahipliği AI\'a geri bırakıldı.';
                } else {
                    $this->errorMessage = 'Sahiplik bırakılamadı.';
                }
            } catch (\Throwable $e) {
                $this->errorMessage = $e->getMessage();
            }
        }
    }

    public function resolveConversation()
    {
        $this->errorMessage = '';
        $this->successMessage = '';
        $conv = $this->selectedConversationForCurrentUser();
        if ($conv) {
            try {
                $service = app(SupportConversationService::class);
                if ($service->markAsResolved($conv, auth()->user())) {
                    $this->successMessage = 'Konuşma çözüldü olarak işaretlendi.';
                } else {
                    $this->errorMessage = 'İşlem başarısız oldu.';
                }
            } catch (\Throwable $e) {
                $this->errorMessage = $e->getMessage();
            }
        }
    }

    public function reopenConversation()
    {
        $this->errorMessage = '';
        $this->successMessage = '';
        $conv = $this->selectedConversationForCurrentUser();
        if ($conv) {
            try {
                $service = app(SupportConversationService::class);
                if ($service->reopen($conv, auth()->user())) {
                    $this->successMessage = 'Konuşma yeniden açıldı.';
                } else {
                    $this->errorMessage = 'İşlem başarısız oldu.';
                }
            } catch (\Throwable $e) {
                $this->errorMessage = $e->getMessage();
            }
        }
    }

    public function changeAiMode(string $mode)
    {
        $this->errorMessage = '';
        $this->successMessage = '';
        $conv = $this->selectedConversationForCurrentUser();
        if ($conv) {
            try {
                $service = app(SupportConversationService::class);
                if ($service->changeAiMode($conv, $mode, auth()->user())) {
                    $this->successMessage = 'Otomasyon modu başarıyla güncellendi: ' . ucfirst($mode);
                }
            } catch (\Throwable $e) {
                $this->errorMessage = $e->getMessage();
            }
        }
    }

    public function generateAiDraft()
    {
        $this->errorMessage = '';
        $this->successMessage = '';
        $conv = $this->selectedConversationForCurrentUser();
        if ($conv) {
            try {
                $this->isLoadingDraft = true;
                $replyService = app(SupportReplyService::class);
                $res = $replyService->generateAiDraft($conv);
                if ($res['success']) {
                    $this->replyText = $res['suggested_answer'];
                    $this->successMessage = 'AI Taslağı başarıyla oluşturuldu.';
                } else {
                    $this->errorMessage = 'AI Taslağı oluşturulamadı: ' . ($res['message'] ?? 'Bilinmeyen hata');
                }
            } catch (\Throwable $e) {
                $this->errorMessage = $e->getMessage();
            } finally {
                $this->isLoadingDraft = false;
            }
        }
    }

    public function sendReply()
    {
        $this->errorMessage = '';
        $this->successMessage = '';

        if (empty(trim($this->replyText))) {
            $this->errorMessage = 'Lütfen bir yanıt yazın.';
            return;
        }

        $conv = $this->selectedConversationForCurrentUser();
        if ($conv) {
            try {
                $replyService = app(SupportReplyService::class);
                $res = $replyService->sendAgentReply($conv, $this->replyText, auth()->id());
                if ($res['success']) {
                    $this->replyText = '';
                    $this->successMessage = ($res['queued'] ?? false)
                        ? 'Yanıtınız güvenli gönderim kuyruğuna alındı.'
                        : 'Yanıtınız başarıyla gönderildi.';
                } else {
                    $this->errorMessage = $res['message'];
                }
            } catch (\Throwable $e) {
                $this->errorMessage = $e->getMessage();
            }
        }
    }

    public function insertSalesSuggestion(string $draft)
    {
        $this->replyText = trim($this->replyText . ' ' . $draft);
        $this->successMessage = 'Satış copilot önerisi yanıt kutusuna eklendi.';
    }

    public function render()
    {
        // Enforce Tenant Isolation for conversation query
        $myStores = CustomerCareOrganizationContext::getAccessibleStores(auth()->user())->get();
        $storeIds = $myStores->pluck('id')->toArray();

        $query = SupportConversation::whereIn('store_id', $storeIds);

        // Apply Search
        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('external_conversation_id', 'like', '%' . $this->search . '%')
                  ->orWhere('external_customer_hash', hash('sha256', trim($this->search)))
                  ->orWhereHas('messages', function ($mq) {
                      $mq->where('body_preview', 'like', '%' . $this->search . '%');
                  });
            });
        }

        // Apply Filters
        if ($this->filterStatus !== 'all') {
            $query->where('status', $this->filterStatus);
        }
        if ($this->filterChannel !== 'all') {
            $query->where('source_type', $this->filterChannel);
        }
        if ($this->filterOwnership !== 'all') {
            $query->where('ownership_status', $this->filterOwnership);
        }
        if ($this->filterAiMode !== 'all') {
            $query->where('ai_mode', $this->filterAiMode);
        }

        // Team and Assignee Filters (Dalga AC)
        if ($this->filterTeamId !== 'all') {
            $query->where('support_team_id', $this->filterTeamId);
        }
        if ($this->filterAssignee === 'me') {
            $query->where('assigned_user_id', auth()->id());
        } elseif ($this->filterAssignee === 'unassigned') {
            $query->whereNull('assigned_user_id');
        }

        $conversations = $query->orderBy('last_message_at', 'desc')->get();

        // Selected Conversation Messages using secure resolver
        $selectedConversation = $this->selectedConversationForCurrentUser();
        $messages = collect();
        $salesSuggestions = [];
        $latestAiRun = null;

        if ($selectedConversation) {
            $messages = $selectedConversation->messages()
                ->where('delivery_status', '!=', 'draft') // do not show drafts in historical timeline
                ->orderBy('created_at', 'asc')
                ->get();

            // Generate Sales Assist recommendations (Dalga AD)
            $salesSuggestions = app(CustomerCareSalesAssistService::class)->generateSalesSuggestions($selectedConversation);
            $latestAiRun = \App\Models\SupportAiRun::where('conversation_id', $selectedConversation->id)
                ->latest('id')->first();
        }

        // Fetch Teams for dropdown
        $teams = SupportTeam::whereIn('store_id', $storeIds)->get();

        return view('livewire.customer-care.inbox', [
            'conversations' => $conversations,
            'selectedConversation' => $selectedConversation,
            'messages' => $messages,
            'salesSuggestions' => $salesSuggestions,
            'teams' => $teams,
            'latestAiRun' => $latestAiRun,
            'channelHealth' => \App\Models\SupportChannel::whereIn('store_id', $storeIds)
                ->orderBy('name')
                ->get(['id', 'name', 'key', 'status', 'is_enabled', 'last_sync_at', 'last_health_check_at', 'last_health_status', 'last_health_error']),
        ])->layout('layouts.app');
    }
}
