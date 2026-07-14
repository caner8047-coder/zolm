<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportOrganizationSetting extends Model
{
    protected $fillable = [
        'legal_entity_id', 'system_actor_email', 'security_policy',
    ];

    protected $casts = [
        'security_policy' => 'array',
    ];

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class, 'legal_entity_id');
    }
}
