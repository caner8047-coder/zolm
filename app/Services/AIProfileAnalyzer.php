<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Http;

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
            $inputStructure = $this->extractFileStructure($inputFilePath);
        }

        if ($outputFilePath && empty($outputStructure)) {
            $outputStructure = $this->extractFileStructure($outputFilePath);
        }

        // AI prompt oluştur
        $prompt = $this->buildPrompt($inputStructure, $outputStructure, $userDescription);

        // AI'dan yanıt al
        $response = $this->callAI($prompt);

        // JSON çıktısını parse et
        $rules = $this->parseAIResponse($response);

        // Kuralları doğrula
        $this->validateRules($rules);

        return $rules;
    }

    /**
     * Extract structure from Excel file
     */
    public function extractFileStructure(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $structure = [
            'sheets' => [],
            'file_name' => basename($filePath),
        ];

        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            $data = $sheet->toArray(null, true, true, true);

            // İlk satır header
            $headers = array_shift($data) ?? [];
            $columns = array_filter(array_values($headers));

            // Örnek veriler (ilk 3 satır)
            $sampleData = array_slice($data, 0, 3);
            $samples = [];
            foreach ($sampleData as $row) {
                $sample = [];
                foreach ($headers as $col => $header) {
                    if ($header) {
                        $sample[$header] = $row[$col] ?? null;
                    }
                }
                $samples[] = $sample;
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
     * Build prompt for AI
     */
    protected function buildPrompt(array $inputStructure, array $outputStructure, string $userDescription): string
    {
        $prompt = <<<PROMPT
Sen bir Excel dönüşüm uzmanısın. Kullanıcının girdi dosyasını analiz edip, istenen çıktıyı üretecek dönüşüm kurallarını JSON formatında oluşturman gerekiyor.

## GİRDİ DOSYASI YAPISI:
```json
{INPUT_STRUCTURE}
```

PROMPT;

        $prompt = str_replace('{INPUT_STRUCTURE}', json_encode($inputStructure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), $prompt);

        if (!empty($outputStructure)) {
            $prompt .= <<<PROMPT

## İSTENEN ÇIKTI DOSYASI YAPISI:
```json
{OUTPUT_STRUCTURE}
```

PROMPT;
            $prompt = str_replace('{OUTPUT_STRUCTURE}', json_encode($outputStructure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), $prompt);
        }

        $prompt .= <<<PROMPT

## KULLANICININ AÇIKLAMASI:
{USER_DESCRIPTION}

## GÖREV:
Yukarıdaki bilgilere göre, aşağıdaki JSON şemasında dönüşüm kurallarını oluştur:

```json
{
  "version": "1.0",
  "input": {
    "sheet_name": "hangi sayfa kullanılacak",
    "header_row": 1,
    "columns": [
      {"name": "kolon adı", "type": "string|integer|date", "required": true|false}
    ]
  },
  "transformations": [
    {
      "type": "filter|map_column|group_by|sort|aggregate",
      "description": "ne yapıyor",
      ...diğer parametreler
    }
  ],
  "outputs": [
    {
      "filename_pattern": "dosya adı şablonu, örn: {GRUP}_OTOMATİK.xlsx",
      "condition": "hangi koşulda bu dosya oluşturulur (opsiyonel)",
      "sheets": [
        {
          "name": "sayfa adı",
          "type": "summary|detail",
          "description": "ne içeriyor",
          "columns": ["hangi kolonlar gösterilecek"],
          "group_by": "gruplama kolonu (summary için)",
          "aggregate": [{"column": "Adet", "function": "SUM"}],
          "sort_by": "sıralama kolonu"
        }
      ]
    }
  ]
}
```

## ÖNEMLİ KURALLAR:
1. Sadece geçerli JSON döndür, başka açıklama yazma.
2. Türkçe karakterleri doğru kullan.
3. Kullanıcının açıklamasını tam olarak karşıla.
4. Kolon isimlerini girdi dosyasından al.
5. Eğer gruplama isteniyorsa, her grup için ayrı dosya oluşturulabilir.

JSON:
PROMPT;

        $prompt = str_replace('{USER_DESCRIPTION}', $userDescription, $prompt);

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
        ])->timeout(60)->post($url, [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Sen bir Excel dönüşüm uzmanısın. Sadece geçerli JSON formatında yanıt ver.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 4096,
            'temperature' => 0.3,
        ]);

        if (!$response->successful()) {
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
        } elseif (preg_match('/\{.*\}/s', $response, $matches)) {
            $jsonStr = $matches[0];
        } else {
            $jsonStr = $response;
        }

        $rules = json_decode($jsonStr, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
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
            throw new \Exception('Kurallar version alanı içermiyor.');
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
            'input' => [
                'sheet_name' => 'Siparişlerim-Detaylı',
                'header_row' => 1,
                'columns' => [
                    ['name' => 'Pazaryeri', 'type' => 'string', 'required' => true],
                    ['name' => 'Ürün', 'type' => 'string', 'required' => true],
                    ['name' => 'Adet', 'type' => 'integer', 'required' => true],
                    ['name' => 'Renk Etiketi', 'type' => 'string', 'required' => true],
                ]
            ],
            'transformations' => [
                [
                    'type' => 'filter',
                    'description' => 'Sadece üretime gidecek satırları filtrele',
                    'condition' => "Renk Etiketi IN ['BERJER', 'KÖŞE & KANEPE', 'PUF & BENCH']"
                ],
                [
                    'type' => 'map_column',
                    'description' => 'Renk etiketini grup adına çevir',
                    'source' => 'Renk Etiketi',
                    'target' => 'GRUP',
                    'mapping' => [
                        'BERJER' => 'BERJER',
                        'KÖŞE & KANEPE' => 'KÖŞE VE KANEPE',
                        'PUF & BENCH' => 'PUF'
                    ]
                ],
                [
                    'type' => 'group_by',
                    'description' => 'Gruba göre ayır',
                    'column' => 'GRUP',
                    'create_separate_files' => true
                ]
            ],
            'outputs' => [
                [
                    'filename_pattern' => '{GRUP}_OTOMATİK.xlsx',
                    'description' => 'Her grup için ayrı dosya',
                    'sheets' => [
                        [
                            'name' => 'DENİZLİ TOPLAM SİPARİŞ',
                            'type' => 'summary',
                            'description' => 'Ürün bazlı toplam adet',
                            'group_by' => 'Ürün',
                            'aggregate' => [['column' => 'Adet', 'function' => 'SUM']]
                        ],
                        [
                            'name' => 'NAZİLLİ SİPARİŞ TAKİP',
                            'type' => 'detail',
                            'description' => 'Tüm siparişler detaylı',
                            'columns' => ['Pazaryeri', 'Mağaza', 'Sip. Tarihi', 'Sipariş No', 'Ürün', 'Adet'],
                            'sort_by' => 'Sip. Tarihi'
                        ]
                    ]
                ]
            ]
        ], JSON_UNESCAPED_UNICODE);
    }
}
