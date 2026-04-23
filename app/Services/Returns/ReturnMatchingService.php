<?php

namespace App\Services\Returns;

use App\Models\ChannelClaim;
use App\Models\ChannelClaimItem;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\ChannelOrderPackage;
use App\Models\MpOrder;
use App\Models\ReturnIntakeItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ReturnMatchingService
{
    /**
     * @param  array<string, mixed>  $analysis
     * @return array<string, mixed>
     */
    public function match(ReturnIntakeItem $item, array $analysis = []): array
    {
        $tracking = $this->clean((string) ($analysis['tracking_number'] ?? $item->detected_tracking_number ?? ''));
        $orderNumber = $this->clean((string) ($analysis['order_number'] ?? $item->detected_order_number ?? ''));
        $barcode = $this->clean((string) ($analysis['product_barcode'] ?? $item->operator_barcode ?? $item->detected_barcode ?? ''));
        $customerName = $this->clean((string) ($analysis['customer_name'] ?? $item->detected_customer_name ?? ''));
        $manualReference = $this->clean((string) ($item->manual_reference ?? ''));

        if ($tracking === '' && $orderNumber === '' && $barcode === '' && $manualReference !== '') {
            if ($this->looksLikeTracking($manualReference)) {
                $tracking = $manualReference;
            } elseif ($this->looksLikeBarcode($manualReference)) {
                $barcode = $manualReference;
            } else {
                $orderNumber = $manualReference;
            }
        }

        $claimCandidates = collect();
        $orderCandidates = collect();
        $matchedBy = null;

        if ($tracking !== '') {
            $claimCandidates = $claimCandidates->concat(
                ChannelClaim::query()
                    ->where('cargo_tracking_number', $tracking)
                    ->get()
                    ->map(fn (ChannelClaim $claim) => ['model' => $claim, 'score' => 80, 'reason' => 'tracking'])
            );

            $orderCandidates = $orderCandidates->concat(
                ChannelOrderPackage::query()
                    ->with('order')
                    ->where('cargo_tracking_number', $tracking)
                    ->get()
                    ->filter(fn (ChannelOrderPackage $package) => $package->order !== null)
                    ->map(fn (ChannelOrderPackage $package) => ['model' => $package->order, 'package' => $package, 'score' => 78, 'reason' => 'tracking'])
            );

            // Fuzzy tracking: tam eşleşme yoksa son 10 hane ile LIKE dene
            // (OCR baş taraftaki 1-2 haneyi yanlış okuyabilir: 0↔O, 1↔l gibi)
            if ($claimCandidates->isEmpty() && $orderCandidates->isEmpty()) {
                $lastDigits = substr(preg_replace('/\D+/', '', $tracking) ?: '', -10);
                if (strlen($lastDigits) >= 10) {
                    $likePattern = '%' . $lastDigits;

                    $claimCandidates = $claimCandidates->concat(
                        ChannelClaim::query()
                            ->where('cargo_tracking_number', 'like', $likePattern)
                            ->limit(3)
                            ->get()
                            ->map(fn (ChannelClaim $claim) => ['model' => $claim, 'score' => 55, 'reason' => 'fuzzy_tracking'])
                    );

                    $orderCandidates = $orderCandidates->concat(
                        ChannelOrderPackage::query()
                            ->with('order')
                            ->where('cargo_tracking_number', 'like', $likePattern)
                            ->limit(3)
                            ->get()
                            ->filter(fn (ChannelOrderPackage $package) => $package->order !== null)
                            ->map(fn (ChannelOrderPackage $package) => ['model' => $package->order, 'package' => $package, 'score' => 50, 'reason' => 'fuzzy_tracking'])
                    );
                }
            }
        }

        if ($orderNumber !== '') {
            $claimCandidates = $claimCandidates->concat(
                ChannelClaim::query()
                    ->where('order_number', $orderNumber)
                    ->get()
                    ->map(fn (ChannelClaim $claim) => ['model' => $claim, 'score' => 64, 'reason' => 'order_number'])
            );

            $orderCandidates = $orderCandidates->concat(
                ChannelOrder::query()
                    ->where('order_number', $orderNumber)
                    ->get()
                    ->map(fn (ChannelOrder $order) => ['model' => $order, 'package' => null, 'score' => 62, 'reason' => 'order_number'])
            );
        }

        if ($barcode !== '') {
            $claimCandidates = $claimCandidates->concat(
                ChannelClaimItem::query()
                    ->with('claim')
                    ->where('barcode', $barcode)
                    ->get()
                    ->filter(fn (ChannelClaimItem $claimItem) => $claimItem->claim !== null)
                    ->map(fn (ChannelClaimItem $claimItem) => ['model' => $claimItem->claim, 'score' => 38, 'reason' => 'barcode'])
            );

            $orderCandidates = $orderCandidates->concat(
                ChannelOrderItem::query()
                    ->with(['order', 'package'])
                    ->where('barcode', $barcode)
                    ->get()
                    ->filter(fn (ChannelOrderItem $orderItem) => $orderItem->order !== null)
                    ->map(fn (ChannelOrderItem $orderItem) => ['model' => $orderItem->order, 'package' => $orderItem->package, 'score' => 35, 'reason' => 'barcode'])
            );
        }

        $claimWinner = $this->chooseWinner($claimCandidates, $customerName);
        $orderWinner = $this->chooseWinner($orderCandidates, $customerName, true);

        /** @var ChannelClaim|null $claim */
        $claim = $claimWinner['model'] ?? null;
        /** @var ChannelOrder|null $order */
        $order = $orderWinner['model'] ?? null;
        /** @var ChannelOrderPackage|null $package */
        $package = $orderWinner['package'] ?? null;

        if (!$order && $claim?->order_number) {
            $order = ChannelOrder::query()->where('order_number', $claim->order_number)->first();
            $package = $order?->packages()->where('cargo_tracking_number', $tracking)->first() ?: $order?->packages()->first();
        }

        if (!$claim && $order?->order_number) {
            $claim = ChannelClaim::query()
                ->where(function ($query) use ($order, $tracking) {
                    $query->where('order_number', $order->order_number);

                    if ($tracking !== '') {
                        $query->orWhere('cargo_tracking_number', $tracking);
                    }
                })
                ->first();
        }

        // -- MpOrder fallback: ChannelOrder bulunamadıysa MpOrder'da da ara --
        if (!$order && !$claim) {
            $mpOrder = $this->searchMpOrder($tracking, $orderNumber, $barcode);

            if ($mpOrder) {
                Log::info('[ReturnMatching] MpOrder fallback eşleşmesi', [
                    'mp_order_id' => $mpOrder->id,
                    'order_number' => $mpOrder->order_number,
                ]);

                // MpOrder'dan ChannelOrder'a köprü kur
                $channelOrder = ChannelOrder::query()->where('order_number', $mpOrder->order_number)->first();
                if ($channelOrder) {
                    $order = $channelOrder;
                    $package = $channelOrder->packages()
                        ->where('cargo_tracking_number', $tracking)
                        ->first() ?: $channelOrder->packages()->first();
                }
            }
        }

        if ($tracking !== '') {
            $matchedBy = 'tracking';
        } elseif ($orderNumber !== '') {
            $matchedBy = 'order_number';
        } elseif ($barcode !== '') {
            $matchedBy = 'barcode';
        }

        $confidence = max((float) ($claimWinner['score'] ?? 0), (float) ($orderWinner['score'] ?? 0));
        $productVerificationStatus = $this->resolveProductVerificationStatus($order, $claim, $barcode);
        $intakeStatus = $this->resolveIntakeStatus($claim, $order, $confidence);

        return [
            'store_id' => $claim?->store_id ?: $order?->store_id,
            'channel_claim_id' => $claim?->id,
            'channel_order_id' => $order?->id,
            'channel_order_package_id' => $package?->id,
            'matched_by' => $matchedBy,
            'matching_confidence' => $confidence > 0 ? min(99.99, $confidence) : null,
            'product_verification_status' => $productVerificationStatus,
            'intake_status' => $intakeStatus,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $candidates
     * @return array<string, mixed>
     */
    protected function chooseWinner(Collection $candidates, string $customerName, bool $hasPackage = false): array
    {
        if ($candidates->isEmpty()) {
            return [];
        }

        $grouped = $candidates
            ->groupBy(fn (array $candidate) => (string) ($candidate['model']?->getKey()))
            ->map(function (Collection $rows) use ($customerName, $hasPackage) {
                $top = $rows->sortByDesc('score')->first();
                $model = $top['model'] ?? null;
                $score = (float) ($top['score'] ?? 0);

                if ($customerName !== '' && $model) {
                    $haystack = $this->clean((string) ($model->customer_name ?? ''));

                    if ($haystack !== '' && str_contains(mb_strtolower($haystack), mb_strtolower($customerName))) {
                        $score += 8;
                    }
                }

                return [
                    'model' => $model,
                    'package' => $hasPackage ? ($top['package'] ?? null) : null,
                    'score' => $score,
                ];
            })
            ->sortByDesc('score')
            ->values();

        $winner = $grouped->first();
        $runnerUp = $grouped->get(1);

        if ($winner === null) {
            return [];
        }

        if ($runnerUp && abs((float) $winner['score'] - (float) $runnerUp['score']) < 5.0) {
            return [];
        }

        return $winner;
    }

    protected function resolveProductVerificationStatus(?ChannelOrder $order, ?ChannelClaim $claim, string $barcode): string
    {
        if ($barcode === '') {
            return 'unverified';
        }

        if ($order && $order->items()->where('barcode', $barcode)->exists()) {
            return 'matched';
        }

        if ($claim && $claim->items()->where('barcode', $barcode)->exists()) {
            return 'matched';
        }

        if ($order || $claim) {
            return 'mismatch';
        }

        return 'unverified';
    }

    protected function resolveIntakeStatus(?ChannelClaim $claim, ?ChannelOrder $order, float $confidence): string
    {
        if (!$claim && !$order) {
            return 'needs_review';
        }

        if ($claim?->status === 'delivered' || ($claim && in_array($claim->status, ['pending', 'shipped', 'in_transit'], true))) {
            return $confidence >= 60 ? 'ready_for_decision' : 'needs_review';
        }

        return $confidence >= 60 ? 'matched' : 'needs_review';
    }

    protected function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?: '');
    }

    protected function looksLikeTracking(string $value): bool
    {
        return preg_match('/^[A-Z]{1,4}[-\s]?\d{6,}$/i', $value) === 1 || preg_match('/^\d{10,20}$/', preg_replace('/\D+/', '', $value)) === 1;
    }

    protected function looksLikeBarcode(string $value): bool
    {
        return preg_match('/^\d{8,14}$/', preg_replace('/\D+/', '', $value)) === 1;
    }

    /**
     * MpOrder tablosunda sipariş ara (ChannelOrder bulunamadığında fallback).
     */
    protected function searchMpOrder(string $tracking, string $orderNumber, string $barcode): ?MpOrder
    {
        if ($orderNumber !== '') {
            $mpOrder = MpOrder::query()->where('order_number', $orderNumber)->first();
            if ($mpOrder) {
                return $mpOrder;
            }
        }

        return null;
    }
}
