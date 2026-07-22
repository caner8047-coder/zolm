<?php

namespace App\Livewire;

use App\Models\MarketplaceQuestion;
use App\Models\MarketplaceQuestionRule;
use App\Models\MarketplaceQuestionTemplate;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\MarketplaceConnectorManager;
use App\Services\Marketplace\MarketplaceManualSyncDispatchService;
use App\Services\Marketplace\MarketplaceQuestionAiService;
use App\Services\Marketplace\MarketplaceQuestionAnswerService;
use App\Services\Marketplace\MarketplaceQuestionRuleEngine;
use App\Services\MpSettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class MarketplaceQuestions extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'open';
    public string $marketplaceFilter = '';
    public string $storeFilter = '';
    public string $question = '';
    public ?int $selectedQuestionId = null;
    public string $answerText = '';
    public string $toastMessage = '';
    public string $toastTone = 'success';

    public bool $showTemplateForm = false;
    public ?int $editingTemplateId = null;
    public string $templateTitle = '';
    public string $templateCategory = '';
    public string $templateMarketplace = '';
    public string $templateBody = '';
    public bool $templateIsActive = true;

    public bool $showRuleForm = false;
    public ?int $editingRuleId = null;
    public string $ruleName = '';
    public string $ruleStoreId = '';
    public string $ruleTemplateId = '';
    public string $ruleKeywords = '';
    public string $ruleMatchType = 'contains';
    public string $ruleResponseText = '';
    public string $ruleActionMode = 'draft';
    public bool $ruleRequiresApproval = true;
    public int $rulePriority = 100;
    public bool $ruleIsActive = true;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => 'open'],
        'marketplaceFilter' => ['except' => ''],
        'storeFilter' => ['except' => ''],
        'question' => ['except' => ''],
    ];

    public function mount(): void
    {
        if ($this->question !== '') {
            $linkedQuestion = $this->ownedQuestionOrNull((int) $this->question);

            if ($linkedQuestion) {
                $this->statusFilter = '';
                $this->storeFilter = (string) $linkedQuestion->store_id;
                $this->selectQuestion($linkedQuestion->id);

                return;
            }

            $this->question = '';
        }

        $this->selectedQuestionId = MarketplaceQuestion::query()
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->open()
            ->latest('asked_at')
            ->value('id');
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'statusFilter', 'marketplaceFilter', 'storeFilter'], true)) {
            $this->resetPage();
            $this->selectedQuestionId = null;
            $this->answerText = '';
            $this->question = '';
        }
    }

    public function selectQuestion(int $questionId): void
    {
        $question = $this->ownedQuestion($questionId);

        $this->selectedQuestionId = $question->id;
        $this->question = (string) $question->id;
        $this->answerText = (string) ($question->answer_text ?: $question->ai_suggested_answer);
    }

    public function useTemplate(int $templateId): void
    {
        $question = $this->currentQuestion();
        $template = $this->ownedTemplate($templateId);

        $this->answerText = $this->renderTemplate($template->body, $question);
        $template->increment('usage_count');
        $this->notify('Hazır cevap editöre eklendi.');
    }

    public function generateAiAnswer(): void
    {
        if (! app(MpSettingsService::class)->isAiAnswerEnabled()) {
            $this->notify('AI cevap üretimi şu anda devre dışı.', 'warning');

            return;
        }

        $question = $this->currentQuestion();
        $answer = app(MarketplaceQuestionAiService::class)->suggestAnswer($question);

        $this->answerText = $answer;
        $this->notify('AI cevap taslağı hazırlandı.');
    }

    public function applyRuleToSelected(): void
    {
        $question = $this->currentQuestion();
        $rule = app(MarketplaceQuestionRuleEngine::class)->apply($question, auth()->user());

        if (!$rule) {
            $this->notify('Bu soru için eşleşen otonom kural bulunamadı.', 'info');
            return;
        }

        $this->answerText = (string) $question->fresh()->answer_text;
        $this->notify("{$rule->name} kuralı uygulandı.");
    }

    public function saveDraft(): void
    {
        $this->validate([
            'answerText' => ['required', 'string', 'min:3', 'max:5000'],
        ], [], [
            'answerText' => 'cevap',
        ]);

        app(MarketplaceQuestionAnswerService::class)->saveDraft(
            $this->currentQuestion(),
            trim($this->answerText),
            auth()->user(),
        );

        $this->notify('Cevap taslak olarak kaydedildi.');
    }

    public function sendAnswer(): void
    {
        $this->validate([
            'answerText' => ['required', 'string', 'min:3', 'max:5000'],
        ], [], [
            'answerText' => 'cevap',
        ]);

        $question = $this->currentQuestion();

        $log = app(MarketplaceQuestionAnswerService::class)->sendAnswer(
            $question,
            trim($this->answerText),
            auth()->user(),
        );

        if ($log->status === 'sent') {
            $this->syncSelectionAfterSentAnswer($question->id);
            $this->notify('Cevap pazaryerine gönderildi.');
            return;
        }

        $this->notify($log->error_message ?: 'Cevap taslak olarak saklandı; canlı gönderim yapılamadı.', 'warning');
    }

    public function syncQuestions(?int $storeId = null): void
    {
        $stores = $this->syncTargetStores($storeId);

        $supportedStores = $stores->filter(fn (MarketplaceStore $store) => $this->storeSupportsQuestionSync($store));
        $unsupportedStores = $stores->reject(fn (MarketplaceStore $store) => $this->storeSupportsQuestionSync($store));

        if ($supportedStores->isEmpty()) {
            $this->notify('Seçili mağazalarda canlı soru çekme henüz desteklenmiyor. Entegrasyon connector ayarlarını kontrol edin.', 'warning');
            return;
        }

        $handled = 0;
        $created = 0;
        $executedInline = 0;
        $errors = [];
        $feedbackMessages = [];
        $dispatcher = app(MarketplaceManualSyncDispatchService::class);

        foreach ($supportedStores as $store) {
            try {
                $result = $dispatcher->dispatch($store, 'questions', [
                    'options' => [
                        'status' => 'open',
                    ],
                    'source' => 'marketplace_questions_page',
                    'origin_screen' => 'questions',
                    'force_inline' => true,
                    'ignore_queued_active' => true,
                    'bypass_recent' => true,
                ]);
                $feedback = $dispatcher->feedback($result, 'Soru', $store->store_name);

                if (($feedback['tone'] ?? 'info') === 'error') {
                    $errors[] = $feedback['message'];
                    continue;
                }

                $feedbackMessages[] = $feedback['message'];
                $handled++;

                if ($result['created']) {
                    $created++;
                }

                if ($result['executed_inline']) {
                    $executedInline++;
                }
            } catch (\Throwable $exception) {
                $errors[] = "{$store->store_name}: {$exception->getMessage()}";
            }
        }

        if ($handled === 0) {
            $message = 'Canlı soru çekme denendi fakat tamamlanamadı.';
        } elseif ($executedInline > 0) {
            $message = "{$executedInline} mağaza için soru sync çalıştırıldı.";
        } elseif ($created > 0) {
            $message = "{$created} mağaza için soru çekme kuyruğa alındı.";
        } else {
            $message = "{$handled} mağaza için soru sync zaten sırada veya az önce işlendi.";
        }

        if ($feedbackMessages !== []) {
            $message .= ' ' . Str::limit(implode(' ', $feedbackMessages), 180);
        }

        if ($unsupportedStores->isNotEmpty()) {
            $message .= ' Henüz desteklenmeyenler: ' . $this->unsupportedStoresSummary($unsupportedStores) . '.';
        }

        if ($errors !== []) {
            $message .= ' Hata: ' . Str::limit(implode(' ', $errors), 180);
        }

        if ($handled > 0) {
            $this->resetPage();
            $this->selectedQuestionId = null;
            $this->question = '';
        }

        $this->notify($message, $errors === [] ? ($created > 0 ? 'success' : 'info') : 'warning');
    }

    public function createTemplate(): void
    {
        $this->resetTemplateForm();
        $this->showTemplateForm = true;
    }

    public function editTemplate(int $templateId): void
    {
        $template = $this->ownedTemplate($templateId);

        $this->editingTemplateId = $template->id;
        $this->templateTitle = $template->title;
        $this->templateCategory = (string) $template->category;
        $this->templateMarketplace = (string) $template->marketplace;
        $this->templateBody = $template->body;
        $this->templateIsActive = (bool) $template->is_active;
        $this->showTemplateForm = true;
    }

    public function saveTemplate(): void
    {
        $this->validate([
            'templateTitle' => ['required', 'string', 'max:120'],
            'templateCategory' => ['nullable', 'string', 'max:80'],
            'templateMarketplace' => ['nullable', 'string', 'max:40'],
            'templateBody' => ['required', 'string', 'min:3', 'max:5000'],
            'templateIsActive' => ['boolean'],
        ], [], [
            'templateTitle' => 'başlık',
            'templateBody' => 'cevap metni',
        ]);

        MarketplaceQuestionTemplate::query()->updateOrCreate([
            'id' => $this->editingTemplateId,
            'user_id' => auth()->id(),
        ], [
            'title' => $this->templateTitle,
            'category' => $this->templateCategory ?: null,
            'marketplace' => $this->templateMarketplace ?: null,
            'body' => $this->templateBody,
            'is_active' => $this->templateIsActive,
        ]);

        $this->resetTemplateForm();
        $this->notify('Hazır cevap kaydedildi.');
    }

    public function deleteTemplate(int $templateId): void
    {
        $this->ownedTemplate($templateId)->delete();
        $this->notify('Hazır cevap silindi.', 'info');
    }

    public function createRule(): void
    {
        $this->resetRuleForm();
        $this->showRuleForm = true;
    }

    public function editRule(int $ruleId): void
    {
        $rule = $this->ownedRule($ruleId);

        $this->editingRuleId = $rule->id;
        $this->ruleName = $rule->name;
        $this->ruleStoreId = (string) $rule->store_id;
        $this->ruleTemplateId = (string) $rule->template_id;
        $this->ruleKeywords = implode(', ', $rule->keywords_json ?? []);
        $this->ruleMatchType = $rule->match_type;
        $this->ruleResponseText = (string) $rule->response_text;
        $this->ruleActionMode = $rule->action_mode;
        $this->ruleRequiresApproval = (bool) $rule->requires_approval;
        $this->rulePriority = (int) $rule->priority;
        $this->ruleIsActive = (bool) $rule->is_active;
        $this->showRuleForm = true;
    }

    public function saveRule(): void
    {
        $this->validate([
            'ruleName' => ['required', 'string', 'max:120'],
            'ruleStoreId' => ['nullable', 'integer'],
            'ruleTemplateId' => ['nullable', 'integer'],
            'ruleKeywords' => ['required', 'string', 'max:1000'],
            'ruleMatchType' => ['required', 'in:contains,exact,regex'],
            'ruleResponseText' => ['nullable', 'string', 'max:5000'],
            'ruleActionMode' => ['required', 'in:draft,auto_send'],
            'ruleRequiresApproval' => ['boolean'],
            'rulePriority' => ['required', 'integer', 'min:1', 'max:9999'],
            'ruleIsActive' => ['boolean'],
        ], [], [
            'ruleName' => 'kural adı',
            'ruleKeywords' => 'anahtar kelimeler',
        ]);

        if ($this->ruleTemplateId === '' && trim($this->ruleResponseText) === '') {
            $this->addError('ruleResponseText', 'Kural için hazır cevap veya cevap metni seçmelisiniz.');
            return;
        }

        $storeId = $this->ruleStoreId !== '' ? (int) $this->ruleStoreId : null;
        $templateId = $this->ruleTemplateId !== '' ? (int) $this->ruleTemplateId : null;

        if ($storeId) {
            $this->ownedStore($storeId);
        }

        if ($templateId) {
            $this->ownedTemplate($templateId);
        }

        MarketplaceQuestionRule::query()->updateOrCreate([
            'id' => $this->editingRuleId,
            'user_id' => auth()->id(),
        ], [
            'store_id' => $storeId,
            'template_id' => $templateId,
            'name' => $this->ruleName,
            'match_type' => $this->ruleMatchType,
            'keywords_json' => $this->parseKeywords($this->ruleKeywords),
            'response_text' => trim($this->ruleResponseText) ?: null,
            'action_mode' => $this->ruleActionMode,
            'requires_approval' => $this->ruleRequiresApproval,
            'priority' => $this->rulePriority,
            'is_active' => $this->ruleIsActive,
        ]);

        $this->resetRuleForm();
        $this->notify('Otonom kural kaydedildi.');
    }

    public function deleteRule(int $ruleId): void
    {
        $this->ownedRule($ruleId)->delete();
        $this->notify('Otonom kural silindi.', 'info');
    }

    public function render()
    {
        $questions = $this->questionsQuery()
            ->with(['store:id,marketplace,store_name,user_id', 'matchedRule:id,name'])
            ->latest('asked_at')
            ->latest('id')
            ->paginate(12);

        if (!$this->selectedQuestionId && $questions->count() > 0) {
            $firstQuestion = $questions->first();
            $this->selectedQuestionId = (int) $firstQuestion->id;
            $this->answerText = (string) ($firstQuestion->answer_text ?: $firstQuestion->ai_suggested_answer);
        }

        $selectedQuestion = $this->selectedQuestionId
            ? MarketplaceQuestion::query()
                ->whereHas('store', fn (Builder $query) => $query->where('user_id', auth()->id()))
                ->with([
                    'store:id,marketplace,store_name,user_id',
                    'messages',
                    'answerLogs.user:id,name',
                    'answerLogs.template:id,title',
                    'answerLogs.rule:id,name',
                    'matchedRule:id,name',
                ])
                ->find($this->selectedQuestionId)
            : null;

        return view('livewire.marketplace-questions', [
            'questions' => $questions,
            'selectedQuestion' => $selectedQuestion,
            'stores' => $this->stores(),
            'marketplaces' => $this->marketplaces(),
            'templates' => $this->templates(),
            'rules' => $this->rules(),
            'metrics' => $this->metrics(),
        ])->layout('layouts.app');
    }

    protected function questionsQuery(): Builder
    {
        return MarketplaceQuestion::query()
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->when($this->search !== '', function (Builder $query) {
                $term = '%' . $this->search . '%';
                $query->where(function (Builder $subQuery) use ($term) {
                    $subQuery->where('question_text', 'like', $term)
                        ->orWhere('product_name', 'like', $term)
                        ->orWhere('product_sku', 'like', $term)
                        ->orWhere('product_barcode', 'like', $term)
                        ->orWhere('customer_name', 'like', $term);
                });
            })
            ->when($this->statusFilter !== '', fn (Builder $query) => $this->statusFilter === 'open'
                ? $query->open()
                : $query->where('status', $this->statusFilter))
            ->when($this->marketplaceFilter !== '', fn (Builder $query) => $query->whereHas('store', fn (Builder $storeQuery) => $storeQuery->where('marketplace', $this->marketplaceFilter)))
            ->when($this->storeFilter !== '', fn (Builder $query) => $query->where('store_id', $this->storeFilter));
    }

    protected function ownedQuestion(int $questionId): MarketplaceQuestion
    {
        return MarketplaceQuestion::query()
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->findOrFail($questionId);
    }

    protected function ownedQuestionOrNull(int $questionId): ?MarketplaceQuestion
    {
        if ($questionId <= 0) {
            return null;
        }

        return MarketplaceQuestion::query()
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->find($questionId);
    }

    protected function currentQuestion(): MarketplaceQuestion
    {
        abort_unless($this->selectedQuestionId, 404);

        return $this->ownedQuestion($this->selectedQuestionId)->load('store');
    }

    protected function syncSelectionAfterSentAnswer(int $answeredQuestionId): void
    {
        $currentQuestion = $this->questionsQuery()
            ->whereKey($answeredQuestionId)
            ->first();

        if ($currentQuestion) {
            $this->selectedQuestionId = $currentQuestion->id;
            $this->question = (string) $currentQuestion->id;
            $this->answerText = (string) ($currentQuestion->answer_text ?: $currentQuestion->ai_suggested_answer);

            return;
        }

        $nextQuestion = $this->questionsQuery()
            ->latest('asked_at')
            ->latest('id')
            ->first();

        if ($nextQuestion) {
            $this->selectedQuestionId = $nextQuestion->id;
            $this->question = (string) $nextQuestion->id;
            $this->answerText = (string) ($nextQuestion->answer_text ?: $nextQuestion->ai_suggested_answer);

            return;
        }

        $this->selectedQuestionId = null;
        $this->question = '';
        $this->answerText = '';
    }

    protected function ownedTemplate(int $templateId): MarketplaceQuestionTemplate
    {
        return MarketplaceQuestionTemplate::query()
            ->where('user_id', auth()->id())
            ->findOrFail($templateId);
    }

    protected function ownedRule(int $ruleId): MarketplaceQuestionRule
    {
        return MarketplaceQuestionRule::query()
            ->where('user_id', auth()->id())
            ->findOrFail($ruleId);
    }

    protected function ownedStore(int $storeId): MarketplaceStore
    {
        return MarketplaceStore::query()
            ->where('user_id', auth()->id())
            ->findOrFail($storeId);
    }

    protected function storeSupportsQuestionSync(MarketplaceStore $store): bool
    {
        try {
            $capabilities = app(MarketplaceConnectorManager::class)
                ->resolveForStore($store)
                ->capabilities();

            return (bool) ($capabilities['questions'] ?? false);
        } catch (\Throwable) {
            return false;
        }
    }

    protected function unsupportedStoresSummary(Collection $stores): string
    {
        $labels = $stores
            ->map(fn (MarketplaceStore $store) => $store->store_name . ' (' . Str::headline($store->marketplace) . ')')
            ->unique()
            ->values();

        $visible = $labels->take(3)->implode(', ');
        $remaining = $labels->count() - 3;

        return $remaining > 0 ? "{$visible} +{$remaining} mağaza" : $visible;
    }

    protected function stores(): Collection
    {
        return MarketplaceStore::query()
            ->where('user_id', auth()->id())
            ->orderBy('store_name')
            ->get(['id', 'marketplace', 'store_name']);
    }

    protected function syncTargetStores(?int $storeId = null): Collection
    {
        return $this->stores()
            ->when($this->marketplaceFilter !== '', fn (Collection $collection) => $collection->where('marketplace', $this->marketplaceFilter))
            ->when($this->storeFilter !== '', fn (Collection $collection) => $collection->where('id', (int) $this->storeFilter))
            ->when($storeId, fn (Collection $collection) => $collection->where('id', $storeId));
    }

    protected function marketplaces(): Collection
    {
        return $this->stores()
            ->pluck('marketplace')
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    protected function templates(): Collection
    {
        return MarketplaceQuestionTemplate::query()
            ->where('user_id', auth()->id())
            ->orderByDesc('is_active')
            ->orderByDesc('usage_count')
            ->latest()
            ->get();
    }

    protected function rules(): Collection
    {
        return MarketplaceQuestionRule::query()
            ->where('user_id', auth()->id())
            ->with(['store:id,store_name,marketplace', 'template:id,title'])
            ->orderByDesc('is_active')
            ->orderBy('priority')
            ->latest()
            ->get();
    }

    /**
     * @return array<string, int>
     */
    protected function metrics(): array
    {
        $base = MarketplaceQuestion::query()
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', auth()->id()));

        return [
            'open' => (clone $base)->open()->count(),
            'draft' => (clone $base)->where('status', 'draft')->count(),
            'answered' => (clone $base)->where('status', 'answered')->count(),
            'rules' => MarketplaceQuestionRule::query()->where('user_id', auth()->id())->where('is_active', true)->count(),
        ];
    }

    public function resetTemplateForm(): void
    {
        $this->editingTemplateId = null;
        $this->templateTitle = '';
        $this->templateCategory = '';
        $this->templateMarketplace = '';
        $this->templateBody = '';
        $this->templateIsActive = true;
        $this->showTemplateForm = false;
    }

    public function resetRuleForm(): void
    {
        $this->editingRuleId = null;
        $this->ruleName = '';
        $this->ruleStoreId = '';
        $this->ruleTemplateId = '';
        $this->ruleKeywords = '';
        $this->ruleMatchType = 'contains';
        $this->ruleResponseText = '';
        $this->ruleActionMode = 'draft';
        $this->ruleRequiresApproval = true;
        $this->rulePriority = 100;
        $this->ruleIsActive = true;
        $this->showRuleForm = false;
    }

    /**
     * @return array<int, string>
     */
    protected function parseKeywords(string $keywords): array
    {
        return collect(preg_split('/[\n,]+/', $keywords) ?: [])
            ->map(fn ($keyword) => trim((string) $keyword))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function renderTemplate(string $body, MarketplaceQuestion $question): string
    {
        return strtr($body, [
            '{urun}' => (string) $question->product_name,
            '{sku}' => (string) $question->product_sku,
            '{barkod}' => (string) $question->product_barcode,
            '{magaza}' => (string) $question->store->store_name,
            '{pazaryeri}' => (string) $question->store->marketplace,
        ]);
    }

    protected function notify(string $message, string $tone = 'success'): void
    {
        $this->toastMessage = $message;
        $this->toastTone = $tone;
    }
}
