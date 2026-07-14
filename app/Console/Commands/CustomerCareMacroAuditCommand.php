<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SupportReplyMacro;
use App\Services\Support\Security\PiiRedactor;
use App\Services\Support\Policy\SupportPolicyEngine;

class CustomerCareMacroAuditCommand extends Command
{
    protected $signature = 'customer-care:macro-audit {--store= : Mağaza ID} {--dry-run : Veri kaydı yapmadan sadece raporlar}';
    protected $description = 'Mağaza yanıt makrolarını politika ihlalleri, prompt injection ve PII sızıntısı açısından denetler';

    public function handle(): int
    {
        $storeId = $this->option('store');
        $dryRun = $this->option('dry-run');

        if (!$storeId) {
            $this->error("Mağaza ID belirtmek zorunludur. Örn: --store=1");
            return 1;
        }

        $macros = SupportReplyMacro::where('store_id', $storeId)->get();

        if ($macros->isEmpty()) {
            $this->info("Denetlenecek makro bulunamadı.");
            return 0;
        }

        $redactor = app(PiiRedactor::class);
        $this->info("Toplam {$macros->count()} makro denetleniyor...");
        $issuesFound = 0;

        foreach ($macros as $macro) {
            $this->line("--------------------------------------------------");
            $this->line("Makro ID: {$macro->id} | Başlık: {$macro->title}");

            $hasIssue = false;

            // 1. PII Kontrolü
            $masked = $redactor->maskPii($macro->body);
            if ($masked !== $macro->body) {
                $this->warn("[HATA/PII SIZINTISI] Makro içeriğinde TCKN, telefon veya e-posta tespit edildi!");
                $hasIssue = true;
            }

            // 2. Prompt Injection Kontrolü
            $injectionKeywords = ['ignore previous', 'system prompt', 'forget instructions', 'you are now', 'talimatları unut'];
            foreach ($injectionKeywords as $keyword) {
                if (str_contains(strtolower($macro->body), $keyword)) {
                    $this->warn("[HATA/PROMPT INJECTION] Potansiyel prompt injection ifadesi tespit edildi: '{$keyword}'");
                    $hasIssue = true;
                }
            }

            // 3. Politika İhlali Kontrolü (N11, Hepsiburada, Link vb.)
            $forbiddenKeywords = ['kapida odeme', 'havale', 'iban', 'n11', 'trendyol', 'hepsiburada', 'amazon'];
            if ($macro->channel_scope) {
                // Kanala göre yasaklı kelimeleri kontrol et
                foreach ($forbiddenKeywords as $keyword) {
                    if (str_contains(strtolower($macro->body), $keyword) && $macro->channel_scope !== 'whatsapp') {
                        $this->warn("[UYARI/POLİTİKA İHLALİ] Kanalı ({$macro->channel_scope}) olan makroda yasaklı kelime tespit edildi: '{$keyword}'");
                        $hasIssue = true;
                    }
                }
            }

            if ($hasIssue) {
                $issuesFound++;
                if (!$dryRun) {
                    // is_active = false yapalım (dry-run değilse kapat)
                    $macro->update(['is_active' => false]);
                    $this->info("Makro pasifleştirildi.");
                }
            } else {
                $this->info("[TEMİZ] Makro denetimden başarıyla geçti.");
            }
        }

        $this->line("==================================================");
        $this->info("Denetim bitti. Toplam sorunlu makro sayısı: {$issuesFound}");

        return 0;
    }
}
