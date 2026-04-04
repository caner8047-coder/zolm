<?php

namespace App\Services\Marketplace;

use App\Models\ChannelOrder;
use App\Models\ChannelOrderPackage;
use App\Services\MpSettingsService;
use App\Services\Marketplace\Support\Code128SvgGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class MarketplaceOrderDocumentService
{
    public function __construct(
        protected MpSettingsService $settingsService,
        protected Code128SvgGenerator $barcodeGenerator,
    ) {
    }

    public function downloadLabels(Collection $orders, array $packageSelectionMap = []): Response
    {
        $orders = $this->prepareOrders($orders);
        $settings = $this->labelSettings();
        $documents = $this->buildLabelDocuments($orders, $settings, $packageSelectionMap);

        $pdf = Pdf::loadView('pdf.marketplace-order-labels', [
            'documents' => $documents,
            'settings' => $settings,
        ]);

        [$paper, $orientation] = $this->resolvePaper($settings['paper'] ?? 'a6');
        $pdf->setPaper($paper, $orientation);

        return $pdf->download($this->labelFilename($orders, count($documents)));
    }

    public function downloadDispatchNotes(Collection $orders, array $packageSelectionMap = []): Response
    {
        $orders = $this->prepareOrders($orders);
        $settings = $this->dispatchSettings();
        $documents = $this->buildDispatchDocuments($orders, $settings, $packageSelectionMap);

        $pdf = Pdf::loadView('pdf.marketplace-order-dispatch-notes', [
            'documents' => $documents,
            'settings' => $settings,
        ]);

        [$paper, $orientation] = $this->resolvePaper($settings['paper'] ?? 'a4');
        $pdf->setPaper($paper, $orientation);

        return $pdf->download($this->dispatchFilename($orders, count($documents)));
    }

    /**
     * @return array<string, mixed>
     */
    public function labelSettings(): array
    {
        return $this->settingsService->getArray('print.label', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function dispatchSettings(): array
    {
        return $this->settingsService->getArray('print.dispatch', []);
    }

    protected function prepareOrders(Collection $orders): Collection
    {
        return $orders
            ->filter(fn ($order) => $order instanceof ChannelOrder)
            ->values()
            ->loadMissing([
                'store.legalEntity',
                'legalEntity',
                'packages',
                'items',
            ]);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<int, array<int, int|string>>  $packageSelectionMap
     * @return array<int, array<string, mixed>>
     */
    protected function buildLabelDocuments(Collection $orders, array $settings, array $packageSelectionMap = []): array
    {
        $documents = [];

        foreach ($orders as $order) {
            $packages = $this->resolvePackagesForOrder($order, $packageSelectionMap);

            foreach ($packages as $package) {
                $packageItems = $this->resolvePackageItems($order, $package);
                $cargoBarcode = $this->resolveCargoBarcode($order, $package);

                $documents[] = [
                    'order' => $order,
                    'package' => $package,
                    'items' => $packageItems,
                    'recipientName' => trim((string) ($order->customer_name ?? '')),
                    'customerPhone' => trim((string) ($order->customer_phone ?? '')),
                    'sender' => $this->resolveSender($order),
                    'shipmentAddress' => $this->resolveShipmentAddress($order),
                    'cargoBarcode' => $cargoBarcode,
                    'barcodeDataUri' => $this->barcodeDataUri(
                        $cargoBarcode,
                        (int) ($settings['barcode_height'] ?? 56),
                        (bool) ($settings['show_barcode_text'] ?? true)
                    ),
                    'trackingNumber' => trim((string) ($package?->cargo_tracking_number ?? '')),
                    'cargoCompany' => trim((string) ($package?->cargo_company ?? '')),
                    'marketplaceLabel' => $order->store?->store_name ?: 'Pazaryeri mağazası',
                    'packageNumber' => trim((string) ($package?->package_number ?? $order->order_number)),
                    'itemSummary' => $packageItems
                        ->map(fn ($item) => trim((string) ($item->product_name ?? '')))
                        ->filter()
                        ->take(4)
                        ->values(),
                ];
            }
        }

        return $documents;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<int, array<int, int|string>>  $packageSelectionMap
     * @return array<int, array<string, mixed>>
     */
    protected function buildDispatchDocuments(Collection $orders, array $settings, array $packageSelectionMap = []): array
    {
        $documents = [];

        foreach ($orders as $order) {
            $packages = $this->resolvePackagesForOrder($order, $packageSelectionMap);

            foreach ($packages as $package) {
                $packageItems = $this->resolvePackageItems($order, $package);
                $cargoBarcode = $this->resolveCargoBarcode($order, $package);

                $documents[] = [
                    'order' => $order,
                    'package' => $package,
                    'items' => $packageItems,
                    'recipientName' => trim((string) ($order->customer_name ?? '')),
                    'customerPhone' => trim((string) ($order->customer_phone ?? '')),
                    'billingName' => trim((string) ($order->billing_name ?? '')),
                    'billingTaxNumber' => trim((string) ($order->billing_tax_number ?? '')),
                    'sender' => $this->resolveSender($order),
                    'shipmentAddress' => $this->resolveShipmentAddress($order),
                    'cargoBarcode' => $cargoBarcode,
                    'barcodeDataUri' => $this->barcodeDataUri(
                        $cargoBarcode,
                        (int) ($settings['barcode_height'] ?? 44),
                        (bool) ($settings['show_barcode_text'] ?? true)
                    ),
                    'trackingNumber' => trim((string) ($package?->cargo_tracking_number ?? '')),
                    'cargoCompany' => trim((string) ($package?->cargo_company ?? '')),
                    'marketplaceLabel' => $order->store?->store_name ?: 'Pazaryeri mağazası',
                    'template' => $settings['template'] ?? 'classic',
                ];
            }
        }

        return $documents;
    }

    /**
     * @return array{name: string, taxNumber: string, phone: string, address: string}
     */
    protected function resolveSender(ChannelOrder $order): array
    {
        $legalEntity = $order->legalEntity ?: $order->store?->legalEntity;

        return [
            'name' => trim((string) ($legalEntity?->name ?: $this->settingsService->get('company.name', ''))),
            'taxNumber' => trim((string) ($legalEntity?->tax_number ?: $this->settingsService->get('company.tax_number', ''))),
            'phone' => trim((string) ($legalEntity?->phone ?: $this->settingsService->get('company.phone', ''))),
            'address' => trim((string) ($legalEntity?->address ?: $this->settingsService->get('company.address', ''))),
        ];
    }

    protected function resolveShipmentAddress(ChannelOrder $order): string
    {
        $raw = $order->raw_payload ?? [];

        $fullAddress = collect([
            data_get($raw, 'shipmentAddress.fullAddress'),
            data_get($raw, 'shipmentAddress.address'),
            $this->joinAddressParts(
                data_get($raw, 'shipmentAddress.address1'),
                data_get($raw, 'shipmentAddress.address2')
            ),
            data_get($raw, 'shippingAddress.fullAddress'),
            data_get($raw, 'shippingAddress.address'),
            $this->joinAddressParts(
                data_get($raw, 'shippingAddress.address1'),
                data_get($raw, 'shippingAddress.address2')
            ),
            data_get($raw, 'detail.shippingAddress.fullAddress'),
            data_get($raw, 'detail.shippingAddress.address'),
            $this->joinAddressParts(
                data_get($raw, 'detail.shippingAddress.addressLine1'),
                data_get($raw, 'detail.shippingAddress.addressLine2')
            ),
            data_get($raw, 'summary.shippingAddress.address'),
            data_get($raw, 'invoiceAddress.address'),
        ])->filter(fn ($value) => filled($value))->map(fn ($value) => trim((string) $value))->first();

        $locationLine = collect([
            $order->shipment_district,
            $order->shipment_city,
            $order->shipment_country,
        ])->filter()->implode(', ');

        return trim(collect([$fullAddress, $locationLine])->filter()->implode(' / '));
    }

    protected function joinAddressParts(?string $line1, ?string $line2): ?string
    {
        $parts = collect([$line1, $line2])
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => trim((string) $value))
            ->values();

        if ($parts->isEmpty()) {
            return null;
        }

        return $parts->implode(' ');
    }

    /**
     * @param  array<int, array<int, int|string>>  $packageSelectionMap
     * @return Collection<int, ChannelOrderPackage|null>
     */
    protected function resolvePackagesForOrder(ChannelOrder $order, array $packageSelectionMap): Collection
    {
        $selectedPackageIds = collect($packageSelectionMap[$order->id] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        if ($order->packages->isNotEmpty()) {
            $packages = $selectedPackageIds->isNotEmpty()
                ? $order->packages->whereIn('id', $selectedPackageIds->all())->values()
                : $order->packages->sortBy('id')->values();

            if ($packages->isNotEmpty()) {
                return $packages->values();
            }

            if ($selectedPackageIds->isNotEmpty()) {
                return collect();
            }
        }

        return collect([null]);
    }

    protected function resolvePackageItems(ChannelOrder $order, ?ChannelOrderPackage $package): Collection
    {
        $items = $package instanceof ChannelOrderPackage
            ? $order->items->where('channel_order_package_id', $package->id)->values()
            : $order->items->values();

        return $items->isNotEmpty() ? $items : $order->items->values();
    }

    protected function resolveCargoBarcode(ChannelOrder $order, ?ChannelOrderPackage $package): string
    {
        return trim((string) (
            $package?->cargo_barcode
            ?: $package?->cargo_tracking_number
            ?: data_get($package?->raw_payload ?? [], 'barcode')
            ?: data_get($package?->raw_payload ?? [], 'cargoTrackingNumber')
            ?: data_get($order->raw_payload ?? [], 'barcode')
            ?: data_get($order->raw_payload ?? [], 'cargoTrackingNumber')
            ?: $order->order_number
        ));
    }

    protected function barcodeDataUri(string $value, int $height, bool $withText): string
    {
        return $this->barcodeGenerator->dataUri(
            $value,
            max(32, min($height, 96)),
            $withText
        );
    }

    protected function labelFilename(Collection $orders, int $documentCount): string
    {
        if ($documentCount === 1 && $orders->count() === 1) {
            return 'kargo-etiketi-' . $orders->first()->order_number . '.pdf';
        }

        return 'kargo-etiketleri-toplu.pdf';
    }

    protected function dispatchFilename(Collection $orders, int $documentCount): string
    {
        if ($documentCount === 1 && $orders->count() === 1) {
            return 'irsaliye-' . $orders->first()->order_number . '.pdf';
        }

        return 'irsaliyeler-toplu.pdf';
    }

    /**
     * @return array{0: array<int|float|string>, 1: string}
     */
    protected function resolvePaper(string $paper): array
    {
        return match ($paper) {
            'thermal_100x150' => [[0, 0, 283.46, 425.20], 'portrait'],
            'a6_landscape' => ['a6', 'landscape'],
            'a5_landscape' => ['a5', 'landscape'],
            'a5' => ['a5', 'portrait'],
            'a4_landscape' => ['a4', 'landscape'],
            default => ['a6', 'portrait'],
        };
    }
}
