<?php

namespace App\Modules\Hr\Core\Services;

use App\Models\LegalEntity;
use Illuminate\Support\Facades\Session;

class TenantContext
{
    private ?LegalEntity $tenant = null;

    public function set(LegalEntity $tenant): void
    {
        $this->tenant = $tenant;
        Session::put('hr_tenant_id', $tenant->id);
    }

    public function get(): LegalEntity
    {
        if (!$this->tenant) {
            $id = Session::get('hr_tenant_id');
            if ($id) {
                $this->tenant = LegalEntity::find($id);
            }
        }

        abort_unless($this->tenant, 403, 'Aktif tüzel kişilik bulunamadı.');

        return $this->tenant;
    }

    public function getId(): int
    {
        return $this->get()->id;
    }

    public function clear(): void
    {
        $this->tenant = null;
        Session::forget('hr_tenant_id');
    }

    public function isSet(): bool
    {
        return $this->tenant !== null;
    }
}
