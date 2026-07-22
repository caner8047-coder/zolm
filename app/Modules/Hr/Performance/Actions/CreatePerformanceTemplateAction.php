<?php

namespace App\Modules\Hr\Performance\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Performance\Models\HrPerformanceTemplate;
use App\Modules\Hr\Performance\Services\PerformanceQuestionnaireService;

class CreatePerformanceTemplateAction
{
    public function __construct(private HrAuditService $audit, private PerformanceQuestionnaireService $questionnaire) {}

    public function execute(string $name, array $sections): HrPerformanceTemplate
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.performance.manage_templates'), 403);
        abort_if(blank($name), 422, 'Şablon adı zorunludur.');
        $sections = $this->questionnaire->normalize($sections);
        $tenant = app(TenantContext::class)->getId();
        $version = (int) HrPerformanceTemplate::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenant)->where('name', trim($name))->max('version') + 1;
        $template = HrPerformanceTemplate::create([
            'legal_entity_id' => $tenant, 'name' => trim($name), 'version' => $version,
            'sections' => $sections, 'is_active' => true, 'created_by' => auth()->id(),
        ]);
        $this->audit->log('performance_template_created', $template, null, ['version' => $version]);

        return $template;
    }
}
