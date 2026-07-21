<?php

namespace Tests\Feature\Hr;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HrPhase8AAssistantRollbackTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_assistant_schema_exists(): void
    {
        $this->assertTrue(Schema::hasColumns('hr_assistant_queries', ['query_encrypted', 'intent', 'response_encrypted', 'sources', 'answered_at']));
    }

    public function test_rollback_preserves_support_and_safety(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 1]);
        $this->assertFalse(Schema::hasTable('hr_assistant_queries'));
        $this->assertTrue(Schema::hasTable('hr_support_tickets'));
        $this->assertTrue(Schema::hasTable('hr_safety_incidents'));
    }
}
