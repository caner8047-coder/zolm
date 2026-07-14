<?php

namespace App\Livewire\CustomerCare;

use Livewire\Component;
use App\Models\MarketplaceStore;
use App\Services\Support\CustomerCareAnalyticsService;
use App\Services\Support\TenantContext;
use App\Livewire\CustomerCare\Concerns\ResolvesAccessibleStores;

class Analytics extends Component
{
    use ResolvesAccessibleStores;

    public ?int $selectedStoreId = null;
    public string $successMessage = '';
    public string $errorMessage = '';

    protected $queryString = [
        'selectedStoreId' => ['except' => null],
    ];

    public function mount()
    {
        // Feature flag protection
        if (!config('customer-care.analytics_enabled', false)) {
            abort(404, 'Müşteri hizmetleri analitik modülü aktif değil.');
        }

        $myStores = $this->getMyStores();
        if ($myStores->isNotEmpty() && !$this->selectedStoreId) {
            $this->selectedStoreId = $myStores->first()->id;
        }
    }

    protected function getMyStores()
    {
        return $this->resolveAccessibleStores();
    }

    public function exportReport()
    {
        $myStoreIds = $this->getMyStores()->pluck('id')->toArray();
        if (!in_array($this->selectedStoreId, $myStoreIds)) {
            abort(403, 'Bu mağazanın verilerine erişim yetkiniz yok.');
        }

        $store = MarketplaceStore::find($this->selectedStoreId);
        if (!$store) {
            abort(404, 'Mağaza bulunamadı.');
        }
        app(\App\Services\Support\Security\SupportRbacService::class)
            ->enforcePermission(auth()->user() ?? TenantContext::getSystemActor(), (int) $store->id, 'analytics_export');

        $metrics = app(CustomerCareAnalyticsService::class)->getStoreMetrics(
            (int) $this->selectedStoreId,
            30,
            auth()->user()
        );

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="customer_care_analytics_' . $this->selectedStoreId . '.csv"',
        ];

        $callback = function() use ($metrics, $store) {
            $file = fopen('php://output', 'w');
            // Write UTF-8 BOM to prevent Excel display corruption
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // ZOLM Excel String Normalization
            $clean = function($val) {
                $val = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)$val);
                if (!mb_check_encoding($val, 'UTF-8')) {
                    $val = mb_convert_encoding($val, 'UTF-8');
                }
                if (preg_match('/^[=+\-@]/u', $val) === 1) {
                    $val = "'" . $val;
                }
                return $val;
            };

            fputcsv($file, [$clean('ZOLM AI Müşteri Hizmetleri Operasyon Raporu')]);
            fputcsv($file, [$clean('Mağaza'), $clean($store->store_name)]);
            fputcsv($file, [$clean('Rapor Tarihi'), $clean(now()->toDateTimeString())]);
            fputcsv($file, []);

            fputcsv($file, [$clean('Metrik Adı'), $clean('Değer')]);
            fputcsv($file, [$clean('Toplam Konuşma'), $metrics['total_conversations']]);
            fputcsv($file, [$clean('AI Taslak Sayısı'), $metrics['ai_draft_count']]);
            fputcsv($file, [$clean('AI Otomatik Yanıt'), $metrics['ai_auto_count']]);
            fputcsv($file, [$clean('Temsilci Yanıtı'), $metrics['human_reply_count']]);
            fputcsv($file, [$clean('Handoff Oranı'), $metrics['metric_meta']['handoff_rate']['reliable'] ? '%' . $metrics['handoff_rate'] : 'Yetersiz örneklem']);
            fputcsv($file, [$clean('Politika Engelleme'), $metrics['policy_block_count']]);
            fputcsv($file, [$clean('Ort. İlk Yanıt Süresi (sn)'), $metrics['avg_first_response_time'] ?? $clean('Ölçüm yok')]);
            fputcsv($file, [$clean('Mesai Dışı AI Yanıtı'), $metrics['after_hours_ai_responded_count'] . '/' . $metrics['after_hours_inbound_count']]);
            fputcsv($file, [$clean('Ort. Çözüm Süresi (sn)'), $metrics['avg_resolution_time'] ?? $clean('Ölçüm yok')]);
            fputcsv($file, [$clean('Çözüm Oranı'), $metrics['metric_meta']['resolution_rate']['reliable'] ? '%' . $metrics['resolution_rate'] : 'Yetersiz örneklem']);
            fputcsv($file, []);
            fputcsv($file, [$clean('Metrik Tanımları'), $clean('Pay'), $clean('Payda'), $clean('Minimum Örnek'), $clean('Güvenilir')]);
            foreach ($metrics['metric_meta'] as $definition) {
                fputcsv($file, [
                    $clean($definition['formula']), $definition['numerator'], $definition['denominator'],
                    $definition['minimum_sample'], $clean($definition['reliable'] ? 'Evet' : 'Hayır'),
                ]);
            }
            fputcsv($file, []);

            fputcsv($file, [$clean('SLA İhlalleri')]);
            fputcsv($file, [$clean('SLA Tipi'), $clean('İhlal Sayısı')]);
            fputcsv($file, [$clean('İlk Yanıt SLA İhlal (30 dk)'), $metrics['breached_first_response_count']]);
            fputcsv($file, [$clean('Çözüm SLA İhlal (24 saat)'), $metrics['breached_resolution_count']]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function render()
    {
        $myStores = $this->getMyStores();
        $myStoreIds = $myStores->pluck('id')->toArray();

        $metrics = null;
        if ($this->selectedStoreId) {
            TenantContext::enforceStoreAccess((int) $this->selectedStoreId, auth()->user());
            $metrics = app(CustomerCareAnalyticsService::class)->getStoreMetrics(
                (int) $this->selectedStoreId,
                30,
                auth()->user()
            );
        }

        return view('livewire.customer-care.analytics', [
            'metrics' => $metrics,
            'myStores' => $myStores,
        ])->layout('layouts.app');
    }
}
