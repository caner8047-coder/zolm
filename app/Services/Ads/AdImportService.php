<?php

namespace App\Services\Ads;

use App\Models\AdImportBatch;
use App\Models\AdImportRow;
use App\Enums\AdImportStatus;
use App\Services\Ads\Parsers\ProductGeneralReportParser;
use App\Services\Ads\Parsers\ProductCampaignReportParser;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class AdImportService
{
    public function __construct(
        protected AdCampaignMatcher $campaignMatcher,
        protected AdNumberParser $numberParser,
        protected AdDateParser $dateParser,
    ) {}

    /**
     * Parse import batch - ham Excel satırlarını normalize eder
     */
    public function parseImportBatch(int $batchId, string $filePath, ?int $userId = null): void
    {
        $batch = AdImportBatch::query()
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->findOrFail($batchId);

        $batch->update(['status' => AdImportStatus::Parsing->value]);
        $batch->adImportRows()->delete();

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();

            $headerRow = null;
            $headerMap = [];
            $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestColumn());

            // İlk 15 satırda başlık ara
            for ($i = 1; $i <= min(15, $sheet->getHighestRow()); $i++) {
                $rowData = [];
                for ($col = 1; $col <= $highestColumn; $col++) {
                    $cellValue = $sheet->getCell([$col, $i])->getValue();
                    $rowData[] = $this->cleanCellValue($cellValue);
                }

                // Başlık satırını tanımla
                if ($headerRow === null && $this->isHeaderRow($rowData, $batch->import_type)) {
                    $headerRow = $i;
                    $headerMap = $rowData;
                    break;
                }
            }

            if ($headerRow === null) {
                throw new \RuntimeException('Excel dosyasında başlık satırı bulunamadı.');
            }

            // Veri satırlarını oku
            $rowNumber = 0;
            for ($i = $headerRow + 1; $i <= $sheet->getHighestRow(); $i++) {
                $rowNumber++;
                $rowData = [];
                for ($col = 1; $col <= $highestColumn; $col++) {
                    $cellValue = $sheet->getCell([$col, $i])->getValue();
                    $rowData[] = $this->cleanCellValue($cellValue);
                }

                // Boş satırları atla
                if (empty(array_filter($rowData))) {
                    continue;
                }

                // Kolon eşleme
                $normalized = $this->mapColumns($headerMap, $rowData, $batch->import_type);

                // Validasyon
                $errors = $this->validateRow($normalized, $batch->import_type);

                AdImportRow::create([
                    'batch_id' => $batchId,
                    'row_number' => $rowNumber,
                    'raw_payload' => array_combine($headerMap, $rowData),
                    'normalized_payload' => $normalized,
                    'validation_errors' => $errors,
                    'status' => empty($errors) ? 'valid' : 'error',
                ]);
            }

            // Toplamları güncelle
            $stats = AdImportRow::where('batch_id', $batchId)
                ->selectRaw('COUNT(*) as total, SUM(CASE WHEN status = "valid" THEN 1 ELSE 0 END) as valid, SUM(CASE WHEN status = "error" THEN 1 ELSE 0 END) as invalid')
                ->first();

            $batch->update([
                'status' => AdImportStatus::PreviewReady->value,
                'row_count' => $stats->total ?? 0,
                'valid_row_count' => $stats->valid ?? 0,
                'invalid_row_count' => $stats->invalid ?? 0,
            ]);

        } catch (\Exception $e) {
            $batch->update([
                'status' => AdImportStatus::Failed->value,
                'error_summary' => ['message' => $e->getMessage()],
            ]);

            throw $e;
        }
    }

    /**
     * Import'u çalıştır - normalize edilmiş verileri snapshot tablolarına yazar
     */
    public function executeImport(int $batchId, ?int $userId = null): void
    {
        $batch = AdImportBatch::query()
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->findOrFail($batchId);

        if ($batch->status !== AdImportStatus::PreviewReady->value) {
            throw new \RuntimeException('Yalnızca önizlemesi hazır bir dosya içe aktarılabilir.');
        }

        if ($batch->valid_row_count < 1) {
            throw new \RuntimeException('İçe aktarılabilecek geçerli satır bulunamadı.');
        }

        // Source fingerprint hesapla
        $fingerprint = $this->calculateSourceFingerprint($batch);

        // Fingerprint duplicate kontrolü
        $existingBatch = AdImportBatch::where('user_id', $batch->user_id)
            ->where('source_fingerprint', $fingerprint)
            ->where('id', '!=', $batch->id)
            ->where('status', 'imported')
            ->first();

        if ($existingBatch) {
            $batch->update([
                'status' => AdImportStatus::Duplicate->value,
                'duplicate_of_batch_id' => $existingBatch->id,
            ]);
            return;
        }

        $batch->update(['source_fingerprint' => $fingerprint]);

        DB::beginTransaction();

        try {
            // Geçerli satırları al
            $validRows = AdImportRow::where('batch_id', $batchId)
                ->where('status', 'valid')
                ->get();

            // Parse türüne göre işle
            match ($batch->import_type) {
                'product_general' => $this->processProductGeneralReport($batch, $validRows),
                'product_campaign' => $this->processProductCampaignReport($batch, $validRows),
                'store_keyword' => $this->processStoreKeywordReport($batch, $validRows),
                'influencer' => $this->processInfluencerReport($batch, $validRows),
                default => throw new \RuntimeException("Desteklenmeyen import türü: {$batch->import_type}"),
            };

            $batch->update(['status' => AdImportStatus::Imported->value]);

            DB::commit();

            // Audit log
            \App\Models\AdAuditLog::log(
                'import',
                'AdImportBatch',
                $batch->id,
                "{$batch->import_type} raporu başarıyla içe aktarıldı. {$batch->valid_row_count} satır işlendi."
            );

        } catch (\Exception $e) {
            DB::rollBack();

            $batch->update([
                'status' => AdImportStatus::Failed->value,
                'error_summary' => ['message' => $e->getMessage()],
            ]);

            throw $e;
        }
    }

    /**
     * Source fingerprint hesaplama
     */
    protected function calculateSourceFingerprint(AdImportBatch $batch): string
    {
        $contentHash = $this->calculateNormalizedContentHash($batch);

        $data = implode('|', [
            $batch->ad_account_id,
            $batch->import_type,
            $batch->report_period_start->format('Y-m-d'),
            $batch->report_period_end->format('Y-m-d'),
            $batch->campaign_id_context ?? '',
            $contentHash,
        ]);

        return hash('sha256', $data);
    }

    /**
     * Canonical JSON ile deterministik content hash
     */
    protected function calculateNormalizedContentHash(AdImportBatch $batch): string
    {
        $rows = AdImportRow::where('batch_id', $batch->id)
            ->where('status', 'valid')
            ->orderBy('row_number')
            ->get()
            ->map(fn($row) => $row->normalized_payload)
            ->toArray();

        // Canonical JSON: sabit sıralama, trim, normalize
        $canonical = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return hash('sha256', $canonical);
    }

    /**
     * Ürün Reklamları Genel Rapor işleme
     */
    protected function processProductGeneralReport(AdImportBatch $batch, $rows): void
    {
        $parser = app(ProductGeneralReportParser::class);

        foreach ($rows as $row) {
            $data = $parser->parse($row->normalized_payload);

            // Kampanya bul veya oluştur
            $campaign = $this->campaignMatcher->findOrCreate(
                $batch->user_id,
                $batch->ad_account_id,
                $data['campaign_name'],
                $data['channel_code'] ?? $batch->channel_code,
                $data['start_at'] ?? null,
                $data['external_campaign_id'] ?? null
            );

            // Snapshot oluştur
            $this->createCampaignSnapshot($batch, $campaign, $data);
        }
    }

    /**
     * Ürün Reklamları Kampanya-Ürün Rapor işleme
     */
    protected function processProductCampaignReport(AdImportBatch $batch, $rows): void
    {
        $parser = app(ProductCampaignReportParser::class);

        // Kampanya bağlamı zorunlu
        if (!$batch->campaign_id_context) {
            throw new \RuntimeException('Kampanya-Ürün raporu için kampanya seçimi zorunludur.');
        }

        $campaign = $this->campaignMatcher->findById($batch->campaign_id_context, $batch->user_id);

        foreach ($rows as $row) {
            $data = $parser->parse($row->normalized_payload);

            // Ürün eşleştir
            $product = $this->matchProduct($campaign, $data);

            // Product snapshot oluştur
            $this->createProductSnapshot($batch, $campaign, $product, $data);
        }
    }

    /**
     * Mağaza reklamları kelime raporunu işle.
     */
    protected function processStoreKeywordReport(AdImportBatch $batch, $rows): void
    {
        if (!$batch->campaign_id_context) {
            throw new \RuntimeException('Kelime raporu için kampanya seçimi zorunludur.');
        }

        $campaign = $this->campaignMatcher->findById($batch->campaign_id_context, $batch->user_id);

        foreach ($rows as $row) {
            $data = $row->normalized_payload;
            $keyword = trim((string) ($data['keyword'] ?? ''));
            $spend = $this->numberParser->parse($data['spend'] ?? 0);
            $revenue = $this->numberParser->parse($data['revenue_total'] ?? 0);

            \App\Models\AdKeywordSnapshot::create([
                'campaign_id' => $campaign->id,
                'import_batch_id' => $batch->id,
                'keyword' => $keyword,
                'normalized_keyword' => mb_strtolower(preg_replace('/\s+/u', ' ', $keyword)),
                'match_type' => $data['match_type'] ?? null,
                'period_start' => $batch->report_period_start,
                'period_end' => $batch->report_period_end,
                'captured_at' => now(),
                'spend' => $spend,
                'impressions' => (int) ($data['impressions'] ?? 0),
                'clicks' => (int) ($data['clicks'] ?? 0),
                'ctr' => $this->numberParser->parse($data['ctr'] ?? 0),
                'sales_total' => (int) ($data['sales_total'] ?? 0),
                'revenue_total' => $revenue,
                'roas' => $this->calculateRoas($revenue, $spend),
                'recommended_gbm' => $this->numberParser->parse($data['recommended_gbm'] ?? null),
                'selected_gbm' => $this->numberParser->parse($data['selected_gbm'] ?? null),
                'actual_gbm' => $this->numberParser->parse($data['actual_gbm'] ?? null),
                'actual_cpc' => $this->numberParser->parse($data['actual_cpc'] ?? null),
            ]);
        }
    }

    /**
     * Influencer performans raporunu işle.
     */
    protected function processInfluencerReport(AdImportBatch $batch, $rows): void
    {
        if (!$batch->campaign_id_context) {
            throw new \RuntimeException('Influencer raporu için kampanya seçimi zorunludur.');
        }

        $campaign = $this->campaignMatcher->findById($batch->campaign_id_context, $batch->user_id);

        foreach ($rows as $row) {
            $data = $row->normalized_payload;
            $handle = ltrim(trim((string) ($data['handle'] ?? '')), '@');
            $platform = mb_strtolower(trim((string) ($data['platform'] ?? 'unknown')));

            $profile = \App\Models\InfluencerProfile::firstOrCreate(
                [
                    'user_id' => $batch->user_id,
                    'platform' => $platform ?: 'unknown',
                    'handle' => $handle,
                ],
                ['display_name' => $data['display_name'] ?? null]
            );

            \App\Models\InfluencerCampaignMember::firstOrCreate([
                'campaign_id' => $campaign->id,
                'influencer_profile_id' => $profile->id,
            ]);

            \App\Models\InfluencerCreatorSnapshot::create([
                'campaign_id' => $campaign->id,
                'influencer_profile_id' => $profile->id,
                'import_batch_id' => $batch->id,
                'period_start' => $batch->report_period_start,
                'period_end' => $batch->report_period_end,
                'captured_at' => now(),
                'link_visits' => (int) ($data['link_visits'] ?? 0),
                'sales_total' => (int) ($data['sales_total'] ?? 0),
                'revenue_total' => $this->numberParser->parse($data['revenue_total'] ?? 0),
                'new_customers' => (int) ($data['new_customers'] ?? 0),
                'estimated_payment' => $this->numberParser->parse($data['estimated_payment'] ?? null),
                'actual_payment' => $this->numberParser->parse($data['actual_payment'] ?? null),
            ]);
        }
    }

    /**
     * Kampanya snapshot oluştur
     */
    protected function createCampaignSnapshot(AdImportBatch $batch, $campaign, array $data): void
    {
        \App\Models\AdCampaignSnapshot::create([
            'campaign_id' => $campaign->id,
            'import_batch_id' => $batch->id,
            'metric_type' => 'period_total',
            'period_start' => $batch->report_period_start,
            'period_end' => $batch->report_period_end,
            'captured_at' => now(),
            'spend' => $this->numberParser->parse($data['spend'] ?? 0),
            'impressions' => (int) ($data['impressions'] ?? 0),
            'clicks' => (int) ($data['clicks'] ?? 0),
            'ctr' => $this->numberParser->parse($data['ctr'] ?? 0),
            'sales_direct' => (int) ($data['sales_direct'] ?? 0),
            'sales_indirect' => (int) ($data['sales_indirect'] ?? 0),
            'sales_total' => (int) ($data['sales_total'] ?? 0),
            'revenue_direct' => $this->numberParser->parse($data['revenue_direct'] ?? 0),
            'revenue_indirect' => $this->numberParser->parse($data['revenue_indirect'] ?? 0),
            'revenue_total' => $this->numberParser->parse($data['revenue_total'] ?? 0),
            'roas' => $this->calculateRoas(
                $this->numberParser->parse($data['revenue_total'] ?? 0),
                $this->numberParser->parse($data['spend'] ?? 0)
            ),
            'actual_cpc' => isset($data['actual_cpc']) ? $this->numberParser->parse($data['actual_cpc']) : null,
            'daily_budget' => isset($data['daily_budget']) ? $this->numberParser->parse($data['daily_budget']) : null,
            'remaining_budget' => isset($data['remaining_budget']) ? $this->numberParser->parse($data['remaining_budget']) : null,
        ]);
    }

    /**
     * Ürün snapshot oluştur
     */
    protected function createProductSnapshot(AdImportBatch $batch, $campaign, $product, array $data): void
    {
        \App\Models\AdProductSnapshot::create([
            'campaign_id' => $campaign->id,
            'ad_campaign_product_id' => $product->id,
            'import_batch_id' => $batch->id,
            'metric_type' => 'period_total',
            'period_start' => $batch->report_period_start,
            'period_end' => $batch->report_period_end,
            'captured_at' => now(),
            'spend' => $this->numberParser->parse($data['spend'] ?? 0),
            'impressions' => (int) ($data['impressions'] ?? 0),
            'clicks' => (int) ($data['clicks'] ?? 0),
            'ctr' => $this->numberParser->parse($data['ctr'] ?? 0),
            'sales_direct' => (int) ($data['sales_direct'] ?? 0),
            'sales_indirect' => (int) ($data['sales_indirect'] ?? 0),
            'sales_total' => (int) ($data['sales_total'] ?? 0),
            'revenue_direct' => $this->numberParser->parse($data['revenue_direct'] ?? 0),
            'revenue_indirect' => $this->numberParser->parse($data['revenue_indirect'] ?? 0),
            'revenue_total' => $this->numberParser->parse($data['revenue_total'] ?? 0),
            'roas' => $this->calculateRoas(
                $this->numberParser->parse($data['revenue_total'] ?? 0),
                $this->numberParser->parse($data['spend'] ?? 0)
            ),
        ]);
    }

    /**
     * Ürün eşleştirme
     */
    protected function matchProduct(\App\Models\AdCampaign $campaign, array $data): \App\Models\AdCampaignProduct
    {
        $contentId = $data['content_id'] ?? null;

        // Content ID varsa mevcut kaydı bul
        if ($contentId) {
            $existing = \App\Models\AdCampaignProduct::where('campaign_id', $campaign->id)
                ->where('marketplace_content_id', $contentId)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        // Yeni ürün oluştur
        return \App\Models\AdCampaignProduct::create([
            'campaign_id' => $campaign->id,
            'marketplace_content_id' => $contentId,
            'marketplace_model_code' => $data['model_code'] ?? null,
            'product_name_snapshot' => $data['product_name'] ?? 'Bilinmeyen Ürün',
        ]);
    }

    /**
     * Kolon eşleme
     */
    protected function mapColumns(array $headers, array $row, string $importType): array
    {
        $aliasMap = $this->getColumnAliasMap($importType);
        $result = [];

        foreach ($row as $index => $value) {
            $header = $headers[$index] ?? null;
            if ($header && isset($aliasMap[$header])) {
                $result[$aliasMap[$header]] = $value;
            }
        }

        return $result;
    }

    /**
     * Kolon alias haritası
     */
    protected function getColumnAliasMap(string $importType): array
    {
        return match ($importType) {
            'product_general' => [
                'Reklam Adı' => 'campaign_name',
                'Reklam Statüsü' => 'status',
                'Başlangıç Tarihi' => 'start_at',
                'Bitiş Tarihi' => 'end_at',
                'Ürün Adedi' => 'product_count',
                'Toplam Bütçe' => 'total_budget',
                'Günlük Bütçe' => 'daily_budget',
                'Kalan Bütçe' => 'remaining_budget',
                'Harcanan Bütçe' => 'spend',
                'TBM Teklifi' => 'bid',
                'Gerçekleşen TBM' => 'actual_cpc',
                'Tıklanma' => 'clicks',
                'Görüntülenme' => 'impressions',
                'Doğrudan Satış Adedi' => 'sales_direct',
                'Dolaylı Satış Adedi' => 'sales_indirect',
                'Toplam Satış Adedi' => 'sales_total',
                'Doğrudan Reklam Cirosu' => 'revenue_direct',
                'Dolaylı Reklam Cirosu' => 'revenue_indirect',
                'Toplam Reklam Cirosu' => 'revenue_total',
                'Harcama Getirisi' => 'roas',
            ],
            'product_campaign' => [
                'Ürün Bilgisi' => 'product_name',
                'Content Id' => 'content_id',
                'Model Kodu' => 'model_code',
                'Harcanan Bütçe' => 'spend',
                'Gösterim Sayısı' => 'impressions',
                'Tıklanma Sayısı' => 'clicks',
                'Tıklanma Oranı' => 'ctr',
                'Doğrudan Satış Adedi' => 'sales_direct',
                'Dolaylı Satış Adedi' => 'sales_indirect',
                'Toplam Satış Adedi' => 'sales_total',
                'Doğrudan Reklam Cirosu' => 'revenue_direct',
                'Dolaylı Reklam Cirosu' => 'revenue_indirect',
                'Toplam Reklam Cirosu' => 'revenue_total',
                'Harcama Getirisi' => 'roas',
            ],
            'store_keyword' => [
                'Anahtar Kelime' => 'keyword',
                'Kelime' => 'keyword',
                'Eşleme Tipi' => 'match_type',
                'Harcanan Bütçe' => 'spend',
                'Harcama' => 'spend',
                'Gösterim Sayısı' => 'impressions',
                'Gösterim' => 'impressions',
                'Tıklanma Sayısı' => 'clicks',
                'Tıklanma' => 'clicks',
                'Tıklanma Oranı' => 'ctr',
                'Toplam Satış Adedi' => 'sales_total',
                'Satış Adedi' => 'sales_total',
                'Toplam Reklam Cirosu' => 'revenue_total',
                'Ciro' => 'revenue_total',
                'Önerilen GBM' => 'recommended_gbm',
                'Seçilen GBM' => 'selected_gbm',
                'Gerçekleşen GBM' => 'actual_gbm',
                'Gerçekleşen TBM' => 'actual_cpc',
            ],
            'influencer' => [
                'Kullanıcı Adı' => 'handle',
                'Influencer' => 'handle',
                'İçerik Üreticisi' => 'handle',
                'Görünen Ad' => 'display_name',
                'Platform' => 'platform',
                'Link Ziyareti' => 'link_visits',
                'Ziyaret' => 'link_visits',
                'Toplam Satış Adedi' => 'sales_total',
                'Satış Adedi' => 'sales_total',
                'Toplam Ciro' => 'revenue_total',
                'Ciro' => 'revenue_total',
                'Yeni Müşteri' => 'new_customers',
                'Tahmini Ödeme' => 'estimated_payment',
                'Gerçekleşen Ödeme' => 'actual_payment',
            ],
            default => [],
        };
    }

    /**
     * Başlık satırı kontrolü
     */
    protected function isHeaderRow(array $row, string $importType): bool
    {
        $keywords = match ($importType) {
            'product_general' => ['reklam', 'bütçe', 'tıklanma'],
            'product_campaign' => ['ürün', 'content', 'harcama'],
            'store_keyword' => ['kelime', 'anahtar'],
            'influencer' => ['kullanıcı', 'ziyaret', 'ciro'],
            default => [],
        };

        $rowText = mb_strtolower(implode(' ', array_filter($row)));
        $matches = 0;

        foreach ($keywords as $keyword) {
            if (str_contains($rowText, $keyword)) {
                $matches++;
            }
        }

        return $matches >= 2;
    }

    /**
     * Hücre değerini temizle
     */
    protected function cleanCellValue($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (string) $value;
        $value = trim($value);

        // UTF-8 kontrolü
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1254');
        }

        // Kontrol karakterlerini temizle (tab ve newline hariç)
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

        return $value ?: null;
    }

    /**
     * Satır validasyonu
     */
    protected function validateRow(array $row, string $importType): array
    {
        $errors = [];

        if (in_array($importType, ['product_general', 'product_campaign'], true)) {
            if ($importType === 'product_general' && empty($row['campaign_name'])) {
                $errors[] = 'Kampanya adı bulunamadı.';
            }

            if ($importType === 'product_campaign' && empty($row['product_name']) && empty($row['content_id'])) {
                $errors[] = 'Ürün bilgisi bulunamadı.';
            }

            if (empty($row['spend']) && empty($row['clicks']) && empty($row['impressions'])) {
                $errors[] = 'Harcama, tıklama veya görüntülenme verisi bulunamadı.';
            }
        }

        if ($importType === 'store_keyword') {
            if (empty($row['keyword'])) {
                $errors[] = 'Anahtar kelime bulunamadı.';
            }

            if (empty($row['spend']) && empty($row['clicks']) && empty($row['impressions'])) {
                $errors[] = 'Kelime performans verisi bulunamadı.';
            }
        }

        if ($importType === 'influencer') {
            if (empty($row['handle'])) {
                $errors[] = 'Influencer kullanıcı adı bulunamadı.';
            }

            if (empty($row['link_visits']) && empty($row['sales_total']) && empty($row['revenue_total'])) {
                $errors[] = 'Influencer performans verisi bulunamadı.';
            }
        }

        return $errors;
    }

    /**
     * ROAS hesaplama
     */
    protected function calculateRoas(float $revenue, float $spend): float
    {
        if ($spend <= 0) {
            return 0;
        }
        return round($revenue / $spend, 4);
    }
}
