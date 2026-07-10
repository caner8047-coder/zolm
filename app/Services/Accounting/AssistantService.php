<?php

namespace App\Services\Accounting;

use App\Models\AssistantQuery;
use App\Models\Party;
use App\Models\Warehouse;
use App\Models\LegalEntity;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * AI Finansal Asistan Servisi (P11 Hardened).
 *
 * - Doğal dil sorgularını güvenli rapor metodlarına yönlendirir.
 * - Tenant izolasyonunu korur (context doğrulama dahil).
 * - Salt-okunur: Asistan hiçbir finansal kayıt oluşturmaz/değiştirmez.
 * - Kayıt: AssistantQuery + sources_json + suggestions_json + intent.
 */
class AssistantService
{
    // İzinli intent listesi
    public const INTENTS = [
        'executive_summary',
        'cash_flow',
        'receivables_aging',
        'payables_aging',
        'income_expense',
        'stock_inventory',
        'party_balances',
        'unknown',
    ];

    // İşlem isteği anahtar kelimeleri — bunlara HİÇBİR ZAMAN işlem yapma
    private const ACTION_KEYWORDS = [
        'iptal et', 'sil', 'ödeme oluştur', 'stok düş', 'sipariş onayla',
        'oluştur', 'kaydet', 'ekle', 'düzenle', 'güncelle', 'transfer et',
        'fatura kes', 'tahsilat yap', 'fatura iptal',
    ];

    private const MAX_QUERY_LENGTH = 1000;
    private const DUPLICATE_GUARD_SECONDS = 10;

    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    // ─── ANA METOT ────────────────────────────────────────────────────────

    public function askAssistant(int $userId, string $queryText, array $context = []): AssistantQuery
    {
        // 1. Validation
        $queryText = trim($queryText);

        if ($queryText === '') {
            throw new InvalidArgumentException('Soru boş olamaz.');
        }

        if (mb_strlen($queryText) > self::MAX_QUERY_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('Soru en fazla %d karakter olabilir.', self::MAX_QUERY_LENGTH)
            );
        }

        // 2. İşlem isteği guard
        if ($this->isActionRequest($queryText)) {
            return AssistantQuery::create([
                'user_id'       => $userId,
                'query_text'    => $queryText,
                'response_text' => 'Bu asistan işlem yapmaz. İlgili modülden manuel onay gerekir.',
                'status'        => 'blocked',
                'intent'        => 'blocked',
                'confidence_score' => 1.0,
            ]);
        }

        // 3. Context güvenlik doğrulaması (Her şeyden önce çalışarak bypass edilmesini önler)
        $this->validateContext($userId, $context);

        // 4. Intent sınıflandırma ve filtre çıkarımı
        $normalized  = $this->normalizeQuestion($queryText);
        $intentData  = $this->classifyIntent($normalized);
        $intent      = $intentData['intent'];
        $confidence  = $intentData['confidence'];
        $filters     = $this->extractFilters($userId, $normalized, $context);

