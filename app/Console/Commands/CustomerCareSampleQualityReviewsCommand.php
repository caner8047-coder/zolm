<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MarketplaceStore;
use App\Models\SupportAiRun;
use App\Models\SupportMessage;
use App\Models\SupportQualityReview;
use App\Services\Support\TenantContext;
use App\Services\Support\Security\SupportRbacService;

class CustomerCareSampleQualityReviewsCommand extends Command
{
    protected $signature = 'customer-care:sample-quality-reviews {--store= : Store ID} {--limit=50 : Limit} {--execute : İnceleme bekleyen örnek adaylarını kaydet}';

    protected $description = 'Rastgele/sistematik kalite inceleme örneklemesi yapar; dry-run varsayılandır.';

    public function handle(): int
    {
        $storeId = $this->option('store');
        $limit = (int)$this->option('limit');
        $execute = $this->option('execute');

        if (!$storeId) {
            $this->error('Lütfen mağaza ID belirtin: --store=ID');
            return 1;
        }

        $store = MarketplaceStore::find($storeId);
        if (!$store) {
            $this->error("Belirtilen ID ({$storeId}) ile eşleşen bir mağaza bulunamadı.");
            return 1;
        }

        $systemActor = null;
        if ($execute) {
            try {
                $systemActor = TenantContext::getSystemActor();
                TenantContext::enforceStoreAccess((int) $storeId, $systemActor);
                app(SupportRbacService::class)->enforcePermission($systemActor, (int) $storeId, 'approve_quality_review');
            } catch (\Exception $e) {
                $this->error('Sistem aktörü veya kalite inceleme yetkisi doğrulanamadı. İşlem durduruldu (Fail-Closed).');
                return 1;
            }
        }

        $this->info("=== Örnekleme Başlatılıyor: Mağaza - {$store->store_name} ===");
        if (!$execute) {
            $this->comment("MOD: DRY-RUN (Değişiklik yapılmayacak)");
        } else {
            $this->warn("MOD: CANLI (Veritabanına yazılacak)");
        }

        // Get AI runs and Agent messages
        $aiRuns = SupportAiRun::where('store_id', $storeId)
            ->inRandomOrder()
            ->limit($limit / 2)
            ->get();

        $agentMessages = SupportMessage::whereHas('conversation', function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            })
            ->where('sender_type', 'agent')
            ->inRandomOrder()
            ->limit($limit - $aiRuns->count())
            ->get();

        $headers = ['Tip', 'ID/MsgID', 'Özet', 'Durum'];
        $rows = [];
        $redactor = app(\App\Services\Support\Security\PiiRedactor::class);

        foreach ($aiRuns as $run) {
            $cleanPrompt = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $run->prompt_raw ?? '');
            $maskedPrompt = $redactor->maskPii($cleanPrompt);
            $summary = mb_substr($maskedPrompt, 0, 40) . '...';
            $rows[] = ['AI Run', $run->id, $summary, 'Taslak'];

            if ($execute) {
                // P1-2: Persist pending quality review candidate (no fake approved / score)
                SupportQualityReview::firstOrCreate([
                    'store_id' => $storeId,
                    'conversation_id' => $run->conversation_id,
                    'message_id' => $run->message_id,
                ], [
                    'reviewer_id' => $systemActor->id,
                    'overall_score' => 0,
                    'decision' => 'pending_review',
                    'feedback' => 'Otomatik örnekleme ile oluşturulmuş, inceleme bekleyen AI run adayı.',
                ]);
            }
        }

        foreach ($agentMessages as $msg) {
            $cleanBody = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $msg->body_encrypted ?? '');
            $maskedBody = $redactor->maskPii($cleanBody);
            $summary = mb_substr($maskedBody, 0, 40) . '...';
            $rows[] = ['Agent Msg', $msg->id, $summary, 'Gönderildi'];

            if ($execute) {
                // P1-2: Persist pending quality review candidate (no fake approved / score)
                SupportQualityReview::firstOrCreate([
                    'store_id' => $storeId,
                    'conversation_id' => $msg->conversation_id,
                    'message_id' => $msg->id,
                ], [
                    'reviewer_id' => $systemActor->id,
                    'overall_score' => 0,
                    'decision' => 'pending_review',
                    'feedback' => 'Otomatik örnekleme ile oluşturulmuş, inceleme bekleyen temsilci mesaj adayı.',
                ]);
            }
        }

        $this->table($headers, $rows);
        $this->info("Toplam Örneklenen Kayıt Sayısı: " . (count($aiRuns) + count($agentMessages)));

        return 0;
    }
}
