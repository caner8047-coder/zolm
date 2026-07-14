<?php

namespace App\Services\Support\AI;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportAiRun;
use Illuminate\Support\Facades\Log;

class CustomerCareAiOrchestrator
{
    private CustomerCareContextBuilder $contextBuilder;
    private CustomerCareAiProviderInterface $aiProvider;
    private CustomerCareConfidenceScorer $confidenceScorer;

    public function __construct(
        CustomerCareContextBuilder $contextBuilder,
        CustomerCareAiProviderInterface $aiProvider,
        CustomerCareConfidenceScorer $confidenceScorer
    ) {
        $this->contextBuilder = $contextBuilder;
        $this->aiProvider = $aiProvider;
        $this->confidenceScorer = $confidenceScorer;
    }

    public function generateDraft(SupportConversation $conversation): array
    {
        $startTime = microtime(true);
        $query = '';

        try {
            $context = $this->contextBuilder->buildContext($conversation);
            $query = $context['query'] ?? '';

            if (empty($query)) {
                return [
                    'success' => false,
                    'status' => 'skipped',
                    'message' => 'Müşteriden gelen son mesaj bulunamadı.'
                ];
            }

            $languageService = app(CustomerCareLanguageService::class);
            $languageDetection = $languageService->detect($query);
            $supportedLanguages = $languageService->supportedLanguages($conversation->channel);
            $detectedLanguage = $languageDetection['language'];
            $languageConfidence = (float) $languageDetection['confidence'];
            $responseLanguage = in_array($detectedLanguage, $supportedLanguages, true)
                ? $detectedLanguage
                : ($supportedLanguages[0] ?? 'tr');

            // Get provider and model names
            $providerClass = get_class($this->aiProvider);
            $providerName = basename(str_replace('\\', '/', $providerClass));
            $modelName = 'unknown';

            if (method_exists($this->aiProvider, 'getModel')) {
                $modelName = $this->aiProvider->getModel();
            } elseif ($providerName === 'FakeCustomerCareAiAdapter') {
                $modelName = 'fake-demo';
            }

            $healthService = app(\App\Services\Support\CustomerCareAiProviderHealthService::class);

            // 1. Bütçe koruması yerel ve deterministiktir; sağlayıcı sağlığına
            // bakmadan önce çalışarak limit aşımında yeni AI çağrısını engeller.
            if ($healthService->hasExceededBudget($conversation->store_id)) {
                app(\App\Services\Support\CustomerCareHandoffService::class)->handoff(
                    $conversation, 'AI bütçe güvenlik sınırı aşıldı.', 'medium'
                );
                return [
                    'success' => false,
                    'status' => 'budget_exceeded',
                    'message' => 'Bu mağaza için günlük veya aylık AI bütçe limiti aşıldı.'
                ];
            }

            // 2. AI Provider Health Check
            if (!$healthService->isProviderHealthy($providerName)) {
                app(\App\Services\Support\CustomerCareHandoffService::class)->handoff(
                    $conversation, 'AI sağlayıcısı kullanılamıyor.', 'high'
                );
                return [
                    'success' => false,
                    'status' => 'provider_down',
                    'message' => 'AI sağlayıcısı devre dışı veya API anahtarı yapılandırılmamış.'
                ];
            }

            // System prompt/yönerge şablonu oluşturma (Grounding & Brand Voice)
            $systemInstruction = "Sen bir ZOLM Müşteri İletişim Merkezi yapay zeka asistanısın.\n";
            $systemInstruction .= "Yanıt tonun kesinlikle şu olmalıdır: {$context['tone']}.\n";
            $systemInstruction .= "Yanıt dili {$responseLanguage} olmalıdır. Canlı fiyat, stok, sipariş numarası ve diğer ticari değerleri çevirirken değiştirme.\n";

            if (!empty($context['prompt_context'])) {
                $systemInstruction .= "Mağaza Hakkında Bağlam: {$context['prompt_context']}\n";
            }
            if (!empty($context['return_policy'])) {
                $systemInstruction .= "İade Politikası: {$context['return_policy']}\n";
            }
            $voice = $context['brand_voice'] ?? [];
            $systemInstruction .= "Hitap: " . ($voice['hitap'] ?? 'siz') . "; cevap uzunluğu: " . ($voice['response_length'] ?? 'medium') . "; emoji seviyesi: " . ($voice['emoji_level'] ?? 'normal') . ".\n";
            $systemInstruction .= "Şikâyet tonu: " . ($voice['complaint_tone'] ?? '') . "; satış tonu: " . ($voice['sales_tone'] ?? '') . "; kriz tonu: " . ($voice['crisis_tone'] ?? '') . ".\n";
            if (!empty($voice['preferred_expressions'])) $systemInstruction .= "Tercih edilen ifadeler: " . implode(', ', $voice['preferred_expressions']) . ".\n";
            if (!empty($voice['forbidden_expressions'])) $systemInstruction .= "Kesinlikle kullanma: " . implode(', ', $voice['forbidden_expressions']) . ".\n";
            $languageVoiceRules = (array) ($voice['language_rules'][$responseLanguage] ?? []);
            if (!empty($languageVoiceRules['forbidden_expressions'])) {
                $systemInstruction .= "{$responseLanguage} dilinde kullanma: " . implode(', ', $languageVoiceRules['forbidden_expressions']) . ".\n";
            }
            $approvedExamples = collect(array_merge(
                filled($voice['sample_response'] ?? null) ? [$voice['sample_response']] : [],
                (array) ($languageVoiceRules['examples'] ?? [])
            ))->filter()->take(3)->values();
            if ($approvedExamples->isNotEmpty()) {
                $systemInstruction .= "İnsan onaylı üslup örnekleri (yalnız biçemi izle, içindeki ticari veriyi gerçek kabul etme):\n- " . $approvedExamples->implode("\n- ") . "\n";
            }

            // Grounding Verileri
            $systemInstruction .= "\n--- GÜVENLİ VE GERÇEK GROUNDING VERİLERİ ---\n";
            if (!empty($context['kb'])) {
                $systemInstruction .= "BİLGİ BANKASI MAKALELERİ:\n{$context['kb']}\n";
            }
            if (!empty($context['orders'])) {
                $systemInstruction .= "MÜŞTERİ SİPARİŞ GEÇMİŞİ:\n{$context['orders']}\n";
            }
            if (!empty($context['products'])) {
                $systemInstruction .= "ÜRÜN KATALOĞU:\n{$context['products']}\n";
            }

            $systemInstruction .= "\n--- GÜVENLİK VE UYDURMA ENGELLEME KURALLARI ---\n";
            $systemInstruction .= "1. Yukarıda belirtilen 'Güvenli ve Gerçek Grounding Verileri' dışında kesinlikle uydurma ürün adı, fiyatı, stok durumu, teslimat tarihi, kargo firması veya kargo takip numarası paylaşma.\n";
            $systemInstruction .= "2. Eğer müşteri kargo durumunu veya siparişini soruyorsa ve yukarıda sipariş bilgisi yoksa, kesinlikle bir durum uydurma. Sadece 'Bu konuda sistemimde güncel bir kayıt göremiyorum, sizi bir temsilciye aktarıyorum.' de.\n";
            $systemInstruction .= "3. Eğer müşteri kataloğunuzda olmayan bir ürünü veya fiyatı soruyorsa, kesinlikle fiyat uydurma. 'Maalesef bu ürün hakkında güncel bilgiye sahip değilim.' de.\n";
            $systemInstruction .= "4. Sorunun cevabı grounding verilerinde açıkça yer almıyorsa veya emin değilsen uydurma cevap yazmak yerine 'Bu konuda detaylı bilgiye sahip değilim, sizi bir müşteri temsilcisine aktarıyorum' (handoff) şeklinde yanıt ver.\n";

            $responseDto = $this->aiProvider->generateAnswer(
                $conversation,
                $context['history_list'],
                $systemInstruction
            );

            $latency = (int)((microtime(true) - $startTime) * 1000);
            $suggestedAnswer = $responseDto->suggestedAnswer;
            $confidence = $this->confidenceScorer->score($responseDto, $context);

            // Hallucination / Uydurma Kontrol Filtresi
            $status = 'draft';
            $handoffReasons = [];
            $citations = $context['citations'] ?? [];
            $sources = !empty($citations)
                ? $citations
                : collect($context['matched_sources'] ?? [])->map(fn ($name) => [
                    'type' => 'context',
                    'name' => $name,
                    'record_id' => null,
                    'freshness_at' => null,
                ])->values()->all();

            $voiceValidation = app(\App\Services\Support\BrandVoiceService::class)
                ->validateResponse($suggestedAnswer, $voice, $responseLanguage);
            if (!$voiceValidation['allowed']) {
                $status = 'handoff';
                $confidence = min($confidence, 30);
                $handoffReasons[] = 'Marka sesi doğrulaması: ' . implode(', ', $voiceValidation['violations'] ?? ['uygunsuz yanıt']);
            }
            if ($languageConfidence < CustomerCareLanguageService::MIN_DETECTION_CONFIDENCE
                || $detectedLanguage === 'und'
                || !in_array($detectedLanguage, $supportedLanguages, true)
                || ($responseDto->language && $responseDto->language !== $responseLanguage)) {
                $status = 'handoff';
                $confidence = min($confidence, 60);
                $handoffReasons[] = 'Dil tespiti, desteklenen dil veya yanıt dili doğrulaması geçilemedi.';
            }

            // A. Sipariş Yokken Sipariş/Kargo Durumu Uydurma Filtresi
            if (empty($context['orders'])) {
                $orderKeywords = ['siparişiniz', 'kargoda', 'kargoya verildi', 'teslim edilecek', 'desi', 'takip no'];
                foreach ($orderKeywords as $kw) {
                    if (mb_stripos($suggestedAnswer, $kw) !== false) {
                        $status = 'handoff';
                        $confidence = 30; // Güven skoru düşürülür
                        $handoffReasons[] = 'Doğrulanmış sipariş/kargo kaynağı olmadan kesin iddia algılandı.';
                        break;
                    }
                }
            }

            // B. Katalog Yokken Ürün/Fiyat Bilgisi Uydurma Filtresi
            if (empty($context['products'])) {
                $catalogKeywords = [' TL', 'fiyatı', 'stokta var', 'stok kodu'];
                foreach ($catalogKeywords as $kw) {
                    if (mb_stripos($suggestedAnswer, $kw) !== false) {
                        $status = 'handoff';
                        $confidence = 30;
                        $handoffReasons[] = 'Doğrulanmış katalog kaynağı olmadan ürün/fiyat iddiası algılandı.';
                        break;
                    }
                }
            }

            // C. Stale / Bayat Veri Filtresi (Fiyat/Stok 24 saatten eskiyse kesin yanıt verilmez)
            if (!empty($context['has_stale_data']) && $context['has_stale_data'] === true) {
                $status = 'handoff';
                $confidence = 30;
                $handoffReasons[] = 'Kullanılan ticari veri güncellik sınırını aştı.';
            }

            // D. Düşük Güven Veya Zorunlu Handoff Cümleleri
            if ($confidence < 75 || mb_stripos($suggestedAnswer, 'müşteri temsilcisine aktarıyorum') !== false || mb_stripos($suggestedAnswer, 'detaylı bilgiye sahip değilim') !== false) {
                $status = 'handoff';
                $handoffReasons[] = "Bileşik güven skoru ({$confidence}) temsilci inceleme eşiğinin altında veya model devir istedi.";
            }

            // Eğer durum 'draft' ise Taslak Mesajı Kaydet
            $messageId = null;
            if ($status === 'draft') {
                $draftMsg = SupportMessage::create([
                    'conversation_id' => $conversation->id,
                    'direction' => 'outbound',
                    'sender_type' => 'ai',
                    'message_type' => 'text',
                    'body_encrypted' => $suggestedAnswer,
                    'body_preview' => mb_substr($suggestedAnswer, 0, 100),
                    'delivery_status' => 'draft',
                    'sent_at' => null
                ]);
                $messageId = $draftMsg->id;
            }

            $inputTokens = (int) (mb_strlen($query) / 4);
            $outputTokens = (int) (mb_strlen($suggestedAnswer) / 4);

            $healthService->recordCost($conversation->store_id, $modelName, $providerName, $inputTokens, $outputTokens);

            // Ledger Kaydı Ekle (support_ai_runs)
            $aiRun = SupportAiRun::create([
                'store_id' => $conversation->store_id,
                'conversation_id' => $conversation->id,
                'message_id' => $messageId,
                'prompt_template_key' => 'copilot_v1',
                'prompt_raw' => $query,
                'response_raw' => $suggestedAnswer,
                'confidence_score' => $confidence,
                'sources_used_json' => $sources,
                'token_in' => $inputTokens,
                'token_out' => $outputTokens,
                'latency_ms' => $latency,
                'status' => $status,
                'detected_language' => $detectedLanguage,
                'language_confidence' => $languageConfidence,
                'response_language' => $responseLanguage,
            ]);

            if ($status === 'handoff') {
                $riskLevel = $confidence < 40 ? 'high' : ($confidence < 75 ? 'medium' : 'low');
                app(\App\Services\Support\CustomerCareHandoffService::class)->handoff(
                    $conversation,
                    implode(' ', array_values(array_unique($handoffReasons))) ?: 'AI yanıtı insan doğrulaması gerektiriyor.',
                    $riskLevel,
                    $sources,
                    $suggestedAnswer,
                    $aiRun->id,
                );
            }

            return [
                'success' => $status === 'draft',
                'status' => $status,
                'suggested_answer' => $suggestedAnswer,
                'confidence' => $confidence,
                'message_id' => $messageId,
                'sources' => $sources,
                'detected_language' => $detectedLanguage,
                'language_confidence' => $languageConfidence,
                'response_language' => $responseLanguage,
            ];

        } catch (\Throwable $e) {
            $latency = (int)((microtime(true) - $startTime) * 1000);
            Log::error('Copilot AI Orchestrator Hatası', ['error' => $e->getMessage()]);

            // Hatalarda da ledger kaydı yazılır
            SupportAiRun::create([
                'store_id' => $conversation->store_id,
                'conversation_id' => $conversation->id,
                'prompt_template_key' => 'copilot_v1',
                'prompt_raw' => $query,
                'response_raw' => null,
                'confidence_score' => 0,
                'sources_used_json' => null,
                'latency_ms' => $latency,
                'status' => 'failed',
                'detected_language' => isset($detectedLanguage) ? $detectedLanguage : null,
                'language_confidence' => isset($languageConfidence) ? $languageConfidence : null,
                'response_language' => isset($responseLanguage) ? $responseLanguage : null,
            ]);

            app(\App\Services\Support\CustomerCareHandoffService::class)->handoff(
                $conversation,
                'AI taslak üretimi teknik veya güvenlik hatasıyla tamamlanamadı.',
                'high',
                [],
                $e->getMessage(),
            );

            return [
                'success' => false,
                'status' => 'failed',
                'message' => $e->getMessage()
            ];
        }
    }
}
