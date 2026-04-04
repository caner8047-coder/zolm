<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\DetailedOrderImportService;
use App\Models\MarketplaceStore;
use Illuminate\Support\Facades\File;

class ProcessDetailedOrderImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 dakika maksimum
    public $tries = 2;
    public string $filePath;
    public ?int $storeId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $filePath, ?int $storeId = null)
    {
        $this->filePath = $filePath;
        $this->storeId = $storeId;
    }

    /**
     * Execute the job.
     */
    public function handle(DetailedOrderImportService $importService): void
    {
        if (!File::exists($this->filePath)) {
            return;
        }

        $store = $this->storeId ? MarketplaceStore::query()->find($this->storeId) : null;

        $importService->importDetailedOrders($this->filePath, $store);

        // İşlem bitince dosyayı temizle
        if (File::exists($this->filePath)) {
            File::delete($this->filePath);
        }
    }
}
