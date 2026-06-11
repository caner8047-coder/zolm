<?php

namespace App\Services\Cargo;

use App\Models\ChannelOrder;
use App\Models\ChannelOrderPackage;
use App\Models\CrmContact;
use App\Models\CrmContactIdentity;
use App\Models\Shipment;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CargoDeliveryLookupService
{
    public function __construct(
        protected CargoShipmentService $shipmentService,
        protected SuratCargoConnector $suratConnector,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function lookup(string $reference, ?int $userId = null): array
    {
        $reference = trim($reference);

        if ($reference === '') {
            throw new \InvalidArgumentException('Kargo kodu gerekli.');
        }

        $userId = $userId ?: auth()->id();
        $variants = $this->referenceVariants($reference);
        $package = $this->findPackage($variants, $userId);
        $shipment = $this->findShipment($variants, $userId, $package);
        $local = $this->localPayload($reference, $package, $shipment);
        $accountUserId = $userId
            ?: data_get($local, 'store.user_id')
            ?: $shipment?->user_id;
        $legalEntityId = data_get($local, 'store.legal_entity_id')
            ?: data_get($local, 'order.legal_entity_id')
            ?: $shipment?->legal_entity_id;
        $account = $this->shipmentService->defaultAccount($accountUserId, $legalEntityId);
        $surat = [
            'available' => false,
            'message' => $account
                ? 'Sürat takip sorgusu henüz çalıştırılamadı.'
                : 'Aktif Sürat sorgulama hesabı bulunamadı.',
        ];
        $attempts = [];

        if ($account) {
            foreach ($this->candidateReferences($reference, $package, $shipment) as $candidate) {
                try {
                    $surat = array_merge(
                        ['available' => true, 'queried_reference' => $candidate],
                        $this->suratConnector->lookupByReference($account, $candidate)
                    );
                    $attempts[] = ['reference' => $candidate, 'success' => true];
                    break;
                } catch (\Throwable $exception) {
                    $attempts[] = [
                        'reference' => $candidate,
                        'success' => false,
                        'message' => $exception->getMessage(),
                    ];
                    $surat = [
                        'available' => false,
                        'message' => $exception->getMessage(),
                    ];

                    if (!$this->isReferenceNotFound($exception->getMessage())) {
                        break;
                    }
                }
            }
        }

        $distribution = $this->analyzeDistribution($surat);

        return [
            'reference' => $reference,
            'local' => $local,
            'surat' => $surat,
            'distribution' => $distribution,
            'templates' => $this->messageTemplates($local, $distribution),
            'attempts' => $attempts,
            'documented_capability' => [
                'direct_address_lookup' => false,
                'source' => 'KargoTakipHareketDetayi API',
                'note' => 'Dağıtım sinyali takip durumu, hareketler ve devir sebebinden üretilir.',
            ],
        ];
    }

    /**
     * @return array{state: string, label: string, tone: string, confidence: string, reason: string}
     */
    public function analyzeDistribution(array $surat): array
    {
        if (($surat['available'] ?? true) === false || empty($surat)) {
            return [
                'state' => 'unknown',
                'label' => 'Sürat bilgisi yok',
                'tone' => 'slate',
                'confidence' => 'low',
                'reason' => (string) ($surat['message'] ?? 'Sürat takip yanıtı alınamadı.'),
            ];
        }

        $statusCode = $this->integerValue($surat['status_code'] ?? null);
        $status = (string) ($surat['status'] ?? '');
        $statusLabel = (string) ($surat['status_label'] ?? '');
        $devirStatus = (string) ($surat['devir_status'] ?? '');
        $devirReason = (string) ($surat['devir_reason'] ?? '');
        $returnStatus = (string) ($surat['return_status'] ?? '');
        $returnReason = (string) ($surat['return_reason'] ?? '');
        $eventsText = collect($surat['events'] ?? [])
            ->map(fn (array $event) => collect(Arr::only($event, ['event_description', 'branch_name', 'event_status']))->filter()->implode(' '))
            ->filter()
            ->implode(' ');
        $text = $this->normalizeText(collect([
            $status,
            $statusLabel,
            $devirStatus,
            $devirReason,
            $returnStatus,
            $returnReason,
            $eventsText,
        ])->filter()->implode(' '));

        if ($statusCode === 6 || $status === 'delivered' || Str::contains($text, 'teslim edildi')) {
            return [
                'state' => 'yes',
                'label' => 'Teslim edildi',
                'tone' => 'emerald',
                'confidence' => 'high',
                'reason' => 'Sürat takip durumu teslim edildi olarak dönüyor.',
            ];
        }

        if ($status === 'returned' || $this->isAffirmative($returnStatus) || Str::contains($text, ['iade surecinde', 'iade geldi'])) {
            return [
                'state' => 'no',
                'label' => 'Dağıtım yok',
                'tone' => 'rose',
                'confidence' => 'high',
                'reason' => $returnReason ?: $devirReason ?: 'Sürat kargo iade sürecinde görünüyor.',
            ];
        }

        if (Str::contains($text, ['dagitim alani disi', 'adres sorunu', 'alici adresinde yok', 'subeden alacak'])) {
            return [
                'state' => 'no',
                'label' => 'Dağıtım yok',
                'tone' => 'rose',
                'confidence' => 'high',
                'reason' => $devirReason ?: $returnReason ?: 'Sürat devir bilgisinde dağıtım/adres problemi var.',
            ];
        }

        if (Str::contains($text, ['mobil', 'belirli gunler', 'telefon ihbarli'])) {
            return [
                'state' => 'warning',
                'label' => 'Dağıtım sınırlı',
                'tone' => 'amber',
                'confidence' => 'medium',
                'reason' => $devirReason ?: 'Adres mobil veya telefon ihbarlı teslimat alanı olarak görünüyor.',
            ];
        }

        if ($this->isAffirmative($devirStatus) && trim($devirReason) !== '') {
            return [
                'state' => 'warning',
                'label' => 'Devir var',
                'tone' => 'amber',
                'confidence' => 'medium',
                'reason' => $devirReason,
            ];
        }

        if (in_array($statusCode, [5, 15], true)
            || $status === 'out_for_delivery'
            || Str::contains($text, ['kurye dagitima cikti', 'kurye dagitimda', 'kargo dagitima cikarildi'])) {
            return [
                'state' => 'yes',
                'label' => 'Dağıtım var',
                'tone' => 'emerald',
                'confidence' => 'high',
                'reason' => 'Sürat hareketlerinde kurye dağıtım sinyali var.',
            ];
        }

        if ($statusCode === 4 || Str::contains($text, ['teslimat subesinde', 'aractan indirildi'])) {
            return [
                'state' => 'warning',
                'label' => 'Şubede',
                'tone' => 'amber',
                'confidence' => 'medium',
                'reason' => 'Kargo teslimat şubesinde; dağıtım hareketi henüz görünmüyor.',
            ];
        }

        return [
            'state' => 'unknown',
            'label' => 'Net değil',
            'tone' => 'slate',
            'confidence' => 'low',
            'reason' => $statusLabel ?: 'Sürat takip yanıtında dağıtım için net işaret yok.',
        ];
    }

    /**
     * @param  array<int, string>  $variants
     */
    protected function findPackage(array $variants, ?int $userId): ?ChannelOrderPackage
    {
        return ChannelOrderPackage::query()
            ->with(['store', 'order.store', 'order.items.product', 'items.product'])
            ->when($userId, fn ($query) => $query->whereHas('store', fn ($storeQuery) => $storeQuery->where('user_id', $userId)))
            ->where(function ($query) use ($variants) {
                $query->whereIn('cargo_tracking_number', $variants)
                    ->orWhereIn('cargo_barcode', $variants)
                    ->orWhereIn('package_number', $variants)
                    ->orWhereIn('external_package_id', $variants)
                    ->orWhereHas('order', function ($orderQuery) use ($variants) {
                        $orderQuery->whereIn('order_number', $variants)
                            ->orWhereIn('external_order_id', $variants);
                    });
            })
            ->latest('updated_at')
            ->first();
    }

    /**
     * @param  array<int, string>  $variants
     */
    protected function findShipment(array $variants, ?int $userId, ?ChannelOrderPackage $package): ?Shipment
    {
        return Shipment::query()
            ->with(['store', 'order.store', 'order.items.product', 'package.store', 'package.order.items.product', 'items.product', 'events'])
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->where(function ($query) use ($variants, $package) {
                $query->whereIn('reference_number', $variants)
                    ->orWhereIn('order_number', $variants)
                    ->orWhereIn('package_number', $variants)
                    ->orWhereIn('tracking_number', $variants)
                    ->orWhereIn('barcode', $variants)
                    ->orWhereIn('external_shipment_id', $variants)
                    ->orWhereIn('shipment_no', $variants);

                if ($package) {
                    $query->orWhere('channel_order_package_id', $package->id);
                }
            })
            ->latest('updated_at')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    protected function localPayload(string $reference, ?ChannelOrderPackage $package, ?Shipment $shipment): array
    {
        $order = $package?->order ?: $shipment?->order;
        $store = $package?->store ?: $order?->store ?: $shipment?->store;
        $items = $this->itemsFor($package, $shipment, $order);
        $raw = $order?->raw_payload ?: $package?->raw_payload ?: [];
        $address = $this->orderAddress($raw)
            ?: $shipment?->destination_address
            ?: $this->orderAddress($package?->raw_payload ?: []);
        $city = $order?->shipment_city ?: $shipment?->destination_city ?: data_get($raw, 'shipmentAddress.city');
        $district = $order?->shipment_district ?: $shipment?->destination_district ?: data_get($raw, 'shipmentAddress.district');
        $phone = $this->resolvePhone($order, $package, $shipment, $raw, $store?->user_id ?: $shipment?->user_id);
        $customerName = $order?->customer_name
            ?: $order?->billing_name
            ?: data_get($raw, 'shipmentAddress.fullName')
            ?: $shipment?->customer_name;

        return [
            'found' => (bool) ($package || $shipment || $order),
            'source' => match (true) {
                (bool) $package => 'marketplace_package',
                (bool) $shipment => 'shipment',
                default => null,
            },
            'reference' => $reference,
            'customer' => [
                'name' => $customerName,
                'phone' => $phone['value'],
                'phone_display' => $this->displayPhone($phone['value']),
                'phone_source' => $phone['source'],
                'phone_missing_reason' => $phone['missing_reason'],
                'whatsapp_url' => $this->whatsappUrl($phone['value']),
                'address' => $address,
                'city' => $city,
                'district' => $district,
            ],
            'order' => [
                'id' => $order?->id,
                'order_number' => $order?->order_number ?: $shipment?->order_number,
                'external_order_id' => $order?->external_order_id,
                'status' => $order?->order_status ?: $shipment?->status,
                'ordered_at' => optional($order?->ordered_at)->format('d.m.Y H:i'),
                'legal_entity_id' => $order?->legal_entity_id ?: $shipment?->legal_entity_id,
            ],
            'package' => [
                'id' => $package?->id,
                'package_number' => $package?->package_number ?: $shipment?->package_number,
                'external_package_id' => $package?->external_package_id,
                'status' => $package?->package_status,
                'cargo_company' => $package?->cargo_company ?: $shipment?->carrier_name,
                'cargo_tracking_number' => $package?->cargo_tracking_number ?: $shipment?->tracking_number,
                'cargo_barcode' => $package?->cargo_barcode ?: $shipment?->barcode,
                'shipped_at' => optional($package?->shipped_at ?: $shipment?->shipped_at)->format('d.m.Y H:i'),
            ],
            'store' => [
                'id' => $store?->id,
                'user_id' => $store?->user_id ?: $shipment?->user_id,
                'legal_entity_id' => $store?->legal_entity_id,
                'marketplace' => $store?->marketplace ?: data_get($shipment?->meta_json, 'marketplace'),
                'name' => $store?->store_name ?: data_get($shipment?->meta_json, 'store_name'),
            ],
            'items' => $items,
            'product_summary' => $this->productSummary($items),
        ];
    }

    /**
     * @return array{value: ?string, source: ?string, missing_reason: ?string}
     */
    protected function resolvePhone(
        ?ChannelOrder $order,
        ?ChannelOrderPackage $package,
        ?Shipment $shipment,
        array $raw,
        ?int $userId
    ): array {
        $direct = $this->firstFilled([
            $order?->customer_phone,
            $shipment?->customer_phone,
            data_get($raw, 'customerPhone'),
            data_get($raw, 'customer_phone'),
            data_get($raw, 'phone'),
            data_get($raw, 'gsm'),
            data_get($raw, 'mobilePhone'),
            data_get($raw, 'telephone'),
            data_get($raw, 'shipmentAddress.phone'),
            data_get($raw, 'shipmentAddress.phoneNumber'),
            data_get($raw, 'shipmentAddress.gsm'),
            data_get($raw, 'shipmentAddress.mobilePhone'),
            data_get($raw, 'shipmentAddress.telephone'),
            data_get($raw, 'invoiceAddress.phone'),
            data_get($raw, 'invoiceAddress.phoneNumber'),
            data_get($raw, 'invoiceAddress.gsm'),
            data_get($raw, 'billingAddress.phone'),
            data_get($raw, 'billingAddress.phoneNumber'),
            data_get($package?->raw_payload ?? [], 'customerPhone'),
            data_get($package?->raw_payload ?? [], 'shipmentAddress.phone'),
        ]);

        if ($direct) {
            return [
                'value' => (string) $direct,
                'source' => 'Sipariş verisi',
                'missing_reason' => null,
            ];
        }

        $embedded = $this->phoneFromText(collect([
            data_get($raw, 'shipmentAddress.fullAddress'),
            data_get($raw, 'shipmentAddress.address1'),
            data_get($raw, 'invoiceAddress.fullAddress'),
            data_get($raw, 'invoiceAddress.address1'),
            $shipment?->destination_address,
        ])->filter()->implode(' '));

        if ($embedded) {
            return [
                'value' => $embedded,
                'source' => 'Adres metni',
                'missing_reason' => null,
            ];
        }

        $historical = $this->historicalPhone($order, $userId);

        if ($historical) {
            return [
                'value' => $historical,
                'source' => 'Geçmiş müşteri kaydı',
                'missing_reason' => null,
            ];
        }

        return [
            'value' => null,
            'source' => null,
            'missing_reason' => 'Trendyol ve Sürat takip yanıtında alıcı telefonu yok.',
        ];
    }

    protected function historicalPhone(?ChannelOrder $order, ?int $userId): ?string
    {
        if (!$order) {
            return null;
        }

        $customerId = data_get($order->raw_payload, 'customerId');
        $name = trim((string) ($order->customer_name ?: data_get($order->raw_payload, 'shipmentAddress.fullName')));
        $normalizedName = $this->normalizeText($name);

        if ($customerId) {
            $phone = ChannelOrder::query()
                ->when($userId, fn ($query) => $query->whereHas('store', fn ($storeQuery) => $storeQuery->where('user_id', $userId)))
                ->where('id', '!=', $order->id)
                ->whereNotNull('customer_phone')
                ->where('customer_phone', '!=', '')
                ->where('raw_payload->customerId', $customerId)
                ->latest('id')
                ->value('customer_phone');

            if ($phone) {
                return (string) $phone;
            }
        }

        if ($name !== '') {
            $phone = ChannelOrder::query()
                ->when($userId, fn ($query) => $query->whereHas('store', fn ($storeQuery) => $storeQuery->where('user_id', $userId)))
                ->where('id', '!=', $order->id)
                ->whereNotNull('customer_phone')
                ->where('customer_phone', '!=', '')
                ->where(function ($query) use ($name) {
                    $query->where('customer_name', $name)
                        ->orWhere('billing_name', $name);
                })
                ->when($order->shipment_city, fn ($query) => $query->where('shipment_city', $order->shipment_city))
                ->latest('id')
                ->value('customer_phone');

            if ($phone) {
                return (string) $phone;
            }
        }

        if (!$userId || $normalizedName === '') {
            return null;
        }

        if (Schema::hasTable('crm_contacts')) {
            $phone = CrmContact::query()
                ->where('user_id', $userId)
                ->whereNotNull('primary_phone')
                ->where('primary_phone', '!=', '')
                ->where(function ($query) use ($name, $normalizedName) {
                    $query->where('display_name', $name)
                        ->orWhere('normalized_name', $normalizedName);
                })
                ->latest('id')
                ->value('primary_phone');

            if ($phone) {
                return (string) $phone;
            }
        }

        if (Schema::hasTable('crm_contact_identities')) {
            $phone = CrmContactIdentity::query()
                ->where('user_id', $userId)
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->where(function ($query) use ($name, $normalizedName) {
                    $query->where('name', $name)
                        ->orWhere('normalized_name', $normalizedName);
                })
                ->latest('id')
                ->value('phone');

            if ($phone) {
                return (string) $phone;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function itemsFor(?ChannelOrderPackage $package, ?Shipment $shipment, ?ChannelOrder $order): array
    {
        $items = collect();

        if ($package && $package->items->isNotEmpty()) {
            $items = $package->items;
        } elseif ($order && $package) {
            $items = $order->items->where('channel_order_package_id', $package->id);
        }

        if ($items->isEmpty() && $order) {
            $items = $order->items;
        }

        if ($items->isEmpty() && $shipment) {
            $items = $shipment->items;
        }

        return $items
            ->map(function ($item) {
                $name = (string) ($item->product_name ?: data_get($item, 'product.product_name') ?: data_get($item, 'product.name') ?: $item->stock_code ?: 'Ürün');

                return [
                    'name' => $name,
                    'quantity' => max(1, (int) ($item->quantity ?? 1)),
                    'stock_code' => $item->stock_code ?? null,
                    'barcode' => $item->barcode ?? null,
                    'is_matched' => (bool) ($item->is_matched ?? false),
                    'is_corner' => $this->containsAny($name, ['köşe', 'kose', 'corner']),
                    'is_sofa' => $this->containsAny($name, ['kanepe', 'koltuk', 'sofa', 'puf', 'berjer']),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, array{label: string, body: string}>
     */
    protected function messageTemplates(array $local, array $distribution): array
    {
        $customerName = trim((string) data_get($local, 'customer.name'));
        $name = $customerName !== '' ? $customerName : 'Değerli müşterimiz';
        $product = data_get($local, 'product_summary') ?: 'ürününüz';
        $tracking = data_get($local, 'package.cargo_tracking_number')
            ?: data_get($local, 'package.cargo_barcode')
            ?: data_get($local, 'reference');
        $distributionReason = trim((string) ($distribution['reason'] ?? ''));

        return [
            'corner_direction' => [
                'label' => 'Köşe yönü',
                'body' => "Merhaba {$name}, {$product} siparişiniz için köşe yönünüzü teyit etmek istiyoruz.\n\nÜrünün kanepe kısmına oturduğunuzu hayal edin. Uzanma bölümü sağınızda kalıyorsa sağ köşe, solunuzda kalıyorsa sol köşe olarak geçer.\n\nYönünüzü bize \"sağ köşe\" veya \"sol köşe\" şeklinde yazar mısınız?",
            ],
            'delivery_yes' => [
                'label' => 'Dağıtım var',
                'body' => "Merhaba {$name}, Sürat Kargo takip kodunuz {$tracking}. Sistem tarafında adresiniz için dağıtım sinyali görünüyor.\n\nTeslimat sırasında telefonunuzun açık olmasını ve kargo görevlisi aradığında ulaşılabilir olmanızı rica ederiz.",
            ],
            'delivery_issue' => [
                'label' => 'Dağıtım yok',
                'body' => "Merhaba {$name}, Sürat Kargo takip kodunuz {$tracking} için adresinizde dağıtım problemi görünüyor." . ($distributionReason !== '' ? "\n\nNot: {$distributionReason}" : '') . "\n\nTeslimatın aksamaması için alternatif adres veya şube teslim seçeneği hakkında sizinle görüşebilir miyiz?",
            ],
            'install_video' => [
                'label' => 'Kurulum',
                'body' => "Merhaba {$name}, {$product} teslimatınız için kurulum bilgilendirmesini paylaşıyoruz.\n\nKurulum videosu: [kurulum video linki]\n\nKurulumda bağlantı aparatlarını sıkmadan önce parçaları hizalamanızı, ardından tüm vidaları dengeli şekilde sabitlemenizi rica ederiz.",
            ],
            'pre_delivery' => [
                'label' => 'Ön bilgi',
                'body' => "Merhaba {$name}, {$product} siparişiniz Sürat Kargo sürecindedir. Takip kodunuz: {$tracking}\n\nKanepe ve köşe ürünleri hacimli olduğu için teslimat günü bina girişi, asansör ve daire kapısı ölçülerinin uygunluğunu kontrol etmenizi rica ederiz.",
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function candidateReferences(string $reference, ?ChannelOrderPackage $package, ?Shipment $shipment): array
    {
        return collect([
            $reference,
            $package?->cargo_tracking_number,
            $package?->cargo_barcode,
            $package?->package_number,
            $package?->external_package_id,
            $shipment?->reference_number,
            $shipment?->tracking_number,
            $shipment?->barcode,
            $shipment?->package_number,
            $shipment?->order_number,
        ])
            ->merge($this->referenceVariants($reference))
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function referenceVariants(string $reference): array
    {
        $trimmed = trim($reference);
        $compact = preg_replace('/\s+/', '', $trimmed) ?: $trimmed;
        $digits = preg_replace('/\D+/', '', $trimmed) ?: '';

        return collect([$trimmed, $compact, $digits])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function orderAddress(?array $payload): ?string
    {
        return data_get($payload, 'shipmentAddress.fullAddress')
            ?: data_get($payload, 'shipmentAddress.address')
            ?: data_get($payload, 'shippingAddress.fullAddress')
            ?: data_get($payload, 'shippingAddress.address')
            ?: data_get($payload, 'address.fullAddress')
            ?: data_get($payload, 'address.address');
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function productSummary(array $items): ?string
    {
        $summary = collect($items)
            ->take(2)
            ->map(fn (array $item) => trim((string) ($item['name'] ?? '')))
            ->filter()
            ->implode(', ');

        if ($summary === '') {
            return null;
        }

        $remaining = max(0, count($items) - 2);

        return $remaining > 0 ? $summary . ' +' . $remaining : $summary;
    }

    protected function displayPhone(?string $phone): ?string
    {
        $digits = $this->phoneDigits($phone);

        if ($digits === '') {
            return $phone ? trim($phone) : null;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '90')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 10) {
            return '0' . substr($digits, 0, 3) . ' ' . substr($digits, 3, 3) . ' ' . substr($digits, 6, 2) . ' ' . substr($digits, 8, 2);
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return substr($digits, 0, 4) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7, 2) . ' ' . substr($digits, 9, 2);
        }

        return $phone ? trim($phone) : $digits;
    }

    protected function phoneFromText(string $text): ?string
    {
        if ($text === '') {
            return null;
        }

        preg_match_all('/(?:\+?90[\s().-]*)?(?:0?5\d{2}[\s().-]*\d{3}[\s().-]*\d{2}[\s().-]*\d{2})/', $text, $matches);

        foreach ($matches[0] ?? [] as $match) {
            $digits = $this->phoneDigits($match);

            if (strlen($digits) === 12 && str_starts_with($digits, '90')) {
                $digits = substr($digits, 2);
            }

            if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
                return $digits;
            }

            if (strlen($digits) === 10 && str_starts_with($digits, '5')) {
                return '0' . $digits;
            }
        }

        return null;
    }

    protected function whatsappUrl(?string $phone): ?string
    {
        $digits = $this->phoneDigits($phone);

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10) {
            $digits = '90' . $digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = '90' . substr($digits, 1);
        }

        return strlen($digits) >= 11 ? 'https://wa.me/' . $digits : null;
    }

    protected function phoneDigits(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?: '';
    }

    /**
     * @param  array<int, mixed>  $values
     */
    protected function firstFilled(array $values): mixed
    {
        foreach ($values as $value) {
            if (filled($value)) {
                return $value;
            }
        }

        return null;
    }

    protected function integerValue(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    protected function isAffirmative(?string $value): bool
    {
        return Str::contains($this->normalizeText((string) $value), ['evet', 'yes', 'true', '1']);
    }

    protected function isReferenceNotFound(string $message): bool
    {
        return Str::contains($this->normalizeText($message), ['bulunamadi', 'kayit yok', 'kayit bulunmadi']);
    }

    /**
     * @param  array<int, string>  $needles
     */
    protected function containsAny(string $haystack, array $needles): bool
    {
        return Str::contains($this->normalizeText($haystack), array_map(fn ($needle) => $this->normalizeText($needle), $needles));
    }

    protected function normalizeText(string $text): string
    {
        return Str::lower(Str::ascii(trim((string) preg_replace('/\s+/', ' ', $text))));
    }
}
