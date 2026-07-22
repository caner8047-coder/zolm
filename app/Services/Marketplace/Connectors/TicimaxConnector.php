<?php

namespace App\Services\Marketplace\Connectors;

use App\Models\ChannelListing;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Contracts\PullsClaims;
use App\Services\Marketplace\Contracts\PullsFinancials;
use App\Services\Marketplace\Contracts\PullsOrders;
use App\Services\Marketplace\Contracts\PullsProducts;
use App\Services\Marketplace\Contracts\PushesPrice;
use App\Services\Marketplace\Contracts\PushesStock;
use App\Services\Marketplace\TicimaxSoapGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class TicimaxConnector extends AbstractMarketplaceConnector implements PullsClaims, PullsFinancials, PullsOrders, PullsProducts, PushesPrice, PushesStock
{
    public function __construct(protected TicimaxSoapGateway $soap) {}

    public function providerKey(): string
    {
        return 'ticimax';
    }

    public function displayName(): string
    {
        return 'Ticimax';
    }

    public function defaultApiBaseUrl(): ?string
    {
        return config('marketplace.ticimax.base_url') ?: null;
    }

    /**
     * @return array<string, bool>
     */
    public function capabilities(): array
    {
        return [
            'orders' => true,
            'products' => true,
            'finance' => true,
            'webhooks' => false,
            'price_push' => true,
            'stock_push' => true,
            'package_status' => false,
            'package_picking' => false,
            'package_invoiced' => false,
            'common_label' => false,
            'package_common_label_create' => false,
            'package_common_label_get' => false,
            'invoice_link' => false,
            'package_invoice_link' => false,
            'questions' => false,
            'question_answer' => false,
            'claims' => true,
            'claim_approve' => false,
            'claim_reject' => false,
        ];
    }

    public function testConnection(MarketplaceStore $store): array
    {
        try {
            $payload = $this->soap->call($store, 'products', 'SelectUrunCount', [
                'UyeKodu' => $this->membershipCode($store),
                'f' => $this->productFilter(),
            ]);

            return [
                'ok' => true,
                'message' => 'Ticimax Ürün Servisi bağlantısı ve Üye Kodu doğrulandı.',
                'meta' => [
                    'provider' => $this->providerKey(),
                    'product_count' => (int) ($this->resultValue($payload, 'SelectUrunCount') ?? 0),
                    'product_wsdl' => $this->soap->wsdlUrl($store, 'products'),
                    'order_wsdl' => $this->soap->wsdlUrl($store, 'orders'),
                ],
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'message' => 'Ticimax bağlantısı doğrulanamadı: '.$exception->getMessage(),
            ];
        }
    }

    public function pullOrders(MarketplaceStore $store, array $options = []): array
    {
        $result = $this->pullOrderRows($store, $options);

        return [
            'items' => collect($result['items'])
                ->map(fn (array $order) => $this->normalizeOrder($order))
                ->values()
                ->all(),
            'meta' => $this->syncMeta($result),
        ];
    }

    public function pullProducts(MarketplaceStore $store, array $options = []): array
    {
        $pageSize = min(100, max(1, (int) ($options['page_size'] ?? config('marketplace.ticimax.product_page_size', 100))));
        $maxPages = max(1, (int) ($options['max_pages'] ?? config('marketplace.ticimax.max_pages_per_sync', 50)));
        $offset = max(0, (int) ($options['offset'] ?? 0));
        $items = [];
        $pagesProcessed = 0;
        $more = false;

        do {
            $payload = $this->soap->call($store, 'products', 'SelectUrun', [
                'UyeKodu' => $this->membershipCode($store),
                'f' => $this->productFilter($options),
                's' => [
                    'BaslangicIndex' => $offset,
                    'KayitSayisi' => $pageSize,
                    'SiralamaDegeri' => 'ID',
                    'SiralamaYonu' => 'ASC',
                ],
            ]);
            $rows = $this->rowsFromResult($payload, 'SelectUrun', 'UrunKarti');

            foreach ($rows as $product) {
                foreach ($this->normalizeProduct($product, $store) as $normalized) {
                    $items[] = $normalized;
                }
            }

            $pagesProcessed++;
            $more = count($rows) >= $pageSize;
            $offset += $pageSize;
        } while ($more && $pagesProcessed < $maxPages);

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'pages_processed' => $pagesProcessed,
                'offset_after' => $offset,
                'more_pages_available' => $more,
                'cursor_after' => now()->toIso8601String(),
            ],
        ];
    }

    public function pullFinancialEvents(MarketplaceStore $store, array $options = []): array
    {
        $limit = max(1, (int) ($options['order_limit'] ?? config('marketplace.ticimax.finance_order_limit', 100)));
        $orders = array_slice($this->pullOrderRows($store, $options)['items'], 0, $limit);
        $events = [];

        foreach ($orders as $order) {
            $payments = $this->nestedRows($order, 'Odemeler', 'WebSiparisOdeme');

            if ($payments === []) {
                $payload = $this->soap->call($store, 'orders', 'SelectSiparisOdeme', [
                    'UyeKodu' => $this->membershipCode($store),
                    'siparisId' => (int) data_get($order, 'ID'),
                    'odemeId' => 0,
                ]);
                $payments = $this->rowsFromResult($payload, 'SelectSiparisOdeme', 'WebSiparisOdeme');
            }

            foreach ($payments as $payment) {
                $event = $this->normalizeFinancialEvent($payment, $order);

                if ($event !== null) {
                    $events[] = $event;
                }
            }
        }

        return [
            'items' => $events,
            'meta' => [
                'items_received' => count($events),
                'orders_scanned' => count($orders),
                'order_limit' => $limit,
                'finance_mode' => 'order_payments',
                'cursor_after' => now()->toIso8601String(),
            ],
        ];
    }

    public function pullClaims(MarketplaceStore $store, array $options = []): array
    {
        $result = $this->pullOrderRows($store, array_merge($options, ['include_cancelled_items' => true]));
        $items = collect($result['items'])
            ->filter(fn (array $order) => $this->isClaimStatus($order))
            ->map(fn (array $order) => $this->normalizeClaim($order))
            ->values()
            ->all();

        return [
            'items' => $items,
            'meta' => array_merge($this->syncMeta($result), ['claim_mode' => 'order_status']),
        ];
    }

    public function pushPrice(ChannelListing $listing, float $price, array $context = []): array
    {
        $listing->loadMissing(['store.connection', 'channelProduct']);
        $variantId = $this->resolveVariantId($listing);
        $response = $this->soap->call($listing->store, 'products', 'VaryasyonGuncelle', [
            'UyeKodu' => $this->membershipCode($listing->store),
            'urun' => [
                'ID' => $variantId,
                'SatisFiyati' => round($price, 2),
            ],
            'ayar' => [
                'SatisFiyatiGuncelle' => true,
            ],
        ]);

        return [
            'status' => 'completed',
            'provider' => $this->providerKey(),
            'listing_id' => $listing->id,
            'variant_id' => $variantId,
            'price' => round($price, 2),
            'external_action_id' => (string) $variantId,
            'response' => $response,
        ];
    }

    public function pushStock(ChannelListing $listing, int $quantity, array $context = []): array
    {
        $listing->loadMissing(['store.connection', 'channelProduct']);
        $variantId = $this->resolveVariantId($listing);
        $quantity = max(0, $quantity);
        $response = $this->soap->call($listing->store, 'products', 'StokAdediGuncelle', [
            'UyeKodu' => $this->membershipCode($listing->store),
            'urunler' => [[
                'ID' => $variantId,
                'StokAdedi' => $quantity,
            ]],
        ]);

        return [
            'status' => 'completed',
            'provider' => $this->providerKey(),
            'listing_id' => $listing->id,
            'variant_id' => $variantId,
            'quantity' => $quantity,
            'external_action_id' => (string) $variantId,
            'response' => $response,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{items: array<int, array<string, mixed>>, pages_processed: int, more_pages_available: bool, cursor_after: string}
     */
    protected function pullOrderRows(MarketplaceStore $store, array $options): array
    {
        $pageSize = min(100, max(1, (int) ($options['page_size'] ?? config('marketplace.ticimax.order_page_size', 100))));
        $maxPages = max(1, (int) ($options['max_pages'] ?? config('marketplace.ticimax.max_pages_per_sync', 50)));
        $offset = max(0, (int) ($options['offset'] ?? 0));
        $start = CarbonImmutable::parse($options['start_date'] ?? now()->subDays(7))->setTimezone('Europe/Istanbul');
        $end = CarbonImmutable::parse($options['end_date'] ?? now())->setTimezone('Europe/Istanbul');
        $items = [];
        $pagesProcessed = 0;
        $more = false;

        do {
            $payload = $this->soap->call($store, 'orders', 'SelectSiparis', [
                'UyeKodu' => $this->membershipCode($store),
                'f' => [
                    'EntegrasyonAktarildi' => -1,
                    'IptalEdilmisUrunler' => (bool) ($options['include_cancelled_items'] ?? true),
                    'OdemeDurumu' => isset($options['payment_status']) ? (int) $options['payment_status'] : -1,
                    'OdemeTipi' => -1,
                    'SiparisDurumu' => isset($options['status']) && is_numeric($options['status']) ? (int) $options['status'] : -1,
                    'SiparisID' => isset($options['order_id']) ? (int) $options['order_id'] : -1,
                    'SiparisKaynagi' => (string) ($options['source'] ?? ''),
                    'SiparisKodu' => (string) ($options['order_code'] ?? ''),
                    'SiparisTarihiBas' => $start->format('Y-m-d\TH:i:s'),
                    'SiparisTarihiSon' => $end->format('Y-m-d\TH:i:s'),
                    'StrSiparisDurumu' => is_string($options['status'] ?? null) ? (string) $options['status'] : '',
                    'TedarikciID' => -1,
                    'UyeID' => -1,
                    'SiparisNo' => (string) ($options['order_number'] ?? ''),
                    'UyeTelefon' => '',
                ],
                's' => [
                    'BaslangicIndex' => $offset,
                    'KayitSayisi' => $pageSize,
                    'SiralamaDegeri' => 'ID',
                    'SiralamaYonu' => 'DESC',
                ],
            ]);
            $rows = $this->rowsFromResult($payload, 'SelectSiparis', 'WebSiparis');

            foreach ($rows as $row) {
                $items[] = $row;
            }

            $pagesProcessed++;
            $more = count($rows) >= $pageSize;
            $offset += $pageSize;
        } while ($more && $pagesProcessed < $maxPages);

        return [
            'items' => $items,
            'pages_processed' => $pagesProcessed,
            'more_pages_available' => $more,
            'cursor_after' => $end->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function productFilter(array $options = []): array
    {
        return [
            'Aktif' => isset($options['status']) ? ((string) $options['status'] === 'active' ? 1 : 0) : -1,
            'Firsat' => -1,
            'Indirimli' => -1,
            'Vitrin' => -1,
            'KategoriID' => (int) ($options['category_id'] ?? 0),
            'MarkaID' => (int) ($options['brand_id'] ?? 0),
            'UrunKartiID' => (int) ($options['product_id'] ?? 0),
            'TedarikciID' => (int) ($options['supplier_id'] ?? 0),
        ];
    }

    protected function membershipCode(MarketplaceStore $store): string
    {
        $store->loadMissing('connection');
        $credentials = $store->connection?->credentials_encrypted ?? [];
        $code = trim((string) ($credentials['api_secret'] ?? $credentials['api_key'] ?? ''));

        if ($code === '') {
            throw new \RuntimeException('Ticimax Üye Kodu / Web Servis Şifresi zorunludur.');
        }

        return $code;
    }

    /**
     * @param  array<string, mixed>|array<int, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    protected function rowsFromResult(array $payload, string $operation, string $rowKey): array
    {
        $result = $this->resultValue($payload, $operation);

        if (is_array($result) && array_key_exists($rowKey, $result)) {
            $result = $result[$rowKey];
        }

        if (! is_array($result) || $result === []) {
            return [];
        }

        if (array_is_list($result)) {
            return array_values(array_filter($result, 'is_array'));
        }

        return [$result];
    }

    protected function resultValue(array $payload, string $operation): mixed
    {
        return data_get($payload, $operation.'Result')
            ?? data_get($payload, lcfirst($operation).'Result')
            ?? data_get($payload, 'return')
            ?? $payload;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function nestedRows(array $payload, string $container, string $rowKey): array
    {
        $rows = data_get($payload, $container, []);

        if (is_array($rows) && array_key_exists($rowKey, $rows)) {
            $rows = $rows[$rowKey];
        }

        if (! is_array($rows) || $rows === []) {
            return [];
        }

        return array_is_list($rows) ? array_values(array_filter($rows, 'is_array')) : [$rows];
    }

    protected function normalizeOrder(array $payload): array
    {
        $orderId = (string) data_get($payload, 'ID');
        $orderNumber = (string) (data_get($payload, 'SiparisKodu') ?: data_get($payload, 'SiparisNo') ?: $orderId);
        $status = $this->normalizeOrderStatus($payload);
        $shipping = Arr::wrap(data_get($payload, 'TeslimatAdresi'));
        $billing = Arr::wrap(data_get($payload, 'FaturaAdresi'));
        $orderedAt = $this->dateTime(data_get($payload, 'SiparisTarihi'));

        return [
            'order' => [
                'external_order_id' => $orderId,
                'order_number' => $orderNumber,
                'order_status' => $status,
                'commercial_type' => data_get($billing, 'isKurumsal') ? 'commercial' : 'individual',
                'currency' => Str::upper((string) (data_get($payload, 'ParaBirimi') ?: 'TRY')),
                'exchange_rate' => data_get($payload, 'Kur') ?: 1,
                'customer_name' => data_get($payload, 'AdiSoyadi') ?: trim((string) (data_get($payload, 'UyeAdi').' '.data_get($payload, 'UyeSoyadi'))),
                'customer_email' => data_get($payload, 'Mail'),
                'customer_phone' => data_get($shipping, 'AliciTelefon') ?: data_get($billing, 'AliciTelefon'),
                'billing_name' => data_get($billing, 'FirmaAdi') ?: data_get($payload, 'AdiSoyadi'),
                'billing_tax_number' => data_get($billing, 'VergiNo'),
                'shipment_country' => data_get($shipping, 'Ulke.Alpha2Code') ?: data_get($shipping, 'Ulke.Alpha3Code'),
                'shipment_city' => data_get($shipping, 'Il'),
                'shipment_district' => data_get($shipping, 'Ilce'),
                'ordered_at' => $orderedAt,
                'approved_at' => in_array($status, ['approved', 'picking', 'shipped', 'delivered'], true) ? $orderedAt : null,
                'delivered_at' => $status === 'delivered' ? $this->dateTime(data_get($payload, 'TeslimatGunu')) : null,
                'cancelled_at' => $status === 'cancelled' ? $orderedAt : null,
                'returned_at' => $status === 'returned' ? $orderedAt : null,
                'raw_payload' => $payload,
            ],
            'package' => [
                'external_package_id' => $orderId,
                'package_number' => $orderNumber,
                'package_status' => $status,
                'cargo_company' => data_get($payload, 'KargoEntegrasyonTanim') ?: data_get($payload, 'KargoFirma'),
                'cargo_tracking_number' => data_get($payload, 'KargoEntegrasyonTakipNo') ?: data_get($payload, 'KargoTakipNo'),
                'shipment_provider' => data_get($payload, 'KargoEntegrasyonID') ?: data_get($payload, 'KargoFirmaId'),
                'shipped_at' => in_array($status, ['shipped', 'delivered'], true) ? $orderedAt : null,
                'delivered_at' => $status === 'delivered' ? $this->dateTime(data_get($payload, 'TeslimatGunu')) : null,
                'raw_payload' => [
                    'order_id' => $orderId,
                    'cargo_integration_id' => data_get($payload, 'KargoEntegrasyonID'),
                ],
            ],
            'items' => collect($this->nestedRows($payload, 'Urunler', 'WebSiparisUrun'))
                ->values()
                ->map(fn (array $row, int $index) => $this->normalizeOrderLine($row, $orderId, $status, $index))
                ->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeProduct(array $payload, MarketplaceStore $store): array
    {
        $parentId = (string) data_get($payload, 'ID');
        $variants = $this->nestedRows($payload, 'Varyasyonlar', 'Varyasyon');

        if ($variants === []) {
            $variants = [$payload];
        }

        return collect($variants)->map(function (array $variant) use ($payload, $parentId, $store): array {
            $variantId = (string) (data_get($variant, 'ID') ?: $parentId);
            $active = (bool) data_get($variant, 'Aktif', data_get($payload, 'Aktif', false));

            return [
                'product' => [
                    'external_product_id' => $variantId,
                    'external_parent_id' => $variantId === $parentId ? null : $parentId,
                    'stock_code' => (string) (data_get($variant, 'StokKodu') ?: $variantId),
                    'barcode' => data_get($variant, 'Barkod'),
                    'title' => data_get($payload, 'UrunAdi'),
                    'brand' => data_get($payload, 'Marka') ?: data_get($payload, 'MarkaID'),
                    'category_name' => data_get($payload, 'AnaKategori'),
                    'vat_rate' => data_get($variant, 'KdvOrani'),
                    'description' => data_get($payload, 'Aciklama') ?: data_get($payload, 'OnYazi'),
                    'images' => data_get($variant, 'Resimler') ?: data_get($payload, 'Resimler') ?: [],
                    'attributes' => [
                        'options' => data_get($variant, 'Ozellikler', []),
                        'category_ids' => data_get($payload, 'Kategoriler', []),
                        'supplier_id' => data_get($payload, 'TedarikciID'),
                        'seo_title' => data_get($payload, 'SeoSayfaBaslik'),
                        'seo_description' => data_get($payload, 'SeoSayfaAciklama'),
                        'custom_fields' => Arr::only($payload, ['OzelAlan1', 'OzelAlan2', 'OzelAlan3', 'OzelAlan4', 'OzelAlan5']),
                    ],
                    'approval_status' => $active ? 'approved' : 'passive',
                    'is_catalog_product' => true,
                    'raw_payload' => array_merge($payload, ['variant' => $variant]),
                ],
                'listing' => array_merge([
                    'listing_id' => $variantId,
                    'listing_status' => $active ? 'active' : 'passive',
                    'sale_price' => $this->money(data_get($variant, 'SatisFiyati')),
                    'list_price' => $this->money(data_get($variant, 'PiyasaFiyati') ?? data_get($variant, 'SatisFiyati')),
                    'currency' => Str::upper((string) (data_get($variant, 'ParaBirimiKodu') ?: $store->currency ?: 'TRY')),
                    'stock_quantity' => (int) round((float) data_get($variant, 'StokAdedi', 0)),
                    'published_at' => $this->dateTime(data_get($payload, 'YayinTarihi')),
                ], $this->catalogDeliveryTermData([
                    'deliveryDurationDays' => data_get($payload, 'TahminiTeslimSuresi'),
                ], $variant, $payload)),
            ];
        })->values()->all();
    }

    protected function normalizeOrderLine(array $payload, string $orderId, string $status, int $index): array
    {
        $quantity = max(1, (int) round((float) data_get($payload, 'Adet', 1)));
        $total = $this->money(data_get($payload, 'Tutar'));
        $unitPrice = $total !== null ? round($total / $quantity, 2) : null;
        $discount = $this->money(data_get($payload, 'KampanyaIndirimTutari'));

        return [
            'external_line_id' => (string) (data_get($payload, 'ID') ?: sha1($orderId.'|'.$index)),
            'stock_code' => (string) data_get($payload, 'StokKodu'),
            'barcode' => data_get($payload, 'Barkod'),
            'product_name' => data_get($payload, 'UrunAdi'),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'gross_amount' => $total,
            'discount_amount' => $discount,
            'marketplace_discount_amount' => null,
            'billable_amount' => $total !== null ? max(0, round($total - ($discount ?? 0), 2)) : null,
            'commission_rate' => null,
            'vat_rate' => data_get($payload, 'KdvOrani'),
            'line_status' => $this->normalizeLineStatus($payload, $status),
            'raw_payload' => $payload,
        ];
    }

    protected function normalizeFinancialEvent(array $payment, array $order): ?array
    {
        $amount = $this->money(data_get($payment, 'Tutar'));

        if ($amount === null) {
            return null;
        }

        $orderStatus = $this->normalizeOrderStatus($order);
        $paymentStatus = (int) data_get($order, 'OdemeDurumu', data_get($payment, 'Onaylandi', 0));
        $isRefund = $paymentStatus === 3 || in_array($orderStatus, ['cancelled', 'returned'], true);
        $paymentId = (string) (data_get($payment, 'ID') ?: data_get($payment, 'PosReferansID') ?: sha1(json_encode($payment)));

        return [
            'event_source' => 'ticimax_order_payment',
            'external_event_id' => $paymentId,
            'order_number' => (string) (data_get($order, 'SiparisKodu') ?: data_get($order, 'SiparisNo') ?: data_get($order, 'ID')),
            'external_package_id' => null,
            'external_line_id' => null,
            'stock_code' => null,
            'barcode' => null,
            'event_type' => $isRefund ? 'refund' : 'payment',
            'reference_number' => (string) (data_get($payment, 'PosReferansID') ?: $paymentId),
            'event_date' => $this->dateTime(data_get($payment, 'Tarih') ?: data_get($order, 'SiparisTarihi')),
            'due_date' => null,
            'settlement_date' => $this->dateTime(data_get($payment, 'Tarih')),
            'amount' => abs($amount),
            'currency' => Str::upper((string) (data_get($order, 'ParaBirimi') ?: 'TRY')),
            'direction' => $isRefund ? 'debit' : 'credit',
            'status' => $paymentStatus === 1 ? 'approved' : ($isRefund ? 'refunded' : 'pending'),
            'notes' => collect([
                data_get($payment, 'OdemeNotu'),
                filled(data_get($payment, 'TaksitSayisi')) ? data_get($payment, 'TaksitSayisi').' taksit' : null,
                filled(data_get($payment, 'BankaKomisyonu')) ? 'Banka komisyonu: '.data_get($payment, 'BankaKomisyonu') : null,
            ])->filter()->implode(' | ') ?: null,
            'raw_payload' => ['payment' => $payment, 'order' => $order],
        ];
    }

    protected function normalizeClaim(array $order): array
    {
        $orderId = (string) data_get($order, 'ID');
        $statusCode = (int) data_get($order, 'Durum', -1);
        $items = collect($this->nestedRows($order, 'Urunler', 'WebSiparisUrun'))
            ->filter(fn (array $row) => $this->isClaimLine($row) || in_array($statusCode, range(8, 17), true))
            ->values()
            ->map(fn (array $row) => [
                'external_item_id' => (string) data_get($row, 'ID'),
                'external_order_line_id' => (string) data_get($row, 'ID'),
                'product_name' => data_get($row, 'UrunAdi'),
                'stock_code' => data_get($row, 'StokKodu'),
                'barcode' => data_get($row, 'Barkod'),
                'quantity' => max(1, (int) round((float) data_get($row, 'Adet', 1))),
                'reason' => data_get($row, 'IslemAd') ?: data_get($row, 'DurumAd'),
                'customer_note' => data_get($order, 'SiparisNotu'),
                'raw_payload' => $row,
            ])->all();

        return [
            'external_claim_id' => 'order-'.$orderId.'-status-'.$statusCode,
            'order_number' => (string) (data_get($order, 'SiparisKodu') ?: data_get($order, 'SiparisNo') ?: $orderId),
            'cargo_tracking_number' => data_get($order, 'KargoEntegrasyonTakipNo') ?: data_get($order, 'KargoTakipNo'),
            'cargo_provider' => data_get($order, 'KargoEntegrasyonTanim') ?: data_get($order, 'KargoFirma'),
            'status' => $this->claimStatus($statusCode),
            'type' => in_array($statusCode, [8, 14, 15], true) ? 'cancel' : 'return',
            'reason' => data_get($order, 'SiparisDurumu'),
            'reason_detail' => data_get($order, 'SiparisNotu'),
            'customer_note' => data_get($order, 'SiparisNotu'),
            'customer_name' => data_get($order, 'AdiSoyadi'),
            'created_date' => $this->dateTime(data_get($order, 'SiparisTarihi')),
            'items' => $items,
            'raw_payload' => $order,
        ];
    }

    protected function normalizeOrderStatus(array $payload): string
    {
        $code = (int) data_get($payload, 'Durum', -1);
        $label = Str::lower((string) data_get($payload, 'SiparisDurumu', ''));

        return match ($code) {
            0, 1, 3 => 'created',
            2 => 'approved',
            4, 5 => 'picking',
            6 => 'shipped',
            7 => 'delivered',
            8, 10, 14, 15 => 'cancelled',
            9, 11, 12, 13, 16, 17 => 'returned',
            default => match (true) {
                Str::contains($label, ['teslim']) => 'delivered',
                Str::contains($label, ['kargo']) => 'shipped',
                Str::contains($label, ['paket', 'tedarik']) => 'picking',
                Str::contains($label, ['iptal']) => 'cancelled',
                Str::contains($label, ['iade']) => 'returned',
                default => 'created',
            },
        };
    }

    protected function normalizeLineStatus(array $payload, string $fallback): string
    {
        $label = Str::lower((string) (data_get($payload, 'DurumAd') ?: data_get($payload, 'IslemAd')));

        if (Str::contains($label, ['iptal'])) {
            return 'cancelled';
        }

        if (Str::contains($label, ['iade'])) {
            return 'returned';
        }

        return $fallback;
    }

    protected function isClaimStatus(array $order): bool
    {
        $code = (int) data_get($order, 'Durum', -1);

        return in_array($code, range(8, 17), true)
            || Str::contains(Str::lower((string) data_get($order, 'SiparisDurumu')), ['iade', 'iptal']);
    }

    protected function isClaimLine(array $line): bool
    {
        return Str::contains(Str::lower((string) (data_get($line, 'DurumAd') ?: data_get($line, 'IslemAd'))), ['iade', 'iptal']);
    }

    protected function claimStatus(int $code): string
    {
        return match ($code) {
            8, 9, 13, 17 => 'completed',
            14, 15, 16 => 'requested',
            11, 12 => 'in_progress',
            default => 'open',
        };
    }

    protected function resolveVariantId(ChannelListing $listing): int
    {
        $id = (string) (
            $listing->listing_id
            ?: data_get($listing->channelProduct?->raw_payload, 'variant.ID')
            ?: $listing->channelProduct?->external_product_id
        );

        if ($id === '' || ! ctype_digit($id)) {
            throw new \RuntimeException('Ticimax fiyat/stok gönderimi için sayısal varyasyon ID bulunamadı. Önce ürün senkronunu çalıştırın.');
        }

        return (int) $id;
    }

    protected function dateTime(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function money(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    /**
     * @param  array{items: array<int, array<string, mixed>>, pages_processed: int, more_pages_available: bool, cursor_after: string}  $result
     * @return array<string, mixed>
     */
    protected function syncMeta(array $result): array
    {
        return [
            'items_received' => count($result['items']),
            'pages_processed' => $result['pages_processed'],
            'more_pages_available' => $result['more_pages_available'],
            'cursor_after' => $result['cursor_after'],
        ];
    }
}
