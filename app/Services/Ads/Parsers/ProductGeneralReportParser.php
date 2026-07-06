<?php

namespace App\Services\Ads\Parsers;

use App\Services\Ads\AdNumberParser;
use App\Services\Ads\AdDateParser;

class ProductGeneralReportParser
{
    public function __construct(
        protected AdNumberParser $numberParser,
        protected AdDateParser $dateParser,
    ) {}

    /**
     * Ham normalize edilmiş satırı parsed data'ya dönüştür
     */
    public function parse(array $row): array
    {
        return [
            'campaign_name' => $row['campaign_name'] ?? null,
            'status' => $row['status'] ?? null,
            'start_at' => $row['start_at'] ? $this->dateParser->parse($row['start_at'])?->format('Y-m-d H:i:s') : null,
            'end_at' => $row['end_at'] ? $this->dateParser->parse($row['end_at'])?->format('Y-m-d H:i:s') : null,
            'product_count' => (int) ($row['product_count'] ?? 0),
            'total_budget' => $this->numberParser->parse($row['total_budget'] ?? null),
            'daily_budget' => $this->numberParser->parse($row['daily_budget'] ?? null),
            'remaining_budget' => $this->numberParser->parse($row['remaining_budget'] ?? null),
            'spend' => $this->numberParser->parse($row['spend'] ?? null),
            'bid' => $this->numberParser->parse($row['bid'] ?? null),
            'actual_cpc' => $this->numberParser->parse($row['actual_cpc'] ?? null),
            'clicks' => (int) ($row['clicks'] ?? 0),
            'impressions' => (int) ($row['impressions'] ?? 0),
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
