<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdImportRow extends Model
{
    protected $fillable = [
        'batch_id',
        'row_number',
        'raw_payload',
        'normalized_payload',
        'validation_errors',
        'status',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'normalized_payload' => 'array',
        'validation_errors' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AdImportBatch::class, 'batch_id');
    }

    public function isValid(): bool
    {
        return $this->status === 'valid';
    }

    public function hasErrors(): bool
    {
        return $this->status === 'error';
    }
}
