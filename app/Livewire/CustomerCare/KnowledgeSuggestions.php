<?php

namespace App\Livewire\CustomerCare;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\SupportKnowledgeSuggestion;
use App\Models\MarketplaceQuestion;
use App\Services\Support\KnowledgeBaseService;
use App\Services\Support\TenantContext;
use App\Livewire\CustomerCare\Concerns\ResolvesAccessibleStores;

class KnowledgeSuggestions extends Component
{
    use WithPagination, ResolvesAccessibleStores;

    public ?int $selectedStoreId = null;
    public string $selectedStatus = 'pending';

    // Edit state
    public ?int $editingSuggestionId = null;
    public string $editTitle = '';
    public string $editProposedAnswer = '';
    public string $editCategory = '';

    public string $successMessage = '';
    public string $errorMessage = '';

    protected $queryString = [
        'selectedStoreId' => ['except' => null],
        'selectedStatus' => ['except' => 'pending'],
    ];

    public function mount()
    {
        $myStores = $this->getMyStores();
        if ($myStores->isNotEmpty() && !$this->selectedStoreId) {
            $this->selectedStoreId = $myStores->first()->id;
        }
    }

    public function updatedSelectedStoreId()
    {
        $this->resetPage();
    }

    public function updatedSelectedStatus()
    {
        $this->resetPage();
    }

    protected function getMyStores()
    {
        return $this->resolveAccessibleStores();
    }

    public function editSuggestion(int $id)
    {
        $suggestion = SupportKnowledgeSuggestion::find($id);
        if ($suggestion) {
            $myStoreIds = $this->getMyStores()->pluck('id')->toArray();
            if (!in_array($suggestion->store_id, $myStoreIds)) {
                $this->errorMessage = 'Bu öneriyi düzenleme yetkiniz yok.';
                return;
            }

            $this->editingSuggestionId = $suggestion->id;
            $this->editTitle = $suggestion->title;
            $this->editProposedAnswer = $suggestion->proposed_answer;
            $this->editCategory = $suggestion->category;
        }
    }

    public function cancelEdit()
    {
        $this->editingSuggestionId = null;
        $this->resetEditFields();
    }

    protected function resetEditFields()
    {
        $this->editTitle = '';
        $this->editProposedAnswer = '';
        $this->editCategory = '';
    }

    private function canPublishKnowledge(int $storeId): bool
    {
        try {
            app(\App\Services\Support\Security\SupportRbacService::class)
                ->enforcePermission(auth()->user() ?? TenantContext::getSystemActor(), $storeId, 'knowledge_publish');
            return true;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->errorMessage = $e->getMessage();
            return false;
        }
    }

    public function saveEdit()
    {
        $this->errorMessage = '';
        $this->successMessage = '';

        if (empty(trim($this->editTitle)) || empty(trim($this->editProposedAnswer))) {
            $this->errorMessage = 'Başlık ve Öneri İçeriği boş bırakılamaz.';
            return;
        }

        $suggestion = SupportKnowledgeSuggestion::find($this->editingSuggestionId);
        if ($suggestion) {
            if (!$this->canPublishKnowledge((int) $suggestion->store_id)) {
                return;
            }
            $myStoreIds = $this->getMyStores()->pluck('id')->toArray();
            if (!in_array($suggestion->store_id, $myStoreIds)) {
                $this->errorMessage = 'Bu öneriyi güncelleme yetkiniz yok.';
                return;
            }

            // Validasyon: prompt injection engelleme
            $injectionKeywords = [
                'ignore previous', 'system prompt', 'translate to', 'you are now', 'dan mode',
                'talimatları unut', 'ignore all', 'sen artık', 'temsilci rolü', 'sistem ayarı'
            ];
            foreach ($injectionKeywords as $keyword) {
                if (mb_stripos($this->editTitle, $keyword) !== false || mb_stripos($this->editProposedAnswer, $keyword) !== false) {
                    $this->errorMessage = 'Potansiyel prompt injection tespiti nedeniyle güncelleme engellendi.';
                    return;
                }
            }

            $redactor = app(\App\Services\Support\Security\PiiRedactor::class);
            $suggestion->update([
                'title' => trim($redactor->maskPii(strip_tags($this->editTitle))),
                'proposed_answer' => trim($redactor->maskPii(strip_tags($this->editProposedAnswer))),
                'category' => trim(strip_tags($this->editCategory ?: 'general')),
            ]);

            $this->successMessage = 'Öneri başarıyla güncellendi.';
            $this->editingSuggestionId = null;
            $this->resetEditFields();
        }
    }

