<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionRevenueImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'filename',
        'source_hash',
        'created_rows',
        'updated_rows',
        'unchanged_rows',
        'skipped_rows',
        'sheet_count',
        'imported_at',
        'months',
        'meta',
    ];

    protected $casts = [
        'months' => 'array',
        'meta' => 'array',
        'imported_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ProductionRevenueEntry::class);
    }
}
