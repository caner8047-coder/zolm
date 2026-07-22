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
        return $this->belongsToCurrentTenant($document)
            && $this->checkPermission($user, 'hr.documents.view')
            && $this->canAccessProtectedContent($user, $document);
    }

    public function download(User $user, HrEmployeeDocument $document): bool
    {
        return $this->belongsToCurrentTenant($document)
            && $this->checkPermission($user, 'hr.documents.download')
            && $this->canAccessProtectedContent($user, $document);
    }

    public function verify(User $user, HrEmployeeDocument $document): bool
    {
        return $this->belongsToCurrentTenant($document)
            && $this->checkPermission($user, 'hr.documents.verify')
            && $this->canAccessProtectedContent($user, $document);
    }

    public function upload(User $user): bool
    {
        return $this->checkPermission($user, 'hr.documents.create');
    }

    public function uploadVersion(User $user, HrEmployeeDocument $document): bool
    {
        return $this->belongsToCurrentTenant($document)
            && $this->checkPermission($user, 'hr.documents.create')
            && $this->canAccessProtectedContent($user, $document);
    }

    public function archive(User $user, HrEmployeeDocument $document): bool
    {
        return $this->belongsToCurrentTenant($document)
            && $this->checkPermission($user, 'hr.documents.archive')
            && $this->canAccessProtectedContent($user, $document);
    }

    private function belongsToCurrentTenant(HrEmployeeDocument $document): bool
    {
        return $document->legal_entity_id === app(TenantContext::class)->getId();
    }

    private function canAccessProtectedContent(User $user, HrEmployeeDocument $document): bool
    {
        $type = $document->documentType;

        if ($type?->sensitivity?->value === 'highly_sensitive'
            && ! $this->checkPermission($user, 'hr.documents.view_sensitive')) {
            return false;
        }

        if ($type?->category?->value === 'health'
            && ! $this->checkPermission($user, 'hr.documents.view_health')) {
            return false;
        }

        return true;
    }
}
