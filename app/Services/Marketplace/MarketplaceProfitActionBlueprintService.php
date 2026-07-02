<?php

namespace App\Services\Marketplace;

use App\Models\MpProfitActionItem;

class MarketplaceProfitActionBlueprintService
{
    /**
     * @return array<string, mixed>
     */
    public function forKey(string $key): array
    {
        return match ($key) {
            'material_variance' => [
                'default_owner' => 'Finans ekibi',
                'default_priority' => MpProfitActionItem::PRIORITY_HIGH,
                'due_in_days' => 2,
                'plan_summary' => 'En yüksek fark üreten siparişleri finans olayı ve kâr snapshot kaydıyla karşılaştır.',
                'success_metric' => 'Materyal mutabakat farkı sıfıra yaklaştırıldı.',
                'playbook_steps' => [
                    'Fark tutarına göre ilk siparişleri aç.',
                    'Tahmini snapshot ile kesin finans hareketini karşılaştır.',
                    'Eksik/yanlış kesinti veya iade etkisini düzeltip yeniden kontrol et.',
                ],
            ],
            'loss_orders' => [
                'default_owner' => 'Kâr merkezi',
                'default_priority' => MpProfitActionItem::PRIORITY_CRITICAL,
                'due_in_days' => 1,
                'plan_summary' => 'Negatif kâr baskısı oluşturan siparişlerde maliyet, kesinti ve iade etkisini ayrıştır.',
                'success_metric' => 'Zarar eden sipariş sayısı ve açık zarar etkisi azaltıldı.',
                'playbook_steps' => [
                    'En yüksek zararlı siparişleri kâr değerine göre sırala.',
                    'Maliyet, komisyon, kargo, hizmet ve stopaj kırılımını kontrol et.',
                    'İade veya finans hareketi etkisini netleştirip aksiyon sonucunu notla kapat.',
                ],
            ],
            'missing_cost' => [
                'default_owner' => 'Ürün maliyet ekibi',
                'default_priority' => MpProfitActionItem::PRIORITY_HIGH,
                'due_in_days' => 3,
                'plan_summary' => 'Eşleşmeyen ürün ve eksik COGS/ambalaj maliyeti satırlarını tamamla.',
                'success_metric' => 'Maliyet hazırlık oranı yükseldi.',
                'playbook_steps' => [
                    'Ürün eşleşmesi olmayan satırları stok kodu veya barkodla eşleştir.',
                    'COGS ve ambalaj maliyeti eksik ürünleri tamamla.',
                    'Kâr hesaplarını yenileyip maliyet hazırlık oranını kontrol et.',
                ],
            ],
            'finance_waiting' => [
                'default_owner' => 'Finans operasyon',
                'default_priority' => MpProfitActionItem::PRIORITY_MEDIUM,
                'due_in_days' => 3,
                'plan_summary' => 'Hakediş/finans olayı oluşmamış siparişlerin net alacak ve kâr kesinliğini tamamla.',
                'success_metric' => 'Finans bekleyen sipariş sayısı azaldı.',
                'playbook_steps' => [
                    'Finans olayı bekleyen siparişleri pazaryeri ve mağazaya göre grupla.',
                    'Hakediş dosyası veya entegrasyon gecikmesini kontrol et.',
                    'Finans hareketi geldikten sonra net alacak ve kâr durumunu yeniden izle.',
                ],
            ],
            'snapshot_missing' => [
                'default_owner' => 'Sistem kontrol',
                'default_priority' => MpProfitActionItem::PRIORITY_HIGH,
                'due_in_days' => 2,
                'plan_summary' => 'Order-level kâr snapshot kaydı olmayan siparişleri yeniden hesaplat.',
                'success_metric' => 'Kâr kaydı eksik sipariş kalmadı.',
                'playbook_steps' => [
                    'Snapshot eksik siparişleri filtrele.',
                    'Ürün, finans ve maliyet kaynaklarını kontrol et.',
                    'Kâr snapshot üretimini tekrar çalıştırıp mutabakat durumunu doğrula.',
                ],
            ],
            default => [
                'default_owner' => 'Operasyon',
                'default_priority' => MpProfitActionItem::PRIORITY_MEDIUM,
                'due_in_days' => 5,
                'plan_summary' => 'Aksiyonun finansal etkisini kontrol edip sonucu notla kapat.',
                'success_metric' => 'Aksiyon kapandı.',
                'playbook_steps' => [
                    'İlgili kayıtları aç.',
                    'Finansal etki ve operasyon nedenini kontrol et.',
                    'Sonucu notla kapat.',
                ],
            ],
        };
    }
}
