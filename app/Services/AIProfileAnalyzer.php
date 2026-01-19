<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIProfileAnalyzer
{
    protected AIService $aiService;

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Analyze input/output files and generate transformation rules
     */
    public function analyze(
        string $inputFilePath,
        ?string $outputFilePath,
        string $userDescription,
        array $inputStructure = [],
        array $outputStructure = []
    ): array {
        // Dosya yapılarını al (eğer verilmediyse)
        if (empty($inputStructure)) {
            $inputStructure = $this->extractFileStructure($inputFilePath, true);
        }

        if ($outputFilePath && empty($outputStructure)) {
            $outputStructure = $this->extractFileStructure($outputFilePath, true);
        }

        Log::info('AIProfileAnalyzer: Yapılar çıkarıldı', [
            'input_sheets' => count($inputStructure['sheets'] ?? []),
            'output_sheets' => count($outputStructure['sheets'] ?? []),
        ]);

        // AI prompt oluştur
        $prompt = $this->buildPrompt($inputStructure, $outputStructure, $userDescription);

        Log::info('AIProfileAnalyzer: Prompt oluşturuldu', ['length' => strlen($prompt)]);

        // AI'dan yanıt al
        $response = $this->callAI($prompt);

        Log::info('AIProfileAnalyzer: AI yanıtı alındı', ['length' => strlen($response)]);

        // JSON çıktısını parse et
        $rules = $this->parseAIResponse($response);

        // Kuralları doğrula
        $this->validateRules($rules);

        return $rules;
    }

    /**
     * Extract detailed structure from Excel file
     */
    public function extractFileStructure(string $filePath, bool $includeAllData = false): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $structure = [
            'sheets' => [],
            'file_name' => basename($filePath),
        ];

        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            $data = $sheet->toArray(null, true, true, true);

            if (empty($data)) continue;

            // İlk satır header
            $headers = array_shift($data) ?? [];
            $columns = array_values(array_filter($headers));

            // Daha fazla örnek veri al (ilk 10 satır) - AI daha iyi anlasın
            $sampleCount = $includeAllData ? min(count($data), 10) : 3;
            $sampleData = array_slice($data, 0, $sampleCount);
            $samples = [];
            
            foreach ($sampleData as $row) {
                $sample = [];
                foreach ($headers as $col => $header) {
                    if ($header) {
                        $sample[$header] = $row[$col] ?? null;
                    }
                }
                if (!empty(array_filter($sample))) {
                    $samples[] = $sample;
                }
            }

            $structure['sheets'][] = [
                'name' => $sheetName,
                'columns' => $columns,
                'row_count' => count($data),
                'sample_data' => $samples,
            ];
        }

        return $structure;
    }

    /**
     * Build improved prompt for AI
     */
    protected function buildPrompt(array $inputStructure, array $outputStructure, string $userDescription): string
    {
        $prompt = "Sen bir Excel dönüşüm uzmanısın. Kullanıcının GİRDİ dosyasını ÇIKTI dosyasına dönüştürmek için gereken kuralları oluşturman gerekiyor.\n\n";
        
        // GİRDİ DOSYASI
        $prompt .= "## GİRDİ DOSYASI: {$inputStructure['file_name']}\n\n";
        
        foreach ($inputStructure['sheets'] as $sheet) {
            $prompt .= "### Sayfa: {$sheet['name']} ({$sheet['row_count']} satır)\n";
            $prompt .= "Kolonlar: " . implode(', ', $sheet['columns']) . "\n";
            
            if (!empty($sheet['sample_data'])) {
                $prompt .= "Örnek veriler:\n";
                foreach (array_slice($sheet['sample_data'], 0, 3) as $i => $sample) {
                    $prompt .= ($i + 1) . ". ";
                    $parts = [];
                    foreach ($sample as $key => $val) {
                        if ($val !== null && $val !== '') {
                            $parts[] = "$key: $val";
                        }
                    }
                    $prompt .= implode(' | ', array_slice($parts, 0, 6)) . "\n";
                }
            }
            $prompt .= "\n";
        }

        // ÇIKTI DOSYASI
        if (!empty($outputStructure['sheets'])) {
            $prompt .= "## HEDEF ÇIKTI DOSYASI: {$outputStructure['file_name']}\n\n";
            $prompt .= "**ÖNEMLİ: Çıktı dosyasının yapısını TAM OLARAK taklit et!**\n\n";
            
            foreach ($outputStructure['sheets'] as $sheet) {
                $prompt .= "### Sayfa: {$sheet['name']} ({$sheet['row_count']} satır)\n";
                $prompt .= "Kolonlar: " . implode(', ', $sheet['columns']) . "\n";
                
                if (!empty($sheet['sample_data'])) {
                    $prompt .= "Örnek veriler:\n";
                    foreach (array_slice($sheet['sample_data'], 0, 3) as $i => $sample) {
                        $prompt .= ($i + 1) . ". ";
                        $parts = [];
                        foreach ($sample as $key => $val) {
                            if ($val !== null && $val !== '') {
                                $parts[] = "$key: $val";
                            }
                        }
                        $prompt .= implode(' | ', array_slice($parts, 0, 8)) . "\n";
                    }
                }
                $prompt .= "\n";
            }
        }

        // KULLANICI AÇIKLAMASI
        $prompt .= "## KULLANICININ AÇIKLAMASI:\n{$userDescription}\n\n";

        // GÖREV VE JSON ŞEMA
        $prompt .= <<<'PROMPT'
## GÖREV:
Girdi dosyasını çıktı dosyasına dönüştürecek kuralları oluştur. Çıktı dosyasındaki SAYFA ADLARINI ve KOLON YAPISI aynı olmalı!

Aşağıdaki JSON formatında yanıt ver. SADECE JSON döndür, başka açıklama yazma:

```json
{
  "version": "1.0",
  "job": {
    "name": "işin-kısa-adı",
    "description": "Entegratör Excel → Hedef Excel dönüşümü"
  },
  "input": {
    "sheet_name": "kaynak sayfa adı (girdi dosyasından)",
    "columns": ["kolon1", "kolon2", "kolon3"]
  },
  "transformations": [
    {
      "type": "filter",
      "description": "açıklama",
      "condition": "kolon IN ['değer1', 'değer2']"
    },
    {
      "type": "map_column",
      "description": "açıklama",
      "source": "kaynak_kolon",
      "target": "hedef_kolon",
      "mapping": {"kaynak_değer": "hedef_değer"}
    }
  ],
  "outputs": [
    {
      "filename_pattern": "CIKTI.xlsx",
      "sheets": [
        {
          "name": "sayfa adı - ÇIKTI DOSYASINDAKİ İLE AYNI OLMALI",
          "type": "detail",
          "columns": ["kolon1", "kolon2", "kolon3"],
          "sort_by": "sıralama_kolonu"
        }
      ]
    }
  ]
}
```

## ÖNEMLİ:
1. Çıktı dosyasındaki SAYFA ADLARINI aynen kullan
2. Çıktı dosyasındaki KOLONLARI aynen kullan
3. Girdi ve çıktı arasındaki farkları analiz et
4. Türkçe karakterleri koru
5. SADECE geçerli JSON döndür

JSON:
PROMPT;

        return $prompt;
    }

    /**
     * Call AI API
     */
    protected function callAI(string $prompt): string
    {
        $apiKey = config('ai.api_key');
        $model = config('ai.model', 'llama-3.3-70b-versatile');
        $provider = config('ai.provider', 'groq');

        if (empty($apiKey)) {
            // Demo mod - örnek kurallar döndür
            return $this->getDemoRules();
        }

        $url = match ($provider) {
            'groq' => 'https://api.groq.com/openai/v1/chat/completions',
            'openai' => 'https://api.openai.com/v1/chat/completions',
            default => 'https://api.groq.com/openai/v1/chat/completions',
        };

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(120)->post($url, [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Sen bir Excel dönüşüm uzmanısın. Sadece geçerli JSON formatında yanıt ver. Açıklama yazma, sadece JSON döndür.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 4096,
            'temperature' => 0.2, // Daha tutarlı çıktı için düşük temperature
        ]);

        if (!$response->successful()) {
            Log::error('AIProfileAnalyzer: API hatası', ['response' => $response->body()]);
            throw new \Exception('AI API hatası: ' . $response->body());
        }

        $data = $response->json();
        return $data['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Parse AI response to extract JSON
     */
    protected function parseAIResponse(string $response): array
    {
        // JSON bloğunu bul
        if (preg_match('/```json\s*(.*?)\s*```/s', $response, $matches)) {
            $jsonStr = $matches[1];
        } elseif (preg_match('/```\s*(.*?)\s*```/s', $response, $matches)) {
            $jsonStr = $matches[1];
        } elseif (preg_match('/\{.*\}/s', $response, $matches)) {
            $jsonStr = $matches[0];
        } else {
            $jsonStr = $response;
        }

        // JSON'u temizle
        $jsonStr = trim($jsonStr);
        
        $rules = json_decode($jsonStr, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('AIProfileAnalyzer: JSON parse hatası', [
                'error' => json_last_error_msg(),
                'response' => substr($response, 0, 500)
            ]);
            throw new \Exception('AI yanıtı geçerli JSON değil: ' . json_last_error_msg());
        }

        return $rules;
    }

    /**
     * Validate generated rules
     */
    protected function validateRules(array $rules): void
    {
        if (!isset($rules['version'])) {
            $rules['version'] = '1.0';
        }

        if (!isset($rules['outputs']) || empty($rules['outputs'])) {
            throw new \Exception('Kurallar çıktı tanımı içermiyor.');
        }
    }

    /**
     * Demo rules for testing without API key
     */
    protected function getDemoRules(): string
    {
        return json_encode([
            'version' => '1.0',
            'job' => [
                'name' => 'demo-donusum',
                'description' => 'Demo dönüşüm kuralları'
            ],
            'input' => [
                'sheet_name' => 'Siparişlerim-Detaylı',
                'columns' => ['Pazaryeri', 'Mağaza', 'Sipariş No', 'Ürün', 'Adet']
            ],
            'transformations' => [
                [
                    'type' => 'filter',
                    'description' => 'Sipariş durumunu filtrele',
                    'condition' => "Sipariş Durumu IS NOT NULL"
                ]
            ],
            'outputs' => [
                [
                    'filename_pattern' => 'SIPARIS_CIKTI.xlsx',
                    'sheets' => [
                        [
                            'name' => 'Siparişler',
                            'type' => 'detail',
                            'columns' => ['Pazaryeri', 'Mağaza', 'Sipariş No', 'Ürün', 'Adet'],
                            'sort_by' => 'Sipariş No'
                        ]
                    ]
                ]
            ]
        ], JSON_UNESCAPED_UNICODE);
    }
}
