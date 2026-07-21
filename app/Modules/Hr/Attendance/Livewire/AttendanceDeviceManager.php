<?php

namespace App\Modules\Hr\Attendance\Livewire;

use App\Modules\Hr\Attendance\Models\HrAttendanceDevice;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Component;

class AttendanceDeviceManager extends Component
{
    public ?int $editingId = null;
    public string $code = '';
    public string $name = '';
    public string $type = 'turnstile';
    public string $location = '';
    public bool $isActive = true;
    public ?string $issuedSecret = null;

    public function save(HrAuditService $audit): void
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.attendance.manage'), 403);
        $tenantId = app(TenantContext::class)->getId();
        $this->validate([
            'code' => 'required|string|max:50', 'name' => 'required|string|max:160',
            'type' => 'required|in:turnstile,qr,nfc,pin,api', 'location' => 'nullable|string|max:255', 'isActive' => 'boolean',
        ]);
        $duplicate = HrAttendanceDevice::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('code', strtoupper(trim($this->code)))->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))->exists();
        abort_if($duplicate, 422, 'Bu cihaz kodu zaten kullanılıyor.');

        $values = ['code' => strtoupper(trim($this->code)), 'name' => trim($this->name), 'type' => $this->type, 'location' => $this->location ?: null, 'is_active' => $this->isActive, 'updated_by' => auth()->id()];
        if ($this->editingId) {
            $device = HrAttendanceDevice::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->findOrFail($this->editingId);
            $device->update($values);
            $audit->log('attendance_device_updated', $device, null, $values);
        } else {
            $this->issuedSecret = Str::random(48);
            $device = HrAttendanceDevice::create($values + ['legal_entity_id' => $tenantId, 'secret_hash' => Hash::make($this->issuedSecret), 'created_by' => auth()->id()]);
            $audit->log('attendance_device_created', $device, null, ['code' => $device->code, 'type' => $device->type]);
        }
        $this->resetForm(keepSecret: true);
        session()->flash('success', 'PDKS cihazı kaydedildi.');
    }

    public function edit(int $id): void
    {
        $device = HrAttendanceDevice::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->findOrFail($id);
        $this->editingId = $device->id; $this->code = $device->code; $this->name = $device->name; $this->type = $device->type; $this->location = $device->location ?? ''; $this->isActive = $device->is_active; $this->issuedSecret = null;
    }

    public function regenerateSecret(int $id, HrAuditService $audit): void
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.attendance.manage'), 403);
        $device = HrAttendanceDevice::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->findOrFail($id);
        $this->issuedSecret = Str::random(48);
        $device->update(['secret_hash' => Hash::make($this->issuedSecret), 'updated_by' => auth()->id()]);
        $audit->log('attendance_device_secret_rotated', $device, null, ['code' => $device->code]);
        session()->flash('success', 'Cihaz anahtarı yenilendi. Eski anahtar artık geçersiz.');
    }

    public function resetForm(bool $keepSecret = false): void
    {
        $secret = $keepSecret ? $this->issuedSecret : null;
        $this->reset(['editingId', 'code', 'name', 'location']);
        $this->type = 'turnstile'; $this->isActive = true; $this->issuedSecret = $secret;
    }

    public function render()
    {
        return view('livewire.hr.attendance.attendance-device-manager', [
            'devices' => HrAttendanceDevice::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->orderBy('name')->get(),
        ])->layout('layouts.app');
    }
}
