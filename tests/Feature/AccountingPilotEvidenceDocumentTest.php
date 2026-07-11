<?php

namespace Tests\Feature;

use Tests\TestCase;

class AccountingPilotEvidenceDocumentTest extends TestCase
{
    public function test_release_evidence_document_exists(): void
    {
        $this->assertFileExists(base_path('docs/accounting-pilot-release-evidence.md'));
    }

    public function test_release_evidence_document_has_required_sections(): void
    {
        $content = file_get_contents(base_path('docs/accounting-pilot-release-evidence.md'));

        $this->assertStringContainsString('Release Kimliği', $content);
        $this->assertStringContainsString('Çalışma Ağacı Durumu', $content);
        $this->assertStringContainsString('Kod Kalite Kontrolü', $content);
        $this->assertStringContainsString('Release Checker Çıktısı', $content);
        $this->assertStringContainsString('Smoke Test Çıktısı', $content);
        $this->assertStringContainsString('Test Sonuçları', $content);
        $this->assertStringContainsString('Release Kararı', $content);
        $this->assertStringContainsString('Rollback Tatbikatı Kaydı', $content);
    }

    public function test_smoke_evidence_template_contains_18_route_checklist(): void
    {
        $content = file_get_contents(base_path('docs/accounting-smoke-test-evidence-template.md'));

        $this->assertStringContainsString('accounting.dashboard', $content);
        $this->assertStringContainsString('accounting.chart-of-accounts', $content);
        $this->assertStringContainsString('accounting.products', $content);
        $this->assertStringContainsString('accounting.audit-logs', $content);
        $this->assertStringContainsString('accounting.pilot-center', $content);
    }

    public function test_release_checklist_mentions_p21_evidence_pack(): void
    {
        $content = file_get_contents(base_path('docs/accounting-release-checklist.md'));

        $this->assertStringContainsString('P21', $content);
        $this->assertStringContainsString('accounting-pilot-release-evidence.md', $content);
        $this->assertStringContainsString('Release checker JSON', $content);
        $this->assertStringContainsString('Smoke test JSON', $content);
        $this->assertStringContainsString('Rollback tatbikat', $content);
    }

    public function test_release_evidence_contains_git_commit_and_decision(): void
    {
        $content = file_get_contents(base_path('docs/accounting-pilot-release-evidence.md'));

        $this->assertStringContainsStringIgnoringCase('Commit hash', $content);
        $this->assertStringContainsStringIgnoringCase('Pilot release kararı', $content);

        $hasValidDecision = str_contains($content, 'Ready') || 
                             str_contains($content, 'Ready with warnings') || 
                             str_contains($content, 'Blocked');
        
        $this->assertTrue($hasValidDecision, 'Evidence should contain release decision (Ready, Ready with warnings, or Blocked)');
    }
}
