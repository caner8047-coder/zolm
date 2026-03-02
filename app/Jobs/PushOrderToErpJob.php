<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use App\Models\MpOrder;
use App\Models\MpErpSetting;
use Throwable;
use Illuminate\Support\Facades\Log;

class PushOrderToErpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * İşin kaç kez deneneceği
     */
    public $tries = 3;

    /**
     * Denemeler arası beklenecek süreler (saniye cinsinden)
     * 1. Hata sonrası: 10 saniye bekle
     * 2. Hata sonrası: 30 saniye bekle
     * 3. Hata sonrası: 60 saniye bekle
     */
    public $backoff = [10, 30, 60];

    public function __construct(
        public MpOrder $order,
        public array $payload,
        public MpErpSetting $setting
    ) {}

    public function handle(): void
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];

        // API Key tanımlıysa (Bearer veya x-api-key) standart olarak Auth header içine gömüyoruz
        if (!empty($this->setting->api_key)) {
            $headers['Authorization'] = 'Bearer ' . $this->setting->api_key;
        }

        // POST İsteği
        $response = Http::withHeaders($headers)
            ->timeout(15) // Worker kilitlenmesini engeller
            ->post($this->setting->webhook_url, $this->payload);

        if ($response->successful()) {
            $this->order->update([
                'erp_status'    => 'success',
                'erp_pushed_at' => now(),
                'erp_response'  => $response->body()
            ]);
        } else {
            // Henüz son deneme değilse ara statü olan `retry` atıyoruz.
            $this->order->update([
                'erp_status'   => 'retry',
                'erp_response' => 'HTTP ' . $response->status() . ' - ' . $response->body()
            ]);

            // Laravel'in retry yapısını tetiklemek için hata fırlatıyoruz
            throw new \Exception('ERP Gönderim Hatası: HTTP ' . $response->status() . ' - ' . substr($response->body(), 0, 100));
        }
    }

    /**
     * Maksimum deneme hakkı dolduğunda (3. kez de hata aldıysa) bu fonksiyon son olarak çağrılır.
     */
    public function failed(Throwable $exception): void
    {
        $this->order->update([
            'erp_status'   => 'failed',
            'erp_response' => "Tüm denemeler ([{$this->tries}]) başarısız oldu. Hata: " . $exception->getMessage()
        ]);
        
        Log::error("Sipariş ERP Push Kalıcı Olarak Başarısız (#{$this->order->order_number}): {$exception->getMessage()}");
    }
}
