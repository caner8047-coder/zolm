<?php

namespace App\Services;

use App\Models\Report;
use App\Models\AIConversation;
use App\Models\OptimizationReport;
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

            'financial_advisor' => "Sen bir E-ticaret ve Finans Uzmanısın. Ürün maliyet ve satış fiyatı verilerini analiz ederek kârlılık artırıcı stratejiler öneriyorsun.

ÖNEMLİ KURALLAR:
- Verilen özet verileri dikkatle analiz et.
- Fırsatları net bir şekilde vurgula.
- Markdown formatında, okunabilir bir çıktı ver.
- 3 bölüm halinde yanıtla: Durum Özeti, Fırsat Analizi, Aksiyon Planı.
- Türkçe yanıt ver.",

            'loss_auditor' => "Sen bir E-ticaret Finansal Denetçisisin (Financial Auditor).
ÖNEMLİ: Sadece ZARAR EDEN ürünlere odaklanmalısın.
GÖREV: Zararın kök nedenini (Root Cause Analysis) bul ve çözüm öner.

ANALİZ ADIMLARI:
1. Verilen ürünlerin neden zarar ettiğini tespit et. (Örn: 'Kargo maliyeti satış bedelinin %50'si', 'Komisyon çok yüksek' vb.)
2. Markdown formatında profesyonel bir denetim raporu yaz.
3. Rapor formatı:
   - 🚨 **Kritik Tespitler**: En büyük zararı oluşturan kalemler.
   - 📉 **Kök Neden Analizi**: Zararın ana kaynağı nedir?
   - ✅ **Acil Çözüm Önerileri**: Fiyat artışı mı? Ürünü kaldırmak mı? Kargo desi güncellemesi mi?
4. Türkçe, ciddi ve net bir dil kullan.",

            'pricing_strategist' => "Sen uzman bir Fiyatlandırma Stratejistisin.
GÖREV: Verilen ürün için psikolojik ve kârlı bir satış fiyatı öner.
KURALLAR:
1. Psikolojik Fiyatlandırma: Fiyatlar .90 veya .99 ile bitmeli. (Örn: 100 yerine 99.90).
2. Kârlılık: Maliyetin üzerine en az %20-30 marj koymaya çalış, ama rekabetçi kal.
3. Çıktı Formatı: SADECE JSON formatında yanıt ver. 
   Örnek: { \"suggested_price\": 149.90, \"reason\": \"Maliyet bazlı %35 marj hedeflendi ve psikolojik sınır uygulandı.\" }
4. JSON dışında hiçbir şey yazma.",

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

        if ($role === 'financial_advisor') {
            return "# 🤖 Finansal Analiz (DEMO)\n\n" .
                "## Durum Özeti\n" .
                "Raporunuzda **47 ürün** incelendi ve toplam **₺12,450 potansiyel kâr artışı** tespit edildi.\n\n" .
                "## Fırsat Analizi\n" .
                "- **En büyük fırsat:** Mobilya kategorisindeki ürünlerde Tarife 2'ye geçiş.\n" .
                "- **Riskli ürünler:** 3 ürün şu an zararına satılıyor.\n\n" .
                "## Aksiyon Planı\n" .
                "1. Öncelikle en yüksek fırsat sunan ilk 5 ürünün fiyatlarını güncelleyin.\n" .
                "2. Zarar eden ürünlerin maliyetlerini tekrar gözden geçirin.\n\n" .
                "> *Not: Bu bir demo yanıtıdır. Gerçek analiz için API anahtarı gereklidir.*";
        }

        return $response;
    }

    /**
     * Optimizasyon raporunu analiz et
     */
    public function analyzeOptimizationReport(OptimizationReport $report): string
    {
        // Context oluştur
        $context = "Rapor Özeti:\n";
        $context .= "- Toplam Ürün: {$report->total_products}\n";
        $context .= "- Fırsat Bulunan Ürün: {$report->opportunity_count}\n";
        $context .= "- Mevcut Toplam Kâr: " . number_format($report->total_current_profit, 2) . " TL\n";
        $context .= "- Optimize Toplam Kâr: " . number_format($report->total_optimized_profit, 2) . " TL\n";
        $context .= "- Potansiyel Ekstra Kâr: " . number_format($report->total_extra_profit, 2) . " TL\n";
        
        $topOpportunities = \App\Models\OptimizationReportItem::where('report_id', $report->id)
            ->where('action', 'update')
            ->orderByDesc('extra_profit')
            ->take(5)
            ->get();
            
        if ($topOpportunities->isNotEmpty()) {
            $context .= "\nEn Yüksek Fırsat İçeren İlk 5 Ürün:\n";
            foreach ($topOpportunities as $item) {
                $context .= "- {$item->product_name} ({$item->stock_code}): Mevcut Kâr " . number_format($item->current_net_profit, 2) . " TL -> Yeni Kâr " . number_format($item->suggested_net_profit, 2) . " TL (Fark: +" . number_format($item->extra_profit, 2) . " TL). Önerilen Tarife: {$item->suggested_tariff}\n";
            }
        }

        $question = "Bu raporu analiz et ve kârlılığı artırmak için stratejik önerilerde bulun.\n\nVeriler:\n" . $context;
        
        return $this->ask('financial_advisor', $question);
    }

    /**
     * Zarar eden ürünleri analiz et
     */
    public function analyzeLosses(OptimizationReport $report): string
    {
        $lossItems = \App\Models\OptimizationReportItem::where('report_id', $report->id)
            ->where('current_net_profit', '<', 0)
            ->orderBy('current_net_profit', 'asc') // En çok zarar eden en üste
            ->take(20) // Token limiti için sınırla
            ->get();

        if ($lossItems->isEmpty()) {
            return "Bu raporda zarar eden ürün bulunmamaktadır. Harika iş! 🎉";
        }

        $context = "ZARAR EDEN ÜRÜNLER LİSTESİ (İlk 20):\n";
        foreach ($lossItems as $item) {
            $totalCost = $item->production_cost + $item->shipping_cost;
            $context .= "- Ürün: {$item->product_name} ({$item->stock_code})\n";
            $context .= "  Satış Fiyatı: {$item->current_price} TL | Komisyon: %{$item->current_commission}\n";
            $context .= "  Üretim: {$item->production_cost} TL | Kargo: {$item->shipping_cost} TL | Toplam Maliyet: {$totalCost} TL\n";
            $context .= "  NET ZARAR: {$item->current_net_profit} TL\n";
            $context .= "--------------------------------------------------\n";
        }

        $question = "Bu zarar eden ürünleri analiz et. Neden zarar ediyoruz? Kök nedenleri bul ve çözüm öner.\n\n" . $context;
        
        return $this->ask('loss_auditor', $question);
    }

    
    /**
     * Rapor ile sohbet et
     */
    public function chatWithReport(AIConversation $conversation, string $message): string
    {
        // Optimizasyon raporu mu yoksa normal rapor mu?
        $optReport = $conversation->optimizationReport;
        
        $context = "";
        
        if ($optReport) {
            // Optimization Report Context
            $context = "Şu an incelenen rapor: '{$optReport->name}'\n";
            $context .= "Toplam Ürün: {$optReport->total_products}, Fırsat: {$optReport->opportunity_count}, Toplam Ekstra Kâr: " . number_format($optReport->total_extra_profit, 2) . " TL.\n\n";
            
            // Eğer soru spesifik bir ürünle ilgiliyse (örn: "X ürünü")
            // Basit bir kelime arama ile ilgili ürünü bulmaya çalışalım
            $words = explode(' ', $message);
            foreach ($words as $word) {
                if (mb_strlen($word) > 3) {
                    $foundItem = \App\Models\OptimizationReportItem::where('report_id', $optReport->id)
                        ->where(function($q) use ($word) {
                            $q->where('product_name', 'like', "%{$word}%")
                              ->orWhere('stock_code', 'like', "%{$word}%");
                        })->first();
                        
                    if ($foundItem) {
                        $context .= "Kullanıcı muhtemelen şu üründen bahsediyor:\n";
                        $context .= "- {$foundItem->product_name} ({$foundItem->stock_code})\n";
                        $context .= "  Mevcut Fiyat: {$foundItem->current_price}, Maliyet: " . ($foundItem->production_cost + $foundItem->shipping_cost) . "\n";
                        $context .= "  Net Kâr: {$foundItem->current_net_profit} -> Önerilen: {$foundItem->suggested_net_profit}\n";
                        break;
                    }
                }
            }
        }
        
        // Sohbet geçmişini al (Son 5 mesaj)
        $history = "";
        if ($conversation->messages) {
            $lastMessages = array_slice($conversation->messages, -5);
            foreach ($lastMessages as $msg) {
                $roleName = $msg['role'] === 'user' ? 'Kullanıcı' : 'Asistan';
                $history .= "{$roleName}: {$msg['content']}\n";
            }
        }
        
        $prompt = "Sen yardımcı bir asistan değil, rapor verilerine hakim bir iş zekası (BI) uzmanısın.\n";
        $prompt .= "Aşağıdaki bağlamı kullanarak kullanıcının sorusunu yanıtla.\n\n";
        $prompt .= "CONTEXT:\n{$context}\n";
        $prompt .= "SOHBET GEÇMİŞİ:\n{$history}\n";
        $prompt .= "SORU: {$message}\n\n";
        $prompt .= "Yanıtın kısa, net ve veriye dayalı olsun.";
        
        return $this->ask('qna', $prompt);
    }

    /**
     * Akıllı Fiyat Önerisi
     */
    public function suggestPrice(string $productName, float $cost, float $currentPrice): array
    {
        $prompt = "Aşağıdaki ürün için optimum satış fiyatını belirle:\n\n";
        $prompt .= "Ürün: {$productName}\n";
        $prompt .= "Toplam Maliyet: " . number_format($cost, 2) . " TL\n";
        $prompt .= "Mevcut Satış Fiyatı: " . number_format($currentPrice, 2) . " TL\n\n";
        $prompt .= "Analiz et ve JSON döndür.";

        $response = $this->ask('pricing_strategist', $prompt);
        
        // JSON temizleme (Markdown code block'larını sil)
        $cleanJson = str_replace(['```json', '```'], '', $response);
        $cleanJson = trim($cleanJson);
        
        try {
            return json_decode($cleanJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            // Fallback: Basit kural tabanlı öneri
            $fallbackPrice = ceil($cost * 1.35) - 0.10; // %35 marj + .90 bitiş
            return [
                'suggested_price' => $fallbackPrice, 
                'reason' => 'AI yanıtı işlenemedi, varsayılan %35 kâr marjı uygulandı.'
            ];
        }
    }
}
