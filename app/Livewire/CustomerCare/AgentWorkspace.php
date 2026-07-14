<?php

namespace App\Livewire\CustomerCare;

use Livewire\Component;
use App\Models\MarketplaceStore;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportReplyMacro;
use App\Models\SupportInternalNote;
use App\Services\Support\CustomerCareWorkspaceService;
use App\Services\Support\CustomerCareOrganizationContext;
use App\Services\Support\TenantContext;
use Illuminate\Support\Facades\Auth;

class AgentWorkspace extends Component
{
    public ?int $selectedStoreId = null;
    public ?int $selectedConversationId = null;
    public string $newNote = '';
    public string $draftReply = '';
    public string $newViewName = '';

    // Filtreler
    public string $statusFilter = 'all';
    public string $channelFilter = 'all';

    public function mount(): void
    {
        $accessibleStores = CustomerCareOrganizationContext::getAccessibleStores(Auth::user())->get();
        if ($accessibleStores->isNotEmpty()) {
            $this->selectedStoreId = $accessibleStores->first()->id;
        }
    }

    public function selectStore(int $storeId): void
    {
        $accessibleStores = CustomerCareOrganizationContext::getAccessibleStores(Auth::user())->get();
        if ($accessibleStores->contains('id', $storeId)) {
            $this->selectedStoreId = $storeId;
            $this->selectedConversationId = null;
            $this->draftReply = '';
        }
    }

    public function selectConversation(int $convId): void
    {
        if (!$this->selectedStoreId) return;

        // Yetki kontrolü (Tenant)
        $conv = SupportConversation::where('id', $convId)
            ->where('store_id', $this->selectedStoreId)
            ->first();

        if ($conv) {
            $this->selectedConversationId = $convId;
            $this->draftReply = '';

            // Soft Presence kaydı
            app(CustomerCareWorkspaceService::class)->registerPresence($convId, Auth::id());
        }
    }

    public function addInternalNote(): void
    {
        if (empty($this->newNote) || !$this->selectedConversationId) return;

        $user = Auth::user();
        TenantContext::enforceConversationAccess($this->selectedConversationId, $user);

        SupportInternalNote::create([
            'conversation_id' => $this->selectedConversationId,
            'user_id'         => $user->id,
            'note_encrypted'  => $this->newNote,
        ]);

        $this->newNote = '';
        session()->flash('note_success', 'Dahili not başarıyla kaydedildi.');
    }

    public function applyMacro(int $macroId): void
    {
        if (!$this->selectedConversationId) return;

        $macro = SupportReplyMacro::where('id', $macroId)
            ->where('store_id', $this->selectedStoreId)
            ->first();

        if ($macro) {
            $variables = [
                'customer_name' => 'Müşteri',
                'store_name'    => $macro->store->store_name ?? 'Mağaza',
            ];
            $this->draftReply = app(CustomerCareWorkspaceService::class)->renderMacro($macro, $variables, Auth::user());
        }
    }

    public function saveCustomView(): void
    {
        if (empty($this->newViewName) || !$this->selectedStoreId) return;

        app(CustomerCareWorkspaceService::class)->saveSavedView(
            Auth::id(),
            $this->selectedStoreId,
            $this->newViewName,
            [
                'status'  => $this->statusFilter,
                'channel' => $this->channelFilter,
            ]
        );

        $this->newViewName = '';
        session()->flash('view_success', 'Görünüm filtresi başarıyla kaydedildi.');
    }

    public function render()
    {
        $user = Auth::user();
        $accessibleStores = CustomerCareOrganizationContext::getAccessibleStores($user)->get();

        // Seçili mağaza yetkisini doğrula
        if ($this->selectedStoreId && !$accessibleStores->contains('id', $this->selectedStoreId)) {
            $this->selectedStoreId = $accessibleStores->first()?->id;
            $this->selectedConversationId = null;
        }

        $conversations = collect();
        $selectedConversation = null;
        $messages = collect();
        $internalNotes = collect();
        $activeOtherAgents = collect();
        $macros = collect();
        $savedViews = collect();

        if ($this->selectedStoreId) {
            $query = SupportConversation::where('store_id', $this->selectedStoreId);

            if ($this->statusFilter !== 'all') {
                $query->where('status', $this->statusFilter);
            }
            if ($this->channelFilter !== 'all') {
                $query->where('support_channel_id', $this->channelFilter);
            }

            $conversations = $query->latest()->get();
            $macros = SupportReplyMacro::where('store_id', $this->selectedStoreId)->where('is_active', true)->get();
            $savedViews = app(CustomerCareWorkspaceService::class)->getSavedViews($user->id, $this->selectedStoreId);

            if ($this->selectedConversationId) {
                $selectedConversation = SupportConversation::where('id', $this->selectedConversationId)
                    ->where('store_id', $this->selectedStoreId)
                    ->first();

                if ($selectedConversation) {
                    $messages = SupportMessage::where('conversation_id', $this->selectedConversationId)->orderBy('sent_at', 'asc')->get();
                    $internalNotes = SupportInternalNote::where('conversation_id', $this->selectedConversationId)->with('user')->get();

                    // Diğer aktif temsilciler
                    $activeOtherAgents = app(CustomerCareWorkspaceService::class)->getActivePresences($this->selectedConversationId, $user->id);
                }
            }
        }

        return view('livewire.customer-care.agent-workspace', [
            'accessibleStores'  => $accessibleStores,
            'conversations'     => $conversations,
            'selectedConversation' => $selectedConversation,
            'messages'          => $messages,
            'internalNotes'     => $internalNotes,
            'activeOtherAgents' => $activeOtherAgents,
            'macros'            => $macros,
            'savedViews'        => $savedViews,
        ])->layout('layouts.app');
    }
}
