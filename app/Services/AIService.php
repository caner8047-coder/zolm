<?php

namespace App\Services;

use App\Models\Report;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AIService
{
    protected string $provider;
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->provider = config('ai.provider', 'groq');
        $this->apiKey = config('ai.api_key', '');
        $this->model = config('ai.model', 'llama-3.3-70b-versatile');
    }

    /**
     * Ask a question to the AI with Excel data context
     */
    public function ask(string $role, string $question, ?Report $report = null): string
    {
        // Eğer API anahtarı yoksa
        if (empty($this->apiKey)) {
            // Demo mode aktifse demo yanıt dön, değilse hata ver
            if (config('ai.demo_mode', false)) {
                return $this->getDemoResponse($role, $question, $report);
            }
            return '❌ AI API anahtarı yapılandırılmamış. Lütfen .env dosyasına AI_API_KEY ekleyin.';
        }

        try {
            $systemPrompt = $this->getSystemPrompt($role, $report);
            
            // Eğer rapor varsa, Excel verilerini oku ve prompt'a ekle
            $dataContext = '';
            if ($report) {
                $dataContext = $this->extractReportData($report);
            }

            $fullQuestion = $question;
            if (!empty($dataContext)) {
                $fullQuestion = "Aşağıdaki sipariş verilerini analiz ederek soruyu yanıtla:\n\n" . $dataContext . "\n\nSoru: " . $question;
            }
            
            // Gemini farklı API formatı kullanıyor
            if ($this->provider === 'gemini') {
                return $this->askGemini($systemPrompt, $fullQuestion);
            }
            
            // OpenAI uyumlu API'ler (Groq, OpenAI)
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(config('ai.timeout', 60))->post($this->getApiUrl(), [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $fullQuestion],
                ],
                'max_tokens' => config('ai.max_tokens', 4000),
                'temperature' => config('ai.temperature', 0.7),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['choices'][0]['message']['content'] ?? 'Yanıt alınamadı.';
            }

            return 'API hatası: ' . $response->body();

        } catch (\Exception $e) {
            return 'Bağlantı hatası: ' . $e->getMessage();
        }
    }

    /**
     * Gemini API ile soru sor (farklı format)
     */
    protected function askGemini(string $systemPrompt, string $question): string
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";
        
        $response = Http::timeout(config('ai.timeout', 60))->post($url, [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $systemPrompt . "\n\n" . $question]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => config('ai.temperature', 0.7),
                'maxOutputTokens' => config('ai.max_tokens', 4000),
            ]
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Yanıt alınamadı.';
        }

        return 'Gemini API hatası: ' . $response->body();
    }

    /**
     * Extract data from report's Excel files
     */
    protected function extractReportData(Report $report): string
    {
        $dataLines = [];
        $today = now()->format('Y-m-d');
        
        // Orijinal dosyayı veya çıktı dosyalarını oku
        foreach ($report->files as $file) {
            $fullPath = Storage::disk('local')->path($file->file_path);
            
            if (!file_exists($fullPath)) {
                continue;
            }

            try {
                $spreadsheet = IOFactory::load($fullPath);
                
                foreach ($spreadsheet->getSheetNames() as $sheetName) {
                    $sheet = $spreadsheet->getSheetByName($sheetName);
                    $data = $sheet->toArray(null, true, true, true);
                    
                    if (empty($data)) continue;
                    
                    // İlk satır header
                    $headers = array_shift($data);
                    $headers = array_filter($headers);
                    
                    if (empty($headers)) continue;
                    
                    $dataLines[] = "\n### {$file->filename} - {$sheetName}";
                    $dataLines[] = "Kolonlar: " . implode(', ', $headers);
                    $dataLines[] = "Toplam satır: " . count($data);
                    
                    // İlk 50 satırı ekle (veya daha az)
                    $rowCount = min(count($data), 50);
                    $dataLines[] = "\nVeri ({$rowCount} satır):";
                    
                    foreach (array_slice($data, 0, $rowCount) as $row) {
                        $rowData = [];
                        foreach ($headers as $col => $header) {
                            $value = $row[$col] ?? '';
                            if (!empty($value)) {
                                $rowData[] = "{$header}: {$value}";
                            }
                        }
                        if (!empty($rowData)) {
                            $dataLines[] = "- " . implode(' | ', $rowData);
                        }
                    }
                }
                
            } catch (\Exception $e) {
                $dataLines[] = "Dosya okunamadı: {$file->filename} - " . $e->getMessage();
            }
        }

        if (empty($dataLines)) {
            return '';
        }

        $header = "## Rapor Verileri\n";
        $header .= "Rapor: {$report->original_filename}\n";
        $header .= "Tarih: {$report->created_at->format('d.m.Y H:i')}\n";
        $header .= "Bugünün tarihi: " . now()->format('d.m.Y') . "\n";
        
        return $header . implode("\n", $dataLines);
    }

    /**
     * Get API URL based on provider
     */
    protected function getApiUrl(): string
    {
        return match ($this->provider) {
            'groq' => 'https://api.groq.com/openai/v1/chat/completions',
            'openai' => 'https://api.openai.com/v1/chat/completions',
            default => 'https://api.groq.com/openai/v1/chat/completions',
        };
    }

    /**
     * Get system prompt based on role
     */
    protected function getSystemPrompt(string $role, ?Report $report): string
    {
        $basePrompt = match ($role) {
            'production' => "Sen bir üretim müdürüsün. E-ticaret sipariş verilerini analiz edip üretim planlaması konusunda önerilerde bulunuyorsun. 

ÖNEMLİ KURALLAR:
- Sana verilen Excel verilerini dikkatle oku
- Somut sipariş numaraları ve ürün adları ver
- 'Kargoya Son Teslim Tarihi' sütununu kontrol et
- Bugün gönderilmesi gerekenleri belirle
- Türkçe yanıt ver
- Kısa ve öz ol",

            'operation' => "Sen bir operasyon sorumlusun. E-ticaret sipariş ve kargo verilerini analiz edip operasyonel önerilerde bulunuyorsun.

ÖNEMLİ KURALLAR:
- Sana verilen Excel verilerini dikkatle oku
- Acil durumları, gecikmeli kargoları ve öncelikli işleri belirle
- Somut sipariş numaraları ver
- 'Kargoya Son Teslim Tarihi' bugün veya geçmiş olanları işaretle
- Türkçe yanıt ver
- Kısa ve öz ol",

            'legal' => "Sen kurumsal bir hukuk müşavirisiniz. Kargo firmalarına yazılacak resmi tazmin dilekçeleri hazırlıyorsun.

ÖNEMLİ KURALLAR:
- Son derece resmi ve hukuki bir dil kullan
- Gereksiz nezaket sözcüklerinden kaçın, net ve talepkar ol
- Yazım ve imla kurallarına dikkat et
- Sadece dilekçe metnini (gövde) oluştur, başlık ve imza kısımlarını yazma
- Paragraflar arasında boşluk bırak",

            default => "Sen bir E-ticaret, Üretim ve Operasyon Uzmanısın. Sipariş verilerini analiz edip profesyonel önerilerde bulunuyorsun.

ÖNEMLİ KURALLAR:
- Sana verilen Excel verilerini dikkatle oku
- Somut veriler ve sayılar kullan
- Spesifik sipariş numaraları ve ürün adları ver
- Acil olanları öncelikle belirt
- Türkçe yanıt ver
- Kısa ve öz ol",
        };

        return $basePrompt;
    }

    /**
     * Tazmin dilekçesi metni oluştur
     */
    public function generatePetitionText(\App\Models\Compensation $compensation): string
    {
        $prompt = "Aşağıdaki bilgilere göre bir kargo tazmin dilekçesi metni oluştur. 
        Giriş (Sayın Yetkili hitabıyla başla), olay örgüsü, talep ve kapanış bölümlerini içersin.
        
        Kargo Firması: {$compensation->cargo_company}
        Takip No: {$compensation->takip_kodu}
        Tarih: {$compensation->tarih->format('d.m.Y')}
        Müşteri: {$compensation->musteri_adi}
        Ürün: {$compensation->urun_adi}
        Tazmin Sebebi: {$compensation->sebep_info['label']}
        Talep Tutarı: " . number_format($compensation->talep_tutari, 2) . " TL
        Ek Açıklama: {$compensation->aciklama}
        
        Durum: Bu kargo {$compensation->sebep_info['label']} durumundadır. Mağduriyet oluşmuştur ve tazmin edilmesini talep ediyoruz.";

        return $this->ask('legal', $prompt);
    }

    /**
     * Demo response when API key is not configured
     */
    protected function getDemoResponse(string $role, string $question, ?Report $report): string
    {
        if ($role === 'legal') {
             return "Sayın Yetkili, (DEMO MODU)\n\n" .
                   "Şirketimiz tarafından gönderilen kargo ile ilgili tazmin talebimiz mevcuttur. " .
                   "Bu içerik DEMO modunda olduğunuz için otomatik oluşturulmuştur. Gerçek AI desteği için API anahtarı gereklidir.\n\n" .
                   "Gereğinin yapılmasını arz ederiz.";
        }

        $response = "⚠️ **Demo Modu**\n\nAI API anahtarı yapılandırılmamış. Gerçek veri analizi için:\n\n1. `.env` dosyasına `AI_API_KEY=gsk_xxx` ekleyin\n2. `php artisan config:clear` çalıştırın\n\n";
        
        if ($report) {
            $response .= "📁 Seçili rapor: **{$report->original_filename}**\n";
            $response .= "📊 Çıktı dosyaları:\n";
            foreach ($report->files as $file) {
                $response .= "- {$file->filename}\n";
            }
        }

        return $response;
    }
}
