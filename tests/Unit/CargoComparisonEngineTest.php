<?php

namespace Tests\Unit;

use App\Models\MpOperationalOrder;
use App\Services\CargoComparisonEngine;
use App\Services\ExcelService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CargoComparisonEngineTest extends TestCase
{
    public function test_it_groups_duplicate_cargo_rows_without_repeating_same_shipment_amount(): void
    {
        $engine = new TestableCargoComparisonEngine();

        $rows = new Collection([
            [
                'web_siparis_kodu' => '7270019504993615',
                'takip_no' => '19733254218587',
                'alici' => 'Emine Kilic',
                'gonderen' => 'Zem Dayanikli',
                'adet' => 1,
                'desi' => 25.0,
                'tutar' => 515.84,
                'fatura_tarihi' => '2025-01-02',
                'teslim_tarihi' => '2025-01-03',
                '_row_number' => 2,
                'barkod' => '01059677313',
            ],
            [
                'web_siparis_kodu' => '7270019504993615',
                'takip_no' => '19733254218587',
                'alici' => 'Emine Kilic',
                'gonderen' => 'Zem Dayanikli',
                'adet' => 1,
                'desi' => 25.0,
                'tutar' => 515.84,
                'fatura_tarihi' => '2025-01-02',
                'teslim_tarihi' => '2025-01-03',
                '_row_number' => 3,
                'barkod' => '01059677314',
            ],
            [
                'web_siparis_kodu' => '7270019504993615',
                'takip_no' => '11421734441861',
                'alici' => 'Emine Kilic',
                'gonderen' => 'Zem Dayanikli',
                'adet' => 1,
                'desi' => 29.0,
                'tutar' => 283.72,
                'fatura_tarihi' => '2025-01-03',
                'teslim_tarihi' => '2025-01-04',
                '_row_number' => 4,
                'barkod' => '01059677434',
            ],
        ]);

        $buckets = $engine->groupBuckets($rows);

        $this->assertCount(1, $buckets);

        $bucket = $buckets->first();

        $this->assertSame('7270019504993615', $bucket['web_siparis_kodu']);
        $this->assertSame(3, $bucket['adet']);
        $this->assertSame(79.0, (float) $bucket['desi']);
        $this->assertEqualsWithDelta(799.56, (float) $bucket['tutar'], 0.001);
        $this->assertCount(2, $bucket['tracking_numbers']);
    }

    public function test_it_detects_returns_by_the_dominant_sender_profile(): void
    {
        $engine = new TestableCargoComparisonEngine();

        $rows = new Collection([
            [
                'gonderen' => 'Zem Dayanikli',
                'borclu_unvan' => 'Zem Dayanikli',
                'alici' => 'Musteri Bir',
                'web_siparis_kodu' => 'ORD-1',
            ],
            [
                'gonderen' => 'Zem Dayanikli',
                'borclu_unvan' => 'Zem Dayanikli',
                'alici' => 'Musteri Iki',
                'web_siparis_kodu' => 'ORD-2',
            ],
            [
                'gonderen' => 'Musteri Uc',
                'borclu_unvan' => '',
                'alici' => 'Zem Dayanikli',
                'web_siparis_kodu' => '',
            ],
        ]);

        $dominantSender = $engine->dominantSender($rows);

        $this->assertSame('Zem Dayanikli', $dominantSender);
        $this->assertTrue($engine->isOutgoing($rows[0], $dominantSender));
        $this->assertFalse($engine->isOutgoing($rows[2], $dominantSender));
    }

    public function test_it_matches_surat_web_order_code_with_marketplace_package_number(): void
    {
        $engine = new TestableCargoComparisonEngine();

        $order = new MpOperationalOrder();
        $order->id = 1;
        $order->order_number = '10026735721';
        $order->package_number = '7270019504993615';
        $order->tracking_number = '19733254218587';
        $order->customer_name = 'Emine Kilic';
        $order->customer_city = 'Sakarya';
        $order->order_date = '2025-01-02';

        [$matchedOrder, $matchType] = $engine->matchBucket([
            'web_siparis_kodu' => '7270019504993615',
            'tracking_numbers' => ['19733254218587'],
            'alici' => 'Emine Kilic',
            'alici_il' => 'Sakarya',
            'fatura_tarihi' => '2025-01-02',
        ], new Collection([$order]));

        $this->assertSame('package_number', $matchType);
        $this->assertSame('10026735721', $matchedOrder?->order_number);
    }

    public function test_it_parses_decimal_values_from_both_dot_and_turkish_formats(): void
    {
        $engine = new TestableCargoComparisonEngine();

        $this->assertEqualsWithDelta(515.84, $engine->parseNumeric('515.84'), 0.0001);
        $this->assertEqualsWithDelta(1049.90, $engine->parseNumeric('1.049,90'), 0.0001);
        $this->assertEqualsWithDelta(12.5, $engine->parseNumeric('12,5'), 0.0001);
        $this->assertEqualsWithDelta(1234.0, $engine->parseNumeric('1,234'), 0.0001);
    }
}

class TestableCargoComparisonEngine extends CargoComparisonEngine
{
    public function __construct()
    {
        parent::__construct(new ExcelService());
    }

    public function groupBuckets(Collection $rows): Collection
    {
        return $this->groupCargoCompareBuckets($rows);
    }

    public function dominantSender(Collection $rows): ?string
    {
        return $this->detectDominantSender($rows);
    }

    public function isOutgoing(array $row, ?string $dominantSender = null): bool
    {
        return $this->isOutgoingCargoRow($row, $dominantSender);
    }

    public function matchBucket(array $bucket, Collection $orders): array
    {
        return $this->findMarketplaceOrderForBucket(
            $bucket,
            $orders->keyBy(fn(MpOperationalOrder $order) => $this->normalizeOrderNumber($order->order_number)),
            $this->buildOrderIndex($orders, fn(MpOperationalOrder $order) => [$this->normalizeOrderNumber($order->package_number)]),
            $this->buildOrderIndex($orders, fn(MpOperationalOrder $order) => [
                $this->normalizeTrackingNumber($order->tracking_number),
                $this->normalizeTrackingNumber($order->second_tracking_number),
            ]),
            $this->buildOrderIndex($orders, fn(MpOperationalOrder $order) => [$this->normalizeOrderNumber($order->cargo_code)]),
            $orders->groupBy(fn(MpOperationalOrder $order) => $this->normalizeCustomerName((string) $order->customer_name)),
            []
        );
    }

    public function parseNumeric($value): float
    {
        return $this->parsePrice($value);
    }
}
