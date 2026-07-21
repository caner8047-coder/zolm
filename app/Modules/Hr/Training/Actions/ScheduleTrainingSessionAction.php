<?php

namespace App\Modules\Hr\Training\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Training\Models\HrTrainingCourse;
use App\Modules\Hr\Training\Models\HrTrainingSession;
use Carbon\Carbon;

class ScheduleTrainingSessionAction
{
    public function __construct(private HrAuditService $audit) {}

    public function execute(HrTrainingCourse $course, array $data): HrTrainingSession
    {
        $tenant = app(TenantContext::class)->getId();
        abort_unless(auth()->user()?->hasHrPermission('hr.training.manage'), 403);
        abort_unless($course->legal_entity_id === $tenant && $course->is_active, 404);
        abort_if(blank($data['starts_at'] ?? null) || blank($data['ends_at'] ?? null), 422);

        $start = Carbon::parse($data['starts_at']);
        $end = Carbon::parse($data['ends_at']);
        $capacity = blank($data['capacity'] ?? null) ? null : (int) $data['capacity'];
        abort_if($end->lte($start) || ($capacity !== null && $capacity < 1), 422, 'Oturum zamanı veya kapasitesi geçersiz.');

        $requestedType = (string) ($data['delivery_type'] ?? 'classroom');
        $type = in_array($requestedType, ['classroom', 'online', 'hybrid'], true) ? $requestedType : 'classroom';
        $session = HrTrainingSession::create([
            'legal_entity_id' => $tenant,
            'course_id' => $course->id,
            'delivery_type' => $type,
            'instructor' => $data['instructor'] ?? null,
            'location' => $data['location'] ?? null,
            'starts_at' => $start,
            'ends_at' => $end,
            'capacity' => $capacity,
            'status' => 'scheduled',
            'created_by' => auth()->id(),
        ]);
        $this->audit->log('training_session_scheduled', $session);

        return $session;
    }
}