    public function approve(int $id)
    {
        $this->errorMessage = '';
        $this->successMessage = '';

        $suggestion = SupportKnowledgeSuggestion::find($id);
        if ($suggestion) {
            if (!$this->canPublishKnowledge((int) $suggestion->store_id)) {
                return;
            }
            $myStoreIds = $this->getMyStores()->pluck('id')->toArray();
            if (!in_array($suggestion->store_id, $myStoreIds)) {
                $this->errorMessage = 'Bu öneriyi onaylama yetkiniz yok.';
                return;
            }

            try {
                $kbService = app(KnowledgeBaseService::class);
                $kbService->createArticle(
                    $suggestion->store_id,
                    $suggestion->title,
                    $suggestion->proposed_answer,
                    auth()->user(),
                    [
                        'version' => $suggestion->version,
                        'effective_until' => $suggestion->effective_until,
                        'scope' => $suggestion->scope,
                        'category' => $suggestion->category,
                    ]
                );

                $suggestion->update([
                    'status' => 'approved',
                    'reviewed_by_user_id' => auth()->id() ?? TenantContext::getSystemActor()?->id,
                    'reviewed_at' => now(),
                    'owner_user_id' => auth()->id() ?? TenantContext::getSystemActor()?->id,
                ]);

                // Update to applied once written to KB
                $suggestion->update(['status' => 'applied']);
                MarketplaceQuestion::where('learning_suggestion_id', $suggestion->id)->update([
                    'learning_status' => 'applied',
                    'learning_reviewed_by_user_id' => auth()->id() ?? TenantContext::getSystemActor()?->id,
                    'learning_reviewed_at' => now(),
                ]);

                $this->successMessage = 'Bilgi makalesi başarıyla onaylandı ve bilgi merkezine eklendi.';
            } catch (\Throwable $e) {
                $this->errorMessage = $e->getMessage();
            }
        }
    }

    public function reject(int $id)
    {
        $this->errorMessage = '';
        $this->successMessage = '';

        $suggestion = SupportKnowledgeSuggestion::find($id);
        if ($suggestion) {
            if (!$this->canPublishKnowledge((int) $suggestion->store_id)) {
                return;
            }
            $myStoreIds = $this->getMyStores()->pluck('id')->toArray();
            if (!in_array($suggestion->store_id, $myStoreIds)) {
                $this->errorMessage = 'Bu öneriyi reddetme yetkiniz yok.';
                return;
            }

            $suggestion->update([
                'status' => 'rejected',
                'reviewed_by_user_id' => auth()->id() ?? TenantContext::getSystemActor()?->id,
                'reviewed_at' => now(),
            ]);
            MarketplaceQuestion::where('learning_suggestion_id', $suggestion->id)->update([
                'learning_status' => 'excluded',
                'learning_excluded_reason' => 'Bilgi bankası incelemesinde reddedildi.',
                'is_golden_candidate' => false,
                'learning_reviewed_by_user_id' => auth()->id() ?? TenantContext::getSystemActor()?->id,
                'learning_reviewed_at' => now(),
            ]);

            $this->successMessage = 'Öneri reddedildi.';
        }
    }

    public function render()
    {
        $myStores = $this->getMyStores();
        $myStoreIds = $myStores->pluck('id')->toArray();

        $query = SupportKnowledgeSuggestion::whereIn('store_id', $myStoreIds);

        if ($this->selectedStoreId && in_array($this->selectedStoreId, $myStoreIds)) {
            $query->where('store_id', $this->selectedStoreId);
        }

        if ($this->selectedStatus) {
            $query->where('status', $this->selectedStatus);
        }

        $suggestions = $query->latest()->paginate(10);

        return view('livewire.customer-care.knowledge-suggestions', [
            'suggestions' => $suggestions,
            'myStores' => $myStores,
        ])->layout('layouts.app');
    }
}
