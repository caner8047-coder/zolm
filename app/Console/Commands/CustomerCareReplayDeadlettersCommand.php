<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SupportDispatch;
use App\Models\SupportIntegrationDelivery;
use App\Services\Support\SupportOutboxService;
use App\Services\Support\Integration\CustomerCareIntegrationHubService;

class CustomerCareReplayDeadlettersCommand extends Command
{
    protected $signature = 'customer-care:replay-deadletters {--store= : Store ID} {--type=dispatch : dispatch or integration} {--execute : Persist changes instead of dry-run}';
    protected $description = 'Terminal hataları (exhausted dispatches veya dead_letter webhooks) yeniden tetikler.';

    public function handle()
    {
        $storeId = $this->option('store');
        $type = $this->option('type');
        $execute = $this->option('execute');
        $dryRun = !$execute;

        if (!$storeId) {
            $this->error("Mağaza ID belirtilmelidir.");
            return 1;
        }

        if ($execute) {
            try {
                $systemActor = \App\Services\Support\TenantContext::getSystemActor();
            } catch (\Exception $e) {
                $this->error('Sistem aktörü (System Actor) bulunamadı. İşlem iptal edildi (Fail-Closed).');
                return 1;
            }

            $rbac = app(\App\Services\Support\Security\SupportRbacService::class);
            try {
                $rbac->enforcePermission($systemActor, $storeId, 'run_compliance');
                $rbac->enforceApproval($systemActor, $storeId, 'replay_deadletters', ['type' => $type]);
            } catch (\App\Exceptions\ApprovalRequiredException $e) {
                $this->error($e->getMessage() . ' Replay işlemi durduruldu. Lütfen Governance ekranından onaylayın.');
                return 1;
            } catch (\Exception $e) {
                $this->error('Yetkilendirme hatası: ' . $e->getMessage());
                return 1;
            }
        }

        $this->info("Replay Deadletters başlatılıyor... Store: {$storeId}, Tip: {$type}, Mod: " . ($dryRun ? 'DRY-RUN (Değişiklik yapılmayacak)' : 'CANLI (Uygulanacak)'));

        if ($type === 'dispatch') {
            $dispatches = SupportDispatch::whereHas('conversation', function ($q) use ($storeId) {
                    $q->where('store_id', $storeId);
                })
                ->where('status', 'exhausted')
                ->get();

            $this->line("Yeniden denenecek Exhausted Dispatch sayısı: " . $dispatches->count());

            if ($execute) {
                foreach ($dispatches as $dispatch) {
                    $dispatch->update([
                        'status' => 'pending',
                        'attempt_count' => 0,
                        'retry_at' => now(),
                    ]);
                }
                $this->info("Kuyruk sıfırlandı. Gönderim tetikleniyor...");
                app(SupportOutboxService::class)->processPendingDispatches();
            }
        } elseif ($type === 'integration') {
            $deliveries = SupportIntegrationDelivery::whereHas('event', function ($q) use ($storeId) {
                    $q->where('store_id', $storeId);
                })
                ->where('status', 'dead_letter')
                ->get();

            $this->line("Yeniden denenecek Dead-Letter Webhook sayısı: " . $deliveries->count());

            if ($execute) {
                $hubService = app(CustomerCareIntegrationHubService::class);
                foreach ($deliveries as $delivery) {
                    // Webhook secret'ını çöz
                    $channel = \App\Models\SupportChannel::where('store_id', $storeId)
                        ->where('key', 'webhook_outbound')
                        ->first();
                    $rawSecret = $channel ? ($channel->config_json['webhook_secret'] ?? '') : '';
                    if (empty($rawSecret)) {
                        $this->warn("Delivery ID {$delivery->id} için imzalama anahtarı eksik. Atlanıyor.");
                        $delivery->update([
                            'status' => 'failed',
                            'last_error' => 'Webhook imzalama anahtarı eksik.',
                        ]);
                        continue;
                    }

                    try {
                        $secret = \Illuminate\Support\Facades\Crypt::decryptString($rawSecret);
                    } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                        $this->warn("Delivery ID {$delivery->id} için şifreli anahtar çözülemedi. Atlanıyor.");
                        $delivery->update([
                            'status' => 'failed',
                            'last_error' => 'Şifrelenmiş webhook anahtarı çözülemedi.',
                        ]);
                        continue;
                    }

                    $delivery->update([
                        'status' => 'pending',
                        'attempts' => 0,
                    ]);

                    $hubService->deliver($delivery, $secret);
                }
            }
        } else {
            $this->error("Geçersiz tip. Sadece 'dispatch' veya 'integration' desteklenmektedir.");
            return 1;
        }

        return 0;
    }
}
