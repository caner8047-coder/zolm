<?php

namespace Tests\Feature;

use App\Models\ReturnIntakeBatch;
use App\Models\ReturnIntakeItem;
use App\Models\User;
use App\Services\Returns\ReturnVisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnVisionServiceLocalFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_extracts_tracking_and_cargo_from_local_ocr_when_ai_is_unavailable(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $batch = ReturnIntakeBatch::create([
            'user_id' => $user->id,
            'source' => 'zolm_mobile',
            'intake_mode' => 'undamaged',
            'status' => 'submitted',
            'captured_at' => now(),
        ]);

        $item = ReturnIntakeItem::create([
            'batch_id' => $batch->id,
            'submitted_by_user_id' => $user->id,
            'intake_type' => 'undamaged',
            'intake_status' => 'queued',
            'condition_status' => 'undamaged',
            'decision_status' => 'pending',
            'arrived_at' => now(),
        ]);

        $service = new class extends ReturnVisionService
        {
            protected function resolveGeminiConfig(): ?array
            {
                return null;
            }

            protected function collectLocalOcrTexts(ReturnIntakeItem $item): array
            {
                return [
                    'engine' => 'tesseract',
                    'texts' => [
                        "Süratkargo TF-114504168216\nPAKET 20\nSUMER/04 DENIZLI AKTARMA",
                    ],
                ];
            }
        };

        $analysis = $service->analyze($item);

        $this->assertSame('local_tesseract', $analysis['provider']);
        $this->assertSame('TF-114504168216', data_get($analysis, 'ocr.tracking_number'));
        $this->assertSame('Sürat Kargo', data_get($analysis, 'ocr.cargo_provider'));
        $this->assertNull(data_get($analysis, 'ocr.customer_name'));
        $this->assertGreaterThan(0.5, (float) ($analysis['confidence'] ?? 0));
        $this->assertStringContainsString('Yerel OCR', (string) data_get($analysis, 'classification.summary'));
    }

    public function test_it_prefers_t_no_tracking_label_over_tf_code_when_both_exist(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $batch = ReturnIntakeBatch::create([
            'user_id' => $user->id,
            'source' => 'zolm_mobile',
            'intake_mode' => 'undamaged',
            'status' => 'submitted',
            'captured_at' => now(),
        ]);

        $item = ReturnIntakeItem::create([
            'batch_id' => $batch->id,
            'submitted_by_user_id' => $user->id,
            'intake_type' => 'undamaged',
            'intake_status' => 'queued',
            'condition_status' => 'undamaged',
            'decision_status' => 'pending',
            'arrived_at' => now(),
        ]);

        $service = new class extends ReturnVisionService
        {
            protected function resolveGeminiConfig(): ?array
            {
                return null;
            }

            protected function collectLocalOcrTexts(ReturnIntakeItem $item): array
            {
                return [
                    'engine' => 'tesseract',
                    'texts' => [
                        "Süratkargo TF-114504168216\nT. No: 2371124923721\nPAKET 20\nSUMER/04 DENIZLI AKTARMA",
                    ],
                ];
            }
        };

        $analysis = $service->analyze($item);

        $this->assertSame('2371124923721', data_get($analysis, 'ocr.tracking_number'));
        $this->assertSame('Sürat Kargo', data_get($analysis, 'ocr.cargo_provider'));
    }

    public function test_it_collapses_likely_ocr_duplicate_digits_for_t_no_tracking(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $batch = ReturnIntakeBatch::create([
            'user_id' => $user->id,
            'source' => 'zolm_mobile',
            'intake_mode' => 'undamaged',
            'status' => 'submitted',
            'captured_at' => now(),
        ]);

        $item = ReturnIntakeItem::create([
            'batch_id' => $batch->id,
            'submitted_by_user_id' => $user->id,
            'intake_type' => 'undamaged',
            'intake_status' => 'queued',
            'condition_status' => 'undamaged',
            'decision_status' => 'pending',
            'arrived_at' => now(),
        ]);

        $service = new class extends ReturnVisionService
        {
            protected function resolveGeminiConfig(): ?array
            {
                return null;
            }

            protected function collectLocalOcrTexts(ReturnIntakeItem $item): array
            {
                return [
                    'engine' => 'tesseract',
                    'texts' => [
                        "Süratkargo TF-114504168216\nT. No: 23711124923721\nPAKET 20\nSUMER/04 DENIZLI AKTARMA",
                    ],
                ];
            }
        };

        $analysis = $service->analyze($item);

        $this->assertSame('2371124923721', data_get($analysis, 'ocr.tracking_number'));
    }
}
