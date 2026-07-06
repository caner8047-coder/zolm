<?php

namespace App\Services\Ads\Parsers;

use App\Services\Ads\AdNumberParser;

class ProductCampaignReportParser
{
    public function __construct(
        protected AdNumberParser $numberParser,
    ) {}

    /**
     * Ham normalize edilmiş satırı parsed data'ya dönüştür
     */
    public function parse(array $row): array
    {
        return [
            'product_name' => $row['product_name'] ?? null,
            'content_id' => $row['content_id'] ?? null,
            'model_code' => $row['model_code'] ?? null,
            'spend' => $this->numberParser->parse($row['spend'] ?? null),
            'impressions' => (int) ($row['impressions'] ?? 0),
            'clicks' => (int) ($row['clicks'] ?? 0),
            'ctr' => $this->numberParser->parse($row['ctr'] ?? null),
            'sales_direct' => (int) ($row['sales_direct'] ?? 0),
            'sales_indirect' => (int) ($row['sales_indirect'] ?? 0),
            'sales_total' => (int) ($row['sales_total'] ?? 0),
            'revenue_direct' => $this->numberParser->parse($row['revenue_direct'] ?? null),
            'revenue_indirect' => $this->numberParser->parse($row['revenue_indirect'] ?? null),
            'revenue_total' => $this->numberParser->parse($row['revenue_total'] ?? null),
            'roas' => $this->numberParser->parse($row['roas'] ?? null),
        ];
    }
}
