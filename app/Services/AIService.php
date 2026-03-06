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
    
    protected string $fallbackProvider;
    protected string $fallbackApiKey;
    protected string $fallbackModel;

    protected string $fallback2Provider;
    protected string $fallback2ApiKey;
    protected string $fallback2Model;

    public function __construct()
    {
        $this->provider = config('ai.provider', 'groq');
        $this->apiKey = config('ai.api_key', '');
        $this->model = config('ai.model', 'llama-3.3-70b-versatile');
        
        $this->fallbackProvider = config('ai.fallback_provider', '');
        $this->fallbackApiKey = config('ai.fallback_api_key', '');
        $this->fallbackModel = config('ai.fallback_model', '');
        
        $this->fallback2Provider = config('ai.fallback2_provider', '');
        $this->fallback2ApiKey = config('ai.fallback2_api_key', '');
        $this->fallback2Model = config('ai.fallback2_model', '');
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
            
            // 1. Birincil Sağlayıcıyı Dene
            $result = $this->askProvider($this->provider, $this->apiKey, $this->model, $systemPrompt, $fullQuestion);
            
            if ($result['success']) {
                return $result['content'];
            }
            
            $errorMsg = "Birincil AI ({$this->provider}) hatası: " . $result['error'];

            // 2. Hata varsa ve Fallback tanımlıysa 1. Yedek Sağlayıcıyı Dene
            if (!empty($this->fallbackApiKey) && !empty($this->fallbackProvider)) {
                $fallbackResult = $this->askProvider($this->fallbackProvider, $this->fallbackApiKey, $this->fallbackModel, $systemPrompt, $fullQuestion);
                
                if ($fallbackResult['success']) {
                    return $fallbackResult['content'];
                }
                
                $errorMsg .= "\nYedek AI 1 ({$this->fallbackProvider}) hatası: " . $fallbackResult['error'];
            }

            // 3. Hala hata varsa ve Fallback 2 tanımlıysa 2. Yedek Sağlayıcıyı Dene
            if (!empty($this->fallback2ApiKey) && !empty($this->fallback2Provider)) {
                $fallback2Result = $this->askProvider($this->fallback2Provider, $this->fallback2ApiKey, $this->fallback2Model, $systemPrompt, $fullQuestion);
                
                if ($fallback2Result['success']) {
                    return $fallback2Result['content'];
                }
                
                $errorMsg .= "\nYedek AI 2 ({$this->fallback2Provider}) hatası: " . $fallback2Result['error'];
            }

            return "❌ AI API isteği başarısız oldu.\n" . $errorMsg;

        } catch (\Exception $e) {
            return 'Bağlantı hatası: ' . $e->getMessage();
        }
    }

    /**
     * Provider ile iletişimi yöneten metod
     */
    protected function askProvider(string $provider, string $apiKey, string $model, string $systemPrompt, string $question): array
    {
        try {
            if ($provider === 'gemini') {
                return $this->askGemini($systemPrompt, $question, $apiKey, $model);
            }
            
            // OpenAI uyumlu API'ler (Groq, OpenAI)
            $apiUrl = match ($provider) {
                'groq' => 'https://api.groq.com/openai/v1/chat/completions',
                'openai' => 'https://api.openai.com/v1/chat/completions',
                default => 'https://api.groq.com/openai/v1/chat/completions',
            };

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(config('ai.timeout', 60))->post($apiUrl, [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $question],
                ],
                'max_tokens' => config('ai.max_tokens', 4000),
                'temperature' => config('ai.temperature', 0.7),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? null;
                if ($content) {
                    return ['success' => true, 'content' => $content];
                }
                return ['success' => false, 'error' => 'Yanıt boş geldi.'];
            }

            return ['success' => false, 'error' => $response->body()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Gemini API ile soru sor (farklı format)
     */
    protected function askGemini(string $systemPrompt, string $question, string $apiKey, string $model): array
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        
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
            $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($content) {
                return ['success' => true, 'content' => $content];
            }
            return ['success' => false, 'error' => 'Gemini yanıtı boş geldi.'];
        }

        return ['success' => false, 'error' => 'Gemini: ' . $response->body()];
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

            'plus_advisor' => "Sen Trendyol Plus Kampanya Uzmanısın. E-ticaret satıcılarına Plus programa katılım stratejisi konusunda danışmanlık yapıyorsun.

UZMANLIK ALANLARIN:
- Plus komisyon yapısı (genellikle %2-3 gibi düşük komisyon avantajı)
- Plus fiyat üst limiti vs mevcut fiyat karşılaştırması
- Plus'a geçişte marj etkisi analizi
- Hangi ürünlerin Plus'a uygun olduğu

ANALİZ FORMATI (3 bölüm):
1. 📊 **Kampanya Özeti**: Kaç ürün analiz edildi, kaçında Plus avantajlı
2. 🎯 **Fırsat ve Risk Analizi**: Plus'a geçişte kârlı ürünler, dikkat edilmesi gerekenler (fiyat limiti baskısı, düşük marjlı ürünler)
3. ✅ **Aksiyon Planı**: Katılınması önerilen ürünler, atlanması gerekenler, fiyat optimizasyonu tavsiyeleri

Türkçe, kısa ve net yanıt ver. Abartma, kritik noktaları vurgula.",

            'badge_advisor' => "Sen Trendyol Avantajlı Ürün Etiketleri (Yıldız Sistemi) Uzmanısın. Satıcılara yıldız etiketi stratejisi konusunda danışmanlık yapıyorsun.

UZMANLIK ALANLARIN:
- 1/2/3 Yıldız fiyat limitleri ve bunların satışa etkisi
- Yıldız etiketinin ürün görünürlüğüne etkisi
- Fiyat düşürme vs görünürlük artışı trade-off'u
- Kategori bazında yıldız stratejisi

ANALİZ FORMATI (3 bölüm):
1. ⭐ **Yıldız Dağılım Özeti**: Kaç ürün hangi yıldıza uygun, mevcut durum
2. 💡 **Fırsat Analizi**: Hangi ürünlerde yıldız almak kârlı (fiyat düşüşü az, görünürlük artışı yüksek), hangilerinde riskli
3. 🏷️ **Strateji Önerisi**: Kategori bazlı tavsiyeler, fiyat-kârlılık dengesi, gözden kaçan fırsatlar

Türkçe, kısa ve net yanıt ver. Gereksiz genel bilgi verme, veriye odaklan.",

            'flash_advisor' => "Sen Trendyol Flaş Kampanya Uzmanısın. Satıcılara flaş ürünler kampanyasına katılım stratejisi konusunda danışmanlık yapıyorsun.

UZMANLIK ALANLARIN:
- 24 saat vs 3 saat flaş fiyat stratejisi
- Agresif indirim riski ve zarar analizi
- Stok-fiyat dengesi (düşük stokta flaş riski)
- Kampanya süresine göre optimal fiyatlama

ANALİZ FORMATI (3 bölüm):
1. ⚡ **Kampanya Özeti**: Toplam ürün, 24h/3h flaş fırsatları, zarar riski olan ürünler
2. 🚨 **Risk ve Fırsat Analizi**: Hangi ürünlerde flaş mantıklı (marj korunuyor), hangilerinde tehlikeli (zarar), stok durumu
3. 🎯 **Katılım Stratejisi**: Katılınması gereken ürünler, kaçınılması gerekenler, alternatif fiyat önerileri

Türkçe, kısa ve net yanıt ver. Kritik riskleri mutlaka vurgula.",

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

        if (in_array($role, ['financial_advisor', 'plus_advisor', 'badge_advisor', 'flash_advisor'])) {
            return "# 🤖 Kampanya Analizi (DEMO)\n\n" .
                "## Durum Özeti\n" .
                "Raporunuz başarıyla incelendi ve potansiyel fırsatlar kârlılık artışı tespit edildi.\n\n" .
                "## Fırsat Analizi\n" .
                "- **En büyük fırsat:** Bazı ürünlerde kampanyaya geçiş veya fiyat optimizasyonu net kâr bareminizi yükseltebilir.\n" .
                "- **Riskli ürünler:** Mevcut komisyon kesintileri nedeniyle zarar etme riski olan ürünleri kontrol edin.\n\n" .
                "## Aksiyon Planı\n" .
                "1. Öncelikle en yüksek fırsat sunan ürünlerde test güncellemeleri yapın.\n" .
                "2. Zarar eden ürünlerin fiyatlarını gözden geçirin.\n\n" .
                "> *Not: Bu içerik DEMO modunda olduğunuz için otomatik oluşturulmuştur. Gerçek AI desteği için .env dosyasına AI_API_KEY ekleyin.*";
        }

        if ($role === 'loss_auditor') {
            return "# 🚨 Zarar Analizi (DEMO)\n\n" .
                "Bu rapordaki bazı ürünler zarar ediyor olabilir.\n\n" .
                "**Kök Neden:** Üretim maliyeti ve kargo desi güncellemeleri kar marjınızı bitirmiş görünüyor.\n\n" .
                "> *Gerçek analiz için lütfen API anahtarınızı yapılandırın.*";
        }

        if ($role === 'pricing_strategist') {
            return json_encode([
                "suggested_price" => 159.90,
                "reason" => "Demo modu aktif. Gerçek yapay zeka önerisi ve stratejik fiyatlama için bir API anahtarı gereklidir."
            ]);
        }

        if ($role === 'qna') {
            return "Merhaba! Raporunuzla ilgili sorularınızı şu anda sadece DEMO modunda yanıtlıyorum. Ürünler hakkında daha iyi ve detaylı çıkarımlar için lütfen '.env' dosyasından AI API anahtarı ekleyin.";
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
     * Hem mevcut fiyatla zarar edenleri, hem de kullanıcının seçtiği senaryoda zarar edenleri tespit eder.
     */
    public function analyzeLosses(OptimizationReport $report): string
    {
        $allItems = \App\Models\OptimizationReportItem::where('report_id', $report->id)->get();

        $lossItems = collect();

        foreach ($allItems as $item) {
            $isLoss = false;
            $lossAmount = 0;
            $lossSource = '';
            $scenarioName = 'Mevcut';
            $scenarioPrice = $item->current_price;

            // 1. Kullanıcı bir senaryo seçmişse, o senaryonun kârına bak
            if ($item->selected_tariff_index !== null && isset($item->scenario_details[$item->selected_tariff_index])) {
                $selectedSc = $item->scenario_details[$item->selected_tariff_index];
                $scenarioName = $selectedSc['name'] ?? 'Seçili';
                $scenarioPrice = $selectedSc['price'] ?? $item->current_price;

                if (($selectedSc['net_profit'] ?? 0) < 0) {
                    $isLoss = true;
                    $lossAmount = $selectedSc['net_profit'];
                    $lossSource = 'selected_scenario';
                }
            }
            // 2. Kullanıcı custom fiyat girmişse, o fiyatla kâr hesapla
            elseif ($item->custom_price) {
                $totalCost = $item->totalCost();
                $customRevenue = $item->custom_price * (1 - ($item->current_commission / 100));
                $customProfit = $customRevenue - $totalCost;

                if ($customProfit < 0) {
                    $isLoss = true;
                    $lossAmount = round($customProfit, 2);
                    $lossSource = 'custom_price';
                    $scenarioName = 'Özel Fiyat';
                    $scenarioPrice = $item->custom_price;
                }
            }
            // 3. Mevcut fiyatla zarar ediyorsa
            elseif ($item->current_net_profit < 0) {
                $isLoss = true;
                $lossAmount = $item->current_net_profit;
                $lossSource = 'current';
            }
            // 4. action=warning olan ürünleri de dahil et (best scenario negative)
            elseif ($item->action === 'warning') {
                $isLoss = true;
                $lossAmount = $item->suggested_net_profit ?? $item->current_net_profit;
                $lossSource = 'warning';
                $scenarioName = $item->suggested_tariff ?? 'Önerilen';
                $scenarioPrice = $item->suggested_price ?? $item->current_price;
            }

            if ($isLoss) {
                $item->_loss_amount = $lossAmount;
                $item->_loss_source = $lossSource;
                $item->_loss_scenario = $scenarioName;
                $item->_loss_price = $scenarioPrice;
                $lossItems->push($item);
            }
        }

        // En çok zarardan en aza sırala
        $lossItems = $lossItems->sortBy('_loss_amount')->take(20);

        if ($lossItems->isEmpty()) {
            return "Bu raporda zarar eden ürün bulunmamaktadır. Harika iş! 🎉";
        }

        $context = "ZARAR EDEN ÜRÜNLER LİSTESİ ({$lossItems->count()} ürün):\n";
        foreach ($lossItems as $item) {
            $totalCost = $item->production_cost + $item->shipping_cost;
            $roi = $totalCost > 0 ? round(($item->_loss_amount / $totalCost) * 100, 1) : 0;
            $context .= "- Ürün: {$item->product_name} ({$item->stock_code})\n";
            $context .= "  Senaryo: {$item->_loss_scenario} | Fiyat: {$item->_loss_price} TL | Komisyon: %{$item->current_commission}\n";
            $context .= "  COGS: {$item->production_cost} TL | Kargo: {$item->shipping_cost} TL | Toplam Maliyet: {$totalCost} TL\n";
            $context .= "  NET ZARAR: {$item->_loss_amount} TL | ROI: %{$roi}\n";
            $context .= "--------------------------------------------------\n";
        }

        $question = "Bu zarar eden ürünleri analiz et. Neden zarar ediyoruz? Her ürün için kök nedeni bul (komisyon yüksek mi, fiyat düşük mü, maliyet yüksek mi). Somut çözüm öner (fiyat artışı, kampanyadan çıkarma, maliyet düşürme vb.).\n\n" . $context;
        
        return $this->ask('loss_auditor', $question);
    }

    /**
     * Plus Kampanya Analizi
     */
    public function analyzePlusCampaign(OptimizationReport $report): string
    {
        $context = "PLUS KOMİSYON TARİFELERİ ANALİZİ:\n";
        $context .= "- Toplam Ürün: {$report->total_products}\n";
        $context .= "- Plus'ta Kârlı Ürün: {$report->opportunity_count}\n";
        $context .= "- Mevcut Toplam Kâr: " . number_format($report->total_current_profit, 2) . " TL\n";
        $context .= "- Plus ile Toplam Kâr: " . number_format($report->total_optimized_profit, 2) . " TL\n";
        $context .= "- Potansiyel Ekstra Kâr: " . number_format($report->total_extra_profit, 2) . " TL\n";
        $context .= "- Eşleşmeyen Ürün: {$report->unmatched_count}\n";

        $items = \App\Models\OptimizationReportItem::where('report_id', $report->id)
            ->orderByDesc('extra_profit')->take(10)->get();

        if ($items->isNotEmpty()) {
            $context .= "\nÜRÜN DETAYLARI (İlk 10):\n";
            foreach ($items as $item) {
                $scenarios = $item->scenario_details ?? [];
                $mevcut = $scenarios[0] ?? [];
                $plus = $scenarios[1] ?? [];
                $context .= "- {$item->product_name}: ";
                $context .= "Mevcut Fiyat: " . number_format($mevcut['price'] ?? 0, 2) . " TL (Kom: %" . ($mevcut['commission'] ?? 0) . "), ";
                $context .= "Plus Limit: " . number_format($plus['price'] ?? 0, 2) . " TL (Kom: %" . ($plus['commission'] ?? 0) . "), ";
                $context .= "Mevcut Kâr: " . number_format($mevcut['net_profit'] ?? 0, 2) . " → Plus Kâr: " . number_format($plus['net_profit'] ?? 0, 2) . " TL\n";
            }
        }

        return $this->ask('plus_advisor', "Bu Plus kampanya verilerini analiz et:\n\n" . $context);
    }

    /**
     * Badge (Yıldız Etiketi) Kampanya Analizi
     */
    public function analyzeBadgeCampaign(OptimizationReport $report): string
    {
        $context = "AVANTAJLI ÜRÜN ETİKETLERİ ANALİZİ:\n";
        $context .= "- Toplam Ürün: {$report->total_products}\n";
        $context .= "- Yıldız Fırsatı Olan: {$report->opportunity_count}\n";
        $context .= "- Toplam Ekstra Kâr Potansiyeli: " . number_format($report->total_extra_profit, 2) . " TL\n";

        $starCounts = [0 => 0, 1 => 0, 2 => 0, 3 => 0];
        foreach ($report->items as $item) {
            $scenarios = $item->scenario_details ?? [];
            foreach ($scenarios as $idx => $sc) {
                if ($sc['is_best'] ?? false) { $starCounts[$idx]++; break; }
            }
        }
        $context .= "- Mevcut En İyi: {$starCounts[0]} | 1★ En İyi: {$starCounts[1]} | 2★: {$starCounts[2]} | 3★: {$starCounts[3]}\n";

        $items = \App\Models\OptimizationReportItem::where('report_id', $report->id)
            ->orderByDesc('extra_profit')->take(10)->get();

        if ($items->isNotEmpty()) {
            $context .= "\nÜRÜN DETAYLARI (İlk 10):\n";
            foreach ($items as $item) {
                $scenarios = $item->scenario_details ?? [];
                $context .= "- {$item->product_name}: Mevcut " . number_format($scenarios[0]['price'] ?? 0, 0) . "₺ (Kâr: " . number_format($scenarios[0]['net_profit'] ?? 0, 0) . "₺)";
                for ($s = 1; $s <= 3; $s++) {
                    if (isset($scenarios[$s]) && ($scenarios[$s]['price'] ?? 0) > 0) {
                        $context .= " | {$s}★ " . number_format($scenarios[$s]['price'], 0) . "₺ (Kâr: " . number_format($scenarios[$s]['net_profit'], 0) . "₺)";
                    }
                }
                $context .= "\n";
            }
        }

        return $this->ask('badge_advisor', "Bu yıldız etiketi kampanya verilerini analiz et:\n\n" . $context);
    }

    /**
     * Flash Kampanya Analizi
     */
    public function analyzeFlashCampaign(OptimizationReport $report): string
    {
        $context = "FLAŞ ÜRÜNLER ANALİZİ:\n";
        $context .= "- Toplam Ürün: {$report->total_products}\n";
        $context .= "- Kârlı Flaş Fırsatı: {$report->opportunity_count}\n";
        $context .= "- Zararlı Ürün: " . $report->items->where('action', 'warning')->count() . "\n";
        $context .= "- Toplam Ekstra Kâr Potansiyeli: " . number_format($report->total_extra_profit, 2) . " TL\n";

        $campaignInfo = json_decode($report->ai_analysis ?? '{}', true) ?? [];
        if (!empty($campaignInfo['campaign_start'])) {
            $context .= "- Kampanya: {$campaignInfo['campaign_start']} → {$campaignInfo['campaign_end']}\n";
        }

        $items = \App\Models\OptimizationReportItem::where('report_id', $report->id)
            ->orderByDesc('extra_profit')->take(10)->get();

        if ($items->isNotEmpty()) {
            $context .= "\nÜRÜN DETAYLARI (İlk 10):\n";
            foreach ($items as $item) {
                $scenarios = $item->scenario_details ?? [];
                $context .= "- {$item->product_name}: Mevcut " . number_format($scenarios[0]['price'] ?? 0, 0) . "₺ (Kâr: " . number_format($scenarios[0]['net_profit'] ?? 0, 0) . "₺)";
                if (isset($scenarios[1]) && ($scenarios[1]['price'] ?? 0) > 0) {
                    $context .= " | 24h " . number_format($scenarios[1]['price'], 0) . "₺ (Kâr: " . number_format($scenarios[1]['net_profit'], 0) . "₺)";
                }
                if (isset($scenarios[2]) && ($scenarios[2]['price'] ?? 0) > 0) {
                    $context .= " | 3h " . number_format($scenarios[2]['price'], 0) . "₺ (Kâr: " . number_format($scenarios[2]['net_profit'], 0) . "₺)";
                }
                $context .= "\n";
            }
        }

        return $this->ask('flash_advisor', "Bu flaş kampanya verilerini analiz et:\n\n" . $context);
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
