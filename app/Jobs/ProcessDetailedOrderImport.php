<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\DetailedOrderImportService;
use Illuminate\Support\Facades\File;

class ProcessDetailedOrderImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 dakika maksimum
    public $tries = 2;
    public string $filePath;

    /**
     * Create a new job instance.
     */
    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Execute the job.
     */
    public function handle(DetailedOrderImportService $importService): void
    {
        if (!File::exists($this->filePath)) {
            return;
        }

        $importService->importDetailedOrders($this->filePath);

        // İşlem bitince dosyayı temizle
        if (File::exists($this->filePath)) {
            File::delete($this->filePath);
        }
    }
}
