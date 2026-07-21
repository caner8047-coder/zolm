<?php
namespace App\Modules\Hr\Training\Services;
use App\Modules\Hr\Training\Models\HrCertificate;
class TrainingEligibilityService { public function hasValidCertificate(int $tenantId,int $employeeId,?int $courseId,string $onDate):bool{if(!$courseId)return true;return HrCertificate::withoutGlobalScope('tenant')->where('legal_entity_id',$tenantId)->where('employee_id',$employeeId)->where('course_id',$courseId)->where('status','valid')->whereDate('issued_on','<=',$onDate)->where(fn($q)=>$q->whereNull('expires_on')->orWhereDate('expires_on','>=',$onDate))->exists();} }
