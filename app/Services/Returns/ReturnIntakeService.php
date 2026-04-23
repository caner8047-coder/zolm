<?php

namespace App\Services\Returns;

use App\Models\ReturnIntakeBatch;
use App\Models\ReturnIntakeItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ReturnIntakeService
{
    public function __construct(
        protected ReturnMediaOptimizationService $mediaOptimizationService,
        protected string $disk = '',
        protected string $basePath = '',
    ) {
        $this->disk = $this->disk !== '' ? $this->disk : (string) config('returns.storage_disk', 'public');
        $this->basePath = $this->basePath !== '' ? trim($this->basePath, '/') : trim((string) config('returns.storage_path', 'returns'), '/');
    }

    /**
     * @param  array{intake_type: string, manual_reference?: string|null, operator_barcode?: string|null, warehouse_note?: string|null, label_images: array<int, UploadedFile>, product_images?: array<int, UploadedFile>, damage_images?: array<int, UploadedFile>}  $payload
     */
    public function create(User $user, array $payload): ReturnIntakeItem
    {
        return DB::transaction(function () use ($user, $payload) {
            $intakeType = (string) ($payload['intake_type'] ?? 'undamaged');
            $operatorBarcode = $this->normalizeBarcode((string) ($payload['operator_barcode'] ?? ''));

            $batch = ReturnIntakeBatch::create([
                'user_id' => $user->id,
                'source' => 'zolm_mobile',
                'intake_mode' => $intakeType,
                'status' => 'submitted',
                'captured_at' => now(),
            ]);

            $item = ReturnIntakeItem::create([
                'batch_id' => $batch->id,
                'submitted_by_user_id' => $user->id,
                'intake_type' => $intakeType,
                'intake_status' => 'queued',
                'condition_status' => $intakeType === 'damaged' ? 'damaged' : 'undamaged',
                'product_verification_status' => 'unverified',
                'decision_status' => 'pending',
                'manual_reference' => $this->clean((string) ($payload['manual_reference'] ?? '')) ?: null,
                'operator_barcode' => $operatorBarcode ?: null,
                'warehouse_note' => $this->clean((string) ($payload['warehouse_note'] ?? '')) ?: null,
                'arrived_at' => now(),
                'raw_summary_json' => [
                    'source' => 'zolm_mobile',
                    'operator' => $user->name,
                    'operator_barcode' => $operatorBarcode ?: null,
                ],
            ]);

            $this->storeMediaGroup($item, 'label', $payload['label_images'] ?? []);
            $this->storeMediaGroup($item, 'product', $payload['product_images'] ?? []);
            $this->storeMediaGroup($item, 'damage', $payload['damage_images'] ?? []);

            return $item->fresh(['batch', 'media']);
        });
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    protected function storeMediaGroup(ReturnIntakeItem $item, string $kind, array $files): void
    {
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $directory = sprintf('%s/%s/%s/%s', $this->basePath, now()->format('Y'), now()->format('m'), $kind);
            $stored = $this->mediaOptimizationService->store($file, $this->disk, $directory);

            $item->media()->create(array_merge([
                'kind' => $kind,
                'captured_at' => now(),
            ], $stored));
        }
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    protected function resolveDimensions(UploadedFile $file): array
    {
        $realPath = $file->getRealPath();

        if (!$realPath) {
            return [null, null];
        }

        $dimensions = @getimagesize($realPath);

        if (!is_array($dimensions)) {
            return [null, null];
        }

        return [
            isset($dimensions[0]) ? (int) $dimensions[0] : null,
            isset($dimensions[1]) ? (int) $dimensions[1] : null,
        ];
    }

    protected function checksum(UploadedFile $file): ?string
    {
        $realPath = $file->getRealPath();

        if (!$realPath || !is_file($realPath)) {
            return null;
        }

        return hash_file('sha256', $realPath) ?: null;
    }

    protected function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?: '');
    }

    protected function normalizeBarcode(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?: '';
    }
}
