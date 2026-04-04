<?php

namespace App\Models;

use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationSyncRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'sync_type',
        'trigger_type',
        'status',
        'cursor_before',
        'cursor_after',
        'started_at',
        'finished_at',
        'duration_ms',
        'items_received',
        'items_created',
        'items_updated',
        'items_skipped',
        'rate_limit_hits',
        'error_count',
        'notes_json',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'notes_json' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnostics(): array
    {
        return Arr::get($this->notes_json ?? [], 'diagnostics', []);
    }

    /**
     * @return array<int, string>
     */
    public function diagnosticsWarnings(): array
    {
        return array_values(array_filter(Arr::wrap(Arr::get($this->diagnostics(), 'warnings', []))));
    }

    public function diagnosticWarningCount(): int
    {
        return count($this->diagnosticsWarnings());
    }

    public function isSmokeTest(): bool
    {
        return $this->trigger_type === 'smoke_test' || (bool) Arr::get($this->notes_json ?? [], 'smoke_test', false);
    }

    public function triggerLabel(): string
    {
        return match ($this->trigger_type) {
            'manual' => 'Manuel',
            'schedule' => 'Zamanlı',
            'webhook' => 'Webhook',
            'retry' => 'Tekrar',
            'webhook_replay' => 'Webhook replay',
            'smoke_test' => 'Smoke test',
            default => str_replace('_', ' ', (string) $this->trigger_type),
        };
    }

    public function diagnosticsTone(): string
    {
        $diagnostics = $this->diagnostics();

        if ($diagnostics === []) {
            return 'default';
        }

        return $this->diagnosticWarningCount() > 0 ? 'warning' : 'success';
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function diagnosticMetrics(): array
    {
        $diagnostics = $this->diagnostics();

        if ($diagnostics === []) {
            return [];
        }

        $metricMap = match ($this->sync_type) {
            'orders' => [
                'package_count' => 'Paket',
                'order_count' => 'Sipariş',
                'item_count' => 'Satır',
                'missing_stock_code_count' => 'Eksik stok kodu',
                'missing_barcode_count' => 'Eksik barkod',
                'missing_item_line_id_count' => 'Eksik line id',
            ],
            'products' => [
                'product_count' => 'Ürün',
                'missing_stock_code_count' => 'Eksik stok kodu',
                'missing_barcode_count' => 'Eksik barkod',
                'missing_sale_price_count' => 'Eksik satış fiyatı',
                'missing_stock_quantity_count' => 'Eksik stok',
            ],
            'finance' => [
                'event_count' => 'Kayıt',
                'missing_order_number_count' => 'Eksik sipariş no',
                'missing_package_id_count' => 'Eksik paket id',
                'missing_amount_count' => 'Eksik tutar',
                'missing_settlement_date_count' => 'Eksik ödeme tarihi',
            ],
            default => [],
        };

        $metrics = [];

        foreach ($metricMap as $key => $label) {
            $value = Arr::get($diagnostics, $key);

            if ($value === null) {
                continue;
            }

            $metrics[] = [
                'label' => $label,
                'value' => number_format((float) $value, 0, ',', '.'),
            ];
        }

        return $metrics;
    }
}
