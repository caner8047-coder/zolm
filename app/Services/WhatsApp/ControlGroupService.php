<?php

namespace App\Services\WhatsApp;

use App\Models\WaContact;
use App\Models\WaControlGroup;
use App\Models\WaControlGroupMember;

class ControlGroupService
{
    /**
     * Kontrol grubu oluştur
     */
    public function createGroup(array $data): WaControlGroup
    {
        return WaControlGroup::create([
            'store_id' => $data['store_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sample_percentage' => $data['sample_percentage'] ?? 10,
            'criteria_json' => $data['criteria'] ?? null,
            'is_active' => true,
        ]);
    }

    /**
     * Müşteriyi kontrol grubuna kaydet
     */
    public function enroll(WaControlGroup $group, WaContact $contact): bool
    {
        $existing = WaControlGroupMember::where('group_id', $group->id)
            ->where('contact_id', $contact->id)
            ->exists();

        if ($existing) {
            return false;
        }

        WaControlGroupMember::create([
            'group_id' => $group->id,
            'contact_id' => $contact->id,
            'store_id' => $contact->store_id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $group->increment('current_enrolled');

        return true;
    }

    /**
     * Müşteri kontrol grubunda mı?
     */
    public function isInControlGroup(WaContact $contact): bool
    {
        return WaControlGroupMember::where('contact_id', $contact->id)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Kontrol grubu raporu
     */
    public function getReport(WaControlGroup $group): array
    {
        $members = $group->members()->where('status', 'active')->count();

        return [
            'group' => [
                'name' => $group->name,
                'sample_percentage' => $group->sample_percentage,
                'current_enrolled' => $members,
            ],
        ];
    }
}
