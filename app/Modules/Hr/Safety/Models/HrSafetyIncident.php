<?php

namespace App\Modules\Hr\Safety\Models;

use App\Models\User;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrSafetyIncident extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = [
        'legal_entity_id', 'incident_number', 'reporter_employee_id', 'affected_employee_id',
        'incident_type', 'severity', 'occurred_at', 'location', 'description_encrypted',
        'immediate_action_encrypted', 'lost_time', 'status', 'assigned_to', 'source_hash',
        'closed_by', 'closed_at',
    ];

    protected $hidden = ['description_encrypted', 'immediate_action_encrypted'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'description_encrypted' => 'encrypted',
            'immediate_action_encrypted' => 'encrypted',
            'lost_time' => 'boolean',
            'closed_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'reporter_employee_id');
    }

    public function affectedEmployee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'affected_employee_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(HrSafetyAction::class, 'safety_incident_id');
    }

    public function description(): string
    {
        return (string) $this->description_encrypted;
    }

    public function immediateAction(): ?string
    {
        return $this->immediate_action_encrypted;
    }
}
