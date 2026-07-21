<?php

namespace Tests\Feature\Hr;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HrPhase6DSupportSafetyRollbackTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_support_and_safety_schema_exists(): void
    {
        $this->assertTrue(Schema::hasColumns('hr_support_tickets', ['ticket_number', 'description_encrypted', 'status', 'assigned_to']));
        $this->assertTrue(Schema::hasColumns('hr_support_messages', ['body_encrypted', 'is_internal']));
        $this->assertTrue(Schema::hasColumns('hr_health_records', ['result_encrypted', 'details_encrypted', 'expires_on']));
        $this->assertTrue(Schema::hasColumns('hr_safety_incidents', ['incident_number', 'description_encrypted', 'source_hash', 'closed_at']));
        $this->assertTrue(Schema::hasColumns('hr_safety_actions', ['due_on', 'status', 'completion_evidence_encrypted']));
    }

    public function test_rollback_preserves_workforce_planning(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 1]);Artisan::call('migrate:rollback', ['--step' => 1]);
        $this->assertFalse(Schema::hasTable('hr_support_tickets'));
        $this->assertFalse(Schema::hasTable('hr_safety_incidents'));
        $this->assertTrue(Schema::hasTable('hr_workforce_plans'));
    }
}
