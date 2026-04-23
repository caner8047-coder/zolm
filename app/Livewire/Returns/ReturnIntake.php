<?php

namespace App\Livewire\Returns;

use App\Jobs\AnalyzeReturnIntakeItemJob;
use App\Models\ReturnIntakeItem;
use App\Services\Returns\ReturnIntakeService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class ReturnIntake extends Component
{
    use WithFileUploads;

    public bool $embedded = false;
    public string $intakeType = 'undamaged';
    public array $labelImages = [];
    public array $productImages = [];
    public array $damageImages = [];
    public string $manualReference = '';
    public string $operatorBarcode = '';
    public string $warehouseNote = '';
    public string $message = '';
    public string $messageType = 'info';
    public ?int $lastCreatedItemId = null;
    public bool $continuousMode = false;
    public bool $bulkMode = false;
    public ?array $lastAnalysisResult = null;

    public function mount(bool $embedded = false): void
    {
        $this->embedded = $embedded;
        abort_unless(Auth::user()?->canAccessReturnsIntake(), 403);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $maxImageKb = max(512, (int) config('returns.max_image_kb', 6144));

        return [
            'intakeType' => ['required', 'in:undamaged,damaged'],
            'labelImages' => ['required', 'array', 'min:1'],
            'labelImages.*' => ['image', 'max:' . $maxImageKb],
            'productImages' => ['nullable', 'array'],
            'productImages.*' => ['image', 'max:' . $maxImageKb],
            'damageImages' => $this->intakeType === 'damaged'
                ? ['required', 'array', 'min:1']
                : ['nullable', 'array'],
            'damageImages.*' => ['image', 'max:' . $maxImageKb],
            'manualReference' => ['nullable', 'string', 'max:120'],
            'operatorBarcode' => ['nullable', 'string', 'max:32'],
            'warehouseNote' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'labelImages' => 'etiket görselleri',
            'labelImages.*' => 'etiket görseli',
            'productImages.*' => 'ürün kanıt görseli',
            'damageImages' => 'hasar görselleri',
            'damageImages.*' => 'hasar görseli',
            'manualReference' => 'ek referans',
            'operatorBarcode' => 'ürün barkodu',
            'warehouseNote' => 'depo notu',
        ];
    }

    public function saveIntake(ReturnIntakeService $intakeService): void
    {
        $validated = $this->validate();

        if ($this->bulkMode) {
            $labelCount = count($validated['labelImages'] ?? []);
            if (count($validated['productImages'] ?? []) > 0 || count($validated['damageImages'] ?? []) > 0) {
                $this->addError('bulkMode', 'Toplu modda sadece etiket fotoğrafları yükleyebilirsiniz. Her fotoğraf ayrı bir kargo paketi sayılacaktır.');
                $this->messageType = 'error';
                return;
            }

            foreach ($validated['labelImages'] as $image) {
                $item = $intakeService->create(Auth::user(), [
                    'intake_type' => $validated['intakeType'],
                    'label_images' => [$image],
                ]);
                // Toplu modda sayfa donmasın diye arka planda (queue) dispatch ediyoruz
                AnalyzeReturnIntakeItemJob::dispatch($item->id);
            }

            $this->message = "{$labelCount} adet iade arkaplana gönderildi. Sırayla eşleştirilip listeye düşecekler.";
            $this->messageType = 'success';
            $this->lastAnalysisResult = null;
            $this->lastCreatedItemId = null;

            $this->reset([
                'labelImages',
                'productImages',
                'damageImages',
                'manualReference',
                'operatorBarcode',
                'warehouseNote',
            ]);

            if (!$this->continuousMode) {
                $this->reset('bulkMode');
            }
            return;
        }

        /** @var ReturnIntakeItem $item */
        $item = $intakeService->create(Auth::user(), [
            'intake_type' => $validated['intakeType'],
            'manual_reference' => $validated['manualReference'] ?? null,
            'operator_barcode' => $validated['operatorBarcode'] ?? null,
            'warehouse_note' => $validated['warehouseNote'] ?? null,
            'label_images' => $validated['labelImages'] ?? [],
            'product_images' => $validated['productImages'] ?? [],
            'damage_images' => $validated['damageImages'] ?? [],
        ]);

        // Analizi her zaman senkron çalıştır — Livewire ortamında
        // dispatchAfterResponse güvenilmez, database queue ise worker gerektirir.
        // Senkron çalıştırarak depocuya anlık geri bildirim veriyoruz.
        try {
            AnalyzeReturnIntakeItemJob::dispatchSync($item->id);
            $item->refresh();

            $this->lastAnalysisResult = [
                'status' => $item->intake_status,
                'tracking' => $item->detected_tracking_number,
                'order' => $item->detected_order_number,
                'customer' => $item->detected_customer_name,
                'cargo' => $item->cargo_provider,
                'condition' => $item->conditionLabel(),
                'matched' => $item->channel_order_id || $item->channel_claim_id,
                'confidence' => $item->matching_confidence,
                'suggestion' => $item->suggestedDecisionLabel(),
                'error' => $item->last_error,
            ];

            if ($item->channel_order_id || $item->channel_claim_id) {
                $this->message = 'Kayıt oluşturuldu ve sipariş eşleştirildi.';
                $this->messageType = 'success';
            } elseif ($item->intake_status === 'failed') {
                $this->message = 'Kayıt oluşturuldu fakat analiz başarısız oldu: ' . ($item->last_error ?: 'Bilinmeyen hata');
                $this->messageType = 'error';
            } else {
                $this->message = 'Kayıt oluşturuldu. Eşleşme bulunamadı — ofis ekibi manuel inceleyecek.';
                $this->messageType = 'success';
            }
        } catch (\Throwable $e) {
            $this->lastAnalysisResult = null;
            $this->message = 'İade kaydı oluşturuldu fakat analiz hatası: ' . $e->getMessage();
            $this->messageType = 'error';
        }

        $this->lastCreatedItemId = $item->id;

        $this->reset([
            'labelImages',
            'productImages',
            'damageImages',
            'manualReference',
            'operatorBarcode',
            'warehouseNote',
        ]);

        // Ardışık tarama modunda iade tipini koruyoruz
        if (!$this->continuousMode) {
            // normal modda varsayılana dön
        }
    }

    #[Computed]
    public function todayStats(): array
    {
        $base = ReturnIntakeItem::query()
            ->whereDate('arrived_at', today());

        return [
            'today' => (clone $base)->count(),
            'ready' => (clone $base)->where('intake_status', 'ready_for_decision')->count(),
            'review' => (clone $base)->whereIn('intake_status', ['needs_review', 'failed'])->count(),
            'decisioned' => (clone $base)->where('decision_status', '!=', 'pending')->count(),
        ];
    }

    #[Computed]
    public function recentItems()
    {
        return ReturnIntakeItem::query()
            ->with(['media', 'latestAnalysis'])
            ->where('submitted_by_user_id', Auth::id())
            ->latest('arrived_at')
            ->limit(10)
            ->get();
    }

    public function render(): View
    {
        $view = view('livewire.returns.return-intake', [
            'todayStats' => $this->todayStats,
            'recentItems' => $this->recentItems,
        ]);

        if ($this->embedded) {
            return $view;
        }

        return $view->layout('layouts.app', [
            'title' => 'İade Kabul',
        ]);
    }
}
