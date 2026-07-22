<?php

namespace Tests\Unit\Hr;

use App\Modules\Hr\Document\Services\HrPersonnelFileChecklistService;
use Tests\TestCase;

class HrPersonnelFileChecklistServiceTest extends TestCase
{
    public function test_personnel_file_checklist_service_analyzes_required_documents(): void
    {
        $service = new HrPersonnelFileChecklistService();
        $res = $service->analyzeEmployeeFile(1, 1);

        $this->assertEquals(8, $res['total_required']);
        $this->assertIsArray($res['checklist']);
        $this->assertArrayHasKey('completion_rate', $res);
        $this->assertArrayHasKey('missing_count', $res);
    }
}
