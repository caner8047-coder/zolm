<?php

namespace Tests\Feature\Hr;

use App\Models\HrFile;
use App\Modules\Hr\Document\Models\HrDocumentRequirement;
use App\Modules\Hr\Document\Models\HrEmployeeDocumentVersion;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Tests\Feature\Hr\RefreshHrDatabase;
use Tests\TestCase;

class HrDocumentFactoryTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_hr_employee_factory_creates_valid_record(): void
    {
        $emp = HrEmployee::factory()->create();
        $this->assertNotNull($emp->id);
        $this->assertNotEmpty($emp->employee_number);
    }

    public function test_hr_file_factory_creates_valid_record(): void
    {
        $file = HrFile::factory()->create();
        $this->assertNotNull($file->id);
        $this->assertNotEmpty($file->checksum);
    }

    public function test_document_requirement_factory_creates_valid_record(): void
    {
        $req = HrDocumentRequirement::factory()->create();
        $this->assertTrue($req->is_required);
        $this->assertNotNull($req->document_type_id);
    }

    public function test_employee_document_version_factory_creates_valid_record(): void
    {
        $version = HrEmployeeDocumentVersion::factory()->create();
        $this->assertNotNull($version->id);
        $this->assertGreaterThanOrEqual(1, $version->version_number);
    }
}
