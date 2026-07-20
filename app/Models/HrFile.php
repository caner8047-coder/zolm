<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrFile extends Model
{
    protected $fillable = [
        'legal_entity_id',
        'uploader_id',
        'subject_type',
        'subject_id',
        'category',
        'original_name',
        'disk_path',
        'mime_type',
        'size_bytes',
        'checksum',
        'is_verified',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function subject()
    {
        return $this->morphTo();
    }
}