        // 5. Duplicate guard (son 10 saniyede aynı user aynı query VE aynı filtreler)
        $recentQueries = AssistantQuery::where('user_id', $userId)
            ->where('query_text', $queryText)
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subSeconds(self::DUPLICATE_GUARD_SECONDS))
            ->get();

        foreach ($recentQueries as $recent) {
            if ($this->areFiltersEqual($recent->filters_json ?? [], $filters)) {
                return $recent;
            }
        }

        // 6. Cevap üret
        try {
            $answer = match ($intent) {
                'executive_summary' => $this->answerExecutiveSummary($userId, $filters),
                'cash_flow'         => $this->answerCashFlow($userId, $filters),
                'receivables_aging' => $this->answerReceivablesAging($userId, $filters),
                'payables_aging'    => $this->answerPayablesAging($userId, $filters),
                'income_expense'    => $this->answerIncomeExpense($userId, $filters),
                'stock_inventory'   => $this->answerStockInventory($userId, $filters),
                'party_balances'    => $this->answerPartyBalances($userId, $filters),
                default             => $this->buildFallbackAnswer($queryText),
            };

            $sources     = $this->buildSources($intent, $filters);
            $suggestions = $this->buildSuggestions($intent, $answer['data'] ?? []);

            return AssistantQuery::create([
                'user_id'          => $userId,
                'query_text'       => $queryText,
                'response_text'    => $answer['text'],
                'status'           => 'completed',
                'intent'           => $intent,
                'confidence_score' => $confidence,
                'filters_json'     => $filters,
                'sources_json'     => $sources,
                'suggestions_json' => $suggestions,
                'meta_json'        => ['report' => $intent, 'data' => $answer['data'] ?? []],
                'answered_at'      => now(),
            ]);
        } catch (\Exception $e) {
            return AssistantQuery::create([
                'user_id'       => $userId,
                'query_text'    => $queryText,
                'response_text' => 'Cevap üretilirken bir hata oluştu. Lütfen tekrar deneyin.',
                'status'        => 'failed',
                'intent'        => $intent,
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    // ─── YARDIMCI: NORMALIZE ──────────────────────────────────────────────

    public function normalizeQuestion(string $query): string
    {
        $query = mb_strtolower(trim($query), 'UTF-8');
        // Noktalama temizle
        $query = preg_replace('/[?!.,;:]+/', ' ', $query);
        $query = preg_replace('/\s+/', ' ', $query);
        return trim($query);
    }

    // ─── YARDIMCI: INTENT SINIFLANDIRMA ──────────────────────────────────

    public function classifyIntent(string $normalizedQuery): array
    {
        $intents = [
            'executive_summary' => ['genel durum', 'özet', 'bugün durum', 'mali durum', 'genel bakış', 'finans durumum', 'finansal özet'],
            'cash_flow'         => ['nakit', 'kasa', 'banka', 'likidite', 'para akışı', 'nakit akış', 'nakit durumu'],
            'receivables_aging' => ['alacak', 'geciken alacak', 'kimden alacağım', 'vadesi geçmiş alacak', 'alacaklarım', 'alacak yaşlandırma'],
            'payables_aging'    => ['borç', 'tedarikçi borcu', 'kime borcum', 'ödemem gereken', 'borçlarım', 'borç yaşlandırma'],
            'income_expense'    => ['kar', 'zarar', 'gelir', 'gider', 'karlılık', 'kâr', 'kârlılık', 'kar zarar'],
            'stock_inventory'   => ['stok', 'envanter', 'stok değeri', 'depo', 'stok varlığı', 'ürün stoğu'],
            'party_balances'    => ['cari bakiye', 'müşteri bakiyesi', 'tedarikçi bakiyesi', 'cari', 'cari durum', 'bakiye'],
        ];

        foreach ($intents as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (Str::contains($normalizedQuery, $keyword)) {
                    return ['intent' => $intent, 'confidence' => 0.85];
                }
            }
        }

        return ['intent' => 'unknown', 'confidence' => 0.0];
    }

    // ─── YARDIMCI: FİLTRE ÇIKARIMI ────────────────────────────────────────

    public function extractFilters(int $userId, string $normalizedQuery, array $context = []): array
    {
        $filters = [];

        // Tarih aralığı çıkarımı
        if (Str::contains($normalizedQuery, 'bu ay')) {
            $filters['date_from'] = now()->startOfMonth()->toDateString();
            $filters['date_to']   = now()->toDateString();
        } elseif (Str::contains($normalizedQuery, 'geçen ay')) {
            $filters['date_from'] = now()->subMonth()->startOfMonth()->toDateString();
            $filters['date_to']   = now()->subMonth()->endOfMonth()->toDateString();
        } elseif (Str::contains($normalizedQuery, 'son 7 gün')) {
            $filters['date_from'] = now()->subDays(7)->toDateString();
            $filters['date_to']   = now()->toDateString();
        } elseif (Str::contains($normalizedQuery, 'son 30 gün')) {
            $filters['date_from'] = now()->subDays(30)->toDateString();
            $filters['date_to']   = now()->toDateString();
        } elseif (Str::contains($normalizedQuery, 'bugün')) {
            $filters['date_from'] = now()->toDateString();
            $filters['date_to']   = now()->toDateString();
        }

        // Cash flow horizon
        if (Str::contains($normalizedQuery, 'önümüzdeki 30 gün')) {
            $filters['forecast_horizon'] = 30;
        }

        // Context'ten doğrulanmış ID'ler (validateContext zaten geçti)
        foreach (['legal_entity_id', 'party_id', 'warehouse_id'] as $key) {
            if (!empty($context[$key])) {
                $filters[$key] = (int) $context[$key];
            }
        }

        return $filters;
    }

    // ─── CEVAP METODLARI ─────────────────────────────────────────────────

    public function answerExecutiveSummary(int $userId, array $filters): array
    {
        $data = $this->reportService->executiveSummary($userId, $filters);

        $text = sprintf(
            "Genel finans özeti: Kasa & Banka bakiyeniz ₺%s. Açık alacaklarınız ₺%s, açık borçlarınız ₺%s. " .
            "30 günlük öngörülen kapanış nakiti ₺%s. Envanter değeriniz ₺%s. " .
            "Dönem net kar/zarar: ₺%s.",
            number_format($data['cash_balance'] ?? 0, 2, ',', '.'),
            number_format($data['total_open_receivables'] ?? 0, 2, ',', '.'),
            number_format($data['total_open_payables'] ?? 0, 2, ',', '.'),
            number_format($data['projected_closing_cash'] ?? 0, 2, ',', '.'),
            number_format($data['inventory_value'] ?? 0, 2, ',', '.'),
            number_format($data['net_profit_loss'] ?? 0, 2, ',', '.')
        );

        return ['text' => $text, 'data' => $data];
    }

    public function answerCashFlow(int $userId, array $filters): array
    {
        $horizon = $filters['forecast_horizon'] ?? 30;
        $data    = $this->reportService->cashFlowForecast($userId, $horizon, $filters);

        $text = sprintf(
            "Nakit görünümü: Açılış bakiyeniz ₺%s. Önümüzdeki %d gün içinde beklenen tahsilat ₺%s, " .
            "beklenen ödeme ₺%s. Öngörülen kapanış nakiti: ₺%s.",
            number_format($data['opening_cash_balance'] ?? 0, 2, ',', '.'),
            $horizon,
            number_format($data['total_expected_inflows'] ?? 0, 2, ',', '.'),
            number_format($data['total_expected_outflows'] ?? 0, 2, ',', '.'),
            number_format($data['projected_closing_balance'] ?? 0, 2, ',', '.')
        );

        return ['text' => $text, 'data' => $data];
    }

    public function answerReceivablesAging(int $userId, array $filters): array
    {
        $data = $this->reportService->receivablesAging($userId, $filters);

        $text = sprintf(
            "Alacak durumu: Toplam açık alacağınız ₺%s (%d fatura). Vadesi gelmemiş ₺%s. " .
            "Vadesi geçmiş: 1-30 gün ₺%s, 31-60 gün ₺%s, 61-90 gün ₺%s, 90+ gün ₺%s.",
            number_format($data['total_open'] ?? 0, 2, ',', '.'),
            $data['count'] ?? 0,
            number_format($data['current'] ?? 0, 2, ',', '.'),
            number_format($data['days_1_30'] ?? 0, 2, ',', '.'),
            number_format($data['days_31_60'] ?? 0, 2, ',', '.'),
            number_format($data['days_61_90'] ?? 0, 2, ',', '.'),
            number_format($data['days_90_plus'] ?? 0, 2, ',', '.')
        );

        return ['text' => $text, 'data' => $data];
    }

    public function answerPayablesAging(int $userId, array $filters): array
    {
        $data = $this->reportService->payablesAging($userId, $filters);

        $text = sprintf(
            "Borç durumu: Toplam açık borcunuz ₺%s (%d fatura). Vadesi gelmemiş ₺%s. " .
            "Vadesi geçmiş: 1-30 gün ₺%s, 31-60 gün ₺%s, 61-90 gün ₺%s, 90+ gün ₺%s.",
            number_format($data['total_open'] ?? 0, 2, ',', '.'),
            $data['count'] ?? 0,
            number_format($data['current'] ?? 0, 2, ',', '.'),
            number_format($data['days_1_30'] ?? 0, 2, ',', '.'),
            number_format($data['days_31_60'] ?? 0, 2, ',', '.'),
            number_format($data['days_61_90'] ?? 0, 2, ',', '.'),
            number_format($data['days_90_plus'] ?? 0, 2, ',', '.')
        );

        return ['text' => $text, 'data' => $data];
    }

    public function answerIncomeExpense(int $userId, array $filters): array
    {
        // Filtre yoksa bu ay varsayılan
        if (empty($filters['date_from'])) {
            $filters['date_from'] = now()->startOfMonth()->toDateString();
            $filters['date_to']   = now()->toDateString();
        }

        $data = $this->reportService->incomeExpenseSummary($userId, $filters);

        $text = sprintf(
            "Gelir-Gider özeti (%s – %s): Toplam gelir ₺%s, toplam gider ₺%s. " .
            "Net sonuç: ₺%s.",
            $filters['date_from'] ?? '-',
            $filters['date_to'] ?? '-',
            number_format($data['total_income'] ?? 0, 2, ',', '.'),
            number_format($data['total_expense'] ?? 0, 2, ',', '.'),
            number_format($data['net_result'] ?? 0, 2, ',', '.')
        );

        return ['text' => $text, 'data' => $data];
    }

    public function answerStockInventory(int $userId, array $filters): array
    {
        $data = $this->reportService->stockInventoryValue($userId, $filters);

        $text = sprintf(
            "Stok durumu: Toplam %s adet ürün, toplam envanter değeri ₺%s. " .
            "Kritik stok uyarısı olan %d ürün mevcut.",
            number_format($data['total_quantity'] ?? 0, 0, ',', '.'),
            number_format($data['total_inventory_value'] ?? 0, 2, ',', '.'),
            $data['low_stock_count'] ?? 0
        );

        return ['text' => $text, 'data' => $data];
    }

    public function answerPartyBalances(int $userId, array $filters): array
    {
        $data = $this->reportService->partyBalanceSummary($userId, $filters);

        $text = sprintf(
            "Cari bakiye özeti: Toplam alacak bakiyesi ₺%s, toplam borç bakiyesi ₺%s. " .
            "En yüksek alacak: %s (₺%s). En yüksek borç: %s (₺%s).",
            number_format($data['total_receivable_balance'] ?? 0, 2, ',', '.'),
            number_format($data['total_payable_balance'] ?? 0, 2, ',', '.'),
            $data['top_debtors'][0]['party_name'] ?? '-',
            number_format($data['top_debtors'][0]['balance'] ?? 0, 2, ',', '.'),
            $data['top_creditors'][0]['party_name'] ?? '-',
            number_format($data['top_creditors'][0]['balance'] ?? 0, 2, ',', '.')
        );

        return ['text' => $text, 'data' => $data];
    }

    // ─── FALLBACK ────────────────────────────────────────────────────────

    public function buildFallbackAnswer(string $query): array
    {
        return [
            'text' => 'Sorunuzu tam olarak anlayamadım. Nakit akışı, alacaklar, borçlar, ' .
                'gelir-gider, stok veya cari bakiye hakkında sorular sorabilirsiniz.',
            'data' => ['fallback' => true, 'original_query' => $query],
        ];
    }

    // ─── KAYNAK BİLGİSİ ─────────────────────────────────────────────────

    public function buildSources(string $intent, array $filters): array
    {
        $methodMap = [
            'executive_summary' => 'executiveSummary',
            'cash_flow'         => 'cashFlowForecast',
            'receivables_aging' => 'receivablesAging',
            'payables_aging'    => 'payablesAging',
            'income_expense'    => 'incomeExpenseSummary',
            'stock_inventory'   => 'stockInventoryValue',
            'party_balances'    => 'partyBalanceSummary',
        ];

        if (!isset($methodMap[$intent])) {
            return [];
        }

        return [[
            'service'      => 'ReportService',
            'method'       => $methodMap[$intent],
            'generated_at' => now()->toDateTimeString(),
            'filters'      => $filters,
        ]];
    }

    // ─── ÖNERİ ÜRETME ────────────────────────────────────────────────────

    public function buildSuggestions(string $intent, array $data): array
    {
        $suggestions = [];

        if ($intent === 'receivables_aging') {
            $overdue90 = $data['days_90_plus'] ?? 0;
            if ($overdue90 > 0) {
                $suggestions[] = [
                    'type'        => 'risk',
                    'severity'    => 'warning',
                    'title'       => '90+ gün geciken alacak mevcut',
                    'description' => sprintf(
                        '₺%s tutarında 90 günü aşmış alacak var. Takip sürecini başlatın.',
                        number_format($overdue90, 2, ',', '.')
                    ),
                ];
            }
        }

        if ($intent === 'cash_flow') {
            $closing = $data['projected_closing_balance'] ?? 0;
            if ($closing < 0) {
                $suggestions[] = [
                    'type'        => 'risk',
                    'severity'    => 'critical',
                    'title'       => 'Nakit açığı riski',
                    'description' => 'Öngörülen kapanış nakiti negatif. Tahsilat önceliklendirilmeli.',
                ];
            }
        }

        if ($intent === 'stock_inventory') {
            $critical = $data['low_stock_count'] ?? 0;
            if ($critical > 0) {
                $suggestions[] = [
                    'type'        => 'risk',
                    'severity'    => 'warning',
                    'title'       => sprintf('%d ürün kritik stok seviyesinde', $critical),
                    'description' => 'Tedarik planlaması yapılmasını öneririz.',
                ];
            }
        }

        if ($intent === 'income_expense') {
            $net = $data['net_result'] ?? 0;
            if ($net < 0) {
                $suggestions[] = [
                    'type'        => 'risk',
                    'severity'    => 'warning',
                    'title'       => 'Dönem zararı',
                    'description' => 'Bu dönem giderler geliri aştı. Gider kalemlerini gözden geçirin.',
                ];
            }
        }

        // Genel follow-up önerisi
        if (in_array($intent, ['executive_summary', 'unknown'], true)) {
            $suggestions[] = [
                'type'        => 'follow_up',
                'severity'    => 'info',
                'title'       => 'Daha fazla detay için',
                'description' => 'Nakit akışı, alacak yaşlandırma veya gelir-gider raporlarını ayrı sorgulayabilirsiniz.',
            ];
        }

        return $suggestions;
    }

    // ─── GUARD: İŞLEM İSTEĞİ ─────────────────────────────────────────────

    private function isActionRequest(string $queryText): bool
    {
        $lower = mb_strtolower($queryText, 'UTF-8');
        foreach (self::ACTION_KEYWORDS as $keyword) {
            if (Str::contains($lower, $keyword)) {
                return true;
            }
        }
        return false;
    }

    // ─── GUARD: TENANT CONTEXT DOĞRULAMA ─────────────────────────────────

    private function validateContext(int $userId, array $context): void
    {
        if (!empty($context['party_id'])) {
            $exists = Party::where('user_id', $userId)->where('id', $context['party_id'])->exists();
            if (!$exists) {
                throw new InvalidArgumentException('Belirtilen cari bu kullanıcıya ait değil.');
            }
        }

        if (!empty($context['legal_entity_id'])) {
            $exists = LegalEntity::where('user_id', $userId)->where('id', $context['legal_entity_id'])->exists();
            if (!$exists) {
                throw new InvalidArgumentException('Belirtilen şirket bu kullanıcıya ait değil.');
            }
        }

        if (!empty($context['warehouse_id'])) {
            $exists = Warehouse::where('user_id', $userId)->where('id', $context['warehouse_id'])->exists();
            if (!$exists) {
                throw new InvalidArgumentException('Belirtilen depo bu kullanıcıya ait değil.');
            }
        }
    }

    private function areFiltersEqual(array $a, array $b): bool
    {
        ksort($a);
        ksort($b);
        return $a === $b;
    }
}
