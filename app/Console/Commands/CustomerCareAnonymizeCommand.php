<?php

namespace App\Console\Commands;

use App\Services\Support\CustomerCareAnonymizationService;
use Illuminate\Console\Command;

class CustomerCareAnonymizeCommand extends Command
{
    /**
     * Artisan komut imzası.
     * store-id zorunlu — global anonymization güvenlik açısından yasak.
     * Dry-run varsayılan; gerçek işlem için --force zorunlu.
     */
    protected $signature = 'customer-care:anonymize
                            {--store-id= : Anonymize edilecek mağaza ID\'si (zorunlu)}
                            {--conversation-id= : Belirli bir konuşmayı anonymize et (opsiyonel)}
                            {--force : Gerçek anonymization başlat (varsayılan: dry-run)}';

    protected $description = 'KVKK kapsamında mağaza/konuşma PII anonymization — Varsayılan dry-run, gerçek işlem için --force';

    public function handle(CustomerCareAnonymizationService $service): int
    {
        $storeId = $this->option('store-id');
        $conversationId = $this->option('conversation-id');
        $force = $this->option('force');
        $dryRun = !$force;

        // Store ID zorunlu kontrol
        if (!$storeId) {
            $this->error('--store-id seçeneği zorunludur. Güvenlik: global anonymization desteklenmez.');
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('🔍 DRY-RUN modu: Gerçek veri değişikliği yapılmayacak.');
            $this->warn('   Gerçek anonymization için --force ekleyin.');
            $this->newLine();
        } else {
            $this->error('⚠️  GERÇEK ANONİMLEŞTİRME — Bu işlem geri alınamaz!');
            if (!$this->confirm('Devam etmek istediğinizden emin misiniz?')) {
                $this->info('İşlem iptal edildi.');
                return self::SUCCESS;
            }
        }

        try {
            if ($conversationId) {
                $result = $service->anonymizeConversation(
                    (int)$conversationId,
                    (int)$storeId,
                    $dryRun
                );
                $this->renderConversationResult($result, $dryRun);
            } else {
                $result = $service->anonymizeStore((int)$storeId, $dryRun);
                $this->renderStoreResult($result, $dryRun);
            }

            return self::SUCCESS;

        } catch (\InvalidArgumentException $e) {
            $this->error('Hata: ' . $e->getMessage());
            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Beklenmeyen hata: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function renderStoreResult(array $result, bool $dryRun): void
    {
        if ($dryRun) {
            $this->info('📋 Dry-run Raporu:');
            $this->table(
                ['Tablo', 'Etkilenecek Kayıt'],
                [
                    ['support_conversations', $result['would_anonymize']['support_conversations'] ?? 0],
                    ['support_messages (body)', $result['would_anonymize']['support_messages'] ?? 0],
                    ['wa_contacts (PII)', $result['would_anonymize']['wa_contacts'] ?? 0],
                    ['support_agent_actions (details)', $result['would_anonymize']['agent_actions_pii_fields'] ?? 0],
                ]
            );
            $this->warn('Audit ledger korunur: ' . ($result['audit_ledger_preserved'] ? 'EVET' : 'HAYIR'));
            $this->line($result['message'] ?? '');
        } else {
            $this->info('✅ Anonymization tamamlandı:');
            $this->table(
                ['İşlem', 'Sayı'],
                [
                    ['Konuşmalar işlendi', $result['conversations_processed'] ?? 0],
                    ['Mesaj body redakte', $result['messages_redacted'] ?? 0],
                    ['WaContact anonymize', $result['wa_contacts_anonymized'] ?? 0],
                    ['AgentAction redakte', $result['agent_actions_redacted'] ?? 0],
                ]
            );

            if (!empty($result['errors'])) {
                $this->error('Hatalar:');
                foreach ($result['errors'] as $error) {
                    $this->line('  - ' . $error);
                }
            }
        }
    }

    private function renderConversationResult(array $result, bool $dryRun): void
    {
        if ($dryRun) {
            $this->info("📋 Dry-run — Konuşma #{$result['conversation_id']}:");
            $this->line("  Redakte edilecek mesaj: {$result['would_redact_messages']}");
        } else {
            $this->info("✅ Konuşma #{$result['conversation_id']} anonymize edildi.");
            $this->line("  Redakte edilen mesaj: {$result['messages_redacted']}");
        }
    }
}
