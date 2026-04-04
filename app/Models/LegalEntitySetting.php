<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalEntitySetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'legal_entity_id',
        'settings_json',
    ];

    protected function casts(): array
    {
        return [
            'settings_json' => 'array',
        ];
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }
}
