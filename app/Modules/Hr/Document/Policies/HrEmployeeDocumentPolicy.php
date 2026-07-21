<?php

namespace App\Modules\Hr\Document\Policies;

use App\Models\User;
use App\Modules\Hr\Core\Policies\HrBasePolicy;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;

class HrEmployeeDocumentPolicy extends HrBasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'hr.documents.view');
    }

    public function view(User $user, HrEmployeeDocument $document): bool
    {
        if ($document->legal_entity_id !== app(TenantContext::class)->getId()) {
            return false;
        }

        // Hassas belge için ek izin
        if ($document->documentType && $document->documentType->sensitivity->value === 'highly_sensitive') {
            return $this->checkPermission($user, 'hr.documents.view_sensitive');
        }

        if ($document->documentType && $document->documentType->category->value === 'health') {
            return $this->checkPermission($user, 'hr.documents.view_health');
        }

        return $this->checkPermission($user, 'hr.documents.view');
    }

    public function download(User $user, HrEmployeeDocument $document): bool
    {
        if ($document->legal_entity_id !== app(TenantContext::class)->getId()) {
            return false;
        }

        return $this->checkPermission($user, 'hr.documents.download');
    }

    public function verify(User $user, HrEmployeeDocument $document): bool
    {
        if ($document->legal_entity_id !== app(TenantContext::class)->getId()) {
            return false;
        }

        return $this->checkPermission($user, 'hr.documents.verify');
    }

    public function upload(User $user): bool
    {
        return $this->checkPermission($user, 'hr.documents.create');
    }
}
