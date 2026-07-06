<?php

namespace App\Livewire\Ads;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\AdAccount;
use App\Models\AdChannel;
use App\Models\AdCampaign;
use App\Models\AdImportBatch;
use App\Models\AdImportRow;
use App\Enums\AdImportType;
use App\Enums\AdImportStatus;
use App\Services\Ads\AdImportService;
use App\Services\Ads\AdCampaignMatcher;

class AdImportCenter extends Component
{
    use WithFileUploads;

    // ─── Form State ─────────────────────────────────────────────
    public $file;
    public string $importType = '';
    public ?int $selectedAccountId = null;
    public string $reportPeriodStart = '';
    public string $reportPeriodEnd = '';
    public string $exportedAt = '';
    public ?int $selectedCampaignId = null;

    // ─── Yeni Reklam Hesabı Modalı ──────────────────────────────
    public bool $showNewAccountModal = false;
    public string $newAccountName = '';
    public string $newAccountExternalId = '';

    // ─── Durum ──────────────────────────────────────────────────
    public bool $isUploading = false;
    public bool $isParsing = false;
    public bool $isImporting = false;
    public string $statusMessage = '';
    public bool $showPreview = false;

    // ─── Veriler ────────────────────────────────────────────────
    public array $accounts = [];
    public array $channels = [];
    public array $importTypes = [];
    public array $previewRows = [];
    public array $importHistory = [];
    public ?int $currentBatchId = null;
    public array $batchStats = ['total' => 0, 'valid' => 0, 'invalid' => 0];

    // ─── Kampanya Eşleştirme ────────────────────────────────────
    public array $campaignCandidates = [];
    public bool $showCampaignSelection = false;

    protected $queryString = [
        'importType' => ['except' => ''],
        'selectedAccountId' => ['except' => null],
    ];

    public function mount()
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $userId = auth()->id();

        $this->accounts = AdAccount::where('user_id', $userId)
            ->where('is_active', true)
            ->get()
            ->toArray();

        $this->channels = AdChannel::where('is_active', true)
            ->get()
            ->pluck('name', 'code')
            ->toArray();

        $this->importTypes = collect(AdImportType::cases())
            ->map(fn($t) => ['value' => $t->value, 'label' => $t->label()])
            ->toArray();

