<?php

namespace App\Services\Marketplace\Support;

use App\Models\ChannelOrder;
use Illuminate\Support\Str;

/**
 * Sipariş yaşam döngüsü durumunu normalize eder.
 *
 * Farklı pazaryerlerinden gelen statü string'lerini
 * mimari doküman §7.3'teki standart durumlarla eşleştirir:
 *
 * - active: Normal aktif sipariş
 * - delivered: Teslim edilmiş
 * - cancelled_pre_ship: Kargoya verilmeden iptal
 * - cancelled_post_ship: Kargoya verildikten sonra iptal
 * - returned_sellable: İade (satılabilir)
 * - returned_damaged: İade (hasarlı)
 * - return_pending: İade süreci devam ediyor
 */
class OrderLifecycleResolver
{
    /**
     * Siparişin normalize edilmiş yaşam döngüsü durumunu döndür.
     */
    public function resolve(ChannelOrder $order): string
    {
        $status = Str::lower(trim((string) $order->order_status));
        $hasShippedPackage = $this->hasShippedPackage($order);
        $isDelivered = $this->isDelivered($order, $status);

        // İade durumları
        if ($this->isReturned($status)) {
            if ($this->isDamaged($status)) {
                return 'returned_damaged';
            }

            if ($isDelivered || $hasShippedPackage) {
                return 'returned_sellable';
            }

            return 'return_pending';
        }

        // İptal durumları
        if ($this->isCancelled($status)) {
            return $hasShippedPackage ? 'cancelled_post_ship' : 'cancelled_pre_ship';
        }

        // Teslim edilmiş
        if ($isDelivered) {
            return 'delivered';
        }

        return 'active';
    }

    /**
     * İade zarar tutarını hesapla — doküman §7.3 kuralları.
     *
     * @return array{loss_amount: float, loss_breakdown: array<string, float>}
     */
    public function calculateReturnLoss(
        ChannelOrder $order,
        float $cargoTotal,
        float $ownCargoCost,
        float $packagingCost,
        ?float $valueLossRate = null,
        ?float $cogsCost = null,
    ): array {
        $lifecycleState = $this->resolve($order);
        $breakdown = [];
        $totalLoss = 0.0;

        switch ($lifecycleState) {
            case 'cancelled_pre_ship':
                // Zarar: 0
                break;

            case 'cancelled_post_ship':
                // Zarar: giden kargo + ambalaj
                $cargoLoss = $cargoTotal > 0 ? $cargoTotal : $ownCargoCost;
                $totalLoss = round($cargoLoss + $packagingCost, 2);
                $breakdown = [
                    'kargo' => round($cargoLoss, 2),
                    'ambalaj' => round($packagingCost, 2),
                ];
                break;

            case 'returned_sellable':
                // Zarar: giden/dönen kargo + ambalaj
                $cargoLoss = $cargoTotal > 0 ? $cargoTotal : ($ownCargoCost * 2); // gidiş + dönüş
                $totalLoss = round($cargoLoss + $packagingCost, 2);
                $breakdown = [
                    'kargo' => round($cargoLoss, 2),
                    'ambalaj' => round($packagingCost, 2),
                ];
                break;

            case 'returned_damaged':
                // Zarar: giden/dönen kargo + ambalaj + opsiyonel değer kaybı
                $cargoLoss = $cargoTotal > 0 ? $cargoTotal : ($ownCargoCost * 2);
                $valueLoss = 0.0;

                if ($valueLossRate !== null && $valueLossRate > 0 && $cogsCost !== null) {
                    $valueLoss = round($cogsCost * ($valueLossRate / 100), 2);
                }

                $totalLoss = round($cargoLoss + $packagingCost + $valueLoss, 2);
                $breakdown = [
                    'kargo' => round($cargoLoss, 2),
                    'ambalaj' => round($packagingCost, 2),
                    'deger_kaybi' => $valueLoss,
                ];
                break;

            case 'return_pending':
                // Henüz kesinleşmemiş — tahmini zarar (kargo + ambalaj)
                $cargoLoss = $cargoTotal > 0 ? $cargoTotal : $ownCargoCost;
                $totalLoss = round($cargoLoss + $packagingCost, 2);
                $breakdown = [
                    'kargo' => round($cargoLoss, 2),
                    'ambalaj' => round($packagingCost, 2),
                    'tahmini' => true,
                ];
                break;
        }

        return [
            'loss_amount' => $totalLoss,
            'loss_breakdown' => $breakdown,
        ];
    }

    /**
     * Profit state'i lifecycle'a göre belirle.
     */
    public function profitState(ChannelOrder $order, bool $hasFinancials): string
    {
        $lifecycle = $this->resolve($order);

        return match ($lifecycle) {
            'cancelled_pre_ship' => 'cancelled_pre_ship',
            'cancelled_post_ship' => 'cancelled_post_ship',
            'return_pending' => 'return_pending',
            'returned_sellable', 'returned_damaged' => 'return_finalized',
            default => $hasFinancials ? 'confirmed' : 'estimated',
        };
    }

    protected function isReturned(string $status): bool
    {
        return Str::contains($status, ['return', 'iade', 'refund']);
    }

    protected function isCancelled(string $status): bool
    {
        return Str::contains($status, ['cancel', 'iptal', 'cancelled']);
    }

    protected function isDelivered(ChannelOrder $order, string $status): bool
    {
        if (Str::contains($status, ['deliver', 'teslim'])) {
            return true;
        }

        return $order->delivered_at !== null;
    }

    protected function isDamaged(string $status): bool
    {
        return Str::contains($status, ['damage', 'hasar', 'broken', 'kirik']);
    }

    protected function hasShippedPackage(ChannelOrder $order): bool
    {
        // İlişki zaten yüklü mü kontrol et
        if ($order->relationLoaded('packages')) {
            return $order->packages->whereNotNull('shipped_at')->isNotEmpty();
        }

        return $order->packages()->whereNotNull('shipped_at')->exists();
    }
}
