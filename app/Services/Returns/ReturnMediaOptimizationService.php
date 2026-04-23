<?php

namespace App\Services\Returns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReturnMediaOptimizationService
{
    /**
     * @return array<string, mixed>
     */
    public function store(UploadedFile $file, string $disk, string $directory): array
    {
        $realPath = $file->getRealPath();
        $originalContents = $realPath && is_file($realPath)
            ? file_get_contents($realPath)
            : false;

        if (!is_string($originalContents) || $originalContents === '') {
            return $this->storeOriginal($file, $disk, $directory);
        }

        $checksum = hash('sha256', $originalContents);
        $image = @imagecreatefromstring($originalContents);

        if (!$image) {
            return $this->storeOriginal($file, $disk, $directory, $checksum);
        }

        $image = $this->normalizeOrientation($image, $file);

        $maxDimension = max(1200, (int) config('returns.max_dimension', 2200));
        $jpegQuality = min(95, max(50, (int) config('returns.jpeg_quality', 82)));
        $thumbDimension = max(240, (int) config('returns.thumbnail_dimension', 480));

        [$sourceWidth, $sourceHeight] = [imagesx($image), imagesy($image)];
        $optimizedImage = $this->resizeToFit($image, $maxDimension);

        if ($optimizedImage !== $image) {
            imagedestroy($image);
        }

        $optimizedBinary = $this->encodeJpeg($optimizedImage, $jpegQuality);

        if ($optimizedBinary === null) {
            imagedestroy($optimizedImage);
            return $this->storeOriginal($file, $disk, $directory, $checksum);
        }

        $path = trim($directory, '/') . '/' . Str::uuid()->toString() . '.jpg';
        Storage::disk($disk)->put($path, $optimizedBinary);

        $thumbnailImage = $this->resizeToFit($optimizedImage, $thumbDimension);
        $thumbnailBinary = $this->encodeJpeg($thumbnailImage, min($jpegQuality, 76));
        $thumbnailPath = null;
        $thumbnailSize = null;

        if ($thumbnailBinary !== null) {
            $thumbnailPath = trim($directory, '/') . '/thumb-' . Str::uuid()->toString() . '.jpg';
            Storage::disk($disk)->put($thumbnailPath, $thumbnailBinary);
            $thumbnailSize = strlen($thumbnailBinary);
        }

        if ($thumbnailImage !== $optimizedImage) {
            imagedestroy($thumbnailImage);
        }

        $width = imagesx($optimizedImage);
        $height = imagesy($optimizedImage);
        imagedestroy($optimizedImage);

        return [
            'disk' => $disk,
            'path' => $path,
            'thumbnail_path' => $thumbnailPath,
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => strlen($optimizedBinary),
            'original_size_bytes' => strlen($originalContents),
            'thumbnail_size_bytes' => $thumbnailSize,
            'width' => $width,
            'height' => $height,
            'checksum' => $checksum,
            'optimized_at' => now(),
            'storage_meta' => [
                'optimized' => [
                    'source_width' => $sourceWidth,
                    'source_height' => $sourceHeight,
                    'quality' => $jpegQuality,
                    'max_dimension' => $maxDimension,
                    'space_saved_bytes' => max(0, strlen($originalContents) - strlen($optimizedBinary)),
                ],
                'thumbnail' => [
                    'dimension' => $thumbDimension,
                    'quality' => min($jpegQuality, 76),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function storeBinary(
        string $contents,
        ?string $mimeType,
        string $disk,
        string $directory,
        ?string $extension = null,
        ?string $checksum = null,
    ): array {
        if ($contents === '') {
            throw new \RuntimeException('Bos medya icerigi saklanamaz.');
        }

        $checksum = $checksum ?: hash('sha256', $contents);
        $image = @imagecreatefromstring($contents);

        if (!$image) {
            $resolvedExtension = strtolower((string) ($extension ?: $this->extensionFromMimeType($mimeType) ?: 'bin'));
            $path = trim($directory, '/') . '/' . Str::uuid()->toString() . '.' . $resolvedExtension;
            Storage::disk($disk)->put($path, $contents);

            return [
                'disk' => $disk,
                'path' => $path,
                'thumbnail_path' => null,
                'mime_type' => $mimeType,
                'extension' => $resolvedExtension,
                'size_bytes' => strlen($contents),
                'original_size_bytes' => strlen($contents),
                'thumbnail_size_bytes' => null,
                'width' => null,
                'height' => null,
                'checksum' => $checksum,
                'optimized_at' => null,
                'storage_meta' => [
                    'optimized' => false,
                ],
            ];
        }

        $maxDimension = max(1200, (int) config('returns.max_dimension', 2200));
        $jpegQuality = min(95, max(50, (int) config('returns.jpeg_quality', 82)));
        $thumbDimension = max(240, (int) config('returns.thumbnail_dimension', 480));

        [$sourceWidth, $sourceHeight] = [imagesx($image), imagesy($image)];
        $optimizedImage = $this->resizeToFit($image, $maxDimension);

        if ($optimizedImage !== $image) {
            imagedestroy($image);
        }

        $optimizedBinary = $this->encodeJpeg($optimizedImage, $jpegQuality);

        if ($optimizedBinary === null) {
            imagedestroy($optimizedImage);

            $resolvedExtension = strtolower((string) ($extension ?: $this->extensionFromMimeType($mimeType) ?: 'bin'));
            $path = trim($directory, '/') . '/' . Str::uuid()->toString() . '.' . $resolvedExtension;
            Storage::disk($disk)->put($path, $contents);

            return [
                'disk' => $disk,
                'path' => $path,
                'thumbnail_path' => null,
                'mime_type' => $mimeType,
                'extension' => $resolvedExtension,
                'size_bytes' => strlen($contents),
                'original_size_bytes' => strlen($contents),
                'thumbnail_size_bytes' => null,
                'width' => $sourceWidth,
                'height' => $sourceHeight,
                'checksum' => $checksum,
                'optimized_at' => null,
                'storage_meta' => [
                    'optimized' => false,
                ],
            ];
        }

        $path = trim($directory, '/') . '/' . Str::uuid()->toString() . '.jpg';
        Storage::disk($disk)->put($path, $optimizedBinary);

        $thumbnailImage = $this->resizeToFit($optimizedImage, $thumbDimension);
        $thumbnailBinary = $this->encodeJpeg($thumbnailImage, min($jpegQuality, 76));
        $thumbnailPath = null;
        $thumbnailSize = null;

        if ($thumbnailBinary !== null) {
            $thumbnailPath = trim($directory, '/') . '/thumb-' . Str::uuid()->toString() . '.jpg';
            Storage::disk($disk)->put($thumbnailPath, $thumbnailBinary);
            $thumbnailSize = strlen($thumbnailBinary);
        }

        if ($thumbnailImage !== $optimizedImage) {
            imagedestroy($thumbnailImage);
        }

        $width = imagesx($optimizedImage);
        $height = imagesy($optimizedImage);
        imagedestroy($optimizedImage);

        return [
            'disk' => $disk,
            'path' => $path,
            'thumbnail_path' => $thumbnailPath,
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => strlen($optimizedBinary),
            'original_size_bytes' => strlen($contents),
            'thumbnail_size_bytes' => $thumbnailSize,
            'width' => $width,
            'height' => $height,
            'checksum' => $checksum,
            'optimized_at' => now(),
            'storage_meta' => [
                'optimized' => [
                    'source_width' => $sourceWidth,
                    'source_height' => $sourceHeight,
                    'quality' => $jpegQuality,
                    'max_dimension' => $maxDimension,
                    'space_saved_bytes' => max(0, strlen($contents) - strlen($optimizedBinary)),
                ],
                'thumbnail' => [
                    'dimension' => $thumbDimension,
                    'quality' => min($jpegQuality, 76),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function storeOriginal(UploadedFile $file, string $disk, string $directory, ?string $checksum = null): array
    {
        $extension = strtolower((string) ($file->guessExtension() ?: $file->extension() ?: 'jpg'));
        $path = $file->storeAs(trim($directory, '/'), Str::uuid()->toString() . '.' . $extension, $disk);
        [$width, $height] = $this->resolveDimensions($file);
        $size = $file->getSize();

        return [
            'disk' => $disk,
            'path' => $path,
            'thumbnail_path' => null,
            'mime_type' => $file->getMimeType(),
            'extension' => $extension,
            'size_bytes' => $size,
            'original_size_bytes' => $size,
            'thumbnail_size_bytes' => null,
            'width' => $width,
            'height' => $height,
            'checksum' => $checksum ?: $this->checksum($file),
            'optimized_at' => null,
            'storage_meta' => [
                'optimized' => false,
            ],
        ];
    }

    protected function normalizeOrientation(\GdImage $image, UploadedFile $file): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        $realPath = $file->getRealPath();

        if (!$realPath || !is_file($realPath)) {
            return $image;
        }

        try {
            $exif = @exif_read_data($realPath);
        } catch (\Throwable) {
            $exif = false;
        }

        $orientation = (int) ($exif['Orientation'] ?? 1);

        return match ($orientation) {
            3 => imagerotate($image, 180, 0) ?: $image,
            6 => imagerotate($image, -90, 0) ?: $image,
            8 => imagerotate($image, 90, 0) ?: $image,
            default => $image,
        };
    }

    protected function resizeToFit(\GdImage $image, int $maxDimension): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $largestSide = max($width, $height);

        if ($largestSide <= $maxDimension) {
            return $image;
        }

        $ratio = $maxDimension / $largestSide;
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if (!$canvas) {
            return $image;
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $canvas;
    }

    protected function encodeJpeg(\GdImage $image, int $quality): ?string
    {
        ob_start();
        $encoded = imagejpeg($image, null, $quality);
        $binary = ob_get_clean();

        if (!$encoded || !is_string($binary) || $binary === '') {
            return null;
        }

        return $binary;
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

    protected function extensionFromMimeType(?string $mimeType): ?string
    {
        return match (trim((string) $mimeType)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => null,
        };
    }
}