        $this->loadImportHistory();
    }

    public function updatedSelectedAccountId(): void
    {
        $this->selectedCampaignId = null;
        $this->campaignCandidates = [];
    }

    public function updatedImportType(): void
    {
        $this->selectedCampaignId = null;
        $this->campaignCandidates = [];
    }

    // ─── Yeni Reklam Hesabı ────────────────────────────────────

    public function openNewAccountModal(): void
    {
        $this->showNewAccountModal = true;
        $this->newAccountName = '';
        $this->newAccountExternalId = '';
    }

    public function closeNewAccountModal(): void
    {
        $this->showNewAccountModal = false;
    }

    public function createNewAccount(): void
    {
        $this->validate([
            'newAccountName' => 'required|string|max:255',
            'newAccountExternalId' => 'nullable|string|max:100',
        ]);

        $account = AdAccount::create([
            'user_id' => auth()->id(),
            'marketplace' => 'trendyol',
            'account_name' => $this->newAccountName,
            'external_account_id' => $this->newAccountExternalId ?: null,
            'currency_code' => 'TRY',
            'timezone' => 'Europe/Istanbul',
            'is_active' => true,
        ]);

        $this->accounts = AdAccount::where('user_id', auth()->id())
            ->where('is_active', true)
            ->get()
            ->toArray();

        $this->selectedAccountId = $account->id;
        $this->showNewAccountModal = false;
        $this->statusMessage = "Reklam hesabı başarıyla oluşturuldu: {$account->account_name}";
    }

    // ─── Dosya Yükleme ─────────────────────────────────────────

    public function updatedFile(): void
    {
        $this->statusMessage = '';
    }

    public function uploadAndPreview(): void
    {
        $this->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:51200',
            'importType' => 'required|string',
            'selectedAccountId' => 'required|integer',
            'reportPeriodStart' => 'required|date',
            'reportPeriodEnd' => 'required|date|after_or_equal:reportPeriodStart',
        ]);

        $importType = AdImportType::from($this->importType);

        // Kampanya bağlamı zorunluluğu kontrolü
        if ($importType->requiresCampaignContext() && !$this->selectedCampaignId) {
            $this->validate([
                'selectedCampaignId' => 'required|integer',
            ]);
        }

        $this->isUploading = true;

        try {
            $path = $this->file->store('ad-imports');
            $absolutePath = Storage::path($path);
            $fileHash = hash_file('sha256', $absolutePath);

            // Dosya hash duplicate kontrolü
            $existingBatch = AdImportBatch::where('user_id', auth()->id())
                ->where('file_hash', $fileHash)
                ->where('status', 'imported')
                ->first();

            if ($existingBatch) {
                $this->statusMessage = 'Bu dosya daha önce başarıyla içe aktarıldı.';
                $this->isUploading = false;
                return;
            }

            // Başarısız batch varsa yeniden deneme seçeneği
            $failedBatch = AdImportBatch::where('user_id', auth()->id())
                ->where('file_hash', $fileHash)
                ->where('status', 'failed')
                ->first();

            if ($failedBatch) {
                $this->currentBatchId = $failedBatch->id;
                $this->statusMessage = 'Bu dosya daha önce başarısız olmuş. Yeniden denenecek.';
            }

            // Geçici batch oluştur
            if (!$this->currentBatchId) {
                $batch = AdImportBatch::create([
                    'user_id' => auth()->id(),
                    'ad_account_id' => $this->selectedAccountId,
                    'channel_code' => $importType->channelCode()->value,
                    'import_type' => $this->importType,
                    'status' => AdImportStatus::Uploaded->value,
                    'report_period_start' => $this->reportPeriodStart,
                    'report_period_end' => $this->reportPeriodEnd,
                    'exported_at' => $this->exportedAt ? now()->parse($this->exportedAt) : null,
                    'uploaded_by_user_id' => auth()->id(),
                    'source_filename' => $this->file->getClientOriginalName(),
                    'storage_path' => $path,
                    'file_hash' => $fileHash,
                    'source_fingerprint' => null, // parse sonrası oluşacak
                    'campaign_id_context' => $this->selectedCampaignId,
                ]);
                $this->currentBatchId = $batch->id;
            }

            // Parse job'ını başlat
            $importService = app(AdImportService::class);
            $importService->parseImportBatch($this->currentBatchId, $absolutePath);

            $this->loadPreview();
            $this->statusMessage = 'Dosya başarıyla işlendi. Önizlemeyi kontrol edin.';

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AdImport upload error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            $this->statusMessage = 'Hata: ' . $e->getMessage();
        } finally {
            $this->isUploading = false;
        }
    }

    // ─── Önizleme ──────────────────────────────────────────────

    public function loadPreview(): void
    {
        if (!$this->currentBatchId) return;

        $rows = AdImportRow::where('batch_id', $this->currentBatchId)
            ->orderBy('row_number')
            ->take(50)
            ->get();

        $this->previewRows = $rows->map(fn($row) => [
            'row_number' => $row->row_number,
            'raw' => $row->raw_payload,
            'normalized' => $row->normalized_payload,
            'errors' => $row->validation_errors,
            'status' => $row->status,
        ])->toArray();

        $stats = AdImportRow::where('batch_id', $this->currentBatchId)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN status = "valid" THEN 1 ELSE 0 END) as valid, SUM(CASE WHEN status = "error" THEN 1 ELSE 0 END) as invalid')
            ->first();

        $this->batchStats = [
            'total' => $stats->total ?? 0,
            'valid' => $stats->valid ?? 0,
            'invalid' => $stats->invalid ?? 0,
        ];

        $this->showPreview = true;
    }

    // ─── Import Onay ───────────────────────────────────────────

    public function confirmImport(): void
    {
        if (!$this->currentBatchId) return;

        $this->isImporting = true;

        try {
            $importService = app(AdImportService::class);
            $importService->executeImport($this->currentBatchId);

            $this->statusMessage = 'İçe aktarma başarıyla tamamlandı!';
            $this->showPreview = false;
            $this->loadImportHistory();
            $this->resetForm();

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AdImport execute error', [
                'error' => $e->getMessage(),
                'batch_id' => $this->currentBatchId,
            ]);
            $this->statusMessage = 'Hata: ' . $e->getMessage();
        } finally {
            $this->isImporting = false;
        }
    }

    public function cancelImport(): void
    {
        if ($this->currentBatchId) {
            AdImportBatch::where('id', $this->currentBatchId)
                ->update(['status' => AdImportStatus::Cancelled->value]);
        }

        $this->resetForm();
        $this->statusMessage = 'Import iptal edildi.';
    }

    // ─── Yardımcılar ───────────────────────────────────────────

    public function resetForm(): void
    {
        $this->file = null;
        $this->importType = '';
        $this->selectedAccountId = null;
        $this->reportPeriodStart = '';
        $this->reportPeriodEnd = '';
        $this->exportedAt = '';
        $this->selectedCampaignId = null;
        $this->showPreview = false;
        $this->previewRows = [];
        $this->batchStats = ['total' => 0, 'valid' => 0, 'invalid' => 0];
        $this->currentBatchId = null;
    }

    public function loadImportHistory(): void
    {
        $this->importHistory = AdImportBatch::where('user_id', auth()->id())
            ->latest()
            ->take(20)
            ->get()
            ->toArray();
    }

    public function render()
    {
        return view('livewire.ads.ad-import-center')
            ->layout('layouts.app', ['title' => 'Reklam Zekâsı — Veri İçe Aktarma']);
    }
}
