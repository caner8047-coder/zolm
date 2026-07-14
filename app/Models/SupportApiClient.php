<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportApiClient extends Model
{
    protected $fillable = [
        'legal_entity_id', 'name', 'client_id', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class, 'legal_entity_id');
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(SupportApiToken::class, 'api_client_id');
    }
}
