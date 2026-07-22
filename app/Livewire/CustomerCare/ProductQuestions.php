<?php

namespace App\Livewire\CustomerCare;

use App\Livewire\CustomerCare\Concerns\ResolvesAccessibleStores;
use App\Models\MarketplaceQuestion;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\MarketplaceConnectorManager;
use App\Services\Marketplace\MarketplaceManualSyncDispatchService;
use App\Services\Support\CustomerCareProductQuestionLearningService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class ProductQuestions extends Component
{
    use WithPagination, ResolvesAccessibleStores;

    protected static array $sortableColumns = [
        'product_name',
        'asked_at',
        'status',
        'learning_status',
    ];

    public ?int $selectedStoreId = null;
    public string $search = '';
    public string $answerFilter = 'all';
    public string $learningFilter = 'all';
    public string $sortField = 'asked_at';
    public string $sortDirection = 'desc';
    public string $successMessage = '';
    public string $errorMessage = '';
    public array $visibleColumns = [
        'product',
        'marketplace',
        'question',
        'answer',
        'learning',
        'date',
    ];

    protected $queryString = [
        'selectedStoreId' => ['except' => null],
        'search' => ['except' => ''],
        'answerFilter' => ['except' => 'all'],
        'learningFilter' => ['except' => 'all'],
        'sortField' => ['except' => 'asked_at'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function mount(): void
    {
        $this->resolveAccessibleStores();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['selectedStoreId', 'search', 'answerFilter', 'learningFilter'], true)) {
            $this->resolveAccessibleStores();
            $this->resetPage();
            $this->clearMessages();
        }
    }

    public function sortTable(string $column): void
    {
        if (!in_array($column, static::$sortableColumns, true)) {
            return;
        }

        if ($this->sortField === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function toggleColumn(string $column): void
    {
        $allowed = array_keys($this->columnLabels());
        if (!in_array($column, $allowed, true)) {
            return;
        }

        if (in_array($column, $this->visibleColumns, true)) {
            if (count($this->visibleColumns) > 1) {
                $this->visibleColumns = array_values(array_diff($this->visibleColumns, [$column]));
            }

            return;
        }

        $this->visibleColumns[] = $column;
    }

    public function syncQuestions(): void
    {
        $this->clearMessages();
        $store = $this->selectedStore();

        try {
            $capabilities = app(MarketplaceConnectorManager::class)
                ->resolveForStore($store)
                ->capabilities();
            if (!(bool) ($capabilities['questions'] ?? false)) {
                throw new \RuntimeException('Bu mağaza bağlayıcısı ürün sorusu çekmeyi desteklemiyor.');
            }

            $dispatcher = app(MarketplaceManualSyncDispatchService::class);
            $messages = [];

            foreach (['open', 'answered'] as $status) {
                $backfillDays = max(1, (int) config('customer-care.product_question_backfill_days', 365));
                $result = $dispatcher->dispatch($store, 'questions', [
                    'options' => [
                        'status' => $status,
                        'start_date' => now()->subDays($backfillDays)->toIso8601String(),
                        'end_date' => now()->toIso8601String(),
                    ],
                    'source' => 'customer_care_product_questions',
                    'origin_screen' => 'customer-care-product-questions',
                    'force_inline' => true,
                    'ignore_queued_active' => true,
                    'bypass_recent' => true,
                ]);
                $feedback = $dispatcher->feedback($result, $status === 'open' ? 'Açık soru' : 'Cevaplanmış soru', $store->store_name);
                $messages[] = $feedback['message'];
            }

            $this->successMessage = implode(' ', array_unique($messages));
        } catch (\Throwable $exception) {
            $this->errorMessage = $exception->getMessage();
        }
    }

    public function createKnowledgeCandidate(int $questionId): void
    {
        $this->clearMessages();

        try {
            $question = $this->ownedQuestion($questionId);
            app(CustomerCareProductQuestionLearningService::class)
                ->createKnowledgeCandidate($question, auth()->user());
            $this->successMessage = 'Soru-cevap bilgi bankası inceleme kuyruğuna eklendi.';
        } catch (\Throwable $exception) {
            $this->errorMessage = $exception->getMessage();
        }
    }

    public function excludeFromLearning(int $questionId): void
    {
        $this->clearMessages();

        try {
            app(CustomerCareProductQuestionLearningService::class)->exclude(
                $this->ownedQuestion($questionId),
                auth()->user(),
                'Ürün soruları ekranında insan kararıyla eğitim dışı bırakıldı.'
            );
            $this->successMessage = 'Soru-cevap eğitim havuzunun dışında bırakıldı.';
        } catch (\Throwable $exception) {
            $this->errorMessage = $exception->getMessage();
        }
    }

    public function restoreToLearning(int $questionId): void
    {
        $this->clearMessages();

        try {
            app(CustomerCareProductQuestionLearningService::class)
                ->restore($this->ownedQuestion($questionId), auth()->user());
            $this->successMessage = 'Soru-cevap yeniden inceleme havuzuna alındı.';
        } catch (\Throwable $exception) {
            $this->errorMessage = $exception->getMessage();
        }
    }

    public function toggleGoldenCandidate(int $questionId): void
    {
        $this->clearMessages();

        try {
            $enabled = app(CustomerCareProductQuestionLearningService::class)
                ->toggleGoldenCandidate($this->ownedQuestion($questionId), auth()->user());
            $this->successMessage = $enabled
                ? 'Soru-cevap golden dataset aday havuzuna eklendi.'
                : 'Soru-cevap golden dataset aday havuzundan çıkarıldı.';
        } catch (\Throwable $exception) {
            $this->errorMessage = $exception->getMessage();
        }
    }

    public function render()
    {
        $stores = $this->resolveAccessibleStores();
        $baseQuery = $this->questionsQuery();
        $questions = (clone $baseQuery)
            ->with(['store:id,marketplace,store_name,user_id', 'learningSuggestion:id,status,effective_until'])
            ->orderBy($this->sortField, $this->sortDirection)
            ->orderByDesc('id')
            ->paginate(15);

        $learningService = app(CustomerCareProductQuestionLearningService::class);
        $questions->getCollection()->each(function (MarketplaceQuestion $question) use ($learningService): void {
            $question->setAttribute('learning_eligibility', $learningService->eligibility($question));
        });

        return view('livewire.customer-care.product-questions', [
            'stores' => $stores,
            'questions' => $questions,
            'metrics' => $this->metrics(),
            'columnLabels' => $this->columnLabels(),
        ])->layout('layouts.app');
    }

    protected function questionsQuery(): Builder
    {
        return $this->baseQuestionsQuery()
            ->when($this->learningFilter !== 'all', function (Builder $query): void {
                if ($this->learningFilter === 'golden') {
                    $query->where('is_golden_candidate', true);
                } else {
                    $query->where('learning_status', $this->learningFilter);
                }
            });
    }

    protected function baseQuestionsQuery(): Builder
    {
        $storeIds = $this->resolveAccessibleStores()->pluck('id');

        return MarketplaceQuestion::query()
            ->whereIn('store_id', $storeIds)
            ->when($this->selectedStoreId, fn (Builder $query) => $query->where('store_id', $this->selectedStoreId))
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%' . trim($this->search) . '%';
                $query->where(function (Builder $subQuery) use ($term): void {
                    $subQuery->where('question_text', 'like', $term)
                        ->orWhere('answer_text', 'like', $term)
                        ->orWhere('product_name', 'like', $term)
                        ->orWhere('product_sku', 'like', $term)
                        ->orWhere('product_barcode', 'like', $term);
                });
            })
            ->when($this->answerFilter === 'answered', fn (Builder $query) => $query->whereNotNull('answer_text')->whereIn('status', ['answered', 'closed']))
            ->when($this->answerFilter === 'unanswered', fn (Builder $query) => $query->where(function (Builder $subQuery): void {
                $subQuery->whereNull('answer_text')->orWhereNotIn('status', ['answered', 'closed']);
            }));
    }

    protected function ownedQuestion(int $questionId): MarketplaceQuestion
    {
        $storeIds = $this->resolveAccessibleStores()->pluck('id');

        return MarketplaceQuestion::whereIn('store_id', $storeIds)
            ->with(['store', 'learningSuggestion'])
            ->findOrFail($questionId);
    }

    protected function selectedStore(): MarketplaceStore
    {
        $store = $this->resolveAccessibleStores()->firstWhere('id', (int) $this->selectedStoreId);
        if (!$store) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Bu mağazaya erişim yetkiniz yok.');
        }

        return $store;
    }

    protected function metrics(): array
    {
        $base = $this->baseQuestionsQuery();

        return [
            'total' => (clone $base)->count(),
            'answered' => (clone $base)->whereNotNull('answer_text')->whereIn('status', ['answered', 'closed'])->count(),
            'candidate' => (clone $base)->where('learning_status', 'candidate')->count(),
            'applied' => (clone $base)->where('learning_status', 'applied')->count(),
            'golden' => (clone $base)->where('is_golden_candidate', true)->count(),
        ];
    }

    protected function columnLabels(): array
    {
        return [
            'product' => 'Ürün',
            'marketplace' => 'Kanal',
            'question' => 'Müşteri Sorusu',
            'answer' => 'Yayınlanmış Cevap',
            'learning' => 'AI Eğitim Durumu',
            'date' => 'Tarih',
        ];
    }

    protected function clearMessages(): void
    {
        $this->successMessage = '';
        $this->errorMessage = '';
    }
}
