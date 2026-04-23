<?php

namespace App\Services\Returns;

use App\Models\ReturnIntakeItem;
use App\Models\ReturnIntakeMedia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReturnVisionService
{
    /**
     * @return array<string, mixed>
     */
    public function analyze(ReturnIntakeItem $item): array
    {
        Log::info('[ReturnVision] Analiz başladı', [
            'item_id' => $item->id,
            'media_count' => $item->media->count(),
            'intake_type' => $item->intake_type,
        ]);

        $localAnalysis = $this->analyzeWithLocalOcr($item);
        $config = $this->resolveAiConfig();

        if ($config === null) {
            Log::warning('[ReturnVision] AI yapılandırılmamış', ['item_id' => $item->id]);

            if ($localAnalysis !== null) {
                return $localAnalysis;
            }

            return $this->fallbackAnalysis($item, 'AI OCR yapılandırılmamış ve yerel OCR kullanılamadı.');
        }

        $aiResult = $config['provider'] === 'openai'
            ? $this->analyzeWithOpenAi($item, $config)
            : $this->analyzeWithGemini($item, $config);

        if (($aiResult['success'] ?? false) === true) {
            Log::info('[ReturnVision] AI başarılı', [
                'item_id' => $item->id,
                'provider' => $config['provider'],
                'tracking' => data_get($aiResult, 'analysis.ocr.tracking_number'),
                'confidence' => data_get($aiResult, 'analysis.confidence'),
            ]);

            return $this->mergeAnalyses(
                $aiResult['analysis'],
                $localAnalysis,
            );
        }

        $errorMessage = (string) ($aiResult['error'] ?? 'AI OCR başarısız oldu.');
        Log::warning('[ReturnVision] AI başarısız', [
            'item_id' => $item->id,
            'provider' => $config['provider'],
            'error' => $errorMessage,
        ]);

        if ($localAnalysis !== null) {
            return $this->decorateLocalFallback($localAnalysis, $errorMessage);
        }

        return $this->fallbackAnalysis(
            $item,
            $this->humanizeFailureMessage($errorMessage),
            ['ai_error' => $errorMessage]
        );
    }

    /**
     * @param  array{provider: string|null, model: string, api_key: string}  $config
     * @return array<string, mixed>
     */
    protected function analyzeWithGemini(ReturnIntakeItem $item, array $config): array
    {
        $parts = [
            ['text' => $this->promptFor($item)],
        ];

        foreach ($item->media as $media) {
            try {
                $contents = Storage::disk($media->disk)->get($media->path);
            } catch (\Throwable) {
                continue;
            }

            $parts[] = [
                'inline_data' => [
                    'mime_type' => $media->mime_type ?: 'image/jpeg',
                    'data' => base64_encode($contents),
                ],
            ];
        }

        if (count($parts) === 1) {
            return [
                'success' => false,
                'error' => 'Analiz edilecek görsel bulunamadı.',
                'raw' => [],
            ];
        }

        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            $config['model'],
            $config['api_key']
        );

        $response = Http::timeout((int) config('ai.timeout', 90))
            ->acceptJson()
            ->post($url, [
                'contents' => [[
                    'role' => 'user',
                    'parts' => $parts,
                ]],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'response_mime_type' => 'application/json',
                    'response_schema' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'tracking_number' => ['type' => 'STRING'],
                            'order_number' => ['type' => 'STRING'],
                            'product_barcode' => ['type' => 'STRING'],
                            'customer_name' => ['type' => 'STRING'],
                            'cargo_provider' => ['type' => 'STRING'],
                            'condition_status' => [
                                'type' => 'STRING',
                                'enum' => ['undamaged', 'damaged', 'unknown'],
                            ],
                            'issue_tags' => [
                                'type' => 'ARRAY',
                                'items' => ['type' => 'STRING'],
                            ],
                            'summary' => ['type' => 'STRING'],
                            'confidence' => ['type' => 'NUMBER'],
                            'raw_text' => ['type' => 'STRING'],
                        ],
                    ],
                ],
            ]);

        if (!$response->successful()) {
            return [
                'success' => false,
                'error' => 'Gemini hatası: ' . $response->body(),
                'raw' => $response->json() ?: [],
            ];
        }

        $decoded = $response->json();
        $text = data_get($decoded, 'candidates.0.content.parts.0.text');
        $payload = is_string($text) ? json_decode($text, true) : null;

        if (!is_array($payload)) {
            return [
                'success' => false,
                'error' => 'Gemini yanıtı çözümlenemedi.',
                'raw' => $decoded,
            ];
        }

        return [
            'success' => true,
            'analysis' => [
                'provider' => 'gemini',
                'model' => $config['model'],
                'prompt_version' => 'returns_v1',
                'confidence' => (float) ($payload['confidence'] ?? 0),
                'ocr' => [
                    'tracking_number' => $this->cleanSignal($payload['tracking_number'] ?? null),
                    'order_number' => $this->cleanSignal($payload['order_number'] ?? null),
                    'product_barcode' => $this->cleanSignal($payload['product_barcode'] ?? null),
                    'customer_name' => $this->cleanSignal($payload['customer_name'] ?? null),
                    'cargo_provider' => $this->cleanSignal($payload['cargo_provider'] ?? null),
                    'raw_text' => trim((string) ($payload['raw_text'] ?? '')),
                ],
                'classification' => [
                    'condition_status' => $payload['condition_status'] ?? 'unknown',
                    'issue_tags' => array_values(array_filter(array_map('strval', $payload['issue_tags'] ?? []))),
                    'summary' => trim((string) ($payload['summary'] ?? '')),
                ],
                'raw_response_json' => $decoded,
            ],
        ];
    }

    /**
     * @param  array{provider: string|null, model: string, api_key: string}  $config
     * @return array<string, mixed>
     */
    protected function analyzeWithOpenAi(ReturnIntakeItem $item, array $config): array
    {
        $messages = [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $this->promptFor($item),
                    ],
                ],
            ],
        ];

        foreach ($item->media as $media) {
            try {
                $contents = Storage::disk($media->disk)->get($media->path);
            } catch (\Throwable) {
                continue;
            }

            $mime = $media->mime_type ?: 'image/jpeg';
            $base64 = base64_encode($contents);
            
            $messages[0]['content'][] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => "data:{$mime};base64,{$base64}",
                ],
            ];
        }

        if (count($messages[0]['content']) === 1) {
            return [
                'success' => false,
                'error' => 'Analiz edilecek görsel bulunamadı.',
                'raw' => [],
            ];
        }

        $response = Http::withToken($config['api_key'])
            ->timeout((int) config('ai.timeout', 90))
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $config['model'],
                'messages' => $messages,
                'temperature' => 0.1,
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'return_analysis',
                        'strict' => true,
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'tracking_number' => ['type' => 'string'],
                                'order_number' => ['type' => 'string'],
                                'product_barcode' => ['type' => 'string'],
                                'customer_name' => ['type' => 'string'],
                                'cargo_provider' => ['type' => 'string'],
                                'condition_status' => [
                                    'type' => 'string',
                                    'enum' => ['undamaged', 'damaged', 'unknown'],
                                ],
                                'issue_tags' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                ],
                                'summary' => ['type' => 'string'],
                                'confidence' => ['type' => 'number'],
                                'raw_text' => ['type' => 'string'],
                            ],
                            'required' => [
                                'tracking_number', 'order_number', 'product_barcode', 
                                'customer_name', 'cargo_provider', 'condition_status', 
                                'issue_tags', 'summary', 'confidence', 'raw_text'
                            ],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
            ]);

        if (!$response->successful()) {
            return [
                'success' => false,
                'error' => 'OpenAI hatası: ' . $response->body(),
                'raw' => $response->json() ?: [],
            ];
        }

        $decoded = $response->json();
        $text = data_get($decoded, 'choices.0.message.content');
        $payload = is_string($text) ? json_decode($text, true) : null;

        if (!is_array($payload)) {
            return [
                'success' => false,
                'error' => 'OpenAI yanıtı çözümlenemedi.',
                'raw' => $decoded,
            ];
        }

        return [
            'success' => true,
            'analysis' => [
                'provider' => 'openai',
                'model' => $config['model'],
                'prompt_version' => 'returns_v1',
                'confidence' => (float) ($payload['confidence'] ?? 0.0),
                'ocr' => [
                    'tracking_number' => $this->cleanSignal($payload['tracking_number'] ?? null),
                    'order_number' => $this->cleanSignal($payload['order_number'] ?? null),
                    'product_barcode' => $this->cleanSignal($payload['product_barcode'] ?? null),
                    'customer_name' => $this->cleanSignal($payload['customer_name'] ?? null),
                    'cargo_provider' => $this->cleanSignal($payload['cargo_provider'] ?? null),
                    'raw_text' => trim((string) ($payload['raw_text'] ?? '')),
                ],
                'classification' => [
                    'condition_status' => $payload['condition_status'] ?? 'unknown',
                    'issue_tags' => array_values(array_filter(array_map('strval', $payload['issue_tags'] ?? []))),
                    'summary' => trim((string) ($payload['summary'] ?? '')),
                ],
                'raw_response_json' => $decoded,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function analyzeWithLocalOcr(ReturnIntakeItem $item): ?array
    {
        $ocrPayload = $this->collectLocalOcrTexts($item);
        $texts = array_values(array_filter(array_map(
            fn ($text) => $this->normalizeOcrText((string) $text),
            $ocrPayload['texts'] ?? []
        )));

        if ($texts === []) {
            return null;
        }

        $rawText = implode("\n\n", array_values(array_unique($texts)));
        $signals = $this->extractSignalsFromRawText($rawText);

        return [
            'provider' => 'local_tesseract',
            'model' => 'tesseract-ocr',
            'prompt_version' => 'returns_v1_local',
            'confidence' => $this->estimateLocalConfidence($signals, $rawText),
            'ocr' => [
                'tracking_number' => $signals['tracking_number'],
                'order_number' => $signals['order_number'],
                'product_barcode' => $signals['product_barcode'],
                'customer_name' => null,
                'cargo_provider' => $signals['cargo_provider'],
                'raw_text' => $rawText,
            ],
            'classification' => [
                'condition_status' => $item->intake_type === 'damaged' ? 'damaged' : 'undamaged',
                'issue_tags' => $item->intake_type === 'damaged' ? ['damage_reported_by_operator'] : ['no_damage_visible'],
                'summary' => $this->buildLocalSummary($signals),
            ],
            'raw_response_json' => [
                'source' => 'local_ocr',
                'engine' => $ocrPayload['engine'] ?? 'tesseract',
                'variant_count' => count($texts),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function collectLocalOcrTexts(ReturnIntakeItem $item): array
    {
        $binary = $this->tesseractBinary();

        if ($binary === null) {
            return [];
        }

        $texts = [];
        $orderedMedia = $item->media
            ->sortBy(fn (ReturnIntakeMedia $media) => match ($media->kind) {
                'label' => 0,
                'product' => 1,
                'damage' => 2,
                default => 3,
            })
            ->values();

            foreach ($orderedMedia as $media) {
            try {
                $contents = Storage::disk($media->disk)->get($media->path);
            } catch (\Throwable) {
                continue;
            }

            foreach ($this->buildOcrVariantFiles($contents) as $variantPath) {
                try {
                    foreach ([6, 7, 11] as $psm) {
                        $text = $this->runTesseract($binary, $variantPath, $psm);

                        if (trim($text) !== '') {
                            $texts[] = $text;
                        }
                    }
                } finally {
                    @unlink($variantPath);
                }
            }
        }

        return [
            'engine' => 'tesseract',
            'texts' => $texts,
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function buildOcrVariantFiles(string $contents): array
    {
        $originalPath = $this->writeTempImage($contents, 'orig');
        $paths = [];

        if ($originalPath !== null) {
            $paths[] = $originalPath;
        }

        $paths = array_merge($paths, $this->writeEnhancedTempImages($contents));

        return array_values(array_unique(array_filter($paths)));
    }

    protected function writeTempImage(string $contents, string $suffix): ?string
    {
        $path = sys_get_temp_dir() . '/return-ocr-' . $suffix . '-' . Str::uuid()->toString() . '.png';

        return file_put_contents($path, $contents) !== false ? $path : null;
    }

    /**
     * @return array<int, string>
     */
    protected function writeEnhancedTempImages(string $contents): array
    {
        if (!class_exists(\Imagick::class)) {
            return [];
        }

        try {
            $image = new \Imagick();
            $image->readImageBlob($contents);
            $image->autoOrient();

            if ($image->getNumberImages() > 1) {
                $image = $image->coalesceImages();
                $image->setFirstIterator();
            }

            $paths = [];

            $variantSpecs = [
                ['angle' => 0, 'sidebar_focus' => false],
                ['angle' => 90, 'sidebar_focus' => false],
                ['angle' => -90, 'sidebar_focus' => false],
                ['angle' => 90, 'sidebar_focus' => true],
                ['angle' => -90, 'sidebar_focus' => true],
            ];

            foreach ($variantSpecs as $spec) {
                $variant = clone $image;

                if ($spec['sidebar_focus']) {
                    $this->applyLabelSidebarFocusCrop($variant);
                }

                if ($spec['angle'] !== 0) {
                    $variant->rotateImage(new \ImagickPixel('white'), $spec['angle']);
                }

                $width = $variant->getImageWidth();
                $height = $variant->getImageHeight();
                $largestSide = max($width, $height);

                if ($largestSide > 0 && $largestSide < 2200) {
                    $ratio = 2200 / $largestSide;
                    $variant->resizeImage(
                        max(1, (int) round($width * $ratio)),
                        max(1, (int) round($height * $ratio)),
                        \Imagick::FILTER_LANCZOS,
                        1
                    );
                }

                $variant->setImageBackgroundColor(new \ImagickPixel('white'));
                $variant->setImageColorspace(\Imagick::COLORSPACE_GRAY);
                $variant->setImageType(\Imagick::IMGTYPE_GRAYSCALE);
                $variant->normalizeImage();
                $variant->contrastImage(1);
                $variant->sharpenImage(0, 1.1);
                $variant->setImageFormat('png');

                $path = sys_get_temp_dir() . '/return-ocr-enhanced-' . $spec['angle'] . '-' . ($spec['sidebar_focus'] ? 'sidebar-' : 'full-') . Str::uuid()->toString() . '.png';
                $variant->writeImage($path);
                $paths[] = $path;
                $variant->clear();
                $variant->destroy();
            }

            $image->clear();
            $image->destroy();

            return $paths;
        } catch (\Throwable) {
            return [];
        }
    }

    protected function applyLabelSidebarFocusCrop(\Imagick $image): void
    {
        $width = $image->getImageWidth();
        $height = $image->getImageHeight();

        if ($width < 200 || $height < 200) {
            return;
        }

        $cropX = max(0, (int) round($width * 0.16));
        $cropY = max(0, (int) round($height * 0.24));
        $cropWidth = max(1, (int) round($width * 0.34));
        $cropHeight = max(1, (int) round($height * 0.50));

        $cropWidth = min($cropWidth, $width - $cropX);
        $cropHeight = min($cropHeight, $height - $cropY);

        if ($cropWidth < 50 || $cropHeight < 50) {
            return;
        }

        $image->cropImage($cropWidth, $cropHeight, $cropX, $cropY);
        $image->setImagePage(0, 0, 0, 0);
    }

    protected function runTesseract(string $binary, string $path, int $psm): string
    {
        $command = sprintf(
            '%s %s stdout --oem 1 --psm %d -l %s quiet 2>/dev/null',
            escapeshellcmd($binary),
            escapeshellarg($path),
            $psm,
            escapeshellarg('tur+eng')
        );

        $output = shell_exec($command);

        return is_string($output) ? $output : '';
    }

    protected function tesseractBinary(): ?string
    {
        $binary = trim((string) shell_exec('command -v tesseract 2>/dev/null'));

        return $binary !== '' ? $binary : null;
    }

    protected function buildLocalSummary(array $signals): string
    {
        if (($signals['tracking_number'] ?? null) && ($signals['cargo_provider'] ?? null)) {
            return 'Yerel OCR ile etiketten takip numarası ve kargo firması okundu.';
        }

        if ($signals['tracking_number'] ?? null) {
            return 'Yerel OCR ile etiketten takip numarası okundu.';
        }

        if ($signals['cargo_provider'] ?? null) {
            return 'Yerel OCR ile etiket kısmen okundu, kargo firması tespit edildi.';
        }

        return 'Yerel OCR ham metin üretti ancak güçlü eşleşme sinyali çıkaramadı.';
    }

    /**
     * @param  array<string, mixed>|null  $localAnalysis
     * @return array<string, mixed>
     */
    protected function mergeAnalyses(array $geminiAnalysis, ?array $localAnalysis): array
    {
        if ($localAnalysis === null) {
            return $geminiAnalysis;
        }

        $geminiOcr = $geminiAnalysis['ocr'] ?? [];
        $localOcr = $localAnalysis['ocr'] ?? [];
        $localRawText = trim((string) ($localOcr['raw_text'] ?? ''));
        $geminiRawText = trim((string) ($geminiOcr['raw_text'] ?? ''));

        $geminiAnalysis['ocr'] = [
            'tracking_number' => $geminiOcr['tracking_number'] ?: ($localOcr['tracking_number'] ?? null),
            'order_number' => $geminiOcr['order_number'] ?: ($localOcr['order_number'] ?? null),
            'product_barcode' => $geminiOcr['product_barcode'] ?: ($localOcr['product_barcode'] ?? null),
            'customer_name' => $geminiOcr['customer_name'] ?: ($localOcr['customer_name'] ?? null),
            'cargo_provider' => $geminiOcr['cargo_provider'] ?: ($localOcr['cargo_provider'] ?? null),
            'raw_text' => trim(implode("\n\n", array_filter([$geminiRawText, $localRawText]))),
        ];

        $geminiAnalysis['confidence'] = max(
            (float) ($geminiAnalysis['confidence'] ?? 0),
            (float) ($localAnalysis['confidence'] ?? 0)
        );

        $geminiAnalysis['raw_response_json'] = array_merge(
            is_array($geminiAnalysis['raw_response_json'] ?? null) ? $geminiAnalysis['raw_response_json'] : [],
            [
                'local_ocr' => [
                    'provider' => $localAnalysis['provider'] ?? null,
                    'model' => $localAnalysis['model'] ?? null,
                    'used_for_enrichment' => true,
                ],
            ]
        );

        return $geminiAnalysis;
    }

    /**
     * @param  array<string, mixed>  $localAnalysis
     * @return array<string, mixed>
     */
    protected function decorateLocalFallback(array $localAnalysis, string $geminiError): array
    {
        $localAnalysis['classification']['summary'] = $this->buildLocalSummary($localAnalysis['ocr'] ?? []);
        $localAnalysis['raw_response_json'] = array_merge(
            is_array($localAnalysis['raw_response_json'] ?? null) ? $localAnalysis['raw_response_json'] : [],
            [
                'gemini_error' => $geminiError,
                'fallback_reason' => $this->humanizeFailureMessage($geminiError),
            ]
        );

        return $localAnalysis;
    }

    /**
     * @return array<string, string|null>
     */
    protected function extractSignalsFromRawText(string $rawText): array
    {
        $normalized = $this->normalizeOcrText($rawText);
        $asciiUpper = Str::upper(Str::ascii($normalized));

        return [
            'tracking_number' => $this->extractTrackingNumber($normalized),
            'order_number' => $this->extractOrderNumber($normalized),
            'product_barcode' => $this->extractProductBarcode($normalized),
            'customer_name' => $this->extractCustomerName($normalized, $asciiUpper),
            'cargo_provider' => $this->detectCargoProvider($asciiUpper),
        ];
    }

    protected function extractTrackingNumber(string $rawText): ?string
    {
        $candidates = [];
        $frequencies = [];
        $normalized = $this->normalizeOcrText($rawText);

        if ($labeledTrackingNumber = $this->extractLabeledTrackingNumber($normalized)) {
            return $labeledTrackingNumber;
        }

        if (preg_match_all('/\bTF[-\s]?\d{8,20}\b/ui', $normalized, $matches)) {
            foreach ($matches[0] as $candidate) {
                $normalizedCandidate = Str::upper(preg_replace('/\s+/u', '-', trim($candidate)) ?: trim($candidate));
                $frequencies[$normalizedCandidate] = ($frequencies[$normalizedCandidate] ?? 0) + 1;
                $candidates[$normalizedCandidate] = 120 + strlen(preg_replace('/\D+/', '', $normalizedCandidate) ?: '');
            }
        }

        if (preg_match_all('/\b\d{12,18}\b/u', $normalized, $matches)) {
            foreach ($matches[0] as $candidate) {
                $digits = preg_replace('/\D+/', '', $candidate) ?: '';

                if (strlen($digits) < 12) {
                    continue;
                }

                $score = strlen($digits);

                if (str_starts_with($digits, '0')) {
                    $score -= 4;
                }

                if (preg_match('/(\d)\1{5,}/', $digits)) {
                    $score -= 6;
                }

                if (in_array(strlen($digits), [13, 14], true)) {
                    $score += 8;
                }

                $frequencies[$digits] = ($frequencies[$digits] ?? 0) + 1;
                $candidates[$digits] = max($candidates[$digits] ?? 0, $score);
            }
        }

        if ($candidates === []) {
            return null;
        }

        foreach ($candidates as $candidate => $score) {
            $candidates[$candidate] = $score + (($frequencies[$candidate] ?? 0) * 5);
        }

        arsort($candidates);

        return array_key_first($candidates);
    }

    protected function extractLabeledTrackingNumber(string $rawText): ?string
    {
        $patterns = [
            '/\bT[\.\s]*NO\b[:\-\s]*([0-9]{12,18})\b/ui',
            '/\bTAKIP[\s\-]*NO(?:SU)?\b[:\-\s]*([0-9]{12,18})\b/ui',
            '/\bGONDERI[\s\-]*NO(?:SU)?\b[:\-\s]*([0-9]{12,18})\b/ui',
        ];
        $candidates = [];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $rawText, $matches)) {
                foreach (($matches[1] ?? []) as $match) {
                    $digits = preg_replace('/\D+/', '', (string) $match) ?: '';

                    if (strlen($digits) < 12) {
                        continue;
                    }

                    foreach ($this->expandLikelyTrackingDigits($digits) as $candidateDigits) {
                        $score = 200;

                        if (strlen($candidateDigits) === 13) {
                            $score += 12;
                        } elseif (strlen($candidateDigits) === 14) {
                            $score += 10;
                        }

                        if (preg_match('/(\d)\1{4,}/', $candidateDigits)) {
                            $score -= 8;
                        }

                        $candidates[$candidateDigits] = max($candidates[$candidateDigits] ?? 0, $score);
                    }
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        arsort($candidates);

        return array_key_first($candidates);
    }

    /**
     * @return array<int, string>
     */
    protected function expandLikelyTrackingDigits(string $digits): array
    {
        $candidates = [$digits];

        if (strlen($digits) === 14 && preg_match('/(\d)\1{2,}/', $digits, $matches, PREG_OFFSET_CAPTURE)) {
            $run = (string) ($matches[0][0] ?? '');
            $offset = (int) ($matches[0][1] ?? 0);

            if (strlen($run) >= 3) {
                $collapsedRun = substr($run, 0, 2);
                $collapsed = substr($digits, 0, $offset) . $collapsedRun . substr($digits, $offset + strlen($run));

                if (strlen($collapsed) >= 12) {
                    $candidates[] = $collapsed;
                }
            }
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    protected function extractOrderNumber(string $rawText): ?string
    {
        if (preg_match('/\b(?:TY|HB|N11|WOO|ORDER)[-\s]?[A-Z0-9]{4,20}\b/ui', $rawText, $matches)) {
            return Str::upper(trim((string) $matches[0]));
        }

        return null;
    }

    protected function extractProductBarcode(string $rawText): ?string
    {
        if (preg_match('/\b(?:BARKOD|BARCODE)[:\s-]*([0-9]{8,14})\b/ui', $rawText, $matches)) {
            return trim((string) ($matches[1] ?? '')) ?: null;
        }

        return null;
    }

    protected function extractCustomerName(string $rawText, string $asciiUpper): ?string
    {
        if (!preg_match('/\b(ALICI|MUSTERI|MÜŞTERI|MUS TERI|ADI|ADI SOYADI|SN)\b/ui', $rawText)) {
            return null;
        }

        $lines = preg_split('/\R+/u', $rawText) ?: [];
        $skipKeywords = [
            'SURAT',
            'KARGO',
            'ACENTE',
            'SUMER',
            'AKTAR',
            'AKTARMA',
            'TESLIM',
            'ADRESE',
            'PAKET',
            'KOLI',
            'SUBE',
            'PACA',
            'PARCA',
            'TELEFON',
            'ADRES',
            'DENIZLI',
            'MERKEZEFENDI',
            'YUKARI',
            'MAH',
            'SOK',
            'NO',
            'TC',
        ];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            $asciiLine = Str::upper(Str::ascii($trimmed));

            if ($trimmed === '' || preg_match('/\d/u', $trimmed)) {
                continue;
            }

            if (Str::length($trimmed) < 5 || Str::length($trimmed) > 40) {
                continue;
            }

            if (collect($skipKeywords)->contains(fn ($keyword) => str_contains($asciiLine, $keyword))) {
                continue;
            }

            if (preg_match('/^[\p{L}\s]{5,40}$/u', $trimmed) !== 1) {
                continue;
            }

            $words = preg_split('/\s+/u', $trimmed) ?: [];

            if (count($words) < 2 || count($words) > 4) {
                continue;
            }

            if (collect($words)->contains(fn ($word) => Str::length($word) < 3 || Str::length($word) > 10)) {
                continue;
            }

            return Str::title(Str::lower($trimmed));
        }

        return null;
    }

    protected function detectCargoProvider(string $asciiUpper): ?string
    {
        $compact = preg_replace('/[^A-Z0-9]+/', '', $asciiUpper) ?: $asciiUpper;

        $providers = [
            'Sürat Kargo' => ['SURATKARGO', 'SURAT KARGO', 'SURATKARG', 'GURATKARGO', 'SURATKARG0', 'SURATKARGE'],
            'Aras Kargo' => ['ARASKARGO', 'ARAS KARGO'],
            'Yurtiçi Kargo' => ['YURTICI', 'YURTICI KARGO'],
            'MNG Kargo' => ['MNGKARGO', 'MNG KARGO'],
            'PTT Kargo' => ['PTT KARGO', 'PTTKARGO'],
            'HepsiJET' => ['HEPSIJET', 'HEPSI JET'],
            'Trendyol Express' => ['TRENDYOL EXPRESS', 'TY EXPRESS'],
        ];

        foreach ($providers as $label => $needles) {
            foreach ($needles as $needle) {
                $normalizedNeedle = preg_replace('/[^A-Z0-9]+/', '', $needle) ?: $needle;

                if (str_contains($asciiUpper, $needle) || str_contains($compact, $normalizedNeedle)) {
                    return $label;
                }
            }
        }

        return null;
    }

    protected function estimateLocalConfidence(array $signals, string $rawText): float
    {
        $confidence = 0.22;

        if ($signals['tracking_number'] ?? null) {
            $confidence += 0.42;
        }

        if ($signals['cargo_provider'] ?? null) {
            $confidence += 0.16;
        }

        if ($signals['customer_name'] ?? null) {
            $confidence += 0.08;
        }

        if (Str::length($rawText) > 40) {
            $confidence += 0.06;
        }

        return min(0.88, round($confidence, 2));
    }

    protected function normalizeOcrText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[^\P{C}\n\t]/u', ' ', $text) ?: $text;
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?: $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?: $text;

        return trim($text);
    }

    protected function promptFor(ReturnIntakeItem $item): string
    {
        return <<<PROMPT
Bu görseller Türkiye e-ticaret operasyonlarında depoya ulaşan iade kayıtlarına aittir.

Amacın yalnızca görülebilen veriyi çıkarmaktır. Tahmin yürütme.

Kurallar:
- Eğer sadece kargo etiketi görünüyorsa ürün doğruluğu hakkında tahmin yapma.
- Takip numarası, sipariş numarası, barkod, müşteri adı ve kargo firmasını yalnızca net okunuyorsa doldur. Bulamazsan boş string ("") dön, kesinlikle null kullanma.
- Hasar fotoğrafları varsa condition_status için `damaged`, görünür hasar yoksa `undamaged`, emin değilsen `unknown` kullan.
- issue_tags alanına kısa İngilizce etiketler yaz. Örnek: damaged_box, torn_package, scratched_surface, broken_part, missing_piece, no_damage_visible.
- operator_barcode bağlamı verilmişse onu ancak görseldeki ürün barkodu ile uyumluysa destekleyici sinyal olarak düşün.
- JSON dışında hiçbir şey döndürme. JSON şemasındaki alan tiplerine tam olarak uy (string beklenen yere null koyma).

Türk kargo etiketleri hakkında ipuçları:
- Sürat Kargo: "T.NO" veya "TAKİP NO" etiketinin yanında 13-14 haneli numara. Logo kırmızı-sarı.
- Aras Kargo: Genellikle barkodun altında gönderi numarası. Logo mor-turuncu.
- Yurtiçi Kargo: "GÖNDERİ NO" etiketli. Logo yeşil-beyaz.
- MNG Kargo: Logo kırmızı. Takip numarası 12-14 hane.
- HepsiJET / Trendyol Express: Genellikle sipariş numarası direkt etikette yazar.
- Numaralarda OCR hataları olabilir: 0↔O, 1↔l, 5↔S gibi. Güvendiğin sonucu yaz.

Bağlam:
- intake_type: {$item->intake_type}
- manual_reference: {$item->manual_reference}
- operator_barcode: {$item->operator_barcode}
- warehouse_note: {$item->warehouse_note}
PROMPT;
    }

    /**
     * @return array{provider: string|null, model: string, api_key: string}|null
     */
    protected function resolveAiConfig(): ?array
    {
        $candidates = [
            [
                'provider' => (string) config('ai.provider', ''),
                'api_key' => (string) config('ai.api_key', ''),
                'model' => (string) (config('ai.model', 'gemini-2.0-flash') ?: 'gemini-2.0-flash'),
            ],
            [
                'provider' => (string) config('ai.fallback_provider', ''),
                'api_key' => (string) config('ai.fallback_api_key', ''),
                'model' => (string) (config('ai.fallback_model', 'gemini-2.0-flash') ?: 'gemini-2.0-flash'),
            ],
            [
                'provider' => (string) config('ai.fallback2_provider', ''),
                'api_key' => (string) config('ai.fallback2_api_key', ''),
                'model' => (string) (config('ai.fallback2_model', 'gemini-2.0-flash') ?: 'gemini-2.0-flash'),
            ],
        ];

        foreach ($candidates as $candidate) {
            if (in_array($candidate['provider'], ['gemini', 'openai']) && $candidate['api_key'] !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    protected function fallbackAnalysis(ReturnIntakeItem $item, string $message, array $raw = []): array
    {
        $manualReference = trim((string) ($item->manual_reference ?? ''));

        return [
            'provider' => null,
            'model' => 'manual_fallback',
            'prompt_version' => 'returns_v1',
            'confidence' => 0.0,
            'ocr' => [
                'tracking_number' => $this->looksLikeTracking($manualReference) ? $manualReference : null,
                'order_number' => $this->looksLikeTracking($manualReference) || $this->looksLikeBarcode($manualReference) ? null : ($manualReference !== '' ? $manualReference : null),
                'product_barcode' => $this->looksLikeBarcode($manualReference) ? $manualReference : null,
                'customer_name' => null,
                'cargo_provider' => null,
                'raw_text' => '',
            ],
            'classification' => [
                'condition_status' => $item->intake_type === 'damaged' ? 'damaged' : 'undamaged',
                'issue_tags' => $item->intake_type === 'damaged' ? ['damage_reported_by_operator'] : ['no_damage_visible'],
                'summary' => $message,
            ],
            'raw_response_json' => $raw,
        ];
    }

    protected function cleanSignal(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    protected function looksLikeTracking(string $value): bool
    {
        return preg_match('/^[A-Z]{1,4}[-\s]?\d{6,}$/i', $value) === 1 || preg_match('/^\d{10,20}$/', preg_replace('/\D+/', '', $value)) === 1;
    }

    protected function looksLikeBarcode(string $value): bool
    {
        return preg_match('/^\d{8,14}$/', preg_replace('/\D+/', '', $value)) === 1;
    }

    protected function humanizeFailureMessage(string $message): string
    {
        $normalized = Str::lower($message);

        if (str_contains($normalized, 'quota exceeded') || str_contains($normalized, 'resource_exhausted') || str_contains($normalized, '429')) {
            return 'Gemini kotası aşıldığı için AI OCR geçici olarak çalışmadı.';
        }

        if (str_contains($normalized, 'api key') || str_contains($normalized, 'unauthorized') || str_contains($normalized, 'permission')) {
            return 'AI OCR kimlik doğrulaması başarısız oldu.';
        }

        if (str_contains($normalized, 'timeout')) {
            return 'AI OCR zaman aşımına uğradı.';
        }

        return 'AI OCR geçici olarak başarısız oldu.';
    }
}
