<?php

namespace App\Http\Controllers;

use App\Models\ChannelOrder;
use App\Models\ChannelOrderPackage;
use App\Services\Marketplace\MarketplaceOrderDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MarketplaceOrderDocumentController extends Controller
{
    public function download(
        Request $request,
        string $documentType,
        MarketplaceOrderDocumentService $documentService
    ) {
        abort_unless(in_array($documentType, ['label', 'dispatch'], true), 404);

        $orderIds = $this->normalizeIds($request->query('order_ids'));
        $packageIds = $this->normalizeIds($request->query('package_ids'));

        abort_if($orderIds === [] && $packageIds === [], 422, 'En az bir sipariş veya paket seçilmelidir.');

        $selectedPackages = $this->selectedPackages($packageIds);
        $packageSelectionMap = $selectedPackages
            ->groupBy('channel_order_id')
            ->map(fn (Collection $packages) => $packages->pluck('id')->map(fn ($id) => (int) $id)->all())
            ->all();

        $resolvedOrderIds = collect($orderIds)
            ->merge($selectedPackages->pluck('channel_order_id')->all())
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $orders = ChannelOrder::query()
            ->whereIn('id', $resolvedOrderIds)
            ->whereHas('store', fn ($query) => $query->where('user_id', auth()->id()))
            ->get();

        abort_if($orders->isEmpty(), 404, 'Seçili siparişler bulunamadı.');

        return match ($documentType) {
            'label' => $documentService->downloadLabels($orders, $packageSelectionMap),
            'dispatch' => $documentService->downloadDispatchNotes($orders, $packageSelectionMap),
        };
    }

    /**
     * @return array<int, int>
     */
    protected function normalizeIds(mixed $raw): array
    {
        if (is_array($raw)) {
            $items = $raw;
        } else {
            $items = preg_split('/[,\s]+/', trim((string) $raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        return collect($items)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    protected function selectedPackages(array $packageIds): Collection
    {
        if ($packageIds === []) {
            return collect();
        }

        return ChannelOrderPackage::query()
            ->select(['id', 'channel_order_id'])
            ->whereIn('id', $packageIds)
            ->whereHas('store', fn ($query) => $query->where('user_id', auth()->id()))
            ->get();
    }
}
