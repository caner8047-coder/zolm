<?php

namespace App\Modules\Hr\Core\Services;

use App\Models\HrFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class HrFileService
{
    private string $disk;
    private int $maxSize;
    private array $allowedMimes;

    public function __construct()
    {
        $this->disk = config('hr.file.disk', 'private');
        $this->maxSize = config('hr.file.max_size_bytes', 20 * 1024 * 1024);
        $this->allowedMimes = config('hr.file.allowed_mimes', []);
    }

    public function upload(UploadedFile $file, string $category, ?int $subjectId = null, ?string $subjectType = null): HrFile
    {
        $this->validateFile($file);

        $tenantId = app(TenantContext::class)->getId();
        $filename = $this->generateFilename($file);
        $path = "hr/{$tenantId}/{$category}/{$filename}";

        $storedPath = $file->storeAs(dirname($path), basename($path), $this->disk);

        return HrFile::create([
            'legal_entity_id' => $tenantId,
            'uploader_id' => auth()->id(),
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'category' => $category,
            'original_name' => $file->getClientOriginalName(),
            'disk_path' => $storedPath,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
        ]);
    }

    public function getSignedUrl(HrFile $file): string
    {
        return Storage::disk($this->disk)->temporaryUrl(
            $file->disk_path,
            now()->addMinutes(15)
        );
    }

    public function download(HrFile $file): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return Storage::disk($this->disk)->download(
            $file->disk_path,
            $file->original_name
        );
    }

    public function delete(HrFile $file): bool
    {
        Storage::disk($this->disk)->delete($file->disk_path);
        return $file->delete();
    }

    private function validateFile(UploadedFile $file): void
    {
        if ($file->getSize() > $this->maxSize) {
            abort(422, 'Dosya boyutu izin verilen maksimumu aşıyor.');
        }

        $mimeType = $file->getMimeType();
        if (!empty($this->allowedMimes) && !in_array($mimeType, $this->allowedMimes)) {
            abort(422, 'Bu dosya türü izin verilmiyor.');
        }

        // MIME doğrulaması: finfo ile gerçek içerik kontrolü
        $realMimeType = mime_content_type($file->getRealPath());
        if ($realMimeType && $mimeType !== $realMimeType) {
            abort(422, 'Dosya içeriği belirtilen türle uyuşmuyor.');
        }
    }

    private function generateFilename(UploadedFile $file): string
    {
        return time() . '_' . bin2hex(random_bytes(8)) . '.' . $file->getClientOriginalExtension();
    }
}
