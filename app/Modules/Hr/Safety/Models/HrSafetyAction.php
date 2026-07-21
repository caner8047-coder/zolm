<?php

namespace App\Modules\Hr\Safety\Models;

use App\Models\User;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrSafetyAction extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = [
        'legal_entity_id', 'safety_incident_id', 'title', 'owner_user_id', 'due_on',
        'status', 'completion_evidence_encrypted', 'completed_by', 'completed_at',
    ];

    protected $hidden = ['completion_evidence_encrypted'];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'completion_evidence_encrypted' => 'encrypted',
            'completed_at' => 'datetime',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(HrSafetyIncident::class, 'safety_incident_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function evidence(): ?string
    {
        return $this->completion_evidence_encrypted;
    }
}
